<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Controller;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\Bundle\IbexaBlocks\Content\RenderableServices;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Templating\BlocksRuntime;
use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeSettings;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;
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

        $html = $this->runtime($fragments)->render([self::renderable(11), ['id' => 12], null, self::renderable(13)], ['current_location_id' => 5]);

        self::assertSame('<b11/><b13/>', $html);
        $params = ['current_location_id' => 5];
        self::assertSame(
            [['ibexa_content::viewAction', ['contentId' => 11, 'viewType' => 'blocks', 'params' => $params]], ['ibexa_content::viewAction', ['contentId' => 13, 'viewType' => 'blocks', 'params' => $params]]],
            $shown,
        );
    }

    private function runtime(FragmentHandler $fragments, ?Environment $twig = null): BlocksRuntime
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn('standard');

        return new BlocksRuntime($fragments, new ThemeSettings(new ThemeRegistry([]), new DesignChain($config, [])), $twig ?? new Environment(new ArrayLoader([])));
    }

    public function testTheToolbarIsShownOnlyWhenTheToolboxIsInstalledAndSomeoneIsSignedIn(): void
    {
        $fragments = $this->createMock(FragmentHandler::class);

        self::assertSame('', $this->runtime($fragments)->toolbox(), 'no toolbox, no toolbar');

        $twig = new Environment(new ArrayLoader([]));
        $twig->addFunction(new TwigFunction('masilia_editorial_toolbox', static fn (): string => '<div class="toolbar"></div>', ['is_safe' => ['html']]));
        $twig->addGlobal('app', (object) ['user' => null]);
        self::assertSame('', $this->runtime($fragments, $twig)->toolbox(), 'nobody signed in');

        $twig->addGlobal('app', (object) ['user' => 'editor']);
        self::assertSame('<div class="toolbar"></div>', $this->runtime($fragments, $twig)->toolbox());
    }

    public function testTheLayoutSettingsAreThoseOfTheThemesAndAnUnknownOneIsAMistake(): void
    {
        $runtime = $this->runtime($this->createMock(FragmentHandler::class));

        self::assertNull($runtime->layout('assets'));
        self::assertSame([], $runtime->layout('fonts'));
        self::assertTrue($runtime->layout('notifications'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The layout has no "colour"');
        $runtime->layout('colour');
    }

    private static function renderable(int $id): RenderableContent
    {
        $services = (new \ReflectionClass(RenderableServices::class))->newInstanceWithoutConstructor();

        return new RenderableContent($id, null, static fn () => throw new \LogicException('not loaded'), static fn () => null, $services);
    }
}
