<?php

use SaltHareket\Theme;

/**
 * StarterSite
 *
 * Timber context, Twig extension ve filter/function registrations.
 * add_to_context() — her sayfada Twig'e aktarılan global değişkenler.
 *
 * @version 2.2.0
 *
 * @changelog
 *   2.2.0 - 2026-08-21
 *     - Add: resolve_header() — header template post'u Timber::compile() ile render eder.
 *            parse_blocks() ile header-builder block'undan fixed/affix/hide_on_scroll okunur,
 *            body class'larına yansıtılır. $header_post_content Timber::compile() ÖNCE
 *            kaydedilir (global $post timing bug fix).
 *     - Add: resolve_footer() — footer template post sistemi.
 *     - Add: salt_set_block_context() iki kez çağrılır: header resolve öncesi (block render
 *            için context inject) ve sonrası (header_options eklendikten sonra güncelleme).
 *     - Fix: add_to_context end Profiler mark'ı artık resolve_header/footer'dan SONRA atılıyor.
 *     - Remove: header_footer_options() deprecated comment bloğu temizlendi.
 *     - Remove: header_footer_options QueryCache wrap kaldırıldı.
 *   2.1.0 - 2026-07-04
 *     - Add: resolve_header() static method — header template post render + fixed/affix
 *     - Add: resolve_footer() static method — footer template post render
 *     - Add: normalizePageSettings() — page_settings normalizasyonu
 *   2.0.0 - 2026-05-16
 *     - Refactor: Monolithic add_to_context() modüler yapıya taşındı
 */
class StarterSite extends Timber\Site {

    private bool $is_admin          = false;
    private bool $is_user_logged_in = false;

    function __construct() {
        $this->is_admin          = is_admin();
        $this->is_user_logged_in = is_user_logged_in();

        add_filter( 'timber/context',              [ $this, 'add_to_context' ] );
        add_filter( 'timber/twig',                 [ $this, 'add_to_twig' ] );
        add_filter( 'timber/twig/filters',         [ $this, 'timber_twig_filters' ] );
        add_filter( 'timber/twig/functions',       [ $this, 'timber_twig_functions' ] );
        add_filter( 'timber/post/image_extensions',[ $this, 'timber_post_image_extensions' ] );
        add_action( 'timber/output',               [ $this, 'timber_output' ], 2, 9999 );

        parent::__construct();
    }

    // ─── Context ─────────────────────────────────────────────────────────────

