<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Mail;

/** A form of the site, as its content says: who receives the submissions, and from where the mail is sent. */
final readonly class FormDefinition
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public int $id,
        public string $title,
        public array $recipients,
        public string $sender,
    ) {
    }
}
