<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;

/** No editing toolbox: nothing about editing reaches a page. */
final readonly class NullAdminMetaProvider implements AdminMetaProvider
{
    public function forContent(Content $content): array
    {
        return [];
    }
}
