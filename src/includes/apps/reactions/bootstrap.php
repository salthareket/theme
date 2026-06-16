<?php

/**
 * SaltReactions — Bootstrap
 *
 * Tum siniflari yukler, WP hook'larini kaydeder, Twig helper'larini ve
 * AJAX case'lerini tanimlar. variables.php'den ENABLE_REACTIONS true ise yuklenir.
 *
 * @version 1.4.0
 * @changelog
 *   1.4.0 - 2026-05-09
 *     - Add: Analytics tab refresh butonu (↻ Refresh) eklendi
 *     - Add: wp_ajax_sh_reactions_get_data action register edildi
 *     - Add: Stat card'larına ID attribute'ları eklendi (JS güncelleme için)
 *     - Add: refreshAnalytics() JS fonksiyonu — AJAX ile stats/chart/content/activity günceller
 *     - Fix: WP Rocket cache uyumu — hydrateCounts() sadece cached sayfalarda çalışır
 *     - Fix: Cumulative mode counts() SUM(value) kullanıyor (COUNT(*) yerine)
 *   1.2.0 - 2026-05-08
 *     - Add: admin_init — Reactions::createTable() value kolonu migration
 *   1.0.0 - 2026-05-06
 *     - Add: Autoload, DB install, WP hooks, Twig helpers, global PHP helpers
 *     - Add: Backward-compat Favorites shim (ENABLE_REACTIONS aktifken)
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // variables.php'de:
 * if (ENABLE_REACTIONS || $is_admin) {
 *     include_once SH_INCLUDES_PATH . 'apps/reactions/bootstrap.php';
 * }
 *
 * // Notifications entegrasyonu otomatik — notify_event dolu type'lar
 * // sh_notify_events filter'i ile Notifications event listesine eklenir.
 *
 * ──────────────────────────────────────────────────────────
 */

use SaltHareket\Reactions\Reactions;
use SaltHareket\Reactions\ReactionsSettings;

// ─── AUTOLOAD ────────────────────────────────────────────

require_once __DIR__ . '/ReactionsSettings.php';
require_once __DIR__ . '/Reactions.php';
require_once __DIR__ . '/ReactionsAppSettings.php';
require_once __DIR__ . '/Admin/ReactionsAjax.php';
require_once __DIR__ . '/Admin/ReactionsAnalytics.php';

// ─── ADMIN PAGE ──────────────────────────────────────────

