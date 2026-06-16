<?php
/**
 * Buttons Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'buttons',
    'title' => __('Buttons', 'theme-styles-new'),
    'description' => __('Configure button styles and variations', 'theme-styles-new'),
    'icon' => 'dashicons-button',
    'priority' => 50,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
