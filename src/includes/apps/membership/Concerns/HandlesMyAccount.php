<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesMyAccount
 *
 * My Account sayfası, endpoint routing, menü sistemi ve WooCommerce abstraction.
 * Dışarıdan bakan kod WooCommerce var mı yok mu bilmez — her şey bu trait üzerinden.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: getEndpointUrl() — WC/non-WC abstraction
 *     - Add: getLoginUrl() / getLogoutUrl() — URL helpers
 *     - Add: getMyAccountPageId() — WC/non-WC page ID
 *     - Add: getCurrentEndpoint() — aktif endpoint
 *     - Add: getMenuItems() — filter-based menü (sh_membership_menu_items)
 *     - Add: getMenu() — tam menü array'i (count'larla)
 *     - Add: registerEndpoints() — rewrite endpoint registration (flush fix)
 *     - Add: redirectToProfile() / redirectIfNotActivated() / redirectIfNotCompleted()
 *     - Add: loginRequired() — role-based access control
 *     - Add: fixDuplicateMyAccountPages() — WC activate/deactivate bug fix
 *     - Add: renderEndpointContent() — endpoint içerik dispatch
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Her zaman bu — WC var mı yok mu umursamıyorsun:
 * $mm = MembershipManager::getInstance();
 * $url  = $mm->getEndpointUrl('profile');
 * $menu = $mm->getMenu();
 * $mm->loginRequired();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Menüye item ekle (dışarıdan):
 *   add_filter('sh_membership_menu_items', function($items) {
 *       $items['portfolio'] = ['title' => 'Portfolyo', 'menu' => 'Portfolyo', 'roles' => ['expert']];
 *       return $items;
 *   });
 *
 * @example
 *   // Menüden item çıkar:
 *   add_filter('sh_membership_menu_items', function($items) {
 *       unset($items['reviews']);
 *       return $items;
 *   });
 *
 * @example
 *   // Endpoint içerik fonksiyonunu override et:
 *   // my-account.php'de: function my_account_custom_content_profile() { ... }
 *
 * @example
 *   // WC aktifken WC menüsüne item ekle:
 *   add_filter('woocommerce_account_menu_items', function($items) {
 *       $items['my-custom'] = 'Custom';
 *       return $items;
 *   });
 *
 * @example
 *   // Login zorunlu, sadece expert rolü:
 *   MembershipManager::getInstance()->loginRequired([
 *       ['role' => 'expert', 'action' => ['edit', 'view']]
 *   ]);
 */
trait HandlesMyAccount
{
    // ─── URL Helpers ─────────────────────────────────────────────────────────

    /**
     * Endpoint URL döndür.
     * WC aktifse wc_get_account_endpoint_url(), değilse custom.
     * Polylang aktifse mevcut dildeki my-account sayfasının URL'si kullanılır.
     */
    public function getEndpointUrl( string $endpoint = '' ): string
    {
        $base_url = $this->getMyAccountBaseUrl();

        if ( $endpoint === '' ) {
            return esc_url( trailingslashit( $base_url ) );
        }

        // WC aktifse WC'nin endpoint query var'larını kullan (pretty URL için)
        if ( self::isWooActive() && function_exists( 'wc_get_account_endpoint_url' ) ) {
            // WC'nin döndürdüğü URL'yi al ama base'ini bizim Polylang-aware base ile değiştir
            $wc_url = wc_get_account_endpoint_url( $endpoint );

            // Polylang aktifse: WC URL'sinin base kısmını mevcut dildeki my-account URL'si ile değiştir
            if ( function_exists( 'pll_current_language' ) ) {
                $wc_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0;
                if ( $wc_page_id > 0 ) {
                    $wc_base = trailingslashit( get_permalink( $wc_page_id ) );
                    // Eğer WC base ile bizim Polylang base farklıysa değiştir
                    if ( $wc_base !== trailingslashit( $base_url ) ) {
                        $wc_url = str_replace( $wc_base, trailingslashit( $base_url ), $wc_url );
                    }
                }
            }

            return esc_url( $wc_url );
        }

        return esc_url( trailingslashit( $base_url ) . trailingslashit( $endpoint ) );
    }

