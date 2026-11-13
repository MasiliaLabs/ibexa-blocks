<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Masilia\Contracts\IbexaBlocks\LinkProvider;

/** What a renderable content asks of the rest of the application when a template reads one of its parts. */
final readonly class RenderableServices
{
    public function __construct(
        public FieldRendererRegistry $fields,
        public LinkProvider $links,
        public BreadcrumbProvider $breadcrumbs,
        public AdminMetaProvider $adminMeta,
        public CacheTagger $tagger,
    ) {
    }
}
