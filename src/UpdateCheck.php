<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source;

use stdClass;
use function _cleanup_header_comment;
use function apply_filters;
use function array_combine;
use function array_merge;
use function dirname;
use function esc_url;
use function get_plugin_data;
use function get_site_transient;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function is_wp_error;
use function json_decode;
use function pathinfo;
use function plugin_basename;
use function preg_match;
use function preg_quote;
use function set_site_transient;
use function sprintf;
use function str_replace;
use function time;
use function version_compare;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use const MINUTE_IN_SECONDS;
use const PATHINFO_FILENAME;

/**
 * Class UpdateCheck
 *
 * This class is responsible for checking the latest update for the plugin.
 */
class UpdateCheck
{
    /**
     * The GitHub API URL for checking the latest release of the plugin.
     */
    public const string GH_LATEST = 'https://api.github.com/repos/ArrayIterator/traydigita-stack/releases/latest';

    /**
     * The name of the transient used for caching temporary update check results.
     */
    public const string TEMP_NAME_TRANSIENT = 'traydigita-stack-temp-transient';

    /**
     * The name of the transient used for caching temporary tag update check results.
     */
    public const string TEMP_TAG_NAME_TRANSIENT = 'traydigita-stack-temp-tag-transient';

    /**
     * Get the raw URL for a specific tag.
     *
     * @param string $tag The tag for which to get the raw URL.
     * @return string The raw URL for the specified tag.
     */
    private static function tagRawUrl(string $tag): string
    {
        return sprintf(
            'https://raw.githubusercontent.com/ArrayIterator/traydigita-stack/refs/tags/%s/traydigita-stack.php',
            $tag
        );
    }

    /**
     * Get the plugin file path.
     *
     * @return string The plugin file path.
     */
    public static function pluginFile(): string
    {
        return dirname(__DIR__) . '/traydigita-stack.php';
    }

    /**
     * Get the plugin base name.
     *
     * @return string The plugin base name.
     */
    public static function pluginBaseName(): string
    {
        return plugin_basename(self::pluginFile());
    }

    /**
     * Get the plugin data from the specified file data.
     *
     * @param string $file_data The file data to extract plugin information from.
     * @return array<string, string> An associative array containing plugin information.
     */
    private static function getTagMetaData(string $file_data): array
    {
        $default_headers = [
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'AuthorURI' => 'Author URI',
            'TextDomain' => 'Text Domain',
            'DomainPath' => 'Domain Path',
            'Network' => 'Network',
            'RequiresWP' => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
            'UpdateURI' => 'Update URI',
            'RequiresPlugins' => 'Requires Plugins',
            'TestedUpTo' => 'Tested up to',
            // Site Wide Only is deprecated in favor of Network.
            '_sitewide' => 'Site Wide Only',
        ];
        // Make sure we catch CR-only line endings.
        $file_data = str_replace("\r", "\n", $file_data);

        /**
         * Filters extra file headers by context.
         *
         * The dynamic portion of the hook name, `$context`, refers to
         * the context where extra headers might be loaded.
         *
         * @param array $extra_context_headers Empty array by default.
         * @since 2.9.0
         *
         */
        $extra_headers = apply_filters("extra_plugin_headers", []);
        $extra_headers = !is_array($extra_headers) ? [] : $extra_headers;
        if ($extra_headers) {
            $extra_headers = array_combine($extra_headers, $extra_headers); // Keys equal values.
            $all_headers = array_merge($extra_headers, $default_headers);
        } else {
            $all_headers = $default_headers;
        }

        foreach ($all_headers as $field => $regex) {
            if (preg_match(
                '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi',
                $file_data,
                $match
            ) && $match[1]) {
                $all_headers[$field] = _cleanup_header_comment($match[1]);
            } else {
                $all_headers[$field] = '';
            }
        }

        return $all_headers;
    }

    /**
     * Get the plugin data from a specific tag.
     *
     * @param string $tag The tag to fetch plugin data for.
     * @return array<string, string>|null An associative array containing plugin information, or null if not found.
     */
    private static function getPluginDataFromTag(string $tag): ?array
    {
        if (empty($tag)) {
            return null;
        }
        $transientName = self::TEMP_TAG_NAME_TRANSIENT . '-' . $tag;
        $trans = get_site_transient($transientName);
        if (is_array($trans) && is_string($trans['body'] ?? null)
            && is_numeric($trans['time'] ?? null)
            && (time() - (int)$trans['time'] < MINUTE_IN_SECONDS * 15)
            && $trans['body']
            && str_starts_with($trans['body'], '<?php')
        ) {
            $body = $trans['body'];
        } else {
            $url = self::tagRawUrl($tag);
            $url .= '?cache_bust=' . time();
            $response = wp_remote_get($url, ['timeout' => 5]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }
            $body = wp_remote_retrieve_body($response);
            set_site_transient($transientName, [
                'body' => $body,
                'time' => time(),
            ], MINUTE_IN_SECONDS * 15);
        }
        if (!str_starts_with($body, '<?php')) {
            return null;
        }
        return self::getTagMetaData($body);
    }

