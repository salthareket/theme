<?php

/**
 * SearchHistory — Arama geçmişi ve popüler arama terimleri yönetimi.
 *
 * Custom DB tablosu (wp_search_terms) kullanır.
 * Kullanıcı bazlı geçmiş user_meta + cookie ile saklanır.
 *
 * KULLANIM:
 *   $sh = new SearchHistory();
 *
 *   // Arama terimi kaydet (arama sonucu varsa)
 *   $sh->set_term('istanbul otelleri', 'hotel');
 *   $sh->set_term('laptop', 'product');
 *   $sh->set_term('kargo takip');  // tüm post type'larda arar
 *
 *   // Kullanıcının son aramaları
 *   $terms = $sh->get_user_terms($user_id, 'search', 5);
 *   // ['laptop', 'istanbul otelleri', ...]
 *
 *   // Popüler aramalar
 *   $popular = $sh->get_popular_terms('product', 10);
 *   // ['iphone', 'samsung', ...]
 *
 *   // "Bunu mu demek istediniz?" önerisi
 *   $suggestion = $sh->did_you_mean('iphne', 2);
 *   // 'iphone'
 *
 * @package SaltHareket
 * @since   2.0.0
 */

class SearchHistory {

    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'search_terms';
        $this->maybe_create_table();

