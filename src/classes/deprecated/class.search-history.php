<?php

/**
 * SearchHistory — Arama gecmisi, populer/trend terimler, istatistik ve admin yonetimi.
 *
 * Custom DB tablosu (wp_search_terms) kullanir.
 * Kullanici bazli gecmis user_meta + cookie ile saklanir.
 * Admin sayfasi, AJAX handler'lar ve Chart.js entegrasyonu dahildir (ACF bagimsiz).
 * FiboSearch (dgwt/wcas) entegrasyonu the_posts filter uzerinden saglanir.
 *
 * @package    SaltHareket\Theme
 * @version    3.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * CHANGELOG:
 * 3.0.0 - 2026-05-10
 *   - Refactor: SearchHistoryAdmin trait kaldirildi — tum admin kodu class icine tasindi
 *   - Refactor: DB schema degisti — no_result_count -> no_results (bool TINYINT)
 *   - Refactor: set_term() icindeki WP_Query tamamen kaldirildi (double query bug fix)
 *   - Refactor: upsert_term() artik no_results parametresi aliyor
 *   - Add: MIN_TERM_LENGTH sabiti (2)
 *   - Add: get_no_results_terms() — no_results=1 olan terimler, rank'e gore sirali
 *   - Add: get_trending_terms() — son N gunde aranan terimler
 *   - Add: get_stats() — total_searches, unique_terms, no_results_count, top_type
 *   - Add: get_chart_data() — son N gunluk gunluk arama sayisi (Chart.js icin)
 *   - Add: get_top_types() — type bazli gruplama
 *   - Add: delete_all() — tabloyu truncate et (nonce dogrulama ile)
 *   - Add: delete_old_terms() — eski dusuk rank'li kayitlari sil
 *   - Add: schedule_cleanup() + run_cleanup() — WP Cron entegrasyonu
 *   - Add: get_blacklist(), add_to_blacklist(), remove_from_blacklist()
 *   - Add: register_admin_page(), render_admin_page() — native WP admin sayfasi
 *   - Add: ajax_delete_term(), ajax_delete_all(), ajax_export_csv() — AJAX handler'lar
 *   - Add: track_fibosearch_query() — dgwt/wcas/search_query/args filter
 *   - Add: auto_track_search() — $_GET['s'] fallback + no_results tracking
 *   - Add: Constructor static flag — coklu instantiation guvenli
 *   - Add: Chart.js CDN sadece admin sayfasinda yukleniyor
 *
 * 2.0.0 - 2026-05-03
 *   - Refactor: set_term() icindeki gereksiz WP_Query kaldirildi
 *   - Refactor: ACF bagimliligI kaldirildi, SearchHistoryAdmin trait eklendi
 *   - Add: record_no_result(), get_trending_terms(), get_no_result_terms()
 *   - Add: get_stats(), get_daily_counts(), cleanup_old_terms(), export_csv()
 *   - Add: Blacklist destegi, min 2 karakter kontrolu
 *   - Add: FiboSearch no_results filter entegrasyonu
 *   - Add: sh_daily_cleanup cron action
 *
 * 1.0.0 - 2026-04-03
 *   - Add: Initial versioned release
 *
 * HOW TO USE:
 *   Sinif variables.php icinde ENABLE_SEARCH_HISTORY true ise otomatik yuklenir.
 *   Dogrudan instantiate etmeye gerek yoktur — constructor hook'lari otomatik baglar.
 *
 *   // Manuel kullanim (search.php gibi):
 *   if ( class_exists('SearchHistory') ) {
 *       $sh = new SearchHistory();
 *       $sh->set_term( get_query_var('s'), 'product' );
 *   }
 *
 *   // Populer aramalar:
 *   $popular = $sh->get_popular_terms( 'product', 10 );
 *
 *   // Trend aramalar (son 7 gun):
 *   $trending = $sh->get_trending_terms( 7, 10 );
 *
 *   // Sifir sonuclu aramalar:
 *   $no_results = $sh->get_no_results_terms( 10 );
 *
 *   // Istatistikler:
 *   $stats = $sh->get_stats();
 *   // ['total_searches'=>1500,'unique_terms'=>320,'no_results_count'=>45,'top_type'=>'product']
 *
 *   // "Bunu mu demek istediniz?":
 *   $suggestion = $sh->did_you_mean( 'iphne', 2 );
 *   // 'iphone'
 *
 * @example Twig'de populer aramalar:
 *   {% for term in popular_terms %}
 *       <a href="/?s={{ term }}">{{ term }}</a>
 *   {% endfor %}
 *
 * @example Twig'de kullanici gecmisi:
 *   {% for term in user_search_terms %}
 *       <span class="recent-search">{{ term }}</span>
 *   {% endfor %}
 *
 * @example PHP'de oneri sistemi:
 *   $sh = new SearchHistory();
 *   $suggestions = $sh->suggestions( $input, 5, 3, 200 );
 *   foreach ( $suggestions as $s ) { echo esc_html( $s ); }
 *
 * @example PHP'de chart verisi:
 *   $chart = $sh->get_chart_data( 30 );
 *   // [['date' => '2026-05-01', 'count' => 42], ...]
 *
 * @example PHP'de istatistik karti:
 *   $stats = $sh->get_stats();
 *   echo 'Toplam arama: ' . $stats['total_searches'];
 *   echo 'Sonucsuz: ' . $stats['no_results_count'];
 */

class SearchHistory {

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /** Minimum arama terimi uzunlugu */
    const MIN_TERM_LENGTH = 2;

    /** Blacklist option key */
    const BLACKLIST_OPTION = 'sh_blacklist';

    /** Cron hook adi */
    const CRON_HOOK = 'sh_daily_cleanup';

    // =========================================================================
    // PROPERTIES
    // =========================================================================

    private string $table_name;

    /** @var string[] Kaydedilmeyecek terimler */
    private array $blacklist = [];

    /** @var bool Constructor'in birden fazla calismasini engeller */
    private static bool $hooks_registered = false;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'search_terms';
        $this->maybe_create_table();
        $this->load_blacklist();

        // Coklu instantiation'da hook'lari tekrar kaydetme
        if ( self::$hooks_registered ) {
            return;
        }
        self::$hooks_registered = true;

        // WP arama query'sini otomatik yakala
        add_filter( 'the_posts', [ $this, 'auto_track_search' ], 10, 2 );

        // FiboSearch: search_query/args filter uzerinden terimi yakala
        add_filter( 'dgwt/wcas/search_query/args', [ $this, 'track_fibosearch_query' ], 10, 1 );

        // Admin sayfasi ve AJAX handler'lar
        add_action( 'admin_menu',                [ $this, 'register_admin_page' ] );
        add_action( 'wp_ajax_sh_delete_term',    [ $this, 'ajax_delete_term' ] );
        add_action( 'wp_ajax_sh_delete_all',     [ $this, 'ajax_delete_all' ] );
        add_action( 'wp_ajax_sh_export_csv',     [ $this, 'ajax_export_csv' ] );

