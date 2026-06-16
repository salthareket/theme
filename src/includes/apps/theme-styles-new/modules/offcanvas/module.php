<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'offcanvas',
    'title'       => __('Offcanvas Menu', 'theme-styles-new'),
    'description' => __('Offcanvas menu styles', 'theme-styles-new'),
    'icon'        => 'dashicons-menu-alt',
    'priority'    => 75,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
