<?php
/**
 * Plugin Name: TrayDigita Stack
 * Plugin URI: https://www.traydigita.com
 * Description: A WordPress plugin that provides a set of tools and utilities for developers to enhance
 * their WordPress development experience. It includes features such as a custom autoloader, a
 * registry for managing components, and a set of helper functions for common tasks. The plugin is
 * designed to be lightweight and easy to use, with a focus on performance and flexibility.
 * Version: 1.0.0
 * Author: TrayDigita
 * Author URI: https://www.traydigita.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.3
 */

if (!defined('ABSPATH')) {
    return;
}

(function () {

    // check if using CLI and execute current script is in this directory
    if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
        $scriptName = $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName
            && ($scriptName = realpath($scriptName))
            && str_starts_with($scriptName, __DIR__)
        ) {
            return;
        }
    }

    // check if PHP version is 8.3 or higher
    if (version_compare(PHP_VERSION, '8.3.0', '<')) {
        add_action('admin_notices', function () {
            // check if plugin page
            global $pagenow;
            if ($pagenow !== 'plugins.php' && $pagenow !== 'update.php') {
                return;
            }
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('TrayDigita Stack requires PHP version 8.3 or higher. Please update your PHP version.',
                    'traydigita-stack');
                echo '</p></div>';
            }
        });
        return; // Exit if PHP version is lower than 8.3
    }


    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require __DIR__ . '/vendor/autoload.php';
    } else {
        spl_autoload_register(function ($class) {
            $prefix = 'TrayDigita\\WP\\Stack\\Source\\';
            $base_dir = __DIR__ . "/src/";
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }
    // Register the update check hooks
    add_action('plugins_loaded', function () {
        if (class_exists(TrayDigita\WP\Stack\Source\UpdateCheck::class)) {
            add_filter(
                'pre_set_site_transient_update_plugins',
                [TrayDigita\WP\Stack\Source\UpdateCheck::class, 'checkForUpdates']
            );
            add_filter(
                'plugins_api',
                [TrayDigita\WP\Stack\Source\UpdateCheck::class, 'pluginApiHooks'],
                10,
                3
            );
        }
    });
})();
