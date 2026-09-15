<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Totp;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Renders scheb/2fa code forms in the shop look for shop customers. As a decorator it hands
 * everyone else (e.g. administrators) to the renderer it wraps.
 */
final readonly class ShopFormRenderer implements TwoFactorFormRendererInterface
{
    public function __construct(
        private ?TwoFactorFormRendererInterface $inner,
        private TokenStorageInterface $tokenStorage,
        private Environment $twig,
        private string $template,
    ) {
    }

    public function renderForm(Request $request, array $templateVars): Response
    {
        if (null !== $this->inner && !$this->tokenStorage->getToken()?->getUser() instanceof TwoFactorShopUserInterface) {
            return $this->inner->renderForm($request, $templateVars);
        }

        return new Response($this->twig->render($this->template, $templateVars));
    }
}
