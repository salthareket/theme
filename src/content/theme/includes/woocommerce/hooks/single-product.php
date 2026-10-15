<?php
/**
 * WooCommerce Single Product — Hook'lar ve Özelleştirmeler
 *
 * Organizasyon:
 *   1. WC Native hook kaldırmaları
 *   2. Variation dropdown sıralama
 *   3. Fiyat encoding fix
 *   4. Variation URL → $_REQUEST inject (attribute değerlerini set et)
 *   5. Auto select first variation (setting opsiyonel)
 *   6. Variation URL → PHP title/desc override (JS atlama olmadan)
 *   7. Variation title JS güncelleme (found_variation)
 *   8. Sticky ATC
 *   9. WC native related kaldır
 *  10. Reviews
 *  11. WC ek bilgiler
 *  12. ProductStats
 *  13. sh_product_stats action
 */


// ─────────────────────────────────────────────────────────────────────────────
// 1. WC NATIVE HOOK KALDIRMALARI
// ─────────────────────────────────────────────────────────────────────────────

// Kısa açıklama (post excerpt) — Twig şablonunda yönetiyoruz
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );

// Başlık — single-product.twig'de yönetiyoruz
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );

// Galeri — kendi product-gallery.twig kullanıyoruz
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );

// Rating — kendi rating-summary.twig kullanıyoruz
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );

// Product meta (SKU, kategoriler) — istenmiyor
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

// Related products — aşağıda kendi product-list.twig ile yönetiyoruz
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
remove_action( 'woocommerce_after_single_product', 'woocommerce_output_related_products', 9 );

// Upsell — product-list.twig ile yönetiyoruz
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

// WC galeri JS — kendi gallery sistemimizi kullanıyoruz
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_script( 'wc-single-product' );
    wp_dequeue_script( 'wc-product-variation-gallery' );
}, 99 );

// ENABLE_CART kapalıysa fiyat ve ATC elementlerini kaldır
if ( ! ENABLE_CART ) {
    remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    add_filter( 'woocommerce_sale_flash', '__return_null' );
    remove_action( 'woocommerce_single_product_summary', 'add-to-cart-form-bundle', 9999 );
}

// WC native tab sistemi — kendi product-tabs.twig kullanıyoruz
add_filter( 'woocommerce_product_tabs', function() { return []; }, 9999 );


// ─────────────────────────────────────────────────────────────────────────────
// 2. VARIATION DROPDOWN SIRALAMA
// Variation'lar dropdown'da available_variations sırasına göre sıralanır.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'woocommerce_dropdown_variation_attribute_options_html', function( $html, $args ) {
    $args = wp_parse_args(
        apply_filters( 'woocommerce_dropdown_variation_attribute_options_args', $args ),
        [
            'options'          => false,
            'attribute'        => false,
            'product'          => false,
            'selected'         => false,
            'name'             => '',
            'id'               => '',
            'class'            => '',
            'show_option_none' => __( 'Choose an option', 'woocommerce' ),
        ]
    );

    if ( false === $args['selected'] && $args['attribute'] && $args['product'] instanceof WC_Product ) {
        $selected_key     = 'attribute_' . sanitize_title( $args['attribute'] );
        $args['selected'] = isset( $_REQUEST[ $selected_key ] )
            ? wc_clean( wp_unslash( $_REQUEST[ $selected_key ] ) )
            : $args['product']->get_variation_default_attribute( $args['attribute'] );
    }

    $options          = $args['options'];
    $product          = $args['product'];
    $attribute        = $args['attribute'];
    $name             = $args['name'] ?: 'attribute_' . sanitize_title( $attribute );
    $id               = $args['id']   ?: sanitize_title( $attribute );
    $class            = $args['class'];
    $show_none        = (bool) $args['show_option_none'];
    $show_none_text   = $args['show_option_none'] ?: __( 'Choose an option', 'woocommerce' );

    if ( empty( $options ) && ! empty( $product ) && ! empty( $attribute ) ) {
        $options = $product->get_variation_attributes()[ $attribute ] ?? [];
    }

    $html  = '<select id="' . esc_attr( $id ) . '" class="' . esc_attr( $class ) . '" name="' . esc_attr( $name ) . '"'
           . ' data-attribute_name="attribute_' . esc_attr( sanitize_title( $attribute ) ) . '"'
           . ' data-show_option_none="' . ( $show_none ? 'yes' : 'no' ) . '">';
    $html .= '<option value="">' . esc_html( $show_none_text ) . '</option>';

    if ( ! empty( $options ) ) {
        if ( $product && taxonomy_exists( $attribute ) ) {
            $terms = wc_get_product_terms( $product->get_id(), $attribute, [ 'fields' => 'all' ] );

            // Variation'ların sırasına göre term'leri yeniden sırala
            foreach ( $terms as $key => $term ) {
                $i = 0;
                foreach ( $product->get_available_variations() as $variation ) {
                    $i++;
                    if ( isset( $variation['attributes'][ $name ] ) && $term->slug === $variation['attributes'][ $name ] ) {
                        unset( $terms[ $key ] );
                        $terms[ $i - 1 ] = $term;
                    }
                }
            }
            ksort( $terms );

            foreach ( $terms as $term ) {
                if ( in_array( $term->slug, $options, true ) ) {
                    $html .= '<option value="' . esc_attr( $term->slug ) . '" '
                           . selected( sanitize_title( $args['selected'] ), $term->slug, false ) . '>'
                           . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ) )
                           . '</option>';
                }
            }
        } else {
            foreach ( $options as $option ) {
                $sel   = sanitize_title( $args['selected'] ) === $args['selected']
                    ? selected( $args['selected'], sanitize_title( $option ), false )
                    : selected( $args['selected'], $option, false );
                $html .= '<option value="' . esc_attr( $option ) . '" ' . $sel . '>'
                       . esc_html( apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ) )
                       . '</option>';
            }
        }
    }

    return $html . '</select>';
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// 3. FİYAT ENCODING FIX
// UTF-8 en-dash karakterinin yanlış yorumlanması düzeltilir.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'woocommerce_get_price_html', function( $price_html ) {
    $price_html = str_replace( 'â€“', '&ndash;', $price_html );
    $price_html = str_replace( "\xe2\x80\x93", '&ndash;', $price_html );
    return $price_html;
}, 999 );

