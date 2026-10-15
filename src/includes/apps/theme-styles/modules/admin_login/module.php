<?php
/**
 * Admin Login Module
 *
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

return [
    'id'          => 'admin_login',
    'title'       => __( 'Admin Login', 'theme-styles' ),
    'description' => __( 'Customize the WordPress admin login page', 'theme-styles' ),
    'icon'        => 'dashicons-admin-users',
    'priority'    => 999,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
