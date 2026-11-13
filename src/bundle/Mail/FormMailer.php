<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Mail;

use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Form\FormSubmission;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/** Sends a submission to the recipients of its form, with the theme's mail template. */
final readonly class FormMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private FormSettings $settings,
    ) {
    }

    public function send(FormDefinition $form, FormSubmission $submission): void
    {
        $html = $this->twig->render('@ibexadesign/' . ltrim($this->settings->template, '/'), [
            'formTitle' => $form->title,
            'formData' => $submission->answers,
            'submittedAt' => new \DateTimeImmutable(),
        ]);

        $email = (new Email())
            ->from(new Address($form->sender))
            ->to(...array_map(static fn (string $recipient): Address => new Address($recipient), $form->recipients))
            // A subject is one line: nothing from the form title can add headers.
            ->subject(trim((string) preg_replace('/\s+/', ' ', $form->title)) . ' - New Submission')
            ->html($html);

        $this->mailer->send($email);
    }
}
