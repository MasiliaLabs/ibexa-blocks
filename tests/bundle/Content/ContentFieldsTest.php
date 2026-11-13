<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Bundle\IbexaBlocks\Content\ContentFields;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Contracts\IbexaBlocks\FieldRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ContentFieldsTest extends TestCase
{
    use Fixtures;

    /** @var list<string> */
    private array $rendered = [];
    private int $loads = 0;

    private function fields(): ContentFields
    {
        $content = $this->content($this->type('page', ['title' => ['type' => 'ibexa_string'], 'image' => ['type' => 'ibexa_image'], 'body' => ['type' => 'ibexa_text']]));
        $renderer = new class(fn (string $identifier) => $this->rendered[] = $identifier) implements FieldRenderer {
            public function __construct(private \Closure $seen) {}

            public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
            {
                ($this->seen)($identifier);

                return match ($identifier) {
                    'title' => 'Hello',
                    'image' => static fn (string $variation = 'original'): string => 'image@' . $variation,
                    default => null,
                };
            }
        };
        $registry = new FieldRendererRegistry(new ServiceLocator(['ibexa_string' => fn () => $renderer, 'ibexa_image' => fn () => $renderer, 'ibexa_text' => fn () => $renderer]));

        return ContentFields::of(function () use ($content): Content {
            ++$this->loads;

            return $content;
        }, $registry);
    }

    public function testAFieldIsRenderedWhenItIsFirstReadAndOnlyOnce(): void
    {
        $fields = $this->fields();
        $this->rendered = [];

        self::assertSame('Hello', $fields['title']);
        self::assertSame('Hello', $fields->get('title'));
        self::assertSame(['title'], $this->rendered, 'the other fields were not rendered');
    }

    public function testAFieldThatIsAClosureIsCalledWithTheVariationOfATemplate(): void
    {
        $fields = $this->fields();

        self::assertSame('image@card', $fields->image('card'));
        self::assertSame('image@original', $fields->image());
    }

    public function testAllGivesEveryFieldWithTheVariationNamedForIt(): void
    {
        $all = $this->fields()->all(['image' => 'hero']);

        self::assertSame(['title' => 'Hello', 'image' => 'image@hero', 'body' => null], $all);
    }

    public function testItKnowsItsFieldsAndRefusesToBeWrittenTo(): void
    {
        $fields = $this->fields();

        self::assertTrue(isset($fields['title']));
        self::assertFalse(isset($fields['nope']));
        self::assertNull($fields['nope']);
        self::assertCount(3, $fields);
        self::assertSame(['title', 'image', 'body'], array_keys(iterator_to_array($fields)));

        $this->expectException(\LogicException::class);
        $fields['title'] = 'x';
    }

    public function testAnImageSetIsFixedAtTheVariationOfAManifestOrLeftToTheTemplate(): void
    {
        $set = new \Masilia\IbexaBlocks\Value\ImageSet(static fn (string $variation) => new \Masilia\IbexaBlocks\Value\Image('/img/' . $variation));
        $renderer = new class($set) implements FieldRenderer {
            public function __construct(private object $set) {}

            public function render(Content $content, string $identifier, FieldDefinition $definition): mixed
            {
                return $identifier === 'image' ? $this->set : 'value';
            }
        };
        $content = $this->content($this->type('page', ['title' => ['type' => 'ibexa_string'], 'image' => ['type' => 'ibexa_image']]));
        $registry = new FieldRendererRegistry(new ServiceLocator(['ibexa_string' => fn () => $renderer, 'ibexa_image' => fn () => $renderer]));
        $fields = ContentFields::of(static fn () => $content, $registry);

        self::assertSame($set, $fields->all()['image'], 'the template asks for its variation');
        self::assertSame('/img/hero', $fields->all(['image' => 'hero'])['image']->uri, 'a manifest still fixes it');
        self::assertSame('/img/card', $fields->image('card')->uri, 'fields.image(\'card\') keeps working');
    }
}
