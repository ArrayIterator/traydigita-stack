<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Utils;

use ReflectionClass;
use ReflectionObject;
use Stringable;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Attributes;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use TrayDigita\WP\Stack\Source\Libs\Helpers\OutputBuffer;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function is_object;
use function is_string;
use function method_exists;

/**
 * Filter util repository
 */
class Filter
{
    // ATTRIBUTES
    /**
     * Filter class names
     *
     * @param mixed ...$classes
     * @return array<string>
     * @see Attributes::filterClass()
     */
    public function classNames(mixed ...$classes): array
    {
        return Attributes::filterClass(...$classes);
    }

    /**
     * Sanitize an attribute key
     *
     * @param mixed $key The attribute key to sanitize.
     * @return string|null The sanitized attribute key, or null if the key is invalid.
     */
    public function attributeKey(mixed $key): ?string
    {
        return Attributes::sanitizeKeyAttribute($key);
    }

    /**
     * Builds a string of HTML attributes from an associative array.
     *
     * @param array<string, mixed> $attributes The associative array of attributes.
     * @return string The string of HTML attributes.
     */
    public function attributes(array $attributes): string
    {
        return Attributes::buildAttributes($attributes);
    }

    // FILTER

    /**
     * Remove magic quotes from the input data.
     *
     * @param mixed $data The input data to process.
     * @return mixed The input data with magic quotes removed.
     */
    public function magicQuotes(mixed $data): mixed
    {
        return DataFilter::removeMagicQuotes($data);
    }

    /**
     * Filter and sanitize a hexadecimal color value.
     *
     * @param mixed $hex The hexadecimal color value to filter and sanitize.
     * @return string|null The sanitized hexadecimal color value, or null if the input is invalid.
     */
    public function hex(mixed $hex): ?string
    {
        return DataFilter::hex($hex);
    }

    /**
     * Cast a value to a string.
     *
     * @param mixed $data The value to cast to a string.
     * @param string|Stringable $default The default value to return if the input is not a string or Stringable.
     * @return string The casted string value, or the default value if the input is not a string or Stringable.
     */
    public function toString(mixed $data, string|Stringable $default = ''): string
    {
        return DataFilter::castString($data, $default);
    }

    /**
     * Filter and sanitize a color value.
     * @param mixed $color The color value to filter and sanitize.
     * @return string|null
     */
    public function color(mixed $color): ?string
    {
        return DataFilter::colorHex($color);
    }

    /**
     * Filter and sanitize a color value to RGBA format.
     * @param mixed $color The color value to filter and sanitize.
     * @return string|null
     */
    public function rgba(mixed $color): ?string
    {
        return DataFilter::rgbaColor($color);
    }

    /**
     * Filter and sanitize a color value to RGB format.
     * @param mixed $color The color value to filter and sanitize.
     * @return string|null
     */
    public function rgb(mixed $color): ?string
    {
        return DataFilter::rgbColor($color);
    }

    /**
     * Filter and sanitize a URL to a relative URL.
     * @param mixed $url The URL to filter and sanitize.
     * @return string|null
     */
    public function relativeUrl(mixed $url): ?string
    {
        if ($url instanceof Stringable || is_object($url) && method_exists($url, '__toString')) {
            $url = (string)$url;
        }
        if (!is_string($url)) {
            return null;
        }
        return DataFilter::relativePathURL($url);
    }

    /**
     * Add trailing slash to a path
     *
     * @param string $path The path to add the trailing slash to.
     * @return string The path with the trailing slash added.
     */
    public function trailingslash(string $path): string
    {
        return DataFilter::trailingslash($path);
    }

    /**
     * Remove trailing slash from a path
     *
     * @param string $path The path to remove the trailing slash from.
     * @return string The path without the trailing slash.
     */
    public function untrailingslash(string $path): string
    {
        return DataFilter::removeTrailingSlash($path);
    }

    /**
     * Normalize a path by removing redundant slashes and resolving relative segments.
     *
     * @param mixed $path The path to normalize.
     * @param bool $unSlashEnd Whether to remove the trailing slash from the normalized path (default: false).
     * @return string The normalized path.
     */
    public function normalizePath(mixed $path, bool $unSlashEnd = false): string
    {
        return DataFilter::normalizePath($path, $unSlashEnd);
    }

    /**
     * Get the basename of a path.
     *
     * @param mixed $path The path to get the basename from.
     * @return string The basename of the path.
     */
    public function basename(mixed $path): string
    {
        return DataFilter::basename($path);
    }

    /**
     * Clean all output buffer and return if headers are sent
     *
     * @return bool true headers are not sent, false headers are sent
     * if this method return true, the content can be sent to the client,
     * otherwise the content is already sent and cannot be modified
     */
    public static function clearBuffer(bool $createNewBuffer = false): bool
    {
        return OutputBuffer::clear($createNewBuffer);
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
    public function reflectionClass(mixed $class, bool $force = false): ?ReflectionClass
    {
        /**
         * @var ($class is T ? ReflectionClass<T> : null) $obj
         */
        $obj = Reflections::class($class, $force);
        return $obj instanceof ReflectionClass ? $obj : null;
    }

    /**
     * @template T of object
     * Creates a ReflectionObject instance for the given object.
     *
     * @param T $object
     * @return ReflectionObject<T>
     */
    public function reflectionObject(object $object): ReflectionObject
    {
        return Reflections::object($object);
    }

    /**
     * Get the lowercase class name of an object or class name.
     * @template T of object
     * @param mixed|T $class An object or a class name.
     * @return ($class is T ? (class-string<T>&lowercase-string) : null)
     * @phpstan-return ($class is T ? (class-string<T>&lowercase-string) : null)
     */
    public static function lowercaseClassName(mixed $class): ?string
    {
        /** @var ?(class-string<T>&lowercase-string) $result */
        $result = Reflections::lowercase($class);
        return $result;
    }
}
