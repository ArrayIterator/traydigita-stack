<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use Stringable;
use TrayDigita\WP\Stack\Source\Libs\Collections\Iter;
use function array_map;
use function array_pop;
use function basename;
use function explode;
use function hexdec;
use function implode;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function parse_url;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_split;
use function stripslashes;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function urldecode;
use function urlencode;

/**
 * A utility class for filtering data.
 */
final class Filter
{
    /**
     * @var array<string, string> Cache for normalized paths
     */
    private static array $normalizePathsCache = [];

    /**
     * Maximum size of the cache for normalized paths.
     */
    private const MAX_CACHED_ITEMS = 2048;

    /**
     * Maximum size of the cache for normalized paths, with a buffer to prevent exceeding the limit.
     */
    private const CACHED_ITEM_REDUCE = self::MAX_CACHED_ITEMS - 100;

    /**
     * Remove magic quotes from the input data.
     *
     * @template T of mixed
     * @param T $data The input data to process.
     * @return T The input data with magic quotes removed.
     */
    public static function removeMagicQuotes(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(static fn($d) => self::removeMagicQuotes($d), $data); // @phpstan-ignore-line
        }
        if (is_string($data)) {
            $data = stripslashes($data);
        }
        /**
         * @var T $data
         */
        return $data;
    }

    /**
     * Filter color
     *
     * @param string $color
     * @return string|null
     */
    public static function colorHex(mixed $color): ?string
    {
        if (!is_string($color)) {
            return null;
        }
        $color = strtolower(trim($color));
        if (str_contains($color, 'rgb')
            && preg_match(
                '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?\s*\)$/',
                $color,
                $matches
            )
        ) {
            $r = (int)$matches[1];
            $g = (int)$matches[2];
            $b = (int)$matches[3];
            $a = isset($matches[4]) ? (float)$matches[4] : 1.0;
            if ($a <= 0) {
                return null;
            }
            if ($r > 255 || $g > 255 || $b > 255) {
                return null;
            }
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
        if (str_starts_with($color, '#')) {
            $color = substr($color, 1);
        }
        $length = strlen($color);
        if ($length !== 3 && $length !== 6 && $length !== 8) {
            return null;
        }
        if (!Ctype::xdigit($color)) {
            return null;
        }
        $color = strtolower($color);
        return match ($length) {
            3 => '#' . implode('', array_map(fn($c) => $c . $c, str_split($color))),
            6 => '#' . $color,
            default => substr($color, 0, 6),
        };
    }

    /**
     * Filter color to rgba
     *
     * @param mixed $color
     * @return string|null
     */
    public static function rgbaColor(mixed $color): ?string
    {
        if (!is_string($color)) {
            return null;
        }
        $color = strtolower(trim($color));
        if (str_contains($color, 'rgb')
            && preg_match(
                '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?\s*\)$/',
                $color,
                $matches
            )
        ) {
            $r = (int)$matches[1];
            $g = (int)$matches[2];
            $b = (int)$matches[3];
            $a = isset($matches[4]) ? (float)$matches[4] : 1.0;
            if ($a <= 0) {
                return null;
            }
            if ($r > 255 || $g > 255 || $b > 255) {
                return null;
            }
            return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $a);
        }
        if (str_starts_with($color, '#')) {
            $color = substr($color, 1);
        }
        $length = strlen($color);
        if ($length !== 3 && $length !== 6 && $length !== 8) {
            return null;
        }
        if (!Ctype::xdigit($color)) {
            return null;
        }
        $color = strtolower($color);
        return match ($length) {
            3 => sprintf(
                'rgba(%d, %d, %d, 1)',
                hexdec($color[0] . $color[0]),
                hexdec($color[1] . $color[1]),
                hexdec($color[2] . $color[2])
            ),
            6 => sprintf(
                'rgba(%d, %d, %d, 1)',
                hexdec(substr($color, 0, 2)),
                hexdec(substr($color, 2, 2)),
                hexdec(substr($color, 4, 2))
            ),
            default => sprintf(
                'rgba(%d, %d, %d, %s)',
                hexdec(substr($color, 0, 2)),
                hexdec(substr($color, 2, 2)),
                hexdec(substr($color, 4, 2)),
                // 100 * alpha value
                (static function ($alpha) {
                    $alpha = hexdec($alpha);
                    $alpha = $alpha / 255;
                    return $alpha === 0
                        ? '0'
                        : ($alpha === 1 ? '1' : sprintf('%.2f', $alpha));
                })(substr($color, 6, 2))
            )
        };
    }

    /**
     * Get relative path from URL
     *
     * @param string $url
     * @return string
     */
    public static function relativePathURL(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        $segments = explode('/', $path);
        $absSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($absSegments);
            } else {
                $absSegments[] = $segment;
            }
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';

        return $scheme . $host . '/' . implode('/', $absSegments);
    }

    /**
     * Filter hex string
     *
     * @param mixed $hex
     * @return string|null
     */
    public static function hex(mixed $hex): ?string
    {
        if (!is_string($hex)) {
            return null;
        }
        $hex = trim($hex);
        return Ctype::xdigit($hex) ? $hex : null;
    }

    /**
     * Cast data to string
     *
     * @param mixed|scalar|Stringable $data
     * @param string|Stringable $default
     * @return string
     */
    public static function castString(mixed $data, string|Stringable $default = ''): string
    {
        if (is_string($data)) {
            return $data;
        }
        if (is_scalar($data)) {
            return (string)$data;
        }
        if (is_object($data) && method_exists($data, '__toString')) {
            return $data->__toString();
        }
        return (string)$default;
    }

    /**
     * Add a trailing slash to a file path.
     *
     * @param string $path The file path to process.
     * @return string The file path with a trailing slash.
     */
    public static function trailingSlash(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }

    /**
     * Remove trailing slashes from a file path.
     *
     * @param string $path The file path to process.
     * @return string The file path without trailing slashes.
     */
    public static function removeTrailingSlash(string $path): string
    {
        return rtrim($path, '/\\');
    }

    /**
     * Normalize a file path by replacing backslashes with forward slashes,
     * removing duplicate slashes, and trimming trailing slashes.
     *
     * @param string $path The file path to normalize.
     * @param bool $unSlashEnd Whether to remove the trailing slash from the normalized path.
     * @return string The normalized file path.
     */
    public static function normalizePath(mixed $path, bool $unSlashEnd = false): string
    {
        if (!is_scalar($path)) {
            return '';
        }
        if (!is_string($path)) {
            return (string)$path;
        }
        if (!isset(self::$normalizePathsCache[$path])) {
            $path = str_replace('\\', '/', $path);
            $path = (string)preg_replace('#/+#', '/', $path);
            $path = rtrim($path, '/');
            // slice the cache to keep only the last MAX_CACHE_FILE_SIZE_SLICE entries
            self::$normalizePathsCache = Iter::sliceIf(
                self::$normalizePathsCache,
                self::MAX_CACHED_ITEMS,
                self::CACHED_ITEM_REDUCE
            );
            self::$normalizePathsCache[$path] = $path;
        }
        return $unSlashEnd ? rtrim(self::$normalizePathsCache[$path], '/') : self::$normalizePathsCache[$path];
    }

    /**
     * Get the base name of a file path, optionally removing a suffix.
     *
     * @param string $path The file path to process.
     * @param string $suffix An optional suffix to remove from the base name.
     * @return string The base name of the file path, with the suffix removed if specified.
     * @see \wp_basename()
     */
    public static function baseName(string $path, string $suffix = ''): string
    {
        return urldecode(
            basename(
                str_replace(
                    ['%2F', '%5C'],
                    '/',
                    urlencode($path)
                ),
                $suffix
            )
        );
    }
}
