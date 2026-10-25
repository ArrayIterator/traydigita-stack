<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

use Psr\Container\ContainerInterface;

interface RegistryInterface extends ContainerInterface
{
    /**
     * The maximum depth for cyclic redundancy checks
     */
    public const CYCLIC_DEPTH = 64;

    /**
     * The minimum depth for cyclic redundancy checks
     */
    public const MIN_CYCLIC_DEPTH = 1;

    /**
     * The maximum depth for cyclic redundancy checks
     */
    public const MAX_CYCLIC_DEPTH = 256;

    /**
     * Check if registry is autowire
     *
     * @return bool
     */
    public function isAutoWire() : bool;

    /**
     * Set the cyclic depth
     *
     * @param int $cyclicDepth The cyclic depth to set
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\IllegalOperationExceptionInterface
     */
    public function setCyclicDepth(int $cyclicDepth); // @phpstan-ignore missingType.return

    /**
     * Get the current cyclic depth
     *
     * @return int<self::MIN_CYCLIC_DEPTH, self::MAX_CYCLIC_DEPTH> The current cyclic depth
     */
    public function getCyclicDepth(): int;

    /**
     * Check if the registry is configuring a specific key
     *
     * @param string $name The key to check
     * @return int<0, max> The number of times the key is being configured, 0 if not configuring
     */
    public function configureCount(string $name): int;

    /**
     * Set the value in the registry
     *
     * @param string $id The key to set
     * @param mixed $value The value to set
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryLockedExceptionInterface
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\IllegalOperationExceptionInterface
     */
    public function set(string $id, mixed $value); // @phpstan-ignore missingType.return

    /**
     * Get the value from the registry
     * @return mixed The value associated with the key
     *
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryNotFoundExceptionInterface
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\IllegalOperationExceptionInterface
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface
     */

    public function get(string $id): mixed;

    /**
     * Get raw value from the registry without any processing,
     * or value that already processed and stored in the registry.
     * This method is useful for retrieving values that may have been modified or processed by the registry,
     * allowing you to access the original value as it was set.
     *
     * @param string $id
     * @return mixed
     *
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryNotFoundExceptionInterface
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface
     */
    public function getRaw(string $id) : mixed;

    /**
     * Remove the key from the registry
     *
     * @param string $id The key to remove
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\LockedExceptionInterface
     * @throws \TrayDigita\WP\Stack\Source\Interfaces\Exceptions\IllegalOperationExceptionInterface
     */
    public function remove(string $id); // @phpstan-ignore missingType.return

    /**
     * Check if the key exists
     *
     * @param string $id The key to check
     * @return bool True if the key exists, false otherwise
     */
    public function has(string $id): bool;

    /**
     * Lock the key
     *
     * @param string $id The key to lock
     * @param bool $unlockable Whether the key can be unlocked later
     */
    public function lock(string $id, bool $unlockable = true); // @phpstan-ignore missingType.return

    /**
     * Unlock the key
     *
     * @param string $id The key to unlock
     */
    public function unlock(string $id); // @phpstan-ignore missingType.return

    /**
     * Check if the key is locked
     *
     * @param string $id The key to check
     * @return bool True if the key is locked, false otherwise
     */
    public function locked(string $id): bool;

    /**
     * Check if the key is unlockable
     *
     * @param string $id The key to check
     * @return bool True if the key is unlockable, false otherwise
     */
    public function unlockable(string $id): bool;
}
