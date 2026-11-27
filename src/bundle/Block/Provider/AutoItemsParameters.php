<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block\Provider;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
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
    public function __construct(
        private ContentLister $latest,
        private SettingsProvider $provider,
    ) {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        if (!in_array($block->type(), $this->provider->listTypes('auto_items'), true)) {
            return [];
        }

        $settings = $this->provider->autoItems();
        $conventions = $this->provider->conventions();
        $itemsField = $conventions->itemsFieldByType === [] ? $conventions->itemsField : $conventions->itemsFieldFor($block->type());
        if (!empty($fields[$itemsField])) {
            return [];
        }

        $source = array_values((array) ($fields[$settings['source_field']] ?? []))[0] ?? null;
        $type = array_values((array) ($fields[$settings['type_field']] ?? []))[0] ?? null;
        if ($source !== $settings['source_value'] || empty($type)) {
            return [];
        }

        $count = (int) ($fields[$settings['count_field']] ?? 0);

        return [$itemsField => $this->latest->of((string) $type, $count > 0 ? $count : $settings['default_count'])];
    }
}
