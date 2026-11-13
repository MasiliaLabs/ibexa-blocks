<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Mail;

use Masilia\IbexaBlocks\Form\ContactSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Mails the answers of a contact form to the recipients the form names, and a confirmation to the visitor when one answer is
 * an email address. The templates have the blocks "subject" and "body" and are the theme's. What the visitor sees afterwards
 * (the success or the error message of the form) is flashed as `email_success` / `email_error`.
 */
final readonly class ContactMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private RequestStack $requests,
        private ContactSettings $settings,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string>         $recipients The addresses of the form, read from its content
     * @param array<string, mixed> $fields     The fields of the form content, for the templates
     * @param array<string, mixed> $answers    The visitor's answers by the label of the question
     */
    public function send(array $recipients, ?string $sender, array $fields, array $answers): void
    {
        $recipients = array_values(array_filter(array_map('trim', $recipients), static fn (string $r): bool => $r !== ''));
        $sender ??= $this->settings->fromAddress;
        if ($recipients === [] || $sender === null) {
            return;
        }

        $parameters = $fields + ['form_data' => $answers, 'full_name' => self::contactName($answers)];
        $template = $this->twig->load('@ibexadesign/' . ltrim($this->settings->template, '/'));

        $email = (new Email())
            ->from(new Address($sender))
            ->to(...array_map(static fn (string $recipient): Address => new Address($recipient), $recipients))
            // A subject is one line: nothing the visitor typed can add headers.
            ->subject(trim((string) preg_replace('/\s+/', ' ', $template->renderBlock('subject', $parameters))))
            ->html($template->renderBlock('body', $parameters));

        $confirmation = null;
        $visitor = self::visitorEmail($answers);
        if ($visitor !== null) {
            $confirmationTemplate = $this->twig->load('@ibexadesign/' . ltrim($this->settings->confirmationTemplate, '/'));
            $confirmation = (new Email())
                ->from(new Address($sender))
                ->to(new Address($visitor))
                ->subject(trim((string) preg_replace('/\s+/', ' ', $confirmationTemplate->renderBlock('subject', $parameters))))
                ->html($confirmationTemplate->renderBlock('body', $parameters));
        }

        try {
            $this->mailer->send($email);
            if ($confirmation !== null) {
                $this->mailer->send($confirmation);
            }
            $this->flash('email_success', $fields[$this->settings->successField] ?? null);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Contact form not sent: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $this->flash('email_error', $fields[$this->settings->errorField] ?? null);
        }
    }

    private function flash(string $type, mixed $message): void
    {
        $request = $this->requests->getMainRequest();
        if (!is_string($message) || $message === '' || $request === null || !$request->hasSession()) {
            return;
        }
        $request->getSession()->getFlashBag()->add($type, nl2br($message));
    }

    /** The answer to the question that asks for a name: the last label that has "name" in it. */
    private static function contactName(array $answers): string
    {
        $name = '';
        foreach ($answers as $label => $value) {
            if (str_contains(self::normalize((string) $label), 'name') && is_scalar($value)) {
                $name = (string) $value;
            }
        }

        return trim($name);
    }

    /** The first answer to a question that asks for an email address, if it is one. */
    private static function visitorEmail(array $answers): ?string
    {
        foreach ($answers as $label => $value) {
            $key = self::normalize((string) $label);
            if ((str_contains($key, 'email') || str_contains($key, 'e-mail')) && is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return $value;
            }
        }

        return null;
    }

    private static function normalize(string $label): string
    {
        $cleaned = (string) preg_replace('/[^a-zA-ZÀ-ÿ\s-]/u', '', $label);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $cleaned);

        return strtolower(trim($ascii === false ? $cleaned : $ascii));
    }
}
