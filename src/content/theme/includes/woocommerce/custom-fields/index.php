<?php
/**
 * WooCommerce Custom Product Fields - Main Loader
 * 
 * Loads all custom fields related files
 * 
 * @package SaltHareket\Theme\WooCommerce\CustomFields
 * @version 2.0.0
 * @author SaltHareket
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load frontend helpers (always load - needed for Timber extend)
if (file_exists(__DIR__ . '/frontend/helpers.php')) {
    require_once __DIR__ . '/frontend/helpers.php';
}

// Load admin files (only in admin)
if (is_admin()) {
    // Settings page (WooCommerce → Settings → Products → Custom Fields)
    if (file_exists(__DIR__ . '/admin/settings.php')) {
        require_once __DIR__ . '/admin/settings.php';
    }
    
    // Product edit page display
    if (file_exists(__DIR__ . '/admin/display.php')) {
        require_once __DIR__ . '/admin/display.php';
    }
}
