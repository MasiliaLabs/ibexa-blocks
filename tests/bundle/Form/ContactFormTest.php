<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Form;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Form\ContactAddresses;
use Masilia\Bundle\IbexaBlocks\Form\ContactFormParameters;
use Masilia\Bundle\IbexaBlocks\Form\MatrixFormBuilder;
use Masilia\Bundle\IbexaBlocks\Mail\ContactMailer;
use Masilia\IbexaBlocks\Block\ViewState;
use Masilia\IbexaBlocks\Form\ContactSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ContactFormTest extends TestCase
{
    /** @var list<Email> */
    private array $sent = [];
    private bool $failing = false;
    private RequestStack $requests;

    private const ROWS = [
        ['label' => 'Full Name', 'type' => 'text', 'is_required' => true, 'values' => ''],
        ['label' => 'E-mail', 'type' => 'email', 'is_required' => true, 'values' => ''],
        ['label' => 'Topic', 'type' => 'select', 'is_required' => false, 'values' => 'Press, Partnership'],
        ['label' => 'Message', 'type' => 'textarea', 'is_required' => false, 'values' => ''],
    ];

    private function provider(?Request $request): ContactFormParameters
    {
        $tokens = new class() implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken { return new CsrfToken($tokenId, 'good'); }
            public function refreshToken(string $tokenId): CsrfToken { return new CsrfToken($tokenId, 'good'); }
            public function removeToken(string $tokenId): ?string { return null; }
            public function isTokenValid(CsrfToken $token): bool { return $token->getValue() === 'good'; }
        };
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension($tokens))
            ->getFormFactory();

        $this->requests = new RequestStack();
        if ($request !== null) {
            $request->setSession(new Session(new MockArraySessionStorage()));
            $this->requests->push($request);
        }

        $mailer = new class($this) implements MailerInterface {
            public function __construct(private ContactFormTest $test) {}

            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                $this->test->deliver($message);
            }
        };
        $twig = new Environment(new ArrayLoader([
            '@ibexadesign/emails/contact.twig' => '{% block subject %} New message from {{ full_name }} {% endblock %}{% block body %}{% for label, value in form_data %}{{ label }}={{ value }};{% endfor %}{% endblock %}',
            '@ibexadesign/emails/confirmation.twig' => '{% block subject %}Thank you{% endblock %}{% block body %}Dear {{ full_name }}{% endblock %}',
        ]));
        $settings = TestSettings::provider(contact: new ContactSettings(['form'], captcha: false, fromAddress: 'noreply@example.org'));

        return new ContactFormParameters(new MatrixFormBuilder($factory), new ContactMailer($mailer, $twig, $this->requests, $settings, new NullLogger()), $this->requests, $settings);
    }

    public function deliver(object $message): void
    {
        if ($this->failing) {
            throw new TransportException('down');
        }
        $this->sent[] = $message;
    }

    private function fields(array $extra = []): array
    {
        return $extra + [
            'fields' => self::ROWS,
            'recipients' => ['Team' => 'team@example.org'],
            'sender' => ['Site' => 'site@example.org'],
            'success_message' => "Thanks!\nWe answer soon.",
            'error_message' => 'Sorry.',
        ];
    }

    private function block(): RenderableContent
    {
        $block = (new \ReflectionClass(RenderableContent::class))->newInstanceWithoutConstructor();
        $content = $this->createStub(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $content->method('getContentType')->willReturn(new \Ibexa\Core\Repository\Values\ContentType\ContentType(['identifier' => 'form']));
        (new \ReflectionProperty(RenderableContent::class, 'content'))->setValue($block, $content);

        return $block;
    }

    private function post(array $data, array $query = []): Request
    {
        return Request::create('/contact', 'POST', ['form' => $data + ['_token' => 'good']] + $query);
    }

    public function testAGetGivesTheFormAndSendsNothing(): void
    {
        $parameters = $this->provider(Request::create('/contact'))->parameters($this->block(), $this->fields(), new ViewState('blocks'));

        self::assertSame(['contact_form'], array_keys($parameters));
        self::assertSame(['full_name', 'e_mail', 'topic', 'message', 'send', '_token'], array_keys($parameters['contact_form']->children + ['_token' => 1]) );
        self::assertSame([], $this->sent);
    }

    public function testAnAnswerIsMailedToTheRecipientsOfTheFormAndTheVisitorGetsAConfirmation(): void
    {
        $request = $this->post(['full_name' => 'Ada Lovelace', 'e_mail' => 'ada@example.org', 'topic' => 'Press', 'message' => 'Hello']);
        $this->provider($request)->parameters($this->block(), $this->fields(), new ViewState('blocks'));

        self::assertCount(2, $this->sent);
        [$team, $visitor] = $this->sent;
        self::assertSame(['team@example.org'], array_map(static fn ($a) => $a->getAddress(), $team->getTo()));
        self::assertSame('site@example.org', $team->getFrom()[0]->getAddress());
        self::assertSame('New message from Ada Lovelace', $team->getSubject());
        self::assertSame('Full Name=Ada Lovelace;E-mail=ada@example.org;Topic=Press;Message=Hello;', $team->getHtmlBody());
        self::assertSame(['ada@example.org'], array_map(static fn ($a) => $a->getAddress(), $visitor->getTo()));
        self::assertSame('Dear Ada Lovelace', $visitor->getHtmlBody());
        self::assertSame(["Thanks!<br />\nWe answer soon."], $request->getSession()->getFlashBag()->get('email_success'));
    }

    public function testWhoReceivesIsTheFormsAndNeverTheRequests(): void
    {
        $request = $this->post(['full_name' => 'Eve', 'e_mail' => 'eve@example.org'], ['recipients' => 'attacker@example.org', 'to' => 'attacker@example.org', 'sender' => 'attacker@example.org']);
        $this->provider($request)->parameters($this->block(), $this->fields(), new ViewState('blocks'));

        $recipients = [];
        foreach ($this->sent as $mail) {
            array_push($recipients, ...array_map(static fn ($a) => $a->getAddress(), $mail->getTo()));
        }
        self::assertSame(['team@example.org', 'eve@example.org'], $recipients, 'the team of the form, then the visitor who sent it');
        self::assertSame('site@example.org', $this->sent[0]->getFrom()[0]->getAddress());
    }

    public function testAnswersThatTheFormDoesNotAskForAreRefused(): void
    {
        $this->provider($this->post(['full_name' => 'Eve', 'e_mail' => 'eve@example.org', 'recipients' => 'attacker@example.org']))->parameters($this->block(), $this->fields(), new ViewState('blocks'));

        self::assertSame([], $this->sent);
    }

    public function testAnInvalidAnswerOrABadTokenIsNotMailed(): void
    {
        $this->provider($this->post(['full_name' => '', 'e_mail' => 'nope']))->parameters($this->block(), $this->fields(), new ViewState('blocks'));
        self::assertSame([], $this->sent, 'required and email constraints');

        $request = Request::create('/contact', 'POST', ['form' => ['full_name' => 'Eve', 'e_mail' => 'eve@example.org', '_token' => 'forged']]);
        $this->provider($request)->parameters($this->block(), $this->fields(), new ViewState('blocks'));
        self::assertSame([], $this->sent, 'csrf');
    }

    public function testAFormWithNoRecipientSendsNothing(): void
    {
        $this->provider($this->post(['full_name' => 'Ada', 'e_mail' => 'ada@example.org']))->parameters($this->block(), $this->fields(['recipients' => []]) + ['sender' => []], new ViewState('blocks'));

        self::assertSame([], $this->sent);
    }

    public function testATransportThatFailsFlashesTheErrorMessageOfTheForm(): void
    {
        $this->failing = true;
        $request = $this->post(['full_name' => 'Ada', 'e_mail' => 'ada@example.org']);
        $this->provider($request)->parameters($this->block(), $this->fields(), new ViewState('blocks'));

        self::assertSame([], $request->getSession()->getFlashBag()->get('email_success'));
        self::assertSame(['Sorry.'], $request->getSession()->getFlashBag()->get('email_error'));
    }

    public function testAddressesAreReadFromAuthorsListsAndStringsAndOnlyValidOnesCount(): void
    {
        self::assertSame(['a@x.org', 'b@x.org'], ContactAddresses::from(['A' => 'a@x.org', 'B' => 'b@x.org', 'C' => 'broken']));
        self::assertSame(['a@x.org'], ContactAddresses::from(' a@x.org '));
        self::assertSame(['a@x.org'], ContactAddresses::from(['a@x.org', 'a@x.org']));
        self::assertSame([], ContactAddresses::from(null));
    }
}
