<?php

/**
 * SearchHistory — Ana Facade Sınıfı
 *
 * Arama geçmişi, popüler/trend terimler, istatistik, admin yönetimi.
 * Trait tabanlı modüler mimari — her sorumluluk ayrı dosyada.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    3.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * CHANGELOG:
 * 3.0.0 - 2026-05-04
 *   - Refactor: Tek dosya → trait tabanlı modüler yapı
 *       - Concerns/ManagesTable.php    — DB tablo oluşturma, migration
 *       - Concerns/ManagesBlacklist.php — blacklist CRUD
 *       - Concerns/TracksSearches.php  — auto_track, set_term, upsert, fibosearch
 *       - Concerns/QueriesData.php     — get_popular, trending, stats, chart, levenshtein
 *       - Concerns/ManagesStorage.php  — user_meta, cookie
 *       - Admin/SearchHistoryAdmin.php — admin sayfa, enqueue, render
 *       - Admin/SearchHistoryAjax.php  — AJAX handler'lar, JS
 *   - Fix: set_term() double WP_Query bug kaldırıldı
 *   - Fix: auto_track_search() $_GET['s'] fallback + no_results tracking
 *   - Fix: GET ile silme → AJAX + nonce
 *   - Add: ACF bağımlılığı tamamen kaldırıldı
 *   - Add: no_results (bool) DB kolonu
 *   - Add: get_no_results_terms(), get_trending_terms(), get_stats(), get_chart_data()
 *   - Add: delete_all(), delete_old_terms(), schedule_cleanup(), run_cleanup()
 *   - Add: Blacklist (wp_options), MIN_TERM_LENGTH
 *   - Add: FiboSearch entegrasyonu (dgwt/wcas/search_query/args)
 *   - Add: Chart.js 30 günlük grafik, trend 🔥 badge, CSV export
 *   - Add: static $hooks_registered — çoklu instantiation güvenli
 *
 * 2.0.0 - 2026-05-03
 *   - Refactor: ACF bağımlılığı kaldırıldı
 *   - Add: no_results tracking, blacklist, FiboSearch
 *
 * 1.0.0 - 2026-04-03
 *   - Add: Initial release
 *
 * HOW TO USE:
 *   Sınıf variables.php'de ENABLE_SEARCH_HISTORY true ise otomatik yüklenir.
 *   Constructor hook'ları otomatik bağlar — manuel instantiate gerekmez.
 *
 *   Admin URL: /wp-admin/admin.php?page=search-history
 *
 * @example Singleton al:
 *   $sh = new SearchHistory();
 *
 * @example Manuel kayıt (search.php'den):
 *   $sh->set_term(get_query_var('s'), 'product');
 *
 * @example Popüler terimler:
 *   $popular = $sh->get_popular_terms('product', 10);
 *
 * @example Trend terimler:
 *   $trending = $sh->get_trending_terms(7, 10);
 *
 * @example İstatistikler:
 *   $stats = $sh->get_stats();
 *   // ['total_searches'=>1500, 'unique_terms'=>320, 'no_results_count'=>45, 'top_type'=>'product']
 *
 * @example "Bunu mu demek istediniz?":
 *   $suggestion = $sh->did_you_mean('iphne', 2); // 'iphone'
 */

// ── Trait'leri yükle ──────────────────────────────────────────────────────────
require_once __DIR__ . '/Concerns/ManagesTable.php';
require_once __DIR__ . '/Concerns/ManagesBlacklist.php';
require_once __DIR__ . '/Concerns/TracksSearches.php';
require_once __DIR__ . '/Concerns/QueriesData.php';
require_once __DIR__ . '/Concerns/ManagesStorage.php';
require_once __DIR__ . '/Admin/SearchHistoryAdmin.php';
require_once __DIR__ . '/Admin/SearchHistoryAjax.php';

class SearchHistory {

    // ── Trait'ler ─────────────────────────────────────────────────────────────
    use ManagesTable;
    use ManagesBlacklist;
    use TracksSearches;
    use QueriesData;
    use ManagesStorage;
    use SearchHistoryAdmin;
    use SearchHistoryAjax;

    // =========================================================================
    // SABİTLER
    // =========================================================================

    /** Minimum arama terimi uzunluğu */
    const MIN_TERM_LENGTH = 2;

    /** Blacklist wp_options key */
    const BLACKLIST_OPTION = 'sh_blacklist';

    /** WP Cron hook adı */
    const CRON_HOOK = 'sh_daily_cleanup';

    // =========================================================================
    // PROPERTIES
    // =========================================================================

    /** @var string DB tablo adı */
    private string $table_name;

    /** @var string[] Kaydedilmeyecek terimler (cache) */
    private array $blacklist = [];

    /** @var bool Hook'ların çoklu kayıt edilmesini engeller */
    private static bool $hooks_registered = false;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'search_terms';
        $this->maybe_create_table();
        $this->load_blacklist();

        if ( self::$hooks_registered ) return;
        self::$hooks_registered = true;

        // Frontend: WP native search + FiboSearch takibi
        add_filter( 'the_posts',                    [ $this, 'auto_track_search' ],    10, 2 );
        add_filter( 'dgwt/wcas/search_query/args',  [ $this, 'track_fibosearch_query' ], 10, 1 );

        // Admin: sayfa + AJAX handler'lar
        add_action( 'admin_menu',                   [ $this, 'register_admin_page' ] );
        add_action( 'wp_ajax_sh_delete_term',       [ $this, 'ajax_delete_term' ] );
        add_action( 'wp_ajax_sh_delete_all',        [ $this, 'ajax_delete_all' ] );
        add_action( 'wp_ajax_sh_delete_clicks',     [ $this, 'ajax_delete_clicks' ] );
        add_action( 'wp_ajax_sh_export_csv',        [ $this, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_sh_blacklist_add',     [ $this, 'ajax_blacklist_add' ] );
        add_action( 'wp_ajax_sh_blacklist_remove',  [ $this, 'ajax_blacklist_remove' ] );
        add_action( 'wp_ajax_sh_search_history_save_toggle', [ $this, 'ajax_save_toggle' ] );

        // Cron: günlük temizlik
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_cleanup' ] );
        add_action( 'wp',            [ __CLASS__, 'schedule_cleanup' ] );
    }
}