add_filter( 'woocommerce_get_price_html_from_to', function( $price, $from, $to ) {
    return sprintf( '%s &ndash; %s', $from, $to );
}, 999, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// 4. VARİATİON URL → $_REQUEST INJECT
// /product/slug/color-03-dark-brown/ URL'inden veya %variation% query var'ından
// attribute değerlerini $_REQUEST'e inject eder.
// WC variation form + product_gallery() bu değerleri okuyarak doğru
// variation'ı seçer/render eder.
//
// Kaynak önceliği:
//   1. %variation% query var (variation-url.php rewrite rules'dan gelir)
//   2. URL path parse (fallback — eski /color-xx/ pattern)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp', function() {
    if ( ! is_singular( 'product' ) ) return;

    global $post;
    $product = $post ? wc_get_product( $post->ID ) : null;
    if ( ! $product || ! $product->is_type( 'variable' ) ) return;

    $variation_attributes = $product->get_variation_attributes();
    if ( empty( $variation_attributes ) ) return;

    // ── Kaynak 1: %variation% query var (variation-url.php) ──────────────────
    $variation_string = get_query_var( 'variation' );
    if ( $variation_string && function_exists( '_wc_var_url_parse_string' ) ) {
        $parsed = _wc_var_url_parse_string( $variation_string );
        if ( ! empty( $parsed ) ) {
            foreach ( $parsed as $attr_slug => $value ) {
                // pa_xxx taxonomy'si mi yoksa custom attribute mi?
                $taxonomy    = taxonomy_exists( 'pa_' . $attr_slug ) ? 'pa_' . $attr_slug : $attr_slug;
                $request_key = 'attribute_' . $taxonomy;
                if ( empty( $_REQUEST[ $request_key ] ) ) {
                    $_REQUEST[ $request_key ] = sanitize_title( $value );
                    $_GET[ $request_key ]     = sanitize_title( $value );
                }
            }
            return; // Query var'dan aldık, path parse'a gerek yok
        }
    }

    // ── Kaynak 2: URL path fallback ───────────────────────────────────────────
    $path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    if ( empty( $path ) ) return;

    $path_parts = array_filter( explode( '/', $path ) );

    foreach ( $variation_attributes as $taxonomy => $options ) {
        // $taxonomy: taxonomy tabanlılar için 'pa_color', custom için 'size' vs.
        $attr_slug   = str_replace( 'pa_', '', $taxonomy );
        $request_key = 'attribute_' . $taxonomy;

        if ( ! empty( $_REQUEST[ $request_key ] ) ) continue;

        foreach ( $path_parts as $part ) {
            if ( strpos( $part, $attr_slug . '-' ) === 0 ) {
                $value = sanitize_title( substr( $part, strlen( $attr_slug ) + 1 ) );
                if ( $value ) {
                    $_REQUEST[ $request_key ] = $value;
                    $_GET[ $request_key ]     = $value;
                }
                break;
            }
        }
    }
}, 10 );


