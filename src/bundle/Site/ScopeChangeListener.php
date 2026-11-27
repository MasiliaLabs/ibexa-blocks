<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Core\MVC\Symfony\MVCEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ibexa changes the scope of the configuration in the middle of a request (the preview of a content in another siteaccess of
 * the admin, then back): what the settings said for the first site is not what they say for the second.
 */
final readonly class ScopeChangeListener implements EventSubscriberInterface
{
    public function __construct(private SettingsProvider $settings)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After the listener of Ibexa (priority 100), which sets the scope of the resolvers.
        return [
            MVCEvents::CONFIG_SCOPE_CHANGE => ['forget', 50],
            MVCEvents::CONFIG_SCOPE_RESTORE => ['forget', 50],
        ];
    }

    public function forget(): void
    {
        $this->settings->reset();
    }
}