        // WP arama query'sini otomatik yakala — sonuç varsa kaydet
        add_action( 'the_posts', [ $this, 'auto_track_search' ], 10, 2 );
    }

    /**
     * Tablo varlığını transient ile cache'le — her instantiation'da SHOW TABLES çalışmasın.
     */
    private function maybe_create_table(): void {
        $cache_key = 'sh_search_terms_table_exists';
        if ( get_transient( $cache_key ) ) return;

        global $wpdb;
        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name )
        );

        if ( ! $exists ) {
            $this->create_table();
        }

        set_transient( $cache_key, true, 7 * DAY_IN_SECONDS );
    }

    private function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'search',
            `rank` int(11) NOT NULL DEFAULT 1,
            date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name_type (name, type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Tablo adını döner — dışarıdan SQL yazan kodlar için.
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    /**
     * WP'nin ana arama query'sini otomatik yakalar.
     * the_posts filter'ı ile sonuç varsa terimi kaydeder.
     * Admin, AJAX ve cron'da çalışmaz.
     *
     * @param  array     $posts Bulunan post'lar
     * @param  \WP_Query $query WP_Query instance
     * @return array            Post'lar (dokunulmaz)
     */
    public function auto_track_search( array $posts, \WP_Query $query ): array {
        // Sadece ana query + arama + frontend
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return $posts;
        if ( ! $query->is_main_query() || ! $query->is_search() ) return $posts;
        if ( empty( $posts ) ) return $posts;

        $term = get_query_var( 's', '' );
        if ( empty( trim( $term ) ) ) {
            $term = get_query_var( 'q', '' ); // custom query var
        }

        if ( ! empty( trim( $term ) ) ) {
            $post_type = $query->get( 'post_type', 'search' );
            if ( is_array( $post_type ) ) {
                $post_type = 'search';
            }
            // set_term içinde tekrar WP_Query çalıştırmaya gerek yok — sonuç zaten var
            $this->upsert_term( trim( mb_strtolower( $term ) ), $post_type ?: 'search' );

            if ( is_user_logged_in() ) {
                $this->add_to_user_meta( trim( mb_strtolower( $term ) ) );
            } else {
                $this->add_to_cookie( trim( mb_strtolower( $term ) ) );
            }
        }

        return $posts;
    }

    // =========================================================================
    // SET — Arama terimi kaydet
    // =========================================================================

    /**
     * Arama terimini DB'ye kaydeder (sonuç varsa).
     * Aynı term+type varsa rank artırılır (ON DUPLICATE KEY UPDATE).
     *
     * @param string $term  Arama terimi
     * @param string $type  Post type veya 'search'/'any' (tüm public post type'lar)
     */
    public function set_term( string $term, string $type = 'search' ): void {
        global $wpdb;

        $term = trim( mb_strtolower( $term ) );
        if ( empty( $term ) ) return;

        // Hangi post type'larda aranacak?
        if ( $type === 'search' || $type === 'any' ) {
            // Tek sorgu ile tüm public post type'larda ara
            $query = new \WP_Query( [
                'post_type'        => 'any',
                's'                => $term,
                'posts_per_page'   => 1,
                'fields'           => 'ids',
                'suppress_filters' => true,
                'no_found_rows'    => true,
            ] );

            if ( ! $query->have_posts() ) return;

            // Sonuç varsa 'search' olarak kaydet
            $this->upsert_term( $term, 'search' );
        } else {
            // Belirli post type'ta ara
            $query = new \WP_Query( [
                'post_type'        => sanitize_key( $type ),
                's'                => $term,
                'posts_per_page'   => 1,
                'fields'           => 'ids',
                'suppress_filters' => true,
                'no_found_rows'    => true,
            ] );

            if ( ! $query->have_posts() ) return;

            $this->upsert_term( $term, $type );
        }

        // Kullanıcı geçmişine ekle
        if ( is_user_logged_in() ) {
            $this->add_to_user_meta( $term );
        } else {
            $this->add_to_cookie( $term );
        }
    }

    /**
     * DB'ye upsert — varsa rank artır, yoksa ekle.
     */
    private function upsert_term( string $term, string $type ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$this->table_name} (name, type, `rank`, date, date_modified)
             VALUES (%s, %s, 1, %s, %s)
             ON DUPLICATE KEY UPDATE `rank` = `rank` + 1, date_modified = VALUES(date_modified)",
            $term,
            $type,
            current_time( 'mysql' ),
            current_time( 'mysql' )
        ) );
    }


    // =========================================================================
    // GET — Kullanıcı geçmişi
    // =========================================================================

    /**
     * Kullanıcının son arama terimleri.
     * Login → user_meta, guest → cookie.
     *
     * @param  int    $user_id  0 ise current user
     * @param  string $type     Kullanılmıyor (geriye uyumluluk)
     * @param  int    $count    Kaç adet
     * @return string[]
     */
    public function get_user_terms( int $user_id = 0, string $type = 'search', int $count = 5 ): array {
        // Guest — cookie'den
        if ( ! is_user_logged_in() && $user_id < 1 ) {
            return $this->get_cookie_terms( $count );
        }

        if ( $user_id < 1 ) {
            $user_id = (int) get_current_user_id();
        }

        if ( $user_id < 1 ) return [];

        $terms = get_user_meta( $user_id, 'search_terms', true );

        if ( ! is_array( $terms ) || empty( $terms ) ) {
            return [];
        }

        return array_slice( array_values( $terms ), -$count );
    }


    // =========================================================================
    // GET — Popüler terimler
    // =========================================================================

    /**
     * En popüler arama terimleri (rank'e göre).
     * QueryCache varsa cache'lenir.
     *
     * @return string[]
     */
    public function get_popular_terms( string $type = 'search', int $count = 5 ): array {
        $fetcher = function () use ( $type, $count ) {
            global $wpdb;
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT name FROM {$this->table_name} WHERE type = %s ORDER BY `rank` DESC LIMIT %d",
                $type,
                $count
            ), ARRAY_A );
            return wp_list_pluck( $results, 'name' );
        };

        if ( class_exists( 'QueryCache' ) ) {
            return \QueryCache::wrap( "popular_search_{$type}_{$count}", $fetcher );
        }

        return $fetcher();
    }


    // =========================================================================
    // DID YOU MEAN — Öneri
    // =========================================================================

    /**
     * Levenshtein mesafesi ile "bunu mu demek istediniz?" önerisi.
     * Tek sonuç döner.
     */
    public function did_you_mean( string $input, int $max_distance = 2, int $limit = 200 ): ?string {
        $results = $this->_levenshtein_search( $input, $max_distance, $limit, 1 );
        return $results[0] ?? null;
    }

    /**
     * Levenshtein mesafesi ile arama önerileri.
     * Birden fazla sonuç döner.
     */
    public function suggestions( string $input, int $count = 5, int $max_distance = 6, int $limit = 200 ): array {
        return $this->_levenshtein_search( $input, $max_distance, $limit, $count );
    }

    /**
     * Ortak levenshtein arama motoru.
     */
    private function _levenshtein_search( string $input, int $max_distance, int $limit, int $count ): array {
        global $wpdb;

        $input = trim( mb_strtolower( $input ) );
        if ( empty( $input ) ) return [];

        $terms = $wpdb->get_col( $wpdb->prepare(
            "SELECT name FROM {$this->table_name} ORDER BY `rank` DESC LIMIT %d",
            $limit
        ) );

        if ( empty( $terms ) ) return [];

        $matches = [];
        foreach ( $terms as $term ) {
            if ( $term === $input ) continue; // tam eşleşme — öneri gereksiz
            $distance = levenshtein( $input, $term );
            if ( $distance <= $max_distance ) {
                $matches[ $term ] = $distance;
            }
        }

        asort( $matches );
        return array_slice( array_keys( $matches ), 0, $count );
    }


    // =========================================================================
    // ADMIN — Tüm kayıtlar
    // =========================================================================

    /**
     * Admin paneli için tüm kayıtları döner.
     * acf-admin.php'deki search ranks tablosu için.
     *
     * @return array
     */
    public function get_all( string $orderby = 'rank', string $order = 'DESC' ): array {
        global $wpdb;

        $allowed_orderby = [ 'rank', 'name', 'type', 'date', 'date_modified' ];
        $orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'rank';
        $order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY `{$orderby}` {$order}"
        );
    }

    /**
     * Tekil kayıt sil.
     */
    public function delete_term( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
    }


    // =========================================================================
    // PRIVATE — User meta / Cookie
    // =========================================================================

    private function add_to_user_meta( string $term ): void {
        $user_id = (int) get_current_user_id();
        if ( $user_id < 1 ) return;

        $terms = get_user_meta( $user_id, 'search_terms', true );
        if ( ! is_array( $terms ) ) {
            $terms = [];
        }

        // Duplicate kontrolü
        if ( in_array( $term, $terms, true ) ) return;

        $terms[] = $term;

        // Max 50 terim sakla
        if ( count( $terms ) > 50 ) {
            $terms = array_slice( $terms, -50 );
        }

        update_user_meta( $user_id, 'search_terms', $terms );
    }

    private function add_to_cookie( string $term ): void {
        // Headers gönderilmişse cookie set edemeyiz
        if ( headers_sent() ) return;

        $cookie_name = 'wp_search_terms';
        $raw         = $_COOKIE[ $cookie_name ] ?? '';
        $terms       = [];

        if ( ! empty( $raw ) ) {
            $decoded = json_decode( stripslashes( $raw ), true );
            if ( is_array( $decoded ) ) {
                $terms = $decoded;
            }
        }

        if ( in_array( $term, $terms, true ) ) return;

        $terms[] = $term;

        // Max 20 terim cookie'de
        if ( count( $terms ) > 20 ) {
            $terms = array_slice( $terms, -20 );
        }

        setcookie( $cookie_name, json_encode( $terms ), time() + ( 86400 * 30 ), '/' );
    }

    private function get_cookie_terms( int $count ): array {
        $cookie_name = 'wp_search_terms';
        $raw         = $_COOKIE[ $cookie_name ] ?? '';

        if ( empty( $raw ) ) return [];

        $decoded = json_decode( stripslashes( $raw ), true );
        if ( ! is_array( $decoded ) ) return [];

        return array_slice( $decoded, -$count );
    }
}
