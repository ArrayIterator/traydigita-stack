<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Resolver;

use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use function get_stylesheet_directory_uri;
use function get_template_directory;
use function get_template_directory_uri;
use function preg_match;
use function preg_replace_callback;
use function str_contains;
use function str_starts_with;

class ThemeDirUrl
{
    /**
     * As Path
     *
     * @param string $uri
     * @param string $path
     * @param bool $trailingSlash
     * @param bool $normalizedPath
     * @return string
     */
    public static function asPath(
        string $uri,
        string $path,
        bool $trailingSlash = true,
        bool $normalizedPath = true
    ): string {
        if ($path === '' || $path === '/') {
            return $trailingSlash && !preg_match('~[?#]~', $uri)
                ? DataFilter::trailingSlash($normalizedPath ? DataFilter::normalizePath($uri) : $uri)
                : $uri;
        }
        if ($normalizedPath) {
            if (str_contains($path, '?') || str_contains($path, '#')) {
                $path = preg_replace_callback(
                    '~^([^?#]*)([?#].*)$~',
                    function ($matches) {
                        return DataFilter::normalizePath($matches[1]) . $matches[2];
                    },
                    $path
                );
            } else {
                $path = DataFilter::normalizePath($path);
            }
        }
        if (!preg_match('~[?#]~', $uri)) {
            if (!$path || !str_starts_with($path, '/')) {
                $path = "/$path";
            }
            $uri = DataFilter::removeTrailingSlash($uri);
        }
        return "$uri$path";
    }

    /**
     * Template directory
     *
     * @param string $path
     * @param bool $normalizedPath
     * @return string
     */
    public static function templateDirectory(string $path = '', bool $normalizedPath = true): string
    {
        return self::asPath(get_template_directory(), $path, true, $normalizedPath);
    }

    /**
     * Stylesheet directory
     *
     * @param string $path
     * @param bool $normalizedPath
     * @return string
     */
    public static function styleSheetDirectory(string $path = '', bool $normalizedPath = true): string
    {
        return self::asPath(get_stylesheet_directory(), $path, true, $normalizedPath);
    }

    /**
     * Stylesheet uri
     *
     * @param string $path
     * @param bool $normalizedPath
     * @return string
     */
    public static function styleSheetUri(string $path = '', bool $normalizedPath = true): string
    {
        return self::asPath(get_stylesheet_directory_uri(), $path, true, $normalizedPath);
    }

    /**
     * The template uri
     *
     * @param string $path
     * @param bool $normalizedPath
     * @return string
     */
    public static function templateUri(string $path = '', bool $normalizedPath = true): string
    {
        return self::asPath(get_template_directory_uri(), $path, true, $normalizedPath);
    }
}
