<?php

namespace SaltHareket\Reactions;

/**
 * Reactions
 * Unified reaction sistemi — Like, Follow, Favorite, Bookmark, Clap ve custom tipler.
 * Post, User, Comment, Term ve herhangi bir object type'a uygulanabilir.
 * Toggle, Additive ve Cumulative (Medium-style clap) modlarini destekler.
 *
 * @version 1.2.0
 * @changelog
 *   1.2.0 - 2026-05-08
 *     - Add: interact() — mode'a gore toggle/additive/cumulative dispatch
 *     - Add: interactCumulative() — amount parametresi, limit kontrolu, tek AJAX ile bulk increment
 *     - Add: interactAdditive() — sadece ekle, kaldir yok
 *     - Add: getUserValue() — kullanicinin cumulative value'sunu dondurur
 *     - Change: count() — cumulative modda SUM(value), diger modlarda COUNT(*)
 *     - Change: createTable() — value INT kolonu eklendi, mevcut kurulumlar icin ALTER TABLE migration
 *     - Change: toggle() — geriye uyumluluk icin interact()'e delegate eder
 *   1.1.0 - 2026-05-07
 *     - Add: counts() bulk query
 *     - Add: getForObject() user_id listesi
 *   1.0.0 - 2026-05-06 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Toggle modu (like/unlike — geri alinabilir)
 * Reactions::interact('like', 123, 'post');
 * Reactions::interact('follow', 456, 'user');
 *
 * // Cumulative modu (clap — her tikta +1, limit var)
 * // amount parametresi ile debounce sonrasi bulk gonder
 * Reactions::interact('clap', 123, 'post', 0, 10); // 10 tik birden
 *
 * // Additive modu (sadece ekle, kaldir yok)
 * Reactions::interact('bookmark', 123, 'post');
 *
 * // Geriye uyumluluk
 * Reactions::toggle('like', 123, 'post');
 *
 * // Kontrol
 * Reactions::has('like', 123, 'post');           // bool
 * Reactions::has('follow', 456, 'user', $uid);   // belirli kullanici icin
 *
 * // Sayi — cumulative'de SUM(value), diger modlarda COUNT(*)
 * Reactions::count('like', 123, 'post');         // int
 * Reactions::count('clap', 123, 'post');         // toplam clap sayisi
 * Reactions::counts([123, 456], 'post', 'like'); // bulk
 *
 * // Kullanicinin cumulative value'su (kac tik yapti)
 * Reactions::getUserValue('clap', 123, 'post');  // int (0-50)
 *
 * // Kullanicinin tum reaction'lari
 * Reactions::getByUser($user_id, 'follow', 'user');
 * Reactions::getByUser($user_id, 'favorite', 'post');
 *
 * // Object'e reaction yapan kullanicilari getir
 * Reactions::getForObject(123, 'post', 'like');
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Toggle: like/unlike
 *   Reactions::interact('like', $post->ID, 'post');
 *
 * @example
 *   // Cumulative: 10 tik birden (debounce sonrasi)
 *   Reactions::interact('clap', $post->ID, 'post', 0, 10);
 *
 * @example
 *   // Kullanicinin clap value'su
 *   $my_claps = Reactions::getUserValue('clap', $post->ID, 'post'); // 23
 *
 * @example
 *   // Toplam clap sayisi (SUM)
 *   $total = Reactions::count('clap', $post->ID, 'post'); // 284
 *
 * @example
 *   // Twig'den:
 *   {{ function('salt_reaction_button', post.ID, 'post', 'clap', {'style': 'icon-count', 'require_login': false}) }}
 *
 * @package SaltHareket\Reactions
 */
class Reactions
{
    private const TABLE = 'reactions';

    // ─── INTERACT ────────────────────────────────────────────────────────────

