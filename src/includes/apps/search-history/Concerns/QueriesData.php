<?php

/**
 * QueriesData — SearchHistory Veri Sorgulama Trait
 *
 * Popüler/trend terimler, istatistikler, chart verisi, Levenshtein önerileri
 * ve tüm kayıtların okunması/silinmesi.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.0.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use QueriesData;` ile kullanılır.
 *
 * @example Popüler terimler:
 *   $popular = $sh->get_popular_terms('product', 10);
 *
 * @example Trend terimler (son 7 gün):
 *   $trending = $sh->get_trending_terms(7, 10);
 *
 * @example Sıfır sonuçlu terimler:
 *   $no_res = $sh->get_no_results_terms(10);
 *
 * @example İstatistikler:
 *   $stats = $sh->get_stats();
 *   // ['total_searches'=>1500, 'unique_terms'=>320, 'no_results_count'=>45, 'top_type'=>'product']
 *
 * @example Chart.js verisi:
 *   $chart = $sh->get_chart_data(30);
 *   // [['date'=>'2026-05-01','count'=>42], ...]
 *
 * @example "Bunu mu demek istediniz?":
 *   $suggestion = $sh->did_you_mean('iphne', 2);
 *   // 'iphone'
 */
trait QueriesData {

    // =========================================================================
    // CLICKS ANALİTİK
    // =========================================================================

    /**
     * En çok tıklanan autocomplete sonuçları.
     * Aynı URL için tıklama sayısını toplar.
     *
     * @param  int    $count Kaç adet
     * @param  int    $days  Son kaç günlük (0 = tüm zamanlar)
     * @return array  [['term'=>string, 'clicked_title'=>string, 'clicked_url'=>string, 'clicked_type'=>string, 'clicks'=>int], ...]
     *
     * @example
     *   $top = $sh->get_top_clicked(10);
     *   foreach ($top as $row) {
     *       echo $row['clicked_title'] . ' — ' . $row['clicks'] . ' tıklama';
     *   }
     */
    public function get_top_clicked( int $count = 10, int $days = 0 ): array {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        $date_filter = '';
        if ( $days > 0 ) {
            $date_filter = $wpdb->prepare( ' AND date >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT clicked_url, clicked_title, clicked_type,
                    COUNT(*) AS clicks,
                    GROUP_CONCAT(DISTINCT term ORDER BY term SEPARATOR ', ') AS terms
             FROM `{$clicks_table}`
             WHERE 1=1 {$date_filter}
             GROUP BY clicked_url
             ORDER BY clicks DESC
             LIMIT %d",
            $count
        ), ARRAY_A );
    }

