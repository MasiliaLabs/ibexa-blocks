<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\RateLimit;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * How often a visitor may use the public endpoints that send a mail (the forms, the newsletter). Nothing is limited unless the
 * site configures `masilia_ibexa_blocks.rate_limit.<name>` (it needs symfony/rate-limiter): a visitor is told apart by its address.
 */
final readonly class RequestRateLimits
{
    /**
     * @param array<string, RateLimiterFactory> $factories A limiter per name ("forms_submit", "subscribe")
     */
    public function __construct(private array $factories = [])
    {
    }

    /**
     * Counts this request against the limit of the name.
     *
     * @return int|null The seconds to wait when the visitor has used it up; null when the request may go on (or nothing is limited)
     */
    public function check(string $name, Request $request): ?int
    {
        $factory = $this->factories[$name] ?? null;
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
