<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\IbexaBlocks\Conventions;
use Masilia\IbexaBlocks\Theme\ViewDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Loader\LoaderInterface;

/**
 * How the current design shows a content type in a view type. A theme does not have to say it: the template is found by its
 * name, "<folder of the view type>/<content type>.twig", in the themes of the design (the design engine's chain, so a theme
 * overrides a template by having a file of the same name).
 *
 *     blocks      blocks/<type>.twig        a block of a page
 *     block_item  cards/<type>.twig         an item of a block (a card, a slide…)
 *     line        line/<type>.twig          a line of a list
 *     embed       embed/<type>.twig, then line/<type>.twig
 *     full        full/<type>.twig          a page of its own (an article, an event…)
 *
 * What a type has no template for falls back to a template that serves for the rest: the page of blocks of the package for a
 * type that holds blocks, then "<folder>/_default.twig". A view the theme.yaml of a theme names (views:) wins over the
 * convention, which keeps themes that predate it, and the exception, working.
 */
final class BlockRegistry
{
    /** The content type that stands for every type no theme names: `*` ("any other content"). */
    public const ANY = '*';

    /** The template that shows the blocks of a page, for any type that has the blocks field. */
    public const BLOCKS_PAGE = '@MasiliaIbexaBlocks/full/blocks_page.twig';

    /** The templates of a view type, by folder, when it is not simply the name of the view type. */
    private const FOLDERS = [
        'block_item' => ['cards'],
        'embed' => ['embed', 'line'],
    ];

    /** @var array<string, ResolvedView|null> */
    private array $known = [];

    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly DesignChain $design,
        #[Autowire(service: 'twig.loader')]
        private readonly ?LoaderInterface $loader = null,
        private readonly ?SettingsProvider $provider = null,
    ) {
    }

    /** The view of a content, with what the convention needs to know of its type (does it hold blocks). */
    public function findFor(string $viewType, ContentType $type): ?ResolvedView
    {
        return $this->find($viewType, $type->identifier, $type->getFieldDefinition($this->conventions()->blocksField) !== null);
    }

    /**
     * In this order: what a theme of the design names for the type, the template the convention names for it, the page of
     * blocks if the type holds blocks, what a theme names for any content, the "_default" template of the folder.
     *
     * @param bool $holdsBlocks Whether the type has the field that holds the blocks of a page
     */
    public function find(string $viewType, string $contentType, bool $holdsBlocks = false): ?ResolvedView
    {
        $key = implode('|', [...$this->design->themes(), $viewType, $contentType, (int) $holdsBlocks]);
        if (!array_key_exists($key, $this->known)) {
            $this->known[$key] = $this->resolve($viewType, $contentType, $holdsBlocks);
        }

        return $this->known[$key];
    }

    private function resolve(string $viewType, string $contentType, bool $holdsBlocks): ?ResolvedView
    {
        return $this->named($viewType, $contentType)
            ?? $this->byConvention($viewType, $contentType)
            ?? ($holdsBlocks && $viewType === 'full' ? new ResolvedView(new ViewDefinition(self::BLOCKS_PAGE), self::BLOCKS_PAGE, null) : null)
            ?? $this->named($viewType, self::ANY)
            ?? $this->byConvention($viewType, '_default');
    }

    /** What a theme.yaml of the design says: the first theme that says it wins. */
    private function named(string $viewType, string $contentType): ?ResolvedView
    {
        foreach ($this->design->themes() as $theme) {
            $definition = $this->themes->manifest($theme)?->view($viewType, $contentType);
            if ($definition !== null) {
                return new ResolvedView($definition, '@ibexadesign/' . ltrim($definition->template, '/'), $theme);
            }
        }

        return null;
    }

    private function byConvention(string $viewType, string $name): ?ResolvedView
    {
        if ($this->loader === null) {
            return null;
        }
        foreach (self::FOLDERS[$viewType] ?? [$viewType] as $folder) {
            $relative = sprintf('%s/%s.twig', $folder, $name);
            if ($this->loader->exists('@ibexadesign/' . $relative)) {
                return new ResolvedView(new ViewDefinition($relative), '@ibexadesign/' . $relative, null);
            }
        }

        return null;
    }

    /** The folders where the templates of a view type are, in the order they are looked in. */
    public static function folders(string $viewType): array
    {
        return self::FOLDERS[$viewType] ?? [$viewType];
    }

    /**
     * @return list<string> The themes of the design that were looked in, for a message
     */
    public function searched(): array
    {
        return $this->design->themes();
    }

    private function conventions(): Conventions
    {
        return $this->provider?->conventions() ?? new Conventions();
    }
}