    /**
     * Mode'a gore reaction isle:
     *   toggle     → ekle/kaldir (like/follow)
     *   additive   → sadece ekle, kaldir yok
     *   cumulative → her tikta +1, kullanici basina limit
     *
     * @return array{success: bool, action: string, count: int, value: int, has_reaction: bool}
     */
    public static function interact(
        string $type,
        int    $object_id,
        string $object_type,
        int    $user_id  = 0,
        int    $amount   = 1,
        string $guest_id = ''
    ): array {
        $user_id = $user_id ?: (int) get_current_user_id();

        // Guest: user_id=0 ama guest_id var
        if ( $user_id < 1 && ! $guest_id ) {
            return [ 'success' => false, 'message' => trans( 'Giris yapmaniz gerekiyor.' ) ];
        }

        // Polylang aktifse post/term ID'lerini her zaman default dildekiyle normalize et.
        // Böylece EN'de favorite yapılan post TR'de de aynı ID ile görünür.
        $object_id = self::normalizeObjectId( $object_id, $object_type );

        $type_config = ReactionsSettings::getType( $type );
        if ( ! $type_config ) {
            return [ 'success' => false, 'message' => 'Unknown reaction type: ' . $type ];
        }

        $mode = $type_config['mode'] ?? 'toggle';

        switch ( $mode ) {
            case 'cumulative':
                return self::interactCumulative( $type, $type_config, $object_id, $object_type, $user_id, max(1, $amount), $guest_id );
            case 'additive':
                return self::interactAdditive( $type, $type_config, $object_id, $object_type, $user_id, $guest_id );
            default:
                return self::interactToggle( $type, $type_config, $object_id, $object_type, $user_id, $guest_id );
        }
    }

    /**
     * Polylang aktifse post/term ID'sini default dildeki karşılığına normalize eder.
     * Farklı dillerdeki aynı içeriğin tek bir ID altında toplanması için kullanılır.
     * Polylang yoksa veya çeviri bulunamazsa orijinal ID döner.
     */
    private static function normalizeObjectId( int $object_id, string $object_type ): int
    {
        if ( $object_id < 1 ) return $object_id;

        if ( $object_type === 'post' && function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
            $default_id = pll_get_post( $object_id, pll_default_language() );
            if ( $default_id && $default_id > 0 ) {
                return $default_id;
            }
        }

        if ( $object_type === 'term' && function_exists( 'pll_get_term' ) && function_exists( 'pll_default_language' ) ) {
            $default_id = pll_get_term( $object_id, pll_default_language() );
            if ( $default_id && $default_id > 0 ) {
                return $default_id;
            }
        }

        return $object_id;
    }

    /**
     * Geriye uyumluluk — toggle() hala calisir.
     */
    public static function toggle(
        string $type,
        int    $object_id,
        string $object_type,
        int    $user_id = 0
    ): array {
        return self::interact( $type, $object_id, $object_type, $user_id );
    }

    // ─── INTERACT MODES ──────────────────────────────────────────────────────

    private static function interactToggle(
        string $type,
        array  $type_config,
        int    $object_id,
        string $object_type,
        int    $user_id,
        string $guest_id = ''
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $where = $user_id > 0
            ? $wpdb->prepare( "user_id = %d AND object_id = %d AND object_type = %s AND type = %s", $user_id, $object_id, $object_type, $type )
            : $wpdb->prepare( "guest_id = %s AND object_id = %d AND object_type = %s AND type = %s", $guest_id, $object_id, $object_type, $type );

        $existing = $wpdb->get_var( "SELECT id FROM {$table} WHERE {$where}" ); // phpcs:ignore

        if ( $existing ) {
            $wpdb->delete( $table, [ 'id' => (int) $existing ] );
            $action = 'removed';
            do_action( "reactions/{$type}/removed", $object_id, $object_type, $user_id );
            do_action( 'reactions/removed', $type, $object_id, $object_type, $user_id );
            self::maybeDeleteNotify( $type, $type_config, $object_id, $object_type, $user_id );
        } else {
            $wpdb->insert( $table, array_filter( [
                'user_id'     => $user_id > 0 ? $user_id : 0,
                'guest_id'    => $guest_id ?: null,
                'object_id'   => $object_id,
                'object_type' => $object_type,
                'type'        => $type,
                'value'       => 1,
                'created_at'  => gmdate( 'Y-m-d H:i:s' ),
            ], fn($v) => $v !== null ) );
            $action = 'added';
            do_action( "reactions/{$type}/added", $object_id, $object_type, $user_id );
            do_action( 'reactions/added', $type, $object_id, $object_type, $user_id );
            self::maybeNotify( $type, $type_config, $object_id, $object_type, $user_id );
        }

        self::clearCache( $type, $object_id, $object_type );
        $count = self::count( $type, $object_id, $object_type );

        return [
            'success'      => true,
            'action'       => $action,
            'count'        => $count,
            'value'        => $action === 'added' ? 1 : 0,
            'has_reaction' => $action === 'added',
        ];
    }

