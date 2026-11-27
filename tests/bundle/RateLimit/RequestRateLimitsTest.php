<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\RateLimit;

use Masilia\Bundle\IbexaBlocks\DependencyInjection\MasiliaIbexaBlocksExtension;
use Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RequestRateLimitsTest extends TestCase
{
    private function limits(int $limit = 2): RequestRateLimits
    {
        return new RequestRateLimits(['subscribe' => new RateLimiterFactory(['id' => 'subscribe', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '10 minutes'], new InMemoryStorage())]);
    }

    private function from(string $address): Request
    {
        return Request::create('/api/subscribe', 'POST', server: ['REMOTE_ADDR' => $address]);
    }

    public function testAVisitorIsAllowedItsLimitThenToldHowLongToWait(): void
    {
        $limits = $this->limits(2);
        $request = $this->from('203.0.113.7');

        self::assertNull($limits->check('subscribe', $request));
        self::assertNull($limits->check('subscribe', $request));
        $wait = $limits->check('subscribe', $request);

        self::assertIsInt($wait);
        self::assertGreaterThanOrEqual(1, $wait);
        self::assertLessThanOrEqual(1200, $wait, 'a sliding window may announce a little more than one interval');
    }

    public function testEachAddressHasItsOwnCount(): void
    {
        $limits = $this->limits(1);

        self::assertNull($limits->check('subscribe', $this->from('203.0.113.1')));
        self::assertNotNull($limits->check('subscribe', $this->from('203.0.113.1')));
        self::assertNull($limits->check('subscribe', $this->from('203.0.113.2')), 'another visitor is not held up by the first');
    }

    public function testWhatIsNotLimitedIsNeverRefused(): void
    {
        $limits = $this->limits(1);

        for ($i = 0; $i < 5; ++$i) {
            self::assertNull($limits->check('forms_submit', $this->from('203.0.113.9')), 'no limit configured for this name');
            self::assertNull((new RequestRateLimits())->check('subscribe', $this->from('203.0.113.9')), 'nothing configured at all');
        }
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('ibexa.site_access.list', ['site']);
        $container->setParameter('ibexa.site_access.groups', ['site_group' => ['site']]);
        $container->setParameter('ibexa.site_access.groups_by_site_access', ['site' => ['site_group']]);
        $container->setParameter('ibexa.site_access.config', []);
        (new MasiliaIbexaBlocksExtension())->load([$config], $container);

        return $container;
    }

    public function testTheExtensionBuildsALimiterForEachConfiguredName(): void
    {
        $limits = $this->load(['rate_limit' => ['subscribe' => ['limit' => 3, 'interval' => '1 hour']]])->getDefinition(RequestRateLimits::class);

        self::assertSame(['subscribe'], array_keys($limits->getArgument(0)));
        self::assertSame(3, $limits->getArgument(0)['subscribe']->getArgument(0)['limit']);
    }

    public function testNothingIsLimitedUnlessTheSiteSaysSo(): void
    {
        self::assertSame([], $this->load([])->getDefinition(RequestRateLimits::class)->getArgument(0));
    }
}
