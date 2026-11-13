<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;

/** The name of the first country chosen. */
#[AsFieldRenderer(['ibexa_country'])]
final class CountryRenderer implements FieldRenderer
{
    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $countries = $content->getFieldValue($identifier)?->countries ?? [];

        return $countries === [] ? null : reset($countries)['Name'];
    }
}