    private static function interactAdditive(
        string $type,
        array  $type_config,
        int    $object_id,
        string $object_type,
        int    $user_id,
        string $guest_id = ''
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $where = $user_id > 0
            ? $wpdb->prepare( "user_id = %d AND object_id = %d AND object_type = %s AND type = %s", $user_id, $object_id, $object_type, $type )
            : $wpdb->prepare( "guest_id = %s AND object_id = %d AND object_type = %s AND type = %s", $guest_id, $object_id, $object_type, $type );

        $existing = $wpdb->get_var( "SELECT id FROM {$table} WHERE {$where}" ); // phpcs:ignore

        if ( $existing ) {
            $count = self::count( $type, $object_id, $object_type );
            return [ 'success' => true, 'action' => 'exists', 'count' => $count, 'value' => 1, 'has_reaction' => true ];
        }

        $wpdb->insert( $table, array_filter( [
            'user_id'     => $user_id > 0 ? $user_id : 0,
            'guest_id'    => $guest_id ?: null,
            'object_id'   => $object_id,
            'object_type' => $object_type,
            'type'        => $type,
            'value'       => 1,
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ], fn($v) => $v !== null ) );

        do_action( "reactions/{$type}/added", $object_id, $object_type, $user_id );
        do_action( 'reactions/added', $type, $object_id, $object_type, $user_id );
        self::maybeNotify( $type, $type_config, $object_id, $object_type, $user_id );
        self::clearCache( $type, $object_id, $object_type );

        $count = self::count( $type, $object_id, $object_type );
        return [ 'success' => true, 'action' => 'added', 'count' => $count, 'value' => 1, 'has_reaction' => true ];
    }

    private static function interactCumulative(
        string $type,
        array  $type_config,
        int    $object_id,
        string $object_type,
        int    $user_id,
        int    $amount = 1,
        string $guest_id = ''
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = (int) ( $type_config['limit'] ?? 50 );

        $where = $user_id > 0
            ? $wpdb->prepare( "user_id = %d AND object_id = %d AND object_type = %s AND type = %s", $user_id, $object_id, $object_type, $type )
            : $wpdb->prepare( "guest_id = %s AND object_id = %d AND object_type = %s AND type = %s", $guest_id, $object_id, $object_type, $type );

        $row = $wpdb->get_row( "SELECT id, value FROM {$table} WHERE {$where}" ); // phpcs:ignore

        $current_value = $row ? (int) $row->value : 0;

        if ( $current_value >= $limit ) {
            return [ 'success' => true, 'action' => 'limit_reached', 'count' => self::count( $type, $object_id, $object_type ), 'value' => $current_value, 'limit' => $limit, 'has_reaction' => true ];
        }

        $add_amount = min( $amount, $limit - $current_value );
        $new_value  = $current_value + $add_amount;

        if ( $row ) {
            $wpdb->update( $table, [ 'value' => $new_value ], [ 'id' => (int) $row->id ] );
        } else {
            $wpdb->insert( $table, array_filter( [
                'user_id'     => $user_id > 0 ? $user_id : 0,
                'guest_id'    => $guest_id ?: null,
                'object_id'   => $object_id,
                'object_type' => $object_type,
                'type'        => $type,
                'value'       => $new_value,
                'created_at'  => gmdate( 'Y-m-d H:i:s' ),
            ], fn($v) => $v !== null ) );
            self::maybeNotify( $type, $type_config, $object_id, $object_type, $user_id );
        }

        do_action( "reactions/{$type}/added", $object_id, $object_type, $user_id );
        do_action( 'reactions/added', $type, $object_id, $object_type, $user_id );
        self::clearCache( $type, $object_id, $object_type );

        return [
            'success'      => true,
            'action'       => $new_value >= $limit ? 'limit_reached' : 'incremented',
            'count'        => self::count( $type, $object_id, $object_type ),
            'value'        => $new_value,
            'limit'        => $limit,
            'has_reaction' => true,
        ];
    }

