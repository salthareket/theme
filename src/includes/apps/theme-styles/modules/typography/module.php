<?php
/**
 * Typography Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Register module
Theme_Styles_Module_Manager::register('typography', [
    'label' => __('Typography', 'theme-styles'),
    'icon' => 'dashicons-editor-textcolor',
    'description' => __('Font families, sizes, weights, and text styles', 'theme-styles'),
    'fields' => [
        'font_family' => [
            'type' => 'font',
            'label' => __('Primary Font', 'theme-styles')
        ],
        'headings' => [
            'type' => 'group',
            'label' => __('Headings', 'theme-styles')
        ],
        'title_sizes' => [
            'type' => 'breakpoints',
            'label' => __('Title Sizes', 'theme-styles')
        ],
        'text_sizes' => [
            'type' => 'breakpoints',
            'label' => __('Text Sizes', 'theme-styles')
        ]
    ]
]);
