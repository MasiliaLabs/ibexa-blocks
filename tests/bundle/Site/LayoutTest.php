<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Controller\LayoutController;
use Masilia\Bundle\IbexaBlocks\Site\LanguageSwitcher;
use Masilia\Bundle\IbexaBlocks\Site\LayoutProvider;
use Masilia\Bundle\IbexaBlocks\Site\NullMenuProvider;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\Contracts\IbexaBlocks\MenuProvider;
use Masilia\IbexaBlocks\Value\Link;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LayoutTest extends TestCase
{
    /**
     * @param array<string, mixed> $values Parameter => value, whatever the namespace and scope
     */
    private function config(array $values): ConfigResolverInterface
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('hasParameter')->willReturnCallback(static fn (string $name) => array_key_exists($name, $values));
        $config->method('getParameter')->willReturnCallback(static fn (string $name, ?string $namespace = null, ?string $scope = null) => $values[$name . ($scope !== null ? '@' . $scope : '')] ?? null);

        return $config;
    }

    public function testTheLayoutContentIsFoundByTheRemoteIdOfTheSiteAndIsNullWhenThereIsNone(): void
    {
        $content = $this->createMock(Content::class);
        $contents = $this->createMock(ContentService::class);
        $contents->expects(self::once())->method('loadContentByRemoteId')->with('site_layout')->willReturn($content);
        $factory = (new \ReflectionClass(ContentFactory::class))->newInstanceWithoutConstructor();

        $provider = new LayoutProvider($this->config(['layout_remote_id' => 'site_layout', 'layout_menus' => ['header' => 'main']]), $contents, $factory);

        self::assertSame($content, $provider->content());
        self::assertSame($content, $provider->content(), 'read once');
        self::assertSame('main', $provider->menuType('header'));
        self::assertNull($provider->menuType('footer'));

        $missing = $this->createMock(ContentService::class);
        $missing->method('loadContentByRemoteId')->willThrowException(new NotFoundException('content', 'x'));
        self::assertNull((new LayoutProvider($this->config(['layout_remote_id' => 'x']), $missing, $factory))->content());
        self::assertNull((new LayoutProvider($this->config([]), $contents, $factory))->content(), 'a site with no layout setting has no layout');
    }

    public function testTheLanguageSwitcherLinksEachLanguageOfThePageThatASiteaccessOfTheSiteServes(): void
    {
        $config = $this->config([
            'translation_siteaccesses' => ['site', 'site_fr', 'site_ar'],
            'languages@site' => ['eng-GB'],
            'languages@site_fr' => ['fre-FR'],
            'languages@site_ar' => ['ara-SA'],
        ]);
        $links = new class() implements LinkProvider {
            public function forLocation(Location $location): Link { return new Link('/', 'x'); }
            public function urlForLocation(int $locationId, ?string $siteaccess = null): string { return sprintf('/%s/%d', $siteaccess ?? 'current', $locationId); }
        };
        $version = $this->createMock(VersionInfo::class);
        $version->method('__get')->willReturnCallback(static fn (string $name) => $name === 'languageCodes' ? ['eng-GB', 'fre-FR', 'spa-ES'] : null);
        $content = $this->createMock(Content::class);
        $content->method('getVersionInfo')->willReturn($version);
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn(9);
        $location->method('getContent')->willReturn($content);

        $switch = (new LanguageSwitcher($config, $links))->forLocation($location);

        self::assertEquals([new Link('/site/9', 'eng-GB', ['language' => 'eng-GB']), new Link('/site_fr/9', 'fre-FR', ['language' => 'fre-FR'])], $switch, 'the page has no Arabic version, and nobody serves Spanish');
    }

    public function testTheHeaderGetsTheMenuTheLanguagesAndTheLayoutAndTheFooterItsMenus(): void
    {
        $menus = new class() implements MenuProvider {
            public function menu(string $type, int $rootLocationId): mixed { return $type . '@' . $rootLocationId; }
        };
        $config = $this->config(['layout_menus' => ['header' => 'main', 'footer' => 'menu_footer', 'footer_bottom' => 'menu_footer_bottom'], 'content.tree_root.location_id' => 62, 'languages' => ['fre-FR']]);
        $layout = new LayoutProvider($config, $this->createMock(ContentService::class), (new \ReflectionClass(ContentFactory::class))->newInstanceWithoutConstructor());
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willThrowException(new NotFoundException('location', 1));
        $twig = new Environment(new ArrayLoader([
            '@ibexadesign/layout/header.twig' => 'header {{ menu_header }} {{ switch_languages|length }} {{ current_language }} {{ current_uri }}',
            '@ibexadesign/layout/footer.twig' => 'footer {{ menu_footer }} {{ legal_links }}',
        ]));
        $controller = new LayoutController($layout, $menus, new LanguageSwitcher($config, $this->createMock(LinkProvider::class)), $locations, $config, $twig);

        self::assertSame('header main@62 0 fre-FR /about', $controller->header(5, '/about')->getContent());
        self::assertSame('footer menu_footer@62 menu_footer_bottom@62', $controller->footer()->getContent());
    }

    public function testWithoutAMenuManagerTheLayoutHasNoMenus(): void
    {
        self::assertNull((new NullMenuProvider())->menu('main', 62));
    }
}