    /**
     * Cached plugin data to avoid repeated calls to get_plugin_data().
     *
     * @var array<string, string>|null
     */
    private static ?array $pluginData = null;

    /**
     * Get the plugin data from the main plugin file.
     *
     * @return array<string, string> An associative array containing plugin information.
     */
    private static function pluginData(): array
    {
        if (is_array(self::$pluginData)) {
            return self::$pluginData;
        }
        $pluginData = get_plugin_data(self::pluginFile());
        $pluginData = is_array($pluginData) ? $pluginData : [];
        self::$pluginData = $pluginData;
        return self::$pluginData;
    }

    /**
     * Get the latest release information from GitHub.
     *
     * @return string|null The latest release information as a JSON string, or null if not available.
     */
    private static function getLatestRelease(): ?string
    {
        $trans = get_site_transient(self::TEMP_NAME_TRANSIENT);
        if (is_array($trans) && is_string($trans['body'] ?? null)
            && is_numeric($trans['time'] ?? null)
            && (time() - (int)$trans['time'] < MINUTE_IN_SECONDS * 15)
            && is_string($trans['body'] ?? null)
            // check if force_check is set
            && (!isset($_GET['force-check']) || ($_GET['force-check'] !== '1'))
        ) {
            $body = $trans['body'];
        } else {
            $url = self::GH_LATEST;
            $url .= '?cache_bust=' . time();
            $response = wp_remote_get($url, ['timeout' => 5]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }
            $body = wp_remote_retrieve_body($response);
            set_site_transient(self::TEMP_NAME_TRANSIENT, [
                'body' => $body,
                'time' => time(),
            ], MINUTE_IN_SECONDS * 15);
        }
        if (!is_string($body)) {
            return null;
        }
        $releaseData = json_decode($body, true);
        if (!is_array($releaseData)
            || !isset($releaseData['tag_name'])
            || !isset($releaseData['assets'])
            || !is_array($releaseData['assets'])
        ) {
            return null;
        }
        return $body;
    }

