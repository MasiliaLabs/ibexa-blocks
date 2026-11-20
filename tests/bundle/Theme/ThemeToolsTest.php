<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Command\ThemeCreateCommand;
use Masilia\Bundle\IbexaBlocks\Command\ThemesCommand;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeChecker;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeScaffold;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ThemeToolsTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/blocks-tools-' . bin2hex(random_bytes(4));
        mkdir($this->project . '/templates/themes', 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->project);
    }

    private function write(string $path, string $content): void
    {
        @mkdir(dirname($this->project . '/' . $path), 0777, true);
        file_put_contents($this->project . '/' . $path, $content);
    }

    private function registry(): ThemeRegistry
    {
        return new ThemeRegistry([$this->project . '/templates/themes']);
    }

    private function standardTheme(): void
    {
        $this->write('templates/themes/standard/theme.yaml', "assets: { entry: standard, styles: [fa-icons], extra: [admin-toolbar.js] }\nfavicons: build/images/brand/icons\nviews:\n  blocks:\n    hero: blocks/hero.twig\n    cta: blocks/cta.twig\n    ghost: blocks/ghost.twig\n");
        $this->write('templates/themes/standard/blocks/hero.twig', 'hero');
        $this->write('templates/themes/standard/blocks/cta.twig', 'cta');
        $this->write('templates/themes/standard/blocks/dead.twig', 'dead');
        $this->write('templates/themes/standard/layout.twig', '{% extends "@MasiliaIbexaBlocks/layout.twig" %}');
        $this->write('assets/themes/standard/scss/main.scss', '');
        $this->write('assets/themes/standard/js/main.js', '');
    }

    public function testTheCheckFindsWhatIsMissingWhatIsDeadAndWhatIsCopied(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/site/theme.yaml', "assets: { entry: site }\nviews:\n  blocks:\n    hero: blocks/hero_site.twig\n");
        $this->write('templates/themes/site/layout.twig', '<html>a copy</html>');

        $issues = (new ThemeChecker($this->registry(), $this->project))->check(['site', 'standard'], ['hero', 'cta', 'map']);
        $messages = array_map(static fn (array $i): string => $i['level'] . ' ' . ($i['theme'] ?? '-') . ' ' . $i['message'], $issues);
        $text = implode("\n", $messages);

        self::assertStringContainsString('notice site views.blocks.hero: the template "blocks/hero_site.twig" is in no theme of this design', $text);
        self::assertStringContainsString('notice standard views.blocks.ghost: the template "blocks/ghost.twig"', $text);
        self::assertStringContainsString('error - The block type "map" is shown by no theme', $text);
        self::assertStringNotContainsString('block type "hero"', $text);
        self::assertStringContainsString('error site assets: assets/themes/site/scss/main.scss does not exist, the entry "site" cannot be built.', $text);
        self::assertStringContainsString('notice site layout.twig does not extend the base layout', $text);
        self::assertStringNotContainsString('standard layout.twig', $text);
        self::assertStringContainsString('notice standard blocks/dead.twig is named after no content type', $text);
        self::assertStringNotContainsString('blocks/cta.twig is referred', $text);
    }

    public function testATemplateNamedAfterAContentTypeIsFoundByTheConventionAndAUselessManifestLineIsSaid(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/standard/blocks/map.twig', 'map');
        $this->write('templates/themes/standard/theme.yaml', "assets: { entry: standard }\nviews:\n  blocks:\n    hero: blocks/hero.twig\n    cta: { template: blocks/cta.twig, variations: { image: banner } }\n");

        $issues = (new ThemeChecker($this->registry(), $this->project))->check(['standard'], ['hero', 'cta', 'map', 'gone'], ['hero', 'cta', 'map', 'gone']);
        $text = implode("\n", array_map(static fn (array $i): string => $i['level'] . ' ' . $i['message'], $issues));

        self::assertStringNotContainsString('block type "map"', $text, 'blocks/map.twig is found by its name');
        self::assertStringContainsString('error The block type "gone" is shown by no theme of the design', $text);
        self::assertStringContainsString('Add blocks/gone.twig to a theme.', $text);
        self::assertStringContainsString('notice views.blocks.hero: "blocks/hero.twig" is the template the convention finds without it', $text);
        self::assertStringNotContainsString('views.blocks.cta: "blocks/cta.twig" is the template the convention', $text, 'a line with variations says more than the convention');
        self::assertStringNotContainsString('blocks/map.twig is named after no content type', $text);
        self::assertStringContainsString('blocks/dead.twig is named after no content type', $text);
    }

    public function testAFileAProviderOrAConfigNamesIsNotDeadAndAWildcardShowsEveryBlockType(): void
    {
        $this->standardTheme();
        $this->write('src/Block/Featured.php', "<?php // useTemplate('blocks/dead.twig')");

        $issues = (new ThemeChecker($this->registry(), $this->project, [$this->project . '/src']))->check(['standard'], ['hero']);

        self::assertStringNotContainsString('dead.twig', implode("\n", array_column($issues, 'message')));
    }

    public function testTheThemesCommandGivesTheEntriesToBuildAndRefusesTwoSourcesForOneEntry(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/other/theme.yaml', "assets: { directory: assets/themes/standard, entry: standard }\n");
        $command = new CommandTester(new ThemesCommand($this->registry(), ['standard' => []], $this->project));

        self::assertSame(0, $command->execute(['--json' => true]));
        $json = json_decode($command->getDisplay(), true);
        self::assertSame(['standard.css' => 'assets/themes/standard/scss/main.scss', 'standard.js' => 'assets/themes/standard/js/main.js'], $json['entries']);

        $this->write('templates/themes/clash/theme.yaml', "assets: { directory: assets/themes/clash, entry: standard }\n");
        $this->write('assets/themes/clash/scss/main.scss', '');
        $this->write('assets/themes/clash/js/main.js', '');
        $clash = new CommandTester(new ThemesCommand($this->registry(), [], $this->project));

        self::assertSame(1, $clash->execute(['--json' => true]));
        self::assertStringContainsString('The entry \"standard.css\" is built from', $clash->getDisplay());
    }

    public function testANewThemeExtendsAnotherWithItsManifestLayoutAndAssets(): void
    {
        $this->standardTheme();
        $command = new CommandTester(new ThemeCreateCommand(new ThemeScaffold($this->registry(), $this->project)));

        self::assertSame(0, $command->execute(['name' => 'blog']));

        $manifest = file_get_contents($this->project . '/templates/themes/blog/theme.yaml');
        self::assertStringContainsString("directory: assets/themes/blog\n  entry: blog\n  styles: [fa-icons]\n  extra: [admin-toolbar.js]", $manifest);
        self::assertStringContainsString('@MasiliaIbexaBlocks/layout.twig', (string) file_get_contents($this->project . '/templates/themes/blog/layout.twig'));
        self::assertStringContainsString("@use '../../standard/scss/main';", (string) file_get_contents($this->project . '/assets/themes/blog/scss/main.scss'));
        self::assertStringContainsString("import '../../standard/js/main.js';", (string) file_get_contents($this->project . '/assets/themes/blog/js/main.js'));
        self::assertStringContainsString('ibexa_design_engine.design_list.blog: [blog, standard]', $command->getDisplay());
        self::assertStringNotContainsString('views', $manifest, 'templates are found by name: the manifest lists none');

        // The theme it made is valid and its assets are found.
        $issues = (new ThemeChecker($this->registry(), $this->project))->check(['blog', 'standard'], ['hero']);
        self::assertSame([], array_filter($issues, static fn (array $i): bool => $i['level'] === 'error' && str_contains($i['message'], 'assets')));
    }

    public function testAThemeInNoDesignIsReported(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/orphan/theme.yaml', "name: orphan\n");
        $types = $this->createStub(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $types->method('loadContentTypeGroupByIdentifier')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\ContentTypeGroup(['identifier' => 'Blocks']));
        $types->method('loadContentTypes')->willReturn([]);
        $types->method('loadContentTypeGroups')->willReturn([]);
        $command = new \Masilia\Bundle\IbexaBlocks\Command\ThemeCheckCommand(
            new ThemeChecker($this->registry(), $this->project),
            $types,
            $this->createStub(\Ibexa\Contracts\Core\Repository\PermissionResolver::class),
            new \Masilia\IbexaBlocks\Conventions(),
            $this->registry(),
            ['site' => ['standard'], 'standard' => []],
        );

        $tester = new CommandTester($command);
        $tester->execute(['design' => 'site']);

        self::assertStringContainsString('[orphan] the theme is in no design', $tester->getDisplay());
    }

    public function testAStandaloneThemeBuildsOnNone(): void
    {
        $command = new CommandTester(new ThemeCreateCommand(new ThemeScaffold($this->registry(), $this->project)));

        self::assertSame(0, $command->execute(['name' => 'solo', '--standalone' => true]));

        self::assertStringContainsString('standalone', (string) file_get_contents($this->project . '/templates/themes/solo/theme.yaml'));
        self::assertStringNotContainsString('@use', (string) file_get_contents($this->project . '/assets/themes/solo/scss/main.scss'));
        self::assertStringContainsString('ibexa_design_engine.design_list.solo: [solo]', $command->getDisplay());
        self::assertStringContainsString('notifications: false', (string) file_get_contents($this->project . '/templates/themes/solo/theme.yaml'), 'no notification template below a standalone theme');
        self::assertFileExists($this->project . '/templates/themes/solo/layout/header.twig');
        self::assertFileExists($this->project . '/templates/themes/solo/layout/footer.twig');
        self::assertSame(2, $command->execute(['name' => 'both', '--standalone' => true, '--extends' => 'standard']), 'one or the other');
    }

    public function testANewThemeNeverOverwritesAndNeedsAParentAndAGoodName(): void
    {
        $this->standardTheme();
        $create = fn (array $input) => (new CommandTester(new ThemeCreateCommand(new ThemeScaffold($this->registry(), $this->project))));

        $tester = $create([]);
        self::assertSame(2, $tester->execute(['name' => 'Bad Name']));
        self::assertSame(1, $tester->execute(['name' => 'standard']), 'a theme that exists');
        self::assertSame(1, $tester->execute(['name' => 'blog', '--extends' => 'nope']), 'a parent with no theme.yaml');
        self::assertDirectoryDoesNotExist($this->project . '/templates/themes/blog');
    }

    public function testTheFirstThemeOfTheChainThatSaysAThingWins(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/acme/theme.yaml', "favicons: build/images/acme/icons\nlayout: { notifications: false }\nfonts: ['https://fonts.googleapis.com/css2?family=X']\n");
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('acme');
        $settings = new ThemeSettings($this->registry(), new DesignChain($config, ['acme' => ['acme', 'standard']]));

        self::assertSame('build/images/acme/icons', $settings->favicons());
        self::assertFalse($settings->notifications());
        self::assertSame(['https://fonts.googleapis.com/css2?family=X'], $settings->fonts());
        self::assertSame('standard', $settings->assets()->entry, 'what acme does not say comes from standard');
    }
}
