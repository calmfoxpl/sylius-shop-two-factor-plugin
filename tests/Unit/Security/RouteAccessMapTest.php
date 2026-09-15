<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Security;

use Calmfox\SyliusShopTwoFactorPlugin\Security\RouteAccessMap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;

final class RouteAccessMapTest extends TestCase
{
    public function testItGrantsTheConfiguredAttributesForPluginRoutes(): void
    {
        $decorated = $this->createMock(AccessMapInterface::class);
        $decorated->expects(self::never())->method('getPatterns');

        $map = new RouteAccessMap($decorated, ['plugin_route' => ['PUBLIC_ACCESS']]);
        $request = new Request();
        $request->attributes->set('_route', 'plugin_route');

        self::assertSame([['PUBLIC_ACCESS'], null], $map->getPatterns($request));
    }

    public function testItLeavesOtherRequestsToTheApplicationRules(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'sylius_admin_dashboard');

        $decorated = $this->createMock(AccessMapInterface::class);
        $decorated->expects(self::once())->method('getPatterns')->with($request)->willReturn([['ROLE_ADMINISTRATION_ACCESS'], null]);

        $map = new RouteAccessMap($decorated, ['plugin_route' => ['PUBLIC_ACCESS']]);

        self::assertSame([['ROLE_ADMINISTRATION_ACCESS'], null], $map->getPatterns($request));
    }

    public function testARequestWithoutRouteIsLeftToTheApplicationRules(): void
    {
        $request = new Request();

        $decorated = $this->createMock(AccessMapInterface::class);
        $decorated->expects(self::once())->method('getPatterns')->with($request)->willReturn([null, null]);

        self::assertSame([null, null], (new RouteAccessMap($decorated, ['plugin_route' => ['PUBLIC_ACCESS']]))->getPatterns($request));
    }
}
