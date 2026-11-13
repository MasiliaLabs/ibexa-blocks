<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\View;

use Ibexa\Core\MVC\Symfony\View\ContentView;
use Ibexa\Core\MVC\Symfony\View\View;
use Ibexa\Core\MVC\Symfony\View\ViewProvider;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Controller\BlockController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Shows a content in the view types of a page of blocks ("blocks", "block_item", "line") when a theme says how or has a template named for it, and the project
 * has not configured that view itself. It is a view provider of lower priority than the project's own content_view rules, so
 * a view the project writes by hand still wins (a block with a controller of its own, say).
 */
final readonly class BlockViewProvider implements ViewProvider
{
    /**
     * @param list<string> $viewTypes
     */
    public function __construct(
        private BlockRegistry $registry,
        #[Autowire('%masilia_blocks.blocks.view_types%')]
        private array $viewTypes,
    ) {
    }

    public function getView(View $view): ?View
    {
        if (!$view instanceof ContentView || !in_array($view->getViewType(), $this->viewTypes, true)) {
            return null;
        }

        $content = $view->getContent();
        if ($content === null || $this->registry->findFor($view->getViewType(), $content->getContentType()) === null) {
            return null;
        }

        $provided = new ContentView();
        $provided->setControllerReference(new ControllerReference(BlockController::class . '::view'));

        return $provided;
    }
}
