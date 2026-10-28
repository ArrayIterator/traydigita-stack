<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use Closure;
use Generator;
use Stringable;
use Throwable;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\BootableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\RebootableInterface;
use function get_resource_type;
use function in_array;
use function is_bool;
use function is_float;
use function is_integer;
use function is_null;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_match;
use function realpath;
use function stream_get_wrappers;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;

final class Is
{
    public const TRUE = [
        '1' => true,
        'yes' => true,
        'on' => true,
        'true' => true,
        'y' => true,
        'enable' => true,
        'enabled' => true
    ];

    public const FALSE = [
        '' => false,
        '0' => false,
        '0.0' => false,
        'off' => false,
        'none' => false,
        'not' => false,
        'no' => false,
        'n' => false,
        'false' => false,
        'disable' => false,
        'disabled' => false
    ];

    /**
     * Check if an option value is true.
     *
     * @param mixed $data
     * @param bool $default
     * @param bool $use_null_default
     * @return bool
     */
    public static function true(mixed $data, bool $default = false, bool $use_null_default = false): bool
    {
        if (is_bool($data)) {
            return $data;
        }
        if ($use_null_default && $data === null) { // exception to null
            return $default;
        }
        if ($data === 1 || $data === 1.0) {
            return true;
        }
        if (!$data) {
            return false;
        }
        if (is_float($data)) {
            return $data < 2 && $data >= 1;
        }
        if (is_string($data)) {
            if (isset(self::TRUE[$data])) {
                return true;
            }
            if (isset(self::FALSE[$data])) {
                return false;
            }
            $data = strtolower(trim($data));
            return self::TRUE[$data] ?? (self::FALSE[$data] ?? $default);
        }
        return $default;
    }

    /**
     * Check if an option value is false.
     * @param mixed $data
     * @param bool $default
     * @return bool
     */
    public static function false(mixed $data, bool $default = false): bool
    {
        if (is_bool($data)) {
            return $data;
        }
        if (is_string($data)) {
            $data = strtolower(trim($data));
            return in_array($data, ['0', 'no', 'off', 'none', 'false', 'disable', 'disabled'])
                || ((
                    !in_array(
                        $data,
                        ['1', 'yes', 'on', 'true', 'y', 'enable', 'enabled']
                    ) && $default));
        }
        if (is_integer($data)) {
            return $data === 0;
        }
        if (is_float($data)) {
            return $data < 1 && $data >= 0;
        }
        return $default;
    }

    /**
     * Check if the data is a valid hex color.
     *
     * @param mixed $data
     * @return bool
     * @phpstan-assert string $data
     */
    public static function hex(mixed $data): bool
    {
        return DataFilter::hex($data) !== null;
    }

    /**
     * Check if the given variable is a closure.
     *
     * @param mixed $arg The variable to check.
     * @return ($arg is Closure ? true : false) True if the variable is a closure, false otherwise.
     * @phpstan-assert-if-true Closure $arg
     * @phpstan-return ($arg is Closure ? true : false)
     */
    public static function closure(mixed $arg): bool
    {
        return $arg instanceof Closure;
    }

    /**
     * Check if the given variable is string convertable. by casting to string or has __toString method.
     *
     * @param mixed $data The variable to check.
     * @return bool True if the variable is string convertable, false otherwise.
     * @phpstan-assert scalar|null|Stringable $data
     */
    public static function stringable(mixed $data): bool
    {
        return is_scalar($data)
            || is_null($data) // null also can be converted to string
            || (is_object($data) && ($data instanceof Stringable || method_exists($data, '__toString')));
    }

    /**
     * Check if the given string is a valid class name.
     *
     * @param string $className The class name to check.
     * @return bool True if the class name is valid, false otherwise.
     * @phpstan-return ($className is class-string ? true : false)
     */
    public static function validClassName(string $className): bool
    {
        return (bool)preg_match(
            '~^\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$~',
            $className
        );
    }

