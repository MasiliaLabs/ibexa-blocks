<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

/** A field whose type no renderer handles: a template would silently get nothing. */
final class UnsupportedFieldType extends \RuntimeException
{
    public static function of(string $type, string $contentType, string $field): self
    {
        return new self(sprintf(
            'No renderer for the field type "%s" (field "%s" of "%s"). Write one with #[AsFieldRenderer([\'%s\'])] implementing FieldRenderer, or list the type under masilia_ibexa_blocks.fields.ignored_types.',
            $type,
            $field,
            $contentType,
            $type,
        ));
    }
}
