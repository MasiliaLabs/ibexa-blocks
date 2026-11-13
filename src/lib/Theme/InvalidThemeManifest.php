<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/** A theme.yaml that says something the theme mechanism cannot use: found when it is read, with what to fix. */
final class InvalidThemeManifest extends \InvalidArgumentException
{
}
