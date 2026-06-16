<?php

/**
 * YITH Ajax Product Filter — Term count fix, preset loader, pre_query hook.
 */

// ─── Pre Query — Taxonomy filtresi ──────────────────────────

function yith_wcan_pre_query($query) {
    if ($query->is_main_query() && (is_product_category() || is_shop()) && isset($_GET['yith_wcan'])) {
        if (isset($_GET['product_cat'])) {
            $tax_query_obj = $query->tax_query;
            $tax_query_obj->queries[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => [sanitize_text_field($_GET['product_cat'])],
                'operator' => 'IN',
            ];
            $tax_query = [];
            foreach ($tax_query_obj->queries as $q) {
                $tax_query[] = $q;
            }
            $query->set('tax_query', $tax_query);
        }
    }
    return $query;
}
// add_action('pre_get_posts', 'yith_wcan_pre_query');

// ─── Term Count Fix (Variation dahil) ───────────────────────

function yith_wcan_term_count($count, $term) {
    $items = wc_get_products([
        'status'   => 'publish',
        'category' => [$term->slug],
        'return'   => 'objects',
    ]);

    $total = 0;
    foreach ($items as $item) {
        $total += $item->is_type('variable') ? 1 + count($item->get_children()) : 1;
    }

    return $total > 0 ? $total : $count;
}
add_filter('yith_wcan_term_count', 'yith_wcan_term_count', 10, 2);

// ─── Filter Presets ─────────────────────────────────────────

function yith_wcan_get_filter_presets() {
    $posts = get_posts([
        'post_type'  => 'yith_wcan_preset',
        'meta_query' => [['key' => '_enabled', 'value' => 'yes']],
    ]);

    $output = [];
    foreach ($posts as $post) {
        $output[$post->post_name] = $post->post_title;
    }
    return $output;
}

add_filter('acf/load_field/key=field_6561da035862e', 'acf_woo_shop_wcan_filters');
function acf_woo_shop_wcan_filters($field) {
    $field['choices'] = [];
    $presets = yith_wcan_get_filter_presets();
    if (is_array($presets)) {
        foreach ($presets as $key => $preset) {
            $field['choices'][$key] = $preset;
        }
    }
    return $field;
}

// ─── Term Page JS (Opsiyonel) ───────────────────────────────

function add_custom_js_for_term_page() {
    if (!is_tax()) return;

    global $wp_query;
    $query_vars = $wp_query->query_vars;
    if (!array_key_exists('taxonomy', $query_vars)) return;

    $taxonomy = $query_vars['taxonomy'];
    $tax      = str_replace('pa_', '', $taxonomy);
    $shop_url = get_permalink(wc_get_page_id('shop'));
    ?>
    <script>
        debugJS('Term page JS: <?php echo esc_js($tax); ?> <?php echo esc_url($shop_url); ?>');
    </script>
    <?php
}
// add_action('wp_footer', 'add_custom_js_for_term_page');

// ─── YITH Mobile Modal Opener'ı devre dışı bırak ───────────
// Biz kendi offcanvas toggle butonumuzu kullanıyoruz.
// YITH'in kendi toggle butonunu eklemesini engelle.
add_filter( 'yith_wcan_modal_on_mobile', '__return_false' );
add_filter( 'option_yith_wcan_modal_on_mobile', function() { return 'no'; } );
add_filter( 'yith_wcan_get_option_yith_wcan_modal_on_mobile', function() { return 'no'; } );

// ─── YITH WCAN Color Swatch — Variation Swatches Köprüsü ────
// YITH preset'te "use all terms" seçiliyse color_1'i boş bırakıyor.
// Variation Swatches plugin'inin product_attribute_color meta key'inden rengi inject et.
// Dual color için secondary_color meta key'ini de inject et.
add_filter( 'yith_wcan_attribute_filter_item_args', function( $term_options, $term_id ) {
    if ( isset( $term_options['mode'] ) && $term_options['mode'] === 'color' && empty( $term_options['color_1'] ) ) {
        $color = get_term_meta( $term_id, 'product_attribute_color', true );
        if ( ! empty( $color ) ) {
            $term_options['color_1'] = $color;
        }
    }
    // Dual color: secondary_color meta key'inden al
    if ( ! empty( $term_options['color_1'] ) && empty( $term_options['color_2'] ) ) {
        $is_dual   = get_term_meta( $term_id, 'is_dual_color', true );
        if ( $is_dual === 'yes' || $is_dual === '1' || $is_dual === true ) {
            $color_2 = get_term_meta( $term_id, 'secondary_color', true );
            if ( ! empty( $color_2 ) ) {
                $term_options['color_2'] = $color_2;
            }
        }
    }
    return $term_options;
}, 10, 2 );

