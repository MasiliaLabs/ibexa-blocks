<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block\Provider;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Search\ContentLister;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;

/**
 * A block with no items of its own and "auto" as its source fills itself with the latest contents of the chosen type (the grid or
 * the slider of the latest articles). The blocks it serves and the names of their fields are the settings
 * (masilia_ibexa_blocks.blocks.lists.auto_items).
 */
final readonly class AutoItemsParameters implements BlockViewParameters
{
    /**
     * @param array{items_field: string, source_field: string, source_value: string, type_field: string, count_field: string, default_count: int} $settings
     */
    public function __construct(
        private ContentLister $latest,
        private array $settings,
    ) {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        if (!empty($fields[$this->settings['items_field']])) {
            return [];
        }

        $source = array_values((array) ($fields[$this->settings['source_field']] ?? []))[0] ?? null;
        $type = array_values((array) ($fields[$this->settings['type_field']] ?? []))[0] ?? null;
        if ($source !== $this->settings['source_value'] || empty($type)) {
            return [];
        }

        $count = (int) ($fields[$this->settings['count_field']] ?? 0);

        return [$this->settings['items_field'] => $this->latest->of((string) $type, $count > 0 ? $count : $this->settings['default_count'])];
    }
}
