<?php
// =============================================================================
// ACTIONS ADMIN — Admin Hooks, SCSS Variables, Icon Picker
// =============================================================================
// @version 1.1.0
//
// @changelog
//   1.1.0 - 2026-08-21
//     - Remove: scss_variables_padding/color/image/array/font() ~60 satır kaldırıldı.
//              Yeni tema stilleri sistemine geçildi (scss-variables.json).
//     - Remove: dropdown_notification DEPRECATED comment satırı kaldırıldı.
//     - Remove: sh_clear_icon_cache geçici endpoint kaldırıldı (tek seferlik kullanım bitti).
//   1.0.0 - 2026-05-16
//     - Add: Initial admin actions file
// =============================================================================

use Timber\Timber;
use SaltHareket\Theme;

// ─── Block Editör REST Preload Optimizasyonu ─────────────────────────────────
// İçerik render eden (the_content → ACF blocks → Timber) ağır path'leri kaldır.
// Bu path'ler her editör açılışında server-side REST dispatch yapar — Timber/Polylang çalışır.
// Kaldırılan path'ler JS tarafından talep üzerine zaten alınır, preload şart değil.
add_filter( 'block_editor_rest_api_preload_paths', function( $paths ) {

    // Kayıtlı tüm post type'ların REST base'lerini dinamik olarak al
    $post_types = get_post_types( [ 'show_in_rest' => true ], 'objects' );
    $rest_bases = [];
    foreach ( $post_types as $pt ) {
        $rest_bases[] = ! empty( $pt->rest_base ) ? $pt->rest_base : $pt->name;
    }
    $bases_pattern = implode( '|', array_map( 'preg_quote', $rest_bases, array_fill( 0, count( $rest_bases ), '~' ) ) );

    $skip_patterns = [
        '~^/wp/v2/(' . $bases_pattern . ')/\d+\?context=edit~',  // post detail — the_content çalışır
        '~^/wp/v2/(' . $bases_pattern . ')/\d+/autosaves~',       // autosaves — gereksiz
        '~^/wp/v2/global-styles/\d+~',                             // global styles — yavaş
        '~^/wp/v2/templates/lookup~',                              // template lookup — yavaş
    ];

    $filtered = array_filter( $paths, function( $path ) use ( $skip_patterns ) {
        $url = is_array( $path ) ? $path[0] : $path;
        foreach ( $skip_patterns as $pattern ) {
            if ( preg_match( $pattern, $url ) ) return false;
        }
        return true;
    });

    return array_values( $filtered );
}, 20 );

add_action('wp_update_nav_menu', function($menu_id, $menu_data = []) {
    delete_transient('timber_menus');
}, 10, 2);

function set_default_image_alt_text($attachment_id) {
    // Mevcut alt text varsa bırak
    $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (!empty($alt_text)) {
        return;
    }

    $image_url = wp_get_attachment_url($attachment_id);
    $path_parts = pathinfo($image_url);
    $filename = $path_parts['filename'];

    // 1. Tire ve alt çizgileri boşlukla değiştir
    $alt_text = str_replace(['-', '_'], ' ', $filename);

    // 2. CamelCase / PascalCase ayırma
    $alt_text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $alt_text);

    // 3. İlk harfleri büyük yap
    $alt_text = ucwords($alt_text);

    // 4. Alt texti kaydet
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
}
add_action('add_attachment', 'set_default_image_alt_text');


// görsel kayudederken gorselin ortalama renk degerini ve bu rengin kontrastını kaydet
function extract_and_save_average_color($post_ID = 0) {
    if(!$post_ID){
        return;
    }
    $mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    $image_path = get_attached_file($post_ID);
    $mime_type = get_post_mime_type($post_ID);
    if (in_array($mime_type, $mime_types)) {
        $colors = get_image_average_color($image_path);
        if($colors){
            update_post_meta($post_ID, 'average_color', $colors["average_color"]);
            update_post_meta($post_ID, 'contrast_color', $colors["contrast_color"]);            
        }
    }
}
add_action('add_attachment', 'extract_and_save_average_color');






