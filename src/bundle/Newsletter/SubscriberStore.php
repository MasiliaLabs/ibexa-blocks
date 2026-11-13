<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;

/** Where the subscribers are kept. */
interface SubscriberStore
{
    public function byEmail(string $email): ?Subscriber;

    public function byToken(string $token): ?Subscriber;

    /** Keeps a new subscriber. */
    public function add(Subscriber $subscriber): void;

    /** Keeps the changes made to subscribers that were read from the store. */
    public function save(): void;
}
