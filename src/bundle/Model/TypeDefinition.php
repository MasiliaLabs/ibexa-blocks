<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Model;

/**
 * A content type as a package or a theme describes it: plain data, read from a YAML file, written to the repository by
 * the ModelInstaller. The same format for the socle of the package and for the types of a theme.
 *
 * @phpstan-type FieldData array{identifier: string, type: string, names: array<string, string>, descriptions: array<string, string>, required: bool, searchable: bool, translatable: bool, category: string, position: int, settings: array<string, mixed>, validator: array<string, mixed>}
 */
final readonly class TypeDefinition
{
    private const KNOWN_KEYS = ['identifier', 'group', 'names', 'name_pattern', 'url_name_pattern', 'container', 'sort', 'always_available', 'allowed_on', 'fields'];
    private const KNOWN_FIELD_KEYS = ['identifier', 'type', 'name', 'names', 'description', 'descriptions', 'required', 'searchable', 'translatable', 'category', 'position', 'settings', 'validator'];
    private const SORT_FIELDS = ['path', 'published', 'modified', 'section', 'depth', 'class_identifier', 'class_name', 'priority', 'name', 'node_id', 'content_id'];

    /**
     * @param array<string, string> $names The name by language code; the first one is the main language
     * @param list<FieldData>       $fields
     * @param list<string>          $allowedOn The content types whose blocks field accepts this block (page, home_page...); empty: none is touched
     */
    public function __construct(
        public string $identifier,
        public string $group,
        public array $names,
        public string $namePattern,
        public string $urlNamePattern,
        public bool $container,
        public string $sortField,
        public string $sortOrder,
        public bool $alwaysAvailable,
        public array $fields,
        public string $source = '',
        public array $allowedOn = [],
    ) {
    }

    public function mainLanguage(): string
    {
        return (string) array_key_first($this->names);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidModel
     */
    public static function fromArray(array $data, string $source): self
    {
        $identifier = (string) ($data['identifier'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidModel(sprintf('%s: "identifier" must be lower case letters, digits and _ (got "%s").', $source, $identifier));
        }
        foreach (array_diff(array_keys($data), self::KNOWN_KEYS) as $unknown) {
            throw new InvalidModel(sprintf('%s: unknown key "%s" (known: %s).', $source, $unknown, implode(', ', self::KNOWN_KEYS)));
        }
        $group = (string) ($data['group'] ?? '');
        $names = is_array($data['names'] ?? null) ? array_map('strval', $data['names']) : [];
        if ($group === '' || $names === []) {
            throw new InvalidModel(sprintf('%s: "group" and "names" (a name by language code) are required.', $source));
        }
        $sort = (array) ($data['sort'] ?? []);
        $sortField = (string) ($sort['field'] ?? 'name');
        if (!in_array($sortField, self::SORT_FIELDS, true)) {
            throw new InvalidModel(sprintf('%s: sort.field "%s" is unknown (known: %s).', $source, $sortField, implode(', ', self::SORT_FIELDS)));
        }
        $sortOrder = strtoupper((string) ($sort['order'] ?? 'ASC'));
        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            throw new InvalidModel(sprintf('%s: sort.order must be ASC or DESC.', $source));
        }

        $fields = [];
        $seen = [];
        foreach ((array) ($data['fields'] ?? []) as $i => $field) {
            $fields[] = self::field((array) $field, sprintf('%s: fields[%d]', $source, $i), $seen);
        }
        if ($fields === []) {
            throw new InvalidModel(sprintf('%s: a content type needs at least one field.', $source));
        }

        $allowedOn = [];
        foreach ((array) ($data['allowed_on'] ?? []) as $owner) {
            if (!is_string($owner) || preg_match('/^[a-z][a-z0-9_]*$/', $owner) !== 1) {
                throw new InvalidModel(sprintf('%s: "allowed_on" is a list of content type identifiers (page, home_page...).', $source));
            }
            $allowedOn[] = $owner;
        }

        $pattern = (string) ($data['name_pattern'] ?? '<' . $fields[0]['identifier'] . '>');

        return new self($identifier, $group, $names, $pattern, (string) ($data['url_name_pattern'] ?? $pattern), (bool) ($data['container'] ?? false), $sortField, $sortOrder, (bool) ($data['always_available'] ?? true), $fields, $source, $allowedOn);
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, true>  $seen
     *
     * @return FieldData
     */
    private static function field(array $field, string $where, array &$seen): array
    {
        foreach (array_diff(array_keys($field), self::KNOWN_FIELD_KEYS) as $unknown) {
            throw new InvalidModel(sprintf('%s: unknown key "%s" (known: %s).', $where, $unknown, implode(', ', self::KNOWN_FIELD_KEYS)));
        }
        $identifier = (string) ($field['identifier'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]*$/', $identifier) !== 1 || isset($seen[$identifier])) {
            throw new InvalidModel(sprintf('%s: "identifier" must be unique lower case letters, digits and _ (got "%s").', $where, $identifier));
        }
        $seen[$identifier] = true;
        $type = (string) ($field['type'] ?? '');
        if ($type === '') {
            throw new InvalidModel(sprintf('%s: "type" (a field type identifier such as ibexa_string) is required.', $where));
        }

        return [
            'identifier' => $identifier,
            'type' => $type,
            'names' => is_array($field['names'] ?? null) ? array_map('strval', $field['names']) : ['*' => (string) ($field['name'] ?? $identifier)],
            'descriptions' => is_array($field['descriptions'] ?? null) ? array_map('strval', $field['descriptions']) : ['*' => (string) ($field['description'] ?? '')],
            'required' => (bool) ($field['required'] ?? false),
            'searchable' => (bool) ($field['searchable'] ?? false),
            'translatable' => (bool) ($field['translatable'] ?? true),
            'category' => (string) ($field['category'] ?? 'content'),
            'position' => (int) ($field['position'] ?? 0),
            'settings' => (array) ($field['settings'] ?? []),
            'validator' => (array) ($field['validator'] ?? []),
        ];
    }

    /** @return list<string> */
    public function fieldIdentifiers(): array
    {
        return array_column($this->fields, 'identifier');
    }
}
