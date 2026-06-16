<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_header_tools( $data, $generator ) {
    $ht  = $data['header_tools'] ?? [];
    $res = [ 'variables' => [], 'mobile' => [], 'media_queries' => [] ];

    // Gap per breakpoint
    foreach ( array_keys( THEME_STYLES_BREAKPOINTS ) as $bp ) {
        if ( ! empty( $ht['gap'][ $bp ] ) ) {
            $res['media_queries'][ $bp ]['header-tools-gap'] = $ht['gap'][ $bp ];
        }
        if ( empty( $ht['height_header'] ) ) {
            if ( ! empty( $ht['height'][ $bp ] ) ) {
                $res['media_queries'][ $bp ]['header-tools-height'] = $ht['height'][ $bp ];
            }
            if ( ! empty( $ht['height_affix'][ $bp ] ) ) {
                $res['media_queries'][ $bp ]['header-tools-height-affix'] = $ht['height_affix'][ $bp ];
            }
        }
    }

    // Simple vars
    $simple = [
        'social.font_family'   => 'header-tools-social-font',
        'social.font_size'     => 'header-tools-social-size',
        'social.color'         => 'header-tools-social-color',
        'social.color_hover'   => 'header-tools-social-color-hover',
        'social.gap'           => 'header-tools-social-gap',
        'icons.font_family'    => 'header-tools-icons-font',
        'icons.font_size'      => 'header-tools-icons-size',
        'icons.color'          => 'header-tools-icons-color',
        'icons.color_hover'    => 'header-tools-icons-color-hover',
        'icons.dot_color'      => 'header-tools-icons-dot-color',
        'toggler.color'        => 'header-tools-toggler-color',
        'toggler.color_hover'  => 'header-tools-toggler-color-hover',
        'counter.color'        => 'header-tools-counter-color',
        'counter.bg_color'     => 'header-tools-counter-bg',
        'link.font_family'     => 'header-tools-link-font',
        'link.font_size'       => 'header-tools-link-size',
        'link.font_weight'     => 'header-tools-link-weight',
        'link.color'           => 'header-tools-link-color',
        'link.color_hover'     => 'header-tools-link-color-hover',
        'link.color_active'    => 'header-tools-link-color-active',
        'language.font_family' => 'header-tools-lang-font',
        'language.font_size'   => 'header-tools-lang-size',
        'language.font_weight' => 'header-tools-lang-weight',
        'language.color'       => 'header-tools-lang-color',
        'language.color_hover' => 'header-tools-lang-color-hover',
        'language.color_active'=> 'header-tools-lang-color-active',
        'button.font_family'   => 'header-tools-btn-font',
        'button.font_size'     => 'header-tools-btn-size',
        'button.font_weight'   => 'header-tools-btn-weight',
    ];

    foreach ( $simple as $path => $var ) {
        $keys  = explode( '.', $path );
        $value = $data;
        foreach ( $keys as $k ) {
            $value = $value[ $k ] ?? null;
            if ( $value === null ) break;
        }
        if ( $value !== null && $value !== '' ) {
            $res['variables'][ $var ] = $value;
        }
    }

    return $res;
}
