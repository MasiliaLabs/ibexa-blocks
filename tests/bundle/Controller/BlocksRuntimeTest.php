<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Controller;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Masilia\Bundle\IbexaBlocks\Templating\BlocksRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

final class BlocksRuntimeTest extends TestCase
{
    public function testEachBlockIsShownByItsOwnViewAndAnythingElseIsSkipped(): void
    {
        $shown = [];
        $fragments = $this->createMock(FragmentHandler::class);
        $fragments->method('render')->willReturnCallback(function (ControllerReference $reference) use (&$shown): string {
            $shown[] = [$reference->controller, $reference->attributes];

            return '<b' . $reference->attributes['contentId'] . '/>';
        });

        $html = (new BlocksRuntime($fragments))->render([self::renderable(11), ['id' => 12], null, self::renderable(13)], ['current_location_id' => 5]);

        self::assertSame('<b11/><b13/>', $html);
        $params = ['current_location_id' => 5];
        self::assertSame(
            [['ibexa_content::viewAction', ['contentId' => 11, 'viewType' => 'blocks', 'params' => $params]], ['ibexa_content::viewAction', ['contentId' => 13, 'viewType' => 'blocks', 'params' => $params]]],
            $shown,
        );
    }

    private static function renderable(int $id): RenderableContent
    {
        $services = (new \ReflectionClass(RenderableServices::class))->newInstanceWithoutConstructor();

        return new RenderableContent($id, null, static fn () => throw new \LogicException('not loaded'), static fn () => null, $services);
    }
}
