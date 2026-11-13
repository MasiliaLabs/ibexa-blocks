<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\IbexaBlocks\Value\Link;

/** A URL field as a link: `cta.uri`, `cta.name`. */
#[AsFieldRenderer(['ibexa_url'])]
final class UrlRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $value = $content->getFieldValue($identifier);

        return $value === null ? null : new Link($value->link, (string) ($value->text ?? 'link'));
    }
}
