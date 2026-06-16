<?php
/**
 * Typography Module
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Register module
Theme_Styles_Module_Manager::register('typography', [
    'label' => __('Typography', 'theme-styles-new'),
    'icon' => 'dashicons-editor-textcolor',
    'description' => __('Font families, sizes, weights, and text styles', 'theme-styles-new'),
    'fields' => [
        'font_family' => [
            'type' => 'font',
            'label' => __('Primary Font', 'theme-styles-new')
        ],
        'headings' => [
            'type' => 'group',
            'label' => __('Headings', 'theme-styles-new')
        ],
        'title_sizes' => [
            'type' => 'breakpoints',
            'label' => __('Title Sizes', 'theme-styles-new')
        ],
        'text_sizes' => [
            'type' => 'breakpoints',
            'label' => __('Text Sizes', 'theme-styles-new')
        ]
    ]
]);