    /**
     * Mevcut dile göre my-account sayfasının base URL'sini döndür.
     * Polylang aktifse çevrilmiş sayfanın permalink'ini kullanır.
     */
    private function getMyAccountBaseUrl(): string
    {
        $page_id = $this->getMyAccountPageId();

        if ( ! $page_id ) {
            return home_url( '/my-account/' );
        }

        // Polylang aktifse mevcut dildeki çevirisini bul
        if ( function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post' ) ) {
            $current_lang    = pll_current_language();
            $translated_id   = pll_get_post( $page_id, $current_lang );
            if ( $translated_id && $translated_id !== $page_id ) {
                return get_permalink( $translated_id );
            }
        }

        return get_permalink( $page_id );
    }

    /**
     * Login URL döndür.
     */
    public function getLoginUrl( string $redirect_to = '' ): string
    {
        $url = $this->getMyAccountBaseUrl();

        if ( $redirect_to ) {
            if ( isset( $_SESSION ) ) {
                $_SESSION['referer_url'] = esc_url( $redirect_to );
            }
        }

        return esc_url( $url );
    }

    /**
     * Logout URL döndür.
     */
    public function getLogoutUrl( string $redirect_url = '' ): string
    {
        if ( ! $redirect_url ) $redirect_url = site_url();
        return str_replace( '&amp;', '&', wp_logout_url( $redirect_url ) );
    }

    /**
     * My Account sayfa ID'sini döndür.
     * WC aktifse WC'nin sayfası, değilse options_myaccount_page_id.
     */
    public function getMyAccountPageId(): int
    {
        if ( self::isWooActive() && function_exists( 'wc_get_page_id' ) ) {
            $id = wc_get_page_id( 'myaccount' );
            if ( $id > 0 ) return $id;
        }

        return (int) get_option( 'options_myaccount_page_id', 0 );
    }

    /**
     * Aktif endpoint'i döndür.
     */
    public function getCurrentEndpoint( string $base = '' ): string
    {
        if ( self::isWooActive() && function_exists( 'WC' ) && WC()->query ) {
            return WC()->query->get_current_endpoint();
        }

        return function_exists( 'getUrlEndpoint' ) ? getUrlEndpoint( '', $base ) : '';
    }

    // ─── Menu System ─────────────────────────────────────────────────────────

    /**
     * Menü item'larını döndür — filter-based, dışarıdan ekle/çıkar/sırala.
     * WC aktifse WC menüsü base alınır, custom item'lar eklenir.
     *
     * @return array ['endpoint' => 'Menü Başlığı']
     */
    public function getMenuItems(): array
    {
        // Sonsuz döngü koruması — WC filter'ı bu metodu tekrar çağırabilir
        static $running = false;
        if ( $running ) return $this->getDefaultMenuItems();
        $running = true;

        if ( self::isWooActive() && function_exists( 'wc_get_account_menu_items' ) ) {
            $items = wc_get_account_menu_items();
        } else {
            $items = $this->getDefaultMenuItems();
        }

        // Filter ile dışarıdan ekle/çıkar/sırala
        $items = apply_filters( 'sh_membership_menu_items', $items );

        // Rol filtresi
        $items = $this->filterMenuItemsByRole( $items );

        $running = false;
        return $items;
    }

    /**
     * Tam menü array'i — URL, class, count bilgileriyle.
     */
    public function getMenu(): array
    {
        $menu  = [];
        $items = $this->getMenuItems();

        foreach ( $items as $endpoint => $label ) {
            $item = [
                'type'   => $endpoint,
                'action' => $endpoint,
                'class'  => $this->getMenuItemClasses( $endpoint ),
                'url'    => $this->getEndpointUrl( $endpoint ),
                'title'  => is_array( $label ) ? ( $label['menu'] ?? $label['title'] ?? $endpoint ) : $label,
                'count'  => 0,
            ];

            // Count'lar
            if ( $endpoint === 'messages' && defined( 'ENABLE_CHAT' ) && ENABLE_CHAT && class_exists( 'Messenger' ) ) {
                $item['count'] = \Messenger::count();
            }
            if ( $endpoint === 'notifications' && defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS ) {
                $item['count'] = $this->getNotificationCount();
            }
            if ( $endpoint === 'favorites' && defined( 'ENABLE_REACTIONS' ) && ENABLE_REACTIONS ) {
                $item['count'] = count( (array) \Data::get( 'favorites' ) );
            }
            if ( $endpoint === 'reviews' && ! ( defined( 'DISABLE_COMMENTS' ) && DISABLE_COMMENTS ) ) {
                $item['count'] = $this->user ? $this->user->get_reviews_count() : 0;
            }

            $item = apply_filters( 'sh_membership_menu_item', $item, $endpoint );
            $menu[] = $item;
        }

        return $menu;
    }

