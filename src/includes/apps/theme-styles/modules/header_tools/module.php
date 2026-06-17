<?php
if (!defined('ABSPATH')) exit;

return [
    'id'          => 'header_tools',
    'title'       => __('Header Tools', 'theme-styles'),
    'description' => __('Header toolbar elements: social, icons, links, language, toggler', 'theme-styles'),
    'icon'        => 'dashicons-admin-tools',
    'priority'    => 65,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => false, // Header modülüne taşındı
];
