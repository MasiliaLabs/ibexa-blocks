<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;

/** The chosen options of a selection, as [index => label]: `|first` is the label of a single choice. */
#[AsFieldRenderer(['ibexa_selection'])]
final class SelectionRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $value = $content->getFieldValue($identifier);
        $options = (array) ($definition->fieldSettings['options'] ?? []);

        return $value === null ? [] : array_intersect_key($options, array_flip($value->selection));
    }
}
