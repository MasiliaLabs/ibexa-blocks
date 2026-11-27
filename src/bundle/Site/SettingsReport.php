<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessProviderInterface;
use Masilia\Bundle\IbexaBlocks\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * What a site has for each setting of the bundle, and where each value comes from: the siteaccess, one of its groups, or
 * "default". What nobody says is left to the code (not listed). The settings of the registry of the sites that the bundle
 * reads before its own (the address, the name) are listed apart.
 */
final readonly class SettingsReport
{
    private const NAMESPACE = 'masilia_ibexa_blocks';

    /** Settings that are one value as a whole although the tree gives them children. */
    private const WHOLE = ['layout_remote_id', 'layout_menus', 'favicons'];

    /** What the registry of the sites says first, and the settings it comes before. */
    private const REGISTRY = ['site_email' => 'forms.from_address, newsletter.from_address', 'site_name' => 'newsletter.subject, newsletter.list_name'];

    /**
     * @param list<string> $scopes The scopes the project gave settings to (keys of `system`)
     */
    public function __construct(
        private ConfigResolverInterface $config,
        private SiteAccessProviderInterface $accesses,
        private array $scopes = [],
    ) {
    }

    /** @return list<string> The names of the settings, from the tree of the configuration */
    public static function names(): array
    {
        $root = (new Configuration())->getConfigTreeBuilder()->buildTree();
        $system = $root instanceof ArrayNode ? $root->getChildren()['system'] ?? null : null;
        $scope = $system instanceof PrototypedArrayNode ? $system->getPrototype() : null;

        return $scope instanceof ArrayNode ? self::leaves($scope, '') : [];
    }

    /**
     * @return array{values: array<string, array{value: mixed, from: string}>, registry: array<string, array{value: mixed, replaces: string}>}
     */
    public function of(string $siteaccess): array
    {
        $site = $this->accesses->getSiteAccess($siteaccess);
        $groups = array_map(static fn ($group): string => $group->getName(), $site->groups);

        $values = [];
        foreach (self::names() as $name) {
            $from = null;
            $value = null;
            if ($this->config->hasParameter($name, self::NAMESPACE, 'default')) {
                [$from, $value] = ['default', $this->config->getParameter($name, self::NAMESPACE, 'default')];
            }
            // Asked for a group, Ibexa answers with "default" when the group says nothing: a group has a value of its own only if
            // it differs from the one of "default" (the first of the groups that does, as Ibexa does for a siteaccess).
            foreach ($groups as $scope) {
                if ($this->config->hasParameter($name, self::NAMESPACE, $scope)) {
                    $own = $this->config->getParameter($name, self::NAMESPACE, $scope);
                    if ($from === null || $own !== $value) {
                        [$from, $value] = [$scope, $own];
                        break;
                    }
                }
            }
            // Asked for a siteaccess, Ibexa answers with what its groups say too: it has a value of its own only if it differs.
            if ($this->config->hasParameter($name, self::NAMESPACE, $siteaccess)) {
                $own = $this->config->getParameter($name, self::NAMESPACE, $siteaccess);
                if ($from === null || $own !== $value) {
                    [$from, $value] = [$siteaccess, $own];
                }
            }
            $values[$name] = ['value' => $value, 'from' => $from ?? 'code'];
        }

        // What the registry of the sites says comes before the settings: the value the site really has.
        $registry = [];
        foreach (self::REGISTRY as $name => $replaces) {
            if ($this->config->hasParameter($name, 'masilia_ibexa_sites', $siteaccess)) {
                $registry[$name] = ['value' => $this->config->getParameter($name, 'masilia_ibexa_sites', $siteaccess), 'replaces' => $replaces];
            }
        }
        $email = $registry['site_email']['value'] ?? null;
        if (is_string($email) && $email !== '') {
            foreach (['forms.from_address', 'newsletter.from_address'] as $name) {
                $values[$name] = ['value' => $email, 'from' => 'registry (site_email)'];
            }
        }
        $siteName = $registry['site_name']['value'] ?? null;
        if (is_string($siteName) && $siteName !== '') {
            $defaults = new \Masilia\IbexaBlocks\Newsletter\NewsletterSettings();
            $subject = (string) ($values['newsletter.subject']['value'] ?? '');
            $list = (string) ($values['newsletter.list_name']['value'] ?? '');
            $values['newsletter.subject'] = ['value' => SettingsProvider::newsletterText($siteName, $subject, $defaults->subject, $defaults->subject . ' — ' . $siteName), 'from' => 'registry (site_name)'];
            $values['newsletter.list_name'] = ['value' => SettingsProvider::newsletterText($siteName, $list, $defaults->listName, 'the ' . $siteName . ' newsletter'), 'from' => 'registry (site_name)'];
        }

        return ['values' => $values, 'registry' => $registry];
    }

    /** @return list<string> The names of the siteaccesses */
    public function siteaccesses(): array
    {
        $names = [];
        foreach ($this->accesses->getSiteAccesses() as $siteaccess) {
            $names[] = $siteaccess->name;
        }

        return $names;
    }

    /**
     * The scopes under `system` that are no siteaccess, no group of one, nor "default" or "global": a typo is a setting that
     * nobody reads.
     *
     * @return list<string>
     */
    public function unknownScopes(): array
    {
        $known = ['default' => true, 'global' => true];
        foreach ($this->accesses->getSiteAccesses() as $siteaccess) {
            $known[$siteaccess->name] = true;
            foreach ($siteaccess->groups as $group) {
                $known[$group->getName()] = true;
            }
        }

        return array_values(array_filter($this->scopes, static fn (string $scope): bool => !isset($known[$scope])));
    }

    /** @return list<string> */
    private static function leaves(ArrayNode $node, string $prefix): array
    {
        $names = [];
        foreach ($node->getChildren() as $key => $child) {
            $name = $prefix === '' ? $key : $prefix . '.' . $key;
            if ($child instanceof ArrayNode && !$child instanceof PrototypedArrayNode && !in_array($name, self::WHOLE, true) && !in_array($name, SettingsProvider::TABLES, true)) {
                $names = [...$names, ...self::leaves($child, $name)];
            } else {
                $names[] = $name;
            }
        }

        return $names;
    }
}
