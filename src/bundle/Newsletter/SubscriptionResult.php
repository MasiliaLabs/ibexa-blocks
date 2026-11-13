<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

/** What came of a subscription request: whether it worked, what to tell the visitor, and the HTTP status. */
final readonly class SubscriptionResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $status = 200,
    ) {
    }
}
