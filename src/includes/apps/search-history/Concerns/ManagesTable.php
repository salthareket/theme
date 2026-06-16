<?php

/**
 * ManagesTable — SearchHistory DB Tablo Yönetimi Trait
 *
 * wp_search_terms ve wp_search_clicks tablolarının oluşturulması, migration.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.1.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.1.0 - 2026-05-04
 *   - Add: wp_search_clicks tablosu — autocomplete tıklama hedefi takibi
 *          term, clicked_url, clicked_title, clicked_type, date
 *   - Add: create_clicks_table(), maybe_create_clicks_table()
 *
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *   - Add: maybe_migrate_table() — no_results kolonu + eski no_result_count kaldırma
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use ManagesTable;` ile kullanılır.
 *   Constructor'da maybe_create_table() otomatik çağrılır.
 *
 * @example Tablo adını al:
 *   $table = $sh->get_table_name();        // 'wp_search_terms'
 *   $table = $sh->get_clicks_table_name(); // 'wp_search_clicks'
 *
 * @example Click kaydet:
 *   $sh->record_click('mascara', 'https://site.com/urun/mascara/', 'Mascara', 'product');
 *
 * @example En çok tıklanan sonuçlar:
 *   $top = $sh->get_top_clicked(10);
 *   // [['term'=>'mascara','clicked_title'=>'Mascara','clicks'=>42,'clicked_url'=>'...'], ...]
 */
trait ManagesTable {

    /**
     * Tablo varlığını transient ile cache'le.
     * Her instantiation'da SHOW TABLES çalışmasın.
     */
    private function maybe_create_table(): void {
        $cache_key = 'sh_search_terms_table_v4'; // v4: lang kolonu + wp_search_clicks
        if ( get_transient( $cache_key ) ) return;

        global $wpdb;
        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name )
        );

        if ( ! $exists ) {
            $this->create_table();
        } else {
            $this->maybe_migrate_table();
        }

        // Clicks tablosunu da oluştur/kontrol et
        $this->maybe_create_clicks_table();

        set_transient( $cache_key, true, 7 * DAY_IN_SECONDS );
    }

    /**
     * Tabloyu oluştur.
     * Schema: id, name, type, rank, no_results (bool), lang, date, date_modified
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
            lang          varchar(10)         NOT NULL DEFAULT '',
            date          datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   name_type_lang (name, type, lang)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Schema migration — no_results kolonu ekle, eski no_result_count kaldır, lang kolonu ekle.
     */
    private function maybe_migrate_table(): void {
        global $wpdb;

        // no_results kolonu
        $has_no_results = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name}` LIKE %s", 'no_results'
        ) );
        if ( empty( $has_no_results ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$this->table_name}` ADD COLUMN `no_results` tinyint(1) NOT NULL DEFAULT 0 AFTER `rank`" );
        }

        // lang kolonu
        $has_lang = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name}` LIKE %s", 'lang'
        ) );
        if ( empty( $has_lang ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$this->table_name}` ADD COLUMN `lang` varchar(10) NOT NULL DEFAULT '' AFTER `no_results`" );
            // UNIQUE KEY güncelle: name_type → name_type_lang
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$this->table_name}` DROP INDEX IF EXISTS `name_type`" );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$this->table_name}` ADD UNIQUE KEY `name_type_lang` (name, type, lang)" );
        }

        // Eski no_result_count kaldır
        $has_old_col = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name}` LIKE %s", 'no_result_count'
        ) );
        if ( ! empty( $has_old_col ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$this->table_name}` DROP COLUMN `no_result_count`" );
        }
    }

    /**
     * Tablo adını döner.
     *
     * @return string  örn. 'wp_search_terms'
     *
     * @example
     *   $table = $sh->get_table_name();
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    // =========================================================================
    // CLICKS TABLOSU
    // =========================================================================

    /**
     * Clicks tablo adını döner.
     *
     * @return string  örn. 'wp_search_clicks'
     *
     * @example
     *   $table = $sh->get_clicks_table_name();
     */
    public function get_clicks_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'search_clicks';
    }

    /**
     * wp_search_clicks tablosunu oluştur (yoksa).
     * Schema: id, term, clicked_url, clicked_title, clicked_type, date
     */
    private function maybe_create_clicks_table(): void {
        global $wpdb;
        $clicks_table = $this->get_clicks_table_name();

        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $clicks_table )
        );

        if ( ! $exists ) {
            $this->create_clicks_table();
        }
    }

    /**
     * wp_search_clicks tablosunu oluştur.
     *
     * Kolonlar:
     *   id            — auto increment PK
     *   term          — arama terimi (kullanıcının yazdığı)
     *   clicked_url   — tıklanan URL
     *   clicked_title — tıklanan sonucun başlığı
     *   clicked_type  — product / post / page / fibosearch
     *   date          — tıklama zamanı
     */
    private function create_clicks_table(): void {
        global $wpdb;
        $charset      = $wpdb->get_charset_collate();
        $clicks_table = $this->get_clicks_table_name();

        $sql = "CREATE TABLE `{$clicks_table}` (
            id            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            term          varchar(255)        NOT NULL,
            clicked_url   varchar(2048)       NOT NULL DEFAULT '',
            clicked_title varchar(500)        NOT NULL DEFAULT '',
            clicked_type  varchar(50)         NOT NULL DEFAULT 'fibosearch',
            date          datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_term (term(100)),
            KEY idx_url  (clicked_url(200)),
            KEY idx_date (date)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Autocomplete tıklamasını clicks tablosuna kaydet.
     *
     * @param  string $term          Arama terimi
     * @param  string $clicked_url   Tıklanan URL
     * @param  string $clicked_title Tıklanan sonucun başlığı
     * @param  string $clicked_type  product / post / page / fibosearch
     * @return bool
     *
     * @example
     *   $sh->record_click('mascara', 'https://site.com/urun/mascara/', 'Mascara', 'product');
     */
    public function record_click( string $term, string $clicked_url, string $clicked_title, string $clicked_type = 'fibosearch' ): bool {
        global $wpdb;

        $term          = mb_strtolower( trim( $term ) );
        $clicked_url   = esc_url_raw( $clicked_url );
        $clicked_title = sanitize_text_field( $clicked_title );
        $clicked_type  = sanitize_key( $clicked_type );

        if ( empty( $term ) || empty( $clicked_url ) ) return false;

        return (bool) $wpdb->insert(
            $this->get_clicks_table_name(),
            [
                'term'          => $term,
                'clicked_url'   => $clicked_url,
                'clicked_title' => $clicked_title,
                'clicked_type'  => $clicked_type,
                'date'          => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }
}