    /**
     * Belirli bir arama terimi için en çok tıklanan sonuçlar.
     *
     * @param  string $term  Arama terimi
     * @param  int    $count Kaç adet
     * @return array  [['clicked_title'=>string, 'clicked_url'=>string, 'clicks'=>int], ...]
     *
     * @example
     *   $results = $sh->get_clicks_for_term('mascara', 5);
     */
    public function get_clicks_for_term( string $term, int $count = 5 ): array {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT clicked_url, clicked_title, clicked_type, COUNT(*) AS clicks
             FROM `{$clicks_table}`
             WHERE term = %s
             GROUP BY clicked_url
             ORDER BY clicks DESC
             LIMIT %d",
            mb_strtolower( trim( $term ) ),
            $count
        ), ARRAY_A );
    }

    /**
     * Toplam tıklama sayısı.
     *
     * @param  int $days Son kaç günlük (0 = tüm zamanlar)
     * @return int
     *
     * @example
     *   $total = $sh->get_total_clicks();
     *   $week  = $sh->get_total_clicks(7);
     */
    public function get_total_clicks( int $days = 0 ): int {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        if ( $days > 0 ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$clicks_table}` WHERE date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clicks_table}`" );
    }

    /**
     * Son X günlük günlük tıklama sayısı (Chart.js için).
     * Data olmayan günler 0 olarak döner.
     *
     * @param  int $days
     * @return array  [['date'=>'YYYY-MM-DD', 'count'=>int], ...]
     *
     * @example
     *   $chart = $sh->get_clicks_chart_data(30);
     */
    public function get_clicks_chart_data( int $days = 30 ): array {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(date) AS `date`, COUNT(*) AS `count`
             FROM `{$clicks_table}`
             WHERE date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(date)
             ORDER BY `date` ASC",
            $days
        ), ARRAY_A );

        $db_map = [];
        foreach ( $rows as $row ) {
            $db_map[ $row['date'] ] = (int) $row['count'];
        }

        $result = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $result[] = [ 'date' => $date, 'count' => $db_map[ $date ] ?? 0 ];
        }

        return $result;
    }

    /**
     * Son X günlük günlük sonuçsuz arama sayısı (Chart.js için).
     * Data olmayan günler 0 olarak döner.
     *
     * @param  int $days
     * @return array  [['date'=>'YYYY-MM-DD', 'count'=>int], ...]
     */
    public function get_no_results_chart_data( int $days = 30 ): array {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(date_modified) AS `date`, COUNT(*) AS `count`
             FROM `{$this->table_name}`
             WHERE date_modified >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND no_results = 1
             GROUP BY DATE(date_modified)
             ORDER BY `date` ASC",
            $days
        ), ARRAY_A );

        $db_map = [];
        foreach ( $rows as $row ) {
            $db_map[ $row['date'] ] = (int) $row['count'];
        }

        $result = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $result[] = [ 'date' => $date, 'count' => $db_map[ $date ] ?? 0 ];
        }

        return $result;
    }

    /**
     * Tüm click kayıtlarını döner (admin tablosu için).
     *
     * @param  string $orderby
     * @param  string $order
     * @param  int    $limit
     * @return object[]
     *
     * @example
     *   $rows = $sh->get_all_clicks('clicks', 'DESC', 200);
     */
    public function get_all_clicks( string $orderby = 'clicks', string $order = 'DESC', int $limit = 500 ): array {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        $allowed = [ 'clicks', 'term', 'clicked_title', 'clicked_type', 'date' ];
        $orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'clicks';
        $order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT clicked_url, clicked_title, clicked_type,
                    COUNT(*) AS clicks,
                    GROUP_CONCAT(DISTINCT term ORDER BY term SEPARATOR ', ') AS terms,
                    MAX(date) AS last_click
             FROM `{$clicks_table}`
             GROUP BY clicked_url
             ORDER BY `{$orderby}` {$order}
             LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    /**
     * Clicks tablosunu temizle.
     *
     * @return bool
     */
    public function delete_all_clicks(): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->query( "TRUNCATE TABLE `{$this->get_clicks_table_name()}`" );
    }

    // =========================================================================
    // KULLANICI GEÇMİŞİ
    // =========================================================================

    /**
     * Kullanıcının son arama terimleri.
     * Login → user_meta, guest → cookie.
     *
     * @param  int    $user_id  0 ise current user
     * @param  string $type     Kullanılmıyor (geriye uyumluluk)
     * @param  int    $count    Kaç adet
     * @return string[]
     *
     * @example
     *   $terms = $sh->get_user_terms(0, 'search', 5);
     */
    public function get_user_terms( int $user_id = 0, string $type = 'search', int $count = 5 ): array {
        if ( ! is_user_logged_in() && $user_id < 1 ) {
            return $this->get_cookie_terms( $count );
        }

        if ( $user_id < 1 ) $user_id = (int) get_current_user_id();
        if ( $user_id < 1 ) return [];

        $terms = get_user_meta( $user_id, 'search_terms', true );
        if ( ! is_array( $terms ) || empty( $terms ) ) return [];

        return array_slice( array_values( $terms ), -$count );
    }

    /**
     * En çok aranan tek terim (rank en yüksek, no_results=0).
     *
     * @return array|null  ['name'=>string, 'rank'=>int] veya null
     */
    public function get_top_searched(): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT name, `rank` FROM `{$this->table_name}`
             WHERE no_results = 0 ORDER BY `rank` DESC LIMIT 1",
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * En son aranan terim (date_modified en yeni, no_results=0).
     *
     * @return array|null  ['name'=>string, 'date_modified'=>string] veya null
     */
    public function get_last_searched(): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT name, date_modified FROM `{$this->table_name}`
             WHERE no_results = 0 ORDER BY date_modified DESC LIMIT 1",
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Belirli gün aralığı için chart verisi döner.
     * get_chart_data()'nın alias'ı — period selector için.
     *
     * @param  int $days
     * @return array
     */
    public function get_chart_data_for_period( int $days ): array {
        return $this->get_chart_data( $days );
    }

    // =========================================================================
    // POPÜLER / TREND
    // =========================================================================

    /**
     * En popüler arama terimleri (rank'e göre).
     * QueryCache varsa cache'lenir.
     * ML siteler için lang parametresi ile dil bazlı filtreleme yapılabilir.
     *
     * @param  string $type  Post type veya 'search'
     * @param  int    $count Kaç adet
     * @param  string $lang  Dil kodu — boş = tüm diller
     * @return string[]
     *
     * @example
     *   $popular = $sh->get_popular_terms('product', 10);       // tüm diller
     *   $popular = $sh->get_popular_terms('product', 10, 'tr'); // sadece Türkçe
     */
    public function get_popular_terms( string $type = 'search', int $count = 5, string $lang = '' ): array {
        $fetcher = function () use ( $type, $count, $lang ) {
            global $wpdb;
            if ( '' !== $lang ) {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT name FROM `{$this->table_name}`
                     WHERE type = %s AND no_results = 0 AND lang = %s
                     ORDER BY `rank` DESC LIMIT %d",
                    $type, $lang, $count
                ), ARRAY_A );
            } else {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT name FROM `{$this->table_name}`
                     WHERE type = %s AND no_results = 0
                     ORDER BY `rank` DESC LIMIT %d",
                    $type, $count
                ), ARRAY_A );
            }
            return wp_list_pluck( $results, 'name' );
        };

        $cache_key = "popular_search_{$type}_{$count}_{$lang}";
        if ( class_exists( 'QueryCache' ) ) {
            return \QueryCache::wrap( $cache_key, $fetcher );
        }
        return $fetcher();
    }

    /**
     * Son X günde en çok aranan terimler (trend).
     *
     * @param  int $days  Kaç günlük pencere
     * @param  int $count Kaç adet
     * @return array      [['name'=>string, 'type'=>string, 'rank'=>int], ...]
     *
     * @example
     *   $trending = $sh->get_trending_terms(7, 10);
     */
    public function get_trending_terms( int $days = 7, int $count = 10 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT name, type, `rank`
             FROM `{$this->table_name}`
             WHERE date_modified >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND no_results = 0
             ORDER BY `rank` DESC LIMIT %d",
            $days, $count
        ), ARRAY_A );
    }

    /**
     * Sıfır sonuçlu en çok aranan terimler.
     *
     * @param  int $count Kaç adet
     * @return array      [['name'=>string, 'rank'=>int], ...]
     *
     * @example
     *   $no_res = $sh->get_no_results_terms(10);
     */
    public function get_no_results_terms( int $count = 10 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT name, `rank` FROM `{$this->table_name}`
             WHERE no_results = 1 ORDER BY `rank` DESC LIMIT %d",
            $count
        ), ARRAY_A );
    }

    // =========================================================================
    // İSTATİSTİKLER
    // =========================================================================

    /**
     * Dashboard istatistikleri.
     *
     * @return array {total_searches:int, unique_terms:int, no_results_count:int, top_type:string}
     *
     * @example
     *   $stats = $sh->get_stats();
     *   echo $stats['total_searches'];
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
             GROUP BY type ORDER BY SUM(`rank`) DESC LIMIT 1"
        );

        return [
            'total_searches'   => $total,
            'unique_terms'     => $unique,
            'no_results_count' => $no_results_count,
            'top_type'         => $top_type,
        ];
    }

    /**
     * Son X günlük günlük arama sayısı (Chart.js için).
     * Data olmayan günler 0 olarak döner — grafik her zaman tam aralığı gösterir.
     *
     * @param  int $days Kaç günlük pencere
     * @return array     [['date'=>'YYYY-MM-DD', 'count'=>int], ...]
     *
     * @example
     *   $chart = $sh->get_chart_data(30);
     */
    public function get_chart_data( int $days = 30 ): array {
        global $wpdb;

        // DB'den mevcut verileri al
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(date_modified) AS `date`, COUNT(*) AS `count`
             FROM `{$this->table_name}`
             WHERE date_modified >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND no_results = 0
             GROUP BY DATE(date_modified)
             ORDER BY `date` ASC",
            $days
        ), ARRAY_A );

        // DB verisini date → count map'e çevir
        $db_map = [];
        foreach ( $rows as $row ) {
            $db_map[ $row['date'] ] = (int) $row['count'];
        }

        // Seçilen aralıktaki TÜM günleri üret — data olmayan günler 0
        $result = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date            = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $result[] = [
                'date'  => $date,
                'count' => $db_map[ $date ] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Type bazlı gruplama.
     *
     * @param  int $count Kaç adet
     * @return array      [['type'=>string, 'total'=>int], ...]
     *
     * @example
     *   $types = $sh->get_top_types(5);
     */
    public function get_top_types( int $count = 5 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT type, SUM(`rank`) AS total
             FROM `{$this->table_name}`
             WHERE no_results = 0
             GROUP BY type ORDER BY total DESC LIMIT %d",
            $count
        ), ARRAY_A );
    }

    // =========================================================================
    // TÜM KAYITLAR / SİLME
    // =========================================================================

    /**
     * Admin için tüm kayıtları döner.
     * wp_search_clicks ile LEFT JOIN — her terim için son tıklanan URL de gelir.
     *
     * @param  string $orderby
     * @param  string $order
     * @return object[]
     *
     * @example
     *   $rows = $sh->get_all('rank', 'DESC');
     *   // $row->last_clicked_url, $row->last_clicked_title, $row->click_count
     */
    public function get_all( string $orderby = 'rank', string $order = 'DESC' ): array {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        $allowed = [ 'rank', 'name', 'type', 'date', 'date_modified', 'no_results', 'click_count' ];
        $orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'rank';
        $order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // clicks tablosu var mı kontrol et
        $clicks_exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $clicks_table )
        );

        if ( $clicks_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return $wpdb->get_results(
                "SELECT t.*,
                        COALESCE(c.click_count, 0) AS click_count,
                        c.last_clicked_url         AS last_clicked_url,
                        c.last_clicked_title       AS last_clicked_title
                 FROM `{$this->table_name}` t
                 LEFT JOIN (
                     SELECT term,
                            clicked_type,
                            COUNT(*) AS click_count,
                            SUBSTRING_INDEX(GROUP_CONCAT(clicked_url ORDER BY date DESC SEPARATOR '|||'), '|||', 1) AS last_clicked_url,
                            SUBSTRING_INDEX(GROUP_CONCAT(clicked_title ORDER BY date DESC SEPARATOR '|||'), '|||', 1) AS last_clicked_title
                     FROM `{$clicks_table}`
                     GROUP BY term, clicked_type
                 ) c ON c.term = t.name
                     AND (
                         c.clicked_type = t.type
                         OR ( t.type IN ('search','fibosearch') AND c.clicked_type IN ('search','fibosearch') )
                     )
                 ORDER BY `{$orderby}` {$order}"
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT *, 0 AS click_count, '' AS last_clicked_url, '' AS last_clicked_title
             FROM `{$this->table_name}` ORDER BY `{$orderby}` {$order}"
        );
    }

    /**
     * Tekil kayıt sil.
     *
     * @param  int $id
     * @return bool
     *
     * @example
     *   $sh->delete_term(42);
     */
    public function delete_term( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Tüm kayıtları sil (TRUNCATE).
     *
     * @return bool
     *
     * @example
     *   $sh->delete_all();
     */
    public function delete_all(): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->query( "TRUNCATE TABLE `{$this->table_name}`" );
    }

    /**
     * Eski ve düşük rank'li kayıtları sil.
     *
     * @param  int $days     Kaç günden eski
     * @param  int $min_rank Bu rank'ın altındakiler silinsin
     *
     * @example
     *   $sh->delete_old_terms(90, 2);
     */
    public function delete_old_terms( int $days = 90, int $min_rank = 2 ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$this->table_name}`
             WHERE date_modified < DATE_SUB(NOW(), INTERVAL %d DAY)
               AND `rank` < %d",
            $days, $min_rank
        ) );
    }

    // =========================================================================
    // CRON
    // =========================================================================

    /**
     * WP Cron'a günlük temizlik görevi kaydet.
     *
     * @example
     *   // Otomatik — constructor'da add_action('wp', ...) ile kayıtlı
     */
    public static function schedule_cleanup(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Cron callback.
     *
     * @example
     *   // Otomatik — sh_daily_cleanup hook'u ile tetiklenir
     */
    public static function run_cleanup(): void {
        ( new self() )->delete_old_terms( 90, 2 );
    }

    // =========================================================================
    // LEVENSHTEIN
    // =========================================================================

    /**
     * "Bunu mu demek istediniz?" önerisi.
     *
     * @param  string   $input
     * @param  int      $max_distance
     * @param  int      $limit
     * @return string|null
     *
     * @example
     *   $sh->did_you_mean('iphne', 2); // 'iphone'
     */
    public function did_you_mean( string $input, int $max_distance = 2, int $limit = 200 ): ?string {
        $results = $this->levenshtein_search( $input, $max_distance, $limit, 1 );
        return $results[0] ?? null;
    }

    /**
     * Levenshtein tabanlı arama önerileri.
     *
     * @param  string $input
     * @param  int    $count
     * @param  int    $max_distance
     * @param  int    $limit
     * @return string[]
     *
     * @example
     *   $sh->suggestions('iphne', 5, 3, 200);
     */
    public function suggestions( string $input, int $count = 5, int $max_distance = 6, int $limit = 200 ): array {
        return $this->levenshtein_search( $input, $max_distance, $limit, $count );
    }

    /**
     * Ortak Levenshtein motoru.
     */
    private function levenshtein_search( string $input, int $max_distance, int $limit, int $count ): array {
        global $wpdb;

        $input = trim( mb_strtolower( $input ) );
        if ( '' === $input ) return [];

        $terms = $wpdb->get_col( $wpdb->prepare(
            "SELECT name FROM `{$this->table_name}`
             WHERE no_results = 0 ORDER BY `rank` DESC LIMIT %d",
            $limit
        ) );

        if ( empty( $terms ) ) return [];

        $matches = [];
        foreach ( $terms as $term ) {
            if ( $term === $input ) continue;
            $d = levenshtein( $input, $term );
            if ( $d <= $max_distance ) $matches[ $term ] = $d;
        }

        asort( $matches );
        return array_slice( array_keys( $matches ), 0, $count );
    }
}
