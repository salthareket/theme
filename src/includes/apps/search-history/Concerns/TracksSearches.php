<?php

/**
 * TracksSearches — SearchHistory Arama Takip Trait
 *
 * WP native search ve FiboSearch aramalarını otomatik yakalar,
 * DB'ye upsert eder, kullanıcı geçmişini günceller.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.0.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *   - Fix: auto_track_search() — $_GET['s'] fallback + no_results tracking
 *   - Fix: set_term() — WP_Query tamamen kaldırıldı (double query bug)
 *   - Add: track_fibosearch_query() — dgwt/wcas/search_query/args filter
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use TracksSearches;` ile kullanılır.
 *   auto_track_search() the_posts filter'ı ile otomatik çalışır.
 *   track_fibosearch_query() dgwt/wcas/search_query/args filter'ı ile çalışır.
 *
 * @example Manuel kayıt (search.php'den):
 *   $sh->set_term('wonder brow', 'product');
 *
 * @example Sonuç bulunamadı kaydı:
 *   $sh->set_term('xyzabc', 'product', true);
 *
 * @example FiboSearch otomatik entegrasyon:
 *   // Constructor'da add_filter('dgwt/wcas/search_query/args', ...) ile otomatik
 *
 * @example WP native search otomatik entegrasyon:
 *   // Constructor'da add_filter('the_posts', ...) ile otomatik
 */
trait TracksSearches {

    // =========================================================================
    // AUTO TRACK
    // =========================================================================

    /**
     * WP ana arama query'sini otomatik yakalar.
     * Sonuç varsa kaydeder, yoksa no_results=1 ile kaydeder.
     * Admin, AJAX ve cron'da çalışmaz.
     *
     * @param  array      $posts
     * @param  \WP_Query  $query
     * @return array
     *
     * @example
     *   // Otomatik — constructor'da add_filter('the_posts', ...) ile kayıtlı
     */
    public function auto_track_search( array $posts, \WP_Query $query ): array {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return $posts;
        if ( ! $query->is_main_query() || ! $query->is_search() ) return $posts;

        $term = (string) get_query_var( 's', '' );
        if ( '' === trim( $term ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        }
        $term = trim( mb_strtolower( $term ) );

        if ( '' === $term || ! $this->is_valid_term( $term ) ) return $posts;

        $post_type = $query->get( 'post_type', 'search' );
        if ( is_array( $post_type ) ) {
            $post_type = ( count( $post_type ) === 1 ) ? reset( $post_type ) : 'search';
        }
        // search sayfasından gelen aramalar her zaman 'search' type — post_type filtresi
        // sadece FiboSearch "See All" için kullanılıyor, kullanıcı intent'i her zaman 'search'
        $type = 'search';

        // shutdown'da kaydet — o noktada found_posts kesinlikle set edilmiş
        // $query referansını closure'a geçir
        add_action( 'shutdown', function() use ( $term, $type, $query ) {
            // found_posts veya post_count — hangisi dolu ise onu kullan
            $found = (int) $query->found_posts;
            if ( $found === 0 ) {
                $found = (int) $query->post_count;
            }
            // posts array'i de kontrol et
            $posts_arr = is_array( $query->posts ) ? $query->posts : [];
            $has_results = $found > 0 || ! empty( $posts_arr );
            $no_results  = ! $has_results;

            $this->upsert_term( $term, $type, $no_results );

            if ( ! $no_results ) {
                if ( is_user_logged_in() ) {
                    $this->add_to_user_meta( $term );
                } else {
                    $this->add_to_cookie( $term );
                }
            }
        }, 5 );

        return $posts;
    }

    /**
     * FiboSearch entegrasyonu — dgwt/wcas/search_query/args filter.
     * Terimi 'fibosearch' type ile kaydeder.
     * Gerçek no_results takibi auto_track_search ile yapılır.
     *
     * @param  array $args
     * @return array  Değiştirilmemiş args
     *
     * @example
     *   // Otomatik — constructor'da add_filter('dgwt/wcas/search_query/args', ...) ile kayıtlı
     */
    public function track_fibosearch_query( array $args ): array {
        // Sadece FiboSearch AJAX request'lerinde çalış — search sayfasında değil
        // FiboSearch AJAX: action=dgwt_wcas_search veya dgwt_wcas parametresi var
        $is_fibosearch_ajax = (
            ( defined( 'DOING_AJAX' ) && DOING_AJAX ) &&
            isset( $_GET['action'] ) &&
            strpos( sanitize_key( $_GET['action'] ), 'dgwt_wcas' ) === 0
        );

        if ( ! $is_fibosearch_ajax ) return $args;

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
    // SET / UPSERT
    // =========================================================================

    /**
     * Arama terimini DB'ye kaydeder.
     * WP_Query çalıştırmaz — sadece upsert + user meta/cookie.
     *
     * @param  string $term
     * @param  string $type       Post type veya 'search'
     * @param  bool   $no_results Sonuç bulunamadıysa true
     *
     * @example
     *   $sh->set_term('mascara', 'product');
     *   $sh->set_term('xyzabc', 'product', true); // no results
     */
    public function set_term( string $term, string $type = 'search', bool $no_results = false ): void {
        $term = trim( mb_strtolower( $term ) );
        if ( '' === $term || ! $this->is_valid_term( $term ) ) return;

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
     * DB'ye upsert — varsa rank artır, yoksa ekle.
     * lang kolonu ile dil bazlı ayrım yapılır (ML siteler için).
     *
     * @param  string $term
     * @param  string $type
     * @param  bool   $no_results
     */
    private function upsert_term( string $term, string $type, bool $no_results = false ): void {
        global $wpdb;

        $lang = $this->detect_current_lang();
        $now  = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO `{$this->table_name}` (name, type, `rank`, no_results, lang, date, date_modified)
             VALUES (%s, %s, 1, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                `rank`        = `rank` + 1,
                no_results    = VALUES(no_results),
                date_modified = VALUES(date_modified)",
            $term,
            $type,
            $no_results ? 1 : 0,
            $lang,
            $now,
            $now
        ) );
    }

    /**
     * Mevcut dil kodunu tespit eder.
     * Polylang → pll_current_language()
     * WPML     → ICL_LANGUAGE_CODE
     * qTranslate-XT → qtranxf_getLanguage()
     * Hiçbiri yoksa → WP site locale'inden 2 harfli dil kodu (örn. 'tr', 'en')
     *
     * @return string  örn. 'tr', 'en', 'ar'
     */
    private function detect_current_lang(): string {
        // Polylang
        if ( function_exists( 'pll_current_language' ) ) {
            $lang = (string) ( pll_current_language( 'slug' ) ?: '' );
            if ( '' !== $lang ) return $lang;
        }
        // WPML
        if ( defined( 'ICL_LANGUAGE_CODE' ) && '' !== ICL_LANGUAGE_CODE ) {
            return (string) ICL_LANGUAGE_CODE;
        }
        // qTranslate-XT
        if ( function_exists( 'qtranxf_getLanguage' ) ) {
            $lang = (string) qtranxf_getLanguage();
            if ( '' !== $lang ) return $lang;
        }
        // Fallback: WP site locale → ilk 2 karakter (örn. 'tr_TR' → 'tr')
        $locale = get_locale();
        return $locale ? strtolower( substr( $locale, 0, 2 ) ) : '';
    }
}
