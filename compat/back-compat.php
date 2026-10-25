<?php
/**
 * Includes backward compatibility some functions for ctype functions.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

// ctype functions are not available in some PHP environments, so we include a polyfill for them.
require_once __DIR__ . '/ctype.php';
require_once __DIR__ . '/array.php';
