<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Newsletter\Subscriptions;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriptionResult;
use Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/** The newsletter: the endpoint the subscribe forms of the themes post to, and the pages of the links of the mails. */
final readonly class SubscriptionController
{
    public function __construct(
        private Subscriptions $subscriptions,
        private SettingsProvider $provider,
        private Environment $twig,
        private LoggerInterface $logger,
        private RequestRateLimits $limits = new RequestRateLimits(),
    ) {
    }

    #[Route('/api/subscribe', name: 'api_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $this->assertEnabled();
        if (($wait = $this->limits->check('subscribe', $request)) !== null) {
            return new JsonResponse(['success' => false, 'message' => 'Too many requests. Please try again later.'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $wait]);
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }
        if (!is_array($data) || $data === []) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request data.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->subscriptions->subscribe($data);
        } catch (\Throwable $e) {
            $this->logger->error('Subscription failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(['success' => false, 'message' => 'An error occurred. Please try again later.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => $result->success, 'message' => $result->message], $result->status);
    }

    #[Route('/subscription/confirm/{token}', name: 'subscription_confirm', methods: ['GET'])]
    public function confirm(string $token): Response
    {
        $this->assertEnabled();
        $result = $this->subscriptions->confirm($token);

        return $this->page($result, $result->success ? 'Subscription Confirmed!' : 'Invalid Link');
    }

    /**
     * The link of the mails. It only asks "do you want to unsubscribe?": something that opens a link by itself (a mail scanner, a
     * preview) must not unsubscribe anyone, so the change is made by the POST below, from the button of that page.
     */
    #[Route('/subscription/unsubscribe/{token}', name: 'subscription_unsubscribe', methods: ['GET'])]
    public function confirmUnsubscribe(string $token): Response
    {
        $this->assertEnabled();
        if (!$this->subscriptions->canUnsubscribe($token)) {
            return $this->page($this->subscriptions->unsubscribe($token), 'Invalid Link', 'info');
        }

        // A theme that wants its own page writes emails/unsubscribe_confirm.html.twig; without it the page of the package is shown.
        $template = $this->twig->resolveTemplate(['@ibexadesign/emails/unsubscribe_confirm.html.twig', '@MasiliaIbexaBlocks/newsletter/unsubscribe.html.twig']);

        return new Response($template->render([
            'title' => 'Unsubscribe',
            'message' => sprintf('Do you want to stop receiving %s?', $this->settings()->listName),
            'button' => 'Yes, unsubscribe me',
        ]));
    }

    #[Route('/subscription/unsubscribe/{token}', name: 'subscription_unsubscribe_confirmed', methods: ['POST'])]
    public function unsubscribe(string $token): Response
    {
        $this->assertEnabled();
        $result = $this->subscriptions->unsubscribe($token);

        return $this->page($result, $result->success ? 'Unsubscribed' : 'Invalid Link', 'info');
    }

    private function page(SubscriptionResult $result, string $title, string $successStatus = 'success'): Response
    {
        return new Response($this->twig->render('@ibexadesign/' . ltrim($this->settings()->statusTemplate, '/'), [
            'status' => $result->success ? $successStatus : 'error',
            'title' => $title,
            'message' => $result->message,
        ]), $result->status);
    }

    private function settings(): NewsletterSettings
    {
        return $this->provider->newsletter();
    }

    /** The routes exist for every site once one turns the newsletter on: the others answer as if there were none. */
    private function assertEnabled(): void
    {
        if (!$this->provider->newsletterEnabled()) {
            throw new NotFoundHttpException();
        }
    }
}
