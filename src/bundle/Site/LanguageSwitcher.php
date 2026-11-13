<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Value\Link;

/**
 * The links that show the page in the other languages it has: one per language of the content that a siteaccess of the same site
 * (the translation siteaccesses of the current one) serves, each going to that siteaccess.
 */
final readonly class LanguageSwitcher
{
    public function __construct(
        private ConfigResolverInterface $config,
        private LinkProvider $links,
    ) {
    }

    /**
     * @return list<Link> Named and labelled by the language code
     */
    public function forLocation(Location $location): array
    {
        $siteaccessByLanguage = [];
        foreach ((array) $this->config->getParameter('translation_siteaccesses') as $siteaccess) {
            $languages = (array) $this->config->getParameter('languages', null, $siteaccess);
            if ($languages !== []) {
                $siteaccessByLanguage[$languages[0]] ??= $siteaccess;
            }
        }

        $switch = [];
        foreach ($location->getContent()->getVersionInfo()->languageCodes as $language) {
            if (isset($siteaccessByLanguage[$language])) {
                $switch[] = new Link($this->links->urlForLocation($location->getId(), $siteaccessByLanguage[$language]), $language, ['language' => $language]);
            }
        }

        return $switch;
    }
}
