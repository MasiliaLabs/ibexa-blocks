<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Site\LanguageSwitcher;
use Masilia\Bundle\IbexaBlocks\Site\LayoutProvider;
use Masilia\Contracts\IbexaBlocks\MenuProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The header, the footer and the notifications of a page, each a fragment of its own (ESI), so that they are cached once for the
 * site and not once per page. The theme says what they look like: layout/header.twig, layout/footer.twig and
 * layout/parts/notification.html.twig. They are given the layout content of the site (its fields as variables, `layout` and
 * `content`), the menus, and for the header the other languages of the page.
 */
final readonly class LayoutController
{
    public function __construct(
        private LayoutProvider $layout,
        private MenuProvider $menus,
        private LanguageSwitcher $languages,
        private LocationService $locations,
        private ConfigResolverInterface $config,
        private Environment $twig,
        private ?SettingsProvider $provider = null,
    ) {
    }

    public function header(int $currentLocationId, string $currentUri): Response
    {
        try {
            $location = $this->locations->loadLocation($currentLocationId);
        } catch (NotFoundException|UnauthorizedException) {
            $location = null;
        }

        return $this->page('@ibexadesign/layout/header.twig', [
            'menu_header' => $this->menu('header'),
            'switch_languages' => $location === null ? [] : $this->languages->forLocation($location),
            'current_language' => ((array) $this->config->getParameter('languages'))[0] ?? 'en',
            'current_uri' => $currentUri,
        ]);
    }

    public function footer(): Response
    {
        return $this->page('@ibexadesign/layout/footer.twig', [
            'menu_footer' => $this->menu('footer'),
            'legal_links' => $this->menu('footer_bottom'),
        ]);
    }

    public function notifications(string $notificationsType = 'success'): Response
    {
        $response = new Response($this->twig->render('@ibexadesign/layout/parts/notification.html.twig', ['notifications_type' => $notificationsType]));
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->setPrivate();

        return $response;
    }

    private function menu(string $part): mixed
    {
        $type = $this->layout->menuType($part);

        return $type === null ? null : $this->menus->menu($type, $this->layout->rootLocationId());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function page(string $template, array $parameters): Response
    {
        $layout = $this->layout->renderable();
        $fields = $layout?->fields()->all() ?? [];

        return new Response($this->twig->render($template, array_merge(
            ($this->provider?->flattenFields() ?? false) ? $fields : [],
            $parameters,
            ['content' => $this->layout->content(), 'layout' => $layout, 'fields' => $fields, 'admin_meta' => $layout?->adminMeta() ?? []],
        )));
    }
}
