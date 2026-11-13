<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Contracts\IbexaBlocks\MenuProvider;
use Novactive\EzMenuManager\Service\MenuBuilder;
use Novactive\EzMenuManagerBundle\Entity\Menu;

/** The menus of the Novactive menu manager: the menu of a type for the root of the site, as the menu manager builds it. */
final readonly class NovactiveMenuProvider implements MenuProvider
{
    public function __construct(
        private EntityManagerInterface $entities,
        private MenuBuilder $builder,
    ) {
    }

    public function menu(string $type, int $rootLocationId): mixed
    {
        $menu = $this->entities->getRepository(Menu::class)->findOneBy(['type' => $type, 'rootLocationId' => $rootLocationId]);

        return $menu === null ? null : $this->builder->build($menu)->setName($menu->getName());
    }
}