// Admin profil sayfasına Title alanını "Name" başlıklı alana ekleyelim
add_action('show_user_profile', 'add_title_field', 0);
add_action('edit_user_profile', 'add_title_field', 0);
function add_title_field($user) {
    ?>
    <div class="postbox">
        <div class="postbox-header"><h2 id="user-title">User Title</h2></div>
        <table class="form-table m-0">
            <tr>
                <th><label for="title"><?php _e('Title'); ?></label></th>
                <td>
                    <input type="text" name="title" id="title" value="<?php echo esc_attr(get_user_meta($user->ID, 'title', true)); ?>" class="regular-text" /><br />
                </td>
            </tr>
        </table>
    </div>
    <?php
}
// Title alanının kaydedilmesi
add_action('personal_options_update', 'save_title_field');
add_action('edit_user_profile_update', 'save_title_field');
function save_title_field($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'title', sanitize_text_field($_POST['title'] ?? ''));
    }
}




function scss_variables_boolean($value=""){
    if(empty($value)){
        $value = "false";
    }else{
        $value = "true";
    }
    return $value;
}

function wp_scss_set_variables(){
    $host_url = get_stylesheet_directory_uri();

    $variables = [
        "woocommerce"             => class_exists("WooCommerce") ? "true" : "false",
        "yobro"                   => class_exists("Redq_YoBro") ? "true" : "false",
        "mapplic"                 => class_exists("Mapplic") ? "true" : "false",
        "newsletter"              => class_exists("Newsletter") ? "true" : "false",
        "yasr"                    => function_exists("yasr_fs") ? "true" : "false",
        "apss"                    => class_exists("APSS_Class") ? "true" : "false",
        "cf7"                     => class_exists("WPCF7") ? "true" : "false",
        "enable_multilanguage"    => boolval(ENABLE_MULTILANGUAGE) ? "true" : "false",
        "enable_reactions"        => boolval(ENABLE_REACTIONS) ? "true" : "false",
        "enable_cart"             => boolval(ENABLE_CART) ? "true" : "false",
        "enable_filters"          => boolval(ENABLE_FILTERS) ? "true" : "false",
        "enable_membership"       => boolval(ENABLE_MEMBERSHIP) ? "true" : "false",
        "enable_chat"             => boolval(ENABLE_CHAT) ? "true" : "false",
        "enable_notifications"    => boolval(ENABLE_NOTIFICATIONS) ? "true" : "false",
        "enable_sms_notifications"=> boolval(ENABLE_NOTIFICATIONS) && boolval(ENABLE_SMS_NOTIFICATIONS) ? "true" : "false",
        "search_history"          => boolval(ENABLE_SEARCH_HISTORY) ? "true" : "false",
        "logo"                    => "'" . ( class_exists('\\SaltHareket\\SaltConfig') ? ( (string) ( \SaltHareket\SaltConfig::get('options_logo')['url'] ?? '' ) ) : ( function_exists('get_field') ? ( (string) ( get_field("logo", "option")['url'] ?? '' ) ) : '' ) ) . "'",
        "host_url"                => "'" . $host_url . "'",
        "node_modules_path"       => '"' . str_replace('\\', '/', NODE_MODULES_PATH) . '"',
        "theme_static_path"       => '"' . str_replace('\\', '/', THEME_STATIC_PATH) . '"',
        "sh_static_path"          => '"' . str_replace('\\', '/', SH_STATIC_PATH) . '"',
        "svg_path"                => "'" . rtrim( STATIC_URL, '/' ) . "/svg'",
    ];

    if (file_exists(get_stylesheet_directory() . "/static/js/js_files_all.json")) {
        $plugins = file_get_contents(get_stylesheet_directory() . "/static/js/js_files_all.json");
        if ($plugins) {
            $variables["plugins"] = str_replace(["[", "]"], "", $plugins);
        }
    }

    // ── Yeni tema stilleri sistemi ──────────────────────────────────────────
    // Kayıt sırasında üretilen scss-variables.json'dan oku (get_theme_styles() çağrısı YOK)
    $scss_json = get_template_directory() . '/theme/static/data/theme-styles/scss-variables.json';
    if (file_exists($scss_json)) {
        $saved = json_decode(file_get_contents($scss_json), true);
        if (!empty($saved) && is_array($saved)) {
            $variables = array_merge($variables, $saved);
        }
    } elseif (class_exists('Theme_Styles')) {
        // JSON henüz oluşturulmamış — anlık üret (ilk kurulum)
        $data      = Theme_Styles::init()->get_data();
        if (!empty($data)) {
            $gen  = new Theme_Styles_CSS_Generator();
            $gen->generate($data);
            $scss_vars = $gen->get_scss_variables();
            if (!empty($scss_vars)) {
                $variables = array_merge($variables, $scss_vars);
            }
        }
    }

    return $variables;
}
add_filter("wp_scss_variables", "wp_scss_set_variables");


