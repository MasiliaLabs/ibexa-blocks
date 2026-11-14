<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** The people who subscribed to the newsletter. Personal data: who may read it is for the caller to decide. */
interface Subscribers
{
    public function count(): int;

    /**
     * @return list<array{email: string, name: ?string, confirmed: bool, active: bool, subscribed_at: string}> The latest first
     */
    public function latest(int $limit): array;
}
