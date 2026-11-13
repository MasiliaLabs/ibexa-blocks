<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;

/** A matrix as a list of rows, each row the cells by column identifier. */
#[AsFieldRenderer(['ibexa_matrix'])]
final class MatrixRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $value = $content->getFieldValue($identifier);
        $rows = [];
        foreach ($value?->getRows() ?? [] as $row) {
            $rows[] = $row->getCells();
        }

        return $rows;
    }
}
