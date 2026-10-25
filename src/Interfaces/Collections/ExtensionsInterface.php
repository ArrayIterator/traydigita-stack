<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

use TrayDigita\WP\Stack\Source\Interfaces\Actions\RebootableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ShutdownableInterface;

/**
 * @template T of ExtensionInterface
 */
interface ExtensionsInterface extends RebootableInterface, ShutdownableInterface
{
    /**
     * Default name for the extension collection
     */
    public const DEFAULT_NAME = 'default';

    /**
     * ExtensionsInterface constructor.
     * @param RegistryInterface $RegistryInterface The registry interface instance
     * @param string $name The name of the extension collection
     * @param bool $isRunningAsPlugin Whether the extension collection is running as a plugin
     */
    public function __construct(
        RegistryInterface $RegistryInterface,
        string $name = self::DEFAULT_NAME,
        bool $isRunningAsPlugin = false
    );

    /**
     * Check if the extension collection is running as a plugin
     *
     * @return bool
     */
    public function isRunningAsPlugin() : bool;

    /**
     * Get the name of the extension collection
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the RegistryInterface instance
     *
     * @return RegistryInterface
     */
    public function getRegistry() : RegistryInterface;

    /**
     * Check if an extension is loaded
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $extension
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return bool
     */
    public function booted(string|ExtensionInterface $extension, bool $strict = false): bool;

    /**
     * Add an extension to the collection
     * @param class-string<T>|T $extension The extension to add
     * @param bool $override If true, will override the existing extension if it exists
     * @return bool True if the extension was added, false if it already exists and override is false
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException
     *  if the extension is already booted and override is false
     */
    public function register(string|ExtensionInterface $extension, bool $override = true) : bool;

    /**
     * Remove an extension from the collection, if already loaded, ignore it
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $extension
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return ?T the removed extension, or null if not found
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException
     * if the extension is loaded and strict is true
     */
    public function remove(string|ExtensionInterface $extension, bool $strict = false): ?ExtensionInterface;

    /**
     *  Check if an extension is in the collection
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $extension
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return bool
     */
    public function has(string|ExtensionInterface $extension, bool $strict = false): bool;

    /**
     * Get an extension from the collection by name
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $name
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return T|null
     */
    public function get(string|ExtensionInterface $name, bool $strict = false): ?ExtensionInterface;

    /**
     *  Get all extensions in the collection
     *
     * @return array<(class-string<T>&lowercase-string), T>
     */
    public function getExtensions(): array;

    /**
     * Get all active extensions in the collection
     *
     * @return array<(class-string<T>&lowercase-string), T>
     */
    public function getActiveExtensions() : array;

    /**
     * Activate an extension by name
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $name
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return ?int The timestamp when the extension was activated, or null if it was not found or already activated
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException
     * if the extension is already booted and strict is true
     */
    public function activate(string|ExtensionInterface $name, bool $strict = false): ?int;

    /**
     * Deactivate an extension by name
     *
     * @param class-string<T>|(class-string<T>&lowercase-string)|T $name
     * @param bool $strict if true, will check if the extension is loaded and is an instance of the given class
     * @return ?int The timestamp when the extension was deactivated, or null if it was not found or already deactivated
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException
     * if the extension is already booted and strict is true
     */
    public function deactivate(string|ExtensionInterface $name, bool $strict = false): ?int;

    /**
     * Get the boot errors of the extension collection
     *
     * @return array<(class-string<T>&lowercase-string), \Throwable>
     *     An associative array of extension names and their corresponding error messages
     */
    public function getBootErrors() : array;

    /**
     * Get the boot time of the extension collection, this returning nanoseconds integer
     * @return TimeRecordsInterface[]|null
     */
    public function getBootTimes() : ?array;

    /**
     * Check if the extension collection is booted
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * Load the extension collection
     */
    public function boot(); // @phpstan-ignore missingType.return

    /**
     * Reboot the extension collection
     */
    public function reboot(); // @phpstan-ignore missingType.return

    /**
     * Shutdown the extension collection
     */
    public function shutdown(); // @phpstan-ignore missingType.return

    /**
     * Get the number of booted extensions
     *
     * @return int
     */
    public function bootCount(): int;

    /**
     * Check if the extension is a core extension
     *
     * @param T|class-string<T> $extension
     * @return bool
     */
    public function isCore(ExtensionInterface|string $extension): bool;
}
