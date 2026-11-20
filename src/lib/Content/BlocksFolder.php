<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Content;

/**
 * The folder that holds the blocks of a page, a child of that page: "<name of the page> Blocks" (the suffix is a setting).
 * Its remote id is the one of the page and "_blocks": the folder is found by it, so renaming the page changes nothing. The
 * three packages that make or find it (blocks, craft, toolbox) agree on these rules through this class.
 */
final class BlocksFolder
{
    public const REMOTE_ID_SUFFIX = '_blocks';

    /** "Home" and "Blocks" give "Home Blocks" */
    public static function name(string $pageName, string $suffix = 'Blocks'): string
    {
        $pageName = trim($pageName);

        return $pageName === '' ? $suffix : $pageName . ' ' . $suffix;
    }

    public static function remoteId(string $pageRemoteId): string
    {
        return $pageRemoteId . self::REMOTE_ID_SUFFIX;
    }

    /** Whether a remote id is one of a folder of blocks */
    public static function isRemoteId(string $remoteId): bool
    {
        return str_ends_with($remoteId, self::REMOTE_ID_SUFFIX) && strlen($remoteId) > strlen(self::REMOTE_ID_SUFFIX);
    }

    /** Whether a folder is named like a folder of blocks ("About Blocks", or just "Blocks"): for the ones made by hand */
    public static function nameMatches(string $folderName, string $suffix = 'Blocks'): bool
    {
        $folderName = trim($folderName);

        return strcasecmp($folderName, $suffix) === 0 || str_ends_with(strtolower($folderName), ' ' . strtolower($suffix));
    }
}
