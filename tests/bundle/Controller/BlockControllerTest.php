<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Controller;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Masilia\Bundle\IbexaBlocks\Block\BlockProviders;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Bundle\IbexaBlocks\Controller\BlockController;
use Masilia\Bundle\IbexaBlocks\Site\NullAdminMetaProvider;
use Masilia\Bundle\IbexaBlocks\Site\NullCacheTagger;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Block\ViewState;
use Masilia\IbexaBlocks\Conventions;
use Masilia\IbexaBlocks\Tests\bundle\Content\Fixtures;
use Masilia\IbexaBlocks\Value\Link;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BlockControllerTest extends TestCase
{
    use Fixtures;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/blocks-ctrl-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/standard', 0777, true);
        file_put_contents($this->dir . '/standard/theme.yaml', "views:\n  blocks:\n    hero:\n      template: blocks/hero.twig\n      variations: { image: banner }\n    grid: blocks/grid.twig\n");
    }

    protected function tearDown(): void
    {
        unlink($this->dir . '/standard/theme.yaml');
        rmdir($this->dir . '/standard');
        rmdir($this->dir);
    }

    /**
     * @param array<string, BlockViewParameters> $providers By id
     */
    private function controller(bool $flatten = false, array $providers = []): BlockController
    {
        $renderer = new class() implements FieldRenderer {
            public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
            {
                return $identifier === 'image' ? static fn (string $variation = 'original'): string => 'image@' . $variation : 'value of ' . $identifier;
            }
        };
        $registry = new FieldRendererRegistry(new ServiceLocator(['ibexa_string' => fn () => $renderer, 'ibexa_image' => fn () => $renderer]));
        $links = new class() implements LinkProvider {
            public function forLocation(Location $location): Link { return new Link('/block', 'Block'); }
            public function urlForLocation(int $locationId, ?string $siteaccess = null): string { return '/block'; }
        };
        $trail = new class() implements BreadcrumbProvider {
            public function forLocation(Location $location): array { return []; }
        };
        $services = new RenderableServices($registry, $links, $trail, new NullAdminMetaProvider(), new NullCacheTagger());
        $factory = new ContentFactory($this->createMock(ContentService::class), $this->createMock(LocationService::class), $services);

        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');
        $blocks = new BlockRegistry(new ThemeRegistry([$this->dir]), new DesignChain($config, ['standard' => ['standard']]));
        $locator = new ServiceLocator(array_map(static fn ($provider) => static fn () => $provider, $providers));
        $byType = [];
        foreach (array_keys($providers) as $id) {
            $byType['grid'][] = ['id' => $id, 'viewTypes' => [], 'priority' => 0];
        }

        return new BlockController($factory, $blocks, new BlockProviders($locator, $byType), new Conventions(), $flatten);
    }

    private function view(string $type, string $viewType = 'blocks', array $parameters = []): ContentView
    {
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn(3);
        $view = new ContentView();
        $view->setContent($this->content($this->type($type, ['title' => ['type' => 'ibexa_string'], 'image' => ['type' => 'ibexa_image']])));
        $view->setLocation($location);
        $view->setViewType($viewType);
        $view->addParameters($parameters);

        return $view;
    }

    public function testTheBlockIsShownByTheTemplateOfTheThemeWithItsFieldsAndItsImagesAtTheirVariation(): void
    {
        $view = $this->controller()->view($this->view('hero'));

        self::assertSame('@ibexadesign/blocks/hero.twig', $view->getTemplateIdentifier());
        $parameters = $view->getParameters();
        self::assertSame(['title' => 'value of title', 'image' => 'image@banner'], $parameters['fields']);
        self::assertInstanceOf(RenderableContent::class, $parameters['block']);
        self::assertSame('/block', $parameters['item_link']->uri);
        self::assertArrayNotHasKey('title', $parameters, 'fields are not variables of their own by default');
    }

    public function testFieldsCanAlsoBeVariablesOfTheirOwn(): void
    {
        $view = $this->controller(true)->view($this->view('hero'));

        self::assertSame('value of title', $view->getParameters()['title']);
        self::assertSame('image@banner', $view->getParameters()['image']);
    }

    public function testTheVariationsOfTheViewConfigurationOverrideThoseOfTheTheme(): void
    {
        $view = $this->controller()->view($this->view('hero', 'blocks', ['variations' => ['image' => 'card']]));

        self::assertSame('image@card', $view->getParameters()['fields']['image']);
    }

    public function testAProviderAddsParametersAndCanChangeTheTemplate(): void
    {
        $provider = new class() implements BlockViewParameters {
            public function parameters(RenderableContent $block, array $fields, ViewState $view): array
            {
                $view->useTemplate('blocks/latest_news.twig');

                return ['items' => ['a', 'b'], 'seen_title' => $fields['title']];
            }
        };

        $view = $this->controller(false, ['featured' => $provider])->view($this->view('grid'));

        self::assertSame('@ibexadesign/blocks/latest_news.twig', $view->getTemplateIdentifier());
        self::assertSame(['a', 'b'], $view->getParameters()['items']);
        self::assertSame('value of title', $view->getParameters()['seen_title'], 'a provider sees the fields');
    }

    public function testAContentTheThemeDoesNotShowSaysWhereToSayIt(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No theme shows a "unknown" in the "blocks" view. Add the template blocks/unknown.twig to a theme of the design (looked in: standard).');

        $this->controller()->view($this->view('unknown'));
    }

    public function testEachViewTypeHasItsOwnDefinitions(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->view($this->view('hero', 'line'));
    }
}
