<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Form;

/** Which contents are forms the site accepts submissions for, and where the form says who receives them. */
final readonly class FormSettings
{
    /**
     * @param list<string> $contentTypes     The content types of the forms that may be submitted: any other content is refused
     * @param string       $recipientsField  The field of the form content that lists who receives the submissions
     * @param string       $senderField      The field of the form content with the address the mail is sent from
     * @param string       $titleField       The field with the title of the form (the subject of the mail)
     * @param string|null  $fromAddress      The address used when the form has no valid sender of its own
     * @param string       $template         The mail template, relative to the templates of the theme
     */
    public function __construct(
        public array $contentTypes = [],
        public string $recipientsField = 'recipients_email',
        public string $senderField = 'sender_email',
        public string $titleField = 'title',
        public ?string $fromAddress = null,
        public string $template = 'emails/form_submission.html.twig',
        public int $maxFields = 60,
        public int $maxLength = 5000,
    ) {
    }

    /**
     * @param array<string, mixed> $settings The "forms" node of the bundle configuration
     */
    public static function fromSettings(array $settings): self
    {
        $d = new self();

        return new self(
            contentTypes: array_values(array_map('strval', (array) ($settings['content_types'] ?? []))),
            recipientsField: (string) ($settings['recipients_field'] ?? $d->recipientsField),
            senderField: (string) ($settings['sender_field'] ?? $d->senderField),
            titleField: (string) ($settings['title_field'] ?? $d->titleField),
            fromAddress: isset($settings['from_address']) && $settings['from_address'] !== '' ? (string) $settings['from_address'] : null,
            template: (string) ($settings['template'] ?? $d->template),
            maxFields: (int) ($settings['max_fields'] ?? $d->maxFields),
            maxLength: (int) ($settings['max_length'] ?? $d->maxLength),
        );
    }
}