    function add_to_context( $context ) {

        \SaltProfiler::mark( 'add_to_context start' );

        $ajax_process = ( defined('DOING_AJAX') && DOING_AJAX ) || ( defined('DOING_CRON') && DOING_CRON );
        $salt         = Data::get( 'salt' );
        $user         = Data::get( 'user' );

        // ── Temel flagler ─────────────────────────────────────────────────────
        $context['ajax_process']  = $ajax_process;
        $context['is_fetch']      = IS_INTERNAL_FETCH;
        $context['is_admin']      = is_admin();
        $context['is_home']       = boolval( is_front_page() );
        $context['block_appendix']= [];
        

        // ── Feature flag'leri ─────────────────────────────────────────────────
        $context['enable_membership']            = boolval( ENABLE_MEMBERSHIP );
        $context['enable_membership_activation'] = boolval( ENABLE_MEMBERSHIP_ACTIVATION );
        $context['membership_activation_type']   = MEMBERSHIP_ACTIVATION_TYPE;
        $context['enable_reactions']             = boolval( ENABLE_REACTIONS );
        $context['enable_cart']                  = boolval( ENABLE_CART );
        $context['enable_filters']               = boolval( ENABLE_FILTERS );

        // ── Product Card (tease.twig) ayarları ────────────────────────────────
        $context['tease_multiple_images']   = TEASE_MULTIPLE_IMAGES;
        $context['tease_show_variations']   = TEASE_SHOW_VARIATIONS;
        $context['tease_swatch_tooltip']    = TEASE_SWATCH_TOOLTIP;
        $context['tease_show_oos_swatches'] = TEASE_SHOW_OOS_SWATCHES;
        $context['tease_swatch_limit']      = TEASE_SWATCH_LIMIT;
        $context['tease_show_sale_badge']   = TEASE_SHOW_SALE_BADGE;
        $context['tease_show_feat_badge']   = TEASE_SHOW_FEAT_BADGE;
        $context['tease_show_oos_badge']    = TEASE_SHOW_OOS_BADGE;
        $context['tease_show_category']     = TEASE_SHOW_CATEGORY;
        $context['tease_show_price']        = TEASE_SHOW_PRICE;
        $context['tease_img_ratio']         = TEASE_IMG_RATIO;
        $context['tease_default_size']      = TEASE_DEFAULT_SIZE;
        $context['tease_bundle_cta']        = TEASE_BUNDLE_CTA;

        // ── Product Gallery (single-product) ayarları ─────────────────────────
        $context['gallery_type']             = GALLERY_TYPE;
        $context['gallery_ratio']            = GALLERY_RATIO;
        $context['gallery_object_fit']       = GALLERY_OBJECT_FIT;
        $context['gallery_hover']            = GALLERY_HOVER;
        $context['gallery_lightbox']         = GALLERY_LIGHTBOX;
        $context['gallery_navigation']       = GALLERY_NAVIGATION;
        $context['gallery_loop']             = GALLERY_LOOP;
        $context['gallery_thumbs']           = GALLERY_THUMBS;
        $context['gallery_thumb_nav']        = GALLERY_THUMB_NAV;
        $context['gallery_variation_update'] = GALLERY_VARIATION_UPDATE;
        $context['gallery_pagination']       = GALLERY_PAGINATION;
        $context['gallery_slides_per_view']  = GALLERY_SLIDES_PER_VIEW;
        $context['gallery_thumb_count']      = GALLERY_THUMB_COUNT;
        $context['gallery_stack_gap']        = GALLERY_STACK_GAP;
        $context['enable_chat']                  = boolval( ENABLE_CHAT );
        $context['enable_notifications']         = boolval( ENABLE_NOTIFICATIONS );
        $context['enable_sms_notifications']     = boolval( ENABLE_NOTIFICATIONS ) && boolval( ENABLE_SMS_NOTIFICATIONS );
        $context['enable_web_push']              = boolval( ENABLE_NOTIFICATIONS ) && boolval( ENABLE_WEB_PUSH );
        $context['enable_search_history']        = boolval( ENABLE_SEARCH_HISTORY );
        $context['enable_reviews']               = defined( 'ENABLE_REVIEWS' ) && boolval( ENABLE_REVIEWS );
        $context['disable_review_approve']       = boolval( DISABLE_REVIEW_APPROVE );
        $context['enable_postcode_validation']   = boolval( ENABLE_POSTCODE_VALIDATION );
        $context['enable_regional_posts']        = boolval( ENABLE_REGIONAL_POSTS );
        $context['enable_ecommerce']             = boolval( ENABLE_ECOMMERCE );
        $context['enable_registration']          = boolval( ENABLE_REGISTRATION );
        $context['enable_remember_login']        = boolval( ENABLE_REMEMBER_LOGIN );
        $context['enable_public_profile']        = (bool) \SaltHareket\Membership\MembershipSettings::getSetting( 'enable_public_profile', false );
        $context['enable_lost_password']         = boolval( ENABLE_LOST_PASSWORD );
        $context['enable_password_recover']      = boolval( ENABLE_PASSWORD_RECOVER );
        $context['enable_social_login']          = boolval( ENABLE_SOCIAL_LOGIN );
        $context['seperate_css']                 = boolval( SEPERATE_CSS );
        $context['seperate_js']                  = boolval( SEPERATE_JS );
        $context['woocommerce']                  = boolval( ENABLE_ECOMMERCE );
        // Rol seçimi — signup formunda kullanıcı rol seçebilir
        // enable_role_selection: true ise VE en az 2 seçilebilir rol varsa
        // false ise veya 1 rol varsa gösterme — seçim anlamsız olur
        $selectable_slugs = [];
        $_role_sel_enabled = (bool) \SaltHareket\Membership\MembershipSettings::getSetting( 'enable_role_selection', false );
        if ( $_role_sel_enabled ) {
            $slugs     = (array) \SaltHareket\Membership\MembershipSettings::getSetting( 'selectable_roles', [] );
            $all_roles = wp_roles()->roles;
            $built     = [];
            foreach ( $slugs as $slug ) {
                if ( isset( $all_roles[ $slug ] ) ) {
                    $built[] = [
                        'slug'  => $slug,
                        'label' => translate_user_role( $all_roles[ $slug ]['name'] ),
                    ];
                }
            }
            $selectable_slugs = $built;
        }
        // Gerçek flag: aktif + en az 2 rol
        $context['enable_role_selection'] = $_role_sel_enabled && count( $selectable_slugs ) >= 2;
        $context['selectable_roles']      = $selectable_slugs;
        // Login Security flags — form-login-native/woo, security.twig için
        $context['login_2fa_method']             = defined('LOGIN_2FA_METHOD') ? LOGIN_2FA_METHOD : 'none';
        $context['enable_2fa_sms']               = defined('ENABLE_2FA_SMS')   && ENABLE_2FA_SMS;
        $context['enable_2fa_totp']              = defined('ENABLE_2FA_TOTP')  && ENABLE_2FA_TOTP;
        $context['enable_passwordless']          = defined('ENABLE_PASSWORDLESS') && ENABLE_PASSWORDLESS;
        $context['trusted_device_days']          = (int) \SaltHareket\Membership\MembershipSettings::getSetting( 'trusted_device_days', 30 );
        $context['enable_sms']                   = defined('ENABLE_SMS') && ENABLE_SMS;
        // SMS OTP ayarları — kutucuk sayısı, countdown, max resend
        $sms_settings = \SaltHareket\Sms\SmsSettings::get();
        $context['otp_length']   = (int) ( $sms_settings['otp_length']  ?? 6 );
        $context['otp_expiry']   = (int) ( $sms_settings['otp_expiry']  ?? 300 );
        $context['otp_max_resend'] = (int) ( $sms_settings['max_resend'] ?? 5 );


        // ── Maintenance ───────────────────────────────────────────────────────
        $context['maintenance_mode'] = boolval( MAINTENANCE_MODE );
        if ( ! defined( 'MAINTENANCE_VISIBLE' ) ) {
            maintenance_check_visibility();
        }
        $context['maintenance_visible'] = boolval( MAINTENANCE_VISIBLE );

        // ── Text domain ───────────────────────────────────────────────────────
        $context['text_domain'] = TEXT_DOMAIN;

        // ── Menüler ───────────────────────────────────────────────────────────
        $menus = [];
        if ( ! is_admin() ) {
            foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
                if ( has_nav_menu( $location ) ) {
                    $menus[ $location ] = QueryCache::get_cached_menu( $location );
                }
            }
        }
        $context['menu'] = $menus;
        \SaltProfiler::mark( 'add_to_context: after menu cache' );

        // ── Upload URL ────────────────────────────────────────────────────────
        $upload_dir       = wp_upload_dir();
        $this->upload_url = $upload_dir['baseurl'] . '/';
        if ( ! Data::has( '_upload_url' ) ) {
            Data::set( '_upload_url', $this->upload_url );
        }

        // ── Site meta ─────────────────────────────────────────────────────────
        $context['site']              = $this;
        $context['current_page_type'] = get_current_page_type();
        $context['page_type']         = get_page_type();

        // ── İletişim / Logo ───────────────────────────────────────────────────
        if ( function_exists( 'acf_get_contacts' ) ) {
            $lang = Data::get( 'language', 'tr' );
            $contact_main = QueryCache::wrap(
                'acf_contacts_main_' . $lang,
                fn() => acf_get_contacts( 'main' ),
                [ 'post_type' => [ 'options' ] ]
            );
            $context['contact']  = $contact_main;
            $context['accounts'] = QueryCache::wrap(
                'acf_accounts_main_' . $lang,
                fn() => acf_get_accounts( $contact_main ),
                [ 'post_type' => [ 'options' ] ]
            );
        }

        foreach ( [ 'logo', 'logo_affix', 'logo_mobile', 'logo_mobile_breakpoint', 'logo_footer', 'logo_icon', 'logo_marker', 'logo_not_found' ] as $field ) {
            $val = \SaltHareket\SaltConfig::get( 'options_' . $field );
            // Eğer SaltConfig'de yoksa (henüz cache güncellenmemişse) QueryCache fallback
            if ( $val === false && class_exists( 'QueryCache' ) ) {
                $val = QueryCache::get_field( $field, 'options' );
            }
            $context[ $field ] = $val;
        }

        // ── Header / Footer ───────────────────────────────────────────────────
        if ( ! $this->is_admin && class_exists( 'ACF' ) && ! $ajax_process ) {
            \SaltProfiler::mark( 'add_to_context: header_footer start' );
            $lang = Data::get( 'language', 'tr' );

            $context['theme_styles'] = QueryCache::wrap(
                'theme_styles_' . $lang,
                fn() => class_exists( 'Theme_Styles' ) ? Theme_Styles::init()->get_context_data() : [],
                [ 'post_type' => [ 'options' ] ]
            );
            \SaltProfiler::mark( 'add_to_context: header_footer end' );
        }


        // ── Arama geçmişi ─────────────────────────────────────────────────────
        if ( ENABLE_SEARCH_HISTORY ) {
            $context['search_history'] = Data::get( 'search_history' );
        }

        // ── Aktif plugin listesi (static cache) ───────────────────────────────
        static $active_plugins_cache = null;
        if ( $active_plugins_cache === null ) {
            $active_plugins_cache = [];
            foreach ( get_option( 'active_plugins', [] ) as $plugin_path ) {
                $slug = strpos( $plugin_path, '/' ) !== false
                    ? explode( '/', $plugin_path )[0]
                    : basename( $plugin_path, '.php' );
                $active_plugins_cache[ $slug ] = true;
            }
        }
        $context['active_plugins'] = $active_plugins_cache;

        // WC 11.1+ variation galleries core'a taşındı — plugin slug artık güvenilmez
        // woo/tease.twig'de multiple_images koşulu için bu flag kullanılır
        $context['wc_has_variation_galleries'] = defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE
            && (
                isset( $active_plugins_cache['woocommerce-additional-variation-images'] )
                || ( function_exists('WC') && version_compare( WC()->version, '11.1', '>=' ) )
            );

        // ── Avatar (üyelik) ───────────────────────────────────────────────────
        if ( ENABLE_MEMBERSHIP && $this->is_user_logged_in && $user && is_object( $user ) ) {
            $context['avatar']     = method_exists( $user, 'get_avatar' )     ? $user->get_avatar()     : '';
            $context['avatar_url'] = str_replace(
                'wp_user_avatar', 'mm',
                method_exists( $user, 'get_avatar_url' ) ? $user->get_avatar_url() : ''
            );
        }

        // ── Dil ───────────────────────────────────────────────────────────────
        $context['language'] = Data::get( 'language' );
        if ( ENABLE_MULTILANGUAGE ) {
            $context['languages']         = Data::get( 'languages', [] );
            $context['language_default']  = Data::get( 'language_default', '' );
            if ( ENABLE_MULTILANGUAGE === 'polylang' ) {
                $context['site']->url = pll_home_url( Data::get( 'language' ) );
            }
        }

