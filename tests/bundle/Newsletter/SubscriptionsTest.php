<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Newsletter;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
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

    private function storeOf(): SubscriberStore
    {
        return new class($this->subscribers, $this->saves) implements SubscriberStore {
            public function __construct(private array &$subscribers, private int &$saves) {}

            public function byEmail(string $email, string $listId = 'default'): ?Subscriber { return $this->subscribers[$listId . '|' . $email] ?? null; }

            public function byToken(string $token, string $listId = 'default'): ?Subscriber
            {
                foreach ($this->subscribers as $subscriber) {
                    if ($subscriber->getConfirmationToken() === $token && $subscriber->getListId() === $listId) {
                        return $subscriber;
                    }
                }

                return null;
            }

            public function count(?string $listId = null): int { return count(array_filter($this->subscribers, static fn (Subscriber $s): bool => $listId === null || $s->getListId() === $listId)); }

            public function latest(int $limit, ?string $listId = null): array { return array_slice(array_reverse(array_values(array_filter($this->subscribers, static fn (Subscriber $s): bool => $listId === null || $s->getListId() === $listId))), 0, $limit); }

            public function add(Subscriber $subscriber): void { $this->subscribers[$subscriber->getListId() . '|' . $subscriber->getEmail()] = $subscriber; }

            public function save(): void { ++$this->saves; }
        };
    }

    private function subscriptions(?string $from = 'news@example.org', string $list = 'default'): Subscriptions
    {
        $store = $this->storeOf();
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
        $settings = TestSettings::provider(newsletter: new NewsletterSettings($from, 'Confirm please', 'the Example newsletter', listId: $list));

        return new Subscriptions($store, new SubscriptionMailer($mailer, $twig, $settings), $urls, $settings, new NullLogger());
    }

    public function testANewSubscriberGetsAConfirmationMailWithAnAbsoluteLink(): void
    {
        $result = $this->subscriptions()->subscribe(['email' => ' ada@example.org ', 'name' => 'Ada']);

        self::assertTrue($result->success);
        self::assertSame(200, $result->status);
        $subscriber = $this->subscribers['default|ada@example.org'];
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
        $token = (string) $this->subscribers['default|ada@example.org']->getConfirmationToken();

        $confirmed = $subscriptions->confirm($token);
        self::assertTrue($confirmed->success);
        self::assertSame('Thank you, Ada! You are now subscribed to the Example newsletter.', $confirmed->message);
        self::assertTrue($this->subscribers['default|ada@example.org']->isConfirmed());
        self::assertNull($this->subscribers['default|ada@example.org']->getConfirmationToken());

        $again = $subscriptions->confirm($token);
        self::assertSame([false, 404], [$again->success, $again->status]);
        self::assertSame(404, $subscriptions->confirm('')->status);
    }

    public function testAnAddressAlreadySubscribedIsToldSoAndAnUnconfirmedOneGetsTheMailAgain(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe(['email' => 'ada@example.org']);
        $first = (string) $this->subscribers['default|ada@example.org']->getConfirmationToken();

        $again = $subscriptions->subscribe(['email' => 'ada@example.org', 'name' => 'Ada L.']);
        self::assertTrue($again->success);
        self::assertSame('A confirmation email has been re-sent. Please check your inbox.', $again->message);
        self::assertNotSame($first, $this->subscribers['default|ada@example.org']->getConfirmationToken(), 'the old link stops working');
        self::assertSame('Ada L.', $this->subscribers['default|ada@example.org']->getName());
        self::assertCount(2, $this->sent);

        $subscriptions->confirm((string) $this->subscribers['default|ada@example.org']->getConfirmationToken());
        $already = $subscriptions->subscribe(['email' => 'ada@example.org']);
        self::assertSame([false, 409], [$already->success, $already->status]);
        self::assertCount(2, $this->sent);
    }

    public function testSomeoneWhoLeftMustConfirmAgain(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);
        $subscriptions->confirm((string) $this->subscribers['default|ada@example.org']->getConfirmationToken());
        $subscriber = $this->subscribers['default|ada@example.org'];

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

    public function testTheSameAddressMaySubscribeToTheListsOfTwoSites(): void
    {
        $first = $this->subscriptions(list: 'imal')->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);
        $second = $this->subscriptions(list: 'fossil')->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);

        self::assertTrue($first->success);
        self::assertTrue($second->success, 'an address of another list is not "already subscribed"');
        self::assertSame(['imal|ada@example.org', 'fossil|ada@example.org'], array_keys($this->subscribers));
    }

    public function testTheLinkOfAListDoesNothingOnAnotherList(): void
    {
        $this->subscriptions(list: 'imal')->subscribe(['email' => 'ada@example.org', 'name' => 'Ada']);
        $token = (string) $this->subscribers['imal|ada@example.org']->getConfirmationToken();

        self::assertSame(404, $this->subscriptions(list: 'fossil')->confirm($token)->status);
        self::assertFalse($this->subscribers['imal|ada@example.org']->isConfirmed());
        self::assertTrue($this->subscriptions(list: 'imal')->confirm($token)->success);
        self::assertFalse($this->subscriptions(list: 'fossil')->canUnsubscribe($token));
    }

    public function testTheAssistantCountsAndListsAllTheListsOrOne(): void
    {
        $this->subscriptions(list: 'imal')->subscribe(['email' => 'ada@example.org']);
        $this->subscriptions(list: 'fossil')->subscribe(['email' => 'bob@example.org']);
        $this->subscriptions(list: 'fossil')->subscribe(['email' => 'eve@example.org']);
        array_map(static fn (Subscriber $s) => $s->onPrePersist(), $this->subscribers); // what Doctrine does when it keeps them
        $subscribers = new \Masilia\Bundle\IbexaBlocks\Newsletter\StoredSubscribers($this->storeOf());

        self::assertSame(3, $subscribers->count());
        self::assertSame(2, $subscribers->count('fossil'));
        self::assertSame(['imal'], array_column($subscribers->latest(10, 'imal'), 'list'));
        self::assertSame(['fossil', 'fossil', 'imal'], array_column($subscribers->latest(10), 'list'));
    }
}
