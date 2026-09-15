<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Passkey;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use lbuchs\WebAuthn\WebAuthn;
use Symfony\Component\HttpFoundation\Request;

/**
 * The WebAuthn ceremonies for a customer's passkey: pairing a new key and signing in
 * with it as the second factor after the password.
 *
 * A passkey is the one second factor that phishing cannot relay: the signature covers the
 * address of the site, so a look-alike page never gets a valid one. Six digits can be retyped
 * into a fake form; a passkey cannot.
 *
 * Built on lbuchs/webauthn, which has no dependencies — a plugin must not drag libraries into
 * a shop that change how the rest of it behaves. Challenges live in the session and are used
 * once: a failed attempt burns the challenge too.
 */
final class PasskeyCeremony
{
    private const SESSION_REGISTER = 'calmfox_shop_two_factor.passkey.register';

    private const SESSION_LOGIN = 'calmfox_shop_two_factor.passkey.login';

    /** Seconds the browser waits for the gesture. Face ID is sometimes preceded by looking for the phone. */
    private const TIMEOUT = 120;

    public function __construct(
        private readonly string $rpName,
        private readonly ?string $rpId = null,
    ) {
    }

    /** @return array<string, mixed> publicKey options for navigator.credentials.create() */
    public function registrationOptions(TwoFactorShopUserInterface $shopUser, Request $request): array
    {
        $webAuthn = $this->webAuthn($request);
        $email = (string) $shopUser->getEmail();

        $args = self::publicKey($webAuthn->getCreateArgs(
            self::userHandle($shopUser),
            $email,
            trim((string) $shopUser->getCustomer()?->getFullName()) ?: $email,
            self::TIMEOUT,
            'preferred',
            true,
            null,
            // the same device must not pair a second key with the same account
            $this->credentialIds($shopUser),
        ));
        // no attestation: which device model the customer uses is none of our business
        $args->attestation = 'none';

        $request->getSession()->set(self::SESSION_REGISTER, self::challenge($webAuthn));

        return self::toArray($args);
    }

    /**
     * Verifies the response of navigator.credentials.create() and adds the key to the account.
     * The caller flushes.
     *
     * @param array<string, mixed> $response
     *
     * @throws PasskeyException
     */
    public function register(TwoFactorShopUserInterface $shopUser, Request $request, array $response, string $name): void
    {
        $challenge = $this->pullChallenge($request, self::SESSION_REGISTER);
        $clientDataJson = self::field($response, 'clientDataJSON');
        $this->assertOrigin($request, $clientDataJson);

        try {
            $data = $this->webAuthn($request)->processCreate(
                $clientDataJson,
                self::field($response, 'attestationObject'),
                $challenge,
                true,  // user verification: fingerprint, face or PIN
                true,
                false, // no attestation roots: we asked for none
            );
        } catch (\Throwable $exception) {
            throw PasskeyException::rejected($exception);
        }

        if (!is_string($data->credentialId ?? null) || !is_string($data->credentialPublicKey ?? null)) {
            throw PasskeyException::rejected();
        }

        $id = self::encode($data->credentialId);
        $credentials = $shopUser->getPasskeyCredentials();
        foreach ($credentials as $credential) {
            if ($credential['id'] === $id) {
                throw PasskeyException::alreadyPaired();
            }
        }

        $credentials[] = [
            'id' => $id,
            'name' => mb_substr('' === trim($name) ? 'Passkey' : trim($name), 0, 64),
            'publicKey' => $data->credentialPublicKey,
            'signCount' => is_int($data->signatureCounter ?? null) ? $data->signatureCounter : 0,
            'createdAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'lastUsedAt' => null,
        ];
        $shopUser->setPasskeyCredentials($credentials);
    }

    /** @return array<string, mixed> publicKey options for navigator.credentials.get(), limited to this customer's keys */
    public function loginOptions(TwoFactorShopUserInterface $shopUser, Request $request): array
    {
        $webAuthn = $this->webAuthn($request);

        $args = self::publicKey($webAuthn->getGetArgs(
            $this->credentialIds($shopUser),
            self::TIMEOUT,
            true,
            true,
            true,
            true,
            true,
            true, // user verification required
        ));

        $request->getSession()->set(self::SESSION_LOGIN, self::challenge($webAuthn));

        return self::toArray($args);
    }

