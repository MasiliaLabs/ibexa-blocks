<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content\Renderer;

use Ibexa\Bundle\Core\EventListener\ContentDownloadRouteReferenceListener;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\MVC\Symfony\Routing\Generator\RouteReferenceGenerator;
use Masilia\Contracts\IbexaBlocks\AsFieldRenderer;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\IbexaBlocks\Value\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** A file to download, with the URL Ibexa serves it from (checked against the rights of the user). */
#[AsFieldRenderer(['ibexa_binaryfile'])]
final readonly class BinaryFileRenderer implements FieldRenderer
{
    public function __construct(
        private RouterInterface $router,
        private RouteReferenceGenerator $references,
    ) {
    }

    public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
    {
        $value = $content->getFieldValue($identifier);
        if (!isset($value->fileName)) {
            return null;
        }

        $reference = $this->references->generate(ContentDownloadRouteReferenceListener::ROUTE_NAME, [
            ContentDownloadRouteReferenceListener::OPT_CONTENT => $content,
            ContentDownloadRouteReferenceListener::OPT_VERSION => $content->getVersionInfo()->versionNo,
            ContentDownloadRouteReferenceListener::OPT_FIELD_IDENTIFIER => $identifier,
        ]);

        return new File(
            $value->fileName,
            $value->fileSize,
            $value->mimeType,
            $this->router->generate($reference->getRoute(), $reference->getParams(), UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