add_filter('robots_txt', function($output, $public) {
    // Sadece site halka açıksa (Arama motoru görünürlüğü açıkken) çalışsın
    // İstersen bu kontrolü kaldırıp direkt yazdırabilirsin
    
    $output = "User-agent: *\n";
    $output .= "Disallow: /wp-admin/\n"; // Admin panelini kapatalım standart olarak
    $output .= "Allow: /wp-admin/admin-ajax.php\n\n";

    // Yoast SEO Sitemap kontrolü
    if ( function_exists('wpseo_sitemap_url') ) {
        $output .= 'Sitemap: ' . wpseo_sitemap_url() . "\n";
    } else {
        $output .= 'Sitemap: ' . home_url('/sitemap_index.xml') . "\n";
    }

    // Ekstra sitemap dosyaların
    $extra_sitemaps = ['llms.txt', 'ssms.txt'];
    foreach ($extra_sitemaps as $file) {
        if ( file_exists(ABSPATH . $file) ) {
            $output .= 'Sitemap: ' . home_url('/' . $file) . "\n";
        }
    }

    return $output;
}, 20, 2);







// 1. WP-CRON'u AJAX VE AKTİF ADMİN SIRASINDA DURDUR
add_action('init', function() {
    
    // Ajax isteği mi?
    $is_ajax = (defined('DOING_AJAX') && DOING_AJAX) || (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
    
    // Admin panelinde miyiz?
    $is_admin = is_admin();

    // Eğer Ajax yapılıyorsa veya Admin'de işlem varsa Cron'u bu sayfa yüklemesinde deaktif et
    if ($is_ajax || $is_admin) {
        remove_action('init', 'wp_cron'); // WP'nin default cron tetikleyicisini siliyoruz
    }
}, 1);






if(ENABLE_ECOMMERCE){
    // add order received page select
    add_filter('woocommerce_get_settings_advanced', 'add_order_received_page_setting', 10, 2);
    function add_order_received_page_setting($settings, $current_section) {
        if ($current_section !== '') return $settings;
        $new_settings = [];
        foreach ($settings as $setting) {
            $new_settings[] = $setting;
            if (isset($setting['id']) && $setting['id'] === 'woocommerce_checkout_page_id') {
                $new_settings[] = [
                    'title'    => __('Order Received Page', 'your-textdomain'),
                    'desc'     => __('This page will be used as the custom "Order Received" page.', TEXT_DOMAIN),
                    'id'       => 'woocommerce_order_received_page_id',
                    'type'     => 'single_select_page_with_search',
                    'default'  => '',
                    'class'    => 'wc-page-search',
                    'css'      => 'min-width:300px;',
                    'desc_tip' => true,
                    'autoload' => false,
                ];
            }
        }
        return $new_settings;
    }

}



// ─── Maintenance App Cache Flush (WP Rocket) ─────────────────────────────────
add_action( 'sh/maintenance/saved', function ( array $settings ) {
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }
} );




// Plugin Activation / Deactivation
add_filter('activated_plugin', 'admin_plugins_activated', 10, 2);
add_filter('deactivated_plugin', 'admin_plugins_deactivated', 10, 2);
function admin_plugins_activated($plugin, $network_activation) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        $page_on_front = get_option('page_on_front');
        set_my_account_page(true);
        $woo_pages = [
            ['endpoint' => 'shop',           'title' => 'Shop',     'content' => '',                       'template' => 'template-shop.php'],
            ['endpoint' => 'cart',           'title' => 'Cart',     'content' => '<!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart is-loading"></div><!-- /wp:woocommerce/cart -->',     'template' => 'template-cart.php'],
            ['endpoint' => 'checkout',       'title' => 'Checkout', 'content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout is-loading"></div><!-- /wp:woocommerce/checkout -->', 'template' => 'template-checkout.php'],
            ['endpoint' => 'refund_returns', 'title' => 'Refund',   'content' => '',                       'template' => ''],
            ['endpoint' => 'order_received', 'title' => 'Order OK', 'content' => '',                       'template' => ''],
        ];
        foreach ($woo_pages as $p) {
            $pid = get_option('woocommerce_' . $p['endpoint'] . '_page_id');
            if (get_post_status($pid) === false) {
                $args = ['post_title' => $p['title'], 'post_content' => $p['content'], 'post_status' => 'publish', 'post_type' => 'page'];
                if (!empty($p['template'])) $args['page_template'] = $p['template'];
                $pid = wp_insert_post($args);
                update_option('woocommerce_' . $p['endpoint'] . '_page_id', $pid);
                if (empty($page_on_front) && $p['endpoint'] === 'shop') {
                    update_option('page_on_front', $pid);
                    update_option('show_on_front', 'page');
                }
            }
        }
        if (function_exists('acf_development_methods_settings')) acf_development_methods_settings(1);
    }
    if ($plugin === 'underconstruction/underConstruction.php') {
        // Maintenance App artık kendi sayfasını yönetiyor — plugin page oluşturma kaldırıldı
        // Maintenance sayfası admin'den seçilir: WP Admin → Maintenance
    }
}
function admin_plugins_deactivated($plugin, $network_activation) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        set_my_account_page(false);
        foreach (['shop', 'cart', 'checkout', 'refund_returns', 'order_received'] as $ep) {
            wp_delete_post(wc_get_page_id($ep), true);
        }
        if (function_exists('acf_development_methods_settings')) acf_development_methods_settings(1);
    }
    if ($plugin === 'underconstruction/underConstruction.php') {
        // Maintenance App artık bağımsız çalışıyor — plugin sayfası silme kaldırıldı
    }
}





// My Account Page Management
function create_my_account_page() {
    $is_woo = class_exists('WooCommerce');
    $shortcode = $is_woo ? '[woocommerce_my_account]' : '[salt_my_account]';
    $content = salt_wrap_shortcode_for_editor($shortcode);

    $pid = wp_insert_post([
        'post_title'    => 'My Account',
        'post_content'  => $content,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'page_template' => 'template-my-account.php'
    ]);

    if (!is_wp_error($pid)) {
        update_option('woocommerce_myaccount_page_id', $pid);
        update_option('options_myaccount_page_id', $pid);
    }
    return $pid;
}

/**
 * Shortcode'u block editor veya classic editor formatina sarar.
 */
function salt_wrap_shortcode_for_editor($shortcode) {
    // Block editor (Gutenberg) aktif mi?
    $use_blocks = function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type('page');
    if ($use_blocks) {
        return '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->';
    }
    return $shortcode;
}

/**
 * My Account sayfasinin shortcode'unu guncelle.
 * @param bool $ecommerce WooCommerce aktif mi
 */
function set_my_account_page($ecommerce = true) {
    $pid = get_option('woocommerce_myaccount_page_id');
    if (!$pid || !get_post($pid)) {
        $pid = get_option('options_myaccount_page_id');
    }

    if (!$pid || !get_post($pid)) {
        create_my_account_page();
        return;
    }

    $shortcode = $ecommerce ? '[woocommerce_my_account]' : '[salt_my_account]';
    $current = get_post_field('post_content', $pid);
    $current_template = get_page_template_slug($pid);
    $needs_update = false;
    $update_args = ['ID' => $pid];

    // Shortcode kontrolu
    if (strpos($current, $shortcode) === false) {
        $update_args['post_content'] = salt_wrap_shortcode_for_editor($shortcode);
        $needs_update = true;
    }

    // Template kontrolu
    if ($current_template !== 'template-my-account.php') {
        $update_args['page_template'] = 'template-my-account.php';
        $needs_update = true;
    }

    if ($needs_update) {
        wp_update_post($update_args);
    }

    // Her iki option'i da sync et
    update_option('woocommerce_myaccount_page_id', $pid);
    update_option('options_myaccount_page_id', $pid);
}


