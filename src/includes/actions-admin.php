<?php

use Timber\Timber;
use SaltHareket\Theme;

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






function scss_variables_padding($padding=""){
    $padding = trim($padding);
    $padding = str_replace("px", " ", $padding);
    $padding = str_replace("  ", " ", $padding);
    $padding = explode(" ", $padding);
    $padding = trim(implode("px ", $padding))."px";
    $padding = str_replace("pxpx", "px", $padding);
    return $padding;
}
function scss_variables_color($value=""){
    if(empty($value)){
        $value = "transparent";
    }
    return $value;
}
function scss_variables_boolean($value=""){
    if(empty($value)){
        $value = "false";
    }else{
        $value = "true";
    }
    return $value;
}
function scss_variables_image($value=""){
    if(empty($value)){
        $value = "none";
    }else{
        $value = "url(".$value.")";
    }
    return $value;
}
function scss_variables_array($array=array()){
    $temp = array();
    foreach($array as $key => $item){
        $temp[] = $key."---".$item;
    }
    $temp = implode("___", $temp);
    $temp = preg_replace('/\s+/', '', $temp);
    return $temp;
}
function scss_variables_font($font = ""){
    if (empty($font)) return "";

    // önce pipe karakterlerini temizle
    $font = str_replace("|", "", $font);

    // Eğer virgül var ise family + fallback ayrımı yap
    if (strpos($font, ',') !== false) {
        $parts = explode(',', $font, 2);
        $family = trim($parts[0]); // family adı
        $fallback = isset($parts[1]) ? trim($parts[1]) : '';

        // family zaten tırnaklı değilse tırnakla sar
        $family = trim($family, '"\''); // varsa fazladan tırnakları temizle
        $family = '"' . $family . '"';

        // fallback varsa ekle
        if ($fallback !== '') {
            return $family . ', ' . $fallback;
        } else {
            return $family;
        }
    } else {
        // tek family varsa
        $font = trim($font, '"\''); // varsa fazladan tırnakları temizle
        return '"' . $font . '"';
    }
}
function wp_scss_set_variables(){
    $host_url = get_stylesheet_directory_uri();
    /*if(ENABLE_PUBLISH){
        if(function_exists("WPH_activated")){
                $wph_settings = get_option("wph_settings");
                $new_theme_path = "";
                if(isset($wph_settings["module_settings"]["new_theme_path"])){
                    $new_theme_path = $wph_settings["module_settings"]["new_theme_path"];
                }
                if(!empty($new_theme_path)){
                    $host_url = PUBLISH_URL."/".$new_theme_path;
                }
        }else{
            $host_url = str_replace(get_host_url(), PUBLISH_URL, $host_url);
        }
    }*/

    $variables = [
        "woocommerce" => class_exists("WooCommerce") ? "true" : "false",
        "yobro" => class_exists("Redq_YoBro") ? "true" : "false",
        "mapplic" => class_exists("Mapplic") ? "true" : "false",
        "newsletter" => class_exists("Newsletter") ? "true" : "false",
        "yasr" => function_exists("yasr_fs") ? "true" : "false",
        "apss" => class_exists("APSS_Class") ? "true" : "false",
        "cf7" => class_exists("WPCF7") ? "true" : "false",
        "enable_multilanguage" => boolval(ENABLE_MULTILANGUAGE) ? "true" : "false",
        "enable_reactions" => boolval(ENABLE_REACTIONS) ? "true" : "false",
        "enable_cart" => boolval(ENABLE_CART) ? "true" : "false",
        "enable_filters" => boolval(ENABLE_FILTERS) ? "true" : "false",
        "enable_membership" => boolval(ENABLE_MEMBERSHIP) ? "true" : "false",
        "enable_chat" => boolval(ENABLE_CHAT) ? "true" : "false",
        "enable_notifications" => boolval(ENABLE_NOTIFICATIONS) ? "true" : "false",
        "enable_sms_notifications" => boolval(ENABLE_NOTIFICATIONS) && boolval(ENABLE_SMS_NOTIFICATIONS) ? "true" : "false",
        "search_history" => boolval(ENABLE_SEARCH_HISTORY) ? "true" : "false",
        "logo" => "'" . get_field("logo", "option") . "'",
        "dropdown_notification" => boolval(header_has_dropdown()) ? "true" : "false",
        "host_url" => "'" . $host_url . "'",
        "node_modules_path" =>  '"' . str_replace('\\', '/', NODE_MODULES_PATH) . '"',
        "theme_static_path" =>  '"' . str_replace('\\', '/', THEME_STATIC_PATH) . '"',
        "sh_static_path" =>  '"' . str_replace('\\', '/', SH_STATIC_PATH) . '"'
    ];
    
    if(file_exists(get_stylesheet_directory() ."/static/js/js_files_all.json")){
        $plugins = file_get_contents(get_stylesheet_directory() ."/static/js/js_files_all.json");
        if($plugins){
           $variables["plugins"] = str_replace(array("[", "]"), "", $plugins);
        }        
    }

    $variables = get_theme_styles($variables);

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






// Under Construction Cache Flush
if (class_exists('underConstruction')) {
    add_filter('option_underConstructionActivationStatus', function($status) {
        if ($status == '1' && function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        return $status;
    });
}

// ACF Setting Change Hooks
foreach (['enable_membership', 'enable_membership_activation', 'enable_chat', 'enable_notifications', 'enable_reactions'] as $fn) {
    add_filter("acf/update_value/name={$fn}", 'acf_general_settings_rewrite', 10, 4);
}
function acf_general_settings_rewrite($value, $post_id, $field, $original) {
    return $value;
}

add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_enable_membership', 10, 4);
function acf_general_settings_enable_membership($value, $post_id, $field, $original) {
    if ($value) {
        create_my_account_page();
    } else {
        $pid = get_option('woocommerce_myaccount_page_id');
        if ($pid) wp_delete_post($pid, true);
    }
    return $value;
}

add_filter('acf/update_value/name=enable_location_db', 'acf_general_settings_enable_location_db', 10, 4);
function acf_general_settings_enable_location_db($value, $post_id, $field, $original) {
    $ip2country = get_field('enable_ip2country', 'option');
    $settings   = get_field('ip2country_settings', 'option');
    return ($ip2country && $settings === 'db') ? 1 : $value;
}

add_filter('acf/update_value/name=enable_registration', 'acf_general_settings_registration', 10, 4);
function acf_general_settings_registration($value, $post_id, $field, $original) {
    update_option('users_can_register', $value);
    update_option('woocommerce_enable_myaccount_registration', $value ? 'yes' : 'no');
    return $value;
}





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
        $pid = wp_insert_post(['post_title' => 'Under Construction', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page', 'page_template' => 'under-construction.php']);
        update_option('under-construction-page', $pid);
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
        $pid = (int) get_option('under-construction-page');
        if ($pid) { wp_delete_post($pid, true); delete_option('under-construction-page'); }
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

add_filter('acf/update_value/name=enable_membership', 'check_my_account_page', 10, 4);
function check_my_account_page($value, $post_id, $field, $original) {
    if ($field['name'] !== 'enable_membership' || !$value) return $value;
    set_my_account_page(class_exists('WooCommerce'));
    if (!class_exists('SaltHareket\MethodClass')) require_once SH_CLASSES_PATH . 'class.methods.php';
    $m = new SaltHareket\MethodClass();
    $m->createFiles(false);
    $m->createFiles(false, 'admin');
    if (function_exists('redirect_notice')) redirect_notice('Frontend/Backend methods compiled!', 'success');
    return $value;
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
