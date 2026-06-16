<?php

/**
 * YASR (Yet Another Stars Rating) plugin entegrasyonu.
 *
 * Rating fonksiyonları Reviews class'ına delegate edildi.
 * Bu dosya sadece YASR plugin hook'larını ve admin UI gizleme işlemlerini içerir.
 *
 * @package SaltHareket
 */

// =========================================================================
// RATING FONKSİYONLARI — Reviews class'ına delegate
// =========================================================================

/**
 * Post bazlı tekil kullanıcı oyu (yasr_log tablosundan).
 * YASR plugin'in kendi tablosunu kullanır — Reviews class'ından bağımsız.
 *
 * @deprecated Yeni kodda Reviews::rating($post_id, 'post') kullanın.
 */
function get_star_vote( int $post_id = 0, int $user_id = 0 ): ?string {
    global $wpdb;
    $table = $wpdb->prefix . 'yasr_log';

    if ( $user_id ) {
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT vote FROM {$table} WHERE post_id = %d AND user_id = %d ORDER BY id ASC LIMIT 1",
            $post_id, $user_id
        ) );
    }

    return $wpdb->get_var( $wpdb->prepare(
        "SELECT vote FROM {$table} WHERE post_id = %d ORDER BY id ASC LIMIT 1",
        $post_id
    ) );
}

/**
 * Post bazlı toplam oy ve ortalama (yasr_log tablosundan).
 *
 * @deprecated Yeni kodda Reviews::rating($post_id, 'post') kullanın.
 */
function get_star_votes( int $post_id ): array {
    global $wpdb;
    $table = $wpdb->prefix . 'yasr_log';

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT COUNT(*) as total, CAST(AVG(vote) AS DECIMAL(10,1)) as point
         FROM {$table} WHERE post_id = %d",
        $post_id
    ), OBJECT );
}

/**
 * Kullanıcı profil bazlı rating (wp_comments + comment_meta).
 *
 * @deprecated Reviews::rating($user_id, 'user') kullanın.
 */
function get_star_votes_profile( int $user_id = 0, int $approved = 1 ): ?object {
    if ( class_exists( 'Reviews' ) ) {
        $data = Reviews::rating( $user_id, 'user' );
        return (object) [ 'total' => $data['total'], 'point' => $data['average'] ];
    }

    // Fallback — Reviews class yoksa eski SQL
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT COUNT(*) as total, CAST(AVG(rm.meta_value) AS DECIMAL(10,1)) as point
         FROM {$wpdb->comments} c
         INNER JOIN {$wpdb->commentmeta} pm ON pm.comment_id = c.comment_ID
             AND pm.meta_key = 'comment_profile' AND pm.meta_value = %d
         INNER JOIN {$wpdb->commentmeta} rm ON rm.comment_id = c.comment_ID
             AND rm.meta_key = 'rating'
         WHERE c.comment_type = 'review' AND c.comment_approved = %d",
        $user_id, $approved
    ) );
}


// =========================================================================
// YASR PLUGIN HOOK'LARI
// =========================================================================

add_filter( 'yasr_filter_schema_type', 'yasr_schema_type' );
function yasr_schema_type( $type ) {
    global $post;
    if ( isset( $post->post_type ) && $post->post_type === 'product' ) {
        $type = 'Product';
    }
    return $type;
}

add_filter( 'yasr_filter_schema_jsonld', 'yasr_schema' );
function yasr_schema( $type ) {
    return $type;
}


// =========================================================================
// ADMIN — YASR menü ve metabox gizleme
// =========================================================================

if ( is_admin() ) {

    add_action( 'admin_menu', static function () {
        remove_menu_page( 'yasr_settings_page' );
        remove_submenu_page( 'yasr_settings_page', 'yasr_settings_page' );
        remove_submenu_page( 'yasr_settings_page', 'yasr_stats_page' );
        remove_submenu_page( 'yasr_settings_page', '#' );
        remove_submenu_page( 'yasr_settings_page', 'yasr_settings_page-pricing' );
    }, 1000000000 );

    add_action( 'add_meta_boxes', static function () {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        remove_meta_box( 'yasr_metabox_overall_rating', $screen->id, 'side' );
        remove_meta_box( 'yasr_metabox_below_editor_metabox', $screen->id, 'normal' );
    }, 20 );

    add_action( 'wp_dashboard_setup', static function () {
        remove_meta_box( 'yasr_widget_log_dashboard', 'dashboard', 'normal' );
        remove_meta_box( 'yasr_users_dashboard_widget', 'dashboard', 'normal' );
    }, 20 );
}
