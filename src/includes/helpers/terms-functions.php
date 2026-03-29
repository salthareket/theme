<?php

/**
 * Taxonomy & Term Helper Functions
 *
 * Term navigasyonu, hiyerarşi, ilişkili post sorguları,
 * arama entegrasyonu ve slug↔ID dönüşümleri.
 */

// ─── Temel Term Yardımcıları ────────────────────────────────────

function get_term_url($term_id) {
    return get_term_link((int) $term_id);
}

/**
 * Term hiyerarşisinde en üst parent'a kadar çıkar.
 */
function get_term_root($term = '', $taxonomy = '') {
    $parent = Timber::get_term($term, $taxonomy);
    if (!$parent || is_wp_error($parent)) return null;

    while ($parent->parent != 0) {
        $parent = Timber::get_term($parent->parent, $taxonomy);
        if (!$parent || is_wp_error($parent)) return null;
    }
    return $parent;
}

/**
 * Term'in kök'ten yaprak'a kadar isim hiyerarşisini döndürür.
 * Örn: ["Elektronik", "Telefon", "iPhone"]
 */
function get_term_hierarchy($taxonomy = '', $term_id = 0, $array = []) {
    $term = get_term_by('id', $term_id, $taxonomy);
    if (!$term) return $array;

    $array[] = $term->name;

    if ($term->parent > 0) {
        return get_term_hierarchy($taxonomy, $term->parent, $array);
    }

    return array_reverse($array);
}

/**
 * Parent term'leri döndürür (verilen term listesinin parent ID'lerine göre).
 */
function get_parent_terms($taxonomy, $terms) {
    $parent_ids = array_unique(wp_list_pluck($terms, 'parent'));
    return get_terms($taxonomy, ['include' => $parent_ids]);
}

// ─── Term → Post Sorguları ──────────────────────────────────────

/**
 * Taxonomy menü item'ından ilk post'u bulur (hiyerarşik).
 */
function get_taxonomy_first_post($post) {
    $taxonomy = $post->object;
    $terms    = Timber::get_terms($taxonomy, [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'parent'     => $post->object_id,
    ]);

    $term = $terms ? $terms[0] : Timber::get_term($post->object_id, $taxonomy);
    return get_terms_first_post($term);
}

/**
 * Term'in en derin child'ından ilk post'u recursive bulur.
 */
function get_terms_first_post($term) {
    if (!empty($term->children)) {
        return get_terms_first_post($term->children[0]);
    }

    return Timber::get_post([
        'tax_query' => [[
            'taxonomy' => $term->taxonomy,
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ]],
    ]);
}

/**
 * Belirli term'deki thumbnail'lı ilk post'un resmini döndürür.
 */
function get_terms_first_post_image($taxonomy, $term_id, $size = 'medium') {
    $post = Timber::get_post([
        'posts_per_page' => 1,
        'tax_query'      => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ]],
        'meta_query' => [[
            'key'     => '_thumbnail_id',
            'compare' => 'EXISTS',
        ]],
    ]);

    if (!$post) return '';

    $src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $size);
    return $src[0] ?? '';
}

/**
 * Taxonomy + children dahil toplam post sayısı.
 */
function get_category_total_post_count($taxonomy = 'category', $term_id = 0) {
    $query = new WP_Query([
        'tax_query' => [[
            'taxonomy'         => $taxonomy,
            'field'            => 'id',
            'terms'            => $term_id,
            'include_children' => true,
        ]],
        'nopaging' => true,
        'fields'   => 'ids',
    ]);
    return $query->post_count;
}

// ─── İlişkili Post Sorguları ────────────────────────────────────

/**
 * Aynı post type'tan mevcut post hariç diğer post'ları döndürür.
 */
function get_other_posts($post_id = 0, $count = 5, $orderby = 'date', $order = 'DESC') {
    return Timber::get_posts([
        'post_type'      => get_post_type($post_id),
        'posts_per_page' => $count,
        'post_status'    => 'publish',
        'post__not_in'   => [$post_id],
        'orderby'        => $orderby,
        'order'          => $order,
    ]);
}

/**
 * Post'un taxonomy'lerine göre ilişkili post'ları bulur (OR relation).
 */
