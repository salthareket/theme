<?php
/**
 * WooCommerce Variation URL
 *
 * Plugin woocommerce-variation-links'ten alındı (Václav Greif, refactored).
 * Namespace kaldırıldı, temaya entegre edildi.
 *
 * Değişiklikler:
 *   - Namespace kaldırıldı
 *   - add_js_to_footer: variation seçince URL replaceState (her zaman aktif)
 *   - auto_select_first_variation: sh_woo_single setting'den okunur
 *
 * @version 1.1
 */


if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'ENABLE_ECOMMERCE' ) || ! ENABLE_ECOMMERCE ) return;

final class SHVariationUrl {

    private static ?SHVariationUrl $inst = null;

    public static function instance(): SHVariationUrl {
        if ( self::$inst === null ) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    private function __construct() {
        add_filter( 'woocommerce_dropdown_variation_attribute_options_args', [ $this, 'variation_dropdown_args' ] );
        add_action( 'init', [ $this, 'register_query_var' ], 1 );
        add_action( 'init', [ $this, 'add_rewrite_rules' ], 10 );
        add_action( 'wp_footer', [ $this, 'add_js_to_footer' ] );
    }

    public function register_query_var(): void {
        add_rewrite_tag( '%variation%', '([^&]+)' );
    }

    public function add_rewrite_rules(): void {
        $slugs = $this->get_product_slugs_all_langs();

        $lang_prefixes = [ '' ];
        if ( function_exists( 'pll_languages_list' ) ) {
            $langs = pll_languages_list( [ 'fields' => 'slug' ] );
            foreach ( $langs as $l ) {
                $lang_prefixes[] = $l . '/';
            }
        }

        foreach ( $slugs as $slug ) {
            $slug = trim( $slug, '/' );
            if ( empty( $slug ) ) continue;

            foreach ( $lang_prefixes as $prefix ) {
                add_rewrite_rule(
                    '^' . preg_quote( $prefix . $slug, '/' ) . '/([^/]+)/([^/]+)/?$',
                    'index.php?product=$matches[1]&variation=$matches[2]',
                    'top'
                );
            }
        }

        // Rule yoksa flush et
        $rules   = get_option( 'rewrite_rules', [] );
        $missing = false;
        foreach ( $slugs as $slug ) {
            $slug    = trim( $slug, '/' );
            if ( empty( $slug ) ) continue;
            $pattern = '^' . preg_quote( $slug, '/' ) . '/([^/]+)/([^/]+)/?$';
            if ( ! isset( $rules[ $pattern ] ) ) { $missing = true; break; }
        }
        if ( $missing ) {
            flush_rewrite_rules( false );
        }
    }

    private function get_product_slugs_all_langs(): array {
        $slugs  = [];
        $pt_obj = get_post_type_object( 'product' );
        $slugs[] = ( $pt_obj && ! empty( $pt_obj->rewrite['slug'] ) )
            ? trim( $pt_obj->rewrite['slug'], '/' )
            : 'product';

        if ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_get_post_type_slug' ) ) {
            foreach ( pll_languages_list( [ 'fields' => 'slug' ] ) as $lang ) {
                $s = trim( pll_get_post_type_slug( 'product', $lang ), '/' );
                if ( $s ) $slugs[] = $s;
            }
        }

        return array_unique( array_filter( $slugs ) );
    }

    public function variation_dropdown_args( array $args ): array {
        $url_string = get_query_var( 'variation' );
        if ( empty( $url_string ) ) return $args;

        $parsed = $this->parse_variation_string( $url_string );
        if ( empty( $parsed ) ) return $args;

        $attribute_key = strtolower( str_replace( 'pa_', '', $args['attribute'] ) );
        if ( array_key_exists( $attribute_key, $parsed ) ) {
            $args['selected'] = $parsed[ $attribute_key ];
        }

        return $args;
    }

    private function parse_variation_string( string $url_string ): array {
        static $cache = [];
        if ( isset( $cache[ $url_string ] ) ) return $cache[ $url_string ];

        $result = [];

        $attribute_slugs = [];
        foreach ( wc_get_attribute_taxonomies() as $attr ) {
            $attribute_slugs[] = strtolower( $attr->attribute_name );
        }
        usort( $attribute_slugs, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );

        if ( empty( $attribute_slugs ) ) {
            return $cache[ $url_string ] = $result;
        }

        $escaped = array_map( 'preg_quote', $attribute_slugs );
        $pattern = '/(?:^|-)(' . implode( '|', $escaped ) . ')-([^-].*?)(?=(?:-(?:' . implode( '|', $escaped ) . '))|$)/';

        if ( preg_match_all( $pattern, '-' . $url_string, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $result[ $match[1] ] = rtrim( $match[2], '-' );
            }
        }

        return $cache[ $url_string ] = $result;
    }