// ─────────────────────────────────────────────────────────────────────────────
// 5. AUTO SELECT FIRST VARIATION
// General Settings → WooCommerce → "İlk variation'ı otomatik seç" aktifse:
// - Ana ürün URL'si girildiğinde ilk variation'ın URL'sine redirect eder
// - Galeri, başlık ve fiyat o variation'a göre gelir (Bölüm 4 ile birlikte çalışır)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'template_redirect', function() {
    if ( ! is_singular( 'product' ) ) return;

    $cfg = (array) get_option( 'sh_woo_single', [] );
    if ( empty( $cfg['auto_select_first_variation'] ) ) return;

    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product || ! $product->is_type( 'variable' ) ) return;

    // URL'de zaten variation var mı? İki yöntemle kontrol et:
    // 1. %variation% query var — variation-url.php rewrite'tan gelir
    if ( get_query_var( 'variation' ) ) return;

    // 2. Path parse — attribute-slug- pattern
    $variation_attributes = $product->get_variation_attributes();
    $path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    $path_parts = array_filter( explode( '/', $path ) );

    foreach ( $variation_attributes as $taxonomy => $options ) {
        $attr_slug = str_replace( 'pa_', '', $taxonomy );
        foreach ( $path_parts as $part ) {
            if ( strpos( $part, $attr_slug . '-' ) === 0 ) return;
        }
    }

    // 3. $_REQUEST'te inject edilmiş attribute var mı?
    foreach ( array_keys( $variation_attributes ) as $taxonomy ) {
        if ( ! empty( $_REQUEST[ 'attribute_' . $taxonomy ] ) ) return;
    }

    // İlk available variation'ı bul
    $variations = $product->get_available_variations();
    if ( empty( $variations ) ) return;

    $first_variation_id = $variations[0]['variation_id'] ?? 0;
    if ( ! $first_variation_id ) return;

    $first_product = wc_get_product( $first_variation_id );
    if ( ! $first_product ) return;

    // Variation URL'sini oluştur
    $var_url = get_permalink( $first_variation_id );

    // Zaten aynı URL'deysek döngüye girme
    $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    if ( trailingslashit( $var_url ) === trailingslashit( $current_url ) ) return;

    wp_safe_redirect( $var_url, 302 );
    exit;
}, 10 );


// ─────────────────────────────────────────────────────────────────────────────
// 6. VARİATİON URL → PHP TITLE / DESCRIPTION OVERRIDE
// XT kapalıyken variation URL ile girildiğinde title ve desc PHP'den
// doğru değerle basılır — JS atlama olmaz.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp', function() {
    if ( ! is_singular( 'product' ) ) return;
    if ( defined( 'XT_WOOVAS' ) ) return; // XT açıksa variation zaten kendi ürünü

    global $post;
    $product = $post ? wc_get_product( $post->ID ) : null;
    if ( ! $product || ! $product->is_type( 'variable' ) ) return;

    // $_REQUEST'te inject edilmiş attribute'ları topla
    $lookup = [];
    foreach ( array_keys( $product->get_variation_attributes() ) as $taxonomy ) {
        $req_key = 'attribute_' . $taxonomy;
        if ( ! empty( $_REQUEST[ $req_key ] ) ) {
            $lookup[ $req_key ] = sanitize_title( wp_unslash( $_REQUEST[ $req_key ] ) );
        }
    }
    if ( empty( $lookup ) ) return;

    // Matching variation bul
    $variation_id = 0;
    if ( class_exists( 'WC_Data_Store' ) ) {
        $variation_id = (int) \WC_Data_Store::load('product')->find_matching_product_variation( $product, $lookup );
    }
    if ( ! $variation_id ) return;

    $var_product = wc_get_product( $variation_id );
    if ( ! $var_product ) return;

    // Variation title oluştur
    $parent_title = $product->get_title();
    $var_title    = get_post_meta( $variation_id, 'variation_title', true );
    if ( ! $var_title ) {
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
        $var_title = $attr_labels ? $parent_title . ' - ' . implode( ' / ', $attr_labels ) : $parent_title;
    }

    // the_title filter — WC title hook'larını ve Timber context'teki post.title'ı override et
    add_filter( 'the_title', function( $title, $id = 0 ) use ( $post, $var_title ) {
        if ( (int) $id === (int) $post->ID ) {
            return esc_html( $var_title );
        }
        return $title;
    }, 20, 2 );

    // wp_title / SEO title da düzelsin
    add_filter( 'wpseo_title',   function( $t ) use ( $var_title ) { return $var_title . ' - ' . get_bloginfo('name'); } );
    add_filter( 'pre_get_document_title', function( $t ) use ( $var_title ) { return $var_title . ' - ' . get_bloginfo('name'); } );

    // Variation description — tüm description filter'larını override et
    $var_desc_raw = $var_product->get_description() ?: $product->get_description();
    $var_desc     = wpautop( wptexturize( $var_desc_raw ) );

    // 1. woocommerce_short_description — WC short desc hook
    add_filter( 'woocommerce_short_description', function() use ( $var_desc ) {
        return $var_desc;
    }, 20 );

    // 2. the_content — Timber {{ post.content }} buradan geliyor
    add_filter( 'the_content', function( $content ) use ( $post, $var_desc ) {
        // Sadece bu ürünün post ID'si için çalış
        if ( (int) get_the_ID() === (int) $post->ID || get_the_ID() === false ) {
            return $var_desc;
        }
        return $content;
    }, 20 );

    // 3. get_the_excerpt — Timber post.excerpt için
    add_filter( 'get_the_excerpt', function( $excerpt, $p = null ) use ( $post, $var_desc_raw ) {
        $pid = is_object( $p ) ? $p->ID : 0;
        if ( ! $pid || (int) $pid === (int) $post->ID ) {
            return $var_desc_raw;
        }
        return $excerpt;
    }, 20, 2 );

}, 15 ); // priority 15 — Bölüm 4 (wp, priority 10) sonrası çalışsın


