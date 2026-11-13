<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Form;

/**
 * What a visitor sent with a form: the id of the form and its answers, label => value. It carries nothing that decides where
 * the mail goes: the recipients are the form's, read on the server.
 */
final readonly class FormSubmission
{
    /**
     * @param array<string, string> $answers
     */
    public function __construct(
        public int $formId,
        public array $answers,
    ) {
    }

    /**
     * @param array<string, mixed> $body A decoded request body
     *
     * @throws \InvalidArgumentException With a message that can be shown to the visitor
     */
    public static function fromArray(array $body, FormSettings $settings): self
    {
        $formId = $body['formId'] ?? null;
        if (!is_int($formId) && !(is_string($formId) && ctype_digit($formId))) {
            throw new \InvalidArgumentException('Unknown form.');
        }

        $data = $body['formData'] ?? null;
        if (!is_array($data) || $data === []) {
            throw new \InvalidArgumentException('No form data provided.');
        }
        if (count($data) > $settings->maxFields) {
            throw new \InvalidArgumentException('Too many fields.');
        }

        $answers = [];
        foreach ($data as $label => $value) {
            $value = is_array($value) ? implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $value)) : (is_scalar($value) ? (string) $value : '');
            if (mb_strlen($value) > $settings->maxLength) {
                throw new \InvalidArgumentException('An answer is too long.');
            }
            $answers[mb_substr(trim((string) $label), 0, 200)] = $value;
        }

        return new self((int) $formId, $answers);
    }
}
