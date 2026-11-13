<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Templating;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Twig\Extension\RuntimeExtensionInterface;

/** Shows the blocks of a page, each by its own view (so each is cached and tagged by itself). */
final readonly class BlocksRuntime implements RuntimeExtensionInterface
{
    public function __construct(private FragmentHandler $fragments)
    {
    }

    /**
     * @param iterable<mixed>      $blocks   The blocks of the page: renderable contents (anything else is skipped)
     * @param array<string, mixed> $params   Parameters every block is shown with (the breadcrumb, the current location…)
     * @param string               $viewType The view type the blocks are shown in
     */
    public function render(iterable $blocks, array $params = [], string $viewType = 'blocks'): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if (!$block instanceof RenderableContent) {
                continue;
            }
            $html .= $this->fragments->render(new ControllerReference('ibexa_content::viewAction', [
                'contentId' => $block->id(),
                'viewType' => $viewType,
                'params' => $params,
            ]));
        }

        return $html;
    }
}