// Aynısını genel tax filter için de yap
add_filter( 'yith_wcan_tax_filter_item_args', function( $term_options, $term_id ) {
    if ( isset( $term_options['mode'] ) && $term_options['mode'] === 'color' && empty( $term_options['color_1'] ) ) {
        $color = get_term_meta( $term_id, 'product_attribute_color', true );
        if ( ! empty( $color ) ) {
            $term_options['color_1'] = $color;
        }
    }
    if ( ! empty( $term_options['color_1'] ) && empty( $term_options['color_2'] ) ) {
        $is_dual = get_term_meta( $term_id, 'is_dual_color', true );
        if ( $is_dual === 'yes' || $is_dual === '1' || $is_dual === true ) {
            $color_2 = get_term_meta( $term_id, 'secondary_color', true );
            if ( ! empty( $color_2 ) ) {
                $term_options['color_2'] = $color_2;
            }
        }
    }
    return $term_options;
}, 10, 2 );

// ─── YITH WCAN Script Inject Guard ──────────────────────────
// YITH AJAX filter apply edince sayfanın script'lerini tekrar inject ediyor.
// Bu, CartManager, BrowserDetect vb. global değişkenlerin redeclaration hatasına yol açıyor.
// Çözüm: wp_footer'da JS ile jQuery.fn.html override - inject edilen script'leri filtrele.
add_action('wp_footer', function() {
    ?>
    <script>
    (function($) {
        // YITH AJAX inject sırasında bizim script'lerimizi tekrar çalıştırmasını engelle
        var _blockedScriptPatterns = [
            'functions.min.js', 'pre-combined.min.js', 'main-combined.min.js',
            'footer-', 'pre-', 'main-'
        ];

        // jQuery html() override - inject edilen HTML'deki script'leri filtrele
        var _origHtml = $.fn.html;
        $.fn.html = function(value) {
            if (typeof value === 'string' && value.indexOf('<script') !== -1) {
                // Script tag'lerini parse et ve bizimkileri kaldır
                var $temp = $('<div>').append($.parseHTML(value, document, true));
                $temp.find('script[src]').each(function() {
                    var src = $(this).attr('src') || '';
                    for (var i = 0; i < _blockedScriptPatterns.length; i++) {
                        if (src.indexOf(_blockedScriptPatterns[i]) !== -1) {
                            $(this).remove();
                            break;
                        }
                    }
                });
                return _origHtml.call(this, $temp.html());
            }
            return _origHtml.apply(this, arguments);
        };
    })(jQuery);
    </script>
    <?php
}, 20);


// Bootstrap offcanvas açılıp kapanırken history state push ediyor.
// YITH bunu popstate ile yakalayıp sayfayı reload ediyor.
// Çözüm: popstate event'ini offcanvas kaynaklıysa engelle.
add_action('wp_footer', function() {
    ?>
    <script>
    (function() {
        // Bootstrap offcanvas state push'larını işaretle
        document.addEventListener('show.bs.offcanvas', function() {
            window._offcanvas_opening = true;
        });
        document.addEventListener('shown.bs.offcanvas', function() {
            window._offcanvas_opening = false;
            window._offcanvas_open = true;
        });
        document.addEventListener('hide.bs.offcanvas', function() {
            window._offcanvas_closing = true;
        });
        document.addEventListener('hidden.bs.offcanvas', function() {
            window._offcanvas_closing = false;
            window._offcanvas_open = false;
            // Kapandıktan sonra kısa süre popstate'i engelle
            window._block_popstate = true;
            setTimeout(function() { window._block_popstate = false; }, 300);
        });

        // YITH'in popstate listener'ından önce çalış (capture: true)
        window.addEventListener('popstate', function(e) {
            if (window._block_popstate || window._offcanvas_open || window._offcanvas_closing) {
                e.stopImmediatePropagation();
            }
        }, true); // capture phase - YITH'den önce çalışır
    })();
    </script>
    <?php
}, 5); // YITH'den önce (YITH genelde 10 kullanır)
