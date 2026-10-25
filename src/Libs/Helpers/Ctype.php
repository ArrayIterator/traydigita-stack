<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function function_exists;
use function is_numeric;
use function is_string;
use function preg_match;

/**
 * A utility class for checking character types in strings.
 * The backward compatibility is kept for PHP 8.0.
 */
final class Ctype
{
    /**
     * Check if the value is a digit (contains only digits)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is a digit, false otherwise
     * @uses \ctype_digit() if available, otherwise uses a regular expression
     */
    public static function digit(mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        $value = (string) $value;
        if (function_exists('ctype_digit')) {
            return \ctype_digit($value);
        }
        return preg_match('/^\d+$/', $value) === 1;
    }

    /**
     * Check if the value is alphabetic (contains only letters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is alphabetic, false otherwise
     * @uses \ctype_alpha() if available, otherwise uses a regular expression
     */
    public static function alpha(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_alpha')) {
            return \ctype_alpha($value);
        }
        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    /**
     * Check if the value is a hexadecimal digit (contains only 0-9, a-f, A-F)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is a hexadecimal digit, false otherwise
     * @uses \ctype_xdigit() if available, otherwise uses a regular expression
     */
    public static function xdigit(mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        $value = (string) $value;
        if (function_exists('ctype_xdigit')) {
            return \ctype_xdigit($value);
        }
        return preg_match('/^[a-fA-F0-9]+$/', $value) === 1;
    }

    /**
     * Check if the value is alphanumeric (contains only letters and digits)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is alphanumeric, false otherwise
     * @uses \ctype_alnum() if available, otherwise uses a regular expression
     */
    public static function alnum(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_alnum')) {
            return \ctype_alnum($value);
        }
        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    /**
     * Check if the value is lowercase (contains only lowercase letters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is lowercase, false otherwise
     * @uses \ctype_lower() if available, otherwise uses a regular expression
     */
    public static function lower(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_lower')) {
            return \ctype_lower($value);
        }
        return preg_match('/^[a-z]+$/', $value) === 1;
    }

    /**
     * Check if the value is uppercase (contains only uppercase letters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is uppercase, false otherwise
     * @uses \ctype_upper() if available, otherwise uses a regular expression
     */
    public static function upper(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_upper')) {
            return \ctype_upper($value);
        }
        return preg_match('/^[A-Z]+$/', $value) === 1;
    }

    /**
     * Check if the value is whitespace (contains only whitespace characters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is whitespace, false otherwise
     * @uses \ctype_space() if available, otherwise uses a regular expression
     */
    public static function space(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_space')) {
            return \ctype_space($value);
        }
        return preg_match('/^\s+$/', $value) === 1;
    }

    /**
     * Check if the value is printable (contains only printable characters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is printable, false otherwise
     * @uses \ctype_print() if available, otherwise uses a regular expression
     */
    public static function print(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_print')) {
            return \ctype_print($value);
        }
        return preg_match('/^[\x20-\x7E]+$/', $value) === 1;
    }

    /**
     * Check if the value is a graphical character (contains only graphical characters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is a graphical character, false otherwise
     * @uses \ctype_graph() if available, otherwise uses a regular expression
     */
    public static function graph(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_graph')) {
            return \ctype_graph($value);
        }
        return preg_match('/^[\x21-\x7E]+$/', $value) === 1;
    }

    /**
     * Check if the value is a control character (contains only control characters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is a control character, false otherwise
     * @uses \ctype_cntrl() if available, otherwise uses a regular expression
     */
    public static function cntrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_cntrl')) {
            return \ctype_cntrl($value);
        }
        return preg_match('/^[\x00-\x1F\x7F]+$/', $value) === 1;
    }

    /**
     * Check if the value is a punctuation character (contains only punctuation characters)
     *
     * @param mixed $value The value to check
     * @return bool True if the value is a punctuation character, false otherwise
     * @uses \ctype_punct() if available, otherwise uses a regular expression
     */
    public static function punct(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (function_exists('ctype_punct')) {
            return \ctype_punct($value);
        }
        return preg_match('/^[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]+$/', $value) === 1;
    }
}
