<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Admin;

use Calmfox\SyliusShopTwoFactorPlugin\Form\Type\TwoFactorSettingsType;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Configuration → Two-factor authentication for customers: the only shop-wide setting is the policy. */
final readonly class SettingsAction
{
    public function __construct(
        private TwoFactorPolicy $policy,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->formFactory->create(TwoFactorSettingsType::class, ['policy' => $this->policy->current()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $policy */
            $policy = $form->get('policy')->getData();
            $this->policy->change($policy);

            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'calmfox_shop_two_factor.settings.saved');
            }

            return new RedirectResponse($this->urlGenerator->generate('calmfox_shop_two_factor_admin_settings'));
        }

        return new Response($this->twig->render('@CalmfoxSyliusShopTwoFactorPlugin/admin/settings.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
