<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\FieldTypeRichText\RichText\Converter;
use Ibexa\Core\Helper\FieldHelper;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Rich text as HTML, the way the site's richtext output is configured (custom tags and styles included). Print it with |raw. */
#[AsFieldRenderer(['ibexa_richtext'])]
final readonly class RichtextRenderer implements FieldRenderer
{
    public function __construct(
        #[Autowire(service: 'ibexa.richtext.converter.output.xhtml5')]
        private Converter $converter,
        private FieldHelper $fields,
    ) {
    }

    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        if ($this->fields->isFieldEmpty($content, $identifier)) {
            return null;
        }

        return $this->converter->convert($content->getFieldValue($identifier)->xml)->saveHTML();
    }
}
