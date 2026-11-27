<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Content;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\FieldType\Relation\Value as RelationValue;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Masilia\Bundle\IbexaBlocks\Content\ContentFactory;
use Masilia\Bundle\IbexaBlocks\Content\FieldRendererRegistry;
use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Bundle\IbexaBlocks\Content\Renderer\RelationRenderer;
use Masilia\Contracts\IbexaBlocks\AdminMetaProvider;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\CacheTagger;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RelationRendererTest extends TestCase
{
    use Fixtures;

    /**
     * @param array<int, Content|\Throwable> $related What loading each related content gives
     */
    private function renderer(array $related, ?LoggerInterface $logger = null, array $summaryTypes = []): RelationRenderer
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContent')->willReturnCallback(static function (int $id) use ($related): Content {
            $result = $related[$id];
            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result;
        });
        $services = new RenderableServices(
            new FieldRendererRegistry(new ServiceLocator([])),
            $this->createStub(LinkProvider::class),
            $this->createStub(BreadcrumbProvider::class),
            $this->createStub(AdminMetaProvider::class),
            $this->createStub(CacheTagger::class),
        );

        return new RelationRenderer(new ContentFactory($contents, $this->createStub(LocationService::class), $services), TestSettings::fromParameters(['masilia_ibexa_blocks:fields.summary_types' => $summaryTypes]), $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function render(RelationRenderer $renderer, \Ibexa\Contracts\Core\FieldType\Value $value): mixed
    {
        $type = $this->type('page', ['blocks' => ['type' => 'ibexa_object_relation_list']]);
        $content = $this->createStub(Content::class);
        $content->method('getFieldValue')->willReturn($value);

        return $renderer->render($content, 'blocks', $type->getFieldDefinition('blocks'));
    }

    public function testARelationListGivesTheContentsThatCanBeShown(): void
    {
        $type = $this->type('hero', []);
        $renderer = $this->renderer([1 => $this->content($type, [], 1, 'One'), 2 => $this->content($type, [], 2, 'Two')]);

        $blocks = $this->render($renderer, new RelationListValue([1, 2]));

        self::assertContainsOnlyInstancesOf(RenderableContent::class, $blocks);
        self::assertSame([1, 2], array_map(static fn (RenderableContent $b): int => $b->id(), $blocks));
    }

    public function testAContentThatIsGoneOrForbiddenIsLeftOutWithoutAFuss(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $renderer = $this->renderer([1 => $this->content($this->type('hero', []), [], 1), 2 => new NotFoundException('content', 2)], $logger);

        self::assertCount(1, $this->render($renderer, new RelationListValue([1, 2])));
    }

    public function testAnUnexpectedFailureLeavesTheContentOutAndSaysWhy(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('could not be loaded'),
            self::callback(static fn (array $context): bool => $context['id'] === 2 && $context['message'] === 'the database is gone'),
        );
        $renderer = $this->renderer([1 => $this->content($this->type('hero', []), [], 1), 2 => new \RuntimeException('the database is gone')], $logger);

        self::assertCount(1, $this->render($renderer, new RelationListValue([1, 2])));
    }

    public function testASummaryTypeGivesItsIdAndName(): void
    {
        $renderer = $this->renderer([5 => $this->content($this->type('tag', []), [], 5, 'Climate')], null, ['tag']);

        self::assertSame([['id' => 5, 'name' => 'Climate']], $this->render($renderer, new RelationListValue([5])));
    }

    public function testASingleRelationGivesOneContentOrNull(): void
    {
        $renderer = $this->renderer([8 => $this->content($this->type('hero', []), [], 8)]);

        self::assertSame(8, $this->render($renderer, new RelationValue(8))->id());
        self::assertNull($this->render($renderer, new RelationValue()));
    }
}
