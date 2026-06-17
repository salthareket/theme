<?php

namespace SaltHareket\Reviews;

/**
 * ReviewsDisabler
 *
 * Enable Reviews = false olduğunda WordPress'in comment/discussion sistemini
 * ve Reviews uygulamasını tamamen gizler.
 *
 * Kapsamı:
 *  - Frontend: comment_template() bypass, comments_open() → false
 *  - Admin menü: Comments menü item'ı kaldırılır
 *  - Admin bar: Comments butonu kaldırılır
 *  - Admin: Settings > Discussion sayfası erişilemiyor
 *  - Admin: Post/page listesinde Comment sütunu yok
 *  - Admin: Post edit ekranında Comments metabox yok
 *  - Admin: Discussion support'u olan tüm post type'lardan kaldırılır
 *  - Dashboard widget: Recent Comments kaldırılır
 *  - REST: comments endpoint devre dışı
 *  - XML-RPC: comment metodları devre dışı
 *
 * @version 1.0.0
 * @package SaltHareket\Reviews
 */
class ReviewsDisabler
{
    public static function register(): void
    {
        // ─── Admin menü + bar ─────────────────────────────────────────────────
        add_action( 'admin_menu', [ self::class, 'removeAdminMenuItems' ], 999 );
        add_action( 'admin_bar_menu', [ self::class, 'removeAdminBarItems' ], 999 );

        // ─── Discussion settings sayfası engelle ──────────────────────────────
        add_action( 'admin_init', [ self::class, 'blockDiscussionPage' ] );

        // ─── Post listesi: Comment sütunu kaldır ─────────────────────────────
        add_filter( 'manage_posts_columns',       [ self::class, 'removeCommentColumns' ] );
        add_filter( 'manage_pages_columns',       [ self::class, 'removeCommentColumns' ] );

        // ─── Post edit: Comments + Discussion metabox kaldır ─────────────────
        add_action( 'add_meta_boxes', [ self::class, 'removeCommentMetaboxes' ], 999 );

        // ─── Post type support: comments + trackbacks kaldır ─────────────────
        add_action( 'init', [ self::class, 'removeCommentSupport' ], 999 );

        // ─── Frontend: comments_open her zaman false ──────────────────────────
        add_filter( 'comments_open',       '__return_false', 999 );
        add_filter( 'pings_open',          '__return_false', 999 );
        add_filter( 'comments_array',      [ self::class, 'returnEmptyArray' ], 999 );
        add_filter( 'get_comments_number', '__return_zero',  999 );

        // ─── Dashboard widget kaldır ──────────────────────────────────────────
        add_action( 'wp_dashboard_setup', [ self::class, 'removeDashboardWidgets' ] );

        // ─── REST API: comments endpoint kapat ───────────────────────────────
        add_filter( 'rest_endpoints', [ self::class, 'disableRestComments' ] );

        // ─── XML-RPC: comment metodları kaldır ───────────────────────────────
        add_filter( 'xmlrpc_methods', [ self::class, 'disableXmlRpcComments' ] );

        // ─── Comment feed linkleri kaldır ─────────────────────────────────────
        add_action( 'wp_head', [ self::class, 'removeCommentFeeds' ], 1 );

        // ─── Admin CSS: Comment sütunlarını gizle (güvenlik katmanı) ──────────
        add_action( 'admin_head', [ self::class, 'injectAdminHideStyles' ] );
    }

    // ─── Admin menü ──────────────────────────────────────────────────────────

    public static function removeAdminMenuItems(): void
    {
        remove_menu_page( 'edit-comments.php' );

        // Settings > Discussion alt menüsünü kaldır
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    // ─── Admin bar ───────────────────────────────────────────────────────────

    public static function removeAdminBarItems( \WP_Admin_Bar $wp_admin_bar ): void
    {
        $wp_admin_bar->remove_node( 'comments' );
    }

    // ─── Discussion settings sayfası erişimini engelle ───────────────────────

    public static function blockDiscussionPage(): void
    {
        global $pagenow;

        if (
            $pagenow === 'options-discussion.php' ||
            ( isset( $_GET['page'] ) && $_GET['page'] === 'options-discussion' )
        ) {
            wp_die(
                __( 'Reviews are disabled. Discussion settings are not available.' ),
                __( 'Feature Disabled' ),
                [ 'response' => 403 ]
            );
        }
    }

    // ─── Post listesi: Comment sütununu kaldır ───────────────────────────────

    public static function removeCommentColumns( array $columns ): array
    {
        unset( $columns['comments'] );
        return $columns;
    }

    // ─── Post edit: Metabox kaldır ───────────────────────────────────────────

    public static function removeCommentMetaboxes(): void
    {
        $post_types = get_post_types();

        foreach ( $post_types as $type ) {
            remove_meta_box( 'commentsdiv',      $type, 'normal' );
            remove_meta_box( 'trackbacksdiv',    $type, 'normal' );
            remove_meta_box( 'commentstatusdiv', $type, 'normal' );
            remove_meta_box( 'commentstatusdiv', $type, 'side' );
        }
    }

    // ─── Post type support kaldır ────────────────────────────────────────────

    public static function removeCommentSupport(): void
    {
        $post_types = get_post_types();

        foreach ( $post_types as $type ) {
            if ( post_type_supports( $type, 'comments' ) ) {
                remove_post_type_support( $type, 'comments' );
            }
            if ( post_type_supports( $type, 'trackbacks' ) ) {
                remove_post_type_support( $type, 'trackbacks' );
            }
        }
    }

    // ─── Frontend helpers ─────────────────────────────────────────────────────

    public static function returnEmptyArray(): array
    {
        return [];
    }

    // ─── Dashboard widget ─────────────────────────────────────────────────────

    public static function removeDashboardWidgets(): void
    {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }

    // ─── REST API ─────────────────────────────────────────────────────────────

    public static function disableRestComments( array $endpoints ): array
    {
        if ( isset( $endpoints['/wp/v2/comments'] ) ) {
            unset( $endpoints['/wp/v2/comments'] );
        }
        if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    }

    // ─── XML-RPC ──────────────────────────────────────────────────────────────

    public static function disableXmlRpcComments( array $methods ): array
    {
        unset(
            $methods['wp.getComment'],
            $methods['wp.getComments'],
            $methods['wp.deleteComment'],
            $methods['wp.editComment'],
            $methods['wp.newComment'],
            $methods['wp.getCommentStatusList'],
            $methods['wp.getCommentCount']
        );
        return $methods;
    }

    // ─── Feed linkleri kaldır ─────────────────────────────────────────────────

    public static function removeCommentFeeds(): void
    {
        remove_action( 'wp_head', 'feed_links_extra', 3 );
    }

    // ─── Admin CSS (güvenlik katmanı — JS disable olsa bile gizleme devam eder)

    public static function injectAdminHideStyles(): void
    {
        echo '<style>
            #menu-comments,
            #wp-admin-bar-comments,
            .column-comments,
            #commentsdiv,
            #trackbacksdiv,
            #commentstatusdiv,
            .comment-count-badge { display: none !important; }
        </style>';
    }
}
