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
        $chain = $this->chain('fossil_exit', ['fossil_exit' => ['fossil_exit', 'mattcch_light', 'standard']]);

        self::assertSame(['fossil_exit', 'mattcch_light', 'standard'], $chain->themes());
        self::assertSame(['unknown'], $this->chain('unknown', [])->themes());
    }

    public function testAThemeIsReadOnceAndOneWithoutManifestIsNull(): void
    {
        $this->theme('standard', "views:\n  blocks:\n    hero: blocks/hero.twig\n");
        $registry = new ThemeRegistry([$this->dir]);

        self::assertSame(['standard'], $registry->names());
        self::assertSame($registry->manifest('standard'), $registry->manifest('standard'));
        self::assertNull($registry->manifest('mattcch'));
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
        $this->theme('fossil_exit', "views:\n  blocks:\n    hero: { template: blocks/hero_fossil.twig, variations: { image: banner } }\n");
        $blocks = new BlockRegistry(new ThemeRegistry([$this->dir]), $this->chain('fossil_exit', ['fossil_exit' => ['fossil_exit', 'mattcch_light', 'standard']]));

        $hero = $blocks->find('blocks', 'hero');
        self::assertSame(['fossil_exit', 'blocks/hero_fossil.twig', ['image' => 'banner']], [$hero->theme, $hero->definition->template, $hero->definition->variations]);
        $cta = $blocks->find('blocks', 'cta');
        self::assertSame(['standard', 'blocks/cta.twig'], [$cta->theme, $cta->definition->template], 'what the theme does not say comes from the next one');
        self::assertNull($blocks->find('blocks', 'nope'));
        self::assertSame(['fossil_exit', 'mattcch_light', 'standard'], $blocks->searched());
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
}
