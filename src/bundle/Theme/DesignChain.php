<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The themes of the design the current siteaccess uses, in the order the design engine looks for a template in them:
 * the first one that has it wins. It is the one chain of themes: a theme does not say what it extends.
 */
final readonly class DesignChain
{
    /**
     * @param array<string, list<string>> $designs Design => its themes, as the design engine knows them
     */
    public function __construct(
        private ConfigResolverInterface $config,
        #[Autowire('%ibexa.design.list%')]
        private array $designs,
    ) {
    }

    /**
     * @return list<string>
     */
    public function themes(): array
    {
        $design = (string) $this->config->getParameter('design');

        return array_values(array_unique([$design, ...(array) ($this->designs[$design] ?? [])]));
    }
}
