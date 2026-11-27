<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\RateLimit;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * How often a visitor may use the public endpoints that send a mail (the forms, the newsletter). Nothing is limited unless the
 * site configures `masilia_ibexa_blocks.system.<scope>.rate_limit.<name>` (it needs symfony/rate-limiter): a visitor is told
 * apart by its address. The limit is that of the site the request is for, read when the request comes.
 */
final class RequestRateLimits
{
    /** @var array<string, RateLimiterFactory> */
    private array $built = [];

    public function __construct(private readonly ?SettingsProvider $settings = null, private readonly ?CacheItemPoolInterface $cache = null)
    {
    }

    private function factory(string $name): ?RateLimiterFactory
    {
        $limit = $this->settings?->get('rate_limit.' . $name . '.limit');
        $interval = $this->settings?->get('rate_limit.' . $name . '.interval');
        if ($limit === null || $interval === null) {
            return null;
        }
        if (!class_exists(RateLimiterFactory::class) || $this->cache === null) {
            throw new \LogicException('masilia_ibexa_blocks rate_limit needs symfony/rate-limiter: composer require symfony/rate-limiter');
        }

        $key = $name . '|' . $limit . '|' . $interval;

        return $this->built[$key] ??= new RateLimiterFactory(
            ['id' => 'masilia_blocks_' . $name, 'policy' => 'sliding_window', 'limit' => (int) $limit, 'interval' => (string) $interval],
            new CacheStorage($this->cache),
        );
    }

    /**
     * Counts this request against the limit of the name.
     *
     * @return int|null The seconds to wait when the visitor has used it up; null when the request may go on (or nothing is limited)
     */
    public function check(string $name, Request $request): ?int
    {
        $factory = $this->factory($name);
        if ($factory === null) {
            return null;
        }

        $limit = $factory->create($request->getClientIp() ?? 'unknown')->consume();
        if ($limit->isAccepted()) {
            return null;
        }

        return max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
