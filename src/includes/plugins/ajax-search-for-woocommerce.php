<?php

/**
 * Ajax Search for WooCommerce (FiboSearch) — Tam Entegrasyon
 *
 * Variation single plugin desteği, autocomplete click tracking,
 * search redirect ve renk swatchi.
 *
 * @package    SaltHareket\Theme\Plugins
 * @version    2.0.0
 * @since      1.0.0
 *
 * CHANGELOG:
 * 2.0.0 - 2026-05-04
 *   - Add: FiboSearch autocomplete suggestion click tracking
 *          navigator.sendBeacon ile redirect öncesi kayıt yapılır
 *   - Add: wp_ajax_nopriv_sh_track_click AJAX handler
 *   - Add: wp_footer JS — suggestion click event delegation (capture phase)
 *   - Fix: type normalization — product_variation → product
 *
 * 1.3.0 - 2026-05-03
 *   - Fix: variation_support_modes sadece XT/Iconic aktifse
 *   - Add: Renk swatchi thumb_html override
 *   - Add: XT variation permalink + title index override
 *
 * 1.0.0 - İlk versiyon
 *
 * HOW TO USE:
 *   variables.php'de class_exists('DGWT_WC_Ajax_Search') kontrolüyle yüklenir.
 *
 *   Autocomplete tracking:
 *   Kullanıcı autocomplete'den bir sonuca tıkladığında sayfa değişir,
 *   search.php çalışmaz. Bu yüzden JS tarafında click event'i yakalanır
 *   ve navigator.sendBeacon ile AJAX'a gönderilir (redirect'ten önce).
 *
 *   ⚠️ Variation index değişikliğinden sonra:
 *   WooCommerce → FiboSearch → Indexer → Rebuild index
 *
 * @example Autocomplete click tracking (otomatik):
 *   Kullanıcı "mascara" yazıp autocomplete'den tıkladığında
 *   type='product' olarak SearchHistory'e kaydedilir.
 *
 * @example SKU araması:
 *   Kullanıcı "SKU-001-RED" yazınca exact match variation gösterilir.
 */

// ── Yardımcı: variation single plugin aktif mi? ───────────────────────────────
function _wcas_variation_single_plugin_active(): bool {
    return class_exists('XT_WOOVAS') || class_exists('Iconic_WSSV');
}

// ── 1. Variation arama modları ────────────────────────────────────────────────
add_filter('dgwt/wcas/variation_support_modes', function($modes) {
    if (!_wcas_variation_single_plugin_active()) return $modes;
    return array_unique(array_merge((array) $modes, ['search_in_sku', 'as_single_product']));
});

// ── 2. XT WooVAS — variation permalink ───────────────────────────────────────
add_filter('dgwt/wcas/variation/permalink', function($url, $wc_variation) {
    if (!class_exists('XT_WOOVAS') || !is_a($wc_variation, 'WC_Product_Variation')) return $url;
    $parent_id  = $wc_variation->get_parent_id();
    if (!$parent_id) return $url;
    $parent_url = get_permalink($parent_id);
    if (!$parent_url) return $url;
    $attributes = array_filter(wc_get_product_variation_attributes($wc_variation->get_id()));
    return empty($attributes) ? $parent_url : add_query_arg($attributes, $parent_url);
}, 10, 2);

// ── 3. XT WooVAS — variation title ───────────────────────────────────────────
add_filter('dgwt/wcas/variation/title', function($title, $wc_variation) {
    if (!class_exists('XT_WOOVAS') || !is_a($wc_variation, 'WC_Product_Variation')) return $title;
    $parent_id    = $wc_variation->get_parent_id();
    if (!$parent_id) return $title;
    $parent_title = get_the_title($parent_id);
    $attributes   = array_filter(wc_get_product_variation_attributes($wc_variation->get_id()));
    if (empty($attributes)) return $parent_title ?: $title;
    $attr_labels = [];
    foreach ($attributes as $key => $value) {
        $taxonomy = str_replace('attribute_', '', $key);
        if (taxonomy_exists($taxonomy)) {
            $tax_obj = get_taxonomy($taxonomy);
            $label   = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;
            $term    = get_term_by('slug', $value, $taxonomy);
            $value   = $term ? $term->name : $value;
        } else {
            $label = ucfirst(str_replace(['pa_', '_', '-'], ['', ' ', ' '], $taxonomy));
        }
        $attr_labels[] = $label . ': ' . $value;
    }
    return $parent_title . ' - ' . implode(', ', $attr_labels);
}, 10, 2);

