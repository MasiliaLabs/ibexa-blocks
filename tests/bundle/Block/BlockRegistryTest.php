<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Block;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\IbexaBlocks\Conventions;
use Masilia\IbexaBlocks\Tests\bundle\Content\Fixtures;
use PHPUnit\Framework\TestCase;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class BlockRegistryTest extends TestCase
{
    use Fixtures;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/blocks-registry-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/standard', 0777, true);
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

    /**
     * @param list<string> $templates The templates of the design, as the design engine finds them ("blocks/hero.twig")
     */
    private function registry(array $templates, string $manifest = '', ?Conventions $conventions = null): BlockRegistry
    {
        if ($manifest !== '') {
            file_put_contents($this->dir . '/standard/theme.yaml', $manifest);
        }
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');
        $loader = new ArrayLoader(array_combine(array_map(static fn (string $t) => '@ibexadesign/' . $t, $templates), array_fill(0, count($templates), '')));

        return new BlockRegistry(new ThemeRegistry([$this->dir]), new DesignChain($config, ['standard' => ['standard']]), $loader, $conventions ?? new Conventions());
    }

    public function testATemplateIsFoundByItsNameInTheFolderOfTheViewType(): void
    {
        $registry = $this->registry(['blocks/hero.twig', 'cards/hero.twig', 'line/hero.twig', 'full/hero.twig', 'embed/hero.twig']);

        self::assertSame('@ibexadesign/blocks/hero.twig', $registry->find('blocks', 'hero')?->template);
        self::assertSame('@ibexadesign/cards/hero.twig', $registry->find('block_item', 'hero')?->template);
        self::assertSame('@ibexadesign/line/hero.twig', $registry->find('line', 'hero')?->template);
        self::assertSame('@ibexadesign/full/hero.twig', $registry->find('full', 'hero')?->template);
        self::assertSame('@ibexadesign/embed/hero.twig', $registry->find('embed', 'hero')?->template);
        self::assertNull($registry->find('blocks', 'unknown'));
    }

    public function testAnEmbedFallsBackToTheLine(): void
    {
        $registry = $this->registry(['line/file.twig']);

        self::assertSame('@ibexadesign/line/file.twig', $registry->find('embed', 'file')?->template);
    }

    public function testAViewTypeThatHasNoSpecialFolderUsesItsOwnName(): void
    {
        $registry = $this->registry(['teaser/article.twig']);

        self::assertSame('@ibexadesign/teaser/article.twig', $registry->find('teaser', 'article')?->template);
    }

    public function testWhatTheManifestNamesWinsOverTheConventionAndIsFoundByItsOwnPath(): void
    {
        $registry = $this->registry(['blocks/hero.twig', 'blocks/other.twig'], "views:\n  blocks:\n    hero: { template: blocks/other.twig, variations: { image: banner } }\n");

        $resolved = $registry->find('blocks', 'hero');
        self::assertSame('@ibexadesign/blocks/other.twig', $resolved?->template);
        self::assertSame(['image' => 'banner'], $resolved?->definition->variations);
        self::assertSame('standard', $resolved->theme);
    }

    public function testAFoundByConventionTemplateHasNoVariationsAndNoTheme(): void
    {
        $resolved = $this->registry(['blocks/hero.twig'])->find('blocks', 'hero');

        self::assertSame([], $resolved?->definition->variations);
        self::assertNull($resolved->theme);
    }

    public function testATypeThatHoldsBlocksIsShownByThePageOfBlocksOfThePackageUnlessItHasATemplateOfItsOwn(): void
    {
        $registry = $this->registry(['full/event.twig']);
        $page = $this->type('home_page', ['blocks' => ['type' => 'ibexa_object_relation_list']]);

        self::assertSame(BlockRegistry::BLOCKS_PAGE, $registry->findFor('full', $page)?->template);
        self::assertNull($registry->findFor('blocks', $page), 'only the full view falls back to a page of blocks');

        $own = $this->registry(['full/home_page.twig']);
        self::assertSame('@ibexadesign/full/home_page.twig', $own->findFor('full', $page)?->template);
    }

    public function testTheBlocksFieldIsTheOneTheConventionsName(): void
    {
        $registry = $this->registry([], '', new Conventions(blocksField: 'sections'));

        self::assertNull($registry->findFor('full', $this->type('page', ['blocks' => ['type' => 'ibexa_object_relation_list']])));
        self::assertSame(BlockRegistry::BLOCKS_PAGE, $registry->findFor('full', $this->type('page', ['sections' => ['type' => 'ibexa_object_relation_list']]))?->template);
    }

    public function testWhatNoTemplateIsWrittenForFallsBackToTheDefaultOfTheFolderOrTheWildcardOfAManifest(): void
    {
        $default = $this->registry(['full/_default.twig']);
        self::assertSame('@ibexadesign/full/_default.twig', $default->find('full', 'anything')?->template);

        $wildcard = $this->registry(['error.twig', 'full/_default.twig'], "views:\n  full:\n    '*': error.twig\n");
        self::assertSame('@ibexadesign/error.twig', $wildcard->find('full', 'anything')?->template, 'a manifest speaks before the convention');

        $both = $this->registry(['full/article.twig', 'full/_default.twig']);
        self::assertSame('@ibexadesign/full/article.twig', $both->find('full', 'article')?->template, 'a type beats the default');
    }

    public function testThePageOfBlocksBeatsTheDefaultButNotAWildcardOfAManifestForATypeThatHasNoBlocks(): void
    {
        $registry = $this->registry(['full/_default.twig']);
        $page = $this->type('page', ['blocks' => ['type' => 'ibexa_object_relation_list']]);
        $other = $this->type('note', ['title' => ['type' => 'ibexa_string']]);

        self::assertSame(BlockRegistry::BLOCKS_PAGE, $registry->findFor('full', $page)?->template);
        self::assertSame('@ibexadesign/full/_default.twig', $registry->findFor('full', $other)?->template);
    }

    public function testWithoutALoaderOnlyTheManifestsSpeak(): void
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');
        file_put_contents($this->dir . '/standard/theme.yaml', "views:\n  blocks:\n    hero: blocks/hero.twig\n");
        $registry = new BlockRegistry(new ThemeRegistry([$this->dir]), new DesignChain($config, ['standard' => ['standard']]));

        self::assertSame('@ibexadesign/blocks/hero.twig', $registry->find('blocks', 'hero')?->template);
        self::assertNull($registry->find('blocks', 'other'));
    }

    public function testAnAnswerIsRememberedPerDesign(): void
    {
        $loader = new class() implements LoaderInterface {
            public int $asked = 0;

            public function getSourceContext(string $name): Source
            {
                return new Source('', $name);
            }

            public function getCacheKey(string $name): string
            {
                return $name;
            }

            public function isFresh(string $name, int $time): bool
            {
                return true;
            }

            public function exists(string $name): bool
            {
                ++$this->asked;

                return $name === '@ibexadesign/blocks/hero.twig';
            }
        };
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');
        $registry = new BlockRegistry(new ThemeRegistry([$this->dir]), new DesignChain($config, ['standard' => ['standard']]), $loader);

        $registry->find('blocks', 'hero');
        $asked = $loader->asked;
        $registry->find('blocks', 'hero');

        self::assertSame($asked, $loader->asked);
    }
}