    /**
     * Verifies the response of navigator.credentials.get() against this customer's keys.
     * The caller flushes (the key's usage data changes).
     *
     * @param array<string, mixed> $response
     *
     * @throws PasskeyException
     */
    public function verifyLogin(TwoFactorShopUserInterface $shopUser, Request $request, array $response): void
    {
        $challenge = $this->pullChallenge($request, self::SESSION_LOGIN);

        $id = is_string($response['rawId'] ?? null) ? $response['rawId'] : '';
        $credentials = $shopUser->getPasskeyCredentials();
        $index = null;
        foreach ($credentials as $i => $credential) {
            if (hash_equals($credential['id'], $id)) {
                $index = $i;

                break;
            }
        }
        // a key from another account never completes this one's login
        if (null === $index) {
            throw PasskeyException::rejected();
        }

        $userHandle = self::responseField($response, 'userHandle');
        if (is_string($userHandle) && '' !== $userHandle && self::decode($userHandle) !== self::userHandle($shopUser)) {
            throw PasskeyException::rejected();
        }

        $clientDataJson = self::field($response, 'clientDataJSON');
        $this->assertOrigin($request, $clientDataJson);

        $webAuthn = $this->webAuthn($request);

        try {
            $webAuthn->processGet(
                $clientDataJson,
                self::field($response, 'authenticatorData'),
                self::field($response, 'signature'),
                $credentials[$index]['publicKey'],
                $challenge,
                $credentials[$index]['signCount'],
                true,
                true,
            );
        } catch (\Throwable $exception) {
            throw PasskeyException::rejected($exception);
        }

        $used = $credentials[$index];
        $counter = $webAuthn->getSignatureCounter();
        $credentials[$index] = [
            'id' => $used['id'],
            'name' => $used['name'],
            'publicKey' => $used['publicKey'],
            'signCount' => is_int($counter) ? $counter : $used['signCount'],
            'createdAt' => $used['createdAt'],
            'lastUsedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
        $shopUser->setPasskeyCredentials($credentials);
    }

    /** @return list<string> binary ids of the account's passkeys */
    private function credentialIds(TwoFactorShopUserInterface $shopUser): array
    {
        return array_map(static fn (array $credential): string => self::decode($credential['id']), $shopUser->getPasskeyCredentials());
    }

    /** @throws PasskeyException */
    private static function publicKey(mixed $args): \stdClass
    {
        if (!$args instanceof \stdClass || !$args->publicKey instanceof \stdClass) {
            throw PasskeyException::rejected();
        }

        return $args->publicKey;
    }

    private static function challenge(WebAuthn $webAuthn): string
    {
        return base64_encode($webAuthn->getChallenge()->getBinaryString());
    }

    /** The WebAuthn user handle: the account id, never the e-mail (it must not identify the person). */
    private static function userHandle(TwoFactorShopUserInterface $shopUser): string
    {
        $id = $shopUser->getId();

        return is_int($id) || is_string($id) ? (string) $id : '';
    }

    private function webAuthn(Request $request): WebAuthn
    {
        return new WebAuthn($this->rpName, $this->rpId($request), null, true);
    }

    private function rpId(Request $request): string
    {
        return $this->rpId ?? $request->getHost();
    }

    /**
     * The library accepts any host that merely ends with the RP ID; we want the exact site or
     * its real subdomain, served over https (plain http only on localhost, as browsers allow).
     *
     * @throws PasskeyException
     */
    private function assertOrigin(Request $request, string $clientDataJson): void
    {
        $clientData = json_decode($clientDataJson, true);
        $origin = is_array($clientData) && is_string($clientData['origin'] ?? null) ? $clientData['origin'] : '';
        $host = (string) parse_url($origin, \PHP_URL_HOST);
        $scheme = (string) parse_url($origin, \PHP_URL_SCHEME);
        $rpId = $this->rpId($request);

        $hostMatches = 0 === strcasecmp($host, $rpId) || str_ends_with(strtolower($host), '.' . strtolower($rpId));
        $schemeAllowed = 'https' === $scheme || ('http' === $scheme && in_array($host, ['localhost', '127.0.0.1'], true));

        if (!$hostMatches || !$schemeAllowed) {
            throw PasskeyException::rejected();
        }
    }

    /** @throws PasskeyException */
    private function pullChallenge(Request $request, string $key): string
    {
        $session = $request->getSession();
        $challenge = $session->get($key);
        $session->remove($key);

        $binary = is_string($challenge) ? base64_decode($challenge, true) : false;
        if (false === $binary || '' === $binary) {
            throw PasskeyException::challengeGone();
        }

        return $binary;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @throws PasskeyException
     */
    private static function field(array $response, string $name): string
    {
        $value = self::responseField($response, $name);
        if (null === $value || '' === $value) {
            throw PasskeyException::rejected();
        }

        return self::decode($value);
    }

    /** @param array<string, mixed> $response */
    private static function responseField(array $response, string $name): ?string
    {
        $fields = $response['response'] ?? null;
        $value = is_array($fields) ? ($fields[$name] ?? null) : null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function toArray(\stdClass $value): array
    {
        /** @var array<string, mixed> $array */
        $array = json_decode(json_encode($value, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        return $array;
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function decode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
