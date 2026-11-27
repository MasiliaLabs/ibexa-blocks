<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Site;

use Masilia\Bundle\IbexaBlocks\Model\ModelSource;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Bundle\IbexaBlocks\Model\TypeDefinition;
use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use PHPUnit\Framework\TestCase;

/** The folders of themes and of content types are those of the site of the request, in a worker that serves several. */
final class DirectoriesPerSiteTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blocks-dirs-' . bin2hex(random_bytes(4));
        file_put_contents($this->file('a/acme/theme.yaml'), "fonts: [build/fonts/a.css]\n");
        file_put_contents($this->file('b/acme/theme.yaml'), "fonts: [build/fonts/b.css]\n");
        file_put_contents($this->file('m1/hero.yaml'), "identifier: hero\ngroup: Blocks\nnames: {eng-GB: Hero}\nfields:\n  - {identifier: title, type: ibexa_string}\n");
        file_put_contents($this->file('m2/cta.yaml'), "identifier: cta\ngroup: Blocks\nnames: {eng-GB: Cta}\nfields:\n  - {identifier: title, type: ibexa_string}\n");
    }

    private function file(string $path): string
    {
        @mkdir(dirname($this->root . '/' . $path), 0777, true);

        return $this->root . '/' . $path;
    }

    public function testTheThemesAreLookedForInTheFoldersOfTheSite(): void
    {
        $site = fn (string $dir) => TestSettings::fromParameters(['masilia_ibexa_blocks:themes.directories' => [$this->root . '/' . $dir]]);

        self::assertSame(['build/fonts/a.css'], (new ThemeRegistry([$this->root . '/none'], $site('a')))->manifest('acme')?->fonts);
        self::assertSame(['build/fonts/b.css'], (new ThemeRegistry([$this->root . '/none'], $site('b')))->manifest('acme')?->fonts);
        self::assertSame(['acme'], (new ThemeRegistry([$this->root . '/a']))->names(), 'a site that says nothing has the folders of the project');
    }

    public function testTheContentTypesAreThoseOfTheFoldersTheSiteAddsToTheOnesAlwaysRead(): void
    {
        $names = static fn (ModelSource $s): array => array_map(static fn (TypeDefinition $t): string => $t->identifier, $s->project());
        $with = static fn (string $dir): ModelSource => new ModelSource([], settings: TestSettings::fromParameters(['masilia_ibexa_blocks:model.directories' => [$dir]]));

        self::assertSame(['hero'], $names($with($this->root . '/m1')));
        self::assertSame(['cta'], $names($with($this->root . '/m2')));
        self::assertSame(['hero'], $names(new ModelSource([$this->root . '/m1'])), 'what is always read, without a setting of the site');
    }
}