    // ─── READ ─────────────────────────────────────────────────────────────────

    /**
     * Kullanıcı bu reaction'ı yapmış mı?
     */
    public static function has(
        string $type,
        int    $object_id,
        string $object_type,
        int    $user_id  = 0,
        string $guest_id = ''
    ): bool {
        $user_id = $user_id ?: (int) get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        if ( $user_id > 0 ) {
            return (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND object_id = %d AND object_type = %s AND type = %s LIMIT 1",
                $user_id, $object_id, $object_type, $type
            ) );
        }

        if ( $guest_id ) {
            return (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE guest_id = %s AND object_id = %d AND object_type = %s AND type = %s LIMIT 1",
                $guest_id, $object_id, $object_type, $type
            ) );
        }

        return false;
    }

    /**
     * Reaction sayisi — cumulative modda SUM(value), diger modlarda COUNT(*).
     */
    public static function count(
        string $type,
        int    $object_id,
        string $object_type
    ): int {
        $cache_key = "sh_reaction_{$type}_{$object_type}_{$object_id}";
        $cached    = wp_cache_get( $cache_key, 'reactions' );
        if ( $cached !== false ) return (int) $cached;

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $type_config = ReactionsSettings::getType( $type );
        $mode        = $type_config['mode'] ?? 'toggle';

        if ( $mode === 'cumulative' ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(value), 0) FROM {$table}
                 WHERE object_id = %d AND object_type = %s AND type = %s",
                $object_id, $object_type, $type
            ) );
        } else {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE object_id = %d AND object_type = %s AND type = %s",
                $object_id, $object_type, $type
            ) );
        }

        wp_cache_set( $cache_key, $count, 'reactions', 300 );
        return $count;
    }

    /**
     * Kullanicinin bu object icin verdigi value (cumulative icin kac tik yapti).
     */
    public static function getUserValue(
        string $type,
        int    $object_id,
        string $object_type,
        int    $user_id = 0
    ): int {
        $user_id = $user_id ?: (int) get_current_user_id();
        if ( $user_id < 1 ) return 0;

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(value, 0) FROM {$table}
             WHERE user_id = %d AND object_id = %d AND object_type = %s AND type = %s LIMIT 1",
            $user_id, $object_id, $object_type, $type
        ) );
    }

    /**
     * Birden fazla object için bulk count.
     * @return array<int, int>  [object_id => count]
     */
    public static function counts(
        array  $object_ids,
        string $object_type,
        string $type
    ): array {
        if ( empty( $object_ids ) ) return [];

        global $wpdb;
        $table       = $wpdb->prefix . self::TABLE;
        $ids_escaped = implode( ',', array_map( 'intval', $object_ids ) );

        $type_config = ReactionsSettings::getType( $type );
        $mode        = $type_config['mode'] ?? 'toggle';

        if ( $mode === 'cumulative' ) {
            $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
                "SELECT object_id, COALESCE(SUM(value), 0) as cnt FROM {$table}
                 WHERE object_id IN ({$ids_escaped}) AND object_type = %s AND type = %s
                 GROUP BY object_id",
                $object_type, $type
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
                "SELECT object_id, COUNT(*) as cnt FROM {$table}
                 WHERE object_id IN ({$ids_escaped}) AND object_type = %s AND type = %s
                 GROUP BY object_id",
                $object_type, $type
            ) );
        }

        $result = array_fill_keys( $object_ids, 0 );
        foreach ( $rows as $row ) {
            $result[ (int) $row->object_id ] = (int) $row->cnt;
        }
        return $result;
    }

    /**
     * Kullanıcının belirli tipte reaction yaptığı object'leri getir.
     * @return int[]  object_id listesi
     */
    public static function getByUser(
        int    $user_id,
        string $type,
        string $object_type
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT object_id FROM {$table}
             WHERE user_id = %d AND type = %s AND object_type = %s
             ORDER BY created_at DESC",
            $user_id, $type, $object_type
        ) );

        return array_map( 'intval', $ids );
    }

    /**
     * Bir object'e reaction yapan kullanıcıları getir.
     * @return int[]  user_id listesi
     */
    public static function getForObject(
        int    $object_id,
        string $object_type,
        string $type,
        int    $limit = 50
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$table}
             WHERE object_id = %d AND object_type = %s AND type = %s
             ORDER BY created_at DESC LIMIT %d",
            $object_id, $object_type, $type, $limit
        ) );

        return array_map( 'intval', $ids );
    }

    // ─── MIGRATION ───────────────────────────────────────────────────────────

    /**
     * Mevcut Favorites sisteminden migrate et.
     * Bir kez çalıştırılır.
     */
    public static function migrateFromFavorites( int $user_id = 0 ): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $query = $user_id
            ? $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'wpcf_favorites' AND user_id = %d", $user_id )
            : "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'wpcf_favorites'";

        $rows    = $wpdb->get_results( $query ); // phpcs:ignore
        $migrated = 0;

        foreach ( $rows as $row ) {
            $ids = json_decode( $row->meta_value, true );
            if ( ! is_array( $ids ) ) continue;

            foreach ( $ids as $post_id ) {
                $post_id = (int) $post_id;
                if ( $post_id < 1 ) continue;

                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE user_id = %d AND object_id = %d AND object_type = 'post' AND type = 'favorite'",
                    $row->user_id, $post_id
                ) );

                if ( ! $exists ) {
                    $wpdb->insert( $table, [
                        'user_id'     => (int) $row->user_id,
                        'object_id'   => $post_id,
                        'object_type' => 'post',
                        'type'        => 'favorite',
                        'created_at'  => gmdate( 'Y-m-d H:i:s' ),
                    ] );
                    $migrated++;
                }
            }
        }

        return $migrated;
    }

    /**
     * Mevcut Follow sisteminden migrate et.
     */
    public static function migrateFromFollow( int $user_id = 0 ): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $query = $user_id
            ? $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'following_user' AND user_id = %d", $user_id )
            : "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'following_user'";

        $rows    = $wpdb->get_results( $query ); // phpcs:ignore
        $migrated = 0;

        foreach ( $rows as $row ) {
            $followed_ids = maybe_unserialize( $row->meta_value );
            if ( ! is_array( $followed_ids ) ) continue;

            foreach ( $followed_ids as $followed_id ) {
                $followed_id = (int) $followed_id;
                if ( $followed_id < 1 ) continue;

                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE user_id = %d AND object_id = %d AND object_type = 'user' AND type = 'follow'",
                    $row->user_id, $followed_id
                ) );

                if ( ! $exists ) {
                    $wpdb->insert( $table, [
                        'user_id'     => (int) $row->user_id,
                        'object_id'   => $followed_id,
                        'object_type' => 'user',
                        'type'        => 'follow',
                        'created_at'  => gmdate( 'Y-m-d H:i:s' ),
                    ] );
                    $migrated++;
                }
            }
        }

        return $migrated;
    }

    // ─── DB ──────────────────────────────────────────────────────────────────

    public static function createTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . self::TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20)   NOT NULL AUTO_INCREMENT,
            user_id     bigint(20)   NOT NULL DEFAULT 0,
            guest_id    varchar(64)  NULL,
            object_id   bigint(20)   NOT NULL,
            object_type varchar(50)  NOT NULL,
            type        varchar(50)  NOT NULL,
            value       int(11)      NOT NULL DEFAULT 1,
            created_at  datetime     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reaction_unique (user_id, guest_id, object_id, object_type, type),
            KEY object_idx  (object_id, object_type, type),
            KEY user_idx    (user_id, type, object_type),
            KEY guest_idx   (guest_id(32))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // value kolonu yoksa ekle (mevcut kurulumlar icin migration)
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
        if ( ! in_array( 'value', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN value int(11) NOT NULL DEFAULT 1 AFTER type" );
        }
        if ( ! in_array( 'guest_id', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN guest_id varchar(64) NULL AFTER user_id" );
            $wpdb->query( "ALTER TABLE {$table} ADD KEY guest_idx (guest_id(32))" );
        }
    }

    // ─── PRIVATE ─────────────────────────────────────────────────────────────

    private static function clearCache( string $type, int $object_id, string $object_type ): void
    {
        wp_cache_delete( "sh_reaction_{$type}_{$object_type}_{$object_id}", 'reactions' );
    }

    /**
     * Reaction eklenince notification gönder.
     */
    private static function maybeNotify(
        string $type,
        array  $type_config,
        int    $object_id,
        string $object_type,
        int    $user_id
    ): void {
        $event = $type_config['notify_event'] ?? '';
        if ( empty( $event ) || ! class_exists( 'Notifications' ) ) return;

        $actor = get_userdata( $user_id );

        // Object sahibini bul
        $recipient_id = self::resolveRecipient( $object_id, $object_type );
        if ( $recipient_id < 1 || $recipient_id === $user_id ) return;

        try {
            \Notifications::fire( $event, [
                'user'      => $actor,
                'recipient' => $recipient_id,
                'post'      => $object_type === 'post' ? get_post( $object_id ) : null,
                'reaction'  => $type,
            ] );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Reactions] Notification send error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Reaction kaldırılınca notification sil.
     * Unlike/unfollow olunca karşı tarafın bildirimlerinden bu event silinir.
     */
    private static function maybeDeleteNotify(
        string $type,
        array  $type_config,
        int    $object_id,
        string $object_type,
        int    $user_id
    ): void {
        $event = $type_config['notify_event'] ?? '';
        if ( empty( $event ) || ! class_exists( 'Notifications' ) ) return;

        $recipient_id = self::resolveRecipient( $object_id, $object_type );
        if ( $recipient_id < 1 || $recipient_id === $user_id ) return;

        try {
            // Notifications sınıfında delete_user_event_notification varsa kullan
            if ( method_exists( 'Notifications', 'delete_user_event_notification' ) ) {
                $notifications = new \Notifications();
                $notifications->delete_user_event_notification( $event, $recipient_id, $user_id );
            }
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Reactions] Notification delete error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Object'in sahibini bul (notification recipient).
     */
    private static function resolveRecipient( int $object_id, string $object_type ): int
    {
        switch ( $object_type ) {
            case 'post':
                $post = get_post( $object_id );
                return $post ? (int) $post->post_author : 0;
            case 'user':
                return $object_id;
            case 'comment':
                $comment = get_comment( $object_id );
                return $comment ? (int) $comment->user_id : 0;
            case 'term':
                // Term'lerin sahibi yok — notification gönderilmez
                return 0;
            default:
                return 0;
        }
    }
}
