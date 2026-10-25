<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface ClosableInterface
{
    /**
     * Close the resource or perform any necessary cleanup.
     */
    public function close(); // @phpstan-ignore missingType.return
}
