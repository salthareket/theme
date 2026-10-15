<?php

use Timber\Timber;
use Timber\Loader;

use SaltHareket\Theme;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Property\Selector;

//acf json save & load folders
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
    $path = get_stylesheet_directory() . '/acf-json';
    return $path;  
}
add_filter('acf/settings/load_json', 'my_acf_json_load_point');
function my_acf_json_load_point( $paths ) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
}

add_filter('acf/fields/wysiwyg/toolbars', function($toolbars) {
    // Bu sadece toolbarları değil, init ayarlarını tetiklemek için bir kancadır
    return $toolbars;
});

// Tüm ACF Wysiwyg alanlarını varsayılan olarak Lazy Load yap
add_filter('acf/load_field/type=wysiwyg', function($field) {
    $field['delay'] = 1; // Bu değer 1 (True) olduğunda Lazy Load aktif olur
    return $field;
});

/**
 * CSS Shorthand değerlerini (Padding/Margin) parse eder.
 * @param string $value Örn: "20px", "20px 10px", "20px 10px 5px 2px"
 * @param string $direction "top", "right", "bottom", "left"
 * @return string
 */
function css_parse_spacing_value($value, $direction = 'top') {
    $value = trim($value);
    if (empty($value)) {
        return '0px';
    }
    $parts = array_values(array_filter(explode(' ', $value), function($val) {
        return $val !== '';
    }));

    $count = count($parts);
    switch ($count) {
        case 1:
            return $parts[0];

        case 2:
            if ($direction === 'top' || $direction === 'bottom') return $parts[0];
            return $parts[1]; // right veya left

        case 3:
            if ($direction === 'top') return $parts[0];
            if ($direction === 'right' || $direction === 'left') return $parts[1];
            return $parts[2]; // bottom

        case 4:
            $map = ['top' => 0, 'right' => 1, 'bottom' => 2, 'left' => 3];
            return $parts[$map[$direction]];

        default:
            return $parts[0];
    }
}

function save_theme_styles_header_themes($header){
        $header_themes = $header["themes"];
        if($header_themes){
            $dom_elements = ["body", "header"];
            $code = "";
            $header_theme_scss = SH_STATIC_PATH . "data/header-theme.scss";
            if (file_exists($header_theme_scss)) {
                $code .= file_get_contents($header_theme_scss);

                foreach($header["themes"] as $theme){
                    $selector = in_array($theme["class"], $dom_elements)?$theme["class"]:".".$theme["class"];
                    $z_index = empty($theme["z-index"])?"null":$theme["z-index"];

                    $default = $theme["default"];
                    $color = empty($default["color"])?"null":$default["color"];
                    $color_active = empty($default["color_active"])?"null":$default["color_active"];
                    $bg_color = empty($default["bg_color"])?"null":$default["bg_color"];
                    $logo = empty($default["logo"])?"revert-layer":$default["logo"];
                    
                    $affix = $theme["affix"];
                    $color_affix = empty($affix["color"])?"null":$affix["color"];
                    $color_active_affix = empty($affix["color_active"])?"null":$affix["color_active"];
                    $bg_color_affix = empty($affix["bg_color"])?"null":$affix["bg_color"];
                    $logo_affix = empty($affix["logo"])?"revert-layer":$affix["logo"];
                    $btn_reverse = scss_variables_boolean($affix["btn_reverse"]);

                    //$code .= $selector.":not(.menu-open):not(.menu-show-header){\n";
                     $code .= $selector."{\n";
                        $code .= "@include headerTheme(";
                            $code .= $color.",";
                            $code .= $color_active.",";
                            $code .= $bg_color.",";
                            $code .= $logo.",";
                            $code .= $color_affix.",";
                            $code .= $color_active_affix.",";
                            $code .= $bg_color_affix.",";
                            $code .= $logo_affix.",";
                            $code .= $z_index.",";
                            $code .= $btn_reverse;
                        $code .= ");\n";
                    $code .= "}\n";
                }
                $wpscss_compiler = new \SaltHareket\AssetManager\SCSSCompiler();
                $code = $wpscss_compiler->compile_string($code);
                ////error_log($code);
                //error_log(print_r($wpscss_compiler->get_compile_errors(), true));
            }
            return $code;
        }
}