// ── 4. Autocomplete suggestion — renk swatchi ────────────────────────────────
add_filter('dgwt/wcas/tnt/search_results/suggestion/product_variation', function($data) {
    if (!_wcas_variation_single_plugin_active()) return $data;
    $variation_id = (int) ($data['variation_id'] ?? 0);
    if (!$variation_id) return $data;
    $variation = wc_get_product($variation_id);
    if (!$variation || !is_a($variation, 'WC_Product_Variation')) return $data;
    $attributes = array_filter(wc_get_product_variation_attributes($variation_id));
    if (empty($attributes)) return $data;

    $color_taxonomies = ['pa_color', 'pa_renk', 'pa_colour'];
    $color_hex = $color_name = '';

    foreach ($attributes as $key => $slug) {
        $taxonomy = str_replace('attribute_', '', $key);
        if (!in_array($taxonomy, $color_taxonomies, true)) continue;
        $term = get_term_by('slug', $slug, $taxonomy);
        if (!$term) continue;
        $color_name = $term->name;
        $hex = get_term_meta($term->term_id, 'product_attribute_color', true)
            ?: get_term_meta($term->term_id, 'color', true);
        if ($hex) $color_hex = esc_attr($hex);
        break;
    }

    if (!$color_hex && !$color_name) return $data;

    $bg = $color_hex ?: '#e5e7eb';
    $data['thumb_html'] = sprintf(
        '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#444;white-space:nowrap;">'
        . '<span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:%s;border:2px solid rgba(0,0,0,0.12);flex-shrink:0;"></span>'
        . '<span>%s</span></span>',
        $bg,
        esc_html($color_name)
    );
    return $data;
}, 10);

// ── 5. Search query'ye product_variation ekle ────────────────────────────────
add_filter('dgwt/wcas/search_query/args', function($args) {
    if (!_wcas_variation_single_plugin_active()) return $args;
    $args['post_type'] = ['product', 'product_variation'];
    return $args;
});

// ── 6. Search form action → /search/ sayfasına yönlendir ─────────────────────
add_action('init', function() {
    add_filter('dgwt/wcas/form/action', function($url) {
        $search_page = get_page_by_path('search');
        return $search_page ? get_permalink($search_page->ID) : $url;
    });
});

// ── 7. Fallback: template_redirect ───────────────────────────────────────────
add_action('template_redirect', function() {
    if (!is_search() || empty($_GET['dgwt_wcas'])) return;
    $search_page = get_page_by_path('search');
    if (!$search_page) return;
    wp_safe_redirect(add_query_arg($_GET, get_permalink($search_page->ID)), 301);
    exit;
});

// ── 8. FiboSearch autocomplete click → SearchHistory kayıt ───────────────────
// Kullanıcı autocomplete'den bir sonuca tıkladığında search.php çalışmaz.
// JS tarafında click event'i yakalanır, navigator.sendBeacon ile AJAX'a gönderilir.
// sendBeacon: sayfa redirect olsa bile istek tamamlanır.
// Kaydedilen veriler: term (arama terimi), type, clicked_url, clicked_title
add_action('wp_ajax_sh_track_click',        'wcas_sh_track_click_handler');
add_action('wp_ajax_nopriv_sh_track_click', 'wcas_sh_track_click_handler');

function wcas_sh_track_click_handler(): void {
    if (!check_ajax_referer('sh_track_nonce', 'nonce', false)) {
        wp_send_json_error('invalid_nonce', 403);
    }

    if (!class_exists('SearchHistory')) {
        wp_send_json_error('no_class', 500);
    }

    $term          = isset($_POST['term'])          ? sanitize_text_field(wp_unslash($_POST['term']))          : '';
    $type          = isset($_POST['type'])          ? sanitize_key($_POST['type'])                             : 'fibosearch';
    $clicked_url   = isset($_POST['clicked_url'])   ? esc_url_raw(wp_unslash($_POST['clicked_url']))           : '';
    $clicked_title = isset($_POST['clicked_title']) ? sanitize_text_field(wp_unslash($_POST['clicked_title'])) : '';

    if (empty($term)) {
        wp_send_json_error('empty_term', 400);
    }

    // Type normalize: product_variation → product
    $type_map = [
        'product_variation' => 'product',
        'product'           => 'product',
        'post'              => 'post',
        'page'              => 'page',
    ];
    $type = $type_map[$type] ?? 'fibosearch';

    $sh = new SearchHistory();

    // 1. Arama terimini kaydet (wp_search_terms)
    $sh->set_term($term, $type, false);

    // 2. Tıklama hedefini kaydet (wp_search_clicks) — URL varsa
    if (!empty($clicked_url)) {
        $sh->record_click($term, $clicked_url, $clicked_title, $type);
    }

    wp_send_json_success();
}

