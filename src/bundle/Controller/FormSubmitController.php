<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Mail\FormDefinitionLoader;
use Masilia\Bundle\IbexaBlocks\Mail\FormMailer;
use Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits;
use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Form\FormSubmission;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where the forms of a site are submitted. The body names the form (the id of its content) and carries the answers; who
 * receives them, and from which address, is read from the form's content here and never taken from the request.
 */
final readonly class FormSubmitController
{
    public function __construct(
        private FormDefinitionLoader $forms,
        private FormMailer $mailer,
        private SettingsProvider $provider,
        private LoggerInterface $logger,
        private RequestRateLimits $limits = new RequestRateLimits(),
    ) {
    }

    #[Route('/api/forms/submit', name: 'api_form_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        if (($wait = $this->limits->check('forms_submit', $request)) !== null) {
            return new JsonResponse(['success' => false, 'message' => 'Too many requests. Please try again later.'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $wait]);
        }

        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $submission = FormSubmission::fromArray(is_array($body) ? $body : [], $this->settings());
        } catch (\JsonException) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->forms->load($submission->formId);
        if ($form === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown form.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->mailer->send($form, $submission);
        } catch (\Throwable $e) {
            $this->logger->error('Form submission failed: {message}', ['message' => $e->getMessage(), 'form' => $form->id, 'exception' => $e]);

            return new JsonResponse(['success' => false, 'message' => 'An error occurred while submitting the form. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $this->logger->info('Form submitted', ['form' => $form->id, 'answers' => count($submission->answers)]);

        return new JsonResponse(['success' => true, 'message' => 'Form submitted successfully']);
    }

    private function settings(): FormSettings
    {
        return $this->provider->forms();
    }
}
