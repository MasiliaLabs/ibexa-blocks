<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Contracts\EditorialToolbox\Meta\AdminMetaBuilderInterface;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;

/** What the in-context editing toolbox needs about a content, for those who may edit it; nothing for everyone else. */
final readonly class ToolboxAdminMetaProvider implements AdminMetaProvider
{
    public function __construct(
        private AdminMetaBuilderInterface $builder,
        private PermissionResolver $permissions,
    ) {
    }

    public function forContent(Content $content): array
    {
        if ($this->permissions->hasAccess('content', 'edit') === false) {
            return [];
        }

        return $this->builder->build($content);
    }
}
