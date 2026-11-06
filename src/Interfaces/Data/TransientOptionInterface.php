<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Data;

interface TransientOptionInterface extends OptionInterface
{
    /**
     * Set the value of the option with an optional expiration time
     *
     * @param string $name The name of the option
     * @param mixed $value The value to set for the option
     * @param int $expired The expiration time in seconds (default is 0, meaning no expiration)
     */
    public function set(string $name, mixed $value, int $expired = 0): void;
}
