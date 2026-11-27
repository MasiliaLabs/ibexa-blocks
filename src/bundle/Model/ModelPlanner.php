<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaBlocks\Conventions;

/** Compares the definitions with the repository and says what is left to create. Reads only. */
final readonly class ModelPlanner
{
    public function __construct(
        private Repository $repository,
        private ContentTypeService $contentTypes,
        private FieldTypeService $fieldTypes,
        private Conventions $conventions,
    ) {
    }

    /** @param list<TypeDefinition> $definitions */
    public function plan(array $definitions, bool $allowContainer = false): ModelPlan
    {
        return $this->repository->sudo(fn (): ModelPlan => $this->build($definitions, $allowContainer));
    }

    /** @param list<TypeDefinition> $definitions */
    private function build(array $definitions, bool $allowContainer): ModelPlan
    {
        $groups = [];
        foreach ($definitions as $definition) {
            if (!isset($groups[$definition->group]) && !$this->groupExists($definition->group)) {
                $groups[$definition->group] = $definition->group;
            }
        }

        $types = [];
        foreach ($definitions as $definition) {
            $types[] = $this->item($definition, $allowContainer);
        }

        return new ModelPlan(array_values($groups), $types, $this->selections($definitions));
    }

    /** @return array{type: TypeDefinition, action: 'create'|'complete'|'conform', add: list<string>, notes: list<string>, container: bool} */
    private function item(TypeDefinition $definition, bool $allowContainer): array
    {
        $notes = [];
        $available = [];
        foreach ($definition->fields as $field) {
            if ($this->fieldTypes->hasFieldType($field['type'])) {
                $available[] = $field['identifier'];
            } else {
                $notes[] = sprintf('field "%s" skipped: the field type "%s" is not installed', $field['identifier'], $field['type']);
            }
        }

        try {
            $type = $this->contentTypes->loadContentTypeByIdentifier($definition->identifier);
        } catch (NotFoundException) {
            return ['type' => $definition, 'action' => 'create', 'add' => $available, 'notes' => $notes, 'container' => false];
        }

        $add = [];
        foreach ($definition->fields as $field) {
            $existing = $type->getFieldDefinition($field['identifier']);
            if ($existing === null) {
                if (in_array($field['identifier'], $available, true)) {
                    $add[] = $field['identifier'];
                }
            } elseif ($existing->fieldTypeIdentifier !== $field['type']) {
                $notes[] = sprintf('field "%s" is a "%s" here, the definition says "%s": left as it is', $field['identifier'], $existing->fieldTypeIdentifier, $field['type']);
            }
        }

        // A type that should hold content but does not: making it a container only widens what it can do, so it is safe, but it is
        // a change of a type that exists: it needs --allow-container. The other way round is never touched.
        $widen = $definition->container && !$type->isContainer();
        if ($widen && !$allowContainer) {
            $notes[] = 'is not a container, the definition says it is (the blocks of the page cannot go under it): run with --allow-container to change it';
        }
        $container = $widen && $allowContainer;

        return ['type' => $definition, 'action' => $add === [] && !$container ? 'conform' : 'complete', 'add' => $add, 'notes' => $notes, 'container' => $container];
    }

    /**
     * @param list<TypeDefinition> $definitions
     *
     * @return list<array{owner: string, field: string, add: list<string>}>
     */
    private function selections(array $definitions): array
    {
        // Where each block says it goes (allowed_on): a block that says nothing is left where it is.
        $byOwner = [];
        foreach ($definitions as $definition) {
            foreach ($definition->allowedOn as $owner) {
                $byOwner[$owner][] = $definition->identifier;
            }
        }
        $selections = [];
        foreach ($byOwner as $owner => $blocks) {
            $current = $this->allowed($owner);
            if ($current === null || $current === []) {
                continue; // no such type yet, or no restriction: every block is already allowed
            }
            $add = array_values(array_diff(array_unique($blocks), $current));
            if ($add !== []) {
                $selections[] = ['owner' => $owner, 'field' => $this->conventions->blocksField, 'add' => $add];
            }
        }

        return $selections;
    }

    /** @return list<string>|null The content types the blocks field of a type accepts; null when the type or the field is missing */
    public function allowed(string $owner): ?array
    {
        try {
            $field = $this->contentTypes->loadContentTypeByIdentifier($owner)->getFieldDefinition($this->conventions->blocksField);
        } catch (NotFoundException) {
            return null;
        }

        return $field === null ? null : array_values(array_map('strval', (array) ($field->getFieldSettings()['selectionContentTypes'] ?? [])));
    }

    private function groupExists(string $identifier): bool
    {
        try {
            $this->contentTypes->loadContentTypeGroupByIdentifier($identifier);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }
}
