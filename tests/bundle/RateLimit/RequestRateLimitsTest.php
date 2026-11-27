<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\RateLimit;

use Masilia\Bundle\IbexaBlocks\RateLimit\RequestRateLimits;
use PHPUnit\Framework\TestCase;
use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class RequestRateLimitsTest extends TestCase
{
    private function limits(int $limit = 2): RequestRateLimits
    {
        return new RequestRateLimits(
            TestSettings::fromParameters(['masilia_ibexa_blocks:rate_limit.subscribe.limit' => $limit, 'masilia_ibexa_blocks:rate_limit.subscribe.interval' => '10 minutes']),
            new ArrayAdapter(),
        );
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

    public function testTheLimitIsThatOfTheSiteOfTheRequest(): void
    {
        $cache = new ArrayAdapter();
        $strict = new RequestRateLimits(TestSettings::fromParameters(['masilia_ibexa_blocks:rate_limit.subscribe.limit' => 1, 'masilia_ibexa_blocks:rate_limit.subscribe.interval' => '1 hour']), $cache);
        $free = new RequestRateLimits(TestSettings::fromParameters([]), $cache);
        $request = $this->from('203.0.113.8');

        self::assertNull($strict->check('subscribe', $request));
        self::assertNotNull($strict->check('subscribe', $request));
        self::assertNull($free->check('subscribe', $request), 'a site that sets no limit is never held up');
    }
}
