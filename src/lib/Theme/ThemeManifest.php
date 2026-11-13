<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/**
 * What a theme says about itself in its theme.yaml: for each view type ("blocks", "block_item", "line"), for each content type,
 * the template and the image variations. A theme only says what it provides: what it does not say comes from the next theme of
 * the design (the chain of the design engine), so a theme that changes two blocks lists two blocks.
 *
 *     name: my_site            # optional, the folder name when absent
 *     assets: { directory: assets/themes/my_site, entry: my_site, styles: [fa-icons], extra: [admin-toolbar.js] }
 *     favicons: build/images/my_site/icons   # android-chrome-192x192.png, favicon-32x32.png, favicon-16x16.png, favicon.ico, site.webmanifest
 *     fonts: [https://fonts.googleapis.com/css2?family=Inter&display=swap]
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
    private const KNOWN_KEYS = ['name', 'views', 'assets', 'favicons', 'fonts', 'layout'];
    private const KNOWN_ASSET_KEYS = ['directory', 'entry', 'styles', 'extra'];
    private const KNOWN_LAYOUT_KEYS = ['notifications'];
    private const KNOWN_VIEW_KEYS = ['template', 'variations', 'params'];

    /**
     * @param array<string, array<string, ViewDefinition>> $views View type => content type => definition
     */
    /**
     * @param list<string> $fonts Stylesheets of web fonts the layout links
     */
    public function __construct(
        public string $name,
        public array $views = [],
        public ?ThemeAssets $assets = null,
        public ?string $favicons = null,
        public array $fonts = [],
        public ?bool $notifications = null,
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
            if (!is_string($font) || !str_starts_with($font, 'https://')) {
                throw new InvalidThemeManifest(sprintf('Theme "%s": every font must be the https URL of a stylesheet.', $theme));
            }
        }

        return new self(
            (string) ($data['name'] ?? $theme),
            $views,
            isset($data['assets']) ? self::assets($theme, $data['assets']) : null,
            isset($data['favicons']) ? trim((string) $data['favicons'], '/') : null,
            array_values($fonts),
            isset($layout['notifications']) ? (bool) $layout['notifications'] : null,
        );
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
