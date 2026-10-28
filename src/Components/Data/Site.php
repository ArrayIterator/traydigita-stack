<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Data;

use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Hook;
use TrayDigita\WP\Stack\Source\Libs\Resolver\ThemeDirUrl;
use WP_Theme;
use function defined;
use function function_exists;
use function get_bloginfo;
use function get_home_url;
use function get_site_url;
use function is_multisite;
use function is_string;
use function trailingslashit;
use function wp_get_theme;
use function wp_parse_url;
use const PHP_URL_HOST;

/**
 * @property-read string $hostname
 * @property-read string $siteUrl
 * @property-read string $homeUrl
 * @property-read string $name
 * @property-read string $description
 * @property-read string $email
 * @property-read bool $multisite
 * @property-read string $language
 * @property-read string $charset
 * @property-read string $version
 * @property-read bool $development
 * @property-read WP_Theme $theme
 * @property string $distributionPath
 * @property-read string $distributionDir
 * @property-read string $distributionUrl
 */
class Site
{
    /**
     * The site's hostname, parsed from the site URL.
     *
     * @var string
     */
    private string $hostname;

    /**
     * The site URL as returned by get_site_url().
     *
     * @var string
     */
    private string $siteUrl;

    /**
     * The home URL as returned by get_home_url().
     *
     * @var string
     */
    private string $homeUrl;

    /**
     * The site name (blog name).
     *
     * @var string
     */
    private string $name;

    /**
     * The site description (tagline).
     *
     * @var string
     */
    private string $description;

    /**
     * The admin email address of the site.
     *
     * @var string
     */
    private string $email;

    /**
     * The site language locale.
     *
     * @var string
     */
    private string $language;

    /**
     * The site charset.
     *
     * @var string
     */
    private string $charset;

    /**
     * The WordPress version.
     *
     * @var string
     */
    private string $version;

    /**
     * Indicates whether the site is part of a multi-site network.
     *
     * @var bool
     */
    private bool $multisite;

    /**
     * The current active theme.
     *
     * @var WP_Theme
     */
    private WP_Theme $theme;

    /**
     * Indicates whether the site is in development mode.
     *
     * @var bool
     */
    public readonly bool $isDevelopment;

    /**
     * The distribution path for the site.
     *
     * @var string
     */
    private string $distributionPath;

    /**
     * The distribution directory URL for the site.
     *
     * @var string
     */
    private string $distributionDir;

    /**
     * The distribution URL for the site.
     *
     * @var string
     */
    private string $distributionUrl;

    /**
     * Site constructor.
     * @param SiteOptions $options The site options instance.
     * @param SiteTransients $transients The site transients instance.
     */
    public function __construct(
        public readonly SiteOptions $options,
        public readonly SiteTransients $transients
    ) {
        // @phpstan-ignore-next-line
        $this->isDevelopment = defined('WP_DEBUG') && \WP_DEBUG
            || defined('TRAYDIGITA_DEVELOPMENT') && \TRAYDIGITA_DEVELOPMENT === true;
        $this->setDistributionPath('dist');
        Hook::add('switch_theme', [$this, 'switchThemeHook'], 10, 2);
    }

    /**
     * Hook to handle theme switching and update the current theme property.
     *
     * @param mixed $newName The new theme name.
     * @param mixed $newTheme The new WP_Theme instance.
     * @return mixed The new theme name.
     */
    public function switchThemeHook(mixed $newName = null, mixed $newTheme = null) : mixed
    {
        if (!$newTheme instanceof WP_Theme || !Hook::doing('switch_theme')) {
            return $newName;
        }
        $this->theme = $newTheme;
        return $newName;
    }

    /**
     * Check if the site is in development mode.
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->isDevelopment;
    }

    /**
     * Get the distribution path for the site.
     *
     * @return string
     */
    public function getDistributionPath(): string
    {
        return $this->distributionPath;
    }

    /**
     * Set the distribution path for the site.
     *
     * @param string $distributionPath
     * @return void
     */
    public function setDistributionPath(string $distributionPath): void
    {
        $distributionPath = DataFilter::normalizePath($distributionPath, true);
        if (!empty($distributionPath)) {
            $this->distributionPath = $distributionPath;
            $this->distributionDir = trailingslashit(ThemeDirUrl::templateDirectory($distributionPath . '/'));
            $this->distributionUrl = trailingslashit(ThemeDirUrl::templateUri($distributionPath . '/'));
        }
    }

    /**
     * Get the distribution directory URL for the site.
     *
     * @param string $path
     * @return string
     */
    public function getDistributionDir(string $path = ''): string
    {
        return ThemeDirUrl::asPath($this->distributionDir, $path);
    }

