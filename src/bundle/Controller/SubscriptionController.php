<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Masilia\Bundle\IbexaBlocks\Newsletter\Subscriptions;
use Masilia\Bundle\IbexaBlocks\Newsletter\SubscriptionResult;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/** The newsletter: the endpoint the subscribe forms of the themes post to, and the pages of the links of the mails. */
final readonly class SubscriptionController
{
    public function __construct(
        private Subscriptions $subscriptions,
        private NewsletterSettings $settings,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/subscribe', name: 'api_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
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
        $result = $this->subscriptions->confirm($token);

        return $this->page($result, $result->success ? 'Subscription Confirmed!' : 'Invalid Link');
    }

    #[Route('/subscription/unsubscribe/{token}', name: 'subscription_unsubscribe', methods: ['GET'])]
    public function unsubscribe(string $token): Response
    {
        $result = $this->subscriptions->unsubscribe($token);

        return $this->page($result, $result->success ? 'Unsubscribed' : 'Invalid Link', 'info');
    }

    private function page(SubscriptionResult $result, string $title, string $successStatus = 'success'): Response
    {
        return new Response($this->twig->render('@ibexadesign/' . ltrim($this->settings->statusTemplate, '/'), [
            'status' => $result->success ? $successStatus : 'error',
            'title' => $title,
            'message' => $result->message,
        ]), $result->status);
    }
}
