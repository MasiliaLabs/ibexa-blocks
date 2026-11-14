<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\Contracts\IbexaBlocks\Subscribers;

/** {@see Subscribers} read from the store of the newsletter. */
final readonly class StoredSubscribers implements Subscribers
{
    public function __construct(private SubscriberStore $store)
    {
    }

    public function count(): int
    {
        return $this->store->count();
    }

    public function latest(int $limit): array
    {
        return array_map(static fn (Subscriber $subscriber): array => [
            'email' => $subscriber->getEmail(),
            'name' => $subscriber->getName(),
            'confirmed' => $subscriber->isConfirmed(),
            'active' => $subscriber->isActive(),
            'subscribed_at' => $subscriber->getCreatedAt()->format(\DATE_ATOM),
        ], $this->store->latest($limit));
    }
}
