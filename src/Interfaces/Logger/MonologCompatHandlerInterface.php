<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Logger;

use Monolog\Handler\HandlerInterface;

interface MonologCompatHandlerInterface extends LogHandlerInterface
{
    /**
     * Convert the current handler to a Monolog handler.
     *
     * @return HandlerInterface The Monolog handler.
     */
    public function toMonologHandler() : HandlerInterface;
}