        // ── Frontend-only (admin hariç) ───────────────────────────────────────
        if ( ! $ajax_process ) {

            // Breadcrumb
            if ( function_exists( 'yoast_breadcrumb' ) && ! empty( get_option( 'wpseo_titles', [] )['breadcrumbs-enable'] ) ) {
                $breadcrumb = WPSEO_Breadcrumbs::breadcrumb( '', '', 0 );
                if ( $breadcrumb ) {
                    $breadcrumb = yoast_breadcrumb(
                        '<div class="breadcrumb-container {{breadcrumb_count}} {{class}}">',
                        '{{breadcrumb_h1}}</div>',
                        1
                    );
                    $breadcrumb = str_replace( '{{breadcrumb_count}}', Data::get( 'breadcrumb_count', 0 ) > 0 ? 'has-items' : '', $breadcrumb );
                    $breadcrumb = str_replace( '{{breadcrumb_h1}}',    Data::get( 'breadcrumb_h1', '' ), $breadcrumb );
                } elseif ( Data::get( 'breadcrumb_h1' ) ) {
                    $breadcrumb = '<div class="breadcrumb-container">' . Data::get( 'breadcrumb_h1' ) . '</div>';
                }
                $context['breadcrumb'] = $breadcrumb ?? '';
            }

            // URL helpers
            if ( Data::get( 'url_query_vars' ) ) {
                $context['url_query_vars'] = [];
                foreach ( Data::get( 'url_query_vars' ) as $var ) {
                    $context['url_query_vars'][ $var ] = get_query_var( $var );
                }
            }
            $context['querystring']              = json_decode( queryStringJSON(), true );
            $context['endpoint']                 = getUrlEndpoint();
            $context['url_parts']                = getUrlParts();
            $context['base_urls']                = Data::get( 'base_urls', [] );
            $context['breakpoints']              = Data::get( 'breakpoints', [] );

            // Posta kodları
            if ( ENABLE_POSTCODE_VALIDATION ) {
                $context['postcodes'] = json_decode( file_get_contents( SH_STATIC_PATH . 'data/postcodes.json' ), true );
            }

            // Post ve page_settings
            global $post, $wp, $wp_query;


            if ( ENABLE_ECOMMERCE && ( is_shop() || is_product_category() || is_tax() ) ) {
                $post = get_post( get_option( 'woocommerce_shop_page_id' ) );
            }
            $post = Timber::get_post( $post );
            if ( is_home() ) {
                $post = Timber::get_post( get_option( 'page_for_posts' ) );
            }

            $page_settings = [];

            // ── Page Settings (ACF-free) ──────────────────────────────────────
            // Öncelik: post-level → post_type/taxonomy options → boş defaults
            $page_settings = \SaltHareket\PageSettings\PageSettings::resolve( $post );
            if ( $page_settings ) {
                $context['page_settings'] = $page_settings;
            }

            // Current URL (sayfalama temizleme)
            $current_url  = current_url();
            $query_string = add_query_arg( [], $wp->request );
            if ( $query_string && strpos( $query_string, 'page/' ) !== false ) {
                [ , $after ] = explode( 'page/', $query_string, 2 );
                $current_url = str_replace( 'page/' . $after . '/', '', $current_url );
            }
            $context['current_url'] = $current_url;

            // Sayfalama
            $paged = max( 1, intval( $wp_query->query_vars['paged'] ?? 1 ) );
            $context['paged'] = $paged;
            $pagination_query = pagination_query();
            $context['query_pagination_vars']    = $pagination_query['vars'];
            $context['query_pagination_request'] = $pagination_query['request'];


            // ── Hero block + search-results posts_per_page ────────────────────
            $block_search_results_posts_per_page = 10;

            $login_blocked = ! IS_INTERNAL_FETCH
                && ! empty( $page_settings['login_required'] )
                && $page_settings['login_required']
                && (
                    ! ( $user->logged ?? false )
                    || (
                        is_array( $page_settings['allowed_roles'] ?? null )
                        && $user
                        && method_exists( $user, 'get_role' )
                        && ! in_array( $user->get_role(), $page_settings['allowed_roles'], true )
                    )
                );

            if ( ! $login_blocked && ( is_single() || is_page() || is_home() || is_tag() || is_category() ) && ! $ajax_process ) {

                $hero_title  = '';
                $block_hero  = '';
                $post_blocks = [];
                Data::set( 'block_index', -1 );

                if ( is_tag() || is_category() ) {
                    $posts_page_id = intval( get_option( 'page_for_posts' ) );
                    if ( $posts_page_id ) {
                        $post_blocks        = \QueryCache::is_enabled()
                            ? \QueryCache::wrap( 'blocks_' . $posts_page_id, fn() => get_blocks( $posts_page_id ), [ 'post_id' => [ $posts_page_id ] ] )
                            : get_blocks( $posts_page_id );
                        $context['page']    = Timber::get_post( $posts_page_id );
                        $hero_title         = single_tag_title( '', false );
                    }
                } elseif ( isset( $post->ID ) ) {
                    $post_blocks = \QueryCache::is_enabled()
                        ? \QueryCache::wrap( 'blocks_' . $post->ID, fn() => get_blocks( $post->ID ), [ 'post_id' => [ (int) $post->ID ] ] )
                        : get_blocks( $post->ID );
                }

                if ( $post_blocks ) {
                    foreach ( $post_blocks as $key => $block ) {
                        if ( ( $block['attrs']['data']['block_settings_hero'] ?? '' ) == 1 ) {
                            if ( $hero_title )              $block['attrs']['data']['title']      = $hero_title;
                            if ( isset( $context['breadcrumb'] ) ) $block['attrs']['data']['breadcrumb'] = $context['breadcrumb'];
                            $block['attrs']['id'] = 'block_hero';
                            $block_hero           = $block;
                            Data::set( 'block_index', $key );
                            break;
                        }
                    }
                    foreach ( $post_blocks as $block ) {
                        if ( $block['blockName'] === 'acf/search-results' && isset( $block['attrs']['data']['posts_per_page'] ) ) {
                            $block_search_results_posts_per_page = $block['attrs']['data']['posts_per_page'];
                            break;
                        }
                    }
                }
                if ( $block_hero ) {
                    $context['block_hero'] = $block_hero;
                }
            }

            // ── Search / Tag / Category sorgusu ───────────────────────────────
            if ( ! empty( get_query_var( 'q' ) ) || is_tag() || is_category() || is_home() ) {
                if ( is_tag() ) {
                    $context['title'] = sprintf( trans( 'Posts tagged as %s' ), '<strong>"' . single_tag_title( '', false ) . '"</strong>' );
                } elseif ( is_category() ) {
                    $context['title'] = sprintf( trans( 'Posts in %s category' ), '<strong>"' . get_queried_object()->name . '"</strong>' );
                } elseif ( ! empty( get_query_var( 'q' ) ) ) {
                    $context['title'] = sprintf( trans( '%s için arama sonuçları' ), '<strong>"' . get_query_var( 'q' ) . '"</strong>' );
                }

                $found_posts = 0;
                if ( ! empty( get_query_var( 'q' ) ) ) {
                    $qpt = get_query_var( 'qpt' );
                    $qpt = ( empty( $qpt ) || $qpt === 'search' || is_numeric( $qpt ) ) ? 'any' : $qpt;
                    if ( EXCLUDE_FROM_SEARCH && $qpt === 'any' ) {
                        $post_types = get_post_types( [ 'public' => true ], 'names' );
                        foreach ( EXCLUDE_FROM_SEARCH as $excluded ) {
                            unset( $post_types[ $excluded ] );
                        }
                        $qpt = $post_types;
                    }
                    $posts = Timber::get_posts( [
                        'post_type'      => $qpt,
                        's'              => urldecode( get_query_var( 'q' ) ),
                        'paged'          => $paged,
                        'posts_per_page' => $block_search_results_posts_per_page,
                    ] );
                    $context['posts'] = $posts;
                    $found_posts      = $posts->found_posts;
                }
                $context['found_posts'] = $found_posts;
            }

        } // end !$ajax_process


        // ── Post pagination ───────────────────────────────────────────────────
        $context['post_pagination'] = Data::get( 'post_pagination', [] );

        // ── Site assets (JS inline + LCP) ─────────────────────────────────────
        if ( defined( 'SITE_ASSETS' ) && is_array( SITE_ASSETS ) ) {
            if ( ! empty( SITE_ASSETS['js'] ) ) {
                $js_code = str_replace(
                    [ '{upload_url}', '{home_url}' ],
                    [ $this->upload_url, home_url( '/' ) ],
                    SITE_ASSETS['js']
                );
                $context['site_assets_js'] = $js_code;
            }
            $lcp_data   = SITE_ASSETS['lcp'] ?? [];
            $lcp_images = [];
            foreach ( [ 'desktop', 'mobile' ] as $device ) {
                $lcp = $lcp_data[ $device ] ?? [];
                if ( ! empty( $lcp['type'] ) && in_array( $lcp['type'], [ 'image', 'video', 'bg-image' ], true ) && ! empty( $lcp['id'] ) ) {
                    $lcp_images[] = $lcp['id'];
                }
            }
            $context['lcp_images'] = $lcp_images;
        }

