<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Form;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Mail\ContactMailer;
use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Masilia\IbexaBlocks\Block\ViewState;
use Masilia\IbexaBlocks\Form\ContactSettings;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A contact form block: gives its template `contact_form` (the Symfony form its matrix describes) and, when the page was posted
 * with the answers, mails them. The recipients and the sender are those of the block's own content, whatever the request says:
 * the form can only mail where the editors of the site said.
 *
 * It answers the POST of the page the form is on: the response of a POST is not cached, and the confirmation flashed for
 * the notifications is seen by the visitor who sent the form and nobody else.
 */
final readonly class ContactFormParameters implements BlockViewParameters
{
    public function __construct(
        private MatrixFormBuilder $builder,
        private ContactMailer $mailer,
        private RequestStack $requests,
        private ContactSettings $settings,
    ) {
    }

    public function parameters(RenderableContent $block, array $fields, ViewState $view): array
    {
        $rows = [];
        foreach ((array) ($fields[$this->settings->fieldsField] ?? []) as $row) {
            if (!is_array($row) || !isset($row['label'])) {
                continue;
            }
            $rows[] = [
                'label' => (string) $row['label'],
                'type' => (string) ($row['type'] ?? 'text'),
                'isRequired' => (bool) ($row['is_required'] ?? false),
                'values' => (string) ($row['values'] ?? ''),
            ];
        }

        $form = $this->builder->build($rows, [
            'attr' => ['id' => 'contact-form', 'name' => 'contact', 'novalidate' => 'novalidate', 'class' => 'contact-page__form', 'label' => false, 'compound' => true],
            'csrf_protection' => true,
        ], $this->settings->captcha);

        $request = $this->requests->getMainRequest();
        if ($request !== null) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->mailer->send(
                    ContactAddresses::from($fields[$this->settings->recipientsField] ?? null),
                    ContactAddresses::from($fields[$this->settings->senderField] ?? null)[0] ?? null,
                    $fields,
                    $this->answers($form),
                );
            }
        }

        return ['contact_form' => $form->createView()];
    }

    /**
     * The answers by the label of their question, without the captcha and what was left empty.
     *
     * @return array<string, mixed>
     */
    private function answers(FormInterface $form): array
    {
        $answers = [];
        foreach ($form->all() as $name => $field) {
            if (in_array($name, ['captcha', 'send'], true) || empty($field->getData())) {
                continue;
            }
            $answers[(string) $field->getConfig()->getOption('label')] = $field->getData();
        }

        return $answers;
    }
}
