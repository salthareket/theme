<?php

/**
 * Taxonomy Registration — Contact Types + Delete Protection
 */

// ─── Contact Type Taxonomy ──────────────────────────────────

register_taxonomy('contact-type', ['post', 'contact'], [
    'public'            => true,
    'single_value'      => false,
    'show_admin_column' => true,
    'hierarchical'      => true,
    'show_in_rest'      => true,
    'labels' => [
        'name'                       => 'Contact Types',
        'singular_name'              => 'Contact Type',
        'menu_name'                  => 'Contact Type',
        'search_items'               => 'Search Contact Type',
        'popular_items'              => 'Popular Contact Types',
        'all_items'                  => 'All Contact Types',
        'edit_item'                  => 'Edit Contact Type',
        'update_item'                => 'Update Contact Type',
        'add_new_item'               => 'Add New Contact Type',
        'new_item_name'              => 'New Contact Type Name',
        'separate_items_with_commas' => 'Separate Contact Type with commas',
        'add_or_remove_items'        => 'Add or remove Contact Type',
        'choose_from_most_used'      => 'Choose from the most popular Contact Types',
    ],
    'rewrite' => [
        'with_front' => true,
        'slug'       => 'contact/contact-type',
    ],
    'capabilities' => [
        'manage_terms' => 'edit_users',
        'edit_terms'   => 'edit_users',
        'delete_terms' => 'edit_users',
        'assign_terms' => 'read',
    ],
]);

// ─── Admin: Default Term Seeding ────────────────────────────

if (is_admin()) {

    add_action('init', function() {
        // Version flag — sadece yeni term eklendiğinde güncelle
        $version  = 'v1.0';
        $opt_name = 'contact_type_seeded_' . $version;
        if (get_option($opt_name)) return;

        $defaults = [
            'main'     => 'Ana Lokasyon',
            'standard' => 'Standart Lokasyon',
        ];

        foreach ($defaults as $slug => $name) {
            $term = term_exists($slug, 'contact-type');
            if (!$term) {
                $result = wp_insert_term($name, 'contact-type', ['slug' => $slug]);
                if (!is_wp_error($result)) {
                    $term_id = $result['term_id'];
                } else {
                    continue;
                }
            } else {
                $term_id = is_array($term) ? $term['term_id'] : $term;
            }

            add_term_meta($term_id, 'delete-protect', true, true);

            $option_key = 'contact_type_' . $slug;
            if (!get_option($option_key)) {
                add_option($option_key, $term_id);
            }
        }

        update_option($opt_name, true);
    }, 999);

    // Yeni contact-type term kaydedildiğinde option + delete-protect ekle
    foreach (['create_term', 'edit_term', 'edited_term', 'saved_term'] as $hook) {
        add_filter($hook, 'contact_type_on_save', 10, 3);
    }
}

function contact_type_on_save($term_id, $tt_id, $taxonomy) {
    if ($taxonomy !== 'contact-type') return;

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) return;

    $option_key = 'contact_type_' . $term->slug;
    if (!get_option($option_key)) {
        add_term_meta($term_id, 'delete-protect', true, true);
        add_option($option_key, $term_id);
    }
}

// ─── Delete Protection ──────────────────────────────────────

/**
 * Korumalı term'lerin edit formunda delete butonunu gizler.
 */
add_action('category_edit_form', function($term, $taxonomy) {
    if (get_term_meta($term->term_id, 'delete-protect', true)) {
        echo '<style>#tag-' . (int) $term->term_id . ' .delete { display: none !important; }</style>';
    }
}, 10, 2);

/**
 * Korumalı term silinmeye çalışılırsa engeller.
 */
add_action('pre_delete_term', function($term_id) {
    if (!get_term_meta($term_id, 'delete-protect', true)) return;

    $term = get_term($term_id);
    $name = $term ? $term->name : "#{$term_id}";

    wp_die(
        '<h2>Delete Protection Active!</h2>' .
        'You cannot delete "' . esc_html($name) . '"<br>' .
        '<a href="javascript:history.back()">Go Back</a>',
        'Protected Term',
        ['response' => 403]
    );
});
