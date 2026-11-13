<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Conventions;
use PHPUnit\Framework\TestCase;

final class ConventionsTest extends TestCase
{
    public function testTheDefaultsAreTheMasiliaWay(): void
    {
        $conventions = new Conventions();

        self::assertSame(['page', 'blocks', 'items', 'Blocks'], [$conventions->pageType, $conventions->blocksField, $conventions->itemsField, $conventions->blockGroup]);
        self::assertContains('Block items', $conventions->contentGroups);
    }

    public function testAProjectSetsItsOwnAndKeepsTheRest(): void
    {
        $conventions = Conventions::fromSettings(['page_type' => 'landing', 'blocks_field' => 'sections', 'content_groups' => ['Pages']]);

        self::assertSame('landing', $conventions->pageType);
        self::assertSame('sections', $conventions->blocksField);
        self::assertSame(['Pages'], $conventions->contentGroups);
        self::assertSame('items', $conventions->itemsField, 'what is not set keeps its default');
    }
}
