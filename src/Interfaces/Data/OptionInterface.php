<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Data;

interface OptionInterface
{
    public const PREFIX = 'traydigita_wp_';

    public const TYPE_SITE_OPTION = 'site_option';

    public const TYPE_OPTION = 'option';

    public const TYPE_TRANSIENT = 'transient';

    public const TYPE_SITE_TRANSIENT = 'site_transient';

    public const MIN_EXPIRATION = 300; // 5 minutes

    public const MAX_EXPIRATION = 2592000; // a month

    public const DEFAULT_EXPIRATION = 604800; // 7 days

    /**
     * Set the expiration time for the option
     *
     * @param int $expiration
     */
    public function setExpiration(int $expiration); // @phpstan-ignore-line

    /**
     * Get the name of the option
     *
     * @return string
     */
    public function getOptionName(): string;

    /**
     * Get the type of the option
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get the expiration time for the option
     *
     * @return int
     */
    public function getExpiration(): int;

    /**
     * Refresh the option value from the database
     *
     * @return array<string, mixed>
     */
    public function refresh() : array;

    /**
     * Get all options as an associative array
     *
     * @return array<string, mixed>
     */
    public function all() : array;

    /**
     * Get the value of an option
     *
     * @param string $name
     * @param mixed $default
     */
    public function get(string $name, mixed $default = null); // @phpstan-ignore-line

    /**
     * Set the value of an option
     *
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, mixed $value); // @phpstan-ignore-line

    /**
     * Check if an option exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Delete an option
     *
     * @param string $name
     */
    public function delete(string $name); // @phpstan-ignore-line
}
