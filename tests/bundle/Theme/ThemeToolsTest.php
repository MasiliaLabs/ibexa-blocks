<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Command\ThemeCreateCommand;
use Masilia\Bundle\IbexaBlocks\Command\ThemesCommand;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\FaviconSet;
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
            \Masilia\IbexaBlocks\Tests\Support\TestSettings::fromParameters([]),
            $this->registry(),
            $this->project,
            ['site' => ['standard'], 'standard' => []],
        );

        $tester = new CommandTester($command);
        $tester->execute(['design' => 'site']);

        self::assertStringContainsString('[orphan] the theme is in no design', $tester->getDisplay());
    }

    public function testATheNotInTheBuildOfTheAssetsIsAnError(): void
    {
        $this->standardTheme();
        $types = $this->createStub(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $types->method('loadContentTypeGroupByIdentifier')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\ContentTypeGroup(['identifier' => 'Blocks']));
        $types->method('loadContentTypes')->willReturn([]);
        $types->method('loadContentTypeGroups')->willReturn([]);
        $make = fn () => new CommandTester(new \Masilia\Bundle\IbexaBlocks\Command\ThemeCheckCommand(
            new ThemeChecker($this->registry(), $this->project),
            $types,
            $this->createStub(\Ibexa\Contracts\Core\Repository\PermissionResolver::class),
            \Masilia\IbexaBlocks\Tests\Support\TestSettings::fromParameters([]),
            $this->registry(),
            $this->project,
            ['standard' => []],
        ));

        $tester = $make();
        $tester->execute(['design' => 'standard']);
        self::assertStringNotContainsString('entrypoints.json', $tester->getDisplay(), 'no build yet: nothing to say about it');

        $this->write('public/build/entrypoints.json', json_encode(['entrypoints' => ['app' => [], 'standard.css' => []]], JSON_THROW_ON_ERROR));
        $tester = $make();
        $tester->execute(['design' => 'standard']);
        self::assertStringContainsString('the entry "standard.js" is not in public/build/entrypoints.json', $tester->getDisplay());
        self::assertStringNotContainsString('the entry "standard.css"', $tester->getDisplay());
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

    /** @param array<string, mixed> $site The setting `favicons` of the site, when it has one */
    private function settingsOf(string $design, array|string|null $site = null): ThemeSettings
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('hasParameter')->willReturn($site !== null);
        $config->method('getParameter')->willReturnCallback(static fn (string $name) => $name === 'favicons' ? $site : $design);

        return new ThemeSettings($this->registry(), new DesignChain($config, [$design => [$design, 'standard']]), new FaviconSet($this->project . '/public'), $config);
    }

    private function icons(string ...$files): void
    {
        foreach ($files as $file) {
            $this->write('public/build/images/acme/icons/' . $file, 'x');
        }
    }

    public function testOnlyTheIconsThatAreThereAreLinked(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/acme/theme.yaml', "favicons: build/images/acme/icons\n");
        $this->icons('favicon.ico', 'favicon-32x32.png', 'Favicon-16x16.png');

        $links = $this->settingsOf('acme')->faviconLinks();

        self::assertSame(['icon', 'shortcut icon', 'icon'], array_column($links, 'rel'), 'favicon-16x16.png is spelled Favicon-16x16.png: no link, the exact name is what a server serves');
        self::assertSame('build/images/acme/icons/favicon-32x32.png', $links[0]['href']);
        self::assertSame('image/x-icon', $links[2]['type']);
    }

    public function testExplicitLinksNoIconsAndTheSiteSetting(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/acme/theme.yaml', "favicons:\n  - { rel: icon, type: image/svg+xml, href: build/images/acme/logo.svg }\n");
        $this->write('templates/themes/bare/theme.yaml', "favicons: ''\n");
        $this->write('templates/themes/standard/theme.yaml', "favicons: build/images/std/icons\n");
        $this->write('public/build/images/std/icons/favicon.ico', 'x');
        $this->icons('favicon.ico');

        self::assertSame([['rel' => 'icon', 'href' => 'build/images/acme/logo.svg', 'type' => 'image/svg+xml']], $this->settingsOf('acme')->faviconLinks());
        self::assertSame([], $this->settingsOf('bare')->faviconLinks(), 'an empty value stops the icons of the theme below');
        self::assertSame('build/images/std/icons/favicon.ico', $this->settingsOf('nothing')->faviconLinks()[0]['href'], 'a theme that says nothing takes the next one');
        self::assertSame('build/images/acme/icons/favicon.ico', $this->settingsOf('acme', 'build/images/acme/icons')->faviconLinks()[0]['href'], 'the setting of the site wins over its theme');
    }

    public function testTheCheckerSaysACaseMismatchAMissingFileAndAFolderThatIsNotThere(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/acme/theme.yaml', "favicons: build/images/acme/icons\n");
        $this->write('templates/themes/logo/theme.yaml', "favicons:\n  - { rel: icon, href: build/images/logo/Logo.svg }\n  - { rel: icon, href: build/images/logo/none.svg }\n");
        $this->write('templates/themes/gone/theme.yaml', "favicons: build/images/gone\n");
        $this->icons('Favicon.ico', 'favicon-32x32.png');
        $this->write('public/build/images/logo/logo.svg', 'x');
        $checker = new ThemeChecker($this->registry(), $this->project);
        $messages = static fn (array $issues): array => array_map(static fn (array $i): string => $i['level'] . ' ' . $i['message'], $issues);

        $acme = implode("\n", $messages($checker->check(['acme', 'standard'], [])));
        self::assertStringContainsString('error favicons: "build/images/acme/icons/favicon.ico" is there as "Favicon.ico"', $acme);
        self::assertStringContainsString('notice favicons: build/images/acme/icons/site.webmanifest is not there', $acme);
        self::assertStringNotContainsString('favicon-32x32.png', $acme);

        $logo = implode("\n", $messages($checker->check(['logo', 'standard'], [])));
        self::assertStringContainsString('"build/images/logo/Logo.svg" is there as "logo.svg"', $logo);
        self::assertStringContainsString('error favicons: build/images/logo/none.svg is not a file of public/', $logo);

        self::assertStringContainsString('error favicons: build/images/gone is not a folder of public/', implode("\n", $messages($checker->check(['gone', 'standard'], []))));
    }

    public function testTheCheckerSaysNothingOfFaviconsBeforeTheBuild(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/acme/theme.yaml', "favicons: build/images/acme/icons\n");

        self::assertSame([], array_filter((new ThemeChecker($this->registry(), $this->project))->check(['acme', 'standard'], []), static fn (array $i): bool => str_contains($i['message'], 'favicons')));
    }

    public function testThePreconnectOriginsAreThoseOfTheFirstThemeThatSaysSo(): void
    {
        $this->write('templates/themes/standard/theme.yaml', "preconnect:\n  - https://fonts.googleapis.com\n  - { origin: https://fonts.gstatic.com, crossorigin: true }\n");
        $this->write('templates/themes/acme/theme.yaml', "fonts: [build/fonts/own.css]\npreconnect: []\n");
        $this->write('templates/themes/quiet/theme.yaml', "layout: { notifications: false }\n");

        self::assertSame([
            ['origin' => 'https://fonts.googleapis.com', 'crossorigin' => false],
            ['origin' => 'https://fonts.gstatic.com', 'crossorigin' => true],
        ], $this->settingsOf('quiet')->preconnect(), 'a theme that says nothing takes the next one');
        self::assertSame([], $this->settingsOf('acme')->preconnect(), 'an empty list says none, and stops the search');
    }

    public function testATemplateASettingNamesIsNotDead(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/standard/blocks/latest_news.twig', 'x');
        $this->write('templates/themes/standard/blocks/dead.twig', 'x');
        $text = static fn (array $issues): string => implode("\n", array_map(static fn (array $i): string => $i['message'], $issues));

        $without = $text((new ThemeChecker($this->registry(), $this->project))->check(['standard'], ['hero']));
        self::assertStringContainsString('blocks/latest_news.twig is named after no content type', $without);

        $with = $text((new ThemeChecker($this->registry(), $this->project, [], \Masilia\IbexaBlocks\Tests\Support\TestSettings::fromParameters(['masilia_ibexa_blocks:blocks.lists.featured.types' => ['grid_cards']])))->check(['standard'], ['hero']));
        self::assertStringNotContainsString('latest_news.twig', $with, 'the Featured list of the bundle settings names it');
        self::assertStringContainsString('blocks/dead.twig is named after no content type', $with, 'the others are still checked');
    }

    public function testAFolderAThemeIncludesFromByABuiltNameIsNotDeadForThatThemeOnly(): void
    {
        $this->standardTheme();
        $this->write('templates/themes/standard/blocks/info_cards.twig', "{% include '@ibexadesign/cards/' ~ type ~ '.twig' %}");
        $this->write('templates/themes/standard/cards/metric.twig', 'x');
        $this->write('templates/themes/light/theme.yaml', "layout: { notifications: false }\n");
        $this->write('templates/themes/light/cards/metric.twig', 'x');
        $text = static fn (array $issues): string => implode("\n", array_map(static fn (array $i): string => ($i['theme'] ?? '') . ': ' . $i['message'], $issues));

        $issues = $text((new ThemeChecker($this->registry(), $this->project))->check(['light', 'standard'], []));

        self::assertStringNotContainsString('standard: cards/metric.twig is named after no content type', $issues, 'standard builds the name of the card it includes');
        self::assertStringContainsString('light: cards/metric.twig is named after no content type', $issues, 'light does not: its card is dead unless something names it');
    }
}
