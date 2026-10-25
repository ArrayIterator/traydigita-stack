<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Provider;

use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Libs\Data\FeatureImage;
use function crc32;
use function in_array;
use function is_array;
use function is_ssl;
use function is_string;
use function ltrim;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function strtolower;
use const PATHINFO_EXTENSION;

/**
 * Provides functionality to work with the Photon CDN for image optimization.
 */
class JetpackPhoton
{
    /**
     * List of WordPress CDN domains used for serving images.
     *
     * @var array<int, string>
     */
    public const WP_CDN_LISTS = [
        'i0.wp.com',
        // 'i1.wp.com',
        // 'i2.wp.com',
        // 'i3.wp.com',
    ];

    /**
     * List of supported image file extensions for the Photon CDN.
     *
     * @var array<int, string>
     */
    public const JETPACK_IMAGE_SUPPORTS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'avif',
        'heic'
    ];

    /**
     * Photon constructor.
     *
     * @param Site $site The site instance to use for URL parsing and validation
     */
    public function __construct(public readonly Site $site)
    {
    }

    /**
     * Index the URL to determine if it can be served by the Photon CDN
     *
     * @param mixed $url The URL to index
     * @return ?array{
     *     url: string,
     *     host: string,
     *     path: string,
     *     index: int
     * } An associative array with 'url', 'host', 'path', and 'index' keys, or null if not applicable
     */
    public function index(mixed $url): ?array
    {
        if ($url instanceof FeatureImage) {
            $url = $url->getFeatureImageUrl();
        }
        if (!is_string($url)) {
            return null;
        }
        foreach (self::WP_CDN_LISTS as $list) {
            /** @noinspection HttpUrlsUsage */
            if (str_starts_with($url, "https://$list/")
                || str_starts_with($url, "http://$list/")
            ) {
                return null;
            }
        }
        $scheme = is_ssl() ? 'https' : 'http';
        if (str_starts_with($url, '//')) {
            $url = "$scheme:$url";
        }
        $host = $this->site->getHostname();
        $parsed = parse_url($url)??[];
        $src_host = $parsed['host'] ?? null;
        $path = $parsed['path'] ?? null;
        if (!$path || $src_host && $src_host !== $host) {
            return null;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!$ext || !in_array(strtolower($ext), self::JETPACK_IMAGE_SUPPORTS)) {
            return null;
        }
        if (!$src_host) {
            $url = sprintf('%s://%s', $scheme, '/' . ltrim($url, '/'));
        }
        $index = crc32($path) % count(self::WP_CDN_LISTS);
        return [
            'url' => $url,
            'host' => $src_host ?: $host,
            'path' => $path,
            'index' => $index
        ];
    }

    /**
     * Get the Photon CDN URL based on the index
     *
     * @param int $index The index of the CDN domain to use
     * @return string|null The Photon CDN URL or null if the index is invalid
     */
    public function urlIndex(int $index): ?string
    {
        $domain = self::WP_CDN_LISTS[$index] ?? null;
        return $domain ? "https://$domain/" : null;
    }

    /**
     * Replace the URL with the Photon CDN URL based on the index
     *
     * @param string $url The URL to replace
     * @param int $index The index of the CDN domain to use
     * @param bool|null $found Whether the URL was replaced or not
     * @param-out bool $found Whether the URL was replaced or not
     * @return string The replaced URL or the original URL if not applicable
     */
    public function replaceWithIndex(string $url, int $index, ?bool &$found = null): string
    {
        $uri = $this->urlIndex($index);
        $found = false;
        if ($uri) {
            if ($this->satisfied($url)) {
                $found = true;
                return $url;
            }
            return (string) preg_replace_callback(
                '~^(?:https?:)?//([^/]+)(/.*)?$~',
                function ($match) use ($uri, &$found) {
                    if (in_array($match[1], self::WP_CDN_LISTS)) {
                        return $match[0];
                    }
                    $found = true;
                    return $uri . $match[1] . ($match[2]??'');
                },
                $url
            );
        }
        return $url;
    }

    /**
     * Replace the URL with the Photon CDN URL if applicable
     *
     * @param mixed $url The URL to replace
     * @param bool|null $found Whether the URL was replaced or not
     * @param-out bool $found Whether the URL was replaced or not
     * @return string The replaced URL or the original URL if not applicable
     */
    public function replace(mixed $url, ?bool &$found = null): string
    {
        if (is_string($url) && $this->satisfied($url)) {
            $found = true;
            return $url;
        }
        $parsed = $this->index($url);
        $found = false;
        if (!is_array($parsed)) {
            return $url;
        }
        return $this->replaceWithIndex($parsed['url'], $parsed['index'], $found);
    }

    /**
     * Check if the URL is satisfied with the Photon CDN
     *
     * @param mixed $url The URL to check
     * @return bool True if the URL is satisfied, false otherwise
     */
    public function satisfied(mixed $url): bool
    {
        return is_string($url) && preg_match(
            '~^https?://i[0-4].wp.com/.+(jpeg?|png|webp|gif|avif|heic)(?:\?.*)?$~i',
            $url
        );
    }
}
