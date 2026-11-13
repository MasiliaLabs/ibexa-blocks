<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Mail;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Bundle\IbexaBlocks\Controller\FormSubmitController;
use Masilia\Bundle\IbexaBlocks\Mail\FormDefinition;
use Masilia\Bundle\IbexaBlocks\Mail\FormDefinitionLoader;
use Masilia\Bundle\IbexaBlocks\Mail\FormMailer;
use Masilia\Bundle\IbexaBlocks\Site\NullAdminMetaProvider;
use Masilia\Bundle\IbexaBlocks\Site\NullCacheTagger;
use Masilia\Bundle\IbexaBlocks\Site\NullMenuProvider;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Tests\bundle\Content\Fixtures;
use Masilia\IbexaBlocks\Value\Link;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class FormMailTest extends TestCase
{
    use Fixtures;

    /** @var list<Email> */
    private array $sent = [];

    private function controller(string $type = 'steps_form', array $values = []): FormSubmitController
    {
        $values += ['recipients_email' => ['Team' => 'team@site.org'], 'sender_email' => 'no-reply@site.org', 'title' => 'Join us'];
        $renderer = new class() implements FieldRenderer {
            public function __construct(public array $values = []) {}
            public function render(Content $content, string $identifier, FieldDefinition $definition): mixed { return $GLOBALS['__form_values'][$identifier] ?? null; }
        };
        $GLOBALS['__form_values'] = $values;
        $types = array_fill_keys(array_keys($values), ['type' => 'x_field']);
        $registry = new FieldRendererRegistry(new ServiceLocator(['x_field' => fn () => $renderer]));
        $links = new class() implements LinkProvider {
            public function forLocation(Location $location): Link { return new Link('/', 'x'); }
            public function urlForLocation(int $locationId, ?string $siteaccess = null): string { return '/'; }
        };
        $trail = new class() implements BreadcrumbProvider { public function forLocation(Location $location): array { return []; } };
        $content = $this->content($this->type($type, $types), [], 42, 'The form');
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContent')->willReturn($content);
        $factory = new ContentFactory($contents, $this->createMock(LocationService::class), new RenderableServices($registry, $links, $trail, new NullAdminMetaProvider(), new NullCacheTagger()));

        $settings = new FormSettings(contentTypes: ['steps_form'], fromAddress: 'fallback@site.org');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $email): void { $this->sent[] = $email; });
        $twig = new Environment(new ArrayLoader(['@ibexadesign/emails/form_submission.html.twig' => '<h1>{{ formTitle }}</h1>{% for k, v in formData %}<p>{{ k }}: {{ v }}</p>{% endfor %}']));

        return new FormSubmitController(new FormDefinitionLoader($factory, $settings), new FormMailer($mailer, $twig, $settings), $settings, new NullLogger());
    }

    private function post(array $body): Request
    {
        return Request::create('/api/forms/submit', 'POST', [], [], [], [], json_encode($body));
    }

    public function testASubmissionGoesToTheRecipientsOfTheFormAndNeverToWhoTheRequestNames(): void
    {
        $response = $this->controller()->submit($this->post(['formId' => 42, 'formData' => ['Name' => 'Lea'], 'recipientEmail' => 'attacker@example.org', 'senderEmail' => 'ceo@example.org', 'formTitle' => "Hi\r\nBcc: x@y.z"]));

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $this->sent);
        $email = $this->sent[0];
        self::assertSame(['team@site.org'], array_map(static fn ($a) => $a->getAddress(), $email->getTo()));
        self::assertSame('no-reply@site.org', $email->getFrom()[0]->getAddress());
        self::assertSame('Join us - New Submission', $email->getSubject(), 'the subject is the form\'s, not the request\'s');
        self::assertStringContainsString('<p>Name: Lea</p>', (string) $email->getHtmlBody());
    }

    public function testAContentThatIsNotAFormOfTheSiteIsUnknown(): void
    {
        $response = $this->controller('page')->submit($this->post(['formId' => 42, 'formData' => ['a' => 'b']]));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame([], $this->sent);
    }

    public function testAFormWithNoValidRecipientIsUnknownAndOneWithoutSenderUsesTheFallback(): void
    {
        self::assertSame(404, $this->controller('steps_form', ['recipients_email' => ['x' => 'not an email']])->submit($this->post(['formId' => 42, 'formData' => ['a' => 'b']]))->getStatusCode());
        self::assertSame([], $this->sent);

        $this->controller('steps_form', ['sender_email' => null])->submit($this->post(['formId' => 42, 'formData' => ['a' => 'b']]));
        self::assertSame('fallback@site.org', $this->sent[0]->getFrom()[0]->getAddress());
    }

    public function testABadRequestIsAnsweredWithoutLookingAnythingUp(): void
    {
        $controller = $this->controller();

        self::assertSame(400, $controller->submit(Request::create('/x', 'POST', [], [], [], [], '{not json'))->getStatusCode());
        self::assertSame(400, $controller->submit($this->post(['formData' => ['a' => 'b']]))->getStatusCode());
        self::assertSame(400, $controller->submit($this->post(['formId' => 42, 'formData' => []]))->getStatusCode());
        self::assertSame([], $this->sent);
    }

    public function testAMailerThatFailsIsAnsweredWithAGenericErrorAndNotItsMessage(): void
    {
        $controller = $this->controller();
        $ref = new \ReflectionProperty($controller, 'mailer');
        $failing = new FormMailer(new class() implements MailerInterface { public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void { throw new \RuntimeException('smtp password is hunter2'); } }, new Environment(new ArrayLoader(['@ibexadesign/emails/form_submission.html.twig' => 'x'])), new FormSettings());
        $broken = new FormSubmitController((new \ReflectionProperty($controller, 'forms'))->getValue($controller), $failing, new FormSettings(), new NullLogger());

        $response = $broken->submit($this->post(['formId' => 42, 'formData' => ['a' => 'b']]));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('hunter2', (string) $response->getContent());
    }
}
