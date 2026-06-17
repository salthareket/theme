<?php
/**
 * Colors Module
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

Theme_Styles_Module_Manager::register('colors', [
    'label' => __('Colors', 'theme-styles'),
    'icon' => 'dashicons-art',
    'description' => __('Primary, secondary, custom colors and gradients', 'theme-styles')
]);
