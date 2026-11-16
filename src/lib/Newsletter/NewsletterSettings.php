<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Newsletter;

/** What the newsletter says in its mails and on its pages, and from where it writes. */
final readonly class NewsletterSettings
{
    /**
     * @param string|null $fromAddress          The address the confirmation mail is sent from
     * @param string      $subject              The subject of the confirmation mail
     * @param string      $listName             What the visitor subscribed to ("the Acme newsletter"), in the confirmation page
     * @param string      $confirmationTemplate The confirmation mail, relative to the templates of the theme
     * @param string      $statusTemplate       The page shown after a click on a link of the mail
     */
    public function __construct(
        public ?string $fromAddress = null,
        public string $subject = 'Confirm your subscription',
        public string $listName = 'our newsletter',
        public string $confirmationTemplate = 'emails/subscription_confirmation.html.twig',
        public string $statusTemplate = 'emails/subscription_status.html.twig',
    ) {
    }

    /**
     * @param array<string, mixed> $newsletter The "newsletter" node of the bundle configuration
     */
    public static function fromSettings(array $newsletter): self
    {
        $d = new self();

        return new self(
            fromAddress: isset($newsletter['from_address']) && $newsletter['from_address'] !== '' ? (string) $newsletter['from_address'] : null,
            subject: (string) ($newsletter['subject'] ?? $d->subject),
            listName: (string) ($newsletter['list_name'] ?? $d->listName),
            confirmationTemplate: (string) ($newsletter['confirmation_template'] ?? $d->confirmationTemplate),
            statusTemplate: (string) ($newsletter['status_template'] ?? $d->statusTemplate),
        );
    }
}
