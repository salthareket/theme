<?php
/**
 * Spacing Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'spacing',
    'title' => __('Spacing', 'theme-styles-new'),
    'description' => __('Configure spacing, margins, paddings and gaps', 'theme-styles-new'),
    'icon' => 'dashicons-editor-expand',
    'priority' => 30,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php',
    'enabled' => false,
];
