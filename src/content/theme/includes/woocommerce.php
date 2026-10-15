<?php

include_once  "woocommerce/functions.php";
include_once 'woocommerce/admin.php';
include_once 'woocommerce/custom-fields/index.php';
include_once 'woocommerce/variation-url.php';

// ─────────────────────────────────────────────────────────────────────────────
// VARIATION URL → TIMBER CONTEXT INJECT
//
// XT Variations as Singles plugin KAPALIYKEN:
// Variation URL ile girildiğinde ($_REQUEST inject yapıldıktan sonra) o
// variation'ın title/desc/attrs/fiyatını Timber context'ine inject eder.
// Twig bu veriyi okuyarak JS'i beklemeden doğru içeriği render eder.
//
// XT AÇIKKEN: plugin zaten variation'ı ayrı ürün olarak render ediyor,
// bu injection gereksiz — atla.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'timber/context', function( array $context ) : array {
    // XT Variations as Singles aktifse dokunma
    if ( defined( 'XT_WOOVAS' ) ) return $context;

    if ( ! is_singular( 'product' ) ) return $context;

    // $_REQUEST'te variation attribute var mı? (Bölüm 4'te inject edildi)
    global $post;
    $product = $post ? wc_get_product( $post->ID ) : null;
    if ( ! $product || ! $product->is_type( 'variable' ) ) return $context;

    $variation_attrs = $product->get_variation_attributes();
    if ( empty( $variation_attrs ) ) return $context;

    // Inject edilmiş attribute'ları topla
    $lookup = [];
    foreach ( array_keys( $variation_attrs ) as $taxonomy ) {
        $req_key = 'attribute_' . $taxonomy;
        if ( ! empty( $_REQUEST[ $req_key ] ) ) {
            $lookup[ $req_key ] = sanitize_title( wp_unslash( $_REQUEST[ $req_key ] ) );
        }
    }
    if ( empty( $lookup ) ) return $context;

    // Matching variation'ı bul
    $variation_id = 0;
    if ( class_exists( 'WC_Data_Store' ) ) {
        $variation_id = (int) \WC_Data_Store::load('product')->find_matching_product_variation( $product, $lookup );
    }
    if ( ! $variation_id ) return $context;

    $var_product = wc_get_product( $variation_id );
    if ( ! $var_product ) return $context;

    $product_title = $product->get_title();
    $product_desc  = $product->get_description();

    // Variation title — önce custom meta, yoksa attribute değerlerinden oluştur
    $var_title = get_post_meta( $variation_id, 'variation_title', true );
    if ( ! $var_title ) {
        // Attribute human-readable değerlerini topla
        $attr_labels = [];
        foreach ( $lookup as $req_key => $slug ) {
            $taxonomy = str_replace( 'attribute_', '', $req_key );
            if ( taxonomy_exists( $taxonomy ) ) {
                $term = get_term_by( 'slug', $slug, $taxonomy );
                $attr_labels[] = $term ? $term->name : $slug;
            } else {
                $attr_labels[] = $slug;
            }
        }
        $var_title = $attr_labels ? $product_title . ' - ' . implode( ' / ', $attr_labels ) : $product_title;
    }

    $var_desc = $var_product->get_description() ?: $product_desc;
    $var_sku  = $var_product->get_sku();

    // Variation thumbnail URL — sticky ATC için
    $var_thumb_id  = $var_product->get_image_id();
    $var_thumb_url = $var_thumb_id
        ? wp_get_attachment_image_url( $var_thumb_id, 'woocommerce_thumbnail' )
        : get_the_post_thumbnail_url( $post->ID, 'woocommerce_thumbnail' );

    // Context'e inject et
    $context['initial_variation'] = [
        'id'          => $variation_id,
        'title'       => $var_title,
        'description' => ( $var_sku ? '<strong>SKU: ' . esc_html( $var_sku ) . '</strong><br>' : '' ) . wpautop( wptexturize( $var_desc ) ),
        'price_html'  => $var_product->get_price_html(),
        'attrs'       => $lookup,
        'thumb_url'   => $var_thumb_url ?: '',
    ];

    return $context;
} );