// ─────────────────────────────────────────────────────────────────────────────
// 7. VARİATİON TITLE JS GÜNCELLEME (found_variation event)
// Variation seçince title/desc JS ile güncellenir.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_footer', function() {
    global $post;
    if ( ! is_product() ) return;
    $product = wc_get_product( $post->ID );
    if ( ! $product || ! $product->is_type( 'variable' ) ) return;

    // Her variation için başlık ve açıklama verisi hazırla
    $data          = [];
    $product_title = $product->get_title();
    $product_desc  = $product->get_description();

    foreach ( $product->get_visible_children() as $variation_id ) {
        $var_product = wc_get_product( $variation_id );
        if ( ! $var_product ) continue;

        $raw_attrs = $product->get_available_variation( $variation_id )['attributes'] ?? [];

        // Tüm attribute'ları dinamik olarak key → human-readable label şeklinde topla
        $variation_attrs = [];
        foreach ( $raw_attrs as $attr_key => $slug ) {
            if ( empty( $slug ) ) continue;
            $taxonomy = str_replace( 'attribute_', '', $attr_key );
            // Taxonomy tabanlı attribute → term adını al
            if ( taxonomy_exists( $taxonomy ) ) {
                $term = get_term_by( 'slug', $slug, $taxonomy );
                $variation_attrs[ $taxonomy ] = $term ? $term->name : $slug;
            } else {
                // Custom (text/select) attribute — slug = değer
                $variation_attrs[ $taxonomy ] = $slug;
            }
        }

        $variation_title = get_post_meta( $variation_id, 'variation_title', true ) ?: '';
        $variation_desc  = $var_product->get_description() ?: wpautop( wptexturize( $product_desc ) );

        $data[ $variation_id ] = [
            'id'           => $variation_id,
            'attrs'        => $variation_attrs,   // dinamik: tüm attribute'lar
            'parent_title' => $product_title,
            'title'        => $variation_title,
            'description'  => ( $var_product->get_sku() ? '<strong>SKU: ' . esc_html( $var_product->get_sku() ) . '</strong><br>' : '' ) . $variation_desc,
        ];
    }

    if ( empty( $data ) ) return;

    // XT Variations as Singles aktifse JS injection gerekmez
    if ( defined( 'XT_WOOVAS' ) ) return;

    // URL'den gelen variation ID'sini PHP'de detect et — ilk render'da anlık set için
    $initial_variation_id = 0;
    $var_lookup = [];
    foreach ( array_keys( $product->get_variation_attributes() ) as $taxonomy ) {
        $req_key = 'attribute_' . $taxonomy;
        if ( ! empty( $_REQUEST[ $req_key ] ) ) {
            $var_lookup[ $req_key ] = sanitize_title( wp_unslash( $_REQUEST[ $req_key ] ) );
        }
    }
    if ( ! empty( $var_lookup ) && class_exists( 'WC_Data_Store' ) ) {
        $initial_variation_id = (int) \WC_Data_Store::load('product')->find_matching_product_variation( $product, $var_lookup );
    }
    ?>
    <script>
    (function($) {
        var variationsData       = <?php echo wp_json_encode( $data ); ?>;
        var productTitle         = <?php echo wp_json_encode( $product_title ); ?>;
        var productDesc          = <?php echo wp_json_encode( wpautop( wptexturize( $product->get_description() ) ) ); ?>;
        var initialVariationId   = <?php echo (int) $initial_variation_id; ?>;

        /**
         * Variation label'ını oluştur:
         * 1. Önce kayıtlı variation_title var mı bak
         * 2. Yoksa tüm attr değerlerini " - " ile birleştir (ör. "Kırmızı - L")
         * 3. Hiç attr yoksa parent title kullan
         */
        function buildVariationTitle( d ) {
            if ( d.title ) return d.title;
            var attrVals = d.attrs ? Object.values( d.attrs ) : [];
            if ( attrVals.length ) return productTitle + ' - ' + attrVals.join(' / ');
            return productTitle;
        }

        function updateVariationTitle( forceId ) {
            var variationId = forceId || $('input.variation_id').val();
            if ( ! variationId || ! variationsData[ variationId ] ) {
                // Seçim yok / reset — orjinal başlık + açıklama
                $('.product_title').text( productTitle );
                $('.product-description').html( productDesc );
                return;
            }
            var d = variationsData[ variationId ];
            var title = buildVariationTitle( d );
            $('.product_title').text( title );

            // Her taxonomy için data-attr="taxonomy" span'larını güncelle (varsa)
            if ( d.attrs ) {
                $.each( d.attrs, function( taxonomy, label ) {
                    $( '.title-' + taxonomy ).html( label );
                });
            }

            $('.product-description').html( d.description );
        }

        // found_variation tetiklenince ve reset_data'da güncelle
        $(document).on( 'found_variation', function( e, variation ) {
            setTimeout( updateVariationTitle, 150 );

            // ── Sticky ATC sync ──────────────────────────────────────────
            var $bar = $('#sh-sticky-atc');
            if ( $bar.length ) {
                // Fiyat — variation objesinden direkt al
                if ( variation.price_html ) {
                    $bar.find('[data-sticky-price]').html( variation.price_html );
                }
                // Görsel — variation.image.thumb_src veya src
                if ( variation.image ) {
                    var imgSrc = variation.image.thumb_src || variation.image.src || '';
                    if ( imgSrc ) $bar.find('[data-sticky-img]').attr('src', imgSrc);
                }
                // Title — .product_title güncellenince oradan al (150ms sonra updateVariationTitle çalışır)
                setTimeout(function() {
                    var title = $('.product_title').text().trim();
                    if ( title ) $bar.find('[data-sticky-title]').text( title );
                }, 200);
            }
        });

        $(document).on( 'reset_data', function() {
            $('.product_title').text( productTitle );
            $('.product-description').html( productDesc );
            // Short description (başlık altındaki) ve tab içindeki description'ı da güncelle
            $('.woocommerce-product-details__short-description').html( productDesc );
            $('.woocommerce-variation-description').empty();
            // Diğer handler'lar sonra yazabilir — gecikmeli de uygula
            setTimeout(function(){
                $('.woocommerce-product-details__short-description').html( productDesc );
                $('.woocommerce-variation-description').empty();
                $('.product-description').html( productDesc );
            }, 200);

            // ── Sticky ATC reset ─────────────────────────────────────────
            var $bar = $('#sh-sticky-atc');
            if ( $bar.length ) {
                // Title
                $bar.find('[data-sticky-title]').text( productTitle );
                // Fiyat — DOM'dan al (reset sonrası range price görünür)
                setTimeout(function() {
                    var $price = $('.summary .price').not('.woocommerce-variation-price .price').first();
                    if ( $price.length ) $bar.find('[data-sticky-price]').html( $price.html() );
                }, 150);
                // Görsel — galeri reset sonrası ilk img
                setTimeout(function() {
                    var $firstImg = $('[data-product-gallery]').find('.product-gallery__img').first();
                    if ( $firstImg.length ) {
                        var src = $firstImg.attr('src') || $firstImg.data('src');
                        if ( src && src.indexOf('data:') !== 0 ) {
                            $bar.find('[data-sticky-img]').attr('src', src);
                        }
                    }
                }, 400);
            }
        });

        // Sayfa ilk açılışında:
        // - Variation URL ile geldiyse (initialVariationId > 0) → anında set et (0ms)
        //   WC form henüz hazır olmayabilir, DOMContentLoaded + kısa bekleme
        // - Normal açılışta → 400ms bekle (WC form init tamamlansın)
        if ( initialVariationId > 0 && variationsData[ initialVariationId ] ) {
            // input.variation_id WC tarafından set edilmeden önce manual update
            $(document).ready(function() {
                updateVariationTitle( initialVariationId );
                // WC form init sonrası tekrar güncelle (input.variation_id set oldu)
                setTimeout( updateVariationTitle, 200 );
            });
        } else {
            setTimeout( updateVariationTitle, 400 );
        }
    })(jQuery);
    </script>
    <?php
} );


