<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\ImageAsset\Value as ImageAssetValue;
use Ibexa\Core\Helper\FieldHelper;
use Ibexa\Core\MVC\Symfony\Templating\Twig\Extension\ImageExtension;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\IbexaBlocks\Value\Image;

/**
 * An image: null when empty, else a closure that gives the image at the variation a template asks for
 * (`fields.image('card')`, "original" by default). An image asset is followed to the image of the asset.
 */
#[AsFieldRenderer(['ibexa_image', 'ibexa_imageasset'])]
final readonly class ImageRenderer implements FieldRenderer
{
    public function __construct(
        private ContentService $contents,
        private FieldHelper $fields,
        private ImageExtension $images,
        private FieldRendererRegistry $registry,
    ) {
    }

    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        if ($this->fields->isFieldEmpty($content, $identifier)) {
            return null;
        }

        $value = $content->getFieldValue($identifier);
        if ($value instanceof ImageAssetValue && $value->destinationContentId) {
            $content = $this->contents->loadContent($value->destinationContentId);
            $identifier = 'image';
        }

        return function (string $variation = 'original') use ($content, $identifier): ?Image {
            if ($this->fields->isFieldEmpty($content, $identifier)) {
                return null;
            }
            $field = $content->getField($identifier);
            $variationUri = $this->images->getImageVariation($field, $content->getVersionInfo(), $variation)?->uri ?: '';

            return new Image($variationUri, $field->value->alternativeText, $this->registry->render($content, 'caption'));
        };
    }
}
