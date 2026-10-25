<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use ReflectionClass;
use ReflectionObject;
use Throwable;
use TrayDigita\WP\Stack\Source\Libs\Collections\Iter;
use function get_class;
use function is_object;
use function is_string;
use function ltrim;
use function strtolower;

/**
 * Reflection helper class to manage ReflectionClass and ReflectionObject instances with caching.
 * This class provides methods to create and cache ReflectionClass and
 * ReflectionObject instances for given objects or class names.
 * It also provides a method to get the lowercase class name of an object or class name.
 * The caching mechanism helps to reduce the overhead of creating ReflectionClass
 * instances for frequently used classes.
 * The class also maintains a cache of unresolved classes to prevent repeated attempts to reflect
 * on non-existent classes, which can help improve performance in scenarios where many classes may not be available.
 */
final class Reflections
{
    /**
     * @var int Maximum number of unresolved reflection classes to cache.
     * This is to prevent memory bloat in case of many unresolved classes.
     */
    private const MAX_CACHED_ITEMS = 1024;

    /**
     * @var int Maximum number of unresolved reflection classes to cache before removing the oldest cached item.
     * This is to prevent memory bloat in case of many unresolved classes.
     */
    private const CACHED_ITEMS_REDUCE = self::MAX_CACHED_ITEMS - 100;

    /**
     * @var array<class-string<object>, ReflectionClass<object>>
     */
    private static array $reflectionClassesCache = [];

    /**
     * @var array<(class-string<object>&lowercase-string)|string, bool>
     */
    private static array $unresolvedClassesCache = [];

    /**
     * @template T of object
     * Creates a ReflectionObject instance for the given object.
     *
     * @param T $object
     * @return ReflectionObject<T>
     */
    public static function object(object $object): ReflectionObject
    {
        return new ReflectionObject($object);
    }

    /**
     * Get the lowercase class name of an object or class name.
     * @template T of object
     * @param mixed|T $class An object or a class name.
     * @return ($class is T ? (class-string<T>&lowercase-string) : null)
     * @phpstan-return ($class is T ? (class-string<T>&lowercase-string) : null)
     */
    public static function lowercase(mixed $class): ?string
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $result = null;
        if (is_string($class)) {
            $result = strtolower(ltrim($class, '\\'));
        }
        /**
         * @var ?(class-string<T>&lowercase-string) $result
         */
        return $result;
    }

    /**
     * Creates a ReflectionClass instance for the given object and caches it.
     *
     * @template T of object
     * @param T $object
     * @return ReflectionClass<T>
     */
    public static function objectClass(object $object, bool $force = false): ReflectionClass
    {
        /**
         * @var (class-string<T>&lowercase-string) $lower
         */
        $lower = self::lowercase($object);
        unset(self::$unresolvedClassesCache[$lower]);
        if (!$force && isset(self::$reflectionClassesCache[$lower])) {
            /**
             * @var ReflectionClass<T> $result
             */
            $result = self::$reflectionClassesCache[$lower];
            return $result;
        }

        self::$reflectionClassesCache = Iter::sliceIf(
            self::$reflectionClassesCache,
            self::MAX_CACHED_ITEMS,
            self::CACHED_ITEMS_REDUCE
        );

        /**
         * @var ReflectionClass<T> $result
         */
        $result = self::$reflectionClassesCache[$lower] = new ReflectionClass(get_class($object));
        return $result;
    }

    /**
     * Get ReflectionClass for a given class name or object.
     *
     * @template T of object
     * @param class-string<T>|T|mixed $class
     * @param bool $force
     * @return ($class is T ? ReflectionClass<T> : null)
     * @phpstan-return ($class is T ? ReflectionClass<T> : null)
     */
    public static function class(mixed $class, bool $force = false): ?ReflectionClass
    {
        if (is_object($class)) {
            /**
             * @var ReflectionClass<T> $result
             */
            $result = self::objectClass($class, $force);
            return $result;
        }
        $lower = self::lowercase($class);
        if (!$lower) {
            return null;
        }
        if (isset(self::$unresolvedClassesCache[$lower])) {
            if (!$force) {
                return null;
            }
        } elseif (!Is::validClassName($class)) {
            return null;
        }

        $class = ltrim($class, '\\');
        try {
            /**
             * @var class-string<T> $class
             */
            self::$reflectionClassesCache[$lower] ??= new ReflectionClass($class);
            unset(self::$unresolvedClassesCache[$lower]);
            /**
             * @var ReflectionClass<T> $result
             */
            $result = self::$reflectionClassesCache[$lower];
        } catch (Throwable) { // @phpstan-ignore-line catch.neverThrown
            if (!isset(self::$unresolvedClassesCache[$lower])) {
                self::$unresolvedClassesCache = Iter::sliceIf(
                    self::$unresolvedClassesCache,
                    self::MAX_CACHED_ITEMS,
                    self::CACHED_ITEMS_REDUCE
                );
                self::$unresolvedClassesCache[$lower] = true;
            }
        }
        return $result ?? null;
    }
}
