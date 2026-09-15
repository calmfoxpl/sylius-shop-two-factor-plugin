<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Policy;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

/** With the customer policy set to "disabled", customers are not asked for a second factor. Other users are not this condition's business. */
final readonly class TwoFactorCondition implements TwoFactorConditionInterface
{
    public function __construct(private TwoFactorPolicy $policy)
    {
    }

    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        if (!$context->getUser() instanceof TwoFactorShopUserInterface) {
            return true;
        }

        return $this->policy->isActive();
    }
}
