<?php
/**
 * WooCommerce Includes Loader
 * 
 * Loads all WooCommerce related files
 * 
 * @package SaltHareket\Theme\WooCommerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load admin files
if (is_admin()) {
    if (file_exists(__DIR__ . '/admin.php')) {
        require_once __DIR__ . '/admin.php';
    }
}

// Load custom fields helpers (frontend + admin)
if (file_exists(__DIR__ . '/custom-fields-helpers.php')) {
    require_once __DIR__ . '/custom-fields-helpers.php';
}

// Load functions
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}
