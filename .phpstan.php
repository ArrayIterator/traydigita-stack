<?php
/**
 * @noinspection PhpDefineCanBeReplacedWithConstInspection
 */
declare(strict_types=1);

// check if not cli
if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    return; // only run in cli mode
}

// disable WP themes for this script
define('WP_USE_THEMES', false);

require_once __DIR__ .'/../../../wp-load.php';
