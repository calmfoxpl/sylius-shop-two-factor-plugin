<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Support;

/**
 * A passkey authenticator in PHP: builds exactly what a browser returns from
 * navigator.credentials.create() and .get(), signed with a real P-256 key, so tests exercise
 * the real WebAuthn verification instead of a mocked one.
 *
 * Authenticator data flags: 0x01 user present, 0x04 user verified, 0x40 attested credential data.
 */
final class SoftwareAuthenticator
{
    public const USER_PRESENT = 0x01;

    public const USER_VERIFIED = 0x04;

    public readonly string $credentialId;

    private \OpenSSLAsymmetricKey $key;

    public function __construct(private readonly string $rpId)
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => \OPENSSL_KEYTYPE_EC]);
        if (false === $key) {
            throw new \RuntimeException('Could not generate a test key.');
        }

        $this->key = $key;
        $this->credentialId = random_bytes(32);
    }

    /**
     * @param array<string, mixed> $options publicKey options from the server
     *
     * @return array<string, mixed>
     */
    public function register(array $options, string $origin, int $flags = self::USER_PRESENT | self::USER_VERIFIED): array
    {
        $clientData = $this->clientData('webauthn.create', $options, $origin);
        $authenticatorData = hash('sha256', $this->rpId, true) . pack('C', $flags | 0x40) . pack('N', 0)
            . str_repeat("\0", 16)
            . pack('n', \strlen($this->credentialId)) . $this->credentialId
            . $this->coseKey();

        $attestation = self::map([
            [self::text('fmt'), self::text('none')],
            [self::text('attStmt'), self::map([])],
            [self::text('authData'), self::bytes($authenticatorData)],
        ]);

        return $this->credential(['clientDataJSON' => self::b64u($clientData), 'attestationObject' => self::b64u($attestation)]);
    }

    /**
     * @param array<string, mixed> $options publicKey options from the server
     *
     * @return array<string, mixed>
     */
    public function login(array $options, string $origin, int $flags = self::USER_PRESENT | self::USER_VERIFIED, ?string $userHandle = null): array
    {
        $clientData = $this->clientData('webauthn.get', $options, $origin);
        $authenticatorData = hash('sha256', $this->rpId, true) . pack('C', $flags) . pack('N', 0);
        openssl_sign($authenticatorData . hash('sha256', $clientData, true), $signature, $this->key, \OPENSSL_ALGO_SHA256);

        $response = [
            'clientDataJSON' => self::b64u($clientData),
            'authenticatorData' => self::b64u($authenticatorData),
            'signature' => self::b64u(is_string($signature) ? $signature : ''),
        ];
        if (null !== $userHandle) {
            $response['userHandle'] = self::b64u($userHandle);
        }

        return $this->credential($response);
    }

    public static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param array<string, string> $response
     *
     * @return array<string, mixed>
     */
    private function credential(array $response): array
    {
        return ['id' => self::b64u($this->credentialId), 'rawId' => self::b64u($this->credentialId), 'type' => 'public-key', 'response' => $response];
    }

    /** @param array<string, mixed> $options */
    private function clientData(string $type, array $options, string $origin): string
    {
        $challenge = $options['challenge'] ?? null;

        return json_encode(['type' => $type, 'challenge' => is_string($challenge) ? $challenge : '', 'origin' => $origin, 'crossOrigin' => false], \JSON_THROW_ON_ERROR);
    }

    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->key);
        if (false === $details || !is_array($details['ec'] ?? null)) {
            throw new \RuntimeException('The test key has no curve coordinates.');
        }
        /** @var array{x: string, y: string} $ec */
        $ec = $details['ec'];

        return self::map([
            [self::int(1), self::int(2)],   // key type: EC2
            [self::int(3), self::int(-7)],  // algorithm: ES256
            [self::int(-1), self::int(1)],  // curve: P-256
            [self::int(-2), self::bytes(str_pad($ec['x'], 32, "\0", \STR_PAD_LEFT))],
            [self::int(-3), self::bytes(str_pad($ec['y'], 32, "\0", \STR_PAD_LEFT))],
        ]);
    }

    // minimal CBOR encoder: unsigned/negative integers, byte and text strings, maps

    private static function head(int $major, int $length): string
    {
        return match (true) {
            $length < 24 => pack('C', ($major << 5) | $length),
            $length < 256 => pack('C', ($major << 5) | 24) . pack('C', $length),
            default => pack('C', ($major << 5) | 25) . pack('n', $length),
        };
    }

    private static function int(int $value): string
    {
        return $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value);
    }

    private static function bytes(string $value): string
    {
        return self::head(2, \strlen($value)) . $value;
    }

    private static function text(string $value): string
    {
        return self::head(3, \strlen($value)) . $value;
    }

    /** @param list<array{string, string}> $pairs */
    private static function map(array $pairs): string
    {
        return self::head(5, \count($pairs)) . implode('', array_map(static fn (array $pair): string => $pair[0] . $pair[1], $pairs));
    }
}
