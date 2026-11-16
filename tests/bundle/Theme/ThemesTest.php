<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Block\BlockProviders;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;
use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ThemesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/blocks-themes-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $folder) {
            rmdir($folder);
        }
        rmdir($this->dir);
    }

    private function theme(string $name, string $yaml): void
    {
        mkdir($this->dir . '/' . $name);
        file_put_contents($this->dir . '/' . $name . '/theme.yaml', $yaml);
    }

    private function chain(string $design, array $designs): DesignChain
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->with('design')->willReturn($design);

        return new DesignChain($config, $designs);
    }

    public function testTheChainIsTheDesignThenItsThemesWithoutDoubles(): void
    {
        $chain = $this->chain('acme', ['acme' => ['acme', 'partner_light', 'standard']]);

        self::assertSame(['acme', 'partner_light', 'standard'], $chain->themes());
        self::assertSame(['unknown'], $this->chain('unknown', [])->themes());
    }

    public function testAThemeIsReadOnceAndOneWithoutManifestIsNull(): void
    {
        $this->theme('standard', "views:\n  blocks:\n    hero: blocks/hero.twig\n");
        $registry = new ThemeRegistry([$this->dir]);

        self::assertSame(['standard'], $registry->names());
        self::assertSame($registry->manifest('standard'), $registry->manifest('standard'));
        self::assertNull($registry->manifest('partner'));
    }

    public function testBrokenYamlAndWrongManifestsSaySoWithTheFile(): void
    {
        $this->theme('broken', "views: [\n");
        $this->theme('wrong', "views:\n  blocks:\n    a: '@ibexadesign/a.twig'\n");
        $registry = new ThemeRegistry([$this->dir]);

        try {
            $registry->manifest('broken');
            self::fail('broken YAML');
        } catch (InvalidThemeManifest $e) {
            self::assertStringContainsString('broken/theme.yaml is not valid YAML', $e->getMessage());
        }

        $this->expectException(InvalidThemeManifest::class);
        $this->expectExceptionMessage('Theme "wrong"');
        $registry->manifest('wrong');
    }

    public function testTheFirstThemeOfTheDesignThatSaysItWinsAndTheOthersFillTheGaps(): void
    {
        $this->theme('standard', "views:\n  blocks:\n    hero: blocks/hero.twig\n    cta: blocks/cta.twig\n");
        $this->theme('acme', "views:\n  blocks:\n    hero: { template: blocks/hero_acme.twig, variations: { image: banner } }\n");
        $blocks = new BlockRegistry(new ThemeRegistry([$this->dir]), $this->chain('acme', ['acme' => ['acme', 'partner_light', 'standard']]));

        $hero = $blocks->find('blocks', 'hero');
        self::assertSame(['acme', 'blocks/hero_acme.twig', ['image' => 'banner']], [$hero->theme, $hero->definition->template, $hero->definition->variations]);
        $cta = $blocks->find('blocks', 'cta');
        self::assertSame(['standard', 'blocks/cta.twig'], [$cta->theme, $cta->definition->template], 'what the theme does not say comes from the next one');
        self::assertNull($blocks->find('blocks', 'nope'));
        self::assertSame(['acme', 'partner_light', 'standard'], $blocks->searched());
    }

    public function testAnyOtherContentIsTheLastResortAndNeverBeatsAType(): void
    {
        $this->theme('standard', "views:\n  full:\n    page: page.twig\n    '*': error.twig\n");
        $this->theme('acme', "views:\n  full:\n    article: post_acme.twig\n");
        $blocks = new BlockRegistry(new ThemeRegistry([$this->dir]), $this->chain('acme', ['acme' => ['acme', 'standard']]));

        self::assertSame('page.twig', $blocks->find('full', 'page')->definition->template);
        self::assertSame('post_acme.twig', $blocks->find('full', 'article')->definition->template);
        self::assertSame(['standard', 'error.twig'], [$blocks->find('full', 'folder')->theme, $blocks->find('full', 'folder')->definition->template], 'a theme that names article does not hide the standard fallback');
        self::assertNull($blocks->find('blocks', 'folder'), 'each view type has its own fallback');
    }

    public function testProvidersRunByPriorityAndOnlyInTheirViewTypes(): void
    {
        $make = static fn (string $name): BlockViewParameters => new class($name) implements BlockViewParameters {
            public function __construct(public string $name) {}
            public function parameters(RenderableContent $block, array $fields, ViewState $view): array { return []; }
        };
        $locator = new ServiceLocator(['low' => fn () => $make('low'), 'high' => fn () => $make('high'), 'items' => fn () => $make('items')]);
        $providers = new BlockProviders($locator, ['grid' => [
            ['id' => 'low', 'viewTypes' => [], 'priority' => 0],
            ['id' => 'high', 'viewTypes' => ['blocks'], 'priority' => 10],
            ['id' => 'items', 'viewTypes' => ['block_item'], 'priority' => 5],
        ]]);

        self::assertSame(['high', 'low'], array_map(static fn ($p) => $p->name, $providers->for('grid', 'blocks')));
        self::assertSame(['items', 'low'], array_map(static fn ($p) => $p->name, $providers->for('grid', 'block_item')));
        self::assertSame([], $providers->for('other', 'blocks'));
    }

    public function testTheBlocksADesignShowsAreTheTemplatesAndTheManifestsOfItsChain(): void
    {
        $this->theme('standard', "views:\n  blocks:\n    hero: blocks/hero_x.twig\n    '*': blocks/any.twig\n");
        mkdir($this->dir . '/standard/blocks');
        file_put_contents($this->dir . '/standard/blocks/cta.twig', '');
        $this->theme('acme', '');
        mkdir($this->dir . '/acme/blocks');
        file_put_contents($this->dir . '/acme/blocks/map.twig', '');
        file_put_contents($this->dir . '/acme/blocks/hero.twig', '');

        $config = $this->createMock(\Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(static fn (string $name, $namespace = null, $scope = null): string => $scope === 'acme_fr' ? 'acme' : 'standard');
        $shown = new \Masilia\Bundle\IbexaBlocks\Theme\ThemeShownBlocks(new ThemeRegistry([$this->dir]), $config, ['acme' => ['acme', 'standard'], 'standard' => ['standard']]);

        self::assertSame(['cta', 'hero', 'map'], $shown->forSiteaccess('acme_fr'));
        self::assertSame(['cta', 'hero'], $shown->forSiteaccess('site'), 'a design of its own themes only');

        foreach (['/standard/blocks/cta.twig', '/acme/blocks/map.twig', '/acme/blocks/hero.twig'] as $file) {
            unlink($this->dir . $file);
        }
        rmdir($this->dir . '/standard/blocks');
        rmdir($this->dir . '/acme/blocks');
    }
}
