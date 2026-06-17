<?php
/**
 * Header Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'header',
    'title' => __('Header', 'theme-styles'),
    'description' => __('Configure header styles and navigation', 'theme-styles'),
    'icon' => 'dashicons-align-center',
    'priority' => 60,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