    /**
     * Get the distribution URL for the site.
     *
     * @param string $path
     * @return string
     */
    public function getDistributionUrl(string $path = ''): string
    {
        return ThemeDirUrl::asPath($this->distributionUrl, $path);
    }

    /**
     * Get the current active theme.
     *
     * @return WP_Theme
     */
    public function getTheme(): WP_Theme
    {
        return $this->theme ??= wp_get_theme();
    }

    /**
     * Get the site URL, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getSiteUrl(bool $force = false): string
    {
        if ($force || empty($this->siteUrl)) {
            $this->siteUrl = get_site_url();
        }
        return $this->siteUrl;
    }

    /**
     * Get the home URL, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getHomeUrl(bool $force = false): string
    {
        if ($force || empty($this->homeUrl)) {
            $this->homeUrl = get_home_url();
        }
        return $this->homeUrl;
    }

    /**
     * Get the site hostname parsed from the site URL, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getHostname(bool $force = false): string
    {
        if ($force || empty($this->hostname)) {
            $url = $this->getSiteUrl($force);
            $parsed = wp_parse_url($url);
            $this->hostname = $parsed[PHP_URL_HOST] ?? '';
        }
        return $this->hostname;
    }

    /**
     * Get the site name (blog name), optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getName(bool $force = false): string
    {
        if ($force || empty($this->name)) {
            $this->name = get_bloginfo('name');
        }
        return $this->name;
    }

    /**
     * Get the site description (tagline), optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getDescription(bool $force = false): string
    {
        if ($force || empty($this->description)) {
            $this->description = get_bloginfo('description');
        }
        return $this->description;
    }

    /**
     * Get the admin email address of the site, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getEmail(bool $force = false): string
    {
        if ($force || empty($this->email)) {
            $this->email = get_bloginfo('admin_email');
        }
        return $this->email;
    }

    /**
     * Get the site language locale, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getLanguage(bool $force = false): string
    {
        if ($force || empty($this->language)) {
            $this->language = get_bloginfo('language');
        }
        return $this->language;
    }

    /**
     * Get the site charset, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getCharset(bool $force = false): string
    {
        if ($force || empty($this->charset)) {
            $this->charset = get_bloginfo('charset');
        }
        return $this->charset;
    }

    /**
     * Get the WordPress version, optionally forcing a refresh.
     *
     * @param bool $force Force re-fetching the value instead of using the cached one.
     * @return string
     */
    public function getVersion(bool $force = false): string
    {
        if ($force || empty($this->version)) {
            $this->version = get_bloginfo('version');
        }
        return $this->version;
    }

    /**
     * Check if the site is part of a multi-site network.
     *
     * @return bool
     */
    public function isMultisite(): bool
    {
        if (isset($this->multisite)) {
            return $this->multisite;
        }
        if (!function_exists('is_multisite')) {
            return false;
        }
        return $this->multisite = is_multisite();
    }

    /**
     * Magic setter to allow setting properties via their public-facing property names.
     *
     * @param string $name The property name to set.
     * @param mixed $value The value to set for the property.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'distributionPath' && is_string($value)) {
            $this->setDistributionPath($value);
        }
    }

    /**
     * Magic getter to access properties via their public-facing property names.
     *
     * @param string $name The property name to retrieve.
     * @return mixed
     */
    public function __get(string $name)
    {
        return match ($name) {
            'siteUrl' => $this->getSiteUrl(),
            'homeUrl' => $this->getHomeUrl(),
            'hostname' => $this->getHostname(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'email' => $this->getEmail(),
            'language' => $this->getLanguage(),
            'charset' => $this->getCharset(),
            'version' => $this->getVersion(),
            'development' => $this->isDevelopment,
            'multisite' => $this->isMultisite(),
            'theme' => $this->getTheme(),
            'distributionPath' => $this->getDistributionPath(),
            default => $this->$name ?? null
        };
    }

    /**
     * Magic un-setter, intentionally a no-op since properties are managed internally.
     *
     * @param string $name The property name to unset.
     * @return void
     */
    public function __unset(string $name): void
    {
        if ($name === 'distributionPath') {
            $this->distributionPath = 'dist';
        }
        // void
    }

    /**
     * Magic isset check for public-facing property names.
     *
     * @param string $name The property name to check.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'siteUrl', 'homeUrl', 'hostname', 'name',
            'description', 'email', 'language', 'charset', 'version',
            'development', 'multisite', 'theme' => true,
            default => isset($this->$name)
        };
    }
}
