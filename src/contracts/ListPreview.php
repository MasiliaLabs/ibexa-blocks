<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** What a list block that fills itself shows today: the contents of a type, the way the front office lists them. */
interface ListPreview
{
    /**
     * @return list<array{content_id: int, name: string}>
     */
    public function of(string $contentType, int $limit): array;
}