// ─────────────────────────────────────────────────────────────────────────────
// 8. STICKY ADD TO CART
// sh_woo_sticky_atc action ile render edilir.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'sh_woo_sticky_atc', function() {
    if ( ! is_singular( 'product' ) ) return;
    $cfg = (array) get_option( 'options_sticky_atc', [] );
    if ( empty( $cfg['enabled'] ) ) return;

    global $post;
    if ( ! wc_get_product( $post->ID ) ) return;

    if ( class_exists( 'Timber\Timber' ) ) {
        \Timber\Timber::render( 'woo/partials/sticky-atc.twig', [
            'post'           => \Timber\Timber::get_post(),
            'sticky_atc_cfg' => $cfg,
        ] );
    }
} );

// Varsayılan konum — manual placement kapalıysa sayfa sonuna ekle
add_action( 'woocommerce_after_single_product', function() {
    $cfg = (array) get_option( 'options_sticky_atc', [] );
    if ( empty( $cfg['enabled'] ) || ! empty( $cfg['manual_placement'] ) ) return;
    do_action( 'sh_woo_sticky_atc' );
}, 99 );


// ─────────────────────────────────────────────────────────────────────────────
// 8. WC NATIVE RELATED / UPSELL KALDIRMA
// product-list.twig ile yönetiyoruz.
// ─────────────────────────────────────────────────────────────────────────────

remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
remove_action( 'woocommerce_after_single_product', 'woocommerce_output_related_products', 9 );


// ─────────────────────────────────────────────────────────────────────────────
// 9. REVIEWS — Salt Reviews App
// ─────────────────────────────────────────────────────────────────────────────

/**
 * sh_product_reviews action — product-tab-content.twig'den tetiklenir.
 * Tüm variation'ların yorumları ana üründe toplanır (Amazon pattern).
 */
add_action( 'sh_product_reviews', function( $post_id ) {
    if ( ! $post_id ) return;

    if ( ! class_exists( '\\SaltHareket\\Reviews\\Reviews' ) ) {
        comments_template();
        return;
    }

    // Variation ise parent'ı kullan
    $review_post_id = (int) $post_id;
    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        if ( $product && $product->is_type( 'variation' ) ) {
            $review_post_id = (int) $product->get_parent_id();
        }
    }

    $user_id     = get_current_user_id();
    $reviews_app = new \SaltHareket\Reviews\Reviews( $user_id );

    $sort       = sanitize_key( $_GET['reviews_sort'] ?? 'recent' );
    if ( ! in_array( $sort, [ 'recent', 'oldest', 'rating_high', 'rating_low', 'helpful' ], true ) ) $sort = 'recent';

    $page    = max( 1, (int) ( $_GET['reviews_page'] ?? 1 ) );
    $sort_q  = ( $sort === 'oldest' ) ? null : $sort;
    $order_q = ( $sort === 'oldest' ) ? 'ASC' : 'DESC';

    $result = $reviews_app->getForPost( $review_post_id, [
        'per_page' => 10,
        'page'     => $page,
        'sort'     => $sort_q,
        'order'    => $order_q,
    ] );

    $stats            = \SaltHareket\Reviews\Reviews::stats( $review_post_id, 'post' );
    $reviews_settings = class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' ) ? \SaltHareket\Reviews\ReviewsSettings::all() : [];
    $reviews_criteria = class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' ) ? \SaltHareket\Reviews\ReviewsSettings::getCriteria( 'product' ) : [];
    $rating_type      = $reviews_settings['rating']['type'] ?? 'stars';
    if ( $rating_type === 'multi' && empty( $reviews_criteria ) ) $rating_type = 'stars';

    // criteria_averages: criteria varsa HER ZAMAN hesapla (rating_type kontrolü yok)
    // PHP'de hesaplanıyor — MySQL JSON_EXTRACT versiyonuna bağımlılık yok
    if ( ! empty( $reviews_criteria ) ) {
        global $wpdb;

        // Bu ürünün tüm onaylı review'larından criteria_ratings meta'larını çek
        // comment_approved '1' string olarak saklanıyor (WP convention)
        $meta_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT cm.meta_value
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID
                 AND cm.meta_key = 'criteria_ratings'
             WHERE c.comment_post_ID = %d
               AND c.comment_type = 'review'
               AND c.comment_approved = '1'",
            $review_post_id
        ), ARRAY_A );

        // DEBUG — geçici, sorun tespiti sonrası kaldır
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[CriteriaAvg] post_id=' . $review_post_id . ' meta_rows=' . count($meta_rows) . ' criteria=' . json_encode(array_column($reviews_criteria,'key')) );
            if ( ! empty($meta_rows) ) error_log( '[CriteriaAvg] sample meta_value=' . $meta_rows[0]['meta_value'] );
        }

        // Her kriter için topla ve ortalama al
        $sums   = [];
        $counts = [];
        foreach ( $meta_rows as $meta_row ) {
            $vals = json_decode( $meta_row['meta_value'], true );
            if ( ! is_array( $vals ) ) {
                // maybe_unserialize dene — WP bazen serialize ediyor
                $vals = maybe_unserialize( $meta_row['meta_value'] );
            }
            if ( ! is_array( $vals ) ) continue;
            foreach ( $vals as $k => $v ) {
                $v = (float) $v;
                if ( $v <= 0 ) continue;
                $sums[ $k ]   = ( $sums[ $k ] ?? 0 ) + $v;
                $counts[ $k ] = ( $counts[ $k ] ?? 0 ) + 1;
            }
        }

        $criteria_averages = [];
        foreach ( $reviews_criteria as $criterion ) {
            $key = $criterion['key'] ?? '';
            if ( ! $key ) continue;
            $criteria_averages[ $key ] = isset( $sums[ $key ] ) && $counts[ $key ] > 0
                ? round( $sums[ $key ] / $counts[ $key ], 1 )
                : 0;
        }
        $stats['criteria_averages'] = $criteria_averages;

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[CriteriaAvg] result=' . json_encode($criteria_averages) );
        }
    }

    $has_reviewed = $user_id ? $reviews_app->has_reviewed( $user_id, $review_post_id, 'post' ) : false;
    $can_review   = $user_id && ! $has_reviewed ? $reviews_app->can_review( $user_id, $review_post_id, 'post' ) : false;

    if ( class_exists( 'Timber\Timber' ) ) {
        // Types config'ten product için review_modes al
        $type_config  = class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' )
            ? \SaltHareket\Reviews\ReviewsSettings::getTypeConfig( 'post', 'product' )
            : [ 'modes' => [ 'rate', 'comment' ] ];
        $review_modes = $type_config['modes'] ?? [ 'rate', 'comment' ];

        \Timber\Timber::render( 'partials/post-reviews/reviews.twig', [
            'post'             => \Timber\Timber::get_post( $review_post_id ),
            'post_id'          => $review_post_id,
            'target_type'      => 'post',
            'reviews'          => $result['reviews'] ?? [],
            'reviews_data'     => $result['data']    ?? [],
            'reviews_stats'    => $stats,
            'has_reviewed'     => $has_reviewed,
            'can_review'       => $can_review,
            'current_sort'     => $sort,
            'reviews_settings' => $reviews_settings,
            'reviews_criteria' => $reviews_criteria,
            'rating_type'      => $rating_type,
            'review_modes'     => $review_modes,
        ] );
    }
}, 10, 1 );


