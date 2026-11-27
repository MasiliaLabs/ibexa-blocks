<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Newsletter;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Masilia\Bundle\IbexaBlocks\Controller\SubscriptionController;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriberStore;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriptionMailer;
use Masilia\Bundle\IbexaBlocks\Newsletter\Subscriptions;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SubscriptionControllerTest extends TestCase
{
    private Subscriber $subscriber;

    /**
     * @param array<string, string> $templates Added to the templates of the page of the package and of the status page
     */
    private function controller(array $templates = [], ?\Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits $limits = null, ?\Masilia\Bundle\IbexaBlocks\Site\SettingsProvider $provider = null): SubscriptionController
    {
        $this->subscriber = (new Subscriber())->setEmail('ada@example.org')->setName('Ada');
        $this->subscriber->generateConfirmationToken();
        $subscriber = $this->subscriber;
        $store = new class($subscriber) implements SubscriberStore {
            public function __construct(private Subscriber $subscriber) {}

            public function byEmail(string $email, string $listId = 'default'): ?Subscriber { return $email === $this->subscriber->getEmail() ? $this->subscriber : null; }

            public function byToken(string $token, string $listId = 'default'): ?Subscriber { return $token === $this->subscriber->getConfirmationToken() ? $this->subscriber : null; }

            public function count(): int { return 1; }

            public function latest(int $limit): array { return [$this->subscriber]; }

            public function add(Subscriber $subscriber): void {}

            public function save(): void {}
        };
        $mailer = new class() implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void {}
        };
        $urls = new class() implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string { return '/' . $name; }

            public function setContext(RequestContext $context): void {}

            public function getContext(): RequestContext { return new RequestContext(); }
        };
        $settings = $provider ?? TestSettings::provider(newsletter: new NewsletterSettings('news@example.org', 'Confirm', 'the Example newsletter'));
        $twig = new Environment(new ArrayLoader($templates + [
            '@MasiliaIbexaBlocks/newsletter/unsubscribe.html.twig' => 'package page: {{ message }} [{{ button }}]',
            '@ibexadesign/emails/subscription_status.html.twig' => '{{ status }}/{{ title }}: {{ message }}',
        ]));

        return new SubscriptionController(new Subscriptions($store, new SubscriptionMailer($mailer, $twig, $settings), $urls, $settings, new NullLogger()), $settings, $twig, new NullLogger(), $limits ?? new \Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits());
    }

    public function testASiteThatDoesNotTurnTheNewsletterOnAnswersNotFoundOnEveryRoute(): void
    {
        $controller = $this->controller(provider: TestSettings::fromParameters([]));
        $token = (string) $this->subscriber->getConfirmationToken();

        foreach ([
            static fn () => $controller->subscribe(\Symfony\Component\HttpFoundation\Request::create('/api/subscribe', 'POST')),
            static fn () => $controller->confirm($token),
            static fn () => $controller->confirmUnsubscribe($token),
            static fn () => $controller->unsubscribe($token),
        ] as $call) {
            try {
                $call();
                self::fail('the route of a site without newsletter must not answer');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertTrue($this->subscriber->isActive());
    }

    public function testTheLinkOfTheMailOnlyAsksAndUnsubscribesNobody(): void
    {
        $controller = $this->controller();

        $response = $controller->confirmUnsubscribe((string) $this->subscriber->getConfirmationToken());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('package page: Do you want to stop receiving the Example newsletter? [Yes, unsubscribe me]', $response->getContent());
        self::assertTrue($this->subscriber->isActive(), 'opening the link, as a mail scanner does, changes nothing');
        self::assertNotNull($this->subscriber->getConfirmationToken(), 'and the link still works');
    }

    public function testTheButtonUnsubscribes(): void
    {
        $controller = $this->controller();

        $response = $controller->unsubscribe((string) $this->subscriber->getConfirmationToken());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('info/Unsubscribed', (string) $response->getContent());
        self::assertFalse($this->subscriber->isActive());
    }

    public function testATokenThatBelongsToNobodyIsTheInvalidLinkPageOnBothVerbs(): void
    {
        $controller = $this->controller();

        foreach ([$controller->confirmUnsubscribe('nope'), $controller->unsubscribe('nope')] as $response) {
            self::assertSame(404, $response->getStatusCode());
            self::assertStringContainsString('error/Invalid Link', (string) $response->getContent());
        }
        self::assertSame(404, $controller->confirmUnsubscribe('')->getStatusCode());
    }

    public function testAThemeMayShowItsOwnConfirmationPage(): void
    {
        $controller = $this->controller(['@ibexadesign/emails/unsubscribe_confirm.html.twig' => 'themed: {{ button }}']);

        self::assertSame('themed: Yes, unsubscribe me', $controller->confirmUnsubscribe((string) $this->subscriber->getConfirmationToken())->getContent());
    }

    public function testTheConfirmationOfASubscriptionStaysOnTheLink(): void
    {
        $controller = $this->controller();

        $response = $controller->confirm((string) $this->subscriber->getConfirmationToken());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->subscriber->isConfirmed());
    }

    public function testTooManyRequestsFromOneAddressAreRefusedWithHowLongToWait(): void
    {
        $limits = new \Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits(TestSettings::fromParameters(['masilia_ibexa_blocks:rate_limit.subscribe.limit' => 1, 'masilia_ibexa_blocks:rate_limit.subscribe.interval' => '1 hour']), new \Symfony\Component\Cache\Adapter\ArrayAdapter());
        $controller = $this->controller([], $limits);
        $request = static fn (): \Symfony\Component\HttpFoundation\Request => \Symfony\Component\HttpFoundation\Request::create('/api/subscribe', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5'], content: 'not json');

        self::assertSame(400, $controller->subscribe($request())->getStatusCode(), 'the first request is let through (and is bad)');
        $refused = $controller->subscribe($request());

        self::assertSame(429, $refused->getStatusCode());
        self::assertGreaterThanOrEqual(1, (int) $refused->headers->get('Retry-After'));
        self::assertFalse(json_decode((string) $refused->getContent(), true)['success']);
    }
}