// ─────────────────────────────────────────────────────────────────────────────
// VARIATION URL → YOAST / WC SCHEMA OVERRIDE
// Variation URL'de structured data da doğru variation bilgisini içersin.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'wpseo_schema_product', function( array $data ) : array {
    if ( ! is_singular( 'product' ) || defined( 'XT_WOOVAS' ) ) return $data;

    global $post;
    $product = $post ? wc_get_product( $post->ID ) : null;
    if ( ! $product || ! $product->is_type( 'variable' ) ) return $data;

    $lookup = [];
    foreach ( array_keys( $product->get_variation_attributes() ) as $taxonomy ) {
        $req_key = 'attribute_' . $taxonomy;
        if ( ! empty( $_REQUEST[ $req_key ] ) ) {
            $lookup[ $req_key ] = sanitize_title( wp_unslash( $_REQUEST[ $req_key ] ) );
        }
    }
    if ( empty( $lookup ) ) return $data;

    $variation_id = 0;
    if ( class_exists( 'WC_Data_Store' ) ) {
        $variation_id = (int) \WC_Data_Store::load('product')->find_matching_product_variation( $product, $lookup );
    }
    if ( ! $variation_id ) return $data;

    $var = wc_get_product( $variation_id );
    if ( ! $var ) return $data;

    // Başlık
    $var_title = get_post_meta( $variation_id, 'variation_title', true );
    if ( ! $var_title ) {
        $labels = [];
        foreach ( $lookup as $req_key => $slug ) {
            $tax  = str_replace( 'attribute_', '', $req_key );
            $term = taxonomy_exists( $tax ) ? get_term_by( 'slug', $slug, $tax ) : null;
            $labels[] = $term ? $term->name : $slug;
        }
        $var_title = $labels ? $product->get_title() . ' - ' . implode( ' / ', $labels ) : $product->get_title();
    }

    $data['name']        = $var_title;
    $data['description'] = wp_strip_all_tags( $var->get_description() ?: $product->get_description() );
    $data['sku']         = $var->get_sku() ?: ( $data['sku'] ?? '' );

    // Fiyat
    $price = $var->get_price();
    if ( $price !== '' ) {
        $data['offers'][0]['price']         = wc_format_decimal( $price, wc_get_price_decimals() );
        $data['offers'][0]['priceCurrency'] = get_woocommerce_currency();
        $data['offers'][0]['availability']  = $var->is_in_stock()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    return $data;
}, 20 );

if (ENABLE_MEMBERSHIP) {
    include_once  "woocommerce/hooks/redirect.php";
    include_once "woocommerce/hooks/my-account.php";
}

include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/product.php';
include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/single-product.php';
include_once 'woocommerce/hooks/product-category.php';
include_once 'woocommerce/hooks/bootstrap/manager.php';

if(ENABLE_CART){
    include_once 'woocommerce/custom-thankyou.php'; 
    include_once 'woocommerce/hooks/checkout.php';
    include_once 'woocommerce/hooks/cart.php';
}

if(!DISABLE_COMMENTS){
    //include_once 'woocommerce/hooks/comments.php';   
}

// ACF post_pagination ayarı varsa WooCommerce'in loop_shop_per_page'ini override et
add_filter('loop_shop_per_page', function($per_page) {
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];
    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        return intval($product_pagination['posts_per_page']);
    }
    return $per_page;
}, 999); // priority 999 — tüm plugin'lerden sonra

// woocommerce_product_query'de de override et — WSSV'den sonra çalışması için priority 99
add_action('woocommerce_product_query', function($q) {
    if ($q->get('is_favorites')) return;
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];
    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        $q->set('posts_per_page', intval($product_pagination['posts_per_page']));
    }
}, 99);

