<?php
/**
 * Buttons Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'buttons',
    'title' => __('Buttons', 'theme-styles'),
    'description' => __('Configure button styles and variations', 'theme-styles'),
    'icon' => 'dashicons-button',
    'priority' => 50,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
