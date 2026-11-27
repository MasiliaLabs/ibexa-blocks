<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Theme\InvalidThemeManifest;
use Masilia\IbexaBlocks\Theme\ThemeFavicons;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThemeFaviconsTest extends TestCase
{
    public function testAFolderIsTrimmedAndNoneIsTheEmptyString(): void
    {
        $folder = ThemeFavicons::fromValue('Theme "t"', '/build/images/t/icons/');
        self::assertSame('build/images/t/icons', $folder->directory);
        self::assertNull($folder->links);

        self::assertSame('', ThemeFavicons::fromValue('Theme "t"', '')->directory);
    }

    public function testLinksKeepTheirRelHrefSizesAndType(): void
    {
        $favicons = ThemeFavicons::fromValue('x', [
            ['rel' => 'icon', 'href' => 'build/a.svg', 'type' => 'image/svg+xml'],
            ['rel' => 'apple-touch-icon', 'href' => 'build/a.png', 'sizes' => '180x180'],
            ['rel' => 'shortcut icon', 'href' => 'build/a.ico'],
        ]);

        self::assertNull($favicons->directory);
        self::assertSame([
            ['rel' => 'icon', 'href' => 'build/a.svg', 'type' => 'image/svg+xml'],
            ['rel' => 'apple-touch-icon', 'href' => 'build/a.png', 'sizes' => '180x180'],
            ['rel' => 'shortcut icon', 'href' => 'build/a.ico'],
        ], $favicons->links);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function refused(): iterable
    {
        yield 'a number' => [12, 'a folder (string) or a list of links'];
        yield 'a map, not a list' => [['rel' => 'icon', 'href' => 'a'], 'a folder (string) or a list of links'];
        yield 'a link that is not a map' => [['build/a.ico'], 'favicons[0] must be a map'];
        yield 'no rel' => [[['href' => 'build/a.ico']], 'needs a rel'];
        yield 'a rel with markup' => [[['rel' => 'icon"><script>', 'href' => 'build/a.ico']], 'needs a rel'];
        yield 'an absolute path' => [[['rel' => 'icon', 'href' => '/etc/a.ico']], 'a path in public/'];
        yield 'a url' => [[['rel' => 'icon', 'href' => 'https://x.test/a.ico']], 'a path in public/'];
        yield 'a parent path' => [[['rel' => 'icon', 'href' => '../a.ico']], 'a path in public/'];
        yield 'an unknown key' => [[['rel' => 'icon', 'href' => 'a.ico', 'media' => 'x']], 'unknown key favicons[0].media'];
        yield 'a size with markup' => [[['rel' => 'icon', 'href' => 'a.ico', 'sizes' => '1"2']], 'favicons[0].sizes is not valid'];
    }

    #[DataProvider('refused')]
    public function testWhatCannotBeALinkIsRefusedWithTheWhereInTheMessage(mixed $value, string $message): void
    {
        $this->expectException(InvalidThemeManifest::class);
        $this->expectExceptionMessage($message);

        ThemeFavicons::fromValue('masilia_ibexa_blocks.favicons', $value);
    }

    public function testTheMessageNamesTheSource(): void
    {
        try {
            ThemeFavicons::fromValue('Theme "acme"', 3);
            self::fail('refused');
        } catch (InvalidThemeManifest $e) {
            self::assertStringStartsWith('Theme "acme":', $e->getMessage());
        }
    }
}
