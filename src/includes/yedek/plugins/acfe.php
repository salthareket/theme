<?php

/**
 * ACF Extended — Field group category seeding + delete protection.
 */

if (is_admin()) {
    add_action('admin_init', function() {
        $version = 'v1.0';
        $opt     = 'acfe_categories_seeded_' . $version;
        if (get_option($opt)) return;

        $taxonomy = 'acf-field-group-category';
        if (!taxonomy_exists($taxonomy)) return;

        $defaults = [
            'general' => 'General',
            'block'   => 'Block',
            'common'  => 'Common',
        ];

        $theme = wp_get_theme();
        if ($theme) {
            $defaults[$theme->get('TextDomain')] = $theme->get('Name');
        }

        foreach ($defaults as $slug => $name) {
            $term = term_exists($slug, $taxonomy);
            if (!$term) {
                $result = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
                if (!is_wp_error($result)) {
                    add_term_meta($result['term_id'], 'delete-protect', true, true);
                }
            } else {
                $tid = is_array($term) ? $term['term_id'] : $term;
                add_term_meta($tid, 'delete-protect', true, true);
            }
        }

        update_option($opt, true);

        foreach (['create_term', 'edit_term', 'edited_term', 'saved_term'] as $hook) {
            add_filter($hook, 'on_save_acf_group_category', 10, 3);
        }
    }, 999);

    function on_save_acf_group_category($term_id, $tt_id, $taxonomy) {
        if ($taxonomy !== 'acf-field-group-category') return;
        add_term_meta($term_id, 'delete-protect', true, true);
    }
}

/**
 * ACF field group'larını category term'lerine göre getirir.
 */
function acf_get_category_posts($terms = [], $mustHave = true) {
    $args = [
        'post_type'      => 'acf-field-group',
        'posts_per_page' => -1,
    ];

    $tax_query = [];
    if ($mustHave && count($terms) > 1) {
        $tax_query['relation'] = 'AND';
        foreach ($terms as $term) {
            $tax_query[] = ['taxonomy' => 'acf-field-group-category', 'field' => 'id', 'terms' => $term];
        }
    } else {
        $tax_query[] = ['taxonomy' => 'acf-field-group-category', 'field' => 'id', 'terms' => $terms];
    }

    $args['tax_query'] = $tax_query;
    return get_posts($args);
}