    /**
     * Menü item CSS class'larını döndür.
     */
    public function getMenuItemClasses( string $endpoint ): string
    {
        if ( self::isWooActive() && function_exists( 'wc_get_account_menu_item_classes' ) ) {
            return wc_get_account_menu_item_classes( $endpoint );
        }
        return 'menu-item-' . sanitize_html_class( $endpoint );
    }

    /**
     * Default menü item'ları (WC yokken).
     * Dışarıdan sh_membership_menu_items filter ile değiştirilebilir.
     */
    public function getDefaultMenuItems(): array
    {
        $items = [];

        $items['profile'] = __( 'Profile', 'salthareket' );

        if ( defined( 'ENABLE_CHAT' ) && ENABLE_CHAT ) {
            $items['messages'] = __( 'Messages', 'salthareket' );
        }
        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS ) {
            $items['notifications'] = __( 'Notifications', 'salthareket' );
        }
        if ( defined( 'ENABLE_REACTIONS' ) && ENABLE_REACTIONS ) {
            $items['favorites'] = __( 'Favorites', 'salthareket' );
        }
        if ( ! ( defined( 'DISABLE_COMMENTS' ) && DISABLE_COMMENTS ) ) {
            $items['reviews'] = __( 'Reviews', 'salthareket' );
        }
        if ( defined( 'ENABLE_PASSWORD_RECOVER' ) && ENABLE_PASSWORD_RECOVER ) {
            $items['security'] = __( 'Security', 'salthareket' );
        }
        if ( self::isActivationRequired() ) {
            $items['not-activated'] = __( 'Activation', 'salthareket' );
        }

        $items['customer-logout'] = __( 'Logout', 'salthareket' );

