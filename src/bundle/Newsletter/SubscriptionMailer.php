<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/** The mail that asks a subscriber to confirm: the theme's template, with the link to confirm. */
final readonly class SubscriptionMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private SettingsProvider $provider,
    ) {
    }

    public function sendConfirmation(Subscriber $subscriber, string $confirmUrl): void
    {
        if ($this->settings()->fromAddress === null) {
            throw new \LogicException('The newsletter has no address to write from: set masilia_ibexa_blocks.newsletter.from_address (or forms.from_address).');
        }

        $this->mailer->send((new Email())
            ->from(new Address($this->settings()->fromAddress))
            ->to(new Address($subscriber->getEmail()))
            ->subject($this->settings()->subject)
            ->html($this->twig->render('@ibexadesign/' . ltrim($this->settings()->confirmationTemplate, '/'), [
                'subscriber' => $subscriber,
                'confirmUrl' => $confirmUrl,
            ])));
    }

    private function settings(): NewsletterSettings
    {
        return $this->provider->newsletter();
    }
}