function acf_save_menu_safelist_classes($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // if (get_post_type($post_id) !== 'your_post_type') return;
    $menu_classes = array();
    if (have_rows('header_tools_start', $post_id)) {
        while (have_rows('header_tools_start', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_center', $post_id)) {
        while (have_rows('header_tools_center', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_end', $post_id)) {
        while (have_rows('header_tools_end', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('theme_styles', $post_id)) {
        $theme_styles = get_field("theme_styles", $post_id);
        if($theme_styles){
            $header_themes = $theme_styles["header"]["themes"];
            if($header_themes){
                foreach($header_themes as $theme){
                    $class = $theme["class"];
                    if(!in_array($class, ["body", "html"])){
                        $classes = array_map('trim', array_filter(explode(' ', $class)));
                        //$menu_classes[] = $classes;
                        $menu_classes[] = $classes;                            
                    }
                }
            }
        }
    }


    // ACF block'larındaki class'ları kontrol et
    $blocks = parse_blocks(get_post_field('post_content', $post_id));
    if($blocks){
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'acf/social-media') {
                if (isset($block['attrs']['data'])) {
                    $block_data = $block['attrs']['data'];
                    if($block_data["add_accounts_from"] == "custom"){
                        $account_count = $block_data['social_accounts_custom'];
                        for ($i = 0; $i < $account_count; $i++) {
                            $account_name = isset($block_data["social_accounts_custom_{$i}_name"]) ? $block_data["social_accounts_custom_{$i}_name"] : '';
                            if ($account_name) {
                                $class = "fa-".$account_name;
                                $classes = array_map('trim', array_filter(explode(' ', $class)));
                                $menu_classes[] = $classes;
                            }
                        }
                    }
                }
                break;
            }
        }
    }

    if($menu_classes){
        $merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = array_values($merged_menu_classes);
        update_dynamic_css_whitelist($json_data);

        /*$merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = json_encode(['dynamicSafelist' => array_values($merged_menu_classes)], JSON_PRETTY_PRINT);
        $file_path = HEME_STATIC_PATH . 'data/css_safelist.json';
        file_put_contents($file_path, $json_data);*/
    }
}
add_action('acf/save_post', 'acf_save_menu_safelist_classes', 20);



add_filter('acf/validate_value', function ($valid, $value, $field, $input) {
    if (!$valid) return $valid;
    if ($field['type'] !== 'flexible_content') {
        return $valid;
    }
    if (!isset($field['wrapper']['class']) || strpos($field['wrapper']['class'], 'once') === false) {
        return $valid;
    }
    $usedLayouts = [];
    foreach ($value as $row) {
        $layout = $row['acf_fc_layout'];
        if (isset($usedLayouts[$layout])) {
            return 'Yalnızca bir adet "<strong>'.$layout.'<strong>" ekleyebilirsin!';
        }
        $usedLayouts[$layout] = true;
    }
    return $valid;
}, 10, 4);


add_filter('acf/load_value/type=file', function($value, $post_id, $field) {
    if (!empty($value) && get_post_status($value) === false) {
        return null; // Eğer dosya yoksa alanı boş yap
    }
    return $value; // Eğer dosya varsa, normal değerini döndür
}, 10, 3);


function on_acf_post_pagination_saved($post_id) {
    // Sadece options sayfasıysa çalış
    if ($post_id !== 'options') {
        return;
    }

    // Değeri güvenle al
    $pagination_items = get_field('post_pagination', 'options');
    //error_log(print_r($pagination_items, true));

    if (is_array($pagination_items)) {
        foreach ($pagination_items as $item) {
            if (
                isset($item['post_type'], $item['paged']) &&
                $item['post_type'] === 'product' &&
                !empty($item['paged'])
            ) {
                // WooCommerce seçeneklerini güncelle
                update_option("woocommerce_catalog_columns", $item["catalog_columns"] ?? 4);
                update_option("woocommerce_catalog_rows", $item["catalog_rows"] ?? 3);

                //error_log("WooCommerce ayarları güncellendi.");
            }
        }
    } else {
        //error_log("post_pagination alanı boş ya da array değil.");
    }
}
add_action('acf/save_post', 'on_acf_post_pagination_saved', 20);


// Google maps
add_filter('acf/update_value/name=map_url', 'acf_map_embed_update', 10, 3);
function acf_map_embed_update( $value, $post_id, $field ) {
    if(strpos($value, "<iframe ") !== false){
        $value = preg_replace('/\\\\/', '', $value);
        $value = get_iframe_src( $value );
    }
    return $value;
}

add_action('acf/update_value', 'acf_map_lat_lng', 99, 3 ); 
function acf_map_lat_lng( $value, $post_id, $field ) {
    if( 'google_map' === $field['type']){
        if( 'map' === $field['name'] ) {
            update_post_meta( $post_id, 'lat', $value['lat'] );
            update_post_meta( $post_id, 'lng', $value['lng'] );
        }
        if( 'lat' === $field['name'] && isset($value['lat']) ) {
            update_post_meta( $post_id, 'lat', $value['lat'] );
        }
        if( 'lng' === $field['name'] && isset($value['lng']) ) {
            update_post_meta( $post_id, 'lng', $value['lng'] );
        }
    }
    return $value;
}
/**
 * Google Maps URL / Embed URL / iframe'den koordinat çıkarır.
 * Desteklenen tüm formatlar:
 *  1. Embed pb=  → !2d(lng)!3d(lat)       maps/embed?pb=...
 *  2. Embed pb=  → !1d(lat)!2d(lng)        Street View embed
 *  3. @lat,lng   → /maps/place/...@lat,lng  Standart maps linki
 *  4. ?q=lat,lng → /maps/search/?q=...
 *  5. ?ll=lat,lng
 *  6. ?center=lat,lng
 *  7. ?sll=lat,lng
 *  8. /maps/@lat,lng
 *  9. /maps?q=lat,lng
 * 10. cbll=lat,lng  (Street View)
 * 11. iframe src içinden otomatik çıkarım
 */
function acf_get_coordinates_from_embed_url( $input ) {

    if ( empty( $input ) ) {
        return false;
    }

    // iframe içinden src'yi çıkar
    if ( stripos( $input, '<iframe' ) !== false ) {
        if ( preg_match( '/src=["\']([^"\']+)["\']/', $input, $m ) ) {
            $input = html_entity_decode( $m[1] );
        }
    }

    // URL decode et (bazı embed URL'leri %21 gibi encode gelir)
    $url = urldecode( $input );

    $lat = null;
    $lng = null;

    // ----------------------------------------------------------------
    // FORMAT 1: Google Embed pb parametresi  →  !2d(LNG)!3d(LAT)
    // Örnek: maps/embed?pb=...!2d28.9718248!3d41.0257297...
    // ----------------------------------------------------------------
    if ( preg_match( '/!2d(-?[0-9]{1,3}\.[0-9]+)[^!]*!3d(-?[0-9]{1,2}\.[0-9]+)/', $url, $m ) ) {
        $lng = (float) $m[1];
        $lat = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 2: Bazen !3d önce gelir (eski embed formatı)  →  !3d(LAT)!...!2d(LNG)
    // ----------------------------------------------------------------
    elseif ( preg_match( '/!3d(-?[0-9]{1,2}\.[0-9]+)[^!]*!2d(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 3: Street View embed  →  !1d(LAT)!2d(LNG)
    // Örnek: ?pb=!4v...!1s...!1d35.676!2d139.746!3f...
    // ----------------------------------------------------------------
    elseif ( preg_match( '/!1d(-?[0-9]{1,2}\.[0-9]+)!2d(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 4: Standart maps URL  →  @lat,lng,zoom
    // Örnek: /maps/place/Galata/@41.025,28.971,17z
    // ----------------------------------------------------------------
    elseif ( preg_match( '/@(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 5: ?q=lat,lng  veya  &q=lat,lng
    // Örnek: /maps/search/?q=41.025,28.971
    // ----------------------------------------------------------------
    elseif ( preg_match( '/[?&]q=(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 6: ?ll=lat,lng
    // ----------------------------------------------------------------
    elseif ( preg_match( '/[?&]ll=(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 7: ?sll=lat,lng  (eski Google Maps Street View)
    // ----------------------------------------------------------------
    elseif ( preg_match( '/[?&]sll=(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 8: ?center=lat,lng  (Maps Embed API)
    // ----------------------------------------------------------------
    elseif ( preg_match( '/[?&]center=(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 9: cbll=lat,lng  (Street View panorama URL)
    // ----------------------------------------------------------------
    elseif ( preg_match( '/[?&]cbll=(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // ----------------------------------------------------------------
    // FORMAT 10: /maps/lat,lng  (kısa path formatı)
    // ----------------------------------------------------------------
    elseif ( preg_match( '/maps\/(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/', $url, $m ) ) {
        $lat = (float) $m[1];
        $lng = (float) $m[2];
    }

    // Koordinat bulunamadı
    if ( $lat === null || $lng === null ) {
        return false;
    }

    // Geçerlilik kontrolü (-90/90 lat, -180/180 lng)
    if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
        return false;
    }

    return [
        'lat' => $lat,
        'lng' => $lng,
    ];
}


// General Settings Condition
/*add_filter('acf/load_field/name=enable_ecommerce', 'acf_general_option_enable_ecommerce');
function acf_general_option_enable_ecommerce($field) {
    if (ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}*/


add_filter('acf/load_field/name=myaccount_page_id', 'acf_general_option_myaccount_page_id');
function acf_general_option_myaccount_page_id($field) {
    if (ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}


/*
add_filter('acf/load_field/name=breadcrumb_add_product_brand', 'acf_general_option_breadcrumb_add_product_brand');
function acf_general_option_breadcrumb_add_product_brand($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=breadcrumb_add_product_taxonomy', 'acf_general_option_breadcrumb_add_product_taxonomy');
function acf_general_option_breadcrumb_add_product_taxonomy($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=remove_woocommerce_styles', 'acf_general_option_remove_woocommerce_styles');
function acf_general_option_remove_woocommerce_styles($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}*/


function acf_get_raw_value($post_id, $field_name, $field_group_name, $index = 0) {
    global $wpdb;

    // Meta key oluşturma mantığı
    if (!empty($field_group_name)) {
        // Index kontrolü: Eğer repeater index'i varsa ekle, yoksa boş geç
        $index_suffix = (isset($index) && $index !== '') ? $index . "_" : "";
        $meta_key = $field_group_name . "_" . $index_suffix . $field_name;
    } else {
        $meta_key = $field_name;
    }

    // Sorguyu güvenli hale getiriyoruz ve tablo prefixini dinamik yapıyoruz
    // wp_postmeta -> {$wpdb->postmeta}
    $query = $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
        $post_id,
        $meta_key
    );

    $value = $wpdb->get_var($query);

    // Çok dilli destek (qTranslate-XT)
    if (!empty($value) && defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE == "qtranslate-xt") {
        if (function_exists('qtranxf_getLanguage') && function_exists('qtranxf_split')) {
            $lang = qtranxf_getLanguage();
            $split_values = qtranxf_split($value);
            
            if (isset($split_values[$lang])) {
                $value = $split_values[$lang];
            }
        }
    }

    return $value;
}


function acf_admin_colors_footer() {
    $colors = [];
    $colors_file = THEME_STATIC_PATH . 'data/colors_mce.json';

    if (file_exists($colors_file)) {
        $json = file_get_contents($colors_file);
        $decoded = json_decode($json, true);
        if (!empty($decoded)) {
            $colors = array_keys($decoded);
        }
    }
    ?>
    <script type="text/javascript">
    (function($) {
        let colors = [];

        <?php if (!empty($colors)) : ?>
            colors = <?php echo json_encode($colors); ?>;
        <?php else : ?>
            let obj = getComputedStyle(document.documentElement);
            let custom_colors = obj.getPropertyValue('--salt-colors').trim();
            if (!IsBlank(custom_colors)) {
                custom_colors = custom_colors.split(",");
                custom_colors.forEach(color => {
                    colors.push(obj.getPropertyValue('--bs-' + color.trim()).trim());
                });
            }
        <?php endif; ?>

        // --- ACF Color Picker ---
        if (typeof acf !== 'undefined') {
            acf.add_filter('color_picker_args', function(args, $field) {
                args.palettes = colors;
                return args;
            });
        }

        // --- Gutenberg editör renkleri ---
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            wp.domReady(function() {
                const colorObjects = colors.map(c => ({
                    name: c,
                    slug: c.replace('#', '').replace(/\s+/g, '-'),
                    color: c
                }));
                
                // Dispatch çağrısı artık güvenlidir
                wp.data.dispatch('core/editor').updateEditorSettings({ colors: colorObjects });
            });
        }
    })(jQuery);
    </script>
    <?php
}
add_action('acf/input/admin_footer', 'acf_admin_colors_footer');


// --- Klasik TinyMCE editör için de renkleri otomatik olarak güncelle ---
add_filter('tiny_mce_before_init', function($init) {
    $colors = [];
    $colors_file = THEME_STATIC_PATH . 'data/colors_mce.json';

    if (file_exists($colors_file)) {
        $json = file_get_contents($colors_file);
        $decoded = json_decode($json, true);
        if (!empty($decoded)) {
            $colors = array_keys($decoded);
        }
    }

    if (!empty($colors)) {
        $color_str = '';
        foreach ($colors as $color) {
            $color_str .= "'{$color}', '{$color}',";
        }

        $init['textcolor_map']  = '[' . rtrim($color_str, ',') . ']';
        $init['textcolor_rows'] = ceil(count($colors) / 8); // otomatik satır sayısı
        $init['textcolor_cols'] = count($colors);            // ne kadar varsa hepsini göster
    }

    return $init;
});


function acf_admin_colors_gradients_footer() { 
    $colors = [];
    $colors_file = THEME_STATIC_PATH . 'data/colors_gradients.json';
    if(file_exists($colors_file)){
        $colors = file_get_contents($colors_file);
        $colors = json_decode($colors, true);
        if($colors){
            return $colors;
        }
    }
}
add_filter('acf_custom_gradients', 'acf_admin_colors_gradients_footer');








function update_acf_post_object_field_choices($title, $post, $field, $post_id) {
    if ($field['name'] == 'contact') {
        $contact_types = wp_get_post_terms($post->ID, "contact-type",  array("fields" => "names"));
        if (!empty($contact_types) && !is_wp_error($contact_types)) {
            $contact_type = $contact_types[0];
            $title = $title . ' <strong class="text-primary">(' . $contact_type . ')</strong>';
        }
    }
    return $title ;    
}
add_filter( 'acf/fields/post_object/result', 'update_acf_post_object_field_choices', 10, 4 );


// ── Kaldırıldı (2026-07-30) ──────────────────────────────────────────────────
// ACF field group_66ba02a81ae19 (Menu options page) silindi.
// Bu fonksiyonlar o field group'a ait ACF key'lerine (field_65d5fc059efb9 vb.)
// bağlıydı. menu_locations + menu_populate artık GeneralSettings App üzerinden
// yönetiliyor, ACF'den bağımsız wp_options ile.
//
// if (false) {
//     function acf_load_menu_field_choices( $field ) { ... }
//     add_filter('acf/load_field/key=field_65d5fc059efb9', 'acf_load_menu_field_choices');
//     function populate_menu_items_on_change() { ... }
//     function populate_menu_items_callback() { ... }
//     add_action('wp_ajax_populate_menu_items', 'populate_menu_items_callback');
//     add_action('wp_ajax_nopriv_populate_menu_items', 'populate_menu_items_callback');
// }
// ─────────────────────────────────────────────────────────────────────────────




// ── Kaldırıldı (2026-08-26) ──────────────────────────────────────────────────
// acf_add_field_options → apps/field-registry/ sistemine taşındı (FieldRegistry + FieldChoices).
// acf_load_offcanvas_template_files(), eski ACF Page Settings group_64258974cbf92'nin
// field_6425cced6668a ('template' select) alanını /partials/offcanvas/*.twig dosyalarıyla
// dolduruyordu. O field group artık canlı acf-json'da yüklü değil (ACF-free PageSettings App'e
// taşındı); offcanvas template'i artık post-ID → _custom/{tpl} ile çözülüyor. Bu loader ölü kod.
// Yükleme noktası: variables.php içinde "include_once .../apps/field-registry/bootstrap.php;"
//
// function acf_load_offcanvas_template_files( $field ) { ... /partials/offcanvas/ tarar ... }
// add_filter('acf/load_field/key=field_6425cced6668a', 'acf_load_offcanvas_template_files');
// ─────────────────────────────────────────────────────────────────────────────


/**
 * Theme Styles Default Loader — Customize sub-field'ları
 *
 * Buttons block'taki modal/offcanvas/dropdown custom_styles field'ları
 * boş olduğunda theme-styles'daki mevcut değerleri default olarak yükler.
 * Böylece "Customize" toggle açılınca field'lar boş değil, global ayarlarla dolu gelir.
 */
add_filter( 'acf/load_value', 'sh_load_theme_styles_defaults_for_custom_styles', 10, 3 );

function sh_load_theme_styles_defaults_for_custom_styles( $value, $post_id, $field ) {
    // Sadece custom_styles group içindeki field'lara uygulanır
    if ( empty( $field['key'] ) ) return $value;

    // Değer zaten set edilmişse dokunma
    if ( $value !== null && $value !== '' && $value !== false ) return $value;

    // Theme Styles verisi al — class yoksa çık
    if ( ! class_exists( 'Theme_Styles' ) ) return $value;

    // Field key → theme_styles path mapping
    $map = [
        // Modal custom_styles sub-fields
        'field_6972c838e9010' => 'modal.bg_color',
        'field_6972c838e9011' => 'modal.border_radius',
        'field_6972c838e9012' => 'modal.box_shadow',
        'field_6972c838e9013' => 'modal.color',
        'field_6972c838e9014' => 'modal.header.bg_color',
        'field_6972c838e9015' => 'modal.header.color',
        'field_6972c838e9016' => 'modal.header.padding',
        'field_6972c838e9017' => 'modal.body.padding',
        'field_6972c838e9018' => 'modal.footer.bg_color',
        'field_6972c838e9019' => 'modal.footer.padding',
        'field_6972c838e9020' => 'modal.animation.type',
        'field_6972c838e9021' => 'modal.animation.duration_raw',
        // Offcanvas custom_styles sub-fields
        'field_6a7beb07bf3b0' => 'offcanvas_panel.bg_color',
        'field_6a7beb07bf3b1' => 'offcanvas_panel.width',
        'field_6a7beb07bf3b2' => 'offcanvas_panel.header.bg_color',
        'field_6a7beb07bf3b3' => 'offcanvas_panel.header.color',
        'field_6a7beb07bf3b4' => 'offcanvas_panel.backdrop.opacity',
        // Dropdown custom_styles sub-fields
        'field_6a7f1598ab5b0' => 'dropdown.bg_color',
        'field_6a7f1598ab5b9' => 'dropdown.color',
        'field_6a7f1598ab5b1' => 'dropdown.border_radius',
        'field_6a7f1598ab5ba' => 'dropdown.border_color',
        'field_6a7f1598ab5bb' => 'dropdown.border_width',
        'field_6a7f1598ab5b2' => 'dropdown.box_shadow',
        'field_6a7f1598ab5bc' => 'dropdown.min_width',
        'field_6a7f1598ab5bd' => 'dropdown.padding_x',
        'field_6a7f1598ab5be' => 'dropdown.padding_y',
        'field_6a7f1598ab5b3' => 'dropdown.item.color',
        'field_6a7f1598ab5bf' => 'dropdown.item.color_hover',
        'field_6a7f1598ab5c0' => 'dropdown.item.bg_hover',
        'field_6a7f1598ab5c1' => 'dropdown.header.color',
        'field_6a7f1598ab5c2' => 'dropdown.divider.color',
    ];

    if ( ! isset( $map[ $field['key'] ] ) ) return $value;

    $ts_data = Theme_Styles::init()->get_data();
    $path    = $map[ $field['key'] ];
    $keys    = explode( '.', $path );
    $val     = $ts_data;

    foreach ( $keys as $k ) {
        if ( ! is_array( $val ) || ! array_key_exists( $k, $val ) ) {
            return $value; // path bulunamadı, dokunma
        }
        $val = $val[ $k ];
    }

    return is_string( $val ) || is_numeric( $val ) ? (string) $val : $value;
}


class UpdateFlexibleFieldLayouts {

    public $post_id;
    public $field_name;
    public $field_key;
    public $field_data;
    public $field_layouts;
    public $block_name;
    public $block_data;
    public $migration;

    private $clone;
    private $breakpoints;


    private $cached_field_data = [];
    private $cached_field_layouts = [];

    public function __construct($post_id = 0, $field_name = "", $field_key = "", $block_name = "", $migration = []) {
        $this->post_id = $post_id;
        $this->field_name = $field_name;
        $this->field_key = $field_key;
        $this->block_name = $block_name;
        $this->migration = $migration;

        $this->clone = array(
            'aria-label' => '',
            'type' => 'clone',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'clone' => array(),
            'display' => 'seamless',
            'layout' => 'block',
            'prefix_label' => 0,
            'prefix_name' => 0,
            'acfe_seamless_style' => 0,
            'acfe_clone_modal' => 0,
            'acfe_clone_modal_close' => 0,
            'acfe_clone_modal_button' => '',
            'acfe_clone_modal_size' => 'large',
        );
        $this->breakpoints = array(
            'aria-label' => '',
            'type' => 'acf_bs_breakpoints',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'acf_bs_breakpoints_type' => 'number',
            'show_description' => 1,
            'acf_bs_breakpoints_choices' => '',
            'allow_in_bindings' => 0,
            'font_size' => 14,
        );
    }

    public function get_block_field_data($block) {
        $data = [];
        $id = $block->post_name;
        $file = get_stylesheet_directory() . '/acf-json/'.$id.".json";
        $data = file_get_contents($file);
        if($data) {
            $data = json_decode($data, true);
            $fields = $data["fields"];
            $layouts = [];
            $data = [];
            foreach($fields as $item) {
                if (isset($item["layouts"])) {
                    $layouts = $item["layouts"];
                    continue;
                }
            }
            if ($layouts) {
                foreach($layouts as $layout) {
                    $fields = [];
                    $sub_fields = $layout["sub_fields"];
                    foreach($sub_fields as $sub_field) {
                        $fields[$sub_field["name"]] = $sub_field["key"];
                    }
                    $data[$layout["name"]] = array(
                        "key" => $layout["key"],
                        "sub_fields" => $fields
                    );
                }
            }
        }
        return $data;
    }

    public function get_block_fields() {
        global $wpdb;
        $block_categories = ["block"];
        $taxonomy = 'acf-field-group-category';
        
        // GÜVENLİ KOD: IN () sorgusunda prepare kullanımı
        // Diziyi güvenli hale getirin
        $taxonomy_terms = array_map('esc_sql', $block_categories);
        $in_placeholders = implode(', ', array_fill(0, count($taxonomy_terms), '%s'));

        $sql = "
            SELECT p.*
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'acf-field-group'
            AND p.post_status = 'publish'
            AND t.slug IN ({$in_placeholders}) 
            AND tt.taxonomy = %s
            ORDER BY p.post_title ASC
        ";
        
        // %s ve %d parametrelerini birleştirin
        $prepared_args = array_merge($taxonomy_terms, [$taxonomy]);

        return $wpdb->get_results($wpdb->prepare($sql, $prepared_args));
    }

    public function field_data($forced = false) {
        if (!empty($this->cached_field_data) && !$forced) {
            return $this->cached_field_data;
        }

        global $wpdb;
        $post_excerpt_value = 'acf_block_columns';
        $post_type = 'acf-field';

        $post_data = $wpdb->get_row( 
                $wpdb->prepare( 
                    "
                    SELECT ID, post_content 
                    FROM $wpdb->posts 
                    WHERE post_excerpt = %s 
                    AND post_type = %s 
                    LIMIT 1
                    ", 
                    $post_excerpt_value, 
                    $post_type
                ), 
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );

        $this->cached_field_data = $post_data;
        return $post_data;
    }
    public function field_layouts() {
        if (!empty($this->cached_field_layouts)) {
            return $this->cached_field_layouts;
        }

        $fields_added = [];
        $post_data = $this->field_data();
        if ($post_data) {
            $post_content = unserialize($post_data['post_content']);
            if (isset($post_content['layouts'])) {
                foreach ($post_content['layouts'] as $item) {
                    if (isset($item['sub_fields'][1])) {
                        $fields_added[] = $item['sub_fields'][1]['name'];
                    }
                }
            }
        }

        $this->cached_field_layouts = $fields_added;
        return $fields_added;
    }

    public function get_block_data() {
        if (!empty($this->block_data)) {
            return $this->block_data;
        } else {
            global $wpdb;
            $post_data = $wpdb->get_row( 
                $wpdb->prepare( 
                    "
                    SELECT *
                    FROM $wpdb->posts 
                    WHERE post_excerpt = %s 
                    AND post_type = %s 
                    AND post_status = 'publish' 
                    LIMIT 1
                    ", 
                        $this->block_name, 
                        'acf-field-group'
                ), 
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );
            $this->block_data = $post_data;
            return $post_data;
        }
    }
    public function block_exists_in_layouts() {
        $layouts = $this->field_layouts();
        $block_name_solid = str_replace("block-", "", $this->block_name);
        ////error_log(json_encode($layouts));
        ////error_log($block_name_solid." var mı? => ".in_array($block_name_solid, $layouts));
        return in_array($block_name_solid, $layouts);
    }
    public function block_exists_in_db() {
        global $wpdb;
        $field_data = $this->field_data();
        
        if (empty($field_data) || !isset($field_data['ID'])) {
            //error_log("field_data boş veya ID yok");
            return false; // Hemen false döndür
        }

        $post_parent = $field_data['ID'];
        $block_name_solid = str_replace("block-", "", $this->block_name);

        $block = $wpdb->get_var( 
            $wpdb->prepare(
                "
                SELECT post_excerpt 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_parent = %d 
                AND post_excerpt = %s
                ", 
                'acf-field', 
                $post_parent,
                $block_name_solid
            )
        );

        //error_log("post_parent: ".$post_parent.", block_name_solid: ".$block_name_solid);
        return !empty($block) ? true : false;
    }

    public function create_clone($block, $post_parent, $layout_name, $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }

        $clone = $this->clone;
        $clone["parent_layout"] = $layout_name;
        $clone["parent_id"] = $post_parent;
        $clone["clone"] = array(
            $block["post_name"]
        );
        $post_data = array(
            //'post_title'    => $block["post_title"],
            'post_content' => serialize($clone), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => str_replace("block-", "", $block["post_excerpt"]), // Parent ID, 8293 olarak belirlenmiş
        );
        $post_id = wp_insert_post($post_data);
        return $clone;
    }
    public function create_field($block, $post_parent, $layout_name, $args = array(), $title = "", $slug = "", $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }
        $args["parent_layout"] = $layout_name;
        $post_data = array(
            'post_title' => $title, //"Breakpoints",
            'post_content' => serialize($args), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => $slug, //"breakpoints",
        );
        $post_id = wp_insert_post($post_data);
        return $args;
    }

    public function update() {
        if (!$this->block_exists_in_db()) {
            //error_log("+++ Ekleniyor");

            $post_data = $this->field_data();
            if ($post_data) {
                $post_parent = $post_data['ID'];
                $post_content = unserialize($post_data['post_content']);
                $layouts = $post_content['layouts'];

                $block = $this->get_block_data();

                $layout_data = [];
                if ($this->migration && in_array($this->block_name, array_values($this->migration))) {
                    $layout_data = $this->migration[$this->block_name];
                    $layout_name = $layout_data['key'];
                } else {
                    $layout_name = "layout_" . uniqid();
                }

                $breakpoints = $this->create_field($block, $post_parent, $layout_name, $this->breakpoints, "Breakpoints", "breakpoints", $layout_data);
                $clone = $this->create_clone($block, $post_parent, $layout_name, $layout_data);

                if ($clone && $breakpoints) {
                    $clone['parent_id'] = $post_parent;

                    $layouts[$layout_name] = array(
                        'key' => $layout_name,
                        'name' => $block['post_excerpt'],
                        'label' => $block['post_title'],
                        'display' => 'block',
                        'sub_fields' => [],
                        'min' => '',
                        'max' => '',
                    );

                    // Post içeriği güncelleniyor
                    $post_content['layouts'] = $layouts;
                    $post_content = serialize($post_content);

                    /*global $wpdb;
                    $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $post_content],
                        ['ID' => $post_parent]
                    );*/

                    // YENİ VE GÜVENLİ KOD (Format belirtilmiş)
                    global $wpdb;
                    $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $post_content], // Güncellenecek veriler (post_content serialized string'dir, yani %s)
                        ['ID' => $post_parent],          // WHERE koşulu (ID bir integer'dır, yani %d)
                        ['%s'],                           // Verilerin formatları (post_content için string)
                        ['%d']                            // Koşulların formatları (ID için integer)
                    );
                }
            }
        } else {
            //error_log("--- Eklenmiyor");
        }
    }

    public function update_layouts($post_parent) {
        global $wpdb;
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_parent = %d 
                AND (post_excerpt IS NULL OR post_excerpt = '')
                ", 
                'acf-field', 
                $post_parent
            )
        );

        if ($posts) {
            foreach($posts as $post) {
                $new_excerpt = sanitize_title(str_replace('block', '', strtolower($post->post_title)));
                $wpdb->update(
                    $wpdb->posts,
                    array('post_excerpt' => $new_excerpt), // post_excerpt alanını güncelle
                    array('ID' => $post->ID) // ID'ye göre güncelle
                );
            }
        }
    }

    public function clear_cache() {
        if ( function_exists('acf_get_field_groups') ) {
            
            // 1. Tüm dahili cache'i tek seferde temizle (En güvenli yol)
            if ( function_exists('acf_cache_delete_all') ) {
                acf_cache_delete_all();
            }

            // 2. Eğer özel olarak field'ları gezmek istiyorsan, 
            // hataları engellemek için sadece field key üzerinden gitmelisin
            $field_groups = acf_get_field_groups();
            
            if ( is_array($field_groups) ) {
                foreach ( $field_groups as $group ) {
                    // Group cache temizliği için sadece key'i kullanmak yetmez, 
                    // ACF'in beklentilerini karşılamak için varsayılanları ekleyelim.
                    $group = wp_parse_args($group, array('name' => '', 'parent' => 0));
                    acf_flush_field_cache( $group );

                    $fields = acf_get_fields( $group['key'] );
                    if ( is_array($fields) ) {
                        foreach ( $fields as $field ) {
                            // Field dizisindeki eksik anahtarları doldurarak hatayı engelle
                            $field = wp_parse_args($field, array('name' => '', 'parent' => 0));
                            acf_flush_field_cache( $field );
                        }
                    }
                }
            }
            //error_log('🔥 ACF Cache başarıyla (ve hatasız) temizlendi.');
        }
    }
    public function update_cache() {
        if ($this->post_id) {
            acf_save_post_block_columns_action($this->post_id);

            // ACF Cache'i temizle
            //$this->clear_cache();

            // Alan grubunu yeniden yükle
            if($this->field_key){
                acf_import_field_group(acf_get_field_group($this->field_key));             
            }

            // Alan grubunu manuel kaydet
            do_action('acf/save_post', $this->post_id);
        }
    }
}
function acf_save_post_block_columns_action( $post_id ){
    if(has_term("block", 'acf-field-group-category', $post_id)){ // is block
        $block = get_post($post_id);

        //error_log("block->post_excerpt: ".$block->post_excerpt);

        remove_action( 'save_post', 'acf_save_post_block_columns', 20 );

        if($block->post_excerpt != "block-bootstrap-columns"){

            $layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $block->post_name, $block->post_excerpt);
            $layouts->update();

        }elseif($block->post_excerpt == "block-bootstrap-columns"){

            $layouts_check = new UpdateFlexibleFieldLayouts();
            $blocks = $layouts_check->get_block_fields();
            if($blocks){
                $group_field_data = $layouts_check->get_block_field_data($block);
                //error_log("block-bootstrap-columns s a v i n g . . . . . . . . . . . . ");
                foreach($blocks as $item){
                    //error_log("adding:".$item->post_excerpt);
                    $layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $item->post_name, $item->post_excerpt, $group_field_data);
                    $layouts->update();
                }
            }
        }

        add_action( 'save_post', 'acf_save_post_block_columns', 20 );

    }
}
function acf_save_post_block_columns( $post_id ) {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( get_post_status( $post_id ) !== 'publish' ) {
        return;
    }
    if ( get_post_type( $post_id ) !== 'acf-field-group' ) {
        return;
    }
    static $has_run = false; // Hook'un iki kere çalışmasını önlemek için flag kullan
    if ($has_run) {
        return;
    }
    $has_run = true;

    acf_save_post_block_columns_action( $post_id );
}
add_action( 'save_post', 'acf_save_post_block_columns', 20);





// DEPRECATED — header_footer_options JSON sistemi kaldırıldı.
/*
function acf_header_footer_options_save_hook($post_id) {
    if ($post_id !== 'options') {
        return;
    }
    if (isset($_POST['acf'])) {
        $current_screen = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($current_screen === 'header' || $current_screen === 'footer') {
            $header_footer_options = header_footer_options(true);
            $preset_file = THEME_STATIC_PATH . 'data/header-footer-options.json';
            $json_data = json_encode($header_footer_options);
            file_put_contents($preset_file, $json_data);
            \SaltHareket\AssetManager\AssetManager::getInstance()->compile_common_css();
        }
    }
}
add_action('acf/save_post', 'acf_header_footer_options_save_hook', 10);
*/




add_filter('acf/update_value/name=modal_home', function ($value, $post_id, $field) {
    $home_id = get_option('page_on_front');
    //error_log(" -----------  updated home: ".$home_id);
    if ($home_id) {
        wp_update_post([
            'ID' => $home_id,
        ]);
    }
    return $value;
}, 10, 3);



// ── Taşındı (2026-08-26) ──────────────────────────────────────────────────────
// Video Processor ACF field hook'ları apps/video-processor/bootstrap.php'ye taşındı.
// delete_related_video_attachments, remove_attachment_from_video_metadata,
// acf_ffmpeg_available, acf_ffmpeg_not_available, acf_ffmpeg_not_available_message,
// sync_acf_with_post_meta + ilgili add_action/add_filter çağrıları.
// ─────────────────────────────────────────────────────────────────────────────



// ── Taşındı (2026-08-26) ──────────────────────────────────────────────────────
// WP Hide ACF field hook'ları → apps/security/Admin/SecurityAdmin.php
// acf_general_option_wph_settings() — ACF options page silindi, ölü kod
// acf_wph_settings() AJAX + admin_footer JS → SecurityAdmin::ajaxWphApply()
// ─────────────────────────────────────────────────────────────────────────────



// toggle_map_fields_based_on_settings, dynamic_map_service_value, dynamic_map_service_update_value
// → General Settings > Maps > ajaxSaveMaps()'e taşındı. ACF hook'ları artık gerekli değil.

add_action('acf/save_post', 'sync_contact_map_coordinates', 25);
function sync_contact_map_coordinates($post_id) {
    
    // Sadece 'contact' post type için çalışsın
    if (get_post_type($post_id) !== 'contact') {
        return;
    }

    // 1. Global Ayarları Çek (QueryCache kullandığın için aynen yazdım)
    $map_service = \SaltHareket\SaltConfig::get("options_map_service"); // 'leaflet' veya 'google'
    $map_view    = \SaltHareket\SaltConfig::get("options_map_view");    // 'js' veya 'embed'

    // Hedef Hidden Field Key'lerin (Bunları kendi keylerinle değiştir abi)
    $lat_hidden_key = 'contact_lat'; 
    $lng_hidden_key = 'contact_lng';

    $final_lat = '';
    $final_lng = '';

    // 2. Senaryo: LEAFLET
    if ($map_service === 'leaflet') {
        $leaflet_data = get_field('contact_map_leaflet', $post_id);
        if (!empty($leaflet_data)) {
            $final_lat = $leaflet_data['lat'] ?? '';
            $final_lng = $leaflet_data['lng'] ?? '';
        }
    } 
    // 3. Senaryo: GOOGLE
    elseif ($map_service === 'google') {
        
        // Google JS API (ACF Google Maps Field)
        if ($map_view === 'js') {
            $google_map = get_field('contact_map_google', $post_id); // Array döner
            if (!empty($google_map)) {
                $final_lat = $google_map['lat'] ?? '';
                $final_lng = $google_map['lng'] ?? '';
            }
        } 
        // Google Embed (URL'den ayıklama)
        elseif ($map_view === 'embed') {
            $map_url = get_field('contact_map_url', $post_id); // Prepare filter'dan geçmiş temiz URL
            if (!empty($map_url)) {
                $coordinates = acf_get_coordinates_from_embed_url($map_url);
                $final_lat = $coordinates["lat"] ?? "";//$lat_match[1] ?? '';
                $final_lng = $coordinates["lng"] ?? "";//$lng_match[1] ?? '';
            }
        }
    }
    

    // 4. Kayıt veya Temizlik
    // Değerler varsa kaydet, yoksa (veya alanlar boşaltıldıysa) hidden field'ları da sil
    update_field($lat_hidden_key, $final_lat, $post_id);
    update_field($lng_hidden_key, $final_lng, $post_id);
}

function create_options_menu($options){
        if(array_iterable($options)){
            $menu_title = $options['title'];
            $redirect = $options['redirect'];
            acf_add_options_page(array(
                'page_title'    => $menu_title,
                'menu_title'    => $menu_title,
                'menu_slug'     => sanitize_title($menu_title),
                'capability'    => 'edit_posts',
                'redirect'      => $redirect
            ));
            $menu_children = $options['children'];
            if($menu_children){
                create_options_menu_children($menu_title, $menu_children);
            }
        }
};
function create_options_menu_children($menu_title, $options){
    for($i = 0; $i < count($options); $i++){
        if(is_array($options[$i])){
            create_options_menu_children($options[$i]["title"], $options[$i]["children"]);
        }else{
            acf_add_options_sub_page(array(
                'page_title'    => $options[$i],
                'menu_title'    => $options[$i],
                'menu_slug'     => sanitize_title($options[$i]),
                'parent_slug'   => sanitize_title($menu_title),
            ));         
        }
    }
}





if(ENABLE_ECOMMERCE){
    //another solutions for below:
    //https://remicorson.com/mastering-woocommerce-products-custom-fields/
    //https://remicorson.com/woocommerce-custom-fields-for-variations/

    // Render fields at the bottom of variations - does not account for field group order or placement.
    add_action( 'woocommerce_product_after_variable_attributes', function( $loop, $variation_data, $variation ) {
        global $abcdefgh_i; // Custom global variable to monitor index
        $abcdefgh_i = $loop;
        // Add filter to update field name
        add_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
        
        // Loop through all field groups
        $acf_field_groups = acf_get_field_groups();
        foreach( $acf_field_groups as $acf_field_group ) {
            foreach( $acf_field_group['location'] as $group_locations ) {
                foreach( $group_locations as $rule ) {
                    // See if field Group has at least one post_type = Variations rule - does not validate other rules
                    if( $rule['param'] == 'post_type' && $rule['operator'] == '==' && $rule['value'] == 'product_variation' ) {
                        // Render field Group
                        acf_render_fields( $variation->ID, acf_get_fields( $acf_field_group ) );
                        break 2;
                    }
                }
            }
        }
        
        // Remove filter
        remove_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
    }, 10, 3 );

    // Filter function to update field names
    function  acf_prepare_field_update_field_name( $field ) {
        global $abcdefgh_i;
        $field['name'] = preg_replace( '/^acf\[/', "acf[$abcdefgh_i][", $field['name'] );
        return $field;
    }
        
    // Save variation data
    add_action( 'woocommerce_save_product_variation', function( $variation_id, $i = -1 ) {
        // Update all fields for the current variation
        if ( ! empty( $_POST['acf'] ) && is_array( $_POST['acf'] ) && array_key_exists( $i, $_POST['acf'] ) && is_array( ( $fields = $_POST['acf'][ $i ] ) ) ) {
            foreach ( $fields as $key => $val ) {
                update_field( $key, $val, $variation_id );
            }
        }
    }, 10, 2 );

    function acf_location_rule_values_Post( $choices ) {
        $choices['product_variation'] = 'Product Variation';
        return $choices;
    }
    add_filter('acf/location/rule_values/post_type', 'acf_location_rule_values_Post');  
}


// ─── SH Icon Picker — AJAX Endpoints ─────────────────────────────────────────

add_action( 'wp_ajax_sh_fa_icon_list', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );
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
        if ( ! defined( 'STATIC_PATH' ) )    define( 'STATIC_PATH', get_template_directory() . '/static/' );
        require_once SH_CLASSES_PATH . 'class.fa-icon-manager.php';
    }
    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) { wp_send_json_error( 'FaIconManager not loaded' ); return; }
    $names = \SaltHareket\FaIconManager::getIconNames( $style );
    if ( empty( $names ) ) { wp_send_json_error( 'No icons found for style: ' . $style ); return; }
    set_transient( $cache_key, $names, 30 * DAY_IN_SECONDS );
    wp_send_json_success( [ 'icons' => $names, 'style' => $style ] );
} );

add_action( 'wp_ajax_sh_fa_icon_svg', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );
    $style = sanitize_key( $_POST['style'] ?? 'solid' );
    $name  = sanitize_key( $_POST['name']  ?? '' );
    if ( ! $name ) { wp_send_json_error( 'No name' ); return; }
    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        require_once SH_CLASSES_PATH . 'class.fa-icon-manager.php';
    }
    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) { wp_send_json_error( 'FaIconManager not loaded' ); return; }
    wp_send_json_success( \SaltHareket\FaIconManager::extractFromJs( $name, $style ) );
} );

add_action( 'wp_ajax_sh_fa_icon_svg_bulk', function () {
    check_ajax_referer( 'sh_icon_picker_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );
    $style = sanitize_key( $_POST['style'] ?? 'solid' );
    $names = array_map( 'sanitize_key', (array) ( $_POST['names'] ?? [] ) );
    $names = array_slice( $names, 0, 100 );
    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) {
        require_once SH_CLASSES_PATH . 'class.fa-icon-manager.php';
    }
    if ( ! class_exists( 'SaltHareket\FaIconManager' ) ) { wp_send_json_error( 'FaIconManager not loaded' ); return; }
    $result = [];
    foreach ( $names as $name ) {
        if ( $name ) $result[ $name ] = \SaltHareket\FaIconManager::extractFromJs( $name, $style );
    }
    wp_send_json_success( $result );
} );

add_action( 'wp_ajax_sh_clear_icon_cache', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'no' );
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sh_fa_icon%' OR option_name LIKE '_transient_timeout_sh_fa_icon%'" );
    $svg_path = defined('STATIC_PATH') ? rtrim(STATIC_PATH, '/') . '/svg/' : '';
    if ( $svg_path ) {
        foreach ( glob( $svg_path . 'icon-names-*.json' ) ?: [] as $f ) unlink( $f );
    }
    wp_send_json_success( 'Cache cleared' );
} );

// Icon picker nonce inject
add_action( 'admin_head', function () {
    static $sh_ip_printed = false;
    if ( $sh_ip_printed ) return;
    $sh_ip_printed = true;
    $nonce = wp_create_nonce( 'sh_icon_picker_nonce' );
    echo '<script>window.shIconPickerConfig={ajaxUrl:' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',nonce:' . wp_json_encode( $nonce ) . '};</script>' . "\n";
} );

// ACF admin sayfalarında enqueue — sh-icon-picker + Buttons Block inline JS
add_action( 'acf/input/admin_enqueue_scripts', function () {
    $base    = get_template_directory_uri() . '/vendor/salthareket/theme/src/includes/apps/';
    $base_fs = get_template_directory()     . '/vendor/salthareket/theme/src/includes/apps/';
    if ( file_exists( $base_fs . 'sh-icon-picker.css' ) ) {
        wp_enqueue_style( 'sh-icon-picker', $base . 'sh-icon-picker.css', [], '2.0.0' );
    }
    if ( file_exists( $base_fs . 'sh-icon-picker.js' ) ) {
        wp_enqueue_script( 'sh-icon-picker', $base . 'sh-icon-picker.js', [ 'jquery' ], '2.0.0', true );
    }

    // ── Buttons Block — content_source seçilince modal_settings içindeki
    //    template/içerik alanlarını gizle. clone prefix_name=1 olduğundan
    //    field data-name'leri "modal_settings_{field_name}" formatında gelir.
    //
    //    content_source != "none" iken GİZLENECEK modal_settings alanları:
    //      template, location_type, lat, lng, posts, ratio,
    //      marker_popup, marker_popup_type, marker_popup_title,
    //      id (page), id (form), content_type, file, url, height
    //
    //    GÖRÜNÜR KALACAKLAR: type, size, scrollable, close, class, title
    wp_add_inline_script( 'acf-input', <<<'JS'
(function($) {
    var MODAL_HIDDEN_WHEN_SOURCE = [
        'type', 'template', 'location_type', 'lat', 'lng', 'posts', 'ratio',
        'marker_popup', 'marker_popup_type', 'marker_popup_title',
        'id', 'content_type', 'file', 'url', 'height'
    ];

    function shSyncOneSource($src) {
        var val       = $src.find('select').val();
        var hasSource = val && val !== 'none' && val !== '';

        var $scope     = $src.closest('.acf-row, .acf-fields, .acf-block-fields');
        if ( ! $scope.length ) $scope = $('body');

        var $allFields  = $scope.find('.acf-field');
        var srcIdx      = $allFields.index($src[0]);
        var $modalGroup = null;

        $scope.find('.acf-field[data-name="modal_settings"]').each(function() {
            if ( $allFields.index(this) > srcIdx ) { $modalGroup = $(this); return false; }
        });

        if ( ! $modalGroup || ! $modalGroup.length ) return;

        MODAL_HIDDEN_WHEN_SOURCE.forEach(function(name) {
            var $f = $modalGroup.find('.acf-field[data-name="' + name + '"]');
            if ( hasSource ) { $f.attr('style', 'display:none !important').addClass('sh-hidden'); }
            else             { $f.removeAttr('style').removeClass('sh-hidden'); }
        });
    }

    function shSyncAll() {
        $('.acf-field[data-name="content_source"]').each(function() { shSyncOneSource($(this)); });
    }

    // ACFE block modal açılınca tetikle
    var modalObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if ( node.nodeType !== 1 ) return;
                var $node = $(node);
                if ( ! ($node.hasClass('acf-block-form-modal') || $node.find('.acf-block-form-modal').length) ) return;
                setTimeout(shSyncAll, 100);
                setTimeout(shSyncAll, 400);
            });
        });
    });
    $(function() { modalObserver.observe(document.body, { childList: true, subtree: true }); });

    // Change event
    $(document).on('change', '.acf-field[data-name="content_source"] select', function() {
        shSyncOneSource($(this).closest('.acf-field[data-name="content_source"]'));
    });

    // ACF conditional logic field'ı show edince sh-hidden olanı geri gizle
    acf.addAction('show_field', function(field) {
        var fieldName = field && field.data && field.data.name;
        var $field    = field && field.$el;
        if ( ! fieldName || ! $field || MODAL_HIDDEN_WHEN_SOURCE.indexOf(fieldName) === -1 ) return;

        var $modalGroup = $field.closest('.acf-field[data-name="modal_settings"]');
        if ( ! $modalGroup.length ) return;

        var $scope     = $modalGroup.closest('.acf-row, .acf-fields, .acf-block-fields');
        if ( ! $scope.length ) $scope = $('body');

        var $allFields = $scope.find('.acf-field');
        var modalIdx   = $allFields.index($modalGroup[0]);
        var $src       = null;

        $scope.find('.acf-field[data-name="content_source"]').each(function() {
            if ( $allFields.index(this) < modalIdx ) { $src = $(this); }
        });

        if ( ! $src ) return;
        var val = $src.find('select').val();
        if ( val && val !== 'none' && val !== '' ) {
            $field.attr('style', 'display:none !important').addClass('sh-hidden');
        }
    });

    // ACF hooks
    acf.addAction('ready',  function() { setTimeout(shSyncAll, 150); setTimeout(shSyncAll, 400); });
    acf.addAction('load',   function() { setTimeout(shSyncAll, 200); setTimeout(shSyncAll, 600); });
    acf.addAction('append', function($el) {
        setTimeout(shSyncAll, 50);
        // Yeni eklenen row'daki icon picker'ları init et
        setTimeout(function() {
            if (window.shIconPicker) {
                var $target = $el && $el.length ? $el : $('body');
                $target.find('[data-sh-icon-picker]').each(function() {
                    // ACF "Add Row" template'i (_x) klonlar; klon, onceden init
                    // edilmis input'un 'data-sh-ip-init' bayragini ve gorsel wrap'ini
                    // de tasir. Bu yuzden init guard "zaten init" deyip atliyordu →
                    // yeni row picker'i calismiyordu. Klon artiklarini temizleyip
                    // TAZE init ediyoruz.
                    this.removeAttribute('data-sh-ip-init');
                    var $sib = jQuery(this).siblings('.sh-icon-picker-wrap');
                    if ($sib.length) $sib.remove();
                    jQuery(this).show(); // createInstance tekrar gizleyecek
                    window.shIconPicker.init(this);
                });
            }
        }, 100);
    });
    acf.addAction('resize', function() { setTimeout(shSyncAll, 50); });
    $(document).on('acf/setup_fields', function() { setTimeout(shSyncAll, 150); setTimeout(shSyncAll, 500); });

})(jQuery);
JS
    );
} );

