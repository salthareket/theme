<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'dropdown',
    'title'       => __('Dropdown', 'theme-styles'),
    'description' => __('Bootstrap dropdown styles', 'theme-styles'),
    'icon'        => 'dashicons-arrow-down-alt2',
    'priority'    => 78,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