require_once __DIR__ . '/Admin/ReactionsAdmin.php';
add_action( 'admin_menu', [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'addMenuPage' ], 20 );
add_action( 'admin_head', [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'hideNotices' ] );
add_action( 'admin_enqueue_scripts', [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'enqueueAssets' ] );
add_action( 'wp_ajax_sh_reactions_save_types',      [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'saveTypes' ] );
add_action( 'wp_ajax_sh_reactions_save_type',          [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxSaveType' ] );
add_action( 'wp_ajax_sh_reactions_delete_type',        [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxDeleteType' ] );
add_action( 'wp_ajax_sh_reactions_save_button',        [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxSaveButton' ] );
add_action( 'wp_ajax_sh_reactions_delete_button',      [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxDeleteButton' ] );
add_action( 'wp_ajax_sh_reactions_toggle_button',      [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxToggleButton' ] );
add_action( 'wp_ajax_sh_reactions_get_data',           [ \SaltHareket\Reactions\Admin\ReactionsAnalytics::class, 'ajaxGetData' ] );
add_action( 'wp_ajax_sh_reactions_save_app_toggle',    [ \SaltHareket\Reactions\Admin\ReactionsAdmin::class, 'ajaxSaveAppToggle' ] );

add_action( 'admin_init', function () {
    Reactions::createTable();
}, 5 );

// ─── NOTIFICATIONS ENTEGRASYONU ──────────────────────────
// notify_event dolu olan reaction type'larini Notifications event listesine ekle.
// Notifications'a hic dokunmadan filter ile calisir.
// Yeni type eklenince veya notify_event degisince otomatik guncellenir.
add_filter( 'sh_notify_events', function ( array $events ): array {
    foreach ( ReactionsSettings::getTypes() as $type_key => $type_def ) {
        $slug = sanitize_key( $type_def['notify_event'] ?? '' );
        if ( ! $slug ) continue;
        // Zaten listede varsa ekleme
        foreach ( $events as $ev ) {
            if ( ( $ev['slug'] ?? '' ) === $slug ) continue 2;
        }
        $label    = $type_def['label'] ?? ucfirst( $type_key );
        $events[] = [
            'slug'        => $slug,
            'title'       => $label . ' Reaction',
            'description' => $label . ' reaction yapildiginda tetiklenir.',
            'group'       => 'Reactions',
        ];
    }
    return $events;
} );

// ─── WP HOOKS ────────────────────────────────────────────

// Frontend: reactions.js + nonce
add_action( 'wp_enqueue_scripts', function () {
    $js_url = trailingslashit( SH_INCLUDES_URL ) . 'apps/reactions/reactions.js';
    wp_enqueue_script( 'salt-reactions', $js_url, [], '1.0.0', true );
} );

add_action( 'wp_head', function () {
    // saltConfig.nonce reactions.js tarafindan kullanilir
    $nonce = wp_create_nonce( 'ajax' );
    echo '<script>window.saltConfig=window.saltConfig||{};window.saltConfig.nonce=' . wp_json_encode( $nonce ) . ';</script>' . "\n";
}, 2 );

// Silinen post/user/comment/term için reaction'ları temizle
add_action( 'before_delete_post', function ( int $post_id ) {
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'reactions', [ 'object_id' => $post_id, 'object_type' => 'post' ], [ '%d', '%s' ] );
} );

add_action( 'delete_user', function ( int $user_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'reactions';
    $wpdb->delete( $table, [ 'user_id' => $user_id ], [ '%d' ] );
    $wpdb->delete( $table, [ 'object_id' => $user_id, 'object_type' => 'user' ], [ '%d', '%s' ] );
} );

add_action( 'delete_comment', function ( int $comment_id ) {
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'reactions', [ 'object_id' => $comment_id, 'object_type' => 'comment' ], [ '%d', '%s' ] );
} );

add_action( 'delete_term', function ( int $term_id ) {
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'reactions', [ 'object_id' => $term_id, 'object_type' => 'term' ], [ '%d', '%s' ] );
} );

// Login'de guest reactions'ı user'a merge et
// GuestIdentity::mergeToUser() wp_guests + wp_reactions günceller (guest_id ile eşleşme)
// Download Log bootstrap'ındaki wp_login hook'u bunu zaten çağırıyor.
// Reactions bootstrap'ında ayrıca çağırmaya gerek yok — tek noktadan yönetilir.

// ─── TURBO API FILTER ────────────────────────────────────

add_filter( 'turbo_api_handle', function ( $handled, string $method, array $vars ) {
    if ( $handled !== null ) return $handled; // başka biri handle ettiyse geç

    $R   = \SaltHareket\Reactions\Reactions::class;
    $uid = get_current_user_id();

    switch ( $method ) {

        // ── Yeni unified endpoint ─────────────────────────────────────────
        case 'reaction_toggle':
            return \SaltHareket\Reactions\Admin\ReactionsAjax::handleToggle( $vars );

        case 'reaction_count':
            return \SaltHareket\Reactions\Admin\ReactionsAjax::handleCount( $vars );

        case 'reaction_state':
            return \SaltHareket\Reactions\Admin\ReactionsAjax::handleState( $vars );

        // ── Legacy favorites → Reactions ─────────────────────────────────
        case 'favorites':
        case 'favorites_get':
            $fav_ids = $R::getByUser( $uid, 'favorite', 'post' );
            $posts   = ! empty( $fav_ids )
                ? \Timber\Timber::get_posts( [ 'post_type' => 'any', 'posts_per_page' => -1, 'post__in' => $fav_ids ] )
                : [];
            if ( $method === 'favorites' && ( $vars['action'] ?? '' ) === 'get' ) {
                $tpl  = $vars['template'] ?? '';
                $html = '';
                foreach ( $posts as $i => $post ) {
                    ob_start();
                    $ctx              = \Timber\Timber::context();
                    $ctx['post']      = $post;
                    $ctx['index']     = $i;
                    $ctx['type']      = 'favorites';
                    $ctx['favorites'] = $fav_ids;
                    \Timber\Timber::render( $tpl ? [ $tpl, 'tease.twig' ] : [ 'tease.twig' ], $ctx );
                    $html .= ob_get_clean();
                }
                return [ 'error' => false, 'html' => $html, 'data' => $fav_ids, 'count' => count( $fav_ids ) ];
            }
            $view = $vars['view'] ?? 'dropdown';
            $ctx  = \Timber\Timber::context();
            $ctx['type']  = 'favorites';
            $ctx['posts'] = $posts;
            return [
                'error' => false,
                'html'  => \Timber\Timber::compile( 'partials/' . $view . '/archive.twig', $ctx ),
                'data'  => $fav_ids,
                'count' => count( $fav_ids ),
            ];

        case 'favorites_add':
            $fav_id = absint( $vars['id'] ?? 0 );
            $result = $R::toggle( 'favorite', $fav_id, 'post' );
            $has    = ( $result['action'] === 'added' );
            return [
                'error'   => false,
                'has'     => $has,
                'count'   => $result['count'],
                'data'    => $R::getByUser( $uid, 'favorite', 'post' ),
                'message' => '<b>' . esc_html( get_the_title( $fav_id ) ) . '</b> ' . ( $has ? trans( 'added to favorites.' ) : trans( 'removed from favorites.' ) ),
                'html'    => $has ? trans( 'Remove' ) : trans( 'Add' ),
            ];

        case 'favorites_remove':
            $fav_id = absint( $vars['id'] ?? 0 );
            $R::toggle( 'favorite', $fav_id, 'post' );
            return [
                'error'   => false,
                'has'     => false,
                'count'   => $R::count( 'favorite', $fav_id, 'post' ),
                'data'    => $R::getByUser( $uid, 'favorite', 'post' ),
                'message' => '<b>' . esc_html( get_the_title( $fav_id ) ) . '</b> ' . trans( 'removed from favorites.' ),
                'html'    => trans( 'Add' ),
            ];

        // ── Legacy follow → Reactions ─────────────────────────────────────
        case 'follow':
            if ( ! is_user_logged_in() ) {
                return [ 'error' => true, 'message' => 'Not logged in' ];
            }
            $follow_id   = absint( $vars['id'] ?? 0 );
            $follow_type = sanitize_key( $vars['type'] ?? 'user' );
            $result      = $R::toggle( 'follow', $follow_id, $follow_type );
            $has         = ( $result['action'] === 'added' );
            return [
                'error'  => false,
                'action' => $result['action'],
                'has'    => $has,
                'count'  => $result['count'],
                'html'   => $has ? trans( 'Unfollow' ) : trans( 'Follow' ),
            ];

        case 'get_followers':
            $follow_id   = absint( $vars['id'] ?? 0 );
            $follow_type = sanitize_key( $vars['type'] ?? 'user' );
            $user_ids    = $R::getForObject( $follow_id, $follow_type, 'follow' );
            $per_page    = (int) ( $vars['posts_per_page'] ?? 10 );
            $page        = max( 1, (int) ( $vars['page'] ?? 1 ) );
            $total       = count( $user_ids );
            $paged_ids   = array_slice( $user_ids, ( $page - 1 ) * $per_page, $per_page );
            $ctx         = \Timber\Timber::context();
            $ctx['users'] = ! empty( $paged_ids ) ? get_users( [ 'include' => $paged_ids ] ) : [];
            $ctx['data']  = [ 'total' => $total, 'pages' => (int) ceil( $total / $per_page ), 'page' => $page ];
            $ctx['vars']  = $vars;
            return [
                'error' => false,
                'data'  => $ctx['data'],
                'html'  => \Timber\Timber::compile( 'user/archive.twig', $ctx ),
            ];
    }

    return null; // bu method bize ait değil
}, 10, 3 );

add_filter( 'timber/twig', function ( \Twig\Environment $twig ) {

    /**
     * Reaction butonu render et.
     *
     * @example {{ function('salt_reaction_button', post.ID, 'post', 'like', {'style': 'icon-count'}) }}
     * @example {{ function('salt_reaction_button', user.ID, 'user', 'follow', {'style': 'pill'}) }}
     * @example {{ function('salt_reaction_button', review.ID, 'comment', 'like', {'style': 'icon-only'}) }}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'salt_reaction_button',
        function ( int $object_id, string $object_type, string $type, array $options = [] ): string {
            return \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $object_id, $object_type, $type, $options );
        },
        [ 'is_safe' => [ 'html' ] ]
    ) );

    /**
     * Reaction sayısını döndür.
     *
     * @example {{ function('salt_reaction_count', post.ID, 'post', 'like') }}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'salt_reaction_count',
        function ( int $object_id, string $object_type, string $type ): int {
            return Reactions::count( $type, $object_id, $object_type );
        }
    ) );

    /**
     * Kullanıcı bu reaction'ı yapmış mı?
     *
     * @example {{ function('salt_has_reaction', post.ID, 'post', 'like') }}
     * @example {% if function('salt_has_reaction', post.ID, 'post', 'favorite') %}...{% endif %}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'salt_has_reaction',
        function ( int $object_id, string $object_type, string $type, int $user_id = 0 ): bool {
            return Reactions::has( $type, $object_id, $object_type, $user_id );
        }
    ) );

    /**
     * Kullanıcının belirli tipte reaction yaptığı object ID'lerini döndür.
     *
     * @example {% set favorites = function('salt_reaction_ids', user.ID, 'post', 'favorite') %}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'salt_reaction_ids',
        function ( int $user_id, string $object_type, string $type ): array {
            return Reactions::getByUser( $user_id, $type, $object_type );
        }
    ) );

    /**
     * Object type için tanımlı reaction'ları döndür (placement rules).
     *
     * @example {% set reactions = function('salt_reactions_for', 'post', post.post_type) %}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'salt_reactions_for',
        function ( string $object_type, string $subtype = '' ): array {
            return ReactionsSettings::getForObject( $object_type, $subtype );
        }
    ) );

    return $twig;
} );

// ─── GLOBAL PHP HELPERS ──────────────────────────────────

if ( ! function_exists( 'salt_reaction_button' ) ) {
    /**
     * @example salt_reaction_button(42, 'post', 'like', ['style' => 'pill']);
     */
    function salt_reaction_button( int $object_id, string $object_type, string $type, array $options = [] ): void {
        echo \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $object_id, $object_type, $type, $options ); // phpcs:ignore
    }
}

if ( ! function_exists( 'salt_reaction_count' ) ) {
    function salt_reaction_count( int $object_id, string $object_type, string $type ): int {
        return Reactions::count( $type, $object_id, $object_type );
    }
}

if ( ! function_exists( 'salt_has_reaction' ) ) {
    function salt_has_reaction( int $object_id, string $object_type, string $type, int $user_id = 0 ): bool {
        return Reactions::has( $type, $object_id, $object_type, $user_id );
    }
}

// ─── BACKWARD-COMPAT: Favorites Shim ────────────────────

/**
 * Eski Favorites class'ını kullanan kodlar kırılmasın diye shim.
 * ENABLE_REACTIONS aktifken Favorites class yüklenmez, bu shim devreye girer.
 * Migration tamamlandıktan sonra kaldırılabilir.
 */
if ( ! class_exists( 'Favorites' ) ) {

    class Favorites {

        public array  $favorites = [];
        public int    $user_id   = 0;
        public string $type      = 'post';
        public int    $per_page  = 0;

        public function __construct( int $user_id = 0 ) {
            $this->user_id   = $user_id > 0 ? $user_id : (int) get_current_user_id();
            $this->favorites = Reactions::getByUser( $this->user_id, 'favorite', 'post' );
        }

        public function add( int $id, string $object_type = 'post' ): void {
            Reactions::toggle( 'favorite', $id, $object_type ?: 'post', $this->user_id );
            $this->favorites = Reactions::getByUser( $this->user_id, 'favorite', 'post' );
        }

        public function remove( int $id ): void {
            global $wpdb;
            $table = $wpdb->prefix . 'reactions';
            $wpdb->delete( $table, [
                'user_id'     => $this->user_id,
                'object_id'   => $id,
                'object_type' => 'post',
                'type'        => 'favorite',
            ], [ '%d', '%d', '%s', '%s' ] );
            $this->favorites = Reactions::getByUser( $this->user_id, 'favorite', 'post' );
        }

        public function exists( int $id ): bool {
            return Reactions::has( 'favorite', $id, 'post', $this->user_id );
        }

        /** @deprecated */
        public function exist( int $id ): bool {
            return $this->exists( $id );
        }

        public function count(): int {
            return count( $this->favorites );
        }

        public function merge(): void {
            \SaltHareket\Reactions\Admin\ReactionsAjax::migrateGuestCookie( $this->user_id );
            $this->favorites = Reactions::getByUser( $this->user_id, 'favorite', 'post' );
        }

        public function check(): void {
            // No-op: Reactions tablosunda stale ID kalmaz
        }

        public function get_posts( string $filter_type = '', int $page = 0, ?int $per_page = null ): array {
            $ids = Reactions::getByUser( $this->user_id, 'favorite', 'post' );
            if ( empty( $ids ) ) return [ 'items' => [], 'total' => 0, 'pages' => 0 ];

            $limit = $per_page ?? $this->per_page;
            $args  = [
                'post__in'       => $ids,
                'post_type'      => $filter_type ?: 'any',
                'post_status'    => 'publish',
                'orderby'        => 'post__in',
                'posts_per_page' => $limit > 0 ? $limit : -1,
            ];
            if ( $page > 0 && $limit > 0 ) {
                $args['paged']         = $page;
                $args['no_found_rows'] = false;
                $query = new WP_Query( $args );
                return [ 'items' => $query->posts, 'total' => $query->found_posts, 'pages' => (int) $query->max_num_pages ];
            }
            return [ 'items' => get_posts( $args ), 'total' => count( $ids ), 'pages' => 1 ];
        }

        // Legacy no-ops
        public function setCookie(): void {}
        public function unsetCookie(): void {}
        public function calculate( array $favorites = [] ): void {}
        public function update(): void {}
    }
}
