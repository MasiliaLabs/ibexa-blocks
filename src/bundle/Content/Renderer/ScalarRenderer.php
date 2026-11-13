<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;

/** The fields whose value is one plain property of the Ibexa value: the text of a string, the flag of a boolean, the number of an integer. */
#[AsFieldRenderer(['ibexa_boolean', 'ibexa_date', 'ibexa_email', 'ibexa_string', 'ibexa_time', 'ibexa_datetime', 'ibexa_float', 'ibexa_integer'])]
final class ScalarRenderer implements FieldRenderer
{
    /** The property of the Ibexa value, by field type. */
    private const PROPERTY = [
        'ibexa_boolean' => 'bool',
        'ibexa_date' => 'date',
        'ibexa_email' => 'email',
        'ibexa_string' => 'text',
        'ibexa_time' => 'time',
        'ibexa_datetime' => 'value',
        'ibexa_float' => 'value',
        'ibexa_integer' => 'value',
    ];

    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $property = self::PROPERTY[$definition->fieldTypeIdentifier];

        return $content->getFieldValue($identifier)?->{$property};
    }
}
