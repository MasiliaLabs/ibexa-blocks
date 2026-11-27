<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** The people who subscribed to the newsletter. Personal data: who may read it is for the caller to decide. */
interface Subscribers
{
    /** @param string|null $list Only the subscribers of this list (`newsletter.list_id`); all of them when null */
    public function count(?string $list = null): int;

    /**
     * @return list<array{email: string, list: string, name: ?string, confirmed: bool, active: bool, subscribed_at: string}> The latest first
     */
    public function latest(int $limit, ?string $list = null): array;
}