    public function add_js_to_footer(): void {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

        global $post;
        $product_url = trailingslashit( get_permalink( $post->ID ) );

        // auto_select_first_variation setting — JS'e geç
        $woo_single    = (array) get_option( 'sh_woo_single', [] );
        $auto_redirect = (bool) ( $woo_single['auto_select_first_variation'] ?? false );
        ?>
        <script>
        (function($) {
            var baseUrl      = <?php echo wp_json_encode( $product_url ); ?>;
            var autoRedirect = <?php echo $auto_redirect ? 'true' : 'false'; ?>;

            function buildVariationUrl() {
                var parts  = [];
                var allSet = true;

                $('.variations select').each(function() {
                    var val = $(this).val();
                    if ( val ) {
                        var attrSlug = $(this).attr('name')
                            .replace('attribute_pa_', '')
                            .replace('attribute_', '');
                        parts.push( attrSlug + '-' + val );
                    } else {
                        allSet = false;
                    }
                });

                if ( allSet && parts.length ) {
                    return baseUrl + parts.join('-') + '/';
                }
                return baseUrl;
            }

            $(document).ready(function() {
                // autoRedirect kapalıysa sayfa yüklenirken yapılan auto-select'i engelle
                // Ama URL'de zaten variation varsa (variation-url rewrite) reset etme
                var hasVariationInUrl = (function() {
                    var path = window.location.pathname;
                    var base = baseUrl.replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '');
                    var rest = path.replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '');
                    // baseUrl'den fazla path segment varsa variation var
                    return rest.length > base.length && rest.indexOf(base) === 0;
                })();

                if ( ! autoRedirect && ! hasVariationInUrl ) {
                    // Swatches auto-select'i engelle — found_variation gelince reset et
                    var _blockAutoSelect = true;
                    setTimeout(function(){ _blockAutoSelect = false; }, 1000);

                    $(document).on('found_variation', '.variations_form', function() {
                        if ( _blockAutoSelect ) {
                            var $form = $(this);
                            setTimeout(function(){
                                $form.find('.reset_variations').trigger('click');
                                // reset_variations async olabilir — butonu da direkt disable et
                                setTimeout(function(){
                                    var $btn = $form.find('.single_add_to_cart_button');
                                    if ( ! $btn.hasClass('wc-variation-is-matched') ) {
                                        $btn.addClass('disabled wc-variation-selection-needed').prop('disabled', true);
                                    }
                                }, 50);
                            }, 0);
                        }
                    });
                }

                $(document).on('change', '.variations select', function() {
                    history.replaceState( null, '', buildVariationUrl() );
                });

                $(document).on('found_variation', '.variations_form', function() {
                    if ( ! _blockAutoSelect ) {
                        // Kullanıcı manuel variation seçti — butonu enable et
                        $(this).find('.single_add_to_cart_button')
                            .removeClass('disabled wc-variation-selection-needed')
                            .prop('disabled', false);
                    }
                });

                $(document).on('found_variation', function() {
                    if ( autoRedirect ) {
                        history.replaceState( null, '', buildVariationUrl() );
                    }
                });

                $(document).on('reset_data', function() {
                    if ( ! hasVariationInUrl ) {
                        history.replaceState( null, '', baseUrl );
                    }
                });
            });
        }(jQuery));
        </script>
        <?php
    }
}

SHVariationUrl::instance();


// ── variation_url_rewrite — SEO-friendly variation URL helper ────────────────
// post.php variation_urls_by_attr() fallback olarak bunu çağırır.
// Parametre formatını (?attribute_pa_color=red) SEO-friendly URL'ye çevirir:
// /product/urun-adi/color-red/
//
// @param  string $param_url  ?attribute_pa_{attr}={value} içeren URL
// @return string             SEO-friendly variation URL
if ( ! function_exists( 'variation_url_rewrite' ) ) {
    function variation_url_rewrite( string $param_url ): string {
        $parts = parse_url( $param_url );
        if ( empty( $parts['query'] ) ) return $param_url;

        $base = strtok( $param_url, '?' );
        $base = trailingslashit( $base );

        parse_str( $parts['query'], $query_vars );

        $slugs = [];
        foreach ( $query_vars as $key => $value ) {
            if ( strpos( $key, 'attribute_pa_' ) === 0 ) {
                $attr  = str_replace( 'attribute_pa_', '', $key );
                $slugs[] = $attr . '-' . $value;
            } elseif ( strpos( $key, 'attribute_' ) === 0 ) {
                $attr  = str_replace( 'attribute_', '', $key );
                $slugs[] = $attr . '-' . $value;
            }
        }

        if ( empty( $slugs ) ) return $base;

        return $base . implode( '-', $slugs ) . '/';
    }
}
