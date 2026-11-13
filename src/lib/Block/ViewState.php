<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Block;

/**
 * What a block is being shown with: the view type, the parameters the view was asked with, and the template, which a
 * provider of parameters can change (a grid of "featured" cards is shown by another template than a plain grid).
 */
final class ViewState
{
    private ?string $template = null;

    /**
     * @param array<string, mixed> $parameters The parameters of the view (the ones the page passed to the block)
     */
    public function __construct(
        public readonly string $viewType,
        public readonly array $parameters = [],
    ) {
    }

    /** @param string $template Relative to the templates of the theme ("blocks/latest_news.twig") */
    public function useTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function template(): ?string
    {
        return $this->template;
    }
}
