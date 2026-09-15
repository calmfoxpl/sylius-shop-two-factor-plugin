<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Access rules for the plugin's own routes, matched by route name instead of an URL pattern.
 *
 * `access_control` cannot be extended by a bundle, which would leave an installation step
 * in the application's security.yaml; matching by route also works with any admin path or
 * locale prefix. Every other request is decided by the application's rules.
 */
final readonly class RouteAccessMap implements AccessMapInterface
{
    /** @param array<string, list<string>> $attributesByRoute */
    public function __construct(
        private AccessMapInterface $decorated,
        private array $attributesByRoute,
    ) {
    }

    /** @return array{array<mixed>|null, string|null} */
    public function getPatterns(Request $request): array
    {
        $route = $request->attributes->get('_route');
        if (is_string($route) && isset($this->attributesByRoute[$route])) {
            return [$this->attributesByRoute[$route], null];
        }

        return $this->decorated->getPatterns($request);
    }
}