// Block editor'de enqueue — sh-icon-picker + modal sync JS
add_action( 'enqueue_block_editor_assets', function () {
    $base    = get_template_directory_uri() . '/vendor/salthareket/theme/src/includes/apps/';
    $base_fs = get_template_directory()     . '/vendor/salthareket/theme/src/includes/apps/';
    if ( file_exists( $base_fs . 'sh-icon-picker.css' ) ) {
        wp_enqueue_style( 'sh-icon-picker', $base . 'sh-icon-picker.css', [], '2.0.0' );
    }
    if ( file_exists( $base_fs . 'sh-icon-picker.js' ) ) {
        wp_enqueue_script( 'sh-icon-picker', $base . 'sh-icon-picker.js', [ 'jquery' ], '2.0.0', true );
    }

    // Modal fields sync — block editor'de acf-input script zaten yüklü,
    // ama jquery-core'a da bağlıyoruz — daha erken çalışır.
    wp_add_inline_script( 'jquery-core', <<<'JS'
(function($) {
    var HIDDEN = ['type','template','location_type','lat','lng','posts','ratio',
                  'marker_popup','marker_popup_type','marker_popup_title','id',
                  'content_type','file','url','height'];

    function shSyncBlock() {
        $('.acf-field[data-name="content_source"]').each(function() {
            var $src      = $(this);
            var val       = $src.find('select').val();
            var hasSource = val && val !== 'none' && val !== '';

            var $scope     = $src.closest('.acf-row, .acf-fields, .acf-block-fields');
            if ( ! $scope.length ) $scope = $('body');

            var $allFields  = $scope.find('.acf-field');
            var srcIdx      = $allFields.index($src[0]);
            var $modalGroup = null;

            $scope.find('.acf-field[data-name="modal_settings"]').each(function() {
                if ( $allFields.index(this) > srcIdx ) { $modalGroup = $(this); return false; }
            });

            if ( ! $modalGroup || ! $modalGroup.length ) return;

            HIDDEN.forEach(function(name) {
                var $f = $modalGroup.find('.acf-field[data-name="' + name + '"]');
                if ( hasSource ) { $f.attr('style', 'display:none !important').addClass('sh-hidden'); }
                else             { $f.removeAttr('style').removeClass('sh-hidden'); }
            });
        });
    }

    $(document).on('change', '.acf-field[data-name="content_source"] select', function() { shSyncBlock(); });
    $(document).on('acf/setup_fields', function() { setTimeout(shSyncBlock, 150); setTimeout(shSyncBlock, 500); });
    setTimeout(shSyncBlock, 300);
    setTimeout(shSyncBlock, 800);
    setTimeout(shSyncBlock, 1500);
})(jQuery);
JS
    );
} );

