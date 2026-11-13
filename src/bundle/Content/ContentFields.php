<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\IbexaBlocks\Value\ImageSet;

/**
 * The fields of a content as a template reads them: `content.fields.title`, `content.fields.image('card')`.
 * A field is rendered the first time it is read, not before; `all()` renders them all for a block that wants them as variables.
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final class ContentFields implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @var array<string, mixed> */
    private array $rendered = [];

    /** @var list<string> */
    private array $identifiers;

    /**
     * @param \Closure(): Content $content Loads the content, which is only needed when a field is read
     */
    public function __construct(
        private readonly \Closure $content,
        private readonly FieldRendererRegistry $registry,
        array $identifiers,
    ) {
        $this->identifiers = array_values($identifiers);
    }

    /**
     * Builds the fields from the field definitions of a content type.
     *
     * @param \Closure(): Content $content
     */
    public static function of(\Closure $content, FieldRendererRegistry $registry): self
    {
        $identifiers = [];
        foreach ($content()->getContentType()->getFieldDefinitions() as $definition) {
            $identifiers[] = $definition->identifier;
        }

        return new self($content, $registry, $identifiers);
    }

    public function get(string $identifier): mixed
    {
        if (!in_array($identifier, $this->identifiers, true)) {
            return null;
        }
        if (!array_key_exists($identifier, $this->rendered)) {
            $this->rendered[$identifier] = $this->registry->render(($this->content)(), $identifier);
        }

        return $this->rendered[$identifier];
    }

    /**
     * Every field, rendered. An image is given at the variation named for its field in $variations (an image the caller wants
     * fixed, for a search or a theme that still lists them); without one it stays an ImageSet, and the template asks.
     *
     * @param array<string, string> $variations Field identifier => variation
     *
     * @return array<string, mixed>
     */
    public function all(array $variations = []): array
    {
        $values = [];
        foreach ($this->identifiers as $identifier) {
            $value = $this->get($identifier);
            $values[$identifier] = match (true) {
                $value instanceof ImageSet && isset($variations[$identifier]) => $value->at($variations[$identifier]),
                $value instanceof \Closure => $value($variations[$identifier] ?? 'original'),
                default => $value,
            };
        }

        return $values;
    }

    /** `content.fields.image('card')` in a template: the image at that variation (a closure field is called with the arguments). */
    public function __call(string $name, array $arguments): mixed
    {
        $value = $this->get($name);

        return match (true) {
            $value instanceof ImageSet => $value->at(...$arguments),
            $value instanceof \Closure => $value(...$arguments),
            default => null,
        };
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && in_array($offset, $this->identifiers, true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('The fields of a content are read only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('The fields of a content are read only.');
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->identifiers as $identifier) {
            yield $identifier => $this->get($identifier);
        }
    }

    public function count(): int
    {
        return count($this->identifiers);
    }
}
