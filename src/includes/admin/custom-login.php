<?php

/**
 * Custom Admin Login Page & Dashboard Cleanup
 */

// ─── Login Page Branding ────────────────────────────────────

add_filter('login_headerurl', fn() => home_url());

add_filter('login_headertext', function() {
    $logo = get_field('admin_logo', 'option');
    $src  = $logo ?: SH_STATIC_URL . 'img/logo-login.png';
    return '<img src="' . esc_url($src) . '" class="img-fluid" alt="Login">';
});

add_action('login_head', function() {
    $bg     = get_field('admin_bg', 'option') ?: [];
    $text   = get_field('admin_text', 'option') ?: [];
    $link   = get_field('admin_link', 'option') ?: [];
    $button = get_field('admin_button', 'option') ?: [];

    $v = fn($arr, $key, $default) => esc_attr($arr[$key] ?? $default);

    echo '<link rel="stylesheet" href="' . esc_url(get_bloginfo('template_directory') . '/static/css/admin-login.css') . '" media="all">';
    echo '<style>
    body.login {
        background-color: ' . $v($bg, 'color', '#111') . ' !important;
        background-image: url(' . esc_url($bg['image'] ?? SH_STATIC_URL . 'img/bg-login-admin.jpg') . ') !important;
        background-position: ' . $v($bg, 'position_hr', 'center') . ' ' . $v($bg, 'position_vr', 'center') . ' !important;
        background-size: ' . $v($bg, 'size', 'auto') . ' !important;
        background-repeat: ' . $v($bg, 'repeat', 'no-repeat') . ' !important;
        display: flex; justify-content: center; align-items: center; flex-direction: column;
    }
    .login form label, .login h1.admin-email__heading, p.admin-email__details, .login .description {
        color: ' . $v($text, 'color', '#fff') . ' !important;
    }
    .login #nav a, .login #backtoblog a { color: ' . $v($link, 'color', '#fff') . ' !important; }
    .login #nav a:hover, .login #backtoblog a:hover { color: ' . $v($link, 'color_hover', '#fff') . ' !important; }
    .button.button-primary {
        background-color: ' . $v($button, 'bg', 'yellow') . ' !important;
        border-color: ' . $v($button, 'border', '#111') . ' !important;
        color: ' . $v($button, 'color', '#111') . ' !important;
    }
    .button.button-primary:hover {
        background-color: ' . $v($button, 'bg_hover', 'yellow') . ' !important;
        border-color: ' . $v($button, 'border_hover', '#111') . ' !important;
        color: ' . $v($button, 'color_hover', '#111') . ' !important;
    }
    .button:disabled { opacity: .5; pointer-events: none; }
    </style>';
});

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
