<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Subscribing to the newsletter: a visitor gives an email address (and a name), gets a mail with a link, and is a subscriber
 * once the link is followed (double opt-in). The same link family unsubscribes.
 */
final readonly class Subscriptions
{
    public function __construct(
        private SubscriberStore $store,
        private SubscriptionMailer $mailer,
        private UrlGeneratorInterface $urls,
        private SettingsProvider $provider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data The body of the request: email, name and `website`, a field that humans leave empty
     */
    public function subscribe(array $data): SubscriptionResult
    {
        $email = trim((string) ($data['email'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return new SubscriptionResult(false, 'Please provide a valid email address.', 422);
        }
        // The honeypot: a robot fills in every field. It is told it worked and nothing is kept.
        if (!empty($data['website'])) {
            return new SubscriptionResult(true, 'Thank you for subscribing!');
        }

        $subscriber = $this->store->byEmail($email);
        if ($subscriber === null) {
            $subscriber = (new Subscriber())->setEmail($email)->setName($name !== '' ? $name : null);
            $subscriber->generateConfirmationToken();
            $this->store->add($subscriber);
            $this->sendConfirmation($subscriber);
            $this->logger->info('New subscriber registered');

            return new SubscriptionResult(true, 'Thank you! Please check your email to confirm your subscription.');
        }

        if ($subscriber->isConfirmed() && $subscriber->isActive()) {
            return new SubscriptionResult(false, 'This email is already subscribed.', 409);
        }

        if (!$subscriber->isConfirmed()) {
            $subscriber->generateConfirmationToken();
            if ($name !== '') {
                $subscriber->setName($name);
            }
            $this->store->save();
            $this->sendConfirmation($subscriber);

            return new SubscriptionResult(true, 'A confirmation email has been re-sent. Please check your inbox.');
        }

        // Confirmed once, unsubscribed since: it must be confirmed again.
        $subscriber->setIsActive(true);
        $subscriber->generateConfirmationToken();
        $this->store->save();
        $this->sendConfirmation($subscriber);

        return new SubscriptionResult(true, 'Welcome back! A confirmation email has been sent.');
    }

    /** The result of a click on the link of the confirmation mail: [title, message, status type, HTTP status]. */
    public function confirm(string $token): SubscriptionResult
    {
        $subscriber = $token === '' ? null : $this->store->byToken($token);
        if ($subscriber === null) {
            return new SubscriptionResult(false, 'This confirmation link is invalid or has already been used.', 404);
        }

        $subscriber->setConfirmed(true);
        $subscriber->setIsActive(true);
        $subscriber->setConfirmationToken(null);
        $this->store->save();
        $this->logger->info('Subscriber confirmed');

        return new SubscriptionResult(true, sprintf('Thank you, %s! You are now subscribed to %s.', $subscriber->getName() ?: 'subscriber', $this->settings()->listName));
    }

    /** Whether the link of an unsubscribe belongs to a subscriber: it changes nothing, it is what the confirmation page asks first. */
    public function canUnsubscribe(string $token): bool
    {
        return $token !== '' && $this->store->byToken($token) !== null;
    }

    public function unsubscribe(string $token): SubscriptionResult
    {
        $subscriber = $token === '' ? null : $this->store->byToken($token);
        if ($subscriber === null) {
            return new SubscriptionResult(false, 'This unsubscribe link is invalid or has already been used.', 404);
        }

        $subscriber->setIsActive(false);
        $subscriber->setConfirmationToken(null);
        $this->store->save();
        $this->logger->info('Subscriber unsubscribed');

        return new SubscriptionResult(true, "You have been successfully unsubscribed. We're sorry to see you go.");
    }

    /** The link a mail to a subscriber puts at its end to unsubscribe (a token is made when the subscriber has none). */
    public function unsubscribeUrl(Subscriber $subscriber): string
    {
        if ($subscriber->getConfirmationToken() === null) {
            $subscriber->generateConfirmationToken();
            $this->store->save();
        }

        return $this->urls->generate('subscription_unsubscribe', ['token' => $subscriber->getConfirmationToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function sendConfirmation(Subscriber $subscriber): void
    {
        $this->mailer->sendConfirmation($subscriber, $this->urls->generate('subscription_confirm', ['token' => $subscriber->getConfirmationToken()], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    private function settings(): NewsletterSettings
    {
        return $this->provider->newsletter();
    }
}
