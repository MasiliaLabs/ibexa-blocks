<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * A relation to a content gives that content, renderable; a list of relations gives the list. What is hidden, trashed, or
 * that the user may not read, is left out. The contents of the types listed under masilia_ibexa_blocks.fields.summary_types
 * (the tags and categories of a site, say) are given as {id, name}, which is all a template does with them.
 *
 * This is how the blocks of a page are found: its blocks field is a relation list.
 */
#[AsFieldRenderer(['ibexa_object_relation', 'ibexa_object_relation_list'])]
final readonly class RelationRenderer implements FieldRenderer
{
    /**
     * @param list<string> $summaryTypes
     */
    public function __construct(
        private ContentFactory $factory,
        #[Autowire('%masilia_blocks.fields.summary_types%')]
        private array $summaryTypes = [],
    ) {
    }

    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $value = $content->getFieldValue($identifier);

        if ($value instanceof RelationListValue) {
            return array_values(array_filter(array_map($this->resolve(...), $value->destinationContentIds)));
        }

        return isset($value->destinationContentId) ? $this->resolve((int) $value->destinationContentId) : null;
    }

    /**
     * @return RenderableContent|array{id: int, name: string}|null
     */
    private function resolve(int $contentId): RenderableContent|array|null
    {
        try {
            $related = $this->factory->fromContentId($contentId);
            if ($related->isHidden() || $related->isTrashed()) {
                return null;
            }
            if (in_array($related->type(), $this->summaryTypes, true)) {
                return ['id' => $related->id(), 'name' => $related->name()];
            }

            return $related;
        } catch (Throwable) {
            return null;
        }
    }
}
