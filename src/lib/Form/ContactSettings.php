<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Form;

/**
 * The contact forms of a site: forms made of a matrix of questions that the visitor fills in on the page, and that are mailed to
 * the recipients the form content names (never to anyone the request names).
 */
final readonly class ContactSettings
{
    /**
     * @param list<string> $contentTypes        The block types that are contact forms
     * @param string       $fieldsField         The matrix field: one row per question (label, type, is_required, values)
     * @param string       $recipientsField     The field (authors, or addresses) of who receives the answers
     * @param string       $senderField         The field with the address the mails are sent from
     * @param string       $successField        The field with the message shown when the mail is sent
     * @param string       $errorField          The field with the message shown when it could not be sent
     * @param string       $template            The mail to the recipients (blocks "subject" and "body"), relative to the templates of the theme
     * @param string       $confirmationTemplate The mail to the visitor, when one of the answers is an email address
     * @param bool         $captcha             Ask for a captcha (needs gregwar/captcha-bundle)
     */
    public function __construct(
        public array $contentTypes = [],
        public string $fieldsField = 'fields',
        public string $recipientsField = 'recipients',
        public string $senderField = 'sender',
        public string $successField = 'success_message',
        public string $errorField = 'error_message',
        public string $template = 'emails/contact.twig',
        public string $confirmationTemplate = 'emails/confirmation.twig',
        public bool $captcha = true,
        public ?string $fromAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $settings The "forms" node of the bundle configuration
     */
    public static function fromSettings(array $settings): self
    {
        $d = new self();
        $contact = (array) ($settings['contact'] ?? []);

        return new self(
            contentTypes: array_values(array_map('strval', (array) ($contact['content_types'] ?? []))),
            fieldsField: (string) ($contact['fields_field'] ?? $d->fieldsField),
            recipientsField: (string) ($contact['recipients_field'] ?? $d->recipientsField),
            senderField: (string) ($contact['sender_field'] ?? $d->senderField),
            successField: (string) ($contact['success_field'] ?? $d->successField),
            errorField: (string) ($contact['error_field'] ?? $d->errorField),
            template: (string) ($contact['template'] ?? $d->template),
            confirmationTemplate: (string) ($contact['confirmation_template'] ?? $d->confirmationTemplate),
            captcha: (bool) ($contact['captcha'] ?? $d->captcha),
            fromAddress: isset($settings['from_address']) && $settings['from_address'] !== '' ? (string) $settings['from_address'] : null,
        );
    }
}