/**
 * sh_reviews_fetch AJAX handler — sort/pagination için.
 */
add_action( 'wp_ajax_sh_reviews_fetch',        'sh_reviews_fetch_handler' );
add_action( 'wp_ajax_nopriv_sh_reviews_fetch', 'sh_reviews_fetch_handler' );

function sh_reviews_fetch_handler(): void {
    if ( ! check_ajax_referer( 'sh_reviews_fetch', '_wpnonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
    }

    $post_id = (int) ( $_POST['post_id'] ?? 0 );
    if ( $post_id < 1 ) wp_send_json_error( [ 'message' => 'Invalid post' ] );

    if ( ! class_exists( '\\SaltHareket\\Reviews\\Reviews' ) || ! class_exists( 'Timber\\Timber' ) ) {
        wp_send_json_error( [ 'message' => 'Reviews not available' ] );
    }

    // Variation → parent
    $review_post_id = $post_id;
    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        if ( $product && $product->is_type( 'variation' ) ) $review_post_id = (int) $product->get_parent_id();
    }

    $sort = sanitize_key( $_POST['sort'] ?? 'recent' );
    if ( ! in_array( $sort, [ 'recent', 'oldest', 'rating_high', 'rating_low', 'helpful' ], true ) ) $sort = 'recent';

    $page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );
    $rating = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : null;
    if ( $rating !== null && ( $rating < 1 || $rating > 5 ) ) $rating = null;

    $sort_q  = ( $sort === 'oldest' ) ? null : $sort;
    $order_q = ( $sort === 'oldest' ) ? 'ASC' : 'DESC';

    $reviews_app = new \SaltHareket\Reviews\Reviews( get_current_user_id() );
    $args        = [ 'per_page' => 10, 'page' => $page, 'sort' => $sort_q, 'order' => $order_q ];
    if ( $rating !== null ) $args['rating'] = $rating;

    $result           = $reviews_app->getForPost( $review_post_id, $args );
    $reviews_settings = class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' ) ? \SaltHareket\Reviews\ReviewsSettings::all() : [];
    $reviews_criteria = class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' ) ? \SaltHareket\Reviews\ReviewsSettings::getCriteria( 'product' ) : [];
    $rating_type      = $reviews_settings['rating']['type'] ?? 'stars';
    if ( $rating_type === 'multi' && empty( $reviews_criteria ) ) $rating_type = 'stars';

    $list_html = \Timber\Timber::compile( 'partials/post-reviews/_review-list.twig', [
        'reviews'          => $result['reviews'] ?? [],
        'reviews_settings' => $reviews_settings,
        'reviews_criteria' => $reviews_criteria,
        'rating_type'      => $rating_type,
    ] );

    $pagination_html = \Timber\Timber::compile( 'partials/post-reviews/_pagination.twig', [
        'reviews_data' => $result['data'] ?? [],
    ] );

    wp_send_json_success( [
        'html'            => $list_html ?: '',
        'pagination_html' => $pagination_html ?: '',
        'sort_label'      => [
            'recent'      => __( 'Most Recent',   'salthareket' ),
            'oldest'      => __( 'Oldest',        'salthareket' ),
            'rating_high' => __( 'Highest Rated', 'salthareket' ),
            'rating_low'  => __( 'Lowest Rated',  'salthareket' ),
            'helpful'     => __( 'Most Helpful',  'salthareket' ),
        ][ $sort ] ?? __( 'Most Recent', 'salthareket' ),
        'data'            => $result['data'] ?? [],
    ] );
}


