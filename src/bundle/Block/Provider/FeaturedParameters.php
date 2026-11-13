<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block\Provider;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;

/**
 * A block whose view type (a selection field) has a given value is shown by another template: the grid of cards "Featured" as
 * the latest news. Settings: masilia_ibexa_blocks.blocks.lists.featured.
 */
final readonly class FeaturedParameters implements BlockViewParameters
{
    /**
     * @param array{field: string, value: string, template: string} $settings The template is relative to the templates of a theme ("blocks/latest_news.twig")
     */
    public function __construct(private array $settings)
    {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        if ((array_values((array) ($fields[$this->settings['field']] ?? []))[0] ?? null) === $this->settings['value']) {
            $view->useTemplate($this->settings['template']);
        }

        return [];
    }
}
