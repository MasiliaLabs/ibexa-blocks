<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Routing;

use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccessGroup;
use Masilia\Bundle\IbexaBlocks\Controller\SearchController;
use Masilia\Bundle\IbexaBlocks\Routing\SiteAccessGroupExpression;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SiteAccessGroupExpressionTest extends TestCase
{
    private function request(?array $groups): Request
    {
        $request = Request::create('/search');
        if ($groups !== null) {
            $siteAccess = new SiteAccess('masilialabs', 'host:map');
            $siteAccess->groups = array_map(static fn (string $name): SiteAccessGroup => new SiteAccessGroup($name), $groups);
            $request->attributes->set('siteaccess', $siteAccess);
        }

        return $request;
    }

    /** The condition of the route, as written on the controller, with the parameter the router resolves. */
    private function condition(): string
    {
        $attributes = (new \ReflectionMethod(SearchController::class, 'page'))->getAttributes(Route::class);
        self::assertCount(1, $attributes);
        $condition = $attributes[0]->newInstance()->getCondition();
        self::assertStringContainsString('%admin_group_name%', (string) $condition);

        return str_replace('%admin_group_name%', 'admin_group', (string) $condition);
    }

    private function routeMatches(Request $request): bool
    {
        $language = new ExpressionLanguage();
        $language->registerProvider(new SiteAccessGroupExpression());

        return (bool) $language->evaluate($this->condition(), ['request' => $request]);
    }

    public function testTheSearchPageIsNotMatchedOnAnAdminSiteaccess(): void
    {
        self::assertFalse($this->routeMatches($this->request(['admin_group'])));
    }

    public function testTheSearchPageIsMatchedOnAFrontSiteaccess(): void
    {
        self::assertTrue($this->routeMatches($this->request(['front_group', 'masilialabs_group'])));
        self::assertTrue($this->routeMatches($this->request(['site_group'])));
    }

    public function testTheSearchPageIsMatchedWhenThereIsNoSiteaccess(): void
    {
        // a request that no siteaccess has matched (a console command generating URLs): not the admin
        self::assertTrue($this->routeMatches($this->request(null)));
    }

    public function testTheCompiledFormCallsTheClassAndAnswersTheSame(): void
    {
        $language = new ExpressionLanguage();
        $language->registerProvider(new SiteAccessGroupExpression());
        $php = $language->compile("siteaccess_in_group('admin_group')", ['request']);
        self::assertStringContainsString(SiteAccessGroupExpression::class . '::inGroup($request', $php);

        $admin = $this->request(['admin_group']);
        $front = $this->request(['front_group']);
        self::assertTrue((static fn (Request $request): bool => eval('return ' . $php . ';'))($admin));
        self::assertFalse((static fn (Request $request): bool => eval('return ' . $php . ';'))($front));
    }
}
