<?php

/**
 * Custom Admin Login Page & Dashboard Cleanup
 * Admin Login stilleri artık Theme Styles → Admin Login modülünden okunur.
 * ACF bağımlılığı kaldırıldı.
 */

// ─── Login Page Branding ────────────────────────────────────

add_filter( 'login_headerurl', fn() => home_url() );

add_filter( 'login_headertext', function () {
    // Önce Theme Styles'tan logo dene
    $logo_url = '';
    if ( function_exists( 'theme_styles_get_admin_login_data' ) ) {
        $al       = theme_styles_get_admin_login_data();
        $logo_url = $al['logo_image_url'] ?? '';
    }
    // Fallback: eski ACF field
    if ( empty( $logo_url ) && function_exists( 'get_field' ) ) {
        $logo_url = get_field( 'admin_logo', 'option' ) ?: '';
    }
    // Son fallback: tema default logosu
    if ( empty( $logo_url ) ) {
        $logo_url = SH_STATIC_URL . 'img/logo-login.png';
    }
    return '<img src="' . esc_url( $logo_url ) . '" class="img-fluid" alt="Login">';
} );

add_action( 'login_head', function () {
    // Processor'ı yükle (fonksiyonlar için)
    $processor = SH_INCLUDES_PATH . 'apps/theme-styles/modules/admin_login/processor.php';
    if ( file_exists( $processor ) && ! function_exists( 'theme_styles_admin_login_defaults' ) ) {
        require_once $processor;
    }

    // Theme Styles'tan veri al
    if ( ! function_exists( 'theme_styles_get_admin_login_data' ) ) {
        // Fallback: eski ACF sistemi (geçiş dönemi)
        if ( function_exists( 'get_field' ) ) {
            $bg     = get_field( 'admin_bg',     'option' ) ?: [];
            $text   = get_field( 'admin_text',   'option' ) ?: [];
            $link   = get_field( 'admin_link',   'option' ) ?: [];
            $button = get_field( 'admin_button', 'option' ) ?: [];
            $v = fn( $arr, $key, $default ) => esc_attr( $arr[ $key ] ?? $default );
            echo '<link rel="stylesheet" href="' . esc_url( get_bloginfo( 'template_directory' ) . '/static/css/admin-login.css' ) . '" media="all">';
            echo '<style>
            body.login { background-color:' . $v($bg,'color','#1d2327') . '!important; background-image:url(' . esc_url($bg['image'] ?? '') . ')!important; background-position:' . $v($bg,'position_hr','center') . ' ' . $v($bg,'position_vr','center') . '!important; background-size:' . $v($bg,'size','auto') . '!important; background-repeat:' . $v($bg,'repeat','no-repeat') . '!important; }
            .login form label,.login h1.admin-email__heading,p.admin-email__details,.login .description{color:' . $v($text,'color','#1d2327') . '!important;}
            .login #nav a,.login #backtoblog a{color:' . $v($link,'color','#2271b1') . '!important;}
            .login #nav a:hover,.login #backtoblog a:hover{color:' . $v($link,'color_hover','#135e96') . '!important;}
            .button.button-primary{background-color:' . $v($button,'bg','#2271b1') . '!important;border-color:' . $v($button,'border','#2271b1') . '!important;color:' . $v($button,'color','#fff') . '!important;}
            .button.button-primary:hover{background-color:' . $v($button,'bg_hover','#135e96') . '!important;border-color:' . $v($button,'border_hover','#135e96') . '!important;color:' . $v($button,'color_hover','#fff') . '!important;}
            </style>';
        }
        return;
    }

    $al = theme_styles_get_admin_login_data();

    // ── Page background ──────────────────────────────────────────
    $bg_type    = $al['page_bg_type']       ?? 'color';
    $bg_color   = $al['page_bg_color']      ?? '#1d2327';
    $bg_image   = $al['page_bg_image_url']  ?? '';
    $bg_size    = $al['page_bg_size']       ?? 'cover';
    $bg_pos     = $al['page_bg_position']   ?? 'center center';
    $bg_repeat  = $al['page_bg_repeat']     ?? 'no-repeat';
    $bg_att     = $al['page_bg_attachment'] ?? 'fixed';
    $bg_grad    = $al['page_bg_gradient']   ?? '';

    // Background value
    if ( $bg_type === 'image' && ! empty( $bg_image ) ) {
        $bg_value = 'url(' . esc_url( $bg_image ) . ')';
    } elseif ( $bg_type === 'gradient' && ! empty( $bg_grad ) ) {
        $bg_value = esc_attr( $bg_grad );
    } else {
        $bg_value = 'none';
    }

    // ── Box ──────────────────────────────────────────────────────
    $box_bg      = $al['box_bg_color']      ?? '';
    $box_radius  = $al['box_border_radius'] ?? '8';
    $box_padding = $al['box_padding']       ?? '24';
    $box_width   = $al['box_width']         ?? '360';
    $box_shadow  = ( ! empty( $al['box_shadow'] ) && $al['box_shadow'] !== '0' )
        ? '0 4px 24px rgba(0,0,0,.18), 0 1px 3px rgba(0,0,0,.1)'
        : 'none';

    // ── Button ───────────────────────────────────────────────────
    $btn_bg           = $al['btn_bg']           ?? '#2271b1';
    $btn_bg_hover     = $al['btn_bg_hover']     ?? '#135e96';
    $btn_color        = $al['btn_color']        ?? '#ffffff';
    $btn_color_hover  = $al['btn_color_hover']  ?? '#ffffff';
    $btn_border       = $al['btn_border']       ?? '#2271b1';
    $btn_border_hover = $al['btn_border_hover'] ?? '#135e96';
    $btn_radius       = $al['btn_radius']       ?? '3';
    $btn_transform    = $al['btn_text_transform'] ?? 'none';
    $btn_weight       = $al['btn_font_weight']  ?? '600';

    // ── Logo ─────────────────────────────────────────────────────
    $logo_h = $al['logo_max_height'] ?? '80';
    $logo_w = $al['logo_max_width']  ?? '200';

    // ── Input ────────────────────────────────────────────────────
    $input_bg     = $al['input_bg']           ?? '#f6f7f7';
    $input_border = $al['input_border']       ?? '#8c8f94';
    $input_focus  = $al['input_border_focus'] ?? '#2271b1';
    $input_color  = $al['input_color']        ?? '#1d2327';
    $input_radius = $al['input_radius']       ?? '4';
    $label_color  = $al['label_color']        ?? '#1d2327';

    // ── Links & Text ─────────────────────────────────────────────
    $link_color       = $al['link_color']       ?? '#2271b1';
    $link_hover       = $al['link_color_hover'] ?? '#135e96';
    $text_color       = $al['text_color']       ?? '#1d2327';
    $desc_color       = $al['description_color'] ?? '#646970';

    // ── Messages ─────────────────────────────────────────────────
    $err_bg     = $al['error_bg']     ?? '#fcf0f1';
    $err_border = $al['error_border'] ?? '#d63638';
    $err_color  = $al['error_color']  ?? '#d63638';

    // ── Custom CSS ───────────────────────────────────────────────
    $custom_css = $al['custom_css'] ?? '';

    // ── Helpers: boş renk → transparent ─────────────────────────
    $c = function( $val, $default = 'transparent' ) {
        $val = trim( (string) $val );
        return ! empty( $val ) ? esc_attr( $val ) : $default;
    };

    // ── Enqueue admin-login.css (compile edilmiş SCSS) ────────────
    $css_path = get_bloginfo( 'template_directory' ) . '/static/css/admin-login.css';
    echo '<link rel="stylesheet" href="' . esc_url( $css_path ) . '" media="all">';

    // ── Inline: sadece CSS root variables ────────────────────────
    echo '<style id="sh-admin-login-vars">:root{';
    echo '--al-page-bg-color:'    . $c( $bg_color, '#1d2327' ) . ';';
    echo '--al-page-bg-image:'    . $bg_value . ';';
    echo '--al-page-bg-size:'     . esc_attr( $bg_size ) . ';';
    echo '--al-page-bg-pos:'      . esc_attr( $bg_pos ) . ';';
    echo '--al-page-bg-repeat:'   . esc_attr( $bg_repeat ) . ';';
    echo '--al-page-bg-att:'      . esc_attr( $bg_att ) . ';';
    echo '--al-box-bg:'           . $c( $box_bg ) . ';';
    echo '--al-box-radius:'       . esc_attr( $box_radius ) . 'px;';
    echo '--al-box-padding:'      . esc_attr( $box_padding ) . 'px;';
    echo '--al-box-width:'        . esc_attr( $box_width ) . 'px;';
    echo '--al-box-shadow:'       . $box_shadow . ';';
    echo '--al-logo-max-height:'  . esc_attr( $logo_h ) . 'px;';
    echo '--al-logo-max-width:'   . esc_attr( $logo_w ) . 'px;';
    echo '--al-label-color:'      . $c( $label_color, 'inherit' ) . ';';
    echo '--al-input-bg:'         . $c( $input_bg ) . ';';
    echo '--al-input-color:'      . $c( $input_color, 'inherit' ) . ';';
    echo '--al-input-border:'     . $c( $input_border ) . ';';
    echo '--al-input-border-focus:' . $c( $input_focus ) . ';';
    echo '--al-input-border-focus-shadow:' . $c( $input_focus ) . '44;';
    echo '--al-input-radius:'     . esc_attr( $input_radius ) . 'px;';
    echo '--al-btn-bg:'           . $c( $btn_bg ) . ';';
    echo '--al-btn-bg-hover:'     . $c( $btn_bg_hover ) . ';';
    echo '--al-btn-color:'        . $c( $btn_color, 'inherit' ) . ';';
    echo '--al-btn-color-hover:'  . $c( $btn_color_hover, 'inherit' ) . ';';
    echo '--al-btn-border:'       . $c( $btn_border ) . ';';
    echo '--al-btn-border-hover:' . $c( $btn_border_hover ) . ';';
    echo '--al-btn-radius:'       . esc_attr( $btn_radius ) . 'px;';
    echo '--al-btn-transform:'    . esc_attr( $btn_transform ) . ';';
    echo '--al-btn-weight:'       . esc_attr( $btn_weight ) . ';';
    echo '--al-link-color:'       . $c( $link_color, 'inherit' ) . ';';
    echo '--al-link-hover:'       . $c( $link_hover, 'inherit' ) . ';';
    echo '--al-text-color:'       . $c( $text_color, 'inherit' ) . ';';
    echo '--al-desc-color:'       . $c( $desc_color, 'inherit' ) . ';';
    echo '--al-error-bg:'         . $c( $err_bg ) . ';';
    echo '--al-error-border:'     . $c( $err_border ) . ';';
    echo '--al-error-color:'      . $c( $err_color, 'inherit' ) . ';';
    echo '}';

    // Custom CSS (kullanıcı eklerse)
    if ( ! empty( $custom_css ) ) {
        echo wp_strip_all_tags( $custom_css );
    }

    echo '</style>';
} );

// ─── Dashboard Cleanup ──────────────────────────────────────

add_action('admin_menu', function() {
    global $menu;
    $restricted = [__('Links'), __('Comments')];
    end($menu);
    while (prev($menu)) {
        $value = explode(' ', $menu[key($menu)][0]);
        if (in_array($value[0] ?? '', $restricted)) {
            unset($menu[key($menu)]);
        }
    }
});

add_action('wp_dashboard_setup', function() {
    $remove = [
        'dashboard_activity', 'dashboard_right_now', 'dashboard_quick_press',
        'dashboard_recent_comments', 'dashboard_incoming_links', 'dashboard_plugins',
        'dashboard_primary', 'dashboard_secondary', 'dashboard_recent_drafts',
    ];
    foreach ($remove as $id) {
        remove_meta_box($id, 'dashboard', in_array($id, ['dashboard_primary', 'dashboard_secondary', 'dashboard_recent_drafts']) ? 'side' : 'normal');
    }
});

add_action('wp_before_admin_bar_render', function() {
    global $wp_admin_bar;
    foreach (['wp-logo', 'about', 'wporg', 'documentation', 'support-forums', 'feedback', 'customize', 'updates', 'comments', 'new-content', 'w3tc', 'wpseo-menu'] as $id) {
        $wp_admin_bar->remove_menu($id);
    }
});

// ─── Content Filters ────────────────────────────────────────

add_filter('embed_oembed_html', fn($html) => '<div class="video-wrapper"><div class="video-wrap">' . $html . '</div></div>', 99, 1);

add_filter('the_content', function($content) {
    // Linked images'a class ekle
    $content = preg_replace(
        '/<a(.*?)href=(\'|")(.*?)\.(bmp|gif|jpeg|jpg|png)(\'|")(.*?)>/i',
        '<a$1class="fancy" href=$2$3.$4$5$6>',
        $content
    );
    // <p> wrapping'i img'lerden kaldır
    return preg_replace('/<p>\s*?(<a .*?><img.*?><\/a>|<img.*?>)?\s*<\/p>/s', '$1', $content);
});
