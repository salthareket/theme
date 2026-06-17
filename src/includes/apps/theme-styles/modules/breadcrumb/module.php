<?php
/**
 * Breadcrumb Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'breadcrumb',
    'title' => __('Breadcrumb', 'theme-styles'),
    'description' => __('Configure breadcrumb navigation styles', 'theme-styles'),
    'icon' => 'dashicons-arrow-right-alt',
    'priority' => 80,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
