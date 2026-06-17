<?php
/**
 * Footer Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'footer',
    'title' => __('Footer', 'theme-styles'),
    'description' => __('Configure footer styles', 'theme-styles'),
    'icon' => 'dashicons-align-full-width',
    'priority' => 70,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
