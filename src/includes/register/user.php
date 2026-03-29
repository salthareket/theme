<?php

/**
 * User Taxonomy Support
 *
 * WordPress native olarak user'lara taxonomy atamayı desteklemez.
 * Bu dosya WP_User_Query'ye fake tax_query desteği ekleyerek
 * user'ları taxonomy term'lerine göre filtrelemeyi mümkün kılar.
 *
 * Kullanım: Simple User Listing plugin veya custom user query'ler.
 */

// ─── Örnek: User Taxonomy Register ─────────────────────────
// Yeni bir user taxonomy eklemek istersen bu şablonu kullan:
//
// register_taxonomy('employment-type', ['user'], [
//     'public'            => true,
//     'single_value'      => false,
//     'show_admin_column' => true,
//     'hierarchical'      => true,
//     'labels' => [
//         'name'          => 'Employment Types',
//         'singular_name' => 'Employment Type',
//         'menu_name'     => 'Employment Type',
//         'search_items'  => 'Search Employment Type',
//         'all_items'     => 'All Employment Types',
//         'edit_item'     => 'Edit Employment Type',
//         'update_item'   => 'Update Employment Type',
//         'add_new_item'  => 'Add New Employment Type',
//         'new_item_name' => 'New Employment Type Name',
//     ],
//     'rewrite' => [
//         'with_front' => true,
//         'slug'       => 'author/employment-type',
//     ],
//     'capabilities' => [
//         'manage_terms' => 'edit_users',
//         'edit_terms'   => 'edit_users',
//         'delete_terms' => 'edit_users',
//         'assign_terms' => 'read',
//     ],
// ]);

// ─── Simple User Listing — Tax Query Desteği ────────────────

/**
 * SUL plugin'inin user query args'ına tax_query parametresi ekler.
 */
add_filter('sul_user_query_args', function($args, $query_id, $atts) {
    if (!empty($atts['taxonomy']) && !empty($atts['terms'])) {
        $args['tax_query'] = [[
            'taxonomy' => $atts['taxonomy'],
            'field'    => 'slug',
            'terms'    => explode('|', $atts['terms']),
        ]];
    }
    return $args;
}, 10, 3);

// ─── WP_User_Query — Fake Tax Query ────────────────────────

/**
 * WP_User_Query native olarak tax_query desteklemez.
 * Bu hook, query_vars'taki tax_query'yi SQL'e çevirip
 * users tablosuna JOIN/WHERE olarak ekler.
 */
add_action('pre_user_query', function($query) {
    global $wpdb;

    if (empty($query->query_vars['tax_query']) || !is_array($query->query_vars['tax_query'])) return;

    $sql = get_tax_sql($query->query_vars['tax_query'], $wpdb->users, 'ID');

    if (!empty($sql['join']))  $query->query_from  .= $sql['join'];
    if (!empty($sql['where'])) $query->query_where .= $sql['where'];
});

// ─── Helper: User'ın Term'lerini Getir ──────────────────────

/**
 * Belirli bir user'ın belirli taxonomy'deki term'lerini döndürür.
 *
 * @param int|WP_User $user     User ID veya WP_User objesi
 * @param string      $taxonomy Taxonomy adı
 * @param array       $args     wp_get_object_terms args
 * @return array|false
 */
function get_terms_for_user($user = false, $taxonomy = '', $args = ['fields' => 'all_with_object_id']) {
    $user_id = is_object($user) ? $user->ID : absint($user);
    if (empty($user_id)) return false;

    return wp_get_object_terms($user_id, $taxonomy, $args);
}
