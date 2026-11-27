<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Masilia\Bundle\IbexaBlocks\Site\ScopeChangeListener;
use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use PHPUnit\Framework\TestCase;

final class ScopeChangeListenerTest extends TestCase
{
    public function testTwoScopesInOneRequestAreNotMixed(): void
    {
        $scope = 'imal';
        $resolver = new class($scope) implements ConfigResolverInterface {
            public function __construct(private string &$scope)
            {
            }

            public function getParameter(string $paramName, ?string $namespace = null, ?string $scope = null): mixed
            {
                return 'Subject of ' . $this->scope;
            }

            public function hasParameter(string $paramName, ?string $namespace = null, ?string $scope = null): bool
            {
                return $paramName === 'newsletter.subject';
            }

            public function setDefaultNamespace(string $defaultNamespace): void
            {
            }

            public function getDefaultNamespace(): string
            {
                return 'masilia_ibexa_blocks';
            }
        };
        $settings = new SettingsProvider($resolver);
        $listener = new ScopeChangeListener($settings);

        self::assertSame('Subject of imal', $settings->newsletter()->subject);

        $scope = 'fossil'; // the preview of a content in another siteaccess: Ibexa changes the scope of its resolvers
        $listener->forget();
        self::assertSame('Subject of fossil', $settings->newsletter()->subject);

        $scope = 'imal'; // and restores it
        $listener->forget();
        self::assertSame('Subject of imal', $settings->newsletter()->subject);
    }

    public function testItListensToTheChangeAndTheRestoreOfTheScopeAfterIbexa(): void
    {
        $events = ScopeChangeListener::getSubscribedEvents();

        self::assertSame(['forget', 50], $events[MVCEvents::CONFIG_SCOPE_CHANGE]);
        self::assertSame(['forget', 50], $events[MVCEvents::CONFIG_SCOPE_RESTORE]);
    }
}
