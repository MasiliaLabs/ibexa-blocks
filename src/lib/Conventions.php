<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks;

/**
 * How the sites of a project are built: the part of the content model that changes from one project to the next.
 * One definition, read by the rendering, by the in-context editing toolbox and by the AI assistant, so that they agree
 * on what a page and a block are. The defaults are the Masilia way.
 */
final readonly class Conventions
{
    /**
     * @param string       $pageType      The content type of a page
     * @param string       $blocksField   The field of a page that holds its blocks (a list of relations)
     * @param string       $itemsField    The field of a block that holds its child items
     * @param array<string, string> $itemsFieldByType The field that holds the child items of the block types that do not call it $itemsField ("info_cards" => "cards")
     * @param string       $blockGroup    The group of content types that are blocks
     * @param list<string> $contentGroups The groups of the other content types the site is made of
     * @param string       $homeType      The content type of a home page (it holds blocks like a page)
     * @param string       $siteType      The content type of the container of a site (its settings)
     */
    public function __construct(
        public string $pageType = 'page',
        public string $blocksField = 'blocks',
        public string $itemsField = 'items',
        public string $blockGroup = 'Blocks',
        public array $contentGroups = ['Content', 'Block items', 'Media', 'Taxonomies', 'Configurations'],
        public array $itemsFieldByType = [],
        public string $homeType = 'home_page',
        public string $siteType = 'site',
    ) {
    }

    /** The field that holds the child items of a block of this type */
    public function itemsFieldFor(string $blockType): string
    {
        return $this->itemsFieldByType[$blockType] ?? $this->itemsField;
    }

    /**
     * @param array<string, mixed> $settings The "conventions" node of the bundle configuration
     */
    public static function fromSettings(array $settings): self
    {
        $defaults = new self();

        return new self(
            pageType: (string) ($settings['page_type'] ?? $defaults->pageType),
            blocksField: (string) ($settings['blocks_field'] ?? $defaults->blocksField),
            itemsField: (string) ($settings['items_field'] ?? $defaults->itemsField),
            blockGroup: (string) ($settings['block_group'] ?? $defaults->blockGroup),
            contentGroups: array_values(array_map('strval', (array) ($settings['content_groups'] ?? $defaults->contentGroups))),
            itemsFieldByType: array_map('strval', (array) ($settings['items_field_by_type'] ?? $defaults->itemsFieldByType)),
            homeType: (string) ($settings['home_type'] ?? $defaults->homeType),
            siteType: (string) ($settings['site_type'] ?? $defaults->siteType),
        );
    }
}