    /**
     * Handle the plugin details request for the WordPress plugins API.
     *
     * @param mixed $result The result of the plugins API request.
     * @param mixed $action The action being performed (e.g., 'plugin_information').
     * @param mixed $args The arguments passed to the plugins API request.
     * @return mixed The modified result of the plugins API request.
     */
    public static function pluginApiHooks(mixed $result, mixed $action = null, mixed $args = null): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || !isset($args->slug)) {
            return $result;
        }
        $slug = self::pluginBaseName();
        $slugDir = str_contains($slug, '/') ? dirname($slug) : pathinfo($slug, PATHINFO_FILENAME);
        if ($args->slug !== $slugDir) {
            return $result;
        }
        $transient = get_site_transient('update_plugins');
        if (!is_object($transient) || !is_array($transient->response ?? null)) {
            $transient = new stdClass();
            $transient->checked = [];
            $transient->response = [];
        }
        $data = self::checkForUpdates($transient);
        if (!is_object($data) || !is_array($data->response ?? null)) {
            return $result;
        }
        if (isset($data->response[$slug])) {
            $plugin = $data->response[$slug];
            if (!is_string($plugin->name ?? null)
                || !is_string($plugin->plugin ?? null)
                || $plugin->plugin !== $slug
            ) {
                return $result;
            }
        } else {
            $pluginData = self::pluginData();
            $plugin = new stdClass();
            $plugin->slug = $slugDir;
            $plugin->plugin = $slug;
            $plugin->version = $pluginData['Version'] ?? Stack::VERSION;
            $plugin->name = $pluginData['Name'] ?? Stack::PLUGIN_NAME;
            $plugin->url = $pluginData['PluginURI'] ?? null;
            $plugin->author = $pluginData['Author'] ?? 'TrayDigita';
        }
        $pluginUrl = $plugin->url ?? null;
        $author = $plugin->author ?? 'TrayDigita';
        $info = new stdClass();
        $info->name = $plugin->name ?: Stack::PLUGIN_NAME;
        $info->slug = $plugin->slug;
        $info->version = $plugin->new_version;
        $info->author = $pluginUrl
            ? "<a href='" . esc_url($pluginUrl) . "' target='_blank' rel='noopener noreferrer'>$author</a>"
            : $author;
        $info->homepage = ($plugin->url ?? null) ?: 'https://www.traydigita.com';
        $info->requires = $plugin->requires ?? '6.0';
        $info->tested = $plugin->tested ?? '6.0';
        $info->requires_php = $plugin->requires_php ?? '8.3';
        $info->download_link = !is_string($plugin->package ?? null) || !$plugin->package ? '' : $plugin->package;
        // Try to get changelog/description from the remote source
        $info->sections = [
            'description' => <<<'HTML'
 <p>A WordPress plugin that provides a set of tools and utilities for developers to enhance
 their WordPress development experience. It includes features such as a custom autoloader,
 a registry for managing components, and a set of helper functions for common tasks.
 The plugin is designed to be lightweight and easy to use, with a focus on performance and flexibility.</p>
<p>The following are the main features of the plugin:</p>
<ol>
    <li>Custom autoloader</li>
    <li>Registry for managing components</li>
    <li>Set of helper functions for common tasks</li>
</ol>
HTML,
        ];
        // rating
        $info->rating = 100;
        $info->num_ratings = 9999;
        return $info;
    }

    /**
     * Check for plugin updates and modify the transient accordingly.
     *
     * @param mixed $transient The transient object containing update information.
     * @return mixed The modified transient object with update information.
     */
    public static function checkForUpdates(mixed $transient): mixed
    {
        $slug = self::pluginBaseName();
        if (!is_object($transient)) {
            return $transient;
        }
        // If we have no checked data, don't do anything
        if (!is_object($transient) || empty($transient->checked)) { // @phpstan-ignore-line
            return $transient;
        }
        if (!$transient instanceof stdClass) {
            return $transient;
        }
        $release = self::getLatestRelease();
        if (is_array($transient->response ?? null)) {
            unset($transient->response[$slug]);
        }
        if (!is_string($release)) {
            return $transient;
        }
        $json = json_decode($release, true);
        if (!is_array($json)) {
            return $transient;
        }
        $downloadUrl = null;
        $downloadName = null;
        if (!is_array($json['assets'] ?? null)) {
            return $transient;
        }
        foreach ($json['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            if (($asset['content_type'] ?? '') === 'application/zip') {
                $downloadUrl = $asset['browser_download_url'] ?? null;
                $downloadName = $asset['name'] ?? null;
                break;
            }
        }
        if (!is_string($downloadUrl) || !$downloadUrl || !is_string($downloadName) || !$downloadName) {
            return $transient;
        }
        $updateInfo = self::getPluginDataFromTag($json['tag_name'] ?? '');
        if (!is_array($updateInfo)) {
            return $transient;
        }
        $remote_version = $updateInfo['Version'] ?? null;
        if (!is_string($remote_version) || !$remote_version) {
            return $transient;
        }
        $plugin_data = self::pluginData();
        $version = $plugin_data['Version'] ?? Stack::VERSION;
        $slugDir = str_contains($slug, '/') ? dirname($slug) : pathinfo($slug, PATHINFO_FILENAME);
        // Update available! Construct the required object.
        if (version_compare($version, $remote_version, '>=')) {
            return $transient;
        }
        $update_object = new stdClass();
        $update_object->name = $updateInfo['Name'] ?? Stack::PLUGIN_NAME;
        $update_object->slug = $slugDir;
        $update_object->plugin = $slug;
        $update_object->version = $version;
        $update_object->new_version = $remote_version;
        $update_object->author = $updateInfo['Author'] ?? 'TrayDigita';
        $update_object->author_profile = $updateInfo['AuthorURI'] ?? 'https://www.traydigita.com';
        $update_object->url = $updateInfo['PluginURI'] ?? 'https://www.traydigita.com';
        // This MUST be a direct link to the ZIP file.
        $update_object->package = $downloadUrl;
        $update_object->package_file = $downloadName;
        $update_object->requires = $updateInfo['RequiresWP'] ?? '6.0';
        // add testing info
        $update_object->tested = $updateInfo['TestedUpTo'] ?? '6.0';
        $update_object->requires_php = $updateInfo['RequiresPHP'] ?? '8.3';
        $update_object->download_url = $downloadUrl;
        if (!is_array($transient->response ?? null)) {
            $transient->response = [];
        }
        $transient->response[$slug] = $update_object;

        return $transient;
    }
}
