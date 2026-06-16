<?php
/**
 * Custom Rewrite Rules & Author Link Filter
 *
 * @package SaltHareket\Theme
 * @version 1.1.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 1.1.0 - 2026-04-30
 * - Fix: wpse17106_author_link() - get_user_role() array dönünce str_replace fatal error düzeltildi
 * - Fix: PHP 8.3 uyumluluğu - str_replace() ikinci argüman string olmalı
 *
 * 1.0.0 - Initial release
 *
 * HOW TO USE:
 * Bu dosya custom rewrite kuralları ve author link filter'ı içerir.
 * Author URL'lerinde /author/ yerine kullanıcı rolü kullanılır.
 *
 * @example Author link filter:
 * // /author/username/ → /editor/username/ (role = editor ise)
 * // get_user_role() array dönebilir, ilk rol alınır
 *
 * @example Custom rewrite:
 * // custom_rewrite_rules() init hook'unda çalışır
 */

function custom_rewrite_rules() {
    //flush_rewrite_rules();

    //$home_id = get_option('page_on_front');

    /*add_rewrite_rule(
        '^basin-bulteni/arsiv/?$',
        'index.php?post_type=basin-bulteni&action=arsiv',
        'top'
    );*/

    if(ENABLE_MEMBERSHIP){
        global $wp_roles;
        if ( ! isset( $wp_roles ) ){
            $wp_roles = new WP_Roles();
        }
        $roles = implode("|", array_keys($wp_roles->get_names()));
        add_rewrite_rule(
            '^('.$roles.')/([^/]+)/?$',
            'index.php?author_name=$matches[2]',
            'top'
        );        
    }

}
add_action('init', 'custom_rewrite_rules');




add_filter( 'author_link', 'wpse17106_author_link', 10, 2 );
function wpse17106_author_link( $link, $author_id ){
    $role = get_user_role($author_id);
    // get_user_role array dönebilir - ilk rolü al
    if (is_array($role)) {
        $role = !empty($role) ? reset($role) : 'author';
    }
    if (empty($role)) $role = 'author';
    $link = str_replace( 'author', $role, $link );
    return $link;
}