function get_related_posts($post_id, $related_count, $args = []) {
    $args = wp_parse_args($args, [
        'orderby' => 'menu_order',
        'return'  => 'query',
        'forced'  => true,
    ]);

    $related_args = [
        'post_type'      => get_post_type($post_id),
        'posts_per_page' => $related_count,
        'post_status'    => 'publish',
        'post__not_in'   => [$post_id],
        'orderby'        => $args['orderby'],
        'tax_query'      => [],
    ];

    $taxonomies = get_object_taxonomies(get_post($post_id), 'names');
    if ($taxonomies) {
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);
            if (empty($terms)) continue;

            $related_args['tax_query'][] = [
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => wp_list_pluck($terms, 'slug'),
            ];
        }

        if (count($related_args['tax_query']) > 1) {
            $related_args['tax_query']['relation'] = 'OR';
        }
    }

    return ($args['return'] === 'query')
        ? Timber::get_posts($related_args)->to_array()
        : $related_args;
}

/**
 * Birden fazla taxonomy filtresiyle ortak term'leri bulur.
 * Polylang uyumlu.
 */
function get_post_terms_with_common_tax($post_type, $target_taxonomy, $tax_filters = [], $orderby = 'name', $order = 'ASC', $language = '') {
    global $wpdb;

    $allowed_orderby = ['name', 'term_id', 'slug'];
    $orderby_safe    = in_array(strtolower($orderby), $allowed_orderby) ? $orderby : 'name';
    $order_safe      = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

    $joins  = [];
    $wheres = [];

    foreach ($tax_filters as $i => $filter) {
        $n     = $i + 1;
        $tax   = $filter['taxonomy'];
        $tids  = (array) $filter['terms'];
        $op    = strtoupper($filter['operator'] ?? 'IN');

        $joins[] = "INNER JOIN {$wpdb->term_relationships} tr{$n} ON tr{$n}.object_id = p.ID
                    INNER JOIN {$wpdb->term_taxonomy} tt{$n} ON tt{$n}.term_taxonomy_id = tr{$n}.term_taxonomy_id";

        $ph        = implode(',', array_fill(0, count($tids), '%d'));
        $wheres[]  = $wpdb->prepare("tt{$n}.taxonomy = %s AND tt{$n}.term_id {$op} ({$ph})", array_merge([$tax], $tids));
    }

    $sql = $wpdb->prepare(
        "SELECT DISTINCT t.term_id
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt_target ON tt_target.term_id = t.term_id
         INNER JOIN {$wpdb->term_relationships} tr_target ON tr_target.term_taxonomy_id = tt_target.term_taxonomy_id
         INNER JOIN {$wpdb->posts} p ON p.ID = tr_target.object_id
         " . implode("\n", $joins) . "
         WHERE p.post_type = %s AND p.post_status = 'publish' AND tt_target.taxonomy = %s"
         . (!empty($wheres) ? ' AND ' . implode(' AND ', $wheres) : '')
         . " ORDER BY t.{$orderby_safe} {$order_safe}",
        $post_type, $target_taxonomy
    );

    $rows  = $wpdb->get_col($sql);
    $terms = [];

    foreach ($rows as $term_id) {
        if (function_exists('pll_get_term')) {
            $lang = $language ?: pll_current_language();
            $translated = pll_get_term($term_id, $lang);
            if ($translated) {
                $terms[] = get_term($translated, $target_taxonomy);
                continue;
            }
        }
        $terms[] = get_term($term_id, $target_taxonomy);
    }

    return $terms;
}

// ─── Hiyerarşik Sıralama ───────────────────────────────────────

/**
 * Düz term listesini parent-child hiyerarşisine dönüştürür.
 * Opsiyonel olarak menü formatına çevirir.
 */
function sort_terms_hierarchicaly(array &$cats, array &$into = [], $parentId = 0, $menu_order = 0, $menu_parent = 0) {
    foreach ($cats as $i => $cat) {
        if ($cat->parent == $parentId) {
            $into[] = $cat;
            unset($cats[$i]);
        }
    }

    usort($into, fn($a, $b) => strcmp($a->name, $b->name));

    foreach ($into as $topCat) {
        $topCat->children = [];
        sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
    }

    if ($menu_order > 0 && $menu_parent > 0) {
        return sorted_terms_to_menu($into, $menu_order, $menu_parent);
    }

    return $into;
}

