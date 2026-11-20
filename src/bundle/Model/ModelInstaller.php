<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinitionCreateStruct;

/** Writes a ModelPlan: creates the groups and the types, adds the missing fields, allows the blocks on the pages. */
final readonly class ModelInstaller
{
    private const SORT_FIELDS = [
        'path' => Location::SORT_FIELD_PATH,
        'published' => Location::SORT_FIELD_PUBLISHED,
        'modified' => Location::SORT_FIELD_MODIFIED,
        'section' => Location::SORT_FIELD_SECTION,
        'depth' => Location::SORT_FIELD_DEPTH,
        'class_identifier' => Location::SORT_FIELD_CLASS_IDENTIFIER,
        'class_name' => Location::SORT_FIELD_CLASS_NAME,
        'priority' => Location::SORT_FIELD_PRIORITY,
        'name' => Location::SORT_FIELD_NAME,
        'node_id' => Location::SORT_FIELD_NODE_ID,
        'content_id' => Location::SORT_FIELD_CONTENTOBJECT_ID,
    ];

    public function __construct(
        private Repository $repository,
        private ContentTypeService $contentTypes,
    ) {
    }

    /**
     * @return list<string> What was done, one line each
     */
    public function apply(ModelPlan $plan): array
    {
        return $this->repository->sudo(fn (): array => $this->write($plan));
    }

    /** @return list<string> */
    private function write(ModelPlan $plan): array
    {
        $done = [];
        foreach ($plan->groups as $group) {
            $this->contentTypes->createContentTypeGroup($this->contentTypes->newContentTypeGroupCreateStruct($group));
            $done[] = sprintf('group "%s" created', $group);
        }
        foreach ($plan->types as $item) {
            if ($item['action'] === 'create') {
                $this->create($item['type'], $item['add']);
                $done[] = sprintf('type "%s" created (%d fields)', $item['type']->identifier, count($item['add']));
            } elseif ($item['action'] === 'complete') {
                $this->complete($item['type'], $item['add'], $item['container']);
                $done[] = sprintf('type "%s" completed: %s', $item['type']->identifier, trim(($item['container'] ? 'now a container ' : '') . implode(', ', $item['add'])));
            }
        }
        foreach ($plan->selections as $selection) {
            $this->allow($selection['owner'], $selection['field'], $selection['add']);
            $done[] = sprintf('%s.%s now accepts %s', $selection['owner'], $selection['field'], implode(', ', $selection['add']));
        }

        return $done;
    }

    /** @param list<string> $fields */
    private function create(TypeDefinition $definition, array $fields): void
    {
        $struct = $this->contentTypes->newContentTypeCreateStruct($definition->identifier);
        $struct->mainLanguageCode = $definition->mainLanguage();
        $struct->names = $definition->names;
        $struct->nameSchema = $definition->namePattern;
        $struct->urlAliasSchema = $definition->urlNamePattern;
        $struct->isContainer = $definition->container;
        $struct->defaultSortField = self::SORT_FIELDS[$definition->sortField];
        $struct->defaultSortOrder = $definition->sortOrder === 'DESC' ? Location::SORT_ORDER_DESC : Location::SORT_ORDER_ASC;
        $struct->defaultAlwaysAvailable = $definition->alwaysAvailable;
        foreach ($definition->fields as $field) {
            if (in_array($field['identifier'], $fields, true)) {
                $struct->addFieldDefinition($this->fieldStruct($definition, $field));
            }
        }
        $draft = $this->contentTypes->createContentType($struct, [$this->group($definition->group)]);
        $this->contentTypes->publishContentTypeDraft($draft);
    }

    /** @param list<string> $fields */
    private function complete(TypeDefinition $definition, array $fields, bool $container = false): void
    {
        $draft = $this->contentTypes->createContentTypeDraft($this->contentTypes->loadContentTypeByIdentifier($definition->identifier));
        if ($container) {
            $update = $this->contentTypes->newContentTypeUpdateStruct();
            $update->isContainer = true;
            $this->contentTypes->updateContentTypeDraft($draft, $update);
        }
        foreach ($definition->fields as $field) {
            if (in_array($field['identifier'], $fields, true)) {
                $this->contentTypes->addFieldDefinition($draft, $this->fieldStruct($definition, $field));
            }
        }
        $this->contentTypes->publishContentTypeDraft($draft);
    }

    /** @param list<string> $add */
    private function allow(string $owner, string $field, array $add): void
    {
        $draft = $this->contentTypes->createContentTypeDraft($this->contentTypes->loadContentTypeByIdentifier($owner));
        $definition = $draft->getFieldDefinition($field);
        if ($definition === null) {
            $this->contentTypes->deleteContentType($draft); // nothing to change: do not leave a draft that blocks the next run

            return;
        }
        $update = $this->contentTypes->newFieldDefinitionUpdateStruct();
        $settings = $definition->getFieldSettings();
        $settings['selectionContentTypes'] = array_values(array_unique([...(array) ($settings['selectionContentTypes'] ?? []), ...$add]));
        $update->fieldSettings = $settings;
        $this->contentTypes->updateFieldDefinition($draft, $definition, $update);
        $this->contentTypes->publishContentTypeDraft($draft);
    }

    /** @param array<string, mixed> $field */
    private function fieldStruct(TypeDefinition $definition, array $field): FieldDefinitionCreateStruct
    {
        $main = $definition->mainLanguage();
        $struct = $this->contentTypes->newFieldDefinitionCreateStruct($field['identifier'], $field['type']);
        $struct->names = $this->byLanguage($field['names'], $main);
        $struct->descriptions = $this->byLanguage($field['descriptions'], $main);
        $struct->fieldGroup = $field['category'];
        $struct->position = $field['position'];
        $struct->isTranslatable = $field['translatable'];
        $struct->isRequired = $field['required'];
        $struct->isSearchable = $field['searchable'];
        $struct->fieldSettings = $field['settings'];
        $struct->validatorConfiguration = $field['validator'];

        return $struct;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string> "*" is the main language of the type
     */
    private function byLanguage(array $values, string $main): array
    {
        $result = [];
        foreach ($values as $language => $value) {
            $result[$language === '*' ? $main : $language] = $value;
        }

        return $result;
    }

    private function group(string $identifier): ContentTypeGroup
    {
        return $this->contentTypes->loadContentTypeGroupByIdentifier($identifier);
    }
}
