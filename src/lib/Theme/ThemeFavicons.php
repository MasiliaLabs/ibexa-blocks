<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/**
 * What a theme.yaml (or the site setting `favicons`) says about the icons of a site, in one of two forms:
 *
 *     favicons: build/images/my_site/icons    # a folder: the icons it holds are linked (see FaviconSet)
 *     favicons: ''                            # no icons at all, whatever the themes below say
 *     favicons:                               # explicit links, for a set the convention does not know
 *         - { rel: icon, type: image/svg+xml, href: build/images/my_site/logo.svg }
 *         - { rel: apple-touch-icon, sizes: 180x180, href: build/images/my_site/touch.png }
 *
 * `href` is a path in public/, as `asset()` takes it.
 */
final readonly class ThemeFavicons
{
    private const LINK_KEYS = ['rel', 'href', 'sizes', 'type'];

    /**
     * @param list<array{rel: string, href: string, sizes?: string, type?: string}>|null $links
     */
    public function __construct(
        public ?string $directory,
        public ?array $links,
    ) {
    }

    /**
     * @throws InvalidThemeManifest
     */
    public static function fromValue(string $where, mixed $value): self
    {
        if (is_string($value)) {
            return new self(trim($value, '/'), null);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidThemeManifest(sprintf('%s: favicons is a folder (string) or a list of links ({rel, href, sizes, type}).', $where));
        }

        $links = [];
        foreach ($value as $i => $link) {
            if (!is_array($link)) {
                throw new InvalidThemeManifest(sprintf('%s: favicons[%d] must be a map {rel, href, sizes, type}.', $where, $i));
            }
            foreach (array_diff(array_keys($link), self::LINK_KEYS) as $unknown) {
                throw new InvalidThemeManifest(sprintf('%s: unknown key favicons[%d].%s (known: %s).', $where, $i, $unknown, implode(', ', self::LINK_KEYS)));
            }
            $rel = $link['rel'] ?? null;
            $href = $link['href'] ?? null;
            if (!is_string($rel) || preg_match('/^[A-Za-z][A-Za-z -]*$/', $rel) !== 1 || !is_string($href) || $href === '' || str_contains($href, '..') || preg_match('#^[a-z]+:|^/#i', $href) === 1) {
                throw new InvalidThemeManifest(sprintf('%s: favicons[%d] needs a rel ("icon") and an href that is a path in public/ ("build/images/site/logo.svg").', $where, $i));
            }
            $entry = ['rel' => $rel, 'href' => $href];
            foreach (['sizes', 'type'] as $key) {
                if (isset($link[$key])) {
                    if (!is_string($link[$key]) || preg_match('/^[\w.+\/-]+$/', $link[$key]) !== 1) {
                        throw new InvalidThemeManifest(sprintf('%s: favicons[%d].%s is not valid.', $where, $i, $key));
                    }
                    $entry[$key] = $link[$key];
                }
            }
            $links[] = $entry;
        }

        return new self(null, $links);
    }
}
