<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Routing;

use Ibexa\Core\MVC\Symfony\SiteAccess;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * `siteaccess_in_group('admin_group')` for the `condition` of a route: true when the siteaccess of the request is in that group.
 *
 * Why a function: the groups of a siteaccess are `SiteAccessGroup` objects, and the expression language compares with `in` strictly,
 * so `'admin_group' in request.attributes.get('siteaccess').groups` is never true.
 *
 * Why a condition: the routes of this package are loaded before Ibexa's (they must win on the front), and a route that matches on an
 * admin siteaccess hides the admin's own route of the same path (`/search`). A route that does not match lets the next one answer;
 * `siteaccess_group_whitelist` of the admin only answers 404 once the route has matched.
 */
final class SiteAccessGroupExpression implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'siteaccess_in_group',
                // the compiled URL matcher (var/cache) calls this class directly: it does not need the provider at run time
                static fn (string $group): string => sprintf('\\%s::inGroup($request, %s)', self::class, $group),
                static fn (array $variables, string $group): bool => self::inGroup($variables['request'] ?? null, $group),
            ),
        ];
    }

    public static function inGroup(?Request $request, string $group): bool
    {
        $siteAccess = $request?->attributes->get('siteaccess');
        if (!$siteAccess instanceof SiteAccess) {
            return false;
        }
        foreach ($siteAccess->groups as $name) {
            if ((string) $name === $group) {
                return true;
            }
        }

        return false;
    }
}