        // ── CF7 formları ──────────────────────────────────────────────────────
        $forms = [];
        if ( class_exists( 'WPCF7' ) ) {
            $has_form = defined( 'SITE_ASSETS' )
                && is_array( SITE_ASSETS['wp_js'] ?? null )
                && ( in_array( 'contact-form-7', SITE_ASSETS['wp_js'], true ) || in_array( 'contact_form', SITE_ASSETS['wp_js'], true ) );
            if ( $has_form ) {
                $forms = get_cf7_forms();
            }
        }
        $context['forms'] = $forms;

        // ── JS files manifest ─────────────────────────────────────────────────
        $plugins_file = get_stylesheet_directory() . '/static/js/js_files_all.json';
        $main_css_mt  = defined( 'STATIC_PATH' ) && file_exists( STATIC_PATH . 'css/main.css' )
            ? filemtime( STATIC_PATH . 'css/main.css' )
            : 0;
        $context['all_js'] = \QueryCache::wrap(
            'js_files_all_json_' . $main_css_mt,
            static function() use ( $plugins_file ) {
                return file_exists( $plugins_file )
                    ? ( json_decode( file_get_contents( $plugins_file ), true ) ?: [] )
                    : [];
            }
        );

        // ── Fetch flag ────────────────────────────────────────────────────────
        $context['fetch'] = isset( $_GET['fetch'] ) || ( ! SEPERATE_CSS && ! SEPERATE_JS );

        // ── Salt / User ───────────────────────────────────────────────────────
        $context['salt'] = $salt;
        $context['user'] = $user;

        // ── Header / Footer resolve ───────────────────────────────────────────
        // resolve_header ve resolve_footer page_settings'ten çalışır.
        // Footer önce resolve edilir — header render sırasında _salt_block_context'e ihtiyaç var.

        // Footer resolve
        $resolved_footer = self::resolve_footer( $context['page_settings'] ?? [] );
        $context['footer_options'] = [ 'resolved' => $resolved_footer ];

        // Header render sırasında block'lar context'e ihtiyaç duyar — önceden inject et
        if ( function_exists( 'salt_set_block_context' ) ) {
            salt_set_block_context( $context );
        } else {
            $GLOBALS['_salt_block_context'] = $context;
        }

        // Header resolve — _salt_block_context dolu olduğunda çalışır
        $resolved_header = self::resolve_header( $context['page_settings'] ?? [] );
        $context['header_options'] = [ 'resolved' => $resolved_header ?: null ];

        // header_options eklendikten sonra context'i güncelle
        if ( function_exists( 'salt_set_block_context' ) ) {
            salt_set_block_context( $context );
        } else {
            $GLOBALS['_salt_block_context'] = $context;
        }

        \SaltProfiler::mark( 'add_to_context end' );

        // ── Reviews context — single product ve user profili için ────────────
        if (
            defined( 'ENABLE_REVIEWS' ) && ENABLE_REVIEWS &&
            class_exists( '\SaltHareket\Reviews\Reviews' ) &&
            ! $ajax_process
        ) {
            if ( is_singular( 'product' ) && defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) {
                $post_id   = get_the_ID();
                $post_type = 'product';
                $type_cfg  = \SaltHareket\Reviews\ReviewsSettings::getTypeConfig( 'post', $post_type );

                if ( ! empty( $type_cfg['enabled'] ) ) {
                    $reviews_obj = new \SaltHareket\Reviews\Reviews();
                    $context['review_type_config'] = $type_cfg;
                    $context['can_review_type']    = true;
                    $context['review_modes']       = $type_cfg['modes'] ?? [ 'rate', 'comment' ];
                    $context['reviews_settings']   = \SaltHareket\Reviews\ReviewsSettings::all();
                    $context['reviews_criteria']   = \SaltHareket\Reviews\ReviewsSettings::getCriteria( $post_type );
                    $context['rating_type']        = $context['reviews_settings']['rating']['type'] ?? 'stars';
                    $context['has_reviewed']       = is_user_logged_in()
                        ? $reviews_obj->has_reviewed( get_current_user_id(), $post_id, 'post' )
                        : false;
                    $context['can_review']         = is_user_logged_in()
                        ? $reviews_obj->can_review( get_current_user_id(), $post_id, 'post' )
                        : false;
                    $reviews_result                = $reviews_obj->getForPost( $post_id, [ 'per_page' => 10 ] );
                    $context['reviews']            = $reviews_result['reviews'] ?? [];
                    $context['reviews_data']       = $reviews_result['data']    ?? [];
                    $context['reviews_stats']      = \SaltHareket\Reviews\Reviews::stats( $post_id, 'post' );
                    $context['reviews_post_type']  = $post_type;
                }
            }
        }

