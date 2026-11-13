<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Search;

/** How a sort key of the search is applied: by a field of a content type, or (with no field) by the date of publication. */
final readonly class SortSpec
{
    /**
     * @param array{0: string, 1: string}|null $field Content type and field identifier; null for the date of publication
     */
    public function __construct(
        public ?array $field,
        public bool $ascending,
        public bool $byName = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data {field?: [type, field], direction?: asc|desc, name?: bool}
     */
    public static function fromArray(string $key, array $data): self
    {
        $field = $data['field'] ?? null;
        if ($field !== null && (!is_array($field) || count($field) !== 2)) {
            throw new \InvalidArgumentException(sprintf('The sort "%s": field must be [content type, field identifier].', $key));
        }
        $direction = strtolower((string) ($data['direction'] ?? 'desc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(sprintf('The sort "%s": direction must be asc or desc.', $key));
        }

        return new self($field === null ? null : [(string) $field[0], (string) $field[1]], $direction === 'asc', (bool) ($data['name'] ?? false));
    }
}
