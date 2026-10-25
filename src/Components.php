<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source;

use Throwable;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;
use TrayDigita\WP\Stack\Source\Components\Auth\StatelessAuthenticator;
use TrayDigita\WP\Stack\Source\Components\Auth\Totp;
use TrayDigita\WP\Stack\Source\Components\Benchmarks\TimeRecords;
use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Components\Data\SiteOptions;
use TrayDigita\WP\Stack\Source\Components\Data\SiteTransients;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Components\Database\WordPressDatabase;
use TrayDigita\WP\Stack\Source\Components\Database\WPDB;
use TrayDigita\WP\Stack\Source\Components\Defaults\DatetimeZoneDefault;
use TrayDigita\WP\Stack\Source\Components\Extensions\Extensions;
use TrayDigita\WP\Stack\Source\Components\Factory\UserFactory;
use TrayDigita\WP\Stack\Source\Components\Logger\LogAware;
use TrayDigita\WP\Stack\Source\Components\Logger\Logger;
use TrayDigita\WP\Stack\Source\Components\Networks\ClientBrowserType;
use TrayDigita\WP\Stack\Source\Components\Networks\Cloudflare;
use TrayDigita\WP\Stack\Source\Components\Networks\Ip;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgent;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgentGenerator;
use TrayDigita\WP\Stack\Source\Components\Provider\JetpackPhoton;
use TrayDigita\WP\Stack\Source\Components\Security\Hash;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistrySingletonInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryComponent;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\AutoWire;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Reference;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Value;

/**
 * @mixin RegistryComponent
 */
final class Components implements RegistrySingletonInterface
{
    /**
     * @var Components $instance The singleton instance of the Components class
     */
    private static Components $instance;

    /**
     * @var RegistryComponent $registry The registry instance used for managing components
     */
    public readonly RegistryComponent $registry;

    /**
     * @var class-string[] $componentClasses An array of component class names to be registered in the registry
     */
    private const COMPONENT_CLASSES = [
        // Assets
        ManifestAssets::class,

        // Auth
        StatelessAuthenticator::class,
        Totp::class,

        // Benchmarks
        TimeRecords::class,

        // Data
        SiteOptions::class,
        SiteTransients::class,
        Site::class,
        WPDB::class,
        WordPressDatabase::class,
        Database::class,

        // Defaults
        DatetimeZoneDefault::class,

        // Extensions
        Extensions::class,
        // Factory
        UserFactory::class,
        // Logger
        Logger::class,
        LogAware::class,

        // Networks
        ClientBrowserType::class,
        Cloudflare::class,
        Ip::class,
        UserAgent::class,
        UserAgentGenerator::class,

        // Provider
        JetpackPhoton::class,
        // Security
        Hash::class,
    ];

    /**
     * Returns an array of component definitions.
     *
     * @return array<string, AutoWire|Value|callable|Reference> An associative array of component definitions
     */
    private function definitions() : array
    {
        $ref = [];
        foreach (self::COMPONENT_CLASSES as $class) {
            $ref[$class] = AutoWire::create($class);
        }
        $ref[self::class] = Value::create($this);
        $ref[\wpdb::class] = static function () {
            global $wpdb;
            return $wpdb;
        };
        $ref['WPDB'] = Reference::create(\wpdb::class);
        return $ref;
    }

    /**
     * Private constructor to prevent direct instantiation of the Components class.
     * Initializes the registry and sets the singleton instance.
     */
    private function __construct()
    {
        self::$instance = $this;
        $this->registry = new RegistryComponent(
            $this->definitions(),
            true,
            true,
            true
        );
    }

    /**
     * Retrieves the singleton instance of the Components class.
     *
     * @return Components
     */
    public static function getInstance(): Components
    {
        return self::$instance ??= new self();
    }

    /**
     * Retrieves the registry component instance.
     *
     * @return RegistryComponent
     */
    public static function registry() : RegistryComponent
    {
        return self::getInstance()->registry;
    }

    /**
     * Creates an instance of the specified class using the registry and returns it.
     * If the class cannot be instantiated, it returns null.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param mixed ...$args
     * @return T|null
     */
    public static function make(string $className, ...$args): ?object
    {
        try {
            return self::registry()->make($className, ...$args);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieves an instance of the specified class from the registry.
     * If the class is not found, it returns null.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public static function get(string $id): mixed
    {
        try {
            return self::registry()->get($id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Checks if the specified ID exists in the registry.
     *
     * @param string $id
     * @return bool
     */
    public static function has(string $id): bool
    {
        return self::registry()->has($id);
    }

    /**
     * Sets a value in the registry with the specified ID.
     * If an error occurs during the operation, it returns the exception; otherwise, it returns null.
     *
     * @param string $id The ID to set in the registry
     * @param mixed $value The value to set in the registry
     * @param bool $lock Whether to lock the value in the registry
     * @return Throwable|null null if successful, or the exception if an error occurs
     */
    public static function set(string $id, mixed $value, bool $lock = false, bool $unlockable = true): ?Throwable
    {
        try {
            self::registry()->set($id, $value, $lock, $unlockable);
            return null;
        } catch (Throwable $e) {
            return $e;
        }
    }

    /**
     * Magic method to handle method calls on the Components class.
     * It forwards the call to the registry instance.
     *
     * @param string $name The name of the method being called
     * @param array<array-key, mixed> $arguments The arguments passed to the method
     * @return mixed The result of the method call on the registry
     */
    public function __call(string $name, array $arguments)
    {
        return $this->registry->$name(...$arguments);
    }

    /**
     * Magic method to handle static method calls on the Components class.
     * It forwards the call to the registry instance.
     *
     * @param string $name The name of the method being called
     * @param array<array-key, mixed> $arguments The arguments passed to the method
     * @return mixed The result of the method call on the registry
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::getInstance()->registry->$name(...$arguments);
    }

    /**
     * Magic method to handle property access on the Components class.
     * It forwards the property access to the registry instance.
     *
     * @param string $name The name of the property being accessed
     * @return mixed The value of the property from the registry
     */
    public function __get(string $name)
    {
        return $this->registry->$name;
    }

    /**
     * Magic method to handle property setting on the Components class.
     * It forwards the property setting to the registry instance.
     *
     * @param string $name The name of the property being set
     * @param mixed $value The value to set for the property
     */
    public function __set(string $name, mixed $value): void
    {
        $this->registry->$name = $value;
    }
}