    /**
     * Check if the current PHP environment is running in CLI mode.
     *
     * @return bool True if running in CLI mode, false otherwise.
     */
    public static function cli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * Check if the given data is a valid Base64 encoded string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base64 encoded string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     * @uses \is_string() to check if the data is a string.
     * @uses \preg_match() to validate the Base64 format.
     */
    public static function isBase64(mixed $data): bool
    {
        return is_string($data) && preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data);
    }

    /**
     * Check if the given data is a valid Base32 encoded string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base32 encoded string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     * @uses \is_string() to check if the data is a string.
     * @uses \preg_match() to validate the Base32 format.
     */
    public static function isBase32(mixed $data): bool
    {
        return Base32::valid($data);
    }

    /**
     * Check if the given data is a valid Base64 encoded image string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base64 encoded image string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     * @uses \is_string() to check if the data is a string.
     * @uses \preg_match() to validate the Base64 image format.
     */
    public static function isImageBase64(mixed $data): bool
    {
        return is_string($data) && preg_match('/^data:image\/[a-zA-Z]+;base64,/', $data);
    }

    /**
     * Check if the given data is bootable,
     *  meaning it can be instantiated and has a public boot method with no required parameters.
     *
     * @template T of BootableInterface|object
     * @param T|class-string<T> $data The data to check.
     * @return bool True if the data is bootable, false otherwise.
     */
    public static function isBootable(mixed $data): bool
    {
        if ($data instanceof BootableInterface) {
            return true;
        }
        if (is_string($data)) {
            $reflection = Reflections::class($data);
            if (!$reflection) {
                return false;
            }
        } else {
            $reflection = Reflections::object($data);
        }
        if (!$reflection->hasMethod('boot')) {
            return false;
        }
        $method = $reflection->getMethod('boot');
        if (!$method->isPublic()) {
            return false;
        }
        return $method->getNumberOfRequiredParameters() === 0;
    }

    /**
     * Check if the given data is rebootable,
     *  meaning it can be instantiated and has a public reboot method with no required parameters.
     *
     * @template T of RebootableInterface|object
     * @param T|class-string<T> $data The data to check.
     * @return bool True if the data is rebootable, false otherwise.
     */
    public static function isRebootable(mixed $data): bool
    {
        if ($data instanceof RebootableInterface) {
            return true;
        }
        if (is_string($data)) {
            $reflection = Reflections::class($data);
            if (!$reflection) {
                return false;
            }
        } else {
            $reflection = Reflections::object($data);
        }
        if (!$reflection->hasMethod('reboot')) {
            return false;
        }
        $method = $reflection->getMethod('reboot');
        if (!$method->isPublic()) {
            return false;
        }
        return $method->getNumberOfRequiredParameters() === 0;
    }

    /**
     * Check if the current operating system is Windows.
     *
     * @return bool True if the operating system is Windows, false otherwise.
     */
    public static function windows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Check if the given path is a stream.
     *
     * @param mixed $path The path to check.
     * @return bool True if the path is a stream, false otherwise.
     * @phpstan-assert string|resource $path
     * @phpstan-return ($path is string|resource ? true : false)
     * @uses \is_resource() to check if the path is a resource.
     * @uses \get_resource_type() to check if the resource is a stream.
     * @uses \is_string() to check if the path is a string.
     */
    public static function stream(mixed $path): bool
    {
        if (is_resource($path)) {
            return get_resource_type($path) === 'stream';
        }
        if (!is_string($path)) {
            return false;
        }
        $scheme_separator = strpos($path, '://');
        if (!$scheme_separator) {
            return false;
        }
        $scheme = substr($path, 0, $scheme_separator);
        $wrappers = stream_get_wrappers();
        return in_array($scheme, $wrappers)
            || ($scheme !== ($lower = strtolower($scheme)) && in_array($lower, $wrappers));
    }

    /**
     * Check if the given path is an absolute path.
     *
     * @param mixed $path The path to check.
     * @return bool True if the path is an absolute path, false otherwise.
     * @phpstan-assert string $path
     */
    public static function absolutePath(mixed $path): bool
    {
        if (!is_string($path)) {
            return false;
        }
        /**
         * This is definitive if true but fails if $path does not exist or contains
         * a symbolic link.
         */
        if (realpath($path) === $path) {
            return true;
        }

        if (strlen($path) === 0 || '.' === $path[0]) {
            return false;
        }

        if (self::windows()) {
            // Windows allows absolute paths like this.
            return (bool)preg_match('#^[a-zA-Z]:[/\\\]{2}~#', $path);
        }
        return $path[0] === '/';
    }

    /**
     * Check if the given generator has been run (i.e., if it has been iterated at least once).
     *
     * @param Generator $generator The generator to check.
     * @return bool True if the generator has been run, false otherwise.
     */
    public static function isGeneratorRun(Generator $generator): bool
    {
        try {
            $generator->rewind();
            return false;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Check if the given generator has been closed (i.e., if it has finished iterating).
     *
     * @param Generator $generator The generator to check.
     * @return bool True if the generator has been closed, false otherwise.
     */
    public static function isGeneratorClosed(Generator $generator): bool
    {
        return $generator->valid() === false;
    }
}
