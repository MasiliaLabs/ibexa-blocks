<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Masilia\Contracts\IbexaSites\Problem;
use Masilia\Contracts\IbexaSites\Site;
use Masilia\Contracts\IbexaSites\SiteAccessDefinition;
use Masilia\Contracts\IbexaSites\SiteInvalid;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteStatus;
use RuntimeException;

/**
 * Creates a site from start to finish: the content tree (container, layout, home page, media), then the site itself in
 * the registry of masilia/ibexa-sites, as a draft (root, layout, design, a siteaccess per language). Without that package
 * it prints the siteaccess configuration to paste. It never activates a site: that serves its hosts.
 */
final readonly class SiteCreator
{
    private const SHORT_CODES = ['eng' => 'en', 'fre' => 'fr', 'ger' => 'de', 'ara' => 'ar', 'spa' => 'es', 'ita' => 'it', 'por' => 'pt', 'dut' => 'nl', 'rus' => 'ru', 'chi' => 'zh', 'jpn' => 'ja', 'pol' => 'pl', 'tur' => 'tr'];

    /** @param SiteRegistry|null $registry Present when masilia/ibexa-sites is installed */
    public function __construct(
        private SiteTreeInitializer $tree,
        private ?SiteRegistry $registry = null,
    ) {
    }

    /**
     * @param list<string> $languages The languages of the site, the first one is the main one and at the root of the host
     *
     * @return array{tree: list<array<string, mixed>>, registry: bool, site: ?Site, problems: list<string>, config: ?string}
     *
     * @throws RuntimeException When the tree cannot be made
     */
    public function create(string $name, string $domain, string $design, array $languages, ?string $host, ?int $parentLocationId, ?int $mediaParentLocationId, bool $dryRun): array
    {
        $host = strtolower(trim($host ?? $domain));
        $slug = SiteTreeInitializer::slug($name);
        $languages = $languages === [] ? [null] : $languages;
        $contentLanguage = $languages[0];
        $problems = [];

        // Run again on a site that is registered: the tree is checked (it creates nothing twice) and the site is left as it is.
        $existing = $this->registry?->find($slug . '_group');

        // Before writing any content: what the registry already refuses (design, hosts, languages).
        if ($this->registry !== null && $existing === null) {
            // The root is not known yet (the home page does not exist): any location stands in for it.
            $problems = $this->check($this->toSite($this->draft($slug, $name, $design, $host, $languages, $parentLocationId ?? 2, null)));
            if ($problems !== []) {
                return ['tree' => [], 'registry' => true, 'site' => null, 'problems' => $problems, 'config' => null];
            }
        }

        $tree = $this->tree->init($name, $domain, $parentLocationId, $mediaParentLocationId, $contentLanguage, $dryRun);
        $home = null;
        $layout = null;
        foreach ($tree as $step) {
            $home = $step['what'] === 'home page' ? $step['locationId'] : $home;
            $layout = $step['what'] === 'layout' ? $step['remoteId'] : $layout;
        }
        $draft = $this->draft($slug, $name, $design, $host, $languages, $home ?? $parentLocationId ?? 0, $layout);

        if ($this->registry === null) {
            return ['tree' => $tree, 'registry' => false, 'site' => null, 'problems' => [], 'config' => $this->config($draft)];
        }
        $site = $this->toSite($draft);
        if ($dryRun || $home === null) {
            return ['tree' => $tree, 'registry' => true, 'site' => $site, 'problems' => [], 'config' => null];
        }
        if ($existing !== null) {
            return ['tree' => $tree, 'registry' => true, 'site' => $existing, 'problems' => [], 'config' => null];
        }
        $problems = $this->check($site);
        if ($problems !== []) {
            return ['tree' => $tree, 'registry' => true, 'site' => null, 'problems' => $problems, 'config' => null];
        }
        try {
            $site = $this->registry->create($site);
        } catch (SiteInvalid $e) {
            return ['tree' => $tree, 'registry' => true, 'site' => null, 'problems' => $e->problems, 'config' => null];
        }

        return ['tree' => $tree, 'registry' => true, 'site' => $site, 'problems' => [], 'config' => null];
    }

    /** @return list<string> */
    private function check(Site $site): array
    {
        $problems = [];
        foreach ($this->registry?->check($site, true) ?? [] as $problem) {
            if (!$problem->warning) {
                $problems[] = sprintf('%s: %s', $problem->path, $problem->message);
            }
        }

        return $problems;
    }

    /**
     * @param list<string|null> $languages
     *
     * @return array{identifier: string, name: string, design: string, root: int, layout: ?string, siteaccesses: list<array{name: string, languages: list<string>, host: string, prefix: ?string}>}
     */
    private function draft(string $slug, string $name, string $design, string $host, array $languages, int $root, ?string $layout): array
    {
        $siteaccesses = [];
        foreach ($languages as $i => $language) {
            $short = $language === null ? null : (self::SHORT_CODES[substr($language, 0, 3)] ?? strtolower(substr($language, 0, 2)));
            $siteaccesses[] = ['name' => $i === 0 ? $slug : $slug . '_' . $short, 'languages' => $language === null ? [] : [$language], 'host' => $host, 'prefix' => $i === 0 ? null : $short];
        }

        return ['identifier' => $slug . '_group', 'name' => $name, 'design' => $design, 'root' => $root, 'layout' => $layout, 'siteaccesses' => $siteaccesses];
    }

    /** @param array{identifier: string, name: string, design: string, root: int, layout: ?string, siteaccesses: list<array{name: string, languages: list<string>, host: string, prefix: ?string}>} $draft */
    private function toSite(array $draft): Site
    {
        return new Site(
            $draft['identifier'],
            $draft['name'],
            SiteStatus::Draft,
            $draft['root'],
            $draft['design'],
            array_map(static fn (array $s): SiteAccessDefinition => new SiteAccessDefinition($s['name'], $s['languages'], $s['host'], $s['prefix']), $draft['siteaccesses']),
            layoutRemoteId: $draft['layout'],
        );
    }

    /** @param array{identifier: string, name: string, design: string, root: int, layout: ?string, siteaccesses: list<array{name: string, languages: list<string>, host: string, prefix: ?string}>} $draft */
    private function config(array $draft): string
    {
        $names = array_column($draft['siteaccesses'], 'name');
        $lines = ['ibexa:', '    siteaccess:', '        list: [' . implode(', ', $names) . ']', '        groups:', '            ' . $draft['identifier'] . ': [' . implode(', ', $names) . ']', '    system:', '        ' . $draft['identifier'] . ':', '            design: ' . $draft['design'], '            content: { tree_root: { location_id: ' . $draft['root'] . ' } }', '# Match each siteaccess to its host (and prefix):'];
        foreach ($draft['siteaccesses'] as $s) {
            $lines[] = sprintf('#   %s: %s/%s, languages: %s', $s['name'], $s['host'], $s['prefix'] ?? '', implode(', ', $s['languages']));
        }
        $lines[] = '# masilia_ibexa_blocks.system.' . $draft['identifier'] . '.layout_remote_id: ' . ($draft['layout'] ?? '');

        return implode("
", $lines);
    }
}