// ACF field render sonrası — acf-sh-icon-picker class'lı field'ları init et
/*add_action( 'acf/input/admin_footer', function () {
    ?>
    <script>
    (function($){
        function initShIconPickerForAcf($scope) {
            var $fields = $scope
                ? $scope.find('.acf-sh-icon-picker input[type="text"], .acf-sh-icon-picker input[type="hidden"]')
                : $('.acf-sh-icon-picker input[type="text"], .acf-sh-icon-picker input[type="hidden"]');
            $fields.each(function() {
                var $inp = $(this);
                if (this.getAttribute('data-sh-ip-init')) return;
                $inp.attr('data-sh-icon-picker', '').attr('data-placeholder', 'İkon seç...');
                if (window.shIconPicker) window.shIconPicker.init($inp[0]);
            });
        }
        $(function() { initShIconPickerForAcf(); });
        $(document).on('acf/setup_fields', function(e, $el) {
            initShIconPickerForAcf($el ? $($el) : null);
        });
        if (window.acf) {
            window.acf.addAction('load',    function()  { initShIconPickerForAcf(); });
            window.acf.addAction('append',  function($el) { initShIconPickerForAcf($el); });
            window.acf.addAction('refresh', function($el) { initShIconPickerForAcf($el); });
        }
    })(jQuery);
    </script>
    <?php
} );*/

