<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'offcanvas_panel',
    'title'       => __('Offcanvas Panel', 'theme-styles'),
    'description' => __('Bootstrap offcanvas panel styles', 'theme-styles'),
    'icon'        => 'dashicons-align-pull-right',
    'priority'    => 76,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
