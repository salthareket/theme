<?php

/**
 * Paginate - WP_Query, WP_User_Query, WP_Comment_Query, WP_Term_Query ve
 * ham SQL sorgulari icin sayfalama (pagination) yoneticisi.
 *
 * @package SaltHareket
 * @version 1.1.0
 * @since   1.0.0
 *
 * @changelog
 *   1.1.0 - 2026-04-01
 *     - Fix: executePostQuery - Timber pagination guvenilmez, her zaman WP_Query found_posts kullaniliyor
 *   1.0.0 - Onceki stabil versiyon
 *
 * How to use:
 *   $paginate = new Paginate( $wp_query_args, ['type' => 'post', 'posts_per_page' => 12] );
 *   $result   = $paginate->get_results();
 *
 *   $paginate = new Paginate( $term_query_args, ['type' => 'taxonomy', 'number' => 20] );
 *   $result   = $paginate->get_results();
 *
 *   $paginate = new Paginate( "SELECT ...", $vars );
 *   $result   = $paginate->get_results();
 *
 *   $paginate = new Paginate( $encrypted_string, $vars );
 *   $paginate = new Paginate( 42, $vars );
 *
 * Return:
 *   $result['posts'] - sorgu sonuclari (Timber Post/User/Comment/Term veya stdClass)
 *   $result['data']  - sayfalama meta verisi:
 *     - count            : int - gosterilen sonuc sayisi
 *     - count_total      : int - toplam sonuc sayisi
 *     - page             : int - mevcut sayfa numarasi
 *     - page_total       : int - toplam sayfa sayisi
 *     - page_count_total : int - max_posts ile sinirli toplam sayfa sayisi
 *     - loader           : string - yukleme tipi (button, scroll, vb.)
 *
 * $vars key haritasi:
 *   posts_per_page    int      Sayfa basina post. paged=true yapar.
 *   number            int      Sayfa basina oge (comment/term/user).
 *   page              int      Mevcut sayfa numarasi (min 1)
 *   paged             bool     Sayfalama aktif mi
 *   max_posts         int      Maksimum gosterilecek toplam sonuc sayisi
 *   type              string   Sorgu tipi: post, taxonomy, user, comment
 *   post_type         string   WordPress post type slug'i
 *   taxonomy          string   Taxonomy slug'i
 *   terms             string   JSON array veya tekil term ID
 *   orderby           string   Siralama kolonu (whitelist: ID, date, title, vb.)
 *   order             string   Siralama yonu: ASC veya DESC
 *   loader            string   Yukleme tipi: button, scroll, default
 *   load_type         string   Yukleme modu: default, ajax, preload
 *   has_thumbnail     bool     Sadece thumbnail'li postlari filtrele
 *
 * Query type tespiti (otomatik):
 *   - array        -> 'wp'        (WP_Query args)
 *   - numeric      -> 'id'        (wp_options.option_id)
 *   - bosluklu str -> 'sql'       (ham SQL sorgusu)
 *   - tek kelime   -> 'encrypted' (Encrypt::decrypt ile cozulur)
 */

class Paginate {

    // -------------------------------------------------------------------------
    //  SQL injection koruması — izin verilen orderby / order değerleri
    // -------------------------------------------------------------------------
    private const ALLOWED_ORDERBY = [
        'id', 'ID', 'post_date', 'post_title', 'post_modified',
        'post_status', 'post_type', 'menu_order', 'rand', 'date',
        'title', 'modified', 'name', 'slug', 'comment_count',
        'created_at', 'updated_at',  // custom table columns (notifications vb.)
    ];

    private const ALLOWED_ORDER = ['ASC', 'DESC'];

    // -------------------------------------------------------------------------
    //  Properties — PHP 8.3 typed + default
    // -------------------------------------------------------------------------
    public string|array $query        = '';
    public string       $query_type   = '';
    public string       $type         = '';
    public string       $post_type    = '';
    public string       $taxonomy     = '';
    public array        $terms        = [];
    public int          $parent       = 0;
    public string       $roles        = '';
    public int          $page         = 1;
    public bool         $paged        = false;
    public int          $posts_per_page = -1;
    public int          $number       = 0;
    public int          $max_posts    = 0;
    public int          $posts_per_page_default = 0;
    public string       $orderby      = '';
    public string       $order        = '';
    public array        $vars         = [];
    public array        $filters      = [];
    public string       $loader       = '';
    public string       $load_type    = '';
    public bool         $has_thumbnail = false;

    // -------------------------------------------------------------------------
    //  Constructor
    // -------------------------------------------------------------------------
    public function __construct( string|array $query = '', array $vars = [] ) {

        // Query kaynağını belirle
        $this->query = ! empty( $query ) ? $query : ( $vars['query'] ?? '' );
        $this->detectQueryType();

        // Vars'ı sakla (dışarıdan erişim için)
        $this->vars = $vars;

        // --- orderby / order — whitelist kontrolü ---
        if ( isset( $vars['orderby'] ) ) {
            $raw = sanitize_key( $vars['orderby'] );
            $this->orderby = in_array( $raw, self::ALLOWED_ORDERBY, true ) ? $raw : 'ID';
        }
        if ( isset( $vars['order'] ) ) {
            $raw = strtoupper( sanitize_text_field( $vars['order'] ) );
            $this->order = in_array( $raw, self::ALLOWED_ORDER, true ) ? $raw : 'DESC';
        }

        // --- Sayısal parametreler ---
        // WP_Comment_Query, WP_Term_Query, WP_User_Query → "number" kullanır
        // WP_Query → "posts_per_page" kullanır
        // İkisi de aynı pagination logic'ine map'lenir
        if ( isset( $vars['posts_per_page'] ) ) {
            $this->posts_per_page = (int) $vars['posts_per_page'];
            $this->paged          = true;
        } elseif ( isset( $vars['number'] ) && (int) $vars['number'] > 0 ) {
            $this->number         = (int) $vars['number'];
            $this->posts_per_page = $this->number;
            $this->paged          = true;
        }
        if ( isset( $vars['max_posts'] ) ) {
            $this->max_posts = (int) $vars['max_posts'];
        }
        if ( isset( $vars['posts_per_page_default'] ) ) {
            $this->posts_per_page_default = (int) $vars['posts_per_page_default'];
        }
        if ( isset( $vars['page'] ) ) {
            $this->page = max( 1, (int) $vars['page'] );
        }
        if ( isset( $vars['paged'] ) ) {
            $this->paged = (bool) $vars['paged'];
        }
        if ( isset( $vars['parent'] ) ) {
            $this->parent = (int) $vars['parent'];
        }

        // --- String parametreler ---
        if ( isset( $vars['type'] ) ) {
            $this->type = sanitize_text_field( $vars['type'] );
        }
        if ( isset( $vars['post_type'] ) ) {
            $this->post_type = sanitize_text_field( $vars['post_type'] );
        }
        if ( isset( $vars['taxonomy'] ) ) {
            $this->taxonomy = sanitize_text_field( $vars['taxonomy'] );
        }
        if ( isset( $vars['roles'] ) ) {
            $this->roles = sanitize_text_field( $vars['roles'] );
        }
        if ( isset( $vars['loader'] ) ) {
            $this->loader = sanitize_text_field( $vars['loader'] );
        }
        if ( isset( $vars['load_type'] ) ) {
            $this->load_type = sanitize_text_field( $vars['load_type'] );
        }
        if ( isset( $vars['has_thumbnail'] ) ) {
            $this->has_thumbnail = (bool) $vars['has_thumbnail'];
        }

        // --- Terms — JSON veya tekil ---
        if ( isset( $vars['terms'] ) ) {
            $this->parseTerms( $vars['terms'] );
        }

        // --- Filters — JSON decode ---
        if ( isset( $vars['filters'] ) ) {
            $decoded = json_decode( stripslashes( $vars['filters'] ), true );
            if ( is_array( $decoded ) ) {
                $this->filters = $decoded;
            }
        }

        // --- Sayfa hesaplama ---
        $this->resolveCurrentPage();
    }

    // -------------------------------------------------------------------------
    //  get_totals — SQL query'den toplam sayı çeker
    // -------------------------------------------------------------------------
    public function get_totals( int $count = 0 ): array {
        global $wpdb;

        $query = $this->query;

        if ( ! is_string( $query ) || empty( $query ) ) {
            return $this->buildTotalsArray( $count, 0 );
        }

        if ( str_contains( $query, ' * ' ) ) {
            $query = str_replace( ' * ', ' count(*) as count ', $query );
        }

        // Internal SQL — encrypted/id kaynaklı, dışarıdan doğrudan gelmez
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var(
            "SELECT combined_table.count FROM ({$query}) AS combined_table"
        );

        return $this->buildTotalsArray( $count, $total );
    }

    // -------------------------------------------------------------------------
    //  get_results — Ana sorgu çalıştırıcı
    //  $type parametresi geriye uyumluluk için korunuyor.
    //  Öncelik: $this->type (vars'tan) > $type (parametre) > 'post' (default)
    // -------------------------------------------------------------------------
    public function get_results( string $type = 'post' ): array {

        // vars['type'] varsa o öncelikli, yoksa parametre, yoksa 'post'
        $resolved_type = $this->type !== '' ? $this->type : $type;

        $this->resolveQuerySource();

        if ( is_array( $this->query ) ) {
            return $this->getResultsFromWpQuery( $resolved_type );
        }

        return $this->getResultsFromSql();
    }

    // =========================================================================
    //  PRIVATE — WP_Query / WP_User_Query / WP_Comment_Query / WP_Term_Query
    // =========================================================================
    private function getResultsFromWpQuery( string $type ): array {
        $query = $this->query;

        // $type = get_results() parametresi, $this->type = vars'tan gelen
        // Fonksiyon parametresi her zaman öncelikli — caller ne istiyorsa o
        $post_count_key = $type === 'post' ? 'posts_per_page' : 'number';

        // --- Sayfalama parametreleri ---
        if ( $this->paged ) {
            $query[ $post_count_key ] = $this->posts_per_page;

            if ( $this->max_posts > 0 ) {
                $max = max( $this->max_posts, $this->posts_per_page );
                $remaining = $max - ( $this->page - 1 ) * $query[ $post_count_key ];
                if ( $remaining > 0 ) {
                    $query[ $post_count_key ] = min( $query[ $post_count_key ], $remaining );
                } else {
                    // max_posts aşıldı — boş sonuç dönecek
                    $query[ $post_count_key ] = 0;
                }
                $query['paged'] = $this->page;
            } elseif ( $this->page > 0 ) {
                $query['paged'] = $this->page;
            }
        } else {
            $query[ $post_count_key ] = $this->posts_per_page;
        }

        // Taxonomy / User — offset hesaplama (paged desteklemez)
        if ( in_array( $type, ['taxonomy', 'user'], true ) && isset( $query['paged'] ) ) {
            $query['paged']  = max( 0, $query['paged'] );
            $query['offset'] = ( $this->page - 1 ) * ( $query['number'] ?? 0 );
            unset( $query['paged'] );
        }

        // Polylang dil filtresi
        if ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE === 'polylang' && function_exists( 'pll_current_language' ) ) {
            $query['lang'] = pll_current_language();
        }

        // Comment — number < 1 ise sınırsız çek
        if ( $type === 'comment' && isset( $query['number'] ) && $query['number'] < 1 ) {
            unset( $query['number'] );
            $query['no_found_rows'] = true;
        }

        // --- Sorguyu çalıştır ---
        $posts       = [];
        $count       = 0;
        $count_total = 0;
        $page_total  = -1;

        switch ( $type ) {
            case 'post':
                [ $posts, $count_total, $page_total ] = $this->executePostQuery( $query );
                break;

            case 'user':
                [ $posts, $count_total ] = $this->executeUserQuery( $query );
                break;

            case 'comment':
                [ $posts, $count_total, $count, $page_total ] = $this->executeCommentQuery( $query );
                break;

            case 'taxonomy':
                [ $posts, $count_total ] = $this->executeTaxonomyQuery( $query );
                break;
        }

        // --- Sonuç meta verisi ---
        return $this->buildPaginatedResult( $posts, $count, $count_total, $page_total );
    }

    // =========================================================================
    //  PRIVATE — Ham SQL sorgusu
    // =========================================================================
    private function getResultsFromSql(): array {
        global $wpdb;

        $query = (string) $this->query;

        if ( $this->posts_per_page > 0 ) {
            $posts_per_page = $this->posts_per_page;
            $offset         = ( $this->page * $posts_per_page ) - $posts_per_page;

            if ( $this->orderby !== '' && $this->order !== '' ) {
                // orderby/order constructor'da whitelist'e sokuldu — güvenli interpolation
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $query .= $wpdb->prepare(
                    " ORDER BY {$this->orderby} {$this->order} LIMIT %d, %d",
                    $offset,
                    $posts_per_page
                );
            } else {
                $query .= $wpdb->prepare( " LIMIT %d, %d", $offset, $posts_per_page );
            }
        } elseif ( $this->orderby !== '' && $this->order !== '' ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $query .= " ORDER BY {$this->orderby} {$this->order}";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $query );

        return [
            'posts' => $results,
            'data'  => $this->get_totals( count( $results ) ),
        ];
    }

    // =========================================================================
    //  PRIVATE — Query type alt sorguları
    // =========================================================================

    /**
     * Post query — Timber v2 PostQuery döner.
     *
     * @return array{0: mixed, 1: int, 2: int}
     */
    private function executePostQuery( array $query ): array {
        // found_posts'u alabilmek için no_found_rows kapalı olmalı
        $query['no_found_rows'] = false;

        $result     = Timber::get_posts( $query );
        $total      = 0;
        $page_total = -1;

        // Timber v2 pagination'dan found_posts almak güvenilir değil,
        // her zaman WP_Query fallback kullan
        if ( isset( $query['post_type'] ) ) {
            $count_query = new \WP_Query( array_merge( $query, [
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ] ) );
            $total = (int) $count_query->found_posts;
            wp_reset_postdata();
        }

        return [ $result, $total, $page_total ];
    }

    /**
     * User query.
     *
     * @return array{0: array, 1: int}
     */
    private function executeUserQuery( array $query ): array {
        $result = new WP_User_Query( $query );
        $total  = (int) $result->get_total();
        $users  = $result->get_results();
        $posts  = Timber::get_users( $users );

        return [ $posts, $total ];
    }

    /**
     * Comment query.
     *
     * @return array{0: array, 1: int, 2: int, 3: int}
     */
    private function executeCommentQuery( array $query ): array {
        $result     = new WP_Comment_Query( $query );
        $total      = (int) ( $result->found_comments ?? 0 );
        $posts      = Timber::get_comments( $result->comments ?? [] );
        $page_total = isset( $result->max_num_pages ) ? (int) $result->max_num_pages : -1;

        if ( ! empty( $query['no_found_rows'] ) ) {
            $total = count( $posts );
        }

        $count = count( $posts );

        return [ $posts, $total, $count, $page_total ];
    }

    /**
     * Taxonomy query.
     *
     * @return array{0: mixed, 1: int}
     */
    private function executeTaxonomyQuery( array $query ): array {
        $result      = Timber::get_terms( $query );
        $count_query = $query;
        unset( $count_query['offset'], $count_query['number'] );

        $total = wp_count_terms( $count_query );
        $total = is_wp_error( $total ) ? 0 : (int) $total;

        return [ $result, $total ];
    }

    // =========================================================================
    //  PRIVATE — Yardımcı metodlar
    // =========================================================================

    /**
     * Query type'ı belirle: wp (array), id (numeric), sql (space içerir), encrypted.
     */
    private function detectQueryType(): void {
        if ( empty( $this->query ) ) {
            $this->query_type = '';
            return;
        }

        if ( is_array( $this->query ) ) {
            $this->query_type = 'wp';
        } elseif ( is_numeric( $this->query ) ) {
            $this->query_type = 'id';
        } elseif ( str_contains( (string) $this->query, ' ' ) ) {
            $this->query_type = 'sql';
        } else {
            $this->query_type = 'encrypted';
        }
    }

    /**
     * Encrypted veya ID kaynaklı query'leri çöz.
     */
    private function resolveQuerySource(): void {
        if ( $this->query_type === 'id' ) {
            global $wpdb;
            $option_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d",
                (int) $this->query
            ) );
            if ( $option_name !== null ) {
                $this->query = QueryCache::get_option( $option_name );
            }
        }

        if ( $this->query_type === 'encrypted' ) {
            $enc         = new Encrypt();
            $this->query = $enc->decrypt( (string) $this->query );
        }
    }

    /**
     * Terms parametresini parse et — JSON array veya tekil değer.
     */
    private function parseTerms( string $raw ): void {
        $decoded = json_validate_custom( stripslashes( $raw ) );

        if ( is_array( $decoded ) ) {
            $this->terms = $decoded;
        } else {
            $this->terms = [ sanitize_text_field( $raw ) ];
        }

        // terms[0] === 0 → tüm term'leri çek
        if ( ! empty( $this->terms ) && ( $this->terms[0] ?? null ) == 0 && $this->taxonomy !== '' ) {
            $all_terms = get_terms( [
                'taxonomy'   => $this->taxonomy,
                'hide_empty' => false,
                'fields'     => 'ids',
            ] );
            $this->terms = is_wp_error( $all_terms ) ? [] : $all_terms;
        }
    }

    /**
     * Mevcut sayfa numarasını belirle — $_GET['cpage'] ve get_query_var('paged') öncelikli.
     */
    private function resolveCurrentPage(): void {
        if ( $this->posts_per_page > 0 && $this->paged ) {
            // max_posts < posts_per_page ise posts_per_page'i düşür
            if ( $this->max_posts > 0 && $this->max_posts < $this->posts_per_page ) {
                $this->posts_per_page = $this->max_posts;
            }

            if ( $this->page < 1 ) {
                // $_GET['cpage'] — AJAX pagination için
                $cpage      = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 0;
                $this->page = max( 1, $cpage );
            }
        }

        // WP'nin kendi paged query var'ı — varsa override et
        $paged_var = (int) get_query_var( 'paged', 0 );
        if ( $paged_var > 0 ) {
            $this->page = $paged_var;
        }

        $this->page = max( 1, $this->page );
    }

    /**
     * get_totals() için standart array oluştur.
     */
    private function buildTotalsArray( int $count, int $total ): array {
        $page_total = 1;
        if ( $this->posts_per_page > 0 && $total > 0 ) {
            $page_total = (int) ceil( $total / $this->posts_per_page );
        }

        return [
            'count'       => $count,
            'count_total' => $total,
            'page'        => $this->page,
            'page_total'  => $page_total,
            'loader'      => $this->loader,
        ];
    }

    /**
     * WP query sonuçları için sayfalama meta verisi hesapla.
     */
    private function buildPaginatedResult( mixed $posts, int $count, int $count_total, int $page_total ): array {

        // count — max_posts varsa sınırla
        if ( $this->max_posts > 0 && $count_total > 0 ) {
            $count = min( $count_total, $this->max_posts );
        } else {
            $count = max( $count, $count_total );
        }

        // page_total hesaplama
        if ( $this->paged ) {
            if ( $this->posts_per_page > 0 && $count_total > 0 ) {
                $page_total = (int) ceil( $count_total / $this->posts_per_page );
            } elseif ( $page_total < 0 ) {
                $page_total = 1;
            }
        } else {
            $page_total = 1;
        }

        $page_count_total = $page_total;

        // max_posts ile sayfa sayısını sınırla
        if ( $this->paged && $this->max_posts > 0 && $count_total > 0 && $this->posts_per_page > 0 ) {
            $total_pages      = (int) ceil( $count_total / $this->posts_per_page );
            $max_pages        = (int) ceil( $this->max_posts / $this->posts_per_page );
            $page_count_total = min( $total_pages, $max_pages );
        }

        return [
            'posts' => $posts,
            'data'  => [
                'count'            => $count,
                'count_total'      => $count_total,
                'page'             => $this->page,
                'page_total'       => $page_total,
                'page_count_total' => $page_count_total,
                'loader'           => $this->loader,
            ],
        ];
    }
}
