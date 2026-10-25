<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface BootableInterface
{
    /**
     * Boot the object
     */
    public function boot(); // @phpstan-ignore missingType.return

    /**
     * Check if the object is booting
     *
     * @return bool
     */
    public function isBooting() : bool;

    /**
     * Check if the object is booted
     *
     * @return bool
     */
    public function isBooted() : bool;
}
