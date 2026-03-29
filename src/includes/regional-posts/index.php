<?php

/**
 * Regional Posts Module
 *
 * Kullanıcının bölgesine göre post/term filtreleme.
 * - "region" taxonomy'sini register eder
 * - pre_get_posts ile query'lere region filtresi ekler
 * - get_terms ile bölgesiz term'leri gizler
 * - Country code → Region ID çözümlemesi
 *
 * Yükleme: variables.php → if (ENABLE_REGIONAL_POSTS) include
 */

// ─── Ayarları Tek Noktadan Çek (Her request'te 1 kez) ──────

function _regional_posts_settings() {
    static $settings = null;
    if ($settings === null) {
        $settings = get_option('options_regional_post_settings') ?: [];
    }
    return $settings;
}

function _regional_post_types() {
    static $types = null;
    if ($types === null) {
        $types = array_column(_regional_posts_settings(), 'post_type');
    }
    return $types;
}

function _regional_taxonomies() {
    static $taxes = null;
    if ($taxes === null) {
        $taxes = array_column(_regional_posts_settings(), 'taxonomy');
    }
    return $taxes;
}

// ─── Taxonomy & ACF Field Group Register ────────────────────

add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) return;

    $post_types = _regional_post_types();
    if (empty($post_types)) return;

    register_taxonomy('region', $post_types, [
        'public'            => true,
        'single_value'      => false,
        'show_admin_column' => true,
        'labels' => [
            'name'                       => 'Regions',
            'singular_name'              => 'Region',
            'menu_name'                  => 'Regions',
            'search_items'               => 'Search Regions',
            'popular_items'              => 'Popular Regions',
            'all_items'                  => 'All Regions',
            'edit_item'                  => 'Edit Region',
            'update_item'                => 'Update Region',
            'add_new_item'               => 'Add New Region',
            'new_item_name'              => 'New Region',
            'separate_items_with_commas' => 'Separate Regions with commas',
            'add_or_remove_items'        => 'Add or remove Region',
            'choose_from_most_used'      => 'Choose from the most popular Region',
        ],
        'rewrite' => [
            'with_front' => false,
        ],
        'capabilities' => [
            'manage_terms' => 'edit_posts',
            'edit_terms'   => 'edit_posts',
            'delete_terms' => 'edit_posts',
            'assign_terms' => 'read',
        ],
    ]);

    acf_add_local_field_group([
        'key'                   => 'group_646230ff021b6',
        'title'                 => 'Region Settings',
        'fields' => [[
            'key'               => 'field_646230ff1262b',
            'label'             => 'Country',
            'name'              => 'country',
            'type'              => 'select',
            'required'          => 0,
            'choices'           => [],
            'return_format'     => 'value',
            'multiple'          => 1,
            'allow_null'        => 0,
            'ui'                => 1,
            'ajax'              => 0,
        ]],
        'location' => [[[
            'param'    => 'taxonomy',
            'operator' => '==',
            'value'    => 'region',
        ]]],
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => [
            'block_editor', 'the_content', 'excerpt', 'discussion',
            'comments', 'format', 'featured_image', 'categories',
            'tags', 'send-trackbacks',
        ],
        'active'         => true,
        'acfe_autosync'  => ['json'],
    ]);
});

// ─── Pre Get Posts — Region Filtresi ────────────────────────

add_action('pre_get_posts', 'regional_posts_prequery');

function regional_posts_prequery($query) {
    if (is_admin() || !$query->is_main_query()) return;

    $post_type  = $query->get('post_type');
    $user_region = Data::get('site_config.user_region');

    // Boş region varsa filtre ekleme
    if (empty($user_region)) return;

    $should_filter = false;

    // Search query'leri her zaman filtrele
    if ($query->is_search) {
        $should_filter = true;
    }

    // Post type regional listede mi?
    if (in_array($post_type, _regional_post_types())) {
        $should_filter = true;
    }

    // Archive sayfasında regional taxonomy mi gösteriliyor?
    if (is_archive()) {
        foreach (_regional_taxonomies() as $tax) {
            if (is_tax($tax)) {
                $should_filter = true;
                break;
            }
        }
    }

    if (!$should_filter) return;

    $tax_query = $query->get('tax_query') ?: [];
    $tax_query['relation'] = 'AND';
    $tax_query[] = [
        'taxonomy' => 'region',
        'field'    => 'term_id',
        'terms'    => $user_region,
        'operator' => 'IN',
    ];
    $query->set('tax_query', $tax_query);
}

// ─── Get Terms Filter — Bölgesiz Term'leri Gizle ────────────

add_filter('get_terms', 'regional_posts_filter_terms', 10, 4);

function regional_posts_filter_terms($terms, $taxonomies, $args, $term_query) {
    if (is_admin() || empty($terms)) return $terms;

    $regional_taxes = _regional_taxonomies();
    if (empty($regional_taxes)) return $terms;

    // Bu sorgu regional taxonomy'lerden birini içeriyor mu?
    $has_match = false;
    foreach ($regional_taxes as $tax) {
        if (in_array($tax, $taxonomies)) {
            $has_match = true;
            break;
        }
    }
    if (!$has_match) return $terms;

    // Sonsuz döngüyü önle: filtreyi geçici kaldır
    remove_filter('get_terms', 'regional_posts_filter_terms', 10);

    $remove_keys = [];
    foreach ($terms as $key => $term) {
        $term_obj = new Term($term);
        if (!$term_obj->get_country_post_count()) {
            $remove_keys[] = $key;
        }
    }

    foreach ($remove_keys as $k) {
        unset($terms[$k]);
    }

    // Filtreyi geri ekle
    add_filter('get_terms', 'regional_posts_filter_terms', 10, 4);

    return $terms;
}

// ─── Country Code → Region ID ───────────────────────────────

function get_region_by_country_code($code = '') {
    if (empty($code)) return [];

    $fallback = (array) get_option('options_region_main');

    $regions = Timber::get_terms([
        'taxonomy'   => 'region',
        'hide_empty' => false,
        'meta_query' => [[
            'key'     => 'country',
            'value'   => serialize(strtoupper($code)),
            'compare' => 'LIKE',
        ]],
    ]);

    if ($regions && !is_wp_error($regions)) {
        return wp_list_pluck($regions, 'ID');
    }

    return $fallback;
}