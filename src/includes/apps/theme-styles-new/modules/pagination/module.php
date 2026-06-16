<?php
/**
 * Pagination Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'pagination',
    'title' => __('Pagination', 'theme-styles-new'),
    'description' => __('Configure pagination styles', 'theme-styles-new'),
    'icon' => 'dashicons-ellipsis',
    'priority' => 90,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
