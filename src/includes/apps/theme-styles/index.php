<?php
/**
 * Theme Styles New - Main Loader
 * 
 * Modern, modular, and extensible theme styles management system
 * 
 * @package SaltHareket\Theme\ThemeStyles
 * @version 1.0.0
 * @author Tolga Koçak
 * @since 2026-04-23
 * 
 * CHANGELOG:
 * 1.0.0 - 2026-04-23
 *   - Initial release
 *   - Modular architecture
 *   - Custom UI (no ACF dependency for UI)
 *   - Compatible with ACF data format
 *   - Live preview support
 *   - Preset system
 * 
 * HOW TO USE:
 * Include this file in your theme to enable the new theme styles system.
 * The system will create an admin page under "Appearance → Theme Styles"
 * and provide a modern UI for managing theme styles.
 * 
 * @example Basic usage:
 * // In functions.php or includes/theme.php
 * include_once get_template_directory() . '/theme/includes/theme-styles/index.php';
 * 
 * @example With modules:
 * // Enable only specific modules
 * define('THEME_STYLES_MODULES', ['typography', 'colors', 'spacing']);
 * include_once get_template_directory() . '/theme/includes/theme-styles/index.php';
 * 
 * @example For template post type:
 * // In template single file
 * if (get_post_type() === 'template') {
 *     $template_type = get_field('template_type');
 *     if ($template_type === 'modal') {
 *         theme_styles_load_modules(['typography', 'spacing'], get_the_ID());
 *     }
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('THEME_STYLES_VERSION', '1.0.0');
define('THEME_STYLES_PATH', __DIR__);
define('THEME_STYLES_URL', get_template_directory_uri() . '/vendor/salthareket/theme/src/includes/apps/theme-styles');

/**
 * Breakpoints - Data::get("breakpoints") üzerinden alınır
 * ACF BS Breakpoints plugin'i ile senkronize
 * 
 * @see vendor/salthareket/theme/src/theme.php - Data class
 * @see wp-content/plugins/acf-bs-breakpoints/acf-bs-breakpoints.php
 */
function theme_styles_breakpoints(): array {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    
    // 1. Data::get("breakpoints") - global store'dan al
    if ( class_exists( 'Data' ) && \Data::has( 'breakpoints' ) ) {
        $bps = \Data::get( 'breakpoints' );
        if ( is_array( $bps ) && ! empty( $bps ) ) {
            $cache = $bps;
            return $cache;
        }
    }
    
    // 2. ACF BS Breakpoints plugin'inden al
    $max_widths = [
        'xxxl' => null,
        'xxl'  => '1599px',
        'xl'   => '1399px',
        'lg'   => '1199px',
        'md'   => '991px',
        'sm'   => '767px',
        'xs'   => '575px',
    ];
    
    if ( class_exists( 'acf_bs_breakpoints' ) ) {
        $field_obj = new acf_bs_breakpoints();
        $cache = [];
        foreach ( array_keys( $field_obj->breakpoints ) as $key ) {
            $cache[ $key ] = $max_widths[ $key ] ?? null;
        }
        // Data store'a kaydet
        if ( class_exists( 'Data' ) ) {
            \Data::set( 'breakpoints', $cache );
        }
        return $cache;
    }
    
    // 3. Fallback: hardcoded
    $cache = $max_widths;
    if ( class_exists( 'Data' ) ) {
        \Data::set( 'breakpoints', $cache );
    }
    return $cache;
}

// Constant - after_setup_theme'de tanımla
add_action( 'after_setup_theme', function() {
    if ( ! defined( 'THEME_STYLES_BREAKPOINTS' ) ) {
        define( 'THEME_STYLES_BREAKPOINTS', theme_styles_breakpoints() );
    }
}, 5 );

// Load core classes
require_once __DIR__ . '/includes/class-theme-styles.php';
require_once __DIR__ . '/includes/class-module-manager.php';
require_once __DIR__ . '/includes/class-css-generator.php';
require_once __DIR__ . '/includes/class-preset-manager.php';

// Load frontend helpers (always load)
require_once __DIR__ . '/frontend/helpers.php';
require_once __DIR__ . '/frontend/generator.php';

// Load admin (only in admin)
if (is_admin()) {
    require_once __DIR__ . '/admin/helpers.php';
    require_once __DIR__ . '/admin/settings.php';
    require_once __DIR__ . '/admin/save-handler.php';
}

// Initialize
Theme_Styles::init();
