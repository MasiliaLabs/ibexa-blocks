<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;

/** The authors as [name => email]. */
#[AsFieldRenderer(['ibexa_author'])]
final class AuthorRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $authors = [];
        foreach ($content->getFieldValue($identifier)?->authors ?? [] as $author) {
            $authors[$author->name] = $author->email;
        }

        return $authors;
    }
}
