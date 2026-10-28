<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Utils;

use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;

class Validator
{
    /**
     * Check if the given data is true.
     *
     * @param mixed $data The data to check.
     * @param bool $default The default value to return if the data is not a boolean.
     * @param bool $use_null_default Whether to use null as the default value.
     * @return bool True if the data is true, false otherwise.
     */
    public function true(mixed $data, bool $default = false, bool $use_null_default = false): bool
    {
        return Is::true($data, $default, $use_null_default);
    }

    /**
     * Check if the given data is false.
     *
     * @param mixed $data The data to check.
     * @param bool $default The default value to return if the data is not a boolean.
     * @return bool True if the data is false, false otherwise.
     */
    public function false(mixed $data, bool $default = false): bool
    {
        return Is::false($data, $default);
    }

    /**
     * Check if the given data is a digit.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a digit, false otherwise.
     * @phpstan-assert string $data
     */
    public function hex(mixed $data): bool
    {
        return Is::hex($data);
    }

    /**
     * Check if the given variable is a closure.
     *
     * @param mixed $arg variable to check.
     * @return ($arg is \Closure ? true : false) True if the variable is a closure, false otherwise.
     * @phpstan-assert-if-true \Closure $arg
     * @phpstan-return ($arg is \Closure ? true : false)
     */
    public function closure(mixed $arg): bool
    {
        return Is::closure($arg);
    }

    /**
     * Check if the given data is stringable.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is stringable, false otherwise.
     * @phpstan-assert scalar|null|\Stringable $data
     */
    public function stringable(mixed $data): bool
    {
        return Is::stringable($data);
    }

    /**
     * Check if the given string is a valid class name.
     *
     * @param string $className The class name to check.
     * @return bool True if the class name is valid, false otherwise.
     * @phpstan-return ($className is class-string ? true : false)
     */
    public function className(string $className): bool
    {
        return Is::validClassName($className);
    }

    /**
     * Check if the current environment is CLI.
     *
     * @return bool True if the current environment is CLI, false otherwise.
     */
    public function cli() : bool
    {
        return Is::cli();
    }

    /**
     * Check if the given data is a valid Base64 encoded string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base64 encoded string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     */
    public function base64(mixed $data): bool
    {
        return Is::isBase64($data);
    }

    /**
     * Check if the given data is a valid Base32 encoded string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base32 encoded string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     */
    public function base32(mixed $data): bool
    {
        return Is::isBase32($data);
    }

    /**
     * Check if the given data is a valid Base64 encoded image string.
     *
     * @param mixed $data The data to check.
     * @return bool True if the data is a valid Base64 encoded image string, false otherwise.
     * @phpstan-assert string $data
     * @phpstan-return ($data is string ? true : false)
     */
    public function imageBase64(mixed $data): bool
    {
        return Is::isImageBase64($data);
    }

    /**
     * Check if the current environment is Windows.
     *
     * @return bool True if the current environment is Windows, false otherwise.
     */
    public function windows() : bool
    {
        return Is::windows();
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
    public function stream(mixed $path): bool
    {
        return Is::stream($path);
    }

    /**
     * Check if the given path is an absolute path.
     *
     * @param mixed $path The path to check.
     * @return bool True if the path is an absolute path, false otherwise.
     * @phpstan-assert string $path
     */
    public function absolutePath(mixed $path): bool
    {
        return Is::absolutePath($path);
    }

    /**
     * Check if the given string is a valid UUID.
     *
     * @param mixed $uuid The UUID to check.
     * @return bool True if the UUID is valid, false otherwise.
     * @phpstan-assert string $uuid
     * @phpstan-return ($uuid is string ? true : false)
     */
    public function uuid(mixed $uuid): bool
    {
        if (!is_string($uuid)) {
            return false;
        }
        // check if valid uuid 1 - 8 (using simple regex)
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }
}
