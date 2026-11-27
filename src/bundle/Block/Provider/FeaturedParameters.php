<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block\Provider;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;

/**
 * A block whose view type (a selection field) has a given value is shown by another template: the grid of cards "Featured" as
 * the latest news. Settings: masilia_ibexa_blocks.blocks.lists.featured.
 */
final readonly class FeaturedParameters implements BlockViewParameters
{
    public function __construct(private SettingsProvider $provider)
    {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        if (!in_array($block->type(), $this->provider->listTypes('featured'), true)) {
            return [];
        }

        $settings = $this->provider->featured();
        if ((array_values((array) ($fields[$settings['field']] ?? []))[0] ?? null) === $settings['value']) {
            $view->useTemplate($settings['template']);
        }

        return [];
    }
}
