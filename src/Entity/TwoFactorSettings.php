<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

/** The two-factor policy for shop customers, set in the panel. One row. */
#[ORM\Entity]
#[ORM\Table(name: 'calmfox_shop_two_factor_settings')]
class TwoFactorSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $policy;

    public function __construct(string $policy)
    {
        $this->policy = $policy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPolicy(): string
    {
        return $this->policy;
    }

    public function setPolicy(string $policy): void
    {
        $this->policy = $policy;
    }
}