/**
 * sh_product_rating_summary action — title altında rating özeti.
 * single-product.twig: {% do action('sh_product_rating_summary', post.id) %}
 */
add_action( 'sh_product_rating_summary', function( $post_id ) {
    if ( ! $post_id || ! defined( 'ENABLE_REVIEWS' ) || ! ENABLE_REVIEWS ) return;
    if ( ! class_exists( '\\SaltHareket\\Reviews\\Reviews' ) ) return;

    $rating_post_id = (int) $post_id;
    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        if ( $product && $product->is_type( 'variation' ) ) $rating_post_id = (int) $product->get_parent_id();
    }

    $avg   = (float) get_post_meta( $rating_post_id, '_post_rating',       true );
    $count = (int)   get_post_meta( $rating_post_id, '_post_rating_count', true );

    if ( class_exists( 'Timber\Timber' ) ) {
        \Timber\Timber::render( 'partials/post-reviews/rating-summary.twig', [
            'avg'            => round( $avg, 1 ),
            'count'          => $count,
            'rating_post_id' => $rating_post_id,
        ] );
    }
}, 10, 1 );


// ─────────────────────────────────────────────────────────────────────────────
// 10. WC EK BİLGİLER — sh_wc_additional_information
// product-tab-content.twig'den tetiklenir.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'sh_wc_additional_information', function( $post_id ) {
    if ( ! $post_id ) return;
    $product = wc_get_product( $post_id );
    if ( ! $product ) return;
    global $product; // WC hook'ları global $product kullanıyor
    wc_display_product_attributes( $product );
}, 10, 1 );


// ─────────────────────────────────────────────────────────────────────────────
// 11. PRODUCTSTATS VIEW COUNT
// Her ürün sayfası görüntülenişinde sayaç artar (cookie ile spam koruması).
// ─────────────────────────────────────────────────────────────────────────────
// Not: ProductStats::incrementView() woocommerce.php'de çağrılıyor,
// bu kısım hook ihtiyacı doğarsa buraya eklenecek.


// ─────────────────────────────────────────────────────────────────────────────
// 12. SH_PRODUCT_STATS ACTION
// Twig: {% do action('sh_product_stats', post.id) %}
// ─────────────────────────────────────────────────────────────────────────────
// bootstrap.php'de tanımlı — burada tekrar tanımlama gerekmez.

