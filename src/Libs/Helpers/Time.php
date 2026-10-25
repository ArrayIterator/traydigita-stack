<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function hrtime;
use function intdiv;
use function microtime;

/**
 * High precision time utility class
 * Use boot time to get the time since the application booted and more precise time than microtime
 */
final class Time
{
    /**
     * @var int $bootTimeNano The boot time in nanoseconds
     */
    private static int $bootTimeNano;

    /**
     * @var int $time The time in nanoseconds when the object was created
     */
    public readonly int $time;

    /**
     * Time constructor.
     */
    public function __construct()
    {
        $this->time = self::nano();
    }

    /**
     * Get the elapsed time since the object was created in nanoseconds
     *
     * @return int time in nanoseconds since the object was created
     */
    public function elapsed(): int
    {
        return self::nano() - $this->time;
    }

    /**
     * Get the boot time in nanoseconds
     *
     * @return int
     */
    public static function boot(): int
    {
        return self::$bootTimeNano ??= (int)(microtime(true) * 1_000_000_000) - hrtime(true);
    }

    /**
     * Get the current second time
     *
     * @return int
     */
    public static function second(): int
    {
        return intdiv(self::nano(), 1_000_000_000);
    }

    /**
     * Get the current millisecond time
     *
     * @return int
     */
    public static function milli(): int
    {
        return intdiv(self::nano(), 1_000_000);
    }

    /**
     * Get the current micro time
     *
     * @return int
     */
    public static function micro(): int
    {
        return intdiv(self::nano(), 1_000);
    }

    /**
     * Get the current nano time
     *
     * @return int
     */
    public static function nano(): int
    {
        return self::boot() + hrtime(true);
    }
}