// posts_per_page son kez override edilip edilmediğini kontrol et
add_filter('the_posts', function($posts, $query) {
    if ($query->get('wc_query') !== 'product_query') return $posts;
    if ($query->get('is_favorites')) return $posts; // favorites query'sine dokunma

    $ppp = 0;

    // 1. ACF post_pagination'dan al
    $product_pagination = function_exists('get_post_type_pagination') ? get_post_type_pagination('product') : [];
    if (!empty($product_pagination['paged']) && !empty($product_pagination['posts_per_page'])) {
        $ppp = intval($product_pagination['posts_per_page']);
    }

    // 2. Boşsa loop_shop_per_page filter'ından al
    if (!$ppp) {
        $ppp = intval(apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()));
    }

    // Sadece max_num_pages'i düzelt — array_slice yapma, YITH filter ile çakışır
    if ($ppp > 0 && $query->get('posts_per_page') != $ppp) {
        $query->set('posts_per_page', $ppp);
        if ($query->found_posts > 0) {
            $query->max_num_pages = ceil($query->found_posts / $ppp);
        }
        // array_slice kaldırıldı — YITH filter sonuçlarını bozuyordu
    }

    return $posts;
}, 99, 2);


// ── WC 11.x + woo-variation-swatches-pro uyumsuzluk fix ──────────────────────
// $.trim jQuery 3.x'te kaldırıldı, WC'nin eski JS'i kullanıyor.
// woo_variation_swatches_pro_options single product sayfasında inject edilmiyor (pro bug).
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_product() ) return;
    if ( ! class_exists( 'Woo_Variation_Swatches_Pro' ) && ! class_exists( 'Woo_Variation_Swatches' ) ) return;

    $wc_js_dir  = WP_PLUGIN_DIR . '/woocommerce/assets/js/frontend/';
    $unminified = $wc_js_dir . 'add-to-cart-variation.js';
    if ( ! file_exists( $unminified ) ) return;

    global $wp_scripts;
    $registered = $wp_scripts->registered['wc-add-to-cart-variation'] ?? null;
    $deps        = $registered ? $registered->deps : [ 'jquery', 'wp-util', 'accounting', 'wc-price-format' ];
    $extra        = $registered ? $registered->extra : [];

    wp_dequeue_script( 'wc-add-to-cart-variation' );
    wp_deregister_script( 'wc-add-to-cart-variation' );

    wp_register_script(
        'wc-add-to-cart-variation',
        plugins_url( 'woocommerce/assets/js/frontend/add-to-cart-variation.js' ),
        $deps,
        filemtime( $unminified ),
        true
    );

    if ( ! empty( $extra ) ) {
        foreach ( $extra as $type => $data ) {
            $wp_scripts->registered['wc-add-to-cart-variation']->extra[ $type ] = $data;
        }
    }

    // Fix 1: $.trim jQuery 3.x'te kaldırıldı
    wp_add_inline_script( 'wc-add-to-cart-variation',
        'if(typeof jQuery!=="undefined"&&typeof jQuery.trim==="undefined"){jQuery.trim=function(s){return s?s.trim():s;};}',
        'before'
    );

    // Fix 2: woo_variation_swatches_pro_options single product'ta inject edilmiyor
    // Archive page class'ının yaptığı localize'ı buradan manuel tetikle
    wp_add_inline_script( 'wc-add-to-cart-variation',
        'if(typeof woo_variation_swatches_pro_options==="undefined"){window.woo_variation_swatches_pro_options=window.woo_variation_swatches_pro_options||{};}' .
        'if(typeof woo_variation_swatches_pro_params==="undefined"){window.woo_variation_swatches_pro_params=window.woo_variation_swatches_pro_params||{};}',
        'before'
    );

    wp_enqueue_script( 'wc-add-to-cart-variation' );
}, 100 );

