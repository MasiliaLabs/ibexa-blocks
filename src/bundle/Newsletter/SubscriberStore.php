<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;

/** Where the subscribers are kept. */
interface SubscriberStore
{
    /** The subscriber of a list: the same address may be subscribed to the lists of several sites. */
    public function byEmail(string $email, string $listId = 'default'): ?Subscriber;

    /** The link of a mail is for the list of the site that sent it. */
    public function byToken(string $token, string $listId = 'default'): ?Subscriber;

    /** @param string|null $listId Only this list (all of them when null) */
    public function count(?string $listId = null): int;

    /** @return list<Subscriber> The latest first */
    public function latest(int $limit, ?string $listId = null): array;

    /** Keeps a new subscriber. */
    public function add(Subscriber $subscriber): void;

    /** Keeps the changes made to subscribers that were read from the store. */
    public function save(): void;
}