/**
 * Shortcode gerektiren ozel sayfalari kaydederken shortcode kontrolu yap.
 * Eger sayfa icinde olmasi gereken shortcode yoksa ekle.
 */
add_action('save_post_page', 'salt_enforce_page_shortcodes', 20, 3);
function salt_enforce_page_shortcodes($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if ($post->post_status === 'trash') return;

    $is_woo = class_exists('WooCommerce');

    // ── Sayfa → [shortcode/content, template] haritası ──────────────────────
    $page_map = [];

    // My Account
    $myaccount_id = (int) get_option('woocommerce_myaccount_page_id');
    if (!$myaccount_id) $myaccount_id = (int) get_option('options_myaccount_page_id');
    if ($myaccount_id) {
        $page_map[$myaccount_id] = [
            'content'  => $is_woo ? '[woocommerce_my_account]' : '[salt_my_account]',
            'template' => 'template-my-account.php',
        ];
    }

    // WooCommerce sayfaları
    if ($is_woo) {
        $wc_pages = [
            'cart'     => [
                'content'  => '<!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart is-loading"></div><!-- /wp:woocommerce/cart -->',
                'template' => 'template-cart.php',
            ],
            'checkout' => [
                'content'  => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout is-loading"></div><!-- /wp:woocommerce/checkout -->',
                'template' => 'template-checkout.php',
            ],
            'shop'     => [
                'content'  => '',
                'template' => 'template-shop.php',
            ],
        ];
        foreach ($wc_pages as $endpoint => $cfg) {
            $pid = (int) get_option('woocommerce_' . $endpoint . '_page_id');
            if ($pid) $page_map[$pid] = $cfg;
        }
    }

    if (!isset($page_map[$post_id])) return;

    $cfg              = $page_map[$post_id];
    $required_content = $cfg['content'];
    $required_template= $cfg['template'];
    $content          = $post->post_content;
    $current_template = get_page_template_slug($post_id);
    $needs_update     = false;
    $update_args      = ['ID' => $post_id];

    // ── İçerik kontrolü ──────────────────────────────────────────────────────
    if (!empty($required_content)) {
        $content_ok = false;
        if (strpos($required_content, 'wp:woocommerce/') !== false) {
            if (preg_match('/wp:woocommerce\/(\w+)/', $required_content, $m)) {
                $block_name = $m[1];
                $content_ok = strpos($content, 'wp:woocommerce/' . $block_name) !== false
                           || strpos($content, '[woocommerce_' . $block_name . ']') !== false;
            }
        } else {
            if (preg_match('/\[(\w+)\]/', $required_content, $m)) {
                $content_ok = strpos($content, '[' . $m[1] . ']') !== false;
            }
        }
        if (!$content_ok) {
            $update_args['post_content'] = empty(trim($content))
                ? $required_content
                : $required_content . "\n" . $content;
            $needs_update = true;
        }
    }

    // ── Template kontrolü ────────────────────────────────────────────────────
    if (!empty($required_template) && $current_template !== $required_template) {
        // Template dosyası tema'da var mı?
        $template_file = get_template_directory() . '/' . $required_template;
        if (file_exists($template_file)) {
            $update_args['page_template'] = $required_template;
            $needs_update = true;
        }
    }

    if (!$needs_update) return;

    remove_action('save_post_page', 'salt_enforce_page_shortcodes', 20);
    wp_update_post($update_args);
    add_action('save_post_page', 'salt_enforce_page_shortcodes', 20, 3);
}


// ─── SH Icon Picker — AJAX Endpoints ─────────────────────────────────────────

/**
 * FA icon listesi — admin icon picker için
 * Request: { action: 'sh_fa_icon_list', style: 'solid', nonce: '...' }
 * Response: { icons: string[], style: string }
 */
