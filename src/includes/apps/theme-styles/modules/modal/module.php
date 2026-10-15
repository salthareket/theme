<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'modal',
    'title'       => __('Modal', 'theme-styles'),
    'description' => __('Bootstrap modal styles', 'theme-styles'),
    'icon'        => 'dashicons-layout',
    'priority'    => 77,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