// Fix 3: woo_variation_swatches_pro_options'ı swatches-pro'nun gerçek değerleriyle doldur
// wp_footer'dan önce — tüm enqueue'lar tamamlandıktan sonra
add_action( 'wp_footer', function() {
    if ( ! is_product() ) return;
    if ( ! class_exists( 'Woo_Variation_Swatches_Pro' ) ) return;

    // Archive page instance'ından js_options al
    $archive_class = 'Woo_Variation_Swatches_Pro_Archive_Page';
    if ( ! class_exists( $archive_class ) ) return;

    try {
        $instance = new $archive_class();
        if ( method_exists( $instance, 'js_options' ) ) {
            $opts   = $instance->js_options();
            $params = method_exists( $instance, 'js_params' ) ? $instance->js_params() : [];

            // auto_select_first_variation setting kapalıysa swatches auto select'i de kapat
            $woo_single = (array) get_option( 'sh_woo_single', [] );
            if ( empty( $woo_single['auto_select_first_variation'] ) ) {
                $opts['enable_auto_select']         = false;
                $opts['auto_select']                = false;
                $opts['autoselect']                 = false;
                $opts['auto_select_variation']      = false;
                $opts['auto_select_first_variation'] = false;
            }

            echo '<script>window.woo_variation_swatches_pro_options=' . wp_json_encode( $opts ) . ';';
            echo 'window.woo_variation_swatches_pro_params=' . wp_json_encode( $params ) . ';</script>';
        }
    } catch ( \Throwable $e ) {
        // Sessizce başarısız ol
    }
}, 1 ); // priority 1 — diğer footer scriptlerinden önce


// ── Reaction sort — WC shop sort dropdown'ına sortable reaction type'ları ekle ─
// ReactionsSettings::getSortableTypes() → sortable:true olan enabled tipler.
// Her type için "Sort by X count" seçeneği dropdown'a eklenir.
// URL: /?orderby=reaction_favorite, /?orderby=reaction_like vb.

add_filter( 'woocommerce_catalog_orderby', function( array $orderby ): array {
    if ( ! class_exists( '\\SaltHareket\\Reactions\\ReactionsSettings' ) ) return $orderby;

    $sortable = \SaltHareket\Reactions\ReactionsSettings::getSortableTypes();
    foreach ( $sortable as $key => $def ) {
        $label                          = $def['label'] ?? ucfirst( $key );
        $orderby[ 'reaction_' . $key ]  = sprintf( __( 'Sort by %s count', 'salthareket' ), $label );
    }
    return $orderby;
} );

add_filter( 'woocommerce_get_catalog_ordering_args', function( array $args ): array {
    $orderby = sanitize_key( $_GET['orderby'] ?? get_option( 'woocommerce_default_catalog_orderby', '' ) );

    if ( strpos( $orderby, 'reaction_' ) !== 0 ) return $args;

    $reaction_key = substr( $orderby, 9 ); // 'reaction_favorite' → 'favorite'
    if ( empty( $reaction_key ) ) return $args;

    global $wpdb;
    $meta_key = '_reaction_count_' . sanitize_key( $reaction_key );

    // Reactions tablosu var mı? Varsa JOIN ile say, yoksa meta fallback
    $reactions_table = $wpdb->prefix . 'reactions';
    $table_exists    = (bool) $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $reactions_table )
    );

    if ( $table_exists ) {
        // Reactions tablosundan count hesapla — post meta'ya gerek yok
        add_filter( 'posts_join', function( string $join ) use ( $wpdb, $reactions_table, $reaction_key ): string {
            $join .= " LEFT JOIN (
                SELECT object_id, COUNT(*) AS reaction_count
                FROM `{$reactions_table}`
                WHERE `type` = '" . esc_sql( $reaction_key ) . "'
                  AND object_type = 'post'
                GROUP BY object_id
            ) sh_rc ON sh_rc.object_id = {$wpdb->posts}.ID ";
            return $join;
        }, 10 );

        add_filter( 'posts_orderby', function( string $orderby_sql ) use ( $wpdb ): string {
            return 'COALESCE(sh_rc.reaction_count, 0) DESC, ' . $wpdb->posts . '.ID DESC';
        }, 10 );

    } else {
        // Fallback: post meta üzerinden (Reactions tablosu yoksa)
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
        $args['meta_key'] = $meta_key;
        $args['meta_query'] = [
            'relation' => 'OR',
            [ 'key' => $meta_key, 'compare' => 'EXISTS' ],
            [ 'key' => $meta_key, 'compare' => 'NOT EXISTS' ],
        ];
    }

    return $args;
} );
