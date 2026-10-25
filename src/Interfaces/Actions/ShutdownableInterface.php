<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface ShutdownableInterface extends BootableInterface
{
    /**
     * Shutdown the object
     */
    public function shutdown(); // @phpstan-ignore missingType.return
}
