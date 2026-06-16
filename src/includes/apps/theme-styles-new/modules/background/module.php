<?php
/**
 * Background Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'background',
    'title' => __('Body', 'theme-styles-new'),
    'description' => __('Configure body background, colors, typography and behavior', 'theme-styles-new'),
    'icon' => 'dashicons-admin-appearance',
    'priority' => 40,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
