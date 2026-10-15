<?php

/**
 * Post Type Registration — Contact
 *
 * contact post type hardcoded olarak register edilir.
 * contact-type taxonomy sadece contact post type'ına bağlıdır.
 * ACF field group (group_63e6945ee6760) options_contact_post_types seçimine göre
 * dinamik olarak ek post type'lara assign edilir.
 *
 * Template post type → apps/templates/bootstrap.php
 */

// ─── CONTACT POST TYPE ───────────────────────────────────────

register_post_type('contact', [
    'labels' => [
        'name'               => 'Contacts',
        'singular_name'      => 'Contact',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Contact',
        'edit_item'          => 'Edit Contact',
        'new_item'           => 'New Contact',
        'all_items'          => 'All Contacts',
        'view_item'          => 'View Contact',
        'search_items'       => 'Search Contacts',
        'not_found'          => 'No Contacts found',
        'not_found_in_trash' => 'No Contacts found in Trash',
        'menu_name'          => 'Contacts',
    ],
    'public'              => true,
    'publicly_queryable'  => true,
    'show_ui'             => true,
    'show_in_menu'        => 'iletisim2',
    'query_var'           => true,
    'rewrite'             => ['slug' => 'contact'],
    'capability_type'     => 'post',
    'has_archive'         => false,
    'hierarchical'        => false,
    'menu_position'       => 5,
    'supports'            => ['title', 'editor', 'thumbnail'],
    'taxonomies'          => ['contact-type'],
]);

if ( is_admin() ) {
    add_action('admin_menu', function() {
        add_submenu_page('anasayfa', 'İletişim Bilgileri', 'İletişim Bilgileri', 'edit_theme_options', 'edit.php?post_type=contact', '', 10);
        add_submenu_page('anasayfa', 'İletişim Kategorileri', 'İletişim Kategorileri', 'edit_theme_options', 'edit-tags.php?taxonomy=contact-type&post_type=contact', '', 11);
    });
}

// ─── CONTACT-TYPE TAXONOMY ───────────────────────────────────

add_action( 'init', function() {

    register_taxonomy( 'contact-type', 'contact', [
        'public'            => true,
        'show_admin_column' => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'labels' => [
            'name'          => 'Contact Types',
            'singular_name' => 'Contact Type',
            'menu_name'     => 'Contact Type',
            'all_items'     => 'All Contact Types',
            'edit_item'     => 'Edit Contact Type',
            'add_new_item'  => 'Add New Contact Type',
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
    ] );

    // Default term seeding (bir kez)
    if ( is_admin() ) {
        $opt = 'contact_type_seeded_v1.0';
        if ( ! get_option($opt) ) {
            $defaults = ['main' => 'Ana Lokasyon', 'standard' => 'Standart Lokasyon'];
            foreach ( $defaults as $slug => $name ) {
                if ( ! term_exists($slug, 'contact-type') ) {
                    $r = wp_insert_term($name, 'contact-type', ['slug' => $slug]);
                    if ( ! is_wp_error($r) ) {
                        add_term_meta($r['term_id'], 'delete-protect', true, true);
                        add_option('contact_type_' . $slug, $r['term_id']);
                    }
                }
            }
            update_option($opt, true);
        }
    }

}, 20 );

// ─── ACF Field Group — Runtime location (fallback) ───────────
// acf/save_post ile DB güncellenir, bu filter ara durumlarda çalışır

add_filter( 'acf/load_field_group', function( $group ) {
    if ( ( $group['key'] ?? '' ) !== 'group_63e6945ee6760' ) return $group;

    $extra = get_option( 'options_contact_post_types', [] );
    if ( ! is_array($extra) ) $extra = ! empty($extra) ? [$extra] : [];
    $flat = [];
    array_walk_recursive($extra, function($v) use (&$flat) {
        if ( is_string($v) && ! empty($v) ) $flat[] = $v;
    });
    $flat[] = 'contact';
    $extra_types = array_values( array_unique( array_filter($flat) ) );

    $location = $group['location'] ?? [];
    $existing = [];
    foreach ( $location as $group_rules ) {
        foreach ( $group_rules as $rule ) {
            if ( ($rule['param'] ?? '') === 'post_type' && ($rule['operator'] ?? '') === '==' ) {
                $existing[] = $rule['value'];
            }
        }
    }
    foreach ( $extra_types as $pt ) {
        if ( ! in_array($pt, $existing, true) ) {
            $location[] = [[ 'param' => 'post_type', 'operator' => '==', 'value' => $pt ]];
        }
    }
    $group['location'] = $location;
    return $group;
} );

// ─── ACF Options Save → Field Group DB Update ────────────────
// contact_post_types seçimi değiştiğinde field group location'ı DB'ye yaz
// Böylece her page load'da kurallar doğru çalışır

add_action( 'acf/save_post', function( $post_id ) {
    if ( $post_id !== 'options' ) return;
    if ( ! function_exists('acf_get_field_group') || ! function_exists('acf_update_field_group') ) return;

    $group = acf_get_field_group( 'group_63e6945ee6760' );
    if ( ! $group ) return;

    $extra = get_field( 'contact_post_types', 'options' ) ?: [];
    if ( ! is_array($extra) ) $extra = ! empty($extra) ? [$extra] : [];
    $flat = [];
    array_walk_recursive($extra, function($v) use (&$flat) {
        if ( is_string($v) && ! empty($v) ) $flat[] = $v;
    });
    $flat[] = 'contact';
    $post_types = array_values( array_unique( array_filter($flat) ) );

    $location = [];
    foreach ( $post_types as $pt ) {
        $location[] = [[ 'param' => 'post_type', 'operator' => '==', 'value' => $pt ]];
    }

    $group['location'] = $location;
    acf_update_field_group( $group );

}, 20 );

// ─── ACF Field — Contact Post Types Choices Dinamik ──────────
// contact_post_types select field'ına tüm public post type'ları listele

add_filter( 'acf/load_field/name=contact_post_types', function( $field ) {
    $post_types = get_post_types( ['public' => true, 'show_ui' => true], 'objects' );
    $choices    = [];
    $skip       = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'contact', 'template'];
    foreach ( $post_types as $pt ) {
        if ( in_array($pt->name, $skip, true) ) continue;
        $choices[$pt->name] = $pt->labels->name . ' (' . $pt->name . ')';
    }
    $field['choices'] = $choices;
    return $field;
} );

// ─── Delete Protection ───────────────────────────────────────

add_action( 'category_edit_form', function( $term, $taxonomy ) {
    if ( get_term_meta($term->term_id, 'delete-protect', true) ) {
        echo '<style>#tag-' . (int)$term->term_id . ' .delete { display: none !important; }</style>';
    }
}, 10, 2 );

add_action( 'pre_delete_term', function( $term_id ) {
    if ( ! get_term_meta($term_id, 'delete-protect', true) ) return;
    $term = get_term($term_id);
    $name = $term ? $term->name : "#{$term_id}";
    wp_die(
        '<h2>Delete Protection Active!</h2>' .
        'You cannot delete "' . esc_html($name) . '"<br>' .
        '<a href="javascript:history.back()">Go Back</a>',
        'Protected Term',
        ['response' => 403]
    );
} );
