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
 */
if (!defined('ABSPATH')) {
    return;
}

require __DIR__  .'/vendor/autoload.php';

// check if using CLI and execute current script is in this directory
if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    $scriptName = $_SERVER['SCRIPT_FILENAME']??$_SERVER['SCRIPT_NAME']??'';
    if ($scriptName
        && ($scriptName = realpath($scriptName))
        && str_starts_with($scriptName, __DIR__)
    ) {
        return;
    }
}
