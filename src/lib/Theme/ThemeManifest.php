<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/**
 * What a theme says about itself in its theme.yaml: for each view type ("blocks", "block_item", "line"), for each content type,
 * the template and the image variations. A theme only says what it provides: what it does not say comes from the next theme of
 * the design (the chain of the design engine), so a theme that changes two blocks lists two blocks.
 *
 *     name: fossil_exit            # optional, the folder name when absent
 *     views:
 *         blocks:
 *             hero_banner: { template: blocks/hero_banner.twig, variations: { background_image: banner_hero } }
 *             richtext: blocks/richtext.twig            # shorthand: just the template
 *         block_item:
 *             card_item: cards/card_item.twig
 */
final readonly class ThemeManifest
{
    private const KNOWN_KEYS = ['name', 'views'];
    private const KNOWN_VIEW_KEYS = ['template', 'variations', 'params'];

    /**
     * @param array<string, array<string, ViewDefinition>> $views View type => content type => definition
     */
    public function __construct(
        public string $name,
        public array $views = [],
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

        return new self((string) ($data['name'] ?? $theme), $views);
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
