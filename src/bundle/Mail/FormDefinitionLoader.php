<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Mail;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\IbexaBlocks\Form\FormSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Reads a form from its content. A content that is not one of the configured form types, is hidden, or cannot be read by the
 * visitor is "unknown form": a submission can only go where a form of the site says.
 */
final readonly class FormDefinitionLoader
{
    public function __construct(
        private ContentFactory $factory,
        private SettingsProvider $provider,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function load(int $formId): ?FormDefinition
    {
        try {
            $form = $this->factory->fromContentId($formId);
            if ($form->isHidden() || $form->isTrashed() || !in_array($form->type(), $this->settings()->contentTypes, true)) {
                return null;
            }
            $fields = $form->fields();
            $recipients = self::emails($fields->get($this->settings()->recipientsField));
            $sender = self::emails($fields->get($this->settings()->senderField))[0] ?? $this->settings()->fromAddress;
            $title = $fields->get($this->settings()->titleField);
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException|\Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException) {
            return null;
        } catch (Throwable $e) {
            // A fault is not an unknown form: the visitor gets the same answer, the log says why.
            $this->logger->warning('The form {id} could not be read: {message}', ['id' => $formId, 'message' => $e->getMessage(), 'exception' => $e]);

            return null;
        }

        if ($recipients === [] || $sender === null) {
            return null;
        }

        return new FormDefinition($formId, is_string($title) && $title !== '' ? $title : $form->name(), $recipients, $sender);
    }

    /**
     * The valid addresses in a field value, whatever it is: a string, a list, or authors as [name => address].
     *
     * @return list<string>
     */
    private static function emails(mixed $value): array
    {
        $found = [];
        foreach (is_array($value) ? [...array_keys($value), ...array_values($value)] : [$value] as $candidate) {
            if (is_string($candidate) && filter_var(trim($candidate), FILTER_VALIDATE_EMAIL) !== false) {
                $found[] = trim($candidate);
            }
        }

        return array_values(array_unique($found));
    }

    private function settings(): FormSettings
    {
        return $this->provider->forms();
    }
}