        // Cron
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_cleanup' ] );
        add_action( 'wp',            [ __CLASS__, 'schedule_cleanup' ] );
    }


    // =========================================================================
    // SETUP — Tablo olusturma ve migration
    // =========================================================================

    /**
     * Tablo varligini transient ile cache'le — her instantiation'da SHOW TABLES calismasin.
     */
    private function maybe_create_table(): void {
        $cache_key = 'sh_search_terms_table_v3';
        if ( get_transient( $cache_key ) ) {
            return;
        }

        global $wpdb;
        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name )
        );

        if ( ! $exists ) {
            $this->create_table();
        } else {
            $this->maybe_migrate_table();
        }

        set_transient( $cache_key, true, 7 * DAY_IN_SECONDS );
    }

    /**
     * Tabloyu olustur.
     * Schema: id, name, type, rank, no_results (bool), date, date_modified
     */
    private function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name          varchar(255)        NOT NULL,
            type          varchar(50)         NOT NULL DEFAULT 'search',
            `rank`        int(11)             NOT NULL DEFAULT 1,
            no_results    tinyint(1)          NOT NULL DEFAULT 0,
            date          datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   name_type (name, type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Mevcut tabloya no_results kolonu ekle (schema migration).
     * Eski no_result_count kolonunu da kaldir (varsa).
     */
    private function maybe_migrate_table(): void {
        global $wpdb;

        // no_results kolonu var mi?
        $has_no_results = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name}` LIKE %s",
            'no_results'
        ) );

        if ( empty( $has_no_results ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query(
                "ALTER TABLE `{$this->table_name}`
                 ADD COLUMN `no_results` tinyint(1) NOT NULL DEFAULT 0 AFTER `rank`"
            );
        }

        // Eski no_result_count kolonunu kaldir (varsa)
        $has_old_col = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name}` LIKE %s",
            'no_result_count'
        ) );

        if ( ! empty( $has_old_col ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query(
                "ALTER TABLE `{$this->table_name}` DROP COLUMN `no_result_count`"
            );
        }
    }

    /**
     * Tablo adini doner — disaridan SQL yazan kodlar icin.
     */
    public function get_table_name(): string {
        return $this->table_name;
    }


    // =========================================================================
    // BLACKLIST
    // =========================================================================

    /**
     * Blacklist'i option'dan yukle.
     */
    private function load_blacklist(): void {
        $saved          = get_option( self::BLACKLIST_OPTION, [] );
        $this->blacklist = is_array( $saved )
            ? array_map( 'mb_strtolower', array_column( $saved, 'term' ) )
            : [];
    }

    /**
     * Blacklist'teki tum kayitlari doner.
     *
     * @return array  [['id' => int, 'term' => string], ...]
     */
    public function get_blacklist(): array {
        $saved = get_option( self::BLACKLIST_OPTION, [] );
        return is_array( $saved ) ? $saved : [];
    }

    /**
     * Blacklist'e yeni terim ekle.
     *
     * @param string $term Eklenecek terim
     * @return bool
     */
    public function add_to_blacklist( string $term ): bool {
        $term = trim( mb_strtolower( $term ) );
        if ( empty( $term ) ) {
            return false;
        }

        $list = $this->get_blacklist();

        // Zaten var mi?
        foreach ( $list as $item ) {
            if ( isset( $item['term'] ) && $item['term'] === $term ) {
                return false;
            }
        }

        $list[] = [
            'id'   => time(),
            'term' => $term,
        ];

        $updated = update_option( self::BLACKLIST_OPTION, $list );
        if ( $updated ) {
            $this->load_blacklist();
        }
        return $updated;
    }

    /**
     * Blacklist'ten terim kaldir.
     *
     * @param int $id Kayit id'si (add_to_blacklist'te atanan timestamp)
     * @return bool
     */
    public function remove_from_blacklist( int $id ): bool {
        $list    = $this->get_blacklist();
        $updated = array_values( array_filter( $list, fn( $item ) => (int) ( $item['id'] ?? 0 ) !== $id ) );

        $result = update_option( self::BLACKLIST_OPTION, $updated );
        if ( $result ) {
            $this->load_blacklist();
        }
        return $result;
    }

    /**
     * Terimin kaydedilip kaydedilmeyecegini kontrol et.
     */
    private function is_valid_term( string $term ): bool {
        if ( mb_strlen( $term ) < self::MIN_TERM_LENGTH ) {
            return false;
        }
        if ( in_array( mb_strtolower( $term ), $this->blacklist, true ) ) {
            return false;
        }
        return true;
    }


    // =========================================================================
    // AUTO TRACK — WP arama query'sini otomatik yakala
    // =========================================================================

    /**
     * WP'nin ana arama query'sini otomatik yakalar.
     * the_posts filter'i ile sonuc varsa terimi kaydeder, yoksa no_results=1 kaydeder.
     * Admin, AJAX ve cron'da calismaZ.
     *
     * @param  array     $posts  Bulunan post'lar
     * @param  \WP_Query $query  WP_Query instance
     * @return array             Post'lar (dokunulmaz)
     */
    public function auto_track_search( array $posts, \WP_Query $query ): array {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return $posts;
        }
        if ( ! $query->is_main_query() || ! $query->is_search() ) {
            return $posts;
        }

        // Terimi al — get_query_var bos gelirse $_GET['s'] fallback
        $term = (string) get_query_var( 's', '' );
        if ( '' === trim( $term ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        }
        $term = trim( mb_strtolower( $term ) );

        if ( '' === $term || ! $this->is_valid_term( $term ) ) {
            return $posts;
        }

        $post_type = $query->get( 'post_type', 'search' );
        if ( is_array( $post_type ) ) {
            $post_type = 'search';
        }
        $type = ( '' !== $post_type ) ? $post_type : 'search';

        $no_results = empty( $posts );

        $this->upsert_term( $term, $type, $no_results );

        if ( ! $no_results ) {
            if ( is_user_logged_in() ) {
                $this->add_to_user_meta( $term );
            } else {
                $this->add_to_cookie( $term );
            }
        }

        return $posts;
    }

    /**
     * FiboSearch: dgwt/wcas/search_query/args filter uzerinden arama terimini yakala.
     * Sonuc bilgisi bu noktada mevcut olmadigi icin sadece terimi kaydeder (no_results=false).
     * Gercek no_results takibi auto_track_search ile yapilir.
     *
     * @param  array $args WP_Query args
     * @return array       Degistirilmemis args
     */
    public function track_fibosearch_query( array $args ): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        if ( '' === $term ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        }
        $term = trim( mb_strtolower( $term ) );

        if ( '' !== $term && $this->is_valid_term( $term ) ) {
            $this->upsert_term( $term, 'fibosearch', false );
        }

        return $args;
    }


    // =========================================================================
    // SET — Arama terimi kaydet
    // =========================================================================

    /**
     * Arama terimini DB'ye kaydeder.
     * WP_Query calistirmaz — sadece upsert + user meta/cookie.
     * Sonuc kontrolu cagiran tarafin sorumlulugundadir.
     *
     * @param string $term       Arama terimi
     * @param string $type       Post type veya 'search'
     * @param bool   $no_results Sonuc bulunamadiysa true
     */
    public function set_term( string $term, string $type = 'search', bool $no_results = false ): void {
        $term = trim( mb_strtolower( $term ) );
        if ( '' === $term || ! $this->is_valid_term( $term ) ) {
            return;
        }

        $this->upsert_term( $term, ( '' !== $type ) ? $type : 'search', $no_results );

        if ( ! $no_results ) {
            if ( is_user_logged_in() ) {
                $this->add_to_user_meta( $term );
            } else {
                $this->add_to_cookie( $term );
            }
        }
    }

    /**
     * DB'ye upsert — varsa rank artir, yoksa ekle.
     * no_results=true ise mevcut kaydin no_results=1 olarak guncellenir.
     *
     * @param string $term       Arama terimi
     * @param string $type       Post type veya 'search'
     * @param bool   $no_results Sonuc bulunamadiysa true
     */
    private function upsert_term( string $term, string $type, bool $no_results = false ): void {
        global $wpdb;

        $no_results_int = $no_results ? 1 : 0;
        $now            = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO `{$this->table_name}` (name, type, `rank`, no_results, date, date_modified)
             VALUES (%s, %s, 1, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                `rank`        = `rank` + 1,
                no_results    = VALUES(no_results),
                date_modified = VALUES(date_modified)",
            $term,
            $type,
            $no_results_int,
            $now,
            $now
        ) );
    }


    // =========================================================================
    // GET — Kullanici gecmisi
    // =========================================================================

    /**
     * Kullanicinin son arama terimleri.
     * Login -> user_meta, guest -> cookie.
     *
     * @param  int    $user_id  0 ise current user
     * @param  string $type     Kullanilmiyor (geriye uyumluluk)
     * @param  int    $count    Kac adet
     * @return string[]
     */
    public function get_user_terms( int $user_id = 0, string $type = 'search', int $count = 5 ): array {
        if ( ! is_user_logged_in() && $user_id < 1 ) {
            return $this->get_cookie_terms( $count );
        }

        if ( $user_id < 1 ) {
            $user_id = (int) get_current_user_id();
        }

        if ( $user_id < 1 ) {
            return [];
        }

        $terms = get_user_meta( $user_id, 'search_terms', true );

        if ( ! is_array( $terms ) || empty( $terms ) ) {
            return [];
        }

        return array_slice( array_values( $terms ), -$count );
    }

    // =========================================================================
    // GET — Populer / Trend terimler
    // =========================================================================

    /**
     * En populer arama terimleri (rank'e gore).
     *
     * @param  string $type  Post type veya 'search'
     * @param  int    $count Kac adet
     * @return string[]
     */
    public function get_popular_terms( string $type = 'search', int $count = 5 ): array {
        $fetcher = function () use ( $type, $count ) {
            global $wpdb;
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT name FROM `{$this->table_name}`
                 WHERE type = %s AND no_results = 0
                 ORDER BY `rank` DESC
                 LIMIT %d",
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

    /**
     * Son X gunde en cok aranan terimler (trend).
     *
     * @param  int $days  Kac gunluk pencere
     * @param  int $count Kac adet
     * @return array      [['name'=>string, 'type'=>string, 'rank'=>int], ...]
     */
    public function get_trending_terms( int $days = 7, int $count = 10 ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT name, type, `rank`
             FROM `{$this->table_name}`
             WHERE date_modified >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND no_results = 0
             ORDER BY `rank` DESC
             LIMIT %d",
            $days,
            $count
        ), ARRAY_A );
    }

    /**
     * Sifir sonuclu en cok aranan terimler.
     *
     * @param  int $count Kac adet
     * @return array      [['name'=>string, 'rank'=>int], ...]
     */
    public function get_no_results_terms( int $count = 10 ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT name, `rank`
             FROM `{$this->table_name}`
             WHERE no_results = 1
             ORDER BY `rank` DESC
             LIMIT %d",
            $count
        ), ARRAY_A );
    }

    /**
     * Dashboard istatistikleri.
     *
     * @return array {total_searches:int, unique_terms:int, no_results_count:int, top_type:string}
     */
    public function get_stats(): array {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            "SELECT SUM(`rank`) FROM `{$this->table_name}` WHERE no_results = 0"
        );

        $unique = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$this->table_name}` WHERE no_results = 0"
        );

        $no_results_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$this->table_name}` WHERE no_results = 1"
        );

        $top_type = (string) $wpdb->get_var(
            "SELECT type FROM `{$this->table_name}`
             WHERE no_results = 0
             GROUP BY type
             ORDER BY SUM(`rank`) DESC
             LIMIT 1"
        );

        return [
            'total_searches'  => $total,
            'unique_terms'    => $unique,
            'no_results_count' => $no_results_count,
            'top_type'        => $top_type,
        ];
    }

    /**
     * Son X gunluk gunluk arama sayisi (Chart.js icin).
     *
     * @param  int $days Kac gunluk pencere
     * @return array     [['date'=>'YYYY-MM-DD', 'count'=>int], ...]
     */
    public function get_chart_data( int $days = 30 ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(date_modified) AS `date`, COUNT(*) AS `count`
             FROM `{$this->table_name}`
             WHERE date_modified >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND no_results = 0
             GROUP BY DATE(date_modified)
             ORDER BY `date` ASC",
            $days
        ), ARRAY_A );
    }

    /**
     * Type bazli gruplama — hangi type kac arama almis.
     *
     * @param  int $count Kac adet
     * @return array      [['type'=>string, 'total'=>int], ...]
     */
    public function get_top_types( int $count = 5 ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT type, SUM(`rank`) AS total
             FROM `{$this->table_name}`
             WHERE no_results = 0
             GROUP BY type
             ORDER BY total DESC
             LIMIT %d",
            $count
        ), ARRAY_A );
    }


    // =========================================================================
    // GET — Tum kayitlar (admin)
    // =========================================================================

    /**
     * Admin paneli icin tum kayitlari doner.
     *
     * @param  string $orderby Siralama kolonu
     * @param  string $order   ASC | DESC
     * @return object[]
     */
    public function get_all( string $orderby = 'rank', string $order = 'DESC' ): array {
        global $wpdb;

        $allowed_orderby = [ 'rank', 'name', 'type', 'date', 'date_modified', 'no_results' ];
        $orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'rank';
        $order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT * FROM `{$this->table_name}` ORDER BY `{$orderby}` {$order}"
        );
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    /**
     * Tekil kayit sil.
     *
     * @param  int $id Kayit ID'si
     * @return bool
     */
    public function delete_term( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Tum kayitlari sil (TRUNCATE).
     * Admin AJAX'tan cagrildiginda nonce dogrulamasi ajax_delete_all() tarafindan yapilir.
     *
     * @return bool
     */
    public function delete_all(): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->query( "TRUNCATE TABLE `{$this->table_name}`" );
    }

    /**
     * Eski ve dusuk rank'li kayitlari sil.
     *
     * @param int $days     Kac gunden eski kayitlar silinsin
     * @param int $min_rank Bu rank'in altindaki kayitlar silinsin
     */
    public function delete_old_terms( int $days = 90, int $min_rank = 2 ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$this->table_name}`
             WHERE date_modified < DATE_SUB(NOW(), INTERVAL %d DAY)
               AND `rank` < %d",
            $days,
            $min_rank
        ) );
    }

    // =========================================================================
    // CRON — Zamanlanmis temizlik
    // =========================================================================

    /**
     * WP Cron'a gunluk temizlik gorevi kaydet.
     * add_action('wp', ...) ile cagrilir.
     */
    public static function schedule_cleanup(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Cron callback — eski dusuk rank'li kayitlari sil.
     */
    public static function run_cleanup(): void {
        $instance = new self();
        $instance->delete_old_terms( 90, 2 );
    }


    // =========================================================================
    // DID YOU MEAN — Oneri sistemi
    // =========================================================================

    /**
     * Levenshtein mesafesi ile "bunu mu demek istediniz?" onerisi.
     *
     * @param  string   $input        Kullanicinin girdisi
     * @param  int      $max_distance Maksimum Levenshtein mesafesi
     * @param  int      $limit        DB'den kac terim cekilsin
     * @return string|null            En yakin terim veya null
     */
    public function did_you_mean( string $input, int $max_distance = 2, int $limit = 200 ): ?string {
        $results = $this->levenshtein_search( $input, $max_distance, $limit, 1 );
        return $results[0] ?? null;
    }

    /**
     * Levenshtein mesafesi ile arama onerileri.
     *
     * @param  string $input        Kullanicinin girdisi
     * @param  int    $count        Kac oneri donulsun
     * @param  int    $max_distance Maksimum Levenshtein mesafesi
     * @param  int    $limit        DB'den kac terim cekilsin
     * @return string[]
     */
    public function suggestions( string $input, int $count = 5, int $max_distance = 6, int $limit = 200 ): array {
        return $this->levenshtein_search( $input, $max_distance, $limit, $count );
    }

    /**
     * Ortak Levenshtein arama motoru.
     */
    private function levenshtein_search( string $input, int $max_distance, int $limit, int $count ): array {
        global $wpdb;

        $input = trim( mb_strtolower( $input ) );
        if ( '' === $input ) {
            return [];
        }

        $terms = $wpdb->get_col( $wpdb->prepare(
            "SELECT name FROM `{$this->table_name}`
             WHERE no_results = 0
             ORDER BY `rank` DESC
             LIMIT %d",
            $limit
        ) );

        if ( empty( $terms ) ) {
            return [];
        }

        $matches = [];
        foreach ( $terms as $term ) {
            if ( $term === $input ) {
                continue;
            }
            $distance = levenshtein( $input, $term );
            if ( $distance <= $max_distance ) {
                $matches[ $term ] = $distance;
            }
        }

        asort( $matches );
        return array_slice( array_keys( $matches ), 0, $count );
    }


    // =========================================================================
    // PRIVATE — User meta / Cookie
    // =========================================================================

    /**
     * Arama terimini kullanici meta'sina ekle.
     */
    private function add_to_user_meta( string $term ): void {
        $user_id = (int) get_current_user_id();
        if ( $user_id < 1 ) {
            return;
        }

        $terms = get_user_meta( $user_id, 'search_terms', true );
        if ( ! is_array( $terms ) ) {
            $terms = [];
        }

        if ( in_array( $term, $terms, true ) ) {
            return;
        }

        $terms[] = $term;

        if ( count( $terms ) > 50 ) {
            $terms = array_slice( $terms, -50 );
        }

        update_user_meta( $user_id, 'search_terms', $terms );
    }

    /**
     * Arama terimini cookie'ye ekle.
     */
    private function add_to_cookie( string $term ): void {
        if ( headers_sent() ) {
            return;
        }

        $cookie_name = 'wp_search_terms';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw   = isset( $_COOKIE[ $cookie_name ] ) ? stripslashes( $_COOKIE[ $cookie_name ] ) : '';
        $terms = [];

        if ( '' !== $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                $terms = $decoded;
            }
        }

        if ( in_array( $term, $terms, true ) ) {
            return;
        }

        $terms[] = $term;

        if ( count( $terms ) > 20 ) {
            $terms = array_slice( $terms, -20 );
        }

        setcookie( $cookie_name, (string) wp_json_encode( $terms ), time() + ( 86400 * 30 ), '/', '', is_ssl(), true );
    }

    /**
     * Cookie'den arama terimlerini al.
     *
     * @param  int $count Kac adet
     * @return string[]
     */
    private function get_cookie_terms( int $count ): array {
        $cookie_name = 'wp_search_terms';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw = isset( $_COOKIE[ $cookie_name ] ) ? stripslashes( $_COOKIE[ $cookie_name ] ) : '';

        if ( '' === $raw ) {
            return [];
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return array_slice( $decoded, -$count );
    }


    // =========================================================================
    // ADMIN — Sayfa kaydi ve enqueue
    // =========================================================================

    /**
     * Admin alt menu sayfasini kaydet.
     * Theme Settings altinda "Search Ranks" olarak gorunur.
     */
    public function register_admin_page(): void {
        $hook = add_submenu_page(
            'theme-settings',
            __( 'Search Ranks', 'salthareket' ),
            __( 'Search Ranks', 'salthareket' ),
            'manage_options',
            'search-history',
            [ $this, 'render_admin_page' ]
        );

        // Chart.js ve inline CSS/JS sadece bu sayfada yukle
        add_action( "admin_print_scripts-{$hook}", [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Chart.js CDN ve inline admin stillerini yukle.
     */
    public function enqueue_admin_assets(): void {
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js',
            [],
            '4.4.2',
            true
        );
    }

    // =========================================================================
    // ADMIN — AJAX handler'lar
    // =========================================================================

    /**
     * AJAX: Tekil kayit sil.
     */
    public function ajax_delete_term(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $id < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Gecersiz ID.', 'salthareket' ) ], 400 );
        }

        if ( $this->delete_term( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Kayit silindi.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Silinemedi.', 'salthareket' ) ], 500 );
        }
    }

    /**
     * AJAX: Tum kayitlari sil.
     */
    public function ajax_delete_all(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        if ( $this->delete_all() ) {
            wp_send_json_success( [ 'message' => __( 'Tum kayitlar silindi.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Silinemedi.', 'salthareket' ) ], 500 );
        }
    }

    /**
     * AJAX: CSV export.
     */
    public function ajax_export_csv(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetersiz yetki.', 'salthareket' ), 403 );
        }

        $rows    = $this->get_all( 'rank', 'DESC' );
        $filename = 'search-history-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            wp_die( 'CSV output stream acilamadi.' );
        }

        // BOM — Excel UTF-8 uyumlulugu
        fputs( $output, "\xEF\xBB\xBF" );

        fputcsv( $output, [ 'ID', 'Term', 'Type', 'Rank', 'No Results', 'First Seen', 'Last Searched' ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $row->id,
                urldecode( $row->name ),
                $row->type,
                $row->rank,
                $row->no_results ? 'Yes' : 'No',
                $row->date,
                $row->date_modified,
            ] );
        }

        fclose( $output );
        exit;
    }


    // =========================================================================
    // ADMIN — render_admin_page()
    // =========================================================================

    /**
     * Admin sayfasini render et.
     * Sticky toolbar, Chart.js grafik, istatistik kartlari,
     * filtrelenebilir/siralalanabilir tablo ve blacklist yonetimi icerir.
     */
    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetersiz yetki.', 'salthareket' ) );
        }

        $stats        = $this->get_stats();
        $all_rows     = $this->get_all( 'rank', 'DESC' );
        $chart_data   = $this->get_chart_data( 30 );
        $trending     = $this->get_trending_terms( 7, 50 );
        $no_res_terms = $this->get_no_results_terms( 10 );
        $blacklist    = $this->get_blacklist();
        $nonce        = wp_create_nonce( 'sh_nonce' );
        $ajax_url     = esc_url( admin_url( 'admin-ajax.php' ) );

        // Trending term isimleri (badge icin)
        $trending_names = array_column( $trending, 'name' );

        // Chart.js icin JSON
        $chart_labels = wp_json_encode( array_column( $chart_data, 'date' ) );
        $chart_counts = wp_json_encode( array_map( 'intval', array_column( $chart_data, 'count' ) ) );

        ?>
        <div class="wrap sh-wrap" id="sh-admin-page">

        <style>
        .sh-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .sh-toolbar {
            position: sticky; top: 32px; z-index: 100;
            background: #fff; border-bottom: 1px solid #ddd;
            padding: 12px 16px; display: flex; align-items: center;
            gap: 12px; flex-wrap: wrap; margin: 0 -20px 20px; box-shadow: 0 2px 4px rgba(0,0,0,.06);
        }
        .sh-toolbar h1 { margin: 0; font-size: 18px; flex: 1; }
        .sh-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .sh-badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .sh-badge-green  { background: #dcfce7; color: #15803d; }
        .sh-badge-red    { background: #fee2e2; color: #b91c1c; }
        .sh-badge-orange { background: #ffedd5; color: #c2410c; }
        .sh-btn {
            padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer;
            font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .sh-btn-primary { background: #2563eb; color: #fff; }
        .sh-btn-primary:hover { background: #1d4ed8; color: #fff; }
        .sh-btn-danger  { background: #dc2626; color: #fff; }
        .sh-btn-danger:hover { background: #b91c1c; color: #fff; }
        .sh-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .sh-btn-secondary:hover { background: #e5e7eb; }
        .sh-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 16px; margin-bottom: 24px; }
        .sh-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
            padding: 16px 20px;
        }
        .sh-card h3 { margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .sh-card .sh-card-val { font-size: 28px; font-weight: 700; color: #111827; }
        .sh-card .sh-card-sub { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        .sh-chart-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
        .sh-chart-wrap h2 { margin: 0 0 16px; font-size: 15px; }
        .sh-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; align-items: center; }
        .sh-filters input, .sh-filters select {
            padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;
        }
        .sh-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 24px; }
        .sh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sh-table th {
            background: #f9fafb; padding: 10px 14px; text-align: left;
            border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;
            cursor: pointer; user-select: none; white-space: nowrap;
        }
        .sh-table th:hover { background: #f3f4f6; }
        .sh-table th .sort-icon { opacity: .4; margin-left: 4px; }
        .sh-table th.sorted-asc .sort-icon::after { content: " ▲"; opacity: 1; }
        .sh-table th.sorted-desc .sort-icon::after { content: " ▼"; opacity: 1; }
        .sh-table td { padding: 9px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .sh-table tr:last-child td { border-bottom: none; }
        .sh-table tr:hover td { background: #fafafa; }
        .sh-table .sh-term { font-weight: 500; }
        .sh-table .sh-type { font-size: 11px; background: #f3f4f6; padding: 2px 7px; border-radius: 10px; color: #6b7280; }
        .sh-table .sh-rank { font-weight: 700; color: #2563eb; }
        .sh-no-results-badge { background: #fee2e2; color: #b91c1c; font-size: 11px; padding: 2px 7px; border-radius: 10px; font-weight: 600; }
        .sh-trending-badge { font-size: 14px; margin-left: 4px; }
        .sh-delete-btn {
            background: none; border: none; cursor: pointer; color: #dc2626;
            font-size: 13px; padding: 2px 6px; border-radius: 3px;
        }
        .sh-delete-btn:hover { background: #fee2e2; }
        .sh-pagination { display: flex; gap: 6px; align-items: center; padding: 12px 16px; border-top: 1px solid #f3f4f6; flex-wrap: wrap; }
        .sh-page-btn {
            padding: 4px 10px; border: 1px solid #d1d5db; border-radius: 4px;
            background: #fff; cursor: pointer; font-size: 12px;
        }
        .sh-page-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .sh-page-btn:hover:not(.active) { background: #f3f4f6; }
        .sh-section-title { font-size: 15px; font-weight: 600; margin: 0 0 12px; }
        .sh-blacklist-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .sh-blacklist-list { list-style: none; margin: 0 0 16px; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
        .sh-blacklist-list li {
            display: flex; align-items: center; gap: 6px;
            background: #f3f4f6; border-radius: 20px; padding: 4px 12px; font-size: 13px;
        }
        .sh-blacklist-remove { background: none; border: none; cursor: pointer; color: #dc2626; font-size: 14px; line-height: 1; }
        .sh-add-blacklist { display: flex; gap: 8px; align-items: center; }
        .sh-add-blacklist input { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; width: 220px; }
        #sh-toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            background: #1f2937; color: #fff; padding: 10px 18px; border-radius: 6px;
            font-size: 13px; opacity: 0; transition: opacity .3s; pointer-events: none;
        }
        #sh-toast.show { opacity: 1; }
        .sh-hidden { display: none !important; }
        </style>

        <!-- Sticky Toolbar -->
        <div class="sh-toolbar">
            <h1><?php esc_html_e( 'Search Ranks', 'salthareket' ); ?></h1>
            <span class="sh-badge sh-badge-blue">
                <?php esc_html_e( 'Toplam', 'salthareket' ); ?>: <strong><?php echo esc_html( number_format_i18n( $stats['total_searches'] ) ); ?></strong>
            </span>
            <span class="sh-badge sh-badge-green">
                <?php esc_html_e( 'Unique', 'salthareket' ); ?>: <strong><?php echo esc_html( number_format_i18n( $stats['unique_terms'] ) ); ?></strong>
            </span>
            <span class="sh-badge sh-badge-red">
                <?php esc_html_e( 'No Results', 'salthareket' ); ?>: <strong><?php echo esc_html( number_format_i18n( $stats['no_results_count'] ) ); ?></strong>
            </span>
            <?php if ( $stats['top_type'] ) : ?>
            <span class="sh-badge sh-badge-orange">
                <?php esc_html_e( 'Top Type', 'salthareket' ); ?>: <strong><?php echo esc_html( $stats['top_type'] ); ?></strong>
            </span>
            <?php endif; ?>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=sh_export_csv' ), 'sh_nonce', 'nonce' ) ); ?>"
               class="sh-btn sh-btn-secondary" id="sh-export-btn">
                &#8595; <?php esc_html_e( 'Export CSV', 'salthareket' ); ?>
            </a>
            <button class="sh-btn sh-btn-danger" id="sh-delete-all-btn"
                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                    data-ajax="<?php echo esc_attr( $ajax_url ); ?>">
                &#128465; <?php esc_html_e( 'Delete All', 'salthareket' ); ?>
            </button>
        </div>

        <!-- Stat Cards -->
        <div class="sh-cards">
            <div class="sh-card">
                <h3><?php esc_html_e( 'Toplam Arama', 'salthareket' ); ?></h3>
                <div class="sh-card-val"><?php echo esc_html( number_format_i18n( $stats['total_searches'] ) ); ?></div>
                <div class="sh-card-sub"><?php esc_html_e( 'Son 30 gun dahil', 'salthareket' ); ?></div>
            </div>
            <div class="sh-card">
                <h3><?php esc_html_e( 'Unique Terim', 'salthareket' ); ?></h3>
                <div class="sh-card-val"><?php echo esc_html( number_format_i18n( $stats['unique_terms'] ) ); ?></div>
                <div class="sh-card-sub"><?php esc_html_e( 'Farkli arama terimi', 'salthareket' ); ?></div>
            </div>
            <div class="sh-card">
                <h3><?php esc_html_e( 'No Results', 'salthareket' ); ?></h3>
                <div class="sh-card-val" style="color:#dc2626"><?php echo esc_html( number_format_i18n( $stats['no_results_count'] ) ); ?></div>
                <div class="sh-card-sub"><?php esc_html_e( 'Sonucsuz arama terimi', 'salthareket' ); ?></div>
            </div>
            <?php if ( ! empty( $no_res_terms ) ) : ?>
            <div class="sh-card">
                <h3><?php esc_html_e( 'Top No-Result', 'salthareket' ); ?></h3>
                <div class="sh-card-val" style="font-size:18px"><?php echo esc_html( $no_res_terms[0]['name'] ?? '-' ); ?></div>
                <div class="sh-card-sub"><?php echo esc_html( sprintf( __( '%d kez aranip bulunamadi', 'salthareket' ), (int) ( $no_res_terms[0]['rank'] ?? 0 ) ) ); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Chart.js Grafik -->
        <div class="sh-chart-wrap">
            <h2><?php esc_html_e( 'Son 30 Gun Arama Hacmi', 'salthareket' ); ?></h2>
            <canvas id="sh-chart" height="80"></canvas>
        </div>

        <!-- Filtreler -->
        <div class="sh-filters">
            <input type="text" id="sh-search-input" placeholder="<?php esc_attr_e( 'Terim ara...', 'salthareket' ); ?>" />
            <select id="sh-type-filter">
                <option value=""><?php esc_html_e( 'Tum tipler', 'salthareket' ); ?></option>
                <?php
                $types = array_unique( array_column( $all_rows, 'type' ) );
                foreach ( $types as $t ) {
                    echo '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
                }
                ?>
            </select>
            <select id="sh-noresults-filter">
                <option value=""><?php esc_html_e( 'Tum sonuclar', 'salthareket' ); ?></option>
                <option value="0"><?php esc_html_e( 'Sonuclu', 'salthareket' ); ?></option>
                <option value="1"><?php esc_html_e( 'Sonucsuz', 'salthareket' ); ?></option>
            </select>
            <span id="sh-row-count" style="font-size:12px;color:#6b7280;margin-left:auto;"></span>
        </div>

        <!-- Ana Tablo -->
        <div class="sh-table-wrap">
            <table class="sh-table" id="sh-main-table">
                <thead>
                    <tr>
                        <th data-col="name"><?php esc_html_e( 'Terim', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th data-col="type"><?php esc_html_e( 'Tip', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th data-col="rank" class="sorted-desc"><?php esc_html_e( 'Rank', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th data-col="no_results"><?php esc_html_e( 'No Results', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th data-col="date"><?php esc_html_e( 'Ilk Gorulme', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th data-col="date_modified"><?php esc_html_e( 'Son Arama', 'salthareket' ); ?><span class="sort-icon"></span></th>
                        <th><?php esc_html_e( 'Islem', 'salthareket' ); ?></th>
                    </tr>
                </thead>
                <tbody id="sh-tbody">
                <?php foreach ( $all_rows as $row ) :
                    $is_trending = in_array( $row->name, $trending_names, true ) && (int) $row->rank > 5;
                    $no_res_int  = (int) $row->no_results;
                ?>
                    <tr data-name="<?php echo esc_attr( $row->name ); ?>"
                        data-type="<?php echo esc_attr( $row->type ); ?>"
                        data-rank="<?php echo esc_attr( $row->rank ); ?>"
                        data-no-results="<?php echo esc_attr( $no_res_int ); ?>"
                        data-date="<?php echo esc_attr( $row->date ); ?>"
                        data-date-modified="<?php echo esc_attr( $row->date_modified ); ?>">
                        <td class="sh-term">
                            <?php echo esc_html( urldecode( $row->name ) ); ?>
                            <?php if ( $is_trending ) : ?>
                                <span class="sh-trending-badge" title="<?php esc_attr_e( 'Son 7 gunde trend', 'salthareket' ); ?>">&#128293;</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="sh-type"><?php echo esc_html( $row->type ); ?></span></td>
                        <td class="sh-rank"><?php echo esc_html( $row->rank ); ?></td>
                        <td>
                            <?php if ( $no_res_int ) : ?>
                                <span class="sh-no-results-badge"><?php esc_html_e( 'Sonucsuz', 'salthareket' ); ?></span>
                            <?php else : ?>
                                <span style="color:#15803d">&#10003;</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->date ) ) ); ?></td>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->date_modified ) ) ); ?></td>
                        <td>
                            <button class="sh-delete-btn"
                                    data-id="<?php echo esc_attr( $row->id ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                    data-ajax="<?php echo esc_attr( $ajax_url ); ?>"
                                    title="<?php esc_attr_e( 'Sil', 'salthareket' ); ?>">
                                &#128465;
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="sh-pagination" id="sh-pagination"></div>
        </div>

        <!-- Blacklist Yonetimi -->
        <div class="sh-blacklist-wrap">
            <p class="sh-section-title"><?php esc_html_e( 'Kara Liste (Blacklist)', 'salthareket' ); ?></p>
            <ul class="sh-blacklist-list" id="sh-blacklist-list">
                <?php foreach ( $blacklist as $item ) : ?>
                <li data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <span><?php echo esc_html( $item['term'] ); ?></span>
                    <button class="sh-blacklist-remove"
                            data-id="<?php echo esc_attr( $item['id'] ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'sh_blacklist_nonce' ) ); ?>"
                            title="<?php esc_attr_e( 'Kaldir', 'salthareket' ); ?>">&#215;</button>
                </li>
                <?php endforeach; ?>
                <?php if ( empty( $blacklist ) ) : ?>
                <li style="color:#9ca3af;background:none;padding:0"><?php esc_html_e( 'Kara liste bos.', 'salthareket' ); ?></li>
                <?php endif; ?>
            </ul>
            <div class="sh-add-blacklist">
                <input type="text" id="sh-blacklist-input" placeholder="<?php esc_attr_e( 'Yeni terim ekle...', 'salthareket' ); ?>" />
                <button class="sh-btn sh-btn-secondary" id="sh-blacklist-add-btn"
                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'sh_blacklist_nonce' ) ); ?>"
                        data-ajax="<?php echo esc_attr( $ajax_url ); ?>">
                    <?php esc_html_e( 'Ekle', 'salthareket' ); ?>
                </button>
            </div>
            <p style="font-size:12px;color:#9ca3af;margin-top:8px">
                <?php esc_html_e( 'Kara listedeki terimler kaydedilmez. Degisiklikler aninda etki eder.', 'salthareket' ); ?>
            </p>
        </div>

        <!-- Toast bildirimi -->
        <div id="sh-toast"></div>

        </div><!-- .sh-wrap -->
        <?php
        $this->render_admin_js( $nonce, $ajax_url, $chart_labels, $chart_counts );
    }


    // =========================================================================
    // ADMIN — JavaScript (tablo, chart, AJAX)
    // =========================================================================

    /**
     * Admin sayfasi icin inline JavaScript.
     * Tablo siralama, filtreleme, sayfalama, AJAX sil/sil-hepsi ve Chart.js grafigi.
     *
     * @param string $nonce       WP nonce
     * @param string $ajax_url    admin-ajax.php URL
     * @param string $chart_labels JSON encoded tarih dizisi
     * @param string $chart_counts JSON encoded sayi dizisi
     */
    private function render_admin_js(
        string $nonce,
        string $ajax_url,
        string $chart_labels,
        string $chart_counts
    ): void {
        ?>
        <script>
        (function() {
            'use strict';

            // ── Toast ──────────────────────────────────────────────────────────
            function toast(msg, isError) {
                var el = document.getElementById('sh-toast');
                el.textContent = msg;
                el.style.background = isError ? '#dc2626' : '#1f2937';
                el.classList.add('show');
                setTimeout(function() { el.classList.remove('show'); }, 3000);
            }

            // ── AJAX helper ───────────────────────────────────────────────────
            function doAjax(action, data, cb) {
                data.action = action;
                var fd = new FormData();
                Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
                fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) { cb(res); })
                    .catch(function() { toast('Baglanti hatasi.', true); });
            }

            // ── Chart.js ──────────────────────────────────────────────────────
            var chartLabels = <?php echo $chart_labels; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
            var chartCounts = <?php echo $chart_counts; // phpcs:ignore WordPress.Security.EscapeOutput ?>;

            if (typeof Chart !== 'undefined') {
                var ctx = document.getElementById('sh-chart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Arama Sayisi',
                                data: chartCounts,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37,99,235,.08)',
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, ticks: { precision: 0 } }
                            }
                        }
                    });
                }
            }

            // ── Tablo verisi ──────────────────────────────────────────────────
            var tbody    = document.getElementById('sh-tbody');
            var allRows  = Array.from(tbody ? tbody.querySelectorAll('tr') : []);
            var filtered = allRows.slice();
            var sortCol  = 'rank';
            var sortDir  = 'desc';
            var PAGE_SIZE = 50;
            var currentPage = 1;

            // ── Siralama ──────────────────────────────────────────────────────
            document.querySelectorAll('#sh-main-table th[data-col]').forEach(function(th) {
                th.addEventListener('click', function() {
                    var col = this.getAttribute('data-col');
                    if (sortCol === col) {
                        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortCol = col;
                        sortDir = 'desc';
                    }
                    document.querySelectorAll('#sh-main-table th').forEach(function(t) {
                        t.classList.remove('sorted-asc', 'sorted-desc');
                    });
                    this.classList.add('sorted-' + sortDir);
                    applyFiltersAndSort();
                });
            });

            function getVal(row, col) {
                return row.getAttribute('data-' + col.replace('_', '-')) || '';
            }

            function sortRows(rows) {
                return rows.slice().sort(function(a, b) {
                    var av = getVal(a, sortCol);
                    var bv = getVal(b, sortCol);
                    var numA = parseFloat(av);
                    var numB = parseFloat(bv);
                    var cmp;
                    if (!isNaN(numA) && !isNaN(numB)) {
                        cmp = numA - numB;
                    } else {
                        cmp = av.localeCompare(bv, undefined, { sensitivity: 'base' });
                    }
                    return sortDir === 'asc' ? cmp : -cmp;
                });
            }

            // ── Filtreleme ────────────────────────────────────────────────────
            var searchInput   = document.getElementById('sh-search-input');
            var typeFilter    = document.getElementById('sh-type-filter');
            var noResFilter   = document.getElementById('sh-noresults-filter');

            function applyFiltersAndSort() {
                var q       = searchInput ? searchInput.value.toLowerCase().trim() : '';
                var typeVal = typeFilter ? typeFilter.value : '';
                var noRes   = noResFilter ? noResFilter.value : '';

                filtered = allRows.filter(function(row) {
                    var name = (row.getAttribute('data-name') || '').toLowerCase();
                    var type = row.getAttribute('data-type') || '';
                    var nr   = row.getAttribute('data-no-results') || '';
                    if (q && name.indexOf(q) === -1) return false;
                    if (typeVal && type !== typeVal) return false;
                    if (noRes !== '' && nr !== noRes) return false;
                    return true;
                });

                filtered = sortRows(filtered);
                currentPage = 1;
                renderPage();
            }

            if (searchInput) searchInput.addEventListener('input', applyFiltersAndSort);
            if (typeFilter)  typeFilter.addEventListener('change', applyFiltersAndSort);
            if (noResFilter) noResFilter.addEventListener('change', applyFiltersAndSort);

            // ── Sayfalama ─────────────────────────────────────────────────────
            function renderPage() {
                var start = (currentPage - 1) * PAGE_SIZE;
                var end   = start + PAGE_SIZE;
                var page  = filtered.slice(start, end);

                allRows.forEach(function(r) { r.classList.add('sh-hidden'); });
                page.forEach(function(r) { r.classList.remove('sh-hidden'); });

                var countEl = document.getElementById('sh-row-count');
                if (countEl) {
                    countEl.textContent = filtered.length + ' kayit';
                }

                renderPagination();
            }

            function renderPagination() {
                var pag   = document.getElementById('sh-pagination');
                if (!pag) return;
                var total = Math.ceil(filtered.length / PAGE_SIZE);
                pag.innerHTML = '';
                if (total <= 1) return;

                for (var i = 1; i <= total; i++) {
                    (function(page) {
                        var btn = document.createElement('button');
                        btn.className = 'sh-page-btn' + (page === currentPage ? ' active' : '');
                        btn.textContent = page;
                        btn.addEventListener('click', function() {
                            currentPage = page;
                            renderPage();
                        });
                        pag.appendChild(btn);
                    })(i);
                }
            }

            // Ilk render
            applyFiltersAndSort();

            // ── Tekil sil ─────────────────────────────────────────────────────
            tbody && tbody.addEventListener('click', function(e) {
                var btn = e.target.closest('.sh-delete-btn');
                if (!btn) return;
                if (!confirm('Bu kaydi silmek istediginizden emin misiniz?')) return;

                var id    = btn.getAttribute('data-id');
                var nonce = btn.getAttribute('data-nonce');

                doAjax('sh_delete_term', { id: id, nonce: nonce }, function(res) {
                    if (res.success) {
                        var row = btn.closest('tr');
                        if (row) {
                            allRows = allRows.filter(function(r) { return r !== row; });
                            row.remove();
                        }
                        applyFiltersAndSort();
                        toast(res.data.message || 'Silindi.');
                    } else {
                        toast((res.data && res.data.message) || 'Hata.', true);
                    }
                });
            });

            // ── Hepsini sil ───────────────────────────────────────────────────
            var deleteAllBtn = document.getElementById('sh-delete-all-btn');
            if (deleteAllBtn) {
                deleteAllBtn.addEventListener('click', function() {
                    if (!confirm('TUM kayitlari silmek istediginizden emin misiniz? Bu islem geri alinamaz!')) return;
                    var nonce = this.getAttribute('data-nonce');
                    doAjax('sh_delete_all', { nonce: nonce }, function(res) {
                        if (res.success) {
                            allRows = [];
                            filtered = [];
                            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Kayit bulunamadi.</td></tr>';
                            renderPage();
                            toast(res.data.message || 'Tum kayitlar silindi.');
                        } else {
                            toast((res.data && res.data.message) || 'Hata.', true);
                        }
                    });
                });
            }

            // ── Blacklist: kaldir ─────────────────────────────────────────────
            var blList = document.getElementById('sh-blacklist-list');
            if (blList) {
                blList.addEventListener('click', function(e) {
                    var btn = e.target.closest('.sh-blacklist-remove');
                    if (!btn) return;
                    var id    = btn.getAttribute('data-id');
                    var nonce = btn.getAttribute('data-nonce');
                    doAjax('sh_blacklist_remove', { id: id, nonce: nonce }, function(res) {
                        if (res.success) {
                            var li = btn.closest('li');
                            if (li) li.remove();
                            toast('Kaldirildi.');
                        } else {
                            toast((res.data && res.data.message) || 'Hata.', true);
                        }
                    });
                });
            }

            // ── Blacklist: ekle ───────────────────────────────────────────────
            var blAddBtn = document.getElementById('sh-blacklist-add-btn');
            if (blAddBtn) {
                blAddBtn.addEventListener('click', function() {
                    var input = document.getElementById('sh-blacklist-input');
                    var term  = input ? input.value.trim() : '';
                    if (!term) return;
                    var nonce = this.getAttribute('data-nonce');
                    doAjax('sh_blacklist_add', { term: term, nonce: nonce }, function(res) {
                        if (res.success) {
                            if (input) input.value = '';
                            var li = document.createElement('li');
                            li.setAttribute('data-id', res.data.id || '');
                            li.innerHTML = '<span>' + term + '</span>'
                                + '<button class="sh-blacklist-remove" data-id="' + (res.data.id || '') + '" data-nonce="' + nonce + '" title="Kaldir">&#215;</button>';
                            var emptyLi = blList ? blList.querySelector('li[style]') : null;
                            if (emptyLi) emptyLi.remove();
                            if (blList) blList.appendChild(li);
                            toast('Eklendi.');
                        } else {
                            toast((res.data && res.data.message) || 'Hata.', true);
                        }
                    });
                });
            }

        })();
        </script>
        <?php
    }


    // =========================================================================
    // ADMIN — Blacklist AJAX handler'lari
    // =========================================================================

    /**
     * AJAX: Blacklist'e terim ekle.
     * render_admin_js() icindeki JS tarafindan cagrilir.
     * Constructor'da kayitli degildir — wp_ajax_ hook'u burada eklenir.
     */
    public function ajax_blacklist_add(): void {
        check_ajax_referer( 'sh_blacklist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        if ( '' === $term ) {
            wp_send_json_error( [ 'message' => __( 'Terim bos olamaz.', 'salthareket' ) ], 400 );
        }

        $result = $this->add_to_blacklist( $term );
        if ( $result ) {
            // Yeni eklenen kaydin id'sini bul
            $list = $this->get_blacklist();
            $id   = 0;
            foreach ( array_reverse( $list ) as $item ) {
                if ( isset( $item['term'] ) && $item['term'] === mb_strtolower( $term ) ) {
                    $id = (int) $item['id'];
                    break;
                }
            }
            wp_send_json_success( [ 'message' => __( 'Eklendi.', 'salthareket' ), 'id' => $id ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Zaten mevcut veya eklenemedi.', 'salthareket' ) ], 400 );
        }
    }

    /**
     * AJAX: Blacklist'ten terim kaldir.
     */
    public function ajax_blacklist_remove(): void {
        check_ajax_referer( 'sh_blacklist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $id < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Gecersiz ID.', 'salthareket' ) ], 400 );
        }

        if ( $this->remove_from_blacklist( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Kaldirildi.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Kaldirilamadi.', 'salthareket' ) ], 500 );
        }
    }

} // end class SearchHistory

// ── Blacklist AJAX hook'larini sinif disinda kaydet ───────────────────────────
// Constructor'da $this referansi olmadan static olarak kaydedemeyiz,
// bu yuzden sinif yuklendikten sonra hook'lari ekliyoruz.
add_action( 'wp_ajax_sh_blacklist_add', function () {
    if ( class_exists( 'SearchHistory' ) ) {
        ( new SearchHistory() )->ajax_blacklist_add();
    }
} );

add_action( 'wp_ajax_sh_blacklist_remove', function () {
    if ( class_exists( 'SearchHistory' ) ) {
        ( new SearchHistory() )->ajax_blacklist_remove();
    }
} );
