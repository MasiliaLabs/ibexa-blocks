<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

/**
 * The icon links of a folder of favicons. The folder is read from the disk, so a link is made only for a file that is there, under
 * the exact name it has (a file called Favicon.ico is not favicon.ico on a server that tells the two apart).
 */
final readonly class FaviconSet
{
    /** File => the link it gets. The apple-touch-icon is the 192px Android icon, as the layout has always linked it. */
    public const FILES = [
        ['android-chrome-192x192.png', ['rel' => 'apple-touch-icon', 'sizes' => '180x180']],
        ['favicon-32x32.png', ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32']],
        ['favicon-16x16.png', ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '16x16']],
        ['site.webmanifest', ['rel' => 'manifest']],
        ['favicon.ico', ['rel' => 'shortcut icon']],
        ['favicon.ico', ['rel' => 'icon', 'type' => 'image/x-icon']],
    ];

    public function __construct(private string $publicDir)
    {
    }

    /**
     * @param string $directory A folder of public/, "build/images/site/icons"
     *
     * @return list<array{rel: string, href: string, sizes?: string, type?: string}>
     */
    public function links(string $directory): array
    {
        $present = array_flip($this->files($directory));
        $links = [];
        foreach (self::FILES as [$file, $attributes]) {
            if (isset($present[$file])) {
                $links[] = $attributes + ['href' => $directory . '/' . $file];
            }
        }

        return $links;
    }

    /** @return list<string> The names of the files of the folder, as the disk spells them (empty when it is not there) */
    public function files(string $directory): array
    {
        $path = $this->publicDir . '/' . trim($directory, '/');

        return is_dir($path) ? array_values(array_diff(scandir($path) ?: [], ['.', '..'])) : [];
    }
}
