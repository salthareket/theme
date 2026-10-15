<?php
/**
 * Admin Login Module — CSS Processor
 * Üretilen CSS variables login_head hook'unda inline <style> olarak enjekte edilir.
 * root.css'e yazılmaz (login sayfası admin tema dışında çalışır).
 *
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function theme_styles_process_admin_login( array $data, $generator ): array {
    // Admin login modülü root.css'e variable yazmaz — login_head'de inline enjekte edilir.
    // Processor burada boş döner; CSS üretimi custom-login.php üzerinden yapılır.
    return [ 'variables' => [], 'mobile' => [], 'media_queries' => [] ];
}

/**
 * Admin Login default değerlerini döner.
 */
function theme_styles_admin_login_defaults(): array {
    return [
        // Layout
        'page_bg_type'          => 'color',
        'page_bg_color'         => '#1d2327',
        'page_bg_gradient'      => '',
        'page_bg_image_id'      => '',
        'page_bg_image_url'     => '',
        'page_bg_size'          => 'cover',
        'page_bg_position'      => 'center center',
        'page_bg_repeat'        => 'no-repeat',
        'page_bg_attachment'    => 'fixed',

        // Logo
        'logo_image_id'         => '',
        'logo_image_url'        => '',
        'logo_max_height'       => '80',
        'logo_max_width'        => '200',

        // Box
        'box_bg_color'          => '',
        'box_bg_opacity'        => '1',
        'box_border_radius'     => '8',
        'box_padding'           => '24',
        'box_shadow'            => '1',
        'box_width'             => '360',

        // Form
        'input_bg'              => '#f6f7f7',
        'input_border'          => '#8c8f94',
        'input_border_focus'    => '#2271b1',
        'input_color'           => '#1d2327',
        'input_radius'          => '4',
        'label_color'           => '#1d2327',

        // Button
        'btn_bg'                => '#2271b1',
        'btn_bg_hover'          => '#135e96',
        'btn_color'             => '#ffffff',
        'btn_color_hover'       => '#ffffff',
        'btn_border'            => '#2271b1',
        'btn_border_hover'      => '#135e96',
        'btn_radius'            => '3',
        'btn_text_transform'    => 'none',
        'btn_font_weight'       => '600',

        // Links
        'link_color'            => '#2271b1',
        'link_color_hover'      => '#135e96',

        // Text
        'text_color'            => '#1d2327',
        'description_color'     => '#646970',

        // Error / Message
        'error_bg'              => '#fcf0f1',
        'error_border'          => '#d63638',
        'error_color'           => '#d63638',
        'success_bg'            => '#edfaef',
        'success_border'        => '#00a32a',
        'success_color'         => '#00a32a',

        // Custom CSS
        'custom_css'            => '',
    ];
}

/**
 * Admin Login için Theme Styles'tan veri çeker, defaults ile birleştirir.
 */
function theme_styles_get_admin_login_data(): array {
    $defaults = theme_styles_admin_login_defaults();

    if ( class_exists( 'Theme_Styles' ) ) {
        $all  = Theme_Styles::init()->get_data();
        $data = $all['admin_login'] ?? [];
    } else {
        $data = [];
    }

    return wp_parse_args( $data, $defaults );
}
