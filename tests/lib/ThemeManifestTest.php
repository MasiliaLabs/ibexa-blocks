<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;
use Masilia\IbexaBlocks\Theme\ThemeManifest;
use Masilia\IbexaBlocks\Theme\ViewDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThemeManifestTest extends TestCase
{
    public function testAThemeSaysWhatItProvidesPerViewTypeAndContentType(): void
    {
        $manifest = ThemeManifest::fromArray('acme', ['views' => [
            'blocks' => [
                'hero_banner' => ['template' => 'blocks/hero_banner.twig', 'variations' => ['background_image' => 'banner_hero']],
                'richtext' => 'blocks/richtext.twig',
            ],
            'block_item' => ['card_item' => ['template' => 'cards/card_item.twig', 'params' => ['compact' => true]]],
        ]]);

        self::assertSame('acme', $manifest->name);
        self::assertEquals(new ViewDefinition('blocks/hero_banner.twig', ['background_image' => 'banner_hero']), $manifest->view('blocks', 'hero_banner'));
        self::assertEquals(new ViewDefinition('blocks/richtext.twig'), $manifest->view('blocks', 'richtext'), 'a template alone is enough');
        self::assertSame(['compact' => true], $manifest->view('block_item', 'card_item')->params);
        self::assertNull($manifest->view('blocks', 'nope'));
        self::assertNull($manifest->view('line', 'hero_banner'), 'a view type of its own');
    }

    public function testAThemeThatSaysNothingIsValid(): void
    {
        self::assertSame([], ThemeManifest::fromArray('partner', [])->views);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalid(): iterable
    {
        yield 'a key that is a typo' => [['view' => []], 'unknown key "view"'];
        yield 'view types that are not a map' => [['views' => ['blocks' => 'x']], 'views.blocks must be a map'];
        yield 'a definition with no template' => [['views' => ['blocks' => ['a' => ['variations' => []]]]], 'views.blocks.a needs a template'];
        yield 'a key of a definition that is a typo' => [['views' => ['blocks' => ['a' => ['template' => 'a.twig', 'variation' => []]]]], 'unknown key "variation"'];
        yield 'a twig namespace' => [['views' => ['blocks' => ['a' => '@ibexadesign/a.twig']]], 'must be relative to the templates of the theme'];
        yield 'an absolute path' => [['views' => ['blocks' => ['a' => '/var/a.twig']]], 'must be relative'];
        yield 'a variation that is not a name' => [['views' => ['blocks' => ['a' => ['template' => 'a.twig', 'variations' => ['image' => ['x']]]]]], 'the variation of "image"'];
        yield 'a definition that is a number' => [['views' => ['blocks' => ['a' => 5]]], 'must be a template or a map'];
    }

    #[DataProvider('invalid')]
    public function testWhatTheMechanismCannotUseIsRefusedWithWhatToFix(array $data, string $message): void
    {
        $this->expectException(InvalidThemeManifest::class);
        $this->expectExceptionMessage($message);

        ThemeManifest::fromArray('t', $data);
    }

    public function testAThemeSaysWhatItsLayoutNeeds(): void
    {
        $manifest = ThemeManifest::fromArray('acme', [
            'assets' => ['directory' => 'assets/themes/fuelexit/', 'entry' => 'fuelexit', 'styles' => ['fa-icons'], 'extra' => ['admin-toolbar.js']],
            'favicons' => 'build/images/acme/icons/',
            'fonts' => ['https://fonts.googleapis.com/css2?family=Inter&display=swap'],
            'layout' => ['notifications' => false],
        ]);

        self::assertSame('assets/themes/fuelexit', $manifest->assets->directory);
        self::assertSame('fuelexit.css', $manifest->assets->entry . '.css');
        self::assertSame('assets/themes/fuelexit/scss/main.scss', $manifest->assets->stylesheetSource());
        self::assertSame('assets/themes/fuelexit/js/main.js', $manifest->assets->scriptSource());
        self::assertSame(['fa-icons'], $manifest->assets->styles);
        self::assertSame('build/images/acme/icons', $manifest->favicons);
        self::assertFalse($manifest->notifications);
        self::assertSame('standard', ThemeManifest::fromArray('t', ['assets' => ['entry' => 'standard']])->assets->entry);
        self::assertSame('assets/themes/t', ThemeManifest::fromArray('t', ['assets' => []])->assets->directory, 'the folder and the entry default to the name of the theme');
        self::assertNull(ThemeManifest::fromArray('t', [])->notifications, 'a theme that says nothing leaves it to the next one');
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidLayout(): iterable
    {
        yield 'a typo in assets' => [['assets' => ['dir' => 'x']], 'unknown key assets.dir'];
        yield 'an entry that is a path' => [['assets' => ['entry' => '../x']], 'assets.entry must be a plain name'];
        yield 'a directory that leaves the project' => [['assets' => ['directory' => '../../etc']], 'must stay in the project'];
        yield 'a font that is not https' => [['fonts' => ['http://x.org/a.css']], 'https URL'];
        yield 'a typo in layout' => [['layout' => ['notification' => true]], 'unknown key layout.notification'];
        yield 'assets that are not a map' => [['assets' => 'x'], 'assets must be a map'];
    }

    #[DataProvider('invalidLayout')]
    public function testWhatTheLayoutCannotUseIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidThemeManifest::class);
        $this->expectExceptionMessage($message);

        ThemeManifest::fromArray('t', $data);
    }

    public function testFaviconsAreAFolderNoneOrExplicitLinks(): void
    {
        $folder = ThemeManifest::fromArray('t', ['favicons' => 'build/images/t/icons/']);
        self::assertSame('build/images/t/icons', $folder->favicons);
        self::assertNull($folder->faviconLinks);

        self::assertSame('', ThemeManifest::fromArray('t', ['favicons' => ''])->favicons, 'an empty value means no icons, and still stops the themes below');

        $links = ThemeManifest::fromArray('t', ['favicons' => [
            ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => 'build/images/t/logo.svg'],
            ['rel' => 'apple-touch-icon', 'sizes' => '180x180', 'href' => 'build/images/t/touch.png'],
        ]]);
        self::assertNull($links->favicons);
        self::assertSame('build/images/t/logo.svg', $links->faviconLinks[0]['href']);
        self::assertSame('180x180', $links->faviconLinks[1]['sizes']);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function badFavicons(): iterable
    {
        yield 'a map' => [['favicons' => ['rel' => 'icon']], 'a folder (string) or a list of links'];
        yield 'no href' => [['favicons' => [['rel' => 'icon']]], 'needs a rel'];
        yield 'outside public' => [['favicons' => [['rel' => 'icon', 'href' => '../x.ico']]], 'path in public/'];
        yield 'an absolute url' => [['favicons' => [['rel' => 'icon', 'href' => 'https://x.test/a.ico']]], 'path in public/'];
        yield 'an unknown key' => [['favicons' => [['rel' => 'icon', 'href' => 'a.ico', 'color' => 'red']]], 'unknown key favicons[0].color'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('badFavicons')]
    public function testBadFaviconsAreRefused(array $data, string $message): void
    {
        $this->expectException(InvalidThemeManifest::class);
        $this->expectExceptionMessage($message);

        ThemeManifest::fromArray('t', $data);
    }

    public function testAFontIsAnHttpsUrlOrAPathOfPublic(): void
    {
        $manifest = ThemeManifest::fromArray('t', ['fonts' => ['https://fonts.bunny.net/css?family=inter', 'https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400..700', 'build/fonts/fonts.css']]);
        self::assertCount(3, $manifest->fonts, 'a URL may hold two dots (a weight range), a path may not');

        foreach (['http://x.test/a.css', '/etc/a.css', '../a.css', 'data:text/css,a', ''] as $bad) {
            try {
                ThemeManifest::fromArray('t', ['fonts' => [$bad]]);
                self::fail(sprintf('"%s" must be refused', $bad));
            } catch (InvalidThemeManifest $e) {
                self::assertStringContainsString('every font must be', $e->getMessage());
            }
        }
    }

    public function testPreconnectIsAListOfOrigins(): void
    {
        $manifest = ThemeManifest::fromArray('t', ['preconnect' => ['https://fonts.googleapis.com', ['origin' => 'https://fonts.gstatic.com', 'crossorigin' => true]]]);
        self::assertSame([['origin' => 'https://fonts.googleapis.com', 'crossorigin' => false], ['origin' => 'https://fonts.gstatic.com', 'crossorigin' => true]], $manifest->preconnect);
        self::assertSame([], ThemeManifest::fromArray('t', ['preconnect' => []])->preconnect);
        self::assertNull(ThemeManifest::fromArray('t', [])->preconnect);

        foreach ([['preconnect' => 'https://x.test'], ['preconnect' => ['http://x.test']], ['preconnect' => ['https://x.test/path']], ['preconnect' => [['origin' => 'https://x.test', 'crossorigin' => 'yes']]], ['preconnect' => [['origin' => 'https://x.test', 'as' => 'font']]]] as $bad) {
            try {
                ThemeManifest::fromArray('t', $bad);
                self::fail('must be refused: ' . json_encode($bad));
            } catch (InvalidThemeManifest $e) {
                self::assertStringContainsString('preconnect', $e->getMessage());
            }
        }
    }
}
