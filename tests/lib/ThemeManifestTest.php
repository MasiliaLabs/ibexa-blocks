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
}
