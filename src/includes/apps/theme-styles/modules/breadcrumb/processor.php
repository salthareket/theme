<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_breadcrumb($data, $generator) {
    $bc   = $data['breadcrumb'] ?? [];
    $vars = [];

    if (!empty($bc['font_family']))     $vars['breadcrumb-item-font-family']          = $bc['font_family'];
    if (!empty($bc['font_size']))       $vars['breadcrumb-item-font-size']            = $bc['font_size'];
    if (!empty($bc['font_weight']))     $vars['breadcrumb-item-font-weight']          = $bc['font_weight'];
    if (!empty($bc['font_weight_hover']))   $vars['breadcrumb-item-font-weight-hover']  = $bc['font_weight_hover'];
    if (!empty($bc['font_weight_active'])) $vars['breadcrumb-item-font-weight-active'] = $bc['font_weight_active'];
    if (!empty($bc['text_transform']))  $vars['breadcrumb-item-text-transform']       = $bc['text_transform'];
    $vars['breadcrumb-item-letter-spacing'] = $bc['letter_spacing'] ?? '0';
    if (!empty($bc['color']))           $vars['breadcrumb-item-color']                = $bc['color'];
    if (!empty($bc['color_hover']))     $vars['breadcrumb-item-color-hover']          = $bc['color_hover'];
    if (!empty($bc['color_active']))    $vars['breadcrumb-item-color-active']         = $bc['color_active'];
    if (!empty($bc['text_decoration']))        $vars['breadcrumb-item-text-decoration']        = $bc['text_decoration'];
    if (!empty($bc['text_decoration_hover']))  $vars['breadcrumb-item-text-decoration-hover']  = $bc['text_decoration_hover'];
    if (!empty($bc['text_decoration_active'])) $vars['breadcrumb-item-text-decoration-active'] = $bc['text_decoration_active'];

    // Separator: SVG'ye geçildi — CSS variable'a artık yazılmıyor
    // Eski unicode format geriye uyumluluk için korundu (CSS content olarak)
    $sep_raw = $bc['separator_icon'] ?? ($bc['separator'] ?? '');
    if ( ! empty( $sep_raw ) && strpos( $sep_raw, ':' ) === false ) {
        // Eski format \f054 — hâlâ CSS content olarak yaz
        $vars['breadcrumb-sep'] = '"' . $sep_raw . '"';
    }

    // Yeni format solid:angle-right → SVG mask için CSS variable üret
    if ( ! empty( $sep_raw ) && strpos( $sep_raw, ':' ) !== false ) {
        $sep_parts  = explode( ':', $sep_raw, 2 );
        $sep_style  = $sep_parts[0] ?? 'regular';
        $sep_name   = $sep_parts[1] ?? '';
        if ( $sep_name && class_exists( 'SaltHareket\FaIconManager' ) ) {
            $svg = \SaltHareket\FaIconManager::extractFromJs( $sep_name, $sep_style );
            if ( $svg ) {
                // xmlns gerekiyor
                if ( strpos( $svg, 'xmlns=' ) === false ) {
                    $svg = str_replace( '<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $svg );
                }
                $vars['breadcrumb-sep-svg'] = 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
            }
        }
    }

    if (!empty($bc['separator_color'])) $vars['breadcrumb-sep-color'] = $bc['separator_color'];
    if (!empty($bc['separator_size']))  $vars['breadcrumb-sep-size']  = $bc['separator_size'];
    if (!empty($bc['gap']))             $vars['breadcrumb-gap']        = $bc['gap'];
    if (!empty($bc['padding']))         $vars['breadcrumb-padding']    = $bc['padding'];

    // Truncation: template'de enable_truncation, eski sistemde truncate
    $truncate = ($bc['enable_truncation'] ?? ($bc['truncate'] ?? '')) === 'yes' || ($bc['truncate'] ?? '') === '1';
    if ($truncate && !empty($bc['max_width'])) {
        $vars['breadcrumb-max-width'] = $bc['max_width'];
    }

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
