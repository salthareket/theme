<?php
/**
 * Spacing Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

return [
    'id' => 'spacing',
    'title' => __('Spacing', 'theme-styles'),
    'description' => __('Configure spacing, margins, paddings and gaps', 'theme-styles'),
    'icon' => 'dashicons-editor-expand',
    'priority' => 30,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php',
    'enabled' => false,
];
