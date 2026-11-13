<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Newsletter;

use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriberStore;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriptionMailer;
use Masilia\Bundle\IbexaBlocks\Newsletter\Subscriptions;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SubscriptionsTest extends TestCase
{
    /** @var array<string, Subscriber> */
    private array $subscribers = [];
    /** @var list<Email> */
    private array $sent = [];
    private int $saves = 0;

    private function subscriptions(?string $from = 'news@example.org'): Subscriptions
    {
        $store = new class($this->subscribers, $this->saves) implements SubscriberStore {
            public function __construct(private array &$subscribers, private int &$saves) {}

            public function byEmail(string $email): ?Subscriber { return $this->subscribers[$email] ?? null; }

            public function byToken(string $token): ?Subscriber
            {
                foreach ($this->subscribers as $subscriber) {
                    if ($subscriber->getConfirmationToken() === $token) {
                        return $subscriber;
                    }
                }

                return null;
            }

            public function add(Subscriber $subscriber): void { $this->subscribers[$subscriber->getEmail()] = $subscriber; }

            public function save(): void { ++$this->saves; }
        };
        $mailer = new class($this->sent) implements MailerInterface {
            public function __construct(private array &$sent) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): void { $this->sent[] = $message; }
        };
        $twig = new Environment(new ArrayLoader(['@ibexadesign/emails/subscription_confirmation.html.twig' => 'Hello {{ subscriber.name }}: {{ confirmUrl }}']));
        $urls = new class() implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return ($referenceType === self::ABSOLUTE_URL ? 'https://site.test' : '') . '/' . $name . '/' . ($parameters['token'] ?? '');
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}

            public function getContext(): \Symfony\Component\Routing\RequestContext { return new \Symfony\Component\Routing\RequestContext(); }
        };
        $settings = new NewsletterSettings($from, 'Confirm please', 'the Example newsletter');

        return new Subscriptions($store, new SubscriptionMailer($mailer, $twig, $settings), $urls, $settings, new NullLogger());
    }

    public function testANewSubscriberGetsAConfirmationMailWithAnAbsoluteLink(): void
    {
        $result = $this->subscriptions()->subscribe(['email' => ' ada@example.org ', 'name' => 'Ada']);

        self::assertTrue($result->success);
        self::assertSame(200, $result->status);
        $subscriber = $this->subscribers['ada@example.org'];
        self::assertFalse($subscriber->isConfirmed());
        self::assertSame(64, strlen((string) $subscriber->getConfirmationToken()));
        self::assertCount(1, $this->sent);
        self::assertSame('Confirm please', $this->sent[0]->getSubject());
        self::assertSame('news@example.org', $this->sent[0]->getFrom()[0]->getAddress());
        self::assertSame('Hello Ada: https://site.test/subscription_confirm/' . $subscriber->getConfirmationToken(), $this->sent[0]->getHtmlBody());
    }

    public function testABadAddressIsRefusedAndARobotIsToldItWorkedWithNothingKept(): void
    {
        $subscriptions = $this->subscriptions();

        $bad = $subscriptions->subscribe(['email' => 'nope']);
        self::assertSame([false, 422], [$bad->success, $bad->status]);

        $robot = $subscriptions->subscribe(['email' => 'bot@example.org', 'website' => 'http://spam']);
        self::assertTrue($robot->success);
        self::assertSame([], $this->subscribers);
        self::assertSame([], $this->sent);
    }

    public function testTheLinkConfirmsOnceThenItIsUsedUp(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);
        $token = (string) $this->subscribers['ada@example.org']->getConfirmationToken();

        $confirmed = $subscriptions->confirm($token);
        self::assertTrue($confirmed->success);
        self::assertSame('Thank you, Ada! You are now subscribed to the Example newsletter.', $confirmed->message);
        self::assertTrue($this->subscribers['ada@example.org']->isConfirmed());
        self::assertNull($this->subscribers['ada@example.org']->getConfirmationToken());

        $again = $subscriptions->confirm($token);
        self::assertSame([false, 404], [$again->success, $again->status]);
        self::assertSame(404, $subscriptions->confirm('')->status);
    }

    public function testAnAddressAlreadySubscribedIsToldSoAndAnUnconfirmedOneGetsTheMailAgain(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe(['email' => 'ada@example.org']);
        $first = (string) $this->subscribers['ada@example.org']->getConfirmationToken();

        $again = $subscriptions->subscribe(['email' => 'ada@example.org', 'name' => 'Ada L.']);
        self::assertTrue($again->success);
        self::assertSame('A confirmation email has been re-sent. Please check your inbox.', $again->message);
        self::assertNotSame($first, $this->subscribers['ada@example.org']->getConfirmationToken(), 'the old link stops working');
        self::assertSame('Ada L.', $this->subscribers['ada@example.org']->getName());
        self::assertCount(2, $this->sent);

        $subscriptions->confirm((string) $this->subscribers['ada@example.org']->getConfirmationToken());
        $already = $subscriptions->subscribe(['email' => 'ada@example.org']);
        self::assertSame([false, 409], [$already->success, $already->status]);
        self::assertCount(2, $this->sent);
    }

    public function testSomeoneWhoLeftMustConfirmAgain(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);
        $subscriptions->confirm((string) $this->subscribers['ada@example.org']->getConfirmationToken());
        $subscriber = $this->subscribers['ada@example.org'];

        $url = $subscriptions->unsubscribeUrl($subscriber);
        self::assertSame('https://site.test/subscription_unsubscribe/' . $subscriber->getConfirmationToken(), $url);
        $left = $subscriptions->unsubscribe((string) $subscriber->getConfirmationToken());
        self::assertTrue($left->success);
        self::assertFalse($subscriber->isActive());
        self::assertNull($subscriber->getConfirmationToken());

        $back = $subscriptions->subscribe(['email' => 'ada@example.org']);
        self::assertSame('Welcome back! A confirmation email has been sent.', $back->message);
        self::assertTrue($subscriber->isActive());
        self::assertNotNull($subscriber->getConfirmationToken());
        self::assertSame(404, $subscriptions->unsubscribe('unknown')->status);
    }

    public function testWithoutAnAddressToWriteFromItSaysSo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('newsletter.from_address');

        $this->subscriptions(null)->subscribe(['email' => 'ada@example.org']);
    }
}
