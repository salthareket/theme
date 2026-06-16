<?php
/**
 * Footer Module Processor
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

function theme_styles_process_footer($data, $generator) {
    $footer = $data['footer'] ?? [];

    $bg_type = $footer['bg_type'] ?? 'color';
    $bg_color = $footer['bg_color'] ?? 'transparent';
    $bg_gradient = $footer['bg_gradient'] ?? '';

    $variables = [
        'footer-bg-color'        => $bg_type === 'gradient' ? 'transparent' : $bg_color,
        'footer-bg-gradient'     => $bg_type === 'gradient' ? $bg_gradient : '',
        'footer-color'           => $footer['color']            ?? '#ffffff',
        'footer-color-link'      => $footer['link_color']       ?? '#adb5bd',
        'footer-color-link-hover'=> $footer['link_color_hover'] ?? '#ffffff',
        'footer-height'          => $footer['height']           ?? 'auto',
        'footer-padding'         => $footer['padding']          ?? '60px 0',
        // bg_size: boş string gelirse 'cover' kullan
        'footer-bg-size'         => (!empty($footer['bg_size'])) ? $footer['bg_size'] : 'cover',
        'footer-bg-repeat'       => (!empty($footer['bg_repeat'])) ? $footer['bg_repeat'] : 'no-repeat',
        'footer-bg-position'     => (!empty($footer['bg_position'])) ? $footer['bg_position'] : 'center center',
        'footer-bg-image'        => !empty($footer['bg_image_url']) ? 'url(' . $footer['bg_image_url'] . ')' : 'none',
    ];

    return [ 'variables' => $variables, 'mobile' => [], 'media_queries' => [] ];
}
