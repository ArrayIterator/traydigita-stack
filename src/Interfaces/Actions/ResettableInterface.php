<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface ResettableInterface
{
    /**
     * Reset the resource or perform any necessary cleanup.
     */
    public function reset(); // @phpstan-ignore missingType.return
}
