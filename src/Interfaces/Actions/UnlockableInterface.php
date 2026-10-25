<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface UnlockableInterface extends LockableInterface
{
    /**
     * Unlock the object to allow modifications
     */
    public function unlock(); // @phpstan-ignore missingType.return
}
