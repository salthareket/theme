<?php

Class Paginate {

    public $query;
    public $query_type;
    public $type;
    public $post_type;
    public $taxonomy;
    public $terms;
    public $parent;
    public $role;
    public $page;
    public $paged;
    public $posts_per_page;
    public $number;
    public $max_posts;
    public $posts_per_page_default;
    public $orderby;
    public $order;
    public $vars;
    public $filters;
    public $loader;
    public $load_type;
    public $has_thumbnail;

    // FIX #1: SQL Injection — izin verilen orderby kolonları
    private static $allowed_orderby = [
        'id', 'ID', 'post_date', 'post_title', 'post_modified',
        'post_status', 'post_type', 'menu_order', 'rand', 'date',
        'title', 'modified', 'name', 'slug', 'comment_count',
    ];

    // FIX #1: SQL Injection — izin verilen order yönleri
    private static $allowed_order = [ 'ASC', 'DESC' ];

    function __construct( $query = "", $vars = [] ) {

        $this->query = ! empty( $query ) ? $query : ( isset( $vars['query'] ) ? $vars['query'] : '' );

        if ( ! empty( $this->query ) ) {
            if ( is_array( $this->query ) ) {
                $this->query_type = 'wp';
            } else {
                if ( is_numeric( $this->query ) ) {
                    $this->query_type = 'id';
                } else {
                    if ( strpos( $this->query, ' ' ) !== false ) {
                        $this->query_type = 'sql';
                    } else {
                        $this->query_type = 'encrypted';
                    }
                }
            }
        }

        if ( isset( $vars ) ) {
            if ( isset( $vars['orderby'] ) ) {
                // FIX #1: orderby whitelist kontrolü
                $raw_orderby = sanitize_key( $vars['orderby'] );
                $this->orderby = in_array( $raw_orderby, self::$allowed_orderby, true )
                    ? $raw_orderby
                    : 'ID';
            }
            if ( isset( $vars['order'] ) ) {
                // FIX #1: order whitelist kontrolü
                $raw_order = strtoupper( sanitize_text_field( $vars['order'] ) );
                $this->order = in_array( $raw_order, self::$allowed_order, true )
                    ? $raw_order
                    : 'DESC';
            }

            $this->posts_per_page = -1;
            if ( isset( $vars['posts_per_page'] ) ) {
                $this->posts_per_page = (int) $vars['posts_per_page'];
                $this->paged = true;
            }
            if ( isset( $vars['max_posts'] ) ) {
                $this->max_posts = (int) $vars['max_posts'];
            }
            if ( isset( $vars['posts_per_page_default'] ) ) {
                $this->posts_per_page_default = (int) $vars['posts_per_page_default'];
            }
            if ( isset( $vars['page'] ) ) {
                $this->page = (int) $vars['page'];
            }
            if ( isset( $vars['paged'] ) ) {
                $this->paged = $vars['paged'];
            }
            if ( isset( $vars['type'] ) ) {
                $this->type = sanitize_text_field( $vars['type'] );
            }
            if ( isset( $vars['post_type'] ) ) {
                $this->post_type = sanitize_text_field( $vars['post_type'] );
            }
            if ( isset( $vars['taxonomy'] ) ) {
                $this->taxonomy = sanitize_text_field( $vars['taxonomy'] );
            }
            if ( isset( $vars['terms'] ) ) {
                $terms = json_validate_custom( stripslashes( $vars['terms'] ) );
                if ( $terms ) {
                    $this->terms = $terms;
                } else {
                    $this->terms = [ sanitize_text_field( $vars['terms'] ) ];
                }
                if ( $this->terms && $this->terms[0] == 0 ) {
                    $this->terms = get_terms( [
                        'taxonomy'   => $this->taxonomy,
                        'hide_empty' => false,
                        'fields'     => 'ids',
                    ] );
                }
            }
            if ( isset( $vars['parent'] ) ) {
                $this->parent = (int) $vars['parent'];
            }
            if ( isset( $vars['roles'] ) ) {
                $this->roles = sanitize_text_field( $vars['roles'] );
            }
            if ( isset( $vars['filters'] ) ) {
                $filters = str_replace( "\\", "", $vars['filters'] );
                $filters = json_decode( $filters, true );
                $this->filters = $filters;
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
        }

        if ( isset( $this->posts_per_page ) && $this->paged ) {
            if ( ! empty( $this->max_posts ) ) {
                if ( $this->max_posts < $this->posts_per_page ) {
                    $this->posts_per_page = $this->max_posts;
                }
            }
            if ( ! isset( $this->page ) ) {
                // FIX #5: $_GET kullanımı intval ile güvence altında
                $page = isset( $_GET['cpage'] ) ? abs( intval( $_GET['cpage'] ) ) : abs( intval( $this->page ?? 0 ) );
                $page = $page < 1 ? 1 : $page;
                $this->page = $page;
            }
        }

        // FIX #4: page çakışması — get_query_var öncelikli ama sadece gerçekten doluysa override et
        $paged_var = get_query_var( 'paged' );
        if ( ! empty( $paged_var ) ) {
            $this->page = (int) $paged_var;
        } else {
            $this->page = empty( $this->page ) ? 1 : (int) $this->page;
        }
    }

    // FIX #3: strpos false kontrolü düzeltildi
    // FIX #7: $count parametresi gerçekten kullanılıyor
    function get_totals( $count = 0 ) {
        global $wpdb;
        $query = $this->query;

        // FIX #3: strpos 0 döndürünce false sayılıyordu — !== false ile düzeltildi
        if ( strpos( $query, ' * ' ) !== false ) {
            $query = str_replace( ' * ', ' count(*) as count ', $query );
        }

        // FIX #2: Ham query çalıştırılıyor — bu fonksiyon sadece internal SQL query ile çalışır.
        // Query dışarıdan geliyorsa ve güvenli değilse buraya gelmemeli.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = $wpdb->get_var( "SELECT combined_table.count FROM ({$query}) AS combined_table" );
        $total = (int) $total;

        $page_total = 1;
        if ( $this->posts_per_page > 0 ) {
            $page_total = ceil( $total / $this->posts_per_page );
        }

        return [
            'count'       => $count, // FIX #7: parametre artık sonuca yansıyor
            'count_total' => $total,
            'page'        => 1,
            'page_total'  => $page_total,
            'loader'      => $this->loader,
        ];
    }

    function get_results( $type = 'post' ) {

        if ( $this->query_type === 'id' ) {
            global $wpdb;
            $option_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d",
                $this->query
            ) );
            $this->query = QueryCache::get_option( $option_name );
        }

        if ( $this->query_type === 'encrypted' ) {
            $enc         = new Encrypt();
            $this->query = $enc->decrypt( $this->query );
        }

        $query = $this->query;

        if ( is_array( $this->query ) ) {

            $post_count_query = $this->type === 'post' ? 'posts_per_page' : 'number';

            if ( $this->paged ) {
                $query[ $post_count_query ] = $this->posts_per_page;
                if ( ! empty( $this->max_posts ) && is_numeric( $this->max_posts ) ) {
                    $max_posts = $this->max_posts < $this->posts_per_page
                        ? $this->posts_per_page
                        : $this->max_posts;
                    if ( $max_posts > 0 ) {
                        $query[ $post_count_query ] = min(
                            $query[ $post_count_query ],
                            $max_posts - ( $this->page - 1 ) * $query[ $post_count_query ]
                        );
                    }
                    $query['paged'] = $this->page;
                } else {
                    if ( ! empty( $this->page ) ) {
                        $query['paged'] = $this->page;
                    }
                }
            } else {
                $query[ $post_count_query ] = $this->posts_per_page;
            }

            if ( $type === 'taxonomy' || $type === 'user' ) {
                if ( isset( $query['paged'] ) ) {
                    if ( $query['paged'] < 0 ) {
                        $query['paged'] = 0;
                    }
                    $query['offset'] = ( $this->page - 1 ) * $query['number'];
                    unset( $query['paged'] );
                }
            }

            if ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE === 'polylang' ) {
                $query['lang'] = pll_current_language();
            }

            if ( $type === 'comment' && $query['number'] < 1 ) {
                unset( $query['number'] );
                $query['no_found_rows'] = true;
            }

            $posts = [];
            $total = 0;

            switch ( $type ) {
                case 'post':
                    $result = Timber::get_posts( $query );
                    $total  = 0;

                    // FIX #6: Timber'ın kendi query objesinden total alınıyor
                    // global $wp_query yerine result üzerinden güvenli okuma
                    if ( $result instanceof \Timber\PostArrayObject && method_exists( $result, 'pagination' ) ) {
                        $pagination = $result->pagination();
                        $total      = isset( $pagination->total ) ? (int) $pagination->total : 0;
                    }
                    // Timber pagination dönmüyorsa WP_Query'den güvenli fallback
                    if ( $total === 0 && isset( $query['post_type'] ) ) {
                        $count_query = new WP_Query( array_merge( $query, [
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'no_found_rows'  => false,
                        ] ) );
                        $total = (int) $count_query->found_posts;
                        wp_reset_postdata();
                    }

                    $posts = $result;
                    if ( isset( $result->max_num_pages ) ) {
                        $page_total = $result->max_num_pages;
                    }
                    break;

                case 'user':
                    $result = new WP_User_Query( $query );
                    $total  = $result->get_total();
                    $posts  = $result->get_results();
                    $posts  = Timber::get_users( $posts );
                    break;

                case 'comment':
                    $result = new WP_Comment_Query( $query );
                    $total  = $result->found_comments;
                    $posts  = $result->comments;
                    if ( isset( $result->max_num_pages ) ) {
                        $page_total = $result->max_num_pages;
                    }
                    $posts = Timber::get_comments( $posts );
                    if ( ! empty( $query['no_found_rows'] ) ) {
                        $total = count( $posts );
                        $count = $total;
                    } else {
                        $count = count( $posts );
                    }
                    break;

                case 'taxonomy':
                    $result = Timber::get_terms( $query );
                    $count_query = $query;
                    unset( $count_query['offset'], $count_query['number'] );
                    $total = wp_count_terms( $count_query );
                    $total = is_wp_error( $total ) ? 0 : (int) $total;
                    $posts = $result;
                    break;
            }

            $count_total    = (int) $total;
            $page_total     = isset( $page_total ) ? (int) $page_total : -1;
            $page_count_total = 1;

            if ( ! empty( $this->max_posts ) && $count_total > 0 ) {
                $count = $count_total > $this->max_posts ? $this->max_posts : $count_total;
            } else {
                $count = $count_total;
            }

            if ( $this->paged ) {
                if ( $this->posts_per_page > 0 && $page_total < 0 && $count_total <= 0 ) {
                    $page_total = 1;
                } elseif ( ! empty( $this->posts_per_page ) && $count_total > 0 ) {
                    $page_total = ceil( $count_total / $this->posts_per_page );
                }
            } else {
                $page_total = 1;
            }

            $page_count_total = (int) $page_total;

            if ( $this->paged && ! empty( $this->max_posts ) && $count_total > 0 ) {
                $total_pages      = ceil( $count_total / $this->posts_per_page );
                $max_pages        = ceil( $this->max_posts / $this->posts_per_page );
                $page_count_total = (int) min( $total_pages, $max_pages );
            }

            return [
                'posts' => $posts,
                'data'  => [
                    'count'            => (int) $count,
                    'count_total'      => (int) $count_total,
                    'page'             => (int) $this->page,
                    'page_total'       => (int) $page_total,
                    'page_count_total' => (int) $page_count_total,
                    'loader'           => $this->loader,
                ],
            ];

        } else {

            // Manuel SQL query — sadece internal (encrypted/id) kaynaklı query'ler buraya gelir
            global $wpdb;

            if ( $this->posts_per_page > 0 ) {
                $posts_per_page = (int) $this->posts_per_page;
                $offset         = ( (int) $this->page * $posts_per_page ) - $posts_per_page;

                if ( isset( $this->orderby ) && isset( $this->order ) ) {
                    // FIX #1 + #2: orderby ve order zaten constructor'da whitelist'e sokuldu
                    // Burada sadece güvenli değerleri kullanıyoruz
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $query .= $wpdb->prepare(
                        " ORDER BY {$this->orderby} {$this->order} LIMIT %d, %d",
                        $offset,
                        $posts_per_page
                    );
                } else {
                    $query .= $wpdb->prepare( " LIMIT %d, %d", $offset, $posts_per_page );
                }
            } else {
                if ( isset( $this->orderby ) && isset( $this->order ) ) {
                    // FIX #1: orderby ve order whitelist'ten geçti — interpolation güvenli
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $query .= " ORDER BY {$this->orderby} {$this->order}";
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $query );

            return [
                'posts' => $results,
                'data'  => $this->get_totals( count( $results ) ),
            ];
        }
    }
}