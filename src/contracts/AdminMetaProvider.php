<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;

/**
 * What the in-context editing toolbox needs to know about a content, for those who may edit it. An empty array
 * for everyone else: nothing about editing reaches a visitor.
 */
interface AdminMetaProvider
{
    /**
     * @return array<string, mixed>
     */
    public function forContent(Content $content): array;
}
