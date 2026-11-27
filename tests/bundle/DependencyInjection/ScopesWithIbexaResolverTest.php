<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\ChainConfigResolver;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\ConfigResolver\DefaultScopeConfigResolver;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\ConfigResolver\SiteAccessGroupConfigResolver;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\ConfigResolver\StaticSiteAccessConfigResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\Provider\StaticSiteAccessProvider;
use Masilia\Bundle\IbexaBlocks\DependencyInjection\MasiliaIbexaBlocksExtension;
use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** The settings of a site through the real resolver of Ibexa: siteaccess, then its group, then "default". */
final class ScopesWithIbexaResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $system
     */
    private function provider(array $system, string $siteaccess): SettingsProvider
    {
        return new SettingsProvider($this->build($system, $siteaccess)[0]);
    }

    /**
     * @param array<string, mixed> $system
     *
     * @return array{0: ChainConfigResolver, 1: StaticSiteAccessProvider, 2: ContainerBuilder}
     */
    private function build(array $system, string $siteaccess): array
    {
        $groups = ['fossil_group' => ['fossil', 'fossil_fr'], 'imal_group' => ['imal'], 'extra_group' => ['fossil_fr']];
        $byAccess = ['fossil' => ['fossil_group'], 'fossil_fr' => ['fossil_group', 'extra_group'], 'imal' => ['imal_group']];
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('ibexa.site_access.list', array_keys($byAccess));
        $container->setParameter('ibexa.site_access.groups', $groups);
        $container->setParameter('ibexa.site_access.groups_by_site_access', $byAccess);
        $container->setParameter('ibexa.site_access.config', []);
        (new MasiliaIbexaBlocksExtension())->load([['system' => $system]], $container);

        $accesses = new StaticSiteAccessProvider(array_keys($byAccess), $byAccess);
        $namespace = 'masilia_ibexa_blocks';
        $site = new StaticSiteAccessConfigResolver($container, $accesses, $namespace);
        $group = new SiteAccessGroupConfigResolver($container, $accesses, $namespace, $groups);
        $default = new DefaultScopeConfigResolver($container, $namespace);
        foreach ([$site, $group] as $resolver) {
            $resolver->setSiteAccess(new SiteAccess($siteaccess, 'test', null, null, array_map(static fn (string $g): \Ibexa\Core\MVC\Symfony\SiteAccessGroup => new \Ibexa\Core\MVC\Symfony\SiteAccessGroup($g), $byAccess[$siteaccess])));
        }
        $chain = new ChainConfigResolver();
        $chain->addResolver($site, 200);
        $chain->addResolver($group, 100);
        $chain->addResolver($default, 0);
        $chain->setDefaultNamespace($namespace);

        return [$chain, $accesses, $container];
    }

    public function testASettingComesFromTheSiteaccessThenItsGroupThenDefaultSettingBySetting(): void
    {
        $system = [
            'default' => ['newsletter' => ['subject' => 'Default subject', 'list_name' => 'the default list', 'from_address' => 'default@example.org']],
            'fossil_group' => ['newsletter' => ['subject' => 'Group subject', 'list_name' => 'the group list']],
            'fossil_fr' => ['newsletter' => ['subject' => 'Sujet fr']],
        ];

        $fr = $this->provider($system, 'fossil_fr')->newsletter();
        self::assertSame('Sujet fr', $fr->subject, 'the siteaccess');
        self::assertSame('the group list', $fr->listName, 'else its group');
        self::assertSame('default@example.org', $fr->fromAddress, 'else default');

        $en = $this->provider($system, 'fossil')->newsletter();
        self::assertSame('Group subject', $en->subject);

        $imal = $this->provider($system, 'imal')->newsletter();
        self::assertSame(['Default subject', 'the default list'], [$imal->subject, $imal->listName], 'a site that says nothing has default');
    }

    public function testAListOrATableOfAMoreSpecificScopeReplacesTheOneBelow(): void
    {
        $system = [
            'default' => ['search' => ['allowed_content_types' => ['article', 'page'], 'sorts' => ['a_asc' => ['field' => ['article', 'a'], 'direction' => 'asc'], 'b_asc' => ['field' => ['article', 'b'], 'direction' => 'asc']], 'page' => ['items' => 10]]],
            'fossil_group' => ['search' => ['allowed_content_types' => ['event'], 'sorts' => ['c_asc' => ['field' => ['event', 'c'], 'direction' => 'asc']]]],
        ];

        $fossil = $this->provider($system, 'fossil')->search();
        self::assertSame(['event'], $fossil->allowedContentTypes);
        self::assertSame(['c_asc'], array_keys($fossil->sorts), 'a table is replaced, not merged');
        self::assertSame(10, $fossil->pageItems, 'what the group does not say is default\'s');

        $imal = $this->provider($system, 'imal')->search();
        self::assertSame(['article', 'page'], $imal->allowedContentTypes);
        self::assertSame(['a_asc', 'b_asc'], array_keys($imal->sorts));
    }

    public function testTheDirectoriesAndTheConventionsAreThoseOfTheSite(): void
    {
        $system = [
            'default' => ['themes' => ['directories' => ['/a']], 'model' => ['directories' => ['/m']]],
            'fossil_group' => ['themes' => ['directories' => ['/b', '/c']], 'conventions' => ['items_field_by_type' => ['info_cards' => 'cards']]],
        ];
        $fossil = $this->provider($system, 'fossil');
        $imal = $this->provider($system, 'imal');

        self::assertSame(['/b', '/c'], $fossil->themeDirectories(['/default']));
        self::assertSame(['/a'], $imal->themeDirectories(['/default']));
        self::assertSame(['/always', '/m'], $imal->modelDirectories(['/always']));
        self::assertSame('cards', $fossil->conventions()->itemsFieldFor('info_cards'));
        self::assertSame('items', $imal->conventions()->itemsFieldFor('info_cards'));
    }

    public function testTheReportSaysWhereEachValueComesFromAndWhichScopesAreTypos(): void
    {
        $system = [
            'default' => ['newsletter' => ['subject' => 'Default subject', 'enabled' => true]],
            'fossil_group' => ['newsletter' => ['subject' => 'Group subject']],
            'fossil_fr' => ['favicons' => 'build/icons'],
            'fosil_group' => ['newsletter' => ['subject' => 'a typo']],
        ];
        [$chain, $accesses, $container] = $this->build($system, 'fossil_fr');
        $report = new \Masilia\Bundle\IbexaBlocks\Site\SettingsReport($chain, $accesses, $container->getParameter('masilia_blocks.scopes'));

        $values = $report->of('fossil_fr')['values'];
        self::assertSame(['value' => 'Group subject', 'from' => 'fossil_group'], $values['newsletter.subject']);
        self::assertSame(['value' => true, 'from' => 'default'], $values['newsletter.enabled']);
        self::assertSame(['value' => 'build/icons', 'from' => 'fossil_fr'], $values['favicons']);
        self::assertSame(['value' => null, 'from' => 'code'], $values['newsletter.list_name'], 'what nobody says is left to the code');
        self::assertSame(['fosil_group'], $report->unknownScopes());
        self::assertContains('newsletter.subject', \Masilia\Bundle\IbexaBlocks\Site\SettingsReport::names());
        self::assertContains('blocks.lists.auto_items.types', \Masilia\Bundle\IbexaBlocks\Site\SettingsReport::names());
        self::assertContains('conventions.items_field_by_type', \Masilia\Bundle\IbexaBlocks\Site\SettingsReport::names());
    }

    public function testASecondGroupAndTheRegistryAreInTheValueTheSiteReallyHas(): void
    {
        $system = [
            'default' => ['newsletter' => ['subject' => 'Default subject', 'from_address' => 'default@example.org']],
            'extra_group' => ['newsletter' => ['list_name' => 'the extra list']],
        ];
        [$chain, $accesses, $container] = $this->build($system, 'fossil_fr');
        $container->setParameter('masilia_ibexa_sites.fossil_fr.site_email', 'hello@fossil.example');
        $container->setParameter('masilia_ibexa_sites.fossil_fr.site_name', 'Fossil Exit');
        $report = new \Masilia\Bundle\IbexaBlocks\Site\SettingsReport($chain, $accesses, []);

        $values = $report->of('fossil_fr')['values'];
        self::assertSame(['value' => 'hello@fossil.example', 'from' => 'registry (site_email)'], $values['newsletter.from_address']);
        self::assertSame(['value' => 'Confirm your subscription — Fossil Exit', 'from' => 'registry (site_name)'], $values['newsletter.subject']);
        self::assertSame(['value' => 'the Fossil Exit newsletter', 'from' => 'registry (site_name)'], $values['newsletter.list_name']);

        $plain = $this->build($system, 'fossil_fr');
        $report = new \Masilia\Bundle\IbexaBlocks\Site\SettingsReport($plain[0], $plain[1], []);
        self::assertSame(['value' => 'the extra list', 'from' => 'extra_group'], $report->of('fossil_fr')['values']['newsletter.list_name'], 'a value of the second group is the group\'s, not the siteaccess\'');
        self::assertSame('default', $report->of('imal')['values']['newsletter.subject']['from']);
    }
}
