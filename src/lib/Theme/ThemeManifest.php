<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/**
 * What a theme says about itself in its theme.yaml: its assets, favicons, fonts and layout. It does not have to list its
 * templates: they are found by their name (blocks/<type>.twig, cards/<type>.twig, line/<type>.twig, full/<type>.twig, see
 * BlockRegistry), and what a theme does not write comes from the next theme of the design (the chain of the design engine).
 * `views` is still read, for the exception: a template that is not named after its content type, or image variations
 * (until a template asks for them itself). What a manifest names wins over the convention.
 *
 *     name: my_site            # optional, the folder name when absent
 *     assets: { directory: assets/themes/my_site, entry: my_site, styles: [fa-icons], extra: [admin-toolbar.js] }
 *     favicons: build/images/my_site/icons   # a folder (android-chrome-192x192.png, favicon-32x32.png, favicon-16x16.png, favicon.ico,
 *                                            # site.webmanifest: the ones it holds are linked), '' for none, or a list of links, see ThemeFavicons
 *     fonts: [https://fonts.googleapis.com/css2?family=Inter&display=swap]
 *     preconnect:                            # the origins the browser connects to early: a URL, or {origin, crossorigin}
 *         - https://fonts.googleapis.com
 *         - { origin: https://fonts.gstatic.com, crossorigin: true }
 *     layout: { notifications: false }
 *     views:
 *         blocks:
 *             hero_banner: { template: blocks/hero_banner.twig, variations: { background_image: banner_hero } }
 *             richtext: blocks/richtext.twig            # shorthand: just the template
 *         block_item:
 *             card_item: cards/card_item.twig
 *         full:
 *             page: page.twig
 *             '*': error.twig           # any other content: what a theme shows for the rest
 */
