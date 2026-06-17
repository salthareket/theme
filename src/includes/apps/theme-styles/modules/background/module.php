<?php
/**
 * Background Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'background',
    'title' => __('Body', 'theme-styles'),
    'description' => __('Configure body background, colors, typography and behavior', 'theme-styles'),
    'icon' => 'dashicons-admin-appearance',
    'priority' => 40,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
