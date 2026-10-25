<?php
/**
 * Backward compatibility for ctype functions.
 *
 * This file provides polyfills for ctype functions in case they are not available
 * in the PHP environment. It ensures that the application can still function correctly
 * even if the ctype extension is not installed.
 */
declare(strict_types=1);

namespace {

    use TrayDigita\WP\Stack\Source\Libs\Helpers\Ctype;

    if (!function_exists('ctype_digit')) {
        function ctype_digit(mixed $str): bool
        {
            return Ctype::digit($str);
        }
    }
    if (!function_exists('ctype_alpha')) {
        function ctype_alpha(mixed $str): bool
        {
            return Ctype::alpha($str);
        }
    }
    if (!function_exists('ctype_xdigit')) {
        function ctype_xdigit(mixed $str): bool
        {
            return Ctype::xdigit($str);
        }
    }
    if (!function_exists('ctype_alnum')) {
        function ctype_alnum(mixed $str): bool
        {
            return Ctype::alnum($str);
        }
    }
    if (!function_exists('ctype_space')) {
        function ctype_space(mixed $str): bool
        {
            return Ctype::space($str);
        }
    }
    if (!function_exists('ctype_cntrl')) {
        function ctype_cntrl(mixed $str): bool
        {
            return Ctype::cntrl($str);
        }
    }
    if (!function_exists('ctype_print')) {
        function ctype_print(mixed $str): bool
        {
            return Ctype::print($str);
        }
    }
    if (!function_exists('ctype_punct')) {
        function ctype_punct(mixed $str): bool
        {
            return Ctype::punct($str);
        }
    }
    if (!function_exists('ctype_graph')) {
        function ctype_graph(mixed $str): bool
        {
            return Ctype::graph($str);
        }
    }
    if (!function_exists('ctype_lower')) {
        function ctype_lower(mixed $str): bool
        {
            return Ctype::lower($str);
        }
    }
    if (!function_exists('ctype_upper')) {
        function ctype_upper(mixed $str): bool
        {
            return Ctype::upper($str);
        }
    }
}