// ACF field render sonrasi — acf-sh-icon-picker class'li field'lari init et
add_action( 'acf/input/admin_footer', function () {
    ?>
    <script>
    (function($){
        function initShIconPickerForAcf($scope) {
            var $fields = $scope
                ? $scope.find('.acf-sh-icon-picker input[type="text"], .acf-sh-icon-picker input[type="hidden"]')
                : $('.acf-sh-icon-picker input[type="text"], .acf-sh-icon-picker input[type="hidden"]');
            $fields.each(function() {
                var $inp = $(this);
                if (this.getAttribute('data-sh-ip-init')) return;
                $inp.attr('data-sh-icon-picker', '').attr('data-placeholder', 'Ikon sec...');
                if (window.shIconPicker) window.shIconPicker.init($inp[0]);
            });
        }
        // Sayfa yuklenince
        $(function() { initShIconPickerForAcf(); });
        // ACF standard hook
        $(document).on('acf/setup_fields', function(e, $el) {
            initShIconPickerForAcf($el ? $($el) : null);
        });
        // ACF block editor - expanded modal acilinca init et
        if (window.acf) {
            window.acf.addAction('load', function() { initShIconPickerForAcf(); });
            window.acf.addAction('append', function($el) { initShIconPickerForAcf($el); });
            window.acf.addAction('refresh', function($el) { initShIconPickerForAcf($el); });
        }
        // ACF block editor gecikmeli init (iframe yüklenince)
        $(document).on('acf-block-fields:loaded', function() {
            setTimeout(function(){ initShIconPickerForAcf(); }, 100);
        });
        // ACF expanded block editor modal
        $(document).on('click', '.acf-block-button.edit, [data-name="expand"] button', function() {
            setTimeout(function(){ initShIconPickerForAcf(); }, 300);
        });
        // MutationObserver ile ACF modal icindeki yeni field'ları izle
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.addedNodes.length) {
                    m.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            var $node = $(node);
                            if ($node.find('.acf-sh-icon-picker').length || $node.hasClass('acf-sh-icon-picker')) {
                                initShIconPickerForAcf($node);
                            }
                        }
                    });
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })(jQuery);
    </script>
    <?php
} );