// ── 9. Frontend JS: FiboSearch suggestion click → sendBeacon ─────────────────
add_action('wp_footer', function() {
    if (!class_exists('SearchHistory')) return;
    if (!defined('ENABLE_SEARCH_HISTORY') || !ENABLE_SEARCH_HISTORY) return;

    $nonce    = wp_create_nonce('sh_track_nonce');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <script>
    (function() {
        var _ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var _nonce   = <?php echo wp_json_encode($nonce); ?>;

        function sendTrack(term, type, clickedUrl, clickedTitle) {
            if (!term || term.length < 2) return;

            var params = new URLSearchParams({
                action:        'sh_track_click',
                nonce:         _nonce,
                term:          term,
                type:          type          || 'fibosearch',
                clicked_url:   clickedUrl   || '',
                clicked_title: clickedTitle || ''
            });

            // sendBeacon: sayfa değişse bile gönderir — en güvenilir yöntem
            if (typeof navigator.sendBeacon === 'function') {
                navigator.sendBeacon(_ajaxUrl, params);
            } else {
                // Fallback: sync XHR (sayfa değişmeden önce tamamlanır)
                var xhr = new XMLHttpRequest();
                xhr.open('POST', _ajaxUrl, false); // false = sync
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                try { xhr.send(params.toString()); } catch(e) {}
            }
        }

        // FiboSearch suggestion'larını dinle — mousedown kullan (click'ten önce tetiklenir)
        // mousedown: kullanıcı butona bastığında, sayfa değişmeden önce çalışır
        document.addEventListener('mousedown', function(e) {
            var suggestion = e.target.closest('.dgwt-wcas-suggestion');
            if (!suggestion) return;

            // "See all", "more", "no results" suggestion'larını atla
            if (
                suggestion.classList.contains('dgwt-wcas-suggestion-more') ||
                suggestion.classList.contains('js-dgwt-wcas-suggestion-more') ||
                suggestion.classList.contains('dgwt-wcas-suggestion-nores')
            ) return;

            // Arama kutusundaki değeri al — birden fazla form olabilir
            var form = suggestion.closest('form, .dgwt-wcas-search-wrapp');
            var input = form
                ? form.querySelector('.dgwt-wcas-search-input')
                : document.querySelector('.dgwt-wcas-search-input');
            if (!input || !input.value.trim()) return;

            var term = input.value.trim();

            // Type tespiti: CSS class'larından
            var type = 'fibosearch';
            var classList = suggestion.className || '';
            if (classList.indexOf('dgwt-wcas-suggestion-product-var') !== -1) {
                type = 'product';
            } else if (classList.indexOf('dgwt-wcas-suggestion-product') !== -1) {
                type = 'product';
            } else if (classList.indexOf('dgwt-wcas-suggestion-post') !== -1) {
                type = 'post';
            } else if (classList.indexOf('dgwt-wcas-suggestion-page') !== -1) {
                type = 'page';
            }

            // URL: suggestion'ın kendisi <a> tag'i olabilir (FiboSearch yapısı)
            // Örnek: <a href="..." class="dgwt-wcas-suggestion dgwt-wcas-suggestion-product ...">
            var clickedUrl = '';
            if (suggestion.tagName === 'A' && suggestion.href) {
                clickedUrl = suggestion.href;
            } else {
                var link = suggestion.querySelector('a[href]');
                clickedUrl = link ? link.href : (suggestion.getAttribute('data-url') || '');
            }

            // Başlık: FiboSearch .dgwt-wcas-st-title span'ı (en spesifik)
            // Fallback: .dgwt-wcas-st, strong, genel text
            var titleEl = suggestion.querySelector(
                '.dgwt-wcas-st-title, .dgwt-wcas-st span, .dgwt-wcas-sp-title, .js-dgwt-wcas-st, strong'
            );
            var clickedTitle = titleEl ? titleEl.textContent.trim() : '';
            if (!clickedTitle) {
                // Fallback: suggestion'ın tüm text'i (img alt vs. temizle)
                clickedTitle = (suggestion.textContent || '').replace(/\s+/g, ' ').trim().substring(0, 150);
            }

            sendTrack(term, type, clickedUrl, clickedTitle);
        }, true); // capture: true — FiboSearch'in kendi handler'ından önce çalışır

    })();
    </script>
    <?php
});
