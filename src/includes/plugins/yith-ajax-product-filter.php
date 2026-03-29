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
