<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

/**
 * What installing a set of definitions would do to the repository. Nothing here is ever a change or a removal of what
 * exists: a type is created, a missing field is added, a block is allowed on a page; the rest is only reported.
 */
final readonly class ModelPlan
{
    /**
     * @param list<string>                                                                                                        $groups     Content type groups to create
     * @param list<array{type: TypeDefinition, action: 'create'|'complete'|'conform', add: list<string>, notes: list<string>, container: bool}>      $types
     * @param list<array{owner: string, field: string, add: list<string>}>                                                         $selections Blocks to allow in the blocks field of a page type
     */
    public function __construct(
        public array $groups,
        public array $types,
        public array $selections,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->changes() === 0;
    }

    /** The number of writes the plan makes */
    public function changes(): int
    {
        $n = count($this->groups) + count($this->selections);
        foreach ($this->types as $item) {
            $n += $item['action'] === 'conform' ? 0 : 1;
        }

        return $n;
    }
}
