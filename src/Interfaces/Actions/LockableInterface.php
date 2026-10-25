<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface LockableInterface
{
    /**
     * Check if the object is locked
     *
     * @return bool
     */
    public function isLocked(): bool;

    /**
     * Lock the object to prevent further modifications
     */
    public function lock(); // @phpstan-ignore missingType.return
}