add_action( 'wp_ajax_sh_fa_icon_list', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    // 2MB+ JS dosyası parse edebilmek için limit artır
    @ini_set( 'memory_limit', '256M' );
    @set_time_limit( 60 );

    $style     = sanitize_key( $_POST['style'] ?? 'solid' );
    $cache_key = 'sh_fa_icon_names_v2_' . $style;
    $cached    = get_transient( $cache_key );

    if ( is_array( $cached ) && count( $cached ) > 0 ) {
        wp_send_json_success( [ 'icons' => $cached, 'style' => $style ] );
        return;
    }

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        if ( ! defined( 'SH_STATIC_PATH' ) ) define( 'SH_STATIC_PATH', get_template_directory() . '/vendor/salthareket/theme/src/static/' );
        if ( ! defined( 'STATIC_PATH' ) ) define( 'STATIC_PATH', get_template_directory() . '/static/' );
        require_once __DIR__ . '/../classes/class.fa-icon-manager.php';
    }

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        wp_send_json_error( 'FaIconManager not loaded' );
        return;
    }

    $names = \SaltHareket\FaIconManager::getIconNames( $style );
    if ( empty( $names ) ) {
        wp_send_json_error( 'No icons found for style: ' . $style );
        return;
    }

    set_transient( $cache_key, $names, 30 * DAY_IN_SECONDS );
    wp_send_json_success( [ 'icons' => $names, 'style' => $style ] );
} );

/**
 * Tek ikon SVG render — icon picker önizleme için
 * Request: { action: 'sh_fa_icon_svg', style: 'solid', name: 'heart', nonce: '...' }
 * Response: SVG string
 */
add_action( 'wp_ajax_sh_fa_icon_svg', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $style = sanitize_key( $_POST['style'] ?? 'solid' );
    $name  = sanitize_key( $_POST['name']  ?? '' );

    if ( ! $name ) { wp_send_json_error( 'No name' ); return; }

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        if ( ! defined( 'SH_STATIC_PATH' ) ) define( 'SH_STATIC_PATH', get_template_directory() . '/vendor/salthareket/theme/src/static/' );
        if ( ! defined( 'STATIC_PATH' ) ) define( 'STATIC_PATH', get_template_directory() . '/static/' );
        require_once __DIR__ . '/../classes/class.fa-icon-manager.php';
    }

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) { wp_send_json_error( 'FaIconManager not loaded' ); return; }

    wp_send_json_success( \SaltHareket\FaIconManager::extractFromJs( $name, $style ) );
} );

/**
 * Tüm admin sayfalarında icon picker nonce + config'i inject et
 */
add_action( 'admin_head', function () {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;

    $nonce = wp_create_nonce( 'sh_icon_picker_nonce' );
    echo '<script>window.shIconPickerConfig={ajaxUrl:' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',nonce:' . wp_json_encode( $nonce ) . '};</script>' . "\n";
} );


/**
 * Toplu SVG render — icon picker dropdown için
 * Request: { action: 'sh_fa_icon_svg_bulk', style: 'solid', names: ['heart','star',...], nonce: '...' }
 * Response: { heart: '<svg>...</svg>', star: '<svg>...</svg>' }
 */
add_action( 'wp_ajax_sh_fa_icon_svg_bulk', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $style = sanitize_key( $_POST['style'] ?? 'solid' );
    $names = array_map( 'sanitize_key', (array) ( $_POST['names'] ?? [] ) );
    $names = array_slice( $names, 0, 100 );

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        if ( ! defined( 'SH_STATIC_PATH' ) ) define( 'SH_STATIC_PATH', get_template_directory() . '/vendor/salthareket/theme/src/static/' );
        if ( ! defined( 'STATIC_PATH' ) ) define( 'STATIC_PATH', get_template_directory() . '/static/' );
        require_once __DIR__ . '/../classes/class.fa-icon-manager.php';
    }

    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) { wp_send_json_error( 'FaIconManager not loaded' ); return; }

    $result = [];
    foreach ( $names as $name ) {
        if ( $name ) $result[ $name ] = \SaltHareket\FaIconManager::extractFromJs( $name, $style );
    }
    wp_send_json_success( $result );
} );


