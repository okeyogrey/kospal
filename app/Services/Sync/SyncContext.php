<?php

namespace App\Services\Sync;

final class SyncContext
{
    private static int $depth = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function silence(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function silenced(): bool
    {
        return self::$depth > 0;
    }
}