/**
 * Hiyerarşik term'leri WP menü item formatına dönüştürür.
 */
function sorted_terms_to_menu($terms, $menu_order, $menu_parent) {
    foreach ($terms as $term) {
        $menu_order++;
        $term->ID               = 2000000 + $menu_order + $term->term_id;
        $term->menu_item_parent = $menu_parent;
        $term->post_type        = 'nav_menu_item';
        $term->menu_order       = $menu_order;
        $term->title            = $term->name;
        $term->object           = $term->taxonomy;
        $term->type             = 'taxonomy';
        $term->db_id            = -1 * $term->term_id;
        $term->url              = get_term_url($term->term_id);

        if (!empty($term->children)) {
            sorted_terms_to_menu($term->children, $menu_order, $term->term_id);
        }
    }
}

/**
 * Hiyerarşik term ağacını düz listeye çevirir (depth-first).
 */
function sort_terms_hierarchicaly_single($arr = [], $arr_new = []) {
    if (empty($arr)) return $arr_new;

    foreach ($arr as $item) {
        $children = $item->children ?? [];
        unset($item->children);
        $arr_new[] = $item;
        $arr_new   = sort_terms_hierarchicaly_single($children, $arr_new);
    }
    return $arr_new;
}

// ─── Arama Entegrasyonu ─────────────────────────────────────────

/**
 * WP_Query'ye term arama sonuçlarını ekler (pagination dahil).
 */
function wpse342309_search_terms($query, $taxonomy) {
    $per_page = absint($query->get('posts_per_page')) ?: max(10, get_option('posts_per_page'));
    $paged    = max(1, $query->get('paged'));
    $offset   = ($paged - 1) * $per_page;

    $args = [
        'taxonomy' => $taxonomy,
        'search'   => $query->get('s'),
        'number'   => $per_page,
        'offset'   => $offset,
    ];

    $terms = get_terms($args);
    $query->terms = (!is_wp_error($terms) && !empty($terms)) ? $terms : [];

    $args['offset'] = 0;
    $args['fields'] = 'count';
    $query->found_terms = get_terms($args);

    $query->term_count     = count($query->terms);
    $query->terms_per_page = $per_page;
    $query->is_all_terms   = ((int) $per_page === $query->term_count);

    $query->set('posts_per_page', max(1, $per_page - $query->term_count));
    $query->set('offset', $query->term_count ? 0 : max(0, $offset - $query->found_terms));
}

// ─── Slug ↔ ID Dönüşümü ────────────────────────────────────────

/**
 * Term slug listesinden ID listesi döndürür.
 * SQL injection korumalı ($wpdb->prepare).
 */
function get_term_slugs_to_ids($slugs = [], $taxonomy = '') {
    global $wpdb;

    if (empty($slugs) || empty($taxonomy)) return [];
    if (!is_array($slugs)) $slugs = [$slugs];

    $placeholders = implode(', ', array_fill(0, count($slugs), '%s'));
    $query = $wpdb->prepare(
        "SELECT DISTINCT t.term_id as id
         FROM {$wpdb->term_taxonomy} tt
         INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
         WHERE tt.taxonomy = %s AND t.slug IN ({$placeholders})",
        array_merge([$taxonomy], $slugs)
    );

    $rows = $wpdb->get_results($query);
    return $rows ? wp_list_pluck($rows, 'id') : [];
}

// ─── HTML Tag Temizleme ─────────────────────────────────────────

/**
 * Belirtilen tag'ler HARİÇ tüm tag'leri (içerikleriyle birlikte) siler.
 * $invert = true ise sadece belirtilen tag'leri siler.
 */
function strip_tags_content($text, $tags = '', $invert = false) {
    preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $parsed);
    $tags = array_unique($parsed[1]);

    if (!empty($tags)) {
        $pattern = implode('|', array_map('preg_quote', $tags));
        return $invert
            ? preg_replace('@<(' . $pattern . ')\b.*?>.*?</\1>@si', '', $text)
            : preg_replace('@<(?!(?:' . $pattern . ')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
    }

    return $invert ? $text : preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
}