        return $items;
    }

    /**
     * Menü item'larını kullanıcı rolüne göre filtrele.
     * Item 'roles' key'i varsa sadece o roller görebilir.
     */
    private function filterMenuItemsByRole( array $items ): array
    {
        if ( ! $this->user ) return $items;

        $role = method_exists( $this->user, 'get_role' ) ? $this->user->get_role() : '';

        return array_filter( $items, function ( $item ) use ( $role ) {
            if ( ! is_array( $item ) ) return true;
            if ( empty( $item['roles'] ) ) return true;
            return in_array( $role, (array) $item['roles'], true );
        } );
    }

    // ─── Endpoint Registration ───────────────────────────────────────────────

    /**
     * Custom endpoint'leri register et.
     * WC aktifse WC kendi endpoint'lerini yönetir, sadece custom olanları ekle.
     * flush_rewrite_rules() sadece yeni endpoint eklendiğinde çağrılır.
     */
    public function registerEndpoints(): void
    {
        $custom_endpoints = array_keys( $this->getDefaultMenuItems() );
        // WC'nin kendi endpoint'leri — bunları atlayalım
        $wc_native = [ 'orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'dashboard', 'customer-logout' ];

        $rules       = get_option( 'rewrite_rules', [] );
        $needs_flush = false;

        foreach ( $custom_endpoints as $endpoint ) {
            if ( in_array( $endpoint, $wc_native, true ) ) continue;

            // Her zaman rewrite endpoint ekle — WC aktif olsa da
            add_rewrite_endpoint( $endpoint, EP_PAGES );

            if ( ! empty( $rules ) && ! isset( $rules[ $endpoint . '(/(.+))?/?' ] ) ) {
                $needs_flush = true;
            }
        }

        if ( $needs_flush ) {
            flush_rewrite_rules( false );
        }

        // WC aktifken query vars'a da ekle
        if ( self::isWooActive() ) {
            add_filter( 'woocommerce_get_query_vars', function( $vars ) use ( $custom_endpoints, $wc_native ) {
                foreach ( $custom_endpoints as $endpoint ) {
                    if ( ! in_array( $endpoint, $wc_native, true ) && ! isset( $vars[ $endpoint ] ) ) {
                        $vars[ $endpoint ] = $endpoint;
                    }
                }
                return $vars;
            } );
        }
    }

    // ─── Endpoint Content Registration ──────────────────────────────────────

    /**
     * My account endpoint content fonksiyonlarını register et.
     * Her endpoint için my_account_custom_content_{endpoint} fonksiyonu tanımlar.
     * Dışarıdan filter ile yeni endpoint eklenebilir veya çıkarılabilir.
     *
     * Dışarıdan endpoint ekle:
     * add_filter('sh_my_account_endpoints', function($endpoints) {
     *     $endpoints['my-custom'] = [
     *         'title'    => 'My Custom',
     *         'callback' => function() {
     *             // içerik render et
     *         },
     *     ];
     *     return $endpoints;
     * });
     *
     * Dışarıdan endpoint çıkar:
     * add_filter('sh_my_account_endpoints', function($endpoints) {
     *     unset($endpoints['favorites']);
     *     return $endpoints;
     * });
     */
    public function registerEndpointContent(): void
    {
        // Default endpoint'ler ve content callback'leri
        $endpoints = [];

        if ( defined( 'ENABLE_REACTIONS' ) && ENABLE_REACTIONS ) {
            $endpoints['favorites'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'Favorites' ) : 'Favorites',
                'callback' => [ $this, 'renderFavoritesContent' ],
            ];
        }

        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS ) {
            $endpoints['notifications'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'Notifications' ) : 'Notifications',
                'callback' => [ $this, 'renderNotificationsContent' ],
            ];
        }

        if ( defined( 'ENABLE_CHAT' ) && ENABLE_CHAT ) {
            $endpoints['messages'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'Messages' ) : 'Messages',
                'callback' => [ $this, 'renderMessagesContent' ],
            ];
        }

        if ( ! ( defined( 'DISABLE_COMMENTS' ) && DISABLE_COMMENTS ) ) {
            $endpoints['reviews'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'My Reviews' ) : 'My Reviews',
                'callback' => [ $this, 'renderReviewsContent' ],
            ];
        }

        if ( defined( 'ENABLE_PASSWORD_RECOVER' ) && ENABLE_PASSWORD_RECOVER ) {
            $endpoints['security'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'Security' ) : 'Security',
                'callback' => [ $this, 'renderSecurityContent' ],
            ];
        }

        if ( defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION ) {
            $endpoints['not-activated'] = [
                'title'    => function_exists( 'trans' ) ? trans( 'Activation' ) : 'Activation',
                'callback' => [ $this, 'renderNotActivatedContent' ],
            ];
        }

        // Dışarıdan ekle/çıkar/değiştir
        $endpoints = apply_filters( 'sh_my_account_endpoints', $endpoints );

        // Her endpoint için my_account_custom_content_{endpoint} fonksiyonu tanımla
        foreach ( $endpoints as $slug => $config ) {
            $func_name = 'my_account_custom_content_' . str_replace( '-', '_', $slug );
            $callback  = $config['callback'] ?? null;

            if ( ! function_exists( $func_name ) && $callback ) {
                // PHP 7.4+ closure binding trick — fonksiyon adıyla global tanımla
                $title = $config['title'] ?? $slug;
                $cb    = $callback;
                $fn    = function() use ( $cb, $title, $slug ) {
                    call_user_func( $cb, $slug, $title );
                };
                // Global namespace'de fonksiyon tanımla
                \Closure::bind( $fn, null, null );
                // WC hook'una bağla
                add_action( 'woocommerce_account_' . $slug . '_endpoint', $fn );
            }
        }
    }

    // ─── Content Renderers ────────────────────────────────────────────────────────

    public function renderFavoritesContent( string $slug = 'favorites', string $title = '' ): void
    {
        if ( ! defined( 'ENABLE_REACTIONS' ) || ! ENABLE_REACTIONS ) return;

        // Yeni Reactions sistemi — favorite tipindeki post ID'lerini al
        $fav_ids = \SaltHareket\Reactions\Reactions::getByUser(
            get_current_user_id(),
            'favorite',
            'post'
        );

        // Fallback: eski wpcf_favorites user meta'sı (migration yapılmamışsa)
        if ( empty( $fav_ids ) ) {
            $legacy = get_user_meta( get_current_user_id(), 'wpcf_favorites', true );
            if ( $legacy ) {
                $decoded = is_array( $legacy ) ? $legacy : json_decode( $legacy, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    $fav_ids = array_map( 'intval', $decoded );
                }
            }
        }

        // Polylang aktifse: kayıtlı ID'ler default dilde — mevcut dildeki karşılıklarına çevir.
        // Çevirisi yoksa default dildeki ID'yi kullan (yani olduğu gibi bırak).
        if ( ! empty( $fav_ids ) && function_exists( 'pll_get_post' ) && function_exists( 'pll_current_language' ) ) {
            $current_lang = pll_current_language();
            $default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : $current_lang;
            if ( $current_lang !== $default_lang ) {
                $translated_ids = [];
                foreach ( $fav_ids as $id ) {
                    $tr_id = pll_get_post( $id, $current_lang );
                    $translated_ids[] = ( $tr_id && $tr_id > 0 ) ? $tr_id : $id;
                }
                $fav_ids = array_values( array_unique( $translated_ids ) );
            }
        }

        // Favorites'taki baskın post_type'ı tespit et
        $post_type = 'product';
        if ( ! empty( $fav_ids ) ) {
            $type_counts = [];
            foreach ( $fav_ids as $id ) {
                $pt = get_post_type( $id );
                if ( $pt && $pt !== 'revision' && $pt !== 'attachment' ) {
                    $pt = ( $pt === 'product_variation' ) ? 'product' : $pt;
                    $type_counts[ $pt ] = ( $type_counts[ $pt ] ?? 0 ) + 1;
                }
            }
            if ( ! empty( $type_counts ) ) {
                arsort( $type_counts );
                $post_type = array_key_first( $type_counts );
            }
        }

        // Bu post_type için post_pagination ayarlarını al
        $pagination_settings = function_exists( 'get_post_type_pagination' )
            ? get_post_type_pagination( $post_type )
            : [];

        // Fallback: Data::get boş geldiyse ACF'den direkt oku
        if ( empty( $pagination_settings ) && function_exists( 'get_field' ) ) {
            $raw = get_field( 'post_pagination', 'options' );
            if ( is_array( $raw ) ) {
                foreach ( $raw as $item ) {
                    if ( isset( $item['post_type'] ) && $item['post_type'] === $post_type ) {
                        $paged_on = ! empty( $item['paged'] );
                        $cols     = max( 1, (int) ( $item['catalog_columns'] ?? 3 ) );
                        $rows     = max( 1, (int) ( $item['catalog_rows'] ?? 2 ) );
                        $pagination_settings = [
                            'paged'           => $paged_on,
                            'type'            => $item['type'] ?? 'default',
                            'posts_per_page'  => $paged_on ? $cols * $rows : -1,
                            'catalog_columns' => $cols,
                            'catalog_rows'    => $rows,
                        ];
                        break;
                    }
                }
            }
        }

        $paged           = max( 1, (int) get_query_var( 'paged', 1 ) );
        $is_paged        = ! empty( $pagination_settings['paged'] );
        $posts_per_page  = $is_paged && ! empty( $pagination_settings['posts_per_page'] )
            ? (int) $pagination_settings['posts_per_page']
            : ( $is_paged ? 12 : -1 );
        $catalog_columns = (int) ( $pagination_settings['catalog_columns'] ?? 3 );

        // loop_shop_columns override — woo_archive_grid() için
        add_filter( 'loop_shop_columns', function() use ( $catalog_columns ) {
            return $catalog_columns;
        }, 999 );

        // loop_shop_per_page override
        add_filter( 'loop_shop_per_page', function() use ( $posts_per_page ) {
            return $posts_per_page;
        }, 999 );

        // Timber query — pagination nesnesi + ürün listesi
        $timber_posts = [];
        if ( ! empty( $fav_ids ) ) {
            $timber_posts = \Timber\Timber::get_posts( [
                'post_type'                     => [ 'product', 'product_variation' ],
                'posts_per_page'                => $posts_per_page,
                'paged'                         => $paged,
                'post__in'                      => $fav_ids,
                'orderby'                       => 'post__in',
                'no_found_rows'                 => false,
                'is_favorites'                  => true,
                'iconic_ssv_exclude_variations' => true,
                'xt_woovas_exclude'             => true,
            ] );
        }

        // WC loop HTML — ilk sayfa PHP'den render et, sonraki sayfalar AJAX'tan gelir
        $loop_html = '';
        if ( ! empty( $timber_posts ) && function_exists( 'wc_get_product' ) ) {
            global $wp_query, $woocommerce_loop;
            $original_query           = $wp_query;
            $woocommerce_loop['loop'] = 0;

            $GLOBALS['pae_tease_extra_ctx'] = [ 'variation_add_to_cart' => true ];

            ob_start();
            foreach ( $timber_posts as $timber_post ) {
                $wc_product = wc_get_product( $timber_post->ID );
                if ( ! $wc_product ) continue;
                if ( $wc_product->is_type( 'variation' ) ) {
                    $parent = wc_get_product( $wc_product->get_parent_id() );
                    if ( ! $parent || $parent->get_status() !== 'publish' ) continue;
                } elseif ( $timber_post->post_status !== 'publish' ) {
                    continue;
                }
                $GLOBALS['product'] = $wc_product;
                wc_setup_product_data( $timber_post->ID );
                $woocommerce_loop['loop']++;
                wc_get_template_part( 'content', 'product' );
            }
            wp_reset_postdata();
            $loop_html = ob_get_clean();

            $wp_query = $original_query;
            unset( $GLOBALS['pae_tease_extra_ctx'] );
        }

        // Pagination vars — pagination_ajax AJAX handler için
        $enc                   = class_exists( 'Encrypt' ) ? new \Encrypt() : null;
        $query_pagination_vars = '';
        if ( $enc && ! empty( $fav_ids ) ) {
            $query_pagination_vars = $enc->encrypt( [
                'post_type'                     => [ 'product', 'product_variation' ],
                'post__in'                      => $fav_ids,
                'orderby'                       => 'post__in',
                'posts_per_page'                => $posts_per_page,
                'paged'                         => 1,
                'is_favorites'                  => true,
                'is_woo_favorites'              => true,
                'iconic_ssv_exclude_variations' => true,
                'xt_woovas_exclude'             => true,
            ] );
        }

        // post_pagination context
        $post_pagination_ctx = class_exists( 'Data' ) ? ( \Data::get( 'post_pagination' ) ?: [] ) : [];
        if ( empty( $post_pagination_ctx[ $post_type ] ) ) {
            $post_pagination_ctx[ $post_type ] = [
                'paged'           => true,
                'type'            => $pagination_settings['type'] ?? 'default',
                'posts_per_page'  => $posts_per_page,
                'catalog_columns' => $catalog_columns,
                'catalog_rows'    => $pagination_settings['catalog_rows'] ?? 3,
            ];
        } elseif ( empty( $post_pagination_ctx[ $post_type ]['posts_per_page'] ) ) {
            $post_pagination_ctx[ $post_type ]['posts_per_page'] = $posts_per_page;
        }

        $context                              = \Timber\Timber::context();
        $context['type']                      = 'my-favorites';
        $context['title']                     = $title ?: ( function_exists( 'trans' ) ? trans( 'Favorites' ) : 'Favorites' );
        $context['post_type']                 = $post_type;
        $context['posts']                     = $timber_posts;
        $context['paged']                     = $paged;
        $context['favorites_empty']           = empty( $fav_ids );
        $context['loop_html']                 = $loop_html;
        $context['query_pagination_vars']     = $query_pagination_vars;
        $context['query_pagination_request']  = '';
        $context['post_pagination']           = $post_pagination_ctx;
        $context['variation_add_to_cart']     = true;

        \Timber\Timber::render( [ 'my-account/my-favorites.twig', 'my-account/favorites.twig' ], $context );
    }

    public function renderNotificationsContent( string $slug = 'notifications', string $title = '' ): void
    {
        if ( ! defined( 'ENABLE_NOTIFICATIONS' ) || ! ENABLE_NOTIFICATIONS ) return;

        $context          = \Timber\Timber::context();
        $context['type']  = 'notifications';
        $context['title'] = $title ?: ( function_exists( 'trans' ) ? trans( 'Notifications' ) : 'Notifications' );

        \Timber\Timber::render( [ 'my-account/notifications.twig' ], $context );
    }

    public function renderMessagesContent( string $slug = 'messages', string $title = '' ): void
    {
        if ( ! defined( 'ENABLE_CHAT' ) || ! ENABLE_CHAT ) return;

        $context          = \Timber\Timber::context();
        $context['type']  = 'messages';
        $context['title'] = $title ?: ( function_exists( 'trans' ) ? trans( 'Messages' ) : 'Messages' );

        \Timber\Timber::render( [ 'my-account/my-messages.twig' ], $context );
    }

    public function renderReviewsContent( string $slug = 'reviews', string $title = '' ): void
    {
        $user = \Data::get( 'user' );
        if ( ! $user ) return;

        $context             = \Timber\Timber::context();
        $context['type']     = 'reviews';
        $context['title']    = $title ?: ( function_exists( 'trans' ) ? trans( 'My Reviews' ) : 'My Reviews' );
        $context['statuses'] = [
            [ 'slug' => 'approved',        'name' => 'Approved',         'count' => $user->get_reviews_count( 1 ) ],
            [ 'slug' => 'waiting-approval','name' => 'Waiting Approval', 'count' => $user->get_reviews_count( 0 ) ],
        ];
        $context['action'] = get_query_var( 'reviews' ) ?: 'approved';

        \Timber\Timber::render( [ 'my-account/my-reviews.twig' ], $context );
    }

    public function renderSecurityContent( string $slug = 'security', string $title = '' ): void
    {
        $user = \Data::get( 'user' );
        if ( ! $user ) return;

        $templates = $user->get_status()
            ? [ 'my-account/security.twig' ]
            : [ 'my-account/not-activated.twig' ];

        $context          = \Timber\Timber::context();
        $context['type']  = 'security';
        $context['title'] = $title ?: ( function_exists( 'trans' ) ? trans( 'Security' ) : 'Security' );

        \Timber\Timber::render( $templates, $context );
    }

    public function renderNotActivatedContent( string $slug = 'not-activated', string $title = '' ): void
    {
        $context          = \Timber\Timber::context();
        $context['type']  = 'not-activated';
        $context['title'] = $title ?: ( function_exists( 'trans' ) ? trans( 'Activation' ) : 'Activation' );

        \Timber\Timber::render( [ 'my-account/not-activated.twig' ], $context );
    }

    // ─── Redirects & Access Control ──────────────────────────────────────────

    /**
     * Login zorunlu — giriş yapılmamışsa login sayfasına yönlendir.
     * Role kontrolü de yapabilir.
     *
     * @param array $req [['role' => 'expert', 'action' => ['edit']]]
     */
    public function loginRequired( array $req = [] ): void
    {
        if ( ! is_user_logged_in() ) {
            if ( isset( $_SESSION ) ) {
                $_SESSION['referer_url'] = function_exists( 'current_url' ) ? current_url() : ( $_SERVER['REQUEST_URI'] ?? '' );
            }
            wp_redirect( $this->getEndpointUrl( 'my-account' ) );
            exit();
        }

        if ( empty( $req ) || ! $this->user ) return;

        $role   = method_exists( $this->user, 'get_role' ) ? $this->user->get_role() : '';
        $action = get_query_var( 'action' );
        $index  = array_search( $role, array_column( $req, 'role' ) );

        if ( $index === false ) {
            wp_safe_redirect( $this->getEndpointUrl( 'profile' ) );
            exit();
        }

        if ( ! empty( $action ) && isset( $req[ $index ]['action'] ) ) {
            if ( ! in_array( $action, (array) $req[ $index ]['action'], true ) ) {
                wp_safe_redirect( $this->getEndpointUrl( 'profile' ) );
                exit();
            }
        }
    }

    /**
     * My-account endpoint'ine gelince profile'a yönlendir (aktif kullanıcı için).
     */
    public function redirectToProfile(): void
    {
        if ( ! is_user_logged_in() || ! $this->user ) return;

        $endpoint = $this->getCurrentEndpoint();
        if ( $endpoint === 'my-account' && $this->isUserActive( $this->user->ID ) ) {
            wp_safe_redirect( $this->getEndpointUrl( 'profile' ) );
            exit();
        }
    }

    /**
     * Aktive edilmemiş kullanıcıyı not-activated sayfasına yönlendir.
     */
    public function redirectIfNotActivated(): void
    {
        if ( ! is_user_logged_in() || ! $this->user ) return;
        if ( ! self::isActivationRequired() ) return;

        $endpoint = $this->getCurrentEndpoint();
        $active   = $this->isUserActive( $this->user->ID );

        if ( ! $active && $endpoint !== 'not-activated' ) {
            wp_safe_redirect( $this->getEndpointUrl( 'not-activated' ) );
            exit();
        }
        if ( $active && $endpoint === 'not-activated' ) {
            wp_safe_redirect( $this->getEndpointUrl( 'profile' ) );
            exit();
        }
    }

    /**
     * Profili tamamlanmamış kullanıcıyı yönlendir.
     */
    public function redirectIfNotCompleted(): void
    {
        if ( ! is_user_logged_in() || ! $this->user ) return;

        $endpoint             = $this->getCurrentEndpoint( 'my-account' );
        $endpoint             = empty( $endpoint ) ? get_query_var( 'pagename' ) : $endpoint;
        $restricted_endpoints = apply_filters( 'sh_profile_required_endpoints', [ 'sessions', 'messages', 'financials' ] );

        $active    = $this->isUserActive( $this->user->ID );
        $completed = (bool) get_user_meta( $this->user->ID, 'profile_completed', true );

        if ( $active && ! $completed && in_array( $endpoint, $restricted_endpoints, true ) ) {
            wp_safe_redirect( $this->getEndpointUrl( 'not-completed' ) );
            exit();
        }
        if ( $active && $completed && $endpoint === 'not-completed' ) {
            wp_safe_redirect( $this->getEndpointUrl( 'profile' ) );
            exit();
        }
    }

    // ─── Notification Count ──────────────────────────────────────────────────

    /**
     * Okunmamış notification sayısı.
     */
    public function getNotificationCount(): int
    {
        if ( ! defined( 'ENABLE_NOTIFICATIONS' ) || ! ENABLE_NOTIFICATIONS ) return 0;

        global $wpdb;
        $user_id = $this->user ? (int) $this->user->ID : get_current_user_id();
        if ( ! $user_id ) return 0;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}notifications
             WHERE receiver_id = %d AND status = 'unread' AND channel = 'alert'",
            $user_id
        ) );
    }

    // ─── Duplicate Page Fix ──────────────────────────────────────────────────

    /**
     * WooCommerce aktif/deaktif olduğunda my-account sayfasını yönet.
     * MembershipHooks::registerPageManagement()'dan çağrılır.
     */
    public static function registerPageManagementHooks(): void
    {
        add_action( 'activated_plugin', [ static::class, 'onWooActivated' ] );
        add_action( 'deactivated_plugin', [ static::class, 'onWooDeactivated' ] );
    }

    /**
     * WooCommerce aktif olduğunda:
     * 1. Duplicate my-account sayfalarını temizle
     * 2. options_myaccount_page_id → WC'nin sayfasına yönlendir
     */
    public static function onWooActivated( string $plugin ): void
    {
        if ( strpos( $plugin, 'woocommerce' ) === false ) return;

        // WC henüz tam yüklenmemiş olabilir — gecikmeli çalıştır
        add_action( 'init', function () {
            if ( ! function_exists( 'wc_get_page_id' ) ) return;

            $wc_page_id = wc_get_page_id( 'myaccount' );

            // WC sayfası yoksa veya geçersizse — WC kendi oluşturacak, biz karışmayalım
            if ( $wc_page_id < 1 ) return;

            // Duplicate sayfaları temizle
            self::cleanupDuplicatePages( $wc_page_id );

            // options_myaccount_page_id → WC sayfasına
            update_option( 'options_myaccount_page_id', $wc_page_id );

        }, 20 );
    }

    /**
     * WooCommerce deaktif olduğunda:
     * options_myaccount_page_id → kendi sayfamıza geri al.
     */
    public static function onWooDeactivated( string $plugin ): void
    {
        if ( strpos( $plugin, 'woocommerce' ) === false ) return;

        // Kendi my-account sayfamızı bul
        $our_page = get_pages( [
            'post_status' => 'publish',
            'meta_key'    => '_sh_myaccount_page',
            'meta_value'  => '1',
            'number'      => 1,
        ] );

        if ( ! empty( $our_page ) ) {
            update_option( 'options_myaccount_page_id', $our_page[0]->ID );
            return;
        }

        // Yoksa slug'a göre bul
        $page = get_page_by_path( 'my-account' );
        if ( $page ) {
            update_option( 'options_myaccount_page_id', $page->ID );
        }
    }

    /**
     * Duplicate my-account sayfalarını temizle.
     * En eski sayfayı koru, diğerlerini trash'e taşı.
     */
    private static function cleanupDuplicatePages( int $keep_id ): void
    {
        $pages = get_posts( [
            'post_type'      => 'page',
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'name'           => 'my-account',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        if ( count( $pages ) <= 1 ) return;

        foreach ( $pages as $page ) {
            if ( $page->ID === $keep_id ) continue;
            wp_trash_post( $page->ID );
            error_log( '[Membership] Duplicate my-account page trashed: #' . $page->ID );
        }
    }

    /**
     * Kendi my-account sayfamızı oluştur (yoksa).
     * Theme setup'ta çağrılır.
     */
    public static function ensureMyAccountPage(): int
    {
        $existing_id = (int) get_option( 'options_myaccount_page_id', 0 );

        if ( $existing_id > 0 && get_post_status( $existing_id ) === 'publish' ) {
            return $existing_id;
        }

        // Slug'a göre ara
        $page = get_page_by_path( 'my-account' );
        if ( $page && $page->post_status === 'publish' ) {
            update_option( 'options_myaccount_page_id', $page->ID );
            update_post_meta( $page->ID, '_sh_myaccount_page', '1' );
            return $page->ID;
        }

        // Oluştur
        $page_id = wp_insert_post( [
            'post_title'  => 'My Account',
            'post_name'   => 'my-account',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ] );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( 'options_myaccount_page_id', $page_id );
            update_post_meta( $page_id, '_sh_myaccount_page', '1' );
            return $page_id;
        }

        return 0;
    }
}
