<?php
/**
 * Breadcrumb Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'breadcrumb',
    'title' => __('Breadcrumb', 'theme-styles-new'),
    'description' => __('Configure breadcrumb navigation styles', 'theme-styles-new'),
    'icon' => 'dashicons-arrow-right-alt',
    'priority' => 80,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
