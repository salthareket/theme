<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_buttons($data, $generator) {
    // Button sizes SCSS variable olarak kullanılıyor (wp_scss_set_variables → get_theme_styles)
    // CSS variable olarak geçersiz format olduğu için root.css'e yazılmıyor.
    return ['variables' => [], 'mobile' => [], 'media_queries' => []];
}
