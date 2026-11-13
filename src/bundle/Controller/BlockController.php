<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Ibexa\Core\MVC\Symfony\View\ContentView;
use Masilia\Bundle\IbexaBlocks\Block\BlockProviders;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\IbexaBlocks\Block\ViewState;
use Masilia\IbexaBlocks\Conventions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows a block, an item of a block, a page, or any content in one of the view types a theme names, for every content type:
 * the template is found by its name in the themes of the design (BlockRegistry; a theme.yaml may name another), the providers add what a kind of block needs.
 *
 * The template gets `block` (the content, renderable) and `fields` (its fields, rendered); a full view also gets `breadcrumb`,
 * `link` and `admin_meta`. With
 * masilia_ibexa_blocks.blocks.flatten_fields the fields are also variables of their own.
 */
final readonly class BlockController
{
    public function __construct(
        private ContentFactory $factory,
        private BlockRegistry $registry,
        private BlockProviders $providers,
        private Conventions $conventions,
        #[Autowire('%masilia_blocks.blocks.flatten_fields%')]
        private bool $flattenFields = false,
    ) {
    }

    public function view(ContentView $view): ContentView
    {
        $content = $view->getContent();
        $type = $content->getContentType()->identifier;
        $viewType = $view->getViewType();

        $resolved = $this->registry->findFor($viewType, $content->getContentType())
            ?? throw new NotFoundHttpException(sprintf(
                'No theme shows a "%s" in the "%s" view. Add the template %s/%s.twig to a theme of the design (looked in: %s).',
                $type,
                $viewType,
                BlockRegistry::folders($viewType)[0],
                $type,
                implode(', ', $this->registry->searched()),
            ));

        $block = $this->factory->fromContent($content, $view->getLocation());
        $variations = $resolved->definition->variations;
        if ($view->hasParameter('variations')) {
            $variations = array_merge($variations, (array) $view->getParameter('variations'));
        }

        $fields = $block->fields()->all($variations);
        $state = new ViewState($viewType, $view->getParameters());
        $parameters = $resolved->definition->params;
        foreach ($this->providers->for($type, $viewType) as $provider) {
            $parameters = array_merge($parameters, $provider->parameters($block, array_merge($fields, $parameters), $state));
        }

        // A page also knows where it is (its breadcrumb) and what the editing toolbox needs; a block does not need them.
        $page = $viewType === 'full' ? ['breadcrumb' => $block->breadcrumb(), 'link' => $block->link(), 'admin_meta' => $block->adminMeta(), 'blocks_field' => $this->conventions->blocksField] : [];

        $view->addParameters(array_merge(
            $this->flattenFields ? $fields : [],
            $parameters,
            $page,
            ['block' => $block, 'fields' => $fields, 'item_link' => $block->link()],
        ));
        $view->setTemplateIdentifier($state->template() !== null ? '@ibexadesign/' . ltrim($state->template(), '/') : $resolved->template);

        return $view;
    }
}