        return $context;
    }

    // ─── Yardımcı metodlar ────────────────────────────────────────────────────

    /**
     * page_settings array'ini normalize eder.
     * body/main/container class'larını implode eder, offcanvas varsa container'ı block_container'a çevirir.
     */
    private function normalizePageSettings( array $ps ): array {
        $ps['classes']['body']      = implode( ' ', (array) ( $ps['classes']['body']      ?? [] ) );
        $ps['classes']['main']      = implode( ' ', (array) ( $ps['classes']['main']      ?? [] ) );
        $ps['classes']['container'] = block_container( $ps['classes']['container'] ?? [] );
        return $ps;
    }


    // ─── Twig ─────────────────────────────────────────────────────────────────

    function add_to_twig( $twig ) {
        if ( ! class_exists( '\App\Twig\AppExtension' ) ) {
            @include_once SH_INCLUDES_PATH . 'plugins/twig.php';
        }
        $twig->addExtension( new \App\Twig\AppExtension() );

        try {
            $twig->addFunction( new \Twig\TwigFunction(
                'cached_posts',
                static function( $query = [] ) {
                    if ( ! is_array( $query ) ) $query = [ 'post_type' => $query ];
                    return class_exists( 'QueryCache' ) && \QueryCache::is_enabled()
                        ? \QueryCache::get_timber_posts( $query )
                        : \Timber\Timber::get_posts( $query );
                }
            ) );
        } catch ( \LogicException $e ) {
            // Zaten kayıtlı
        }

        return $twig;
    }

    function timber_output( $output, $data, $file ) {
        if ( strpos( $file, 'tease' ) === false ) return $output;

        $folder = explode( '/', $file )[0] ?? '';
        if ( ENABLE_ECOMMERCE && $folder === 'woo' ) return $output;

        $post_types = $data['post_pagination'] ?? [];
        if ( $post_types && in_array( $folder, array_keys( $post_types ), true ) ) {
            $page  = Data::get( 'pagination_page' ) ? "data-page='" . Data::get( 'pagination_page' ) . "'" : '';
            $output = "<div class='col' {$page}>{$output}</div>";
            $parser = \WyriHaximus\HtmlCompress\Factory::constructFastest();
            $output = $parser->compress( $output );
        }
        return $output;
    }

    function timber_twig_filters( $filters ) {
        $default = [
            'trans', 'trans_arr', 'printf_array', 'trans_plural', 'trans_static',
            'uppertr', 'get_option', 'phone_link', 'email_link', 'url_link',
            'list_social_accounts', 'json_decode', 'wrap_last', 'variation_url_rewrite',
            '_addClass', 'array2List', 'class_salt', 'ucwords', 'array_find_replace',
            'str_replace_arr', 'br2Nl', 'secure_string', 'pluralize', 'html_entity_decode',
            'boolval', 'boolstr', 'inline_svg', 'is_current_url', 'acf_get_contacts',
            'acf_layouts_container', 'acf_layouts_row', 'array2Attrs', 'masked_text',
            'acf_dynamic_container', 'array_merge_recursive_items', 'getMonthName',
            'translateContent', 'unit_value', 'urldecode', 'hex2rgbValues', 'json_attr', 'fa_icon',
        ];
        foreach ( $default as $filter ) {
            $filters[ $filter ] = [ 'callable' => $filter ];
        }
        foreach ( (array) Data::get( 'twig_filters', [] ) as $filter ) {
            $filters[ $filter ] = [ 'callable' => $filter ];
        }
        return $filters;
    }


    function timber_twig_functions( $functions ) {

        $functions['offcanvas_toggler']    = [ 'callable' => 'get_offcanvas_toggler' ];
        $functions['register_offcanvas']   = [ 'callable' => 'register_offcanvas_host' ];
        $functions['img']                  = [ 'callable' => 'get_image_set' ];
        $functions['video']                = [ 'callable' => 'get_video' ];
        $functions['get_post_by']          = [ 'callable' => 'Timber::get_post_by' ];
        $functions['translate']            = [ 'callable' => 'trans' ];
        $functions['translate_n_noop']     = [ 'callable' => 'trans_n_noop' ];
        $functions['remove_params']        = [ 'callable' => 'remove_params_from_url' ];
        $functions['get_title_from_url']   = [ 'callable' => 'get_title_from_url' ];
        $functions['get_map_config']       = [ 'callable' => 'get_map_config' ];
        $functions['lightGallerySource']   = [ 'callable' => 'lightGallerySource' ];
        $functions['get_page']             = [ 'callable' => '_get_page' ];
        $functions['get_menu']             = [ 'callable' => 'timber_get_menu' ];
        $functions['localization']         = [ 'callable' => 'localization' ];
        $functions['fa_icon']              = [ 'callable' => 'fa_icon', 'options' => [ 'is_safe' => [ 'html' ] ] ];

        // cached_posts — QueryCache üzerinden cache'li get_posts
        $cached_posts_fn = static function( $query = [] ) {
            if ( ! is_array( $query ) ) $query = [ 'post_type' => $query ];
            return class_exists( 'QueryCache' ) && \QueryCache::is_enabled()
                ? \QueryCache::get_timber_posts( $query )
                : \Timber\Timber::get_posts( $query );
        };
        $functions['cached_posts'] = [ 'callable' => $cached_posts_fn ];

        /**
         * posts_prefetch — Post listesi render edilmeden önce bulk cache doldurma.
         *
         * ENABLE_REACTIONS aktifse reaction count + has verilerini bulk cache'e yazar.
         * update_post_caches() ile postmeta + term ilişkileri tek sorguda önbelleğe alınır.
         *
         * Kullanım (Twig):  {% do posts_prefetch(posts) %}
         *                   {% do posts_prefetch(posts, 'product') %}
         */
        $functions['posts_prefetch'] = [
            'callable' => static function( $posts, $post_type = '' ): string {
                if ( empty( $posts ) ) return '';

                $ids           = [];
                $detected_type = (string) ( $post_type ?? '' );
                foreach ( $posts as $p ) {
                    $id = 0;
                    if ( is_object( $p ) ) {
                        try { $id = (int) $p->ID; } catch ( \Throwable $e ) {}
                        if ( ! $id && method_exists( $p, 'id' ) ) {
                            try { $id = (int) $p->id(); } catch ( \Throwable $e ) {}
                        }
                        if ( ! $detected_type && property_exists( $p, 'post_type' ) ) {
                            $detected_type = (string) $p->post_type;
                        }
                    } elseif ( is_array( $p ) ) {
                        $id = (int) ( $p['ID'] ?? $p['id'] ?? 0 );
                        if ( ! $detected_type && ! empty( $p['post_type'] ) ) {
                            $detected_type = $p['post_type'];
                        }
                    } elseif ( is_int( $p ) && $p > 0 ) {
                        $id = $p;
                    }
                    if ( $id > 0 ) $ids[] = $id;
                }

                if ( empty( $ids ) ) return '';

                // Reaction prefetch
                if ( defined( 'ENABLE_REACTIONS' ) && ENABLE_REACTIONS
                    && class_exists( '\SaltHareket\Reactions\Admin\ReactionsAjax' ) ) {
                    \SaltHareket\Reactions\Admin\ReactionsAjax::prefetch( $ids, 'post' );
                }

                // Bulk postmeta + term cache
                if ( function_exists( 'update_post_caches' ) ) {
                    $wp_posts = array_values( array_filter( array_map( 'get_post', $ids ) ) );
                    if ( $wp_posts ) update_post_caches( $wp_posts );

                    // Thumbnail (one cikan gorsel) attachment'larini toplu prefetch:
                    // tease.twig'te her post'un thumbnail'i img() ile cekilirken
                    // attachment post + meta icin ayri sorgu gidiyor. _thumbnail_id
                    // metasi yukarida cache'lendi; simdi attachment post objelerini +
                    // metalarini tek seferde WP object cache'e dolduruyoruz.
                    $thumb_ids = [];
                    foreach ( $ids as $pid ) {
                        $tid = (int) get_post_thumbnail_id( $pid );
                        if ( $tid > 0 ) $thumb_ids[] = $tid;
                    }
                    $thumb_ids = array_values( array_unique( $thumb_ids ) );
                    if ( $thumb_ids && function_exists( '_prime_post_caches' ) ) {
                        // Attachment postlari + postmeta'lari tek sorguda cache'e doldur
                        _prime_post_caches( $thumb_ids, true, true );
                    }
                }

                return '';
            },
            'options' => [],
        ];

        foreach ( (array) Data::get( 'twig_functions', [] ) as $key => $fn ) {
            $functions[ $key ] = [ 'callable' => $fn ];
        }

        return $functions;
    }

    function timber_post_image_extensions( $extensions ) {
        foreach ( (array) Data::get( 'upload_mimes', [] ) as $key => $mime ) {
            $extensions[] = $key;
        }
        return $extensions;
    }

    // ─── Footer / Header Template Resolver ───────────────────────────────────

    /**
     * Footer template'ini çözer.
     * Öncelik: page_settings.footer_template → footer_options.template → global default (SaltConfig)
     *
     * @return array {
     *   path        string  Twig template path ya da boş string
     *   id          string  footer HTML id
     *   class       string  footer CSS class
     *   template    string  çözülen template değeri (static | post_id | ...)
     *   post        mixed   Timber post (custom template ise)
     * }
     */
    /**
     * Header template'ini çözer.
     *
     * Öncelik sırası:
     * 1. page_settings['header_template'] — sayfa bazlı override
     * 2. header_options['template']        — site geneli seçim
     * 3. SaltConfig 'options_default_header_template' — global default
     *
     * Döndürdüğü array:
     *   post     — Timber\Post (template post)
     *   html     — render edilmiş HTML string (header tag + offcanvas dahil)
     *
     * Boş/geçersiz template seçilmişse null döner → base.twig eski sistemi kullanır.
     *
     * @param  array $page_settings
     * @param  array $header_options
     * @return array|null
     */
    public static function resolve_header( array $page_settings = [], array $header_options = [] ): ?array {
        // 0. Compile-time fetch override
        $is_compile_fetch = defined( 'IS_INTERNAL_FETCH' ) && IS_INTERNAL_FETCH
                         && isset( $_GET['_salt_compile_templates'] )
                         && ( $_GET['_salt_compile_templates'] === '1' );

        // 1. Sayfa bazlı override
        $template = '';
        if ( ! $is_compile_fetch
             && ! empty( $page_settings['header_template'] )
             && $page_settings['header_template'] !== 'default' ) {
            $template = $page_settings['header_template'];
        }

        // 2. Global default
        if ( empty( $template ) ) {
            $template = get_option( 'options_default_header_template', '' );
            if ( empty( $template ) ) {
                $template = \SaltHareket\SaltConfig::get( 'options_default_header_template', '' );
            }
        }

        // Template secilmemisse → null → eski ACF options header devreye girer
        // 'none' veya bos string → hic header render edilmez
        if ( $template === 'none' ) {
            return [ 'html' => '', 'post' => null, 'fixed' => 'no', 'affix' => false, 'hide_on_scroll' => false, 'none' => true ];
        }
        if ( empty( $template ) || $template === 'default' ) {
            return null;
        }

        // Static twig — header-static.twig
        if ( $template === 'static' ) {
            \Data::set( '_header_is_static', true );

            // Global block context'i koru — compile sonrası geri yaz
            $saved_block_context = $GLOBALS['_salt_block_context'] ?? null;

            $twig_context = ! empty( $GLOBALS['_salt_block_context'] )
                ? $GLOBALS['_salt_block_context']
                : \Timber\Timber::context();
            $html = \Timber\Timber::compile( 'header-static.twig', $twig_context ) ?: '';

            // Global post ve block context'i restore et
            wp_reset_postdata();
            if ( $saved_block_context !== null ) {
                $GLOBALS['_salt_block_context'] = $saved_block_context;
            }

            return [
                'post'           => null,
                'html'           => $html,
                'fixed'          => 'no',
                'affix'          => false,
                'hide_on_scroll' => false,
                'is_static'      => true,
            ];
        }

        $post = \Timber\Timber::get_post( (int) $template );
        if ( ! $post ) {
            return null;
        }

        // Header template twig'ini render et.
        // Global block context'i kullan — Timber::context() double-call'u önler
        $twig_context = [];
        if ( ! empty( $GLOBALS['_salt_block_context'] ) ) {
            $twig_context = $GLOBALS['_salt_block_context'];
        } else {
            $twig_context = \Timber\Timber::context();
        }
        $twig_context['post'] = $post;

        // Builder'in dogru post ID'sini bulabilmesi icin global set et
        // NOT: Bu global salt_render_acf_block içinde get_fields()'i etkilemez
        $GLOBALS['_salt_header_template_post_id'] = (int) $post->ID;

        // Template post'un CSS'ini context'e ekle — twig'de inline inject edilecek.
        //
        // ONCELIK 1: bootstrap.php _template_render'in urettigi SCOPE'LU CSS
        //   (theme/templates/_custom/{post_id}.css). Bu CSS template'in kok DOM
        //   elemani ile scope'lu (header → 'header#header .col{...}'). Boylece
        //   Bootstrap kurallari (.col, .row, row-cols-*) template disina tasmaz;
        //   ornegin shop urun grid'ini ezmez. meta 'css' bu dosyayi isaret eder.
        //
        // ONCELIK 2 (fallback): PAE'nin urettigi assets['css_page'] — DIKKAT: bu
        //   SCOPE'SUZ. Sadece scope'lu dosya yoksa kullanilir.
        $twig_context['header_template_css'] = '';

        $scoped_css_read = false;
        $scoped_css_meta = get_post_meta( (int) $post->ID, 'css', true );
        if ( ! empty( $scoped_css_meta ) ) {
            $scoped_abs = trailingslashit( get_stylesheet_directory() )
                        . 'theme/templates/_custom/' . ltrim( (string) $scoped_css_meta, '/' );
            if ( file_exists( $scoped_abs ) ) {
                $css_content = @file_get_contents( $scoped_abs );
                if ( $css_content !== false && $css_content !== '' ) {
                    $twig_context['header_template_css'] = $css_content;
                    $scoped_css_read = true;
                }
            }
        }

        // Fallback: scope'lu dosya yoksa PAE css_page (scope'suz)
        if ( ! $scoped_css_read ) {
            $tmpl_assets   = get_post_meta( (int) $post->ID, 'assets', true );
            $is_rtl_lang   = (bool) \Data::get( 'language_rtl' );
            $tmpl_css_path = '';
            if ( is_array( $tmpl_assets ) ) {
                $css_key       = $is_rtl_lang ? 'css_page_rtl' : 'css_page';
                $tmpl_css_path = $tmpl_assets[ $css_key ] ?? $tmpl_assets['css_page'] ?? '';
            }
            if ( ! empty( $tmpl_css_path ) && defined( 'STATIC_PATH' ) ) {
                $abs = rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $tmpl_css_path, '/' );
                if ( file_exists( $abs ) ) {
                    $css_content = @file_get_contents( $abs );
                    if ( $css_content ) {
                        $twig_context['header_template_css'] = $css_content;
                    }
                }
            }
        }

        $html = '';
        // Global post'u kaydet — Timber::compile sonrası restore edilecek
        global $post;
        $saved_post = $post;

        // header-builder verilerini Timber::compile ÖNCE kaydet
        // compile sırasında global $post değişeceği için burada saklıyoruz
        $header_post_content = '';
        if ( isset( $twig_context['post'] ) ) {
            $tp = $twig_context['post'];
            // Timber\Post veya WP_Post — post_content raw field
            $header_post_content = $tp->post_content ?? ( method_exists( $tp, 'content' ) ? $tp->raw_content ?? '' : '' );
        }

        // _salt_block_context'i geçici kopyala — compile bittikten sonra dirty flag ile reset
        $saved_block_ctx = $GLOBALS['_salt_block_context'] ?? null;

        $html = \Timber\Timber::compile(
            [ 'blocks/header-builder/header-template.twig' ],
            $twig_context
        ) ?: '';

        // header-builder block'undan fixed/affix/hide_on_scroll degerlerini oku
        $fixed          = 'no';
        $affix          = false;
        $hide_on_scroll = false;

        // Global post ve context'i restore et
        $post = $saved_post;
        wp_reset_postdata();
        if ( $saved_block_ctx !== null ) {
            $GLOBALS['_salt_block_context'] = $saved_block_ctx;
        }
        // Static cache'i sıfırla
        if ( function_exists( 'salt_render_acf_block_reset_context' ) ) {
            salt_render_acf_block_reset_context();
        }

        $post_blocks = parse_blocks( $header_post_content );
        foreach ( $post_blocks as $pb ) {
            if ( ( $pb['blockName'] ?? '' ) === 'acf/header-builder' ) {
                $bd             = $pb['attrs']['data'] ?? [];
                $fixed          = $bd['fixed']          ?? 'no';
                $affix          = ! empty( $bd['affix'] );
                $hide_on_scroll = ! empty( $bd['hide_on_scroll'] );
                break;
            }
        }

        return [
            'post'           => $post,
            'html'           => $html ?: '',
            'fixed'          => $fixed,
            'affix'          => $affix,
            'hide_on_scroll' => $hide_on_scroll,
        ];
    }

    public static function resolve_footer( array $page_settings = [], array $footer_options = [] ): array {
        // 0. Compile-time fetch override — compile_common_css() X-Internal-Fetch:1 +
        //    ?_salt_compile_templates=1 ile fetch yaparken sayfa bazli override'i atla,
        //    her zaman global default template'i yukle.
        $is_compile_fetch = defined( 'IS_INTERNAL_FETCH' ) && IS_INTERNAL_FETCH
                         && isset( $_GET['_salt_compile_templates'] )
                         && ( $_GET['_salt_compile_templates'] === '1' );

        // 1. page_settings override — sayfa bazlı (compile fetch'te atla)
        $template = '';
        if ( ! $is_compile_fetch
             && ! empty( $page_settings['footer_template'] )
             && $page_settings['footer_template'] !== 'default' ) {
            $template = $page_settings['footer_template'];
        }

        // 2. footer_options'tan (eski ACF header/footer options sistemi)
        if ( empty( $template ) ) {
            $opt_tpl = $footer_options['template'] ?? '';
            if ( ! empty( $opt_tpl ) && $opt_tpl !== 'default' ) {
                $template = $opt_tpl;
            }
        }

        // 3. Global default — get_option direkt, SaltConfig fallback
        if ( empty( $template ) ) {
            $template = get_option( 'options_default_footer_template', '' );
            if ( empty( $template ) ) {
                $template = \SaltHareket\SaltConfig::get( 'options_default_footer_template', '' );
            }
            // DB'de deger yoksa (ilk kurulum) → static fallback
            if ( empty( $template ) ) {
                $template = 'static';
            }
        }

        // None secilmisse hic footer render etme
        if ( $template === 'none' ) {
            return [ 'template' => 'none', 'path' => '', 'id' => 'footer', 'class' => '', 'post' => null, 'none' => true ];
        }

        $path        = '';
        $id          = 'footer';
        $class       = 'footer z-0';
        $post        = null;

        if ( $template === 'static' ) {
            \Data::set( '_footer_is_static', true );
            $class .= ' pt-5 pb-4';
            $path   = 'footer-static.twig';
        } elseif ( ! empty( $template ) ) {
            $id    .= '-custom';
            $post   = \Timber\Timber::get_post( (int) $template );
            if ( $post ) {
                $class .= ' ' . $post->slug;
                // Render template var mı?
                if ( $post->meta( 'render_template' ) && $post->meta( 'template' ) ) {
                    $twig_file = 'theme/templates/_custom/' . $post->meta( 'template' );
                    if ( file_exists( get_stylesheet_directory() . '/' . $twig_file ) ) {
                        $path = $twig_file;
                    }
                }
            }
        }

        return compact( 'template', 'path', 'id', 'class', 'post' );
    }

}
