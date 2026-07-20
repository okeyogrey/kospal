<?php

namespace App\Support;

/**
 * Packaging mode helpers — domain code should prefer contracts over this.
 */
final class Deployment
{
    public const MODE_DESKTOP = 'desktop';

    public const MODE_WEB = 'web';

    public static function mode(): string
    {
        $mode = (string) config('deployment.mode', self::MODE_DESKTOP);

        return in_array($mode, [self::MODE_DESKTOP, self::MODE_WEB], true)
            ? $mode
            : self::MODE_DESKTOP;
    }

    public static function isDesktop(): bool
    {
        return self::mode() === self::MODE_DESKTOP;
    }

    public static function isWeb(): bool
    {
        return self::mode() === self::MODE_WEB;
    }
}
