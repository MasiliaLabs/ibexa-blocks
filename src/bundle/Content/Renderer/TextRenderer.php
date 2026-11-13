<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\IbexaBlocks\Value\Text;

/** A long text: null when empty, else a value that prints with its line breaks. */
#[AsFieldRenderer(['ibexa_text'])]
final class TextRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $text = (string) ($content->getFieldValue($identifier)?->text ?? '');

        return $text === '' ? null : new Text($text);
    }
}
