<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'popover',
    'title'       => __('Popover & Tooltip', 'theme-styles'),
    'description' => __('Bootstrap popover and tooltip styles', 'theme-styles'),
    'icon'        => 'dashicons-info-outline',
    'priority'    => 79,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
