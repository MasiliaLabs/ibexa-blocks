<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\Support;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\IbexaBlocks\Form\ContactSettings;
use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Newsletter\NewsletterSettings;

/** A SettingsProvider over fixed values, for the tests of the classes that read the settings of the site. */
final class TestSettings
{
    /**
     * @param array<string, mixed> $parameters The settings by dotted name, those of the registry as "masilia_ibexa_sites:site_email"
     */
    public static function fromParameters(array $parameters): SettingsProvider
    {
        return new SettingsProvider(new class($parameters) implements ConfigResolverInterface {
            /** @param array<string, mixed> $parameters */
            public function __construct(private array $parameters)
            {
            }

            public function getParameter(string $paramName, ?string $namespace = null, ?string $scope = null): mixed
            {
                return $this->parameters[$namespace . ':' . $paramName];
            }

            public function hasParameter(string $paramName, ?string $namespace = null, ?string $scope = null): bool
            {
                return array_key_exists($namespace . ':' . $paramName, $this->parameters);
            }

            public function setDefaultNamespace(string $defaultNamespace): void
            {
            }

            public function getDefaultNamespace(): string
            {
                return 'masilia_ibexa_blocks';
            }
        });
    }

    public static function provider(?FormSettings $forms = null, ?ContactSettings $contact = null, ?NewsletterSettings $newsletter = null): SettingsProvider
    {
        $p = [];
        $set = static function (string $name, mixed $value) use (&$p): void {
            $p['masilia_ibexa_blocks:' . $name] = $value;
        };
        if ($forms !== null) {
            $set('forms.content_types', $forms->contentTypes);
            $set('forms.recipients_field', $forms->recipientsField);
            $set('forms.sender_field', $forms->senderField);
            $set('forms.title_field', $forms->titleField);
            $set('forms.from_address', $forms->fromAddress);
            $set('forms.template', $forms->template);
            $set('forms.max_fields', $forms->maxFields);
            $set('forms.max_length', $forms->maxLength);
        }
        if ($contact !== null) {
            $set('forms.contact.content_types', $contact->contentTypes);
            $set('forms.contact.fields_field', $contact->fieldsField);
            $set('forms.contact.recipients_field', $contact->recipientsField);
            $set('forms.contact.sender_field', $contact->senderField);
            $set('forms.contact.success_field', $contact->successField);
            $set('forms.contact.error_field', $contact->errorField);
            $set('forms.contact.template', $contact->template);
            $set('forms.contact.confirmation_template', $contact->confirmationTemplate);
            $set('forms.contact.captcha', $contact->captcha);
            $set('forms.from_address', $contact->fromAddress);
        }
        if ($newsletter !== null) {
            $set('newsletter.enabled', true);
            $set('newsletter.from_address', $newsletter->fromAddress);
            $set('newsletter.subject', $newsletter->subject);
            $set('newsletter.list_name', $newsletter->listName);
            $set('newsletter.confirmation_template', $newsletter->confirmationTemplate);
            $set('newsletter.status_template', $newsletter->statusTemplate);
            $set('newsletter.list_id', $newsletter->listId);
        }

        return self::fromParameters($p);
    }
}
