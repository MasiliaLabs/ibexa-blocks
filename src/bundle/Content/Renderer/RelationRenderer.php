<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
     * @param SettingsProvider|null $settings fields.summary_types of the site
     */
    public function __construct(
        private ContentFactory $factory,
        private ?SettingsProvider $settings = null,
        private LoggerInterface $logger = new NullLogger(),
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
            if (in_array($related->type(), $this->summaryTypes(), true)) {
                return ['id' => $related->id(), 'name' => $related->name()];
            }

            return $related;
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException|\Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException) {
            // A related content that is gone, or that the visitor may not read, is simply not shown.
            return null;
        } catch (Throwable $e) {
            // Anything else is a fault: the block it should have shown would vanish with no trace.
            $this->logger->warning('The content {id} related to a content could not be loaded: {message}', ['id' => $contentId, 'message' => $e->getMessage(), 'exception' => $e]);

            return null;
        }
    }

    /** @return list<string> */
    private function summaryTypes(): array
    {
        return array_values(array_map('strval', (array) ($this->settings?->get('fields.summary_types', []) ?? [])));
    }
}