final readonly class ThemeManifest
{
    private const KNOWN_KEYS = ['name', 'views', 'assets', 'favicons', 'fonts', 'preconnect', 'layout'];
    private const KNOWN_ASSET_KEYS = ['directory', 'entry', 'styles', 'extra'];
    private const KNOWN_LAYOUT_KEYS = ['notifications'];
    private const KNOWN_VIEW_KEYS = ['template', 'variations', 'params'];

    /**
     * @param array<string, array<string, ViewDefinition>> $views View type => content type => definition
     */
    /**
     * @param list<string> $fonts Stylesheets of web fonts the layout links: https URLs, or paths in public/
     */
    public function __construct(
        public string $name,
        public array $views = [],
        public ?ThemeAssets $assets = null,
        public ?string $favicons = null,
        public array $fonts = [],
        public ?bool $notifications = null,
        /** @var list<array{rel: string, href: string, sizes?: string, type?: string}>|null The explicit links of `favicons` (then $favicons is null) */
        public ?array $faviconLinks = null,
        /** @var list<array{origin: string, crossorigin: bool}>|null The origins of `preconnect` ([] for none, null when the theme says nothing) */
        public ?array $preconnect = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data The parsed theme.yaml
     *
     * @throws InvalidThemeManifest
     */
    public static function fromArray(string $theme, array $data): self
    {
        foreach (array_diff(array_keys($data), self::KNOWN_KEYS) as $unknown) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": unknown key "%s" (known: %s).', $theme, $unknown, implode(', ', self::KNOWN_KEYS)));
        }

        $views = [];
        foreach ((array) ($data['views'] ?? []) as $viewType => $types) {
            if (!is_array($types)) {
                throw new InvalidThemeManifest(sprintf('Theme "%s": views.%s must be a map of content types.', $theme, $viewType));
            }
            foreach ($types as $type => $definition) {
                $views[(string) $viewType][(string) $type] = self::definition($theme, (string) $viewType, (string) $type, $definition);
            }
        }

        $layout = (array) ($data['layout'] ?? []);
        foreach (array_diff(array_keys($layout), self::KNOWN_LAYOUT_KEYS) as $unknown) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": unknown key layout.%s (known: %s).', $theme, $unknown, implode(', ', self::KNOWN_LAYOUT_KEYS)));
        }
        $fonts = (array) ($data['fonts'] ?? []);
        foreach ($fonts as $font) {
            // The https URL of a stylesheet of web fonts, or the path in public/ of one that the project hosts itself.
            if (!is_string($font) || $font === '' || (!str_starts_with($font, 'https://') && (str_contains($font, '..') || preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $font) === 1))) {
                throw new InvalidThemeManifest(sprintf('Theme "%s": every font must be the https URL of a stylesheet, or its path in public/ (build/fonts/fonts.css).', $theme));
            }
        }

        $favicons = isset($data['favicons']) ? ThemeFavicons::fromValue(sprintf('Theme "%s"', $theme), $data['favicons']) : null;

        return new self(
            (string) ($data['name'] ?? $theme),
            $views,
            isset($data['assets']) ? self::assets($theme, $data['assets']) : null,
            $favicons?->directory,
            array_values($fonts),
            isset($layout['notifications']) ? (bool) $layout['notifications'] : null,
            $favicons?->links,
            isset($data['preconnect']) ? self::preconnect($theme, $data['preconnect']) : null,
        );
    }

    /**
     * @return list<array{origin: string, crossorigin: bool}>
     */
    private static function preconnect(string $theme, mixed $data): array
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": preconnect is a list of origins (https://fonts.googleapis.com) or of {origin, crossorigin}.', $theme));
        }
        $origins = [];
        foreach ($data as $i => $item) {
            $origin = is_array($item) ? ($item['origin'] ?? null) : $item;
            $crossorigin = is_array($item) ? ($item['crossorigin'] ?? false) : false;
            if (is_array($item) && array_diff(array_keys($item), ['origin', 'crossorigin']) !== []) {
                throw new InvalidThemeManifest(sprintf('Theme "%s": preconnect[%d] knows only origin and crossorigin.', $theme, $i));
            }
            if (!is_string($origin) || preg_match('#^https://[A-Za-z0-9.-]+(:\d+)?$#', $origin) !== 1 || !is_bool($crossorigin)) {
                throw new InvalidThemeManifest(sprintf('Theme "%s": preconnect[%d] must be an origin ("https://fonts.gstatic.com", no path) and crossorigin true or false.', $theme, $i));
            }
            $origins[] = ['origin' => $origin, 'crossorigin' => $crossorigin];
        }

        return $origins;
    }

    private static function assets(string $theme, mixed $data): ThemeAssets
    {
        if (!is_array($data)) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": assets must be a map.', $theme));
        }
        foreach (array_diff(array_keys($data), self::KNOWN_ASSET_KEYS) as $unknown) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": unknown key assets.%s (known: %s).', $theme, $unknown, implode(', ', self::KNOWN_ASSET_KEYS)));
        }
        $directory = trim((string) ($data['directory'] ?? 'assets/themes/' . $theme), '/');
        $entry = (string) ($data['entry'] ?? $theme);
        if (preg_match('/^[A-Za-z0-9_-]+$/', $entry) !== 1 || str_contains($directory, '..')) {
            throw new InvalidThemeManifest(sprintf('Theme "%s": assets.entry must be a plain name and assets.directory must stay in the project.', $theme));
        }

        return new ThemeAssets($directory, $entry, array_values(array_map('strval', (array) ($data['styles'] ?? []))), array_values(array_map('strval', (array) ($data['extra'] ?? []))));
    }

    public function view(string $viewType, string $contentType): ?ViewDefinition
    {
        return $this->views[$viewType][$contentType] ?? null;
    }

    private static function definition(string $theme, string $viewType, string $type, mixed $definition): ViewDefinition
    {
        $where = sprintf('Theme "%s": views.%s.%s', $theme, $viewType, $type);
        if (is_string($definition)) {
            $definition = ['template' => $definition];
        }
        if (!is_array($definition)) {
            throw new InvalidThemeManifest($where . ' must be a template or a map with a template.');
        }
        foreach (array_diff(array_keys($definition), self::KNOWN_VIEW_KEYS) as $unknown) {
            throw new InvalidThemeManifest(sprintf('%s: unknown key "%s" (known: %s).', $where, $unknown, implode(', ', self::KNOWN_VIEW_KEYS)));
        }

        $template = $definition['template'] ?? null;
        if (!is_string($template) || $template === '') {
            throw new InvalidThemeManifest($where . ' needs a template.');
        }
        if (str_starts_with($template, '@') || str_starts_with($template, '/')) {
            throw new InvalidThemeManifest(sprintf('%s: the template "%s" must be relative to the templates of the theme ("blocks/hero.twig"), not a Twig namespace or a path.', $where, $template));
        }

        $variations = [];
        foreach ((array) ($definition['variations'] ?? []) as $field => $variation) {
            if (!is_string($variation) || $variation === '') {
                throw new InvalidThemeManifest(sprintf('%s: the variation of "%s" must be the name of an image variation.', $where, $field));
            }
            $variations[(string) $field] = $variation;
        }

        return new ViewDefinition($template, $variations, (array) ($definition['params'] ?? []));
    }
}
