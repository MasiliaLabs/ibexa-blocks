<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\View;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Ibexa\Core\Repository\Values\Content\Content;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Controller\BlockController;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Bundle\IbexaBlocks\View\BlockViewProvider;
use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;
use Twig\Loader\ArrayLoader;

final class BlockViewProviderTest extends TestCase
{
    private function provider(array $templates): BlockViewProvider
    {
        $config = $this->createStub(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');
        $loader = new ArrayLoader(array_combine(array_map(static fn (string $t) => '@ibexadesign/' . $t, $templates), array_fill(0, count($templates), '')));
        $registry = new BlockRegistry(new ThemeRegistry([]), new DesignChain($config, ['standard' => ['standard']]), $loader, TestSettings::fromParameters([]));

        return new BlockViewProvider($registry, ['blocks', 'block_item', 'line']);
    }

    private function view(string $type, string $viewType): ContentView
    {
        $info = new ContentInfo(['id' => 1, 'name' => 'A', 'contentType' => new ContentType(['identifier' => $type])]);
        $content = new Content(['versionInfo' => new VersionInfo(['contentInfo' => $info, 'names' => ['eng-GB' => 'A'], 'initialLanguageCode' => 'eng-GB']), 'contentType' => new ContentType(['identifier' => $type])]);
        $view = new ContentView(null, [], $viewType);
        $view->setContent($content);

        return $view;
    }

    public function testAContentThatATemplateOfTheDesignShowsGetsTheBlockController(): void
    {
        $provided = $this->provider(['blocks/hero.twig'])->getView($this->view('hero', 'blocks'));

        self::assertNotNull($provided);
        self::assertSame(BlockController::class . '::view', $provided->getControllerReference()->controller);
    }

    public function testAContentNoTemplateShowsIsLeftToTheNextProvider(): void
    {
        self::assertNull($this->provider(['blocks/hero.twig'])->getView($this->view('cta', 'blocks')), 'no template for the type');
    }

    public function testOnlyTheViewTypesOfTheBlocksAreHandled(): void
    {
        self::assertNull($this->provider(['full/hero.twig'])->getView($this->view('hero', 'full')), 'full is not one of the view types of the blocks');
        self::assertNull($this->provider(['blocks/hero.twig'])->getView(new class() extends \Ibexa\Core\MVC\Symfony\View\BaseView {
        }), 'not a content view');
    }
}
