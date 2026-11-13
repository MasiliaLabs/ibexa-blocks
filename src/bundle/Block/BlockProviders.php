<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block;

use Masilia\Contracts\IbexaBlocks\BlockViewParameters;
use Psr\Container\ContainerInterface;

/** The providers of parameters of a content type in a view type, in order of priority. Built when first asked for. */
final readonly class BlockProviders
{
    /**
     * @param ContainerInterface                                                                 $providers The providers by service id (set by BlockProviderPass)
     * @param array<string, list<array{id: string, viewTypes: list<string>, priority: int}>>     $byType    Content type => its providers
     */
    public function __construct(
        private ContainerInterface $providers,
        private array $byType = [],
    ) {
    }

    /**
     * @return list<BlockViewParameters>
     */
    public function for(string $contentType, string $viewType): array
    {
        $found = array_filter(
            $this->byType[$contentType] ?? [],
            static fn (array $provider): bool => $provider['viewTypes'] === [] || in_array($viewType, $provider['viewTypes'], true),
        );
        usort($found, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return array_map(fn (array $provider): BlockViewParameters => $this->providers->get($provider['id']), $found);
    }
}
