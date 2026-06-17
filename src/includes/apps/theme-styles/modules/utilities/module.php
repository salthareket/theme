<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'utilities',
    'title'       => __('Utilities', 'theme-styles'),
    'description' => __('Scroll to top button and hero section settings', 'theme-styles'),
    'icon'        => 'dashicons-admin-tools',
    'priority'    => 90,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
