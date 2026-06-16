<?php

namespace SaltHareket\Localization\Concerns;

/**
 * HandlesRegionalPosts
 *
 * Regional post/term filtreleme — ACF bağımlılığı yok.
 * - "region" taxonomy'sini register eder
 * - Her region term'ine ülke seçimi için Select2 destekli form ekler
 * - Regions listesinde "Countries" kolonu gösterir
 * - pre_get_posts ile query'lere region filtresi ekler
 * - Country code → Region ID çözümlemesi
 * - Filtre mantığı: "Region atanmamış (global) VEYA kullanıcının region'ına atanmış"
 *
 * @version 3.0.0
 * @changelog
 *   3.0.0 - 2026-06-16
 *     - Port: salt-next versiyonu alındı — ACF bağımlılığı kaldırıldı
 *     - Add: Select2 ile ülke seçimi (WC varsa WC'nin kopyası, yoksa CDN)
 *     - Add: Regions listesinde "Countries" kolonu (badge'li görünüm)
 *     - Fix: filterRegionalPosts mantığı — "region atanmamış OR kullanıcının region'ı" doğru filtre
 *     - Fix: getRegionalPostTypes/Taxonomies'den static cache kaldırıldı (init öncesi boş kalıyordu)
 *     - Fix: registerRegionTaxonomy — 'acf/include_fields' yerine 'init' hook'unda çalışır
 *     - Fix: getRegionByCountryCode — Timber::get_terms yerine WP native get_terms + term_meta
 *     - Fix: registerRegionalPosts() — constant kontrolü kaldırıldı, LocationSettings'den okur
 *   1.0.0 - 2026-05-09 — Initial release
 *
 * ─── FILTER LOGIC ─────────────────────────────────────────
 *
 * Kullanıcı DE'den geliyor, "Europe" region'ı DE içeriyor:
 *   → "Region atanmamış (global)" VEYA "Europe region'ına atanmış" postlar gösterilir
 *
 * Kullanıcı TR'den geliyor, TR hiçbir region'da yok:
 *   → Sadece "Region atanmamış (global)" postlar gösterilir
 *   → Belirli region'a atanmış postlar GİZLENİR
 */
trait HandlesRegionalPosts
{
    // ─── Register ────────────────────────────────────────────────────────────

    /**
     * Regional posts hook'larını register et.
     * bootstrap.php'den çağrılır — constant bağımlılığı yok.
     */
    public function registerRegionalPosts(): void
    {
        add_action( 'init',                            [ $this, 'registerRegionTaxonomy' ] );
        add_action( 'region_add_form_fields',          [ $this, 'regionAddFormFields' ] );
        add_action( 'region_edit_form_fields',         [ $this, 'regionEditFormFields' ], 10, 2 );
        add_action( 'created_region',                  [ $this, 'saveRegionMeta' ] );
        add_action( 'edited_region',                   [ $this, 'saveRegionMeta' ] );
        add_action( 'pre_get_posts',                   [ $this, 'filterRegionalPosts' ] );
        add_filter( 'manage_edit-region_columns',      [ $this, 'regionColumns' ] );
        add_filter( 'manage_region_custom_column',     [ $this, 'regionColumnContent' ], 10, 3 );
        // Post editörde güzel region seçici meta box
        add_action( 'add_meta_boxes',                  [ $this, 'addRegionMetaBox' ] );
    }

    // ─── Taxonomy ────────────────────────────────────────────────────────────

    public function registerRegionTaxonomy(): void
    {
        $post_types = $this->getRegionalPostTypes();
        // Mapping yoksa bile 'post' ile register et — taxonomy yoksa term eklenemez
        if ( empty( $post_types ) ) {
            $post_types = [ 'post' ];
        }

        register_taxonomy( 'region', $post_types, [
            'public'            => true,
            'show_admin_column' => true,
            'labels'            => [
                'name'          => 'Regions',
                'singular_name' => 'Region',
                'menu_name'     => 'Regions',
                'all_items'     => 'All Regions',
                'edit_item'     => 'Edit Region',
                'add_new_item'  => 'Add New Region',
            ],
            'rewrite'      => [ 'with_front' => false ],
            'capabilities' => [
                'manage_terms' => 'edit_posts',
                'edit_terms'   => 'edit_posts',
                'delete_terms' => 'edit_posts',
                'assign_terms' => 'read',
            ],
        ] );
    }

    // ─── Meta Box — Countries ─────────────────────────────────────────────────

    /**
     * "Add Region" formuna ülke seçimi ekle.
     */
    public function regionAddFormFields(): void
    {
        $countries = $this->getCountryOptions();
        $this->enqueueSelect2();
        ?>
        <div class="form-field">
            <label for="region_countries">Countries</label>
            <select name="region_countries[]" id="region_countries_add" class="sh-region-select2" multiple style="width:100%">
                <?php foreach ( $countries as $code => $name ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)</option>
                <?php endforeach; ?>
            </select>
            <p>Search and select countries for this region.</p>
        </div>
        <script>jQuery(function($){ $('#region_countries_add').select2({ width:'100%', placeholder:'Search countries...', allowClear:true }); });</script>
        <?php
    }

    /**
     * "Edit Region" formuna ülke seçimi ekle.
     */
    public function regionEditFormFields( \WP_Term $term ): void
    {
        $countries = $this->getCountryOptions();
        $selected  = (array) get_term_meta( $term->term_id, 'countries', true );
        $this->enqueueSelect2();
        ?>
        <tr class="form-field">
            <th><label for="region_countries_edit">Countries</label></th>
            <td>
                <select name="region_countries[]" id="region_countries_edit" class="sh-region-select2" multiple style="width:100%;max-width:500px">
                    <?php foreach ( $countries as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php echo in_array( $code, $selected, true ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Search and select countries for this region.</p>
                <script>jQuery(function($){ $('#region_countries_edit').select2({ width:'100%', placeholder:'Search countries...', allowClear:true }); });</script>
            </td>
        </tr>
        <?php
    }

    /**
     * Select2 enqueue — WooCommerce varsa WC'nin kopyasını kullan, yoksa CDN.
     */
    private function enqueueSelect2(): void
    {
        if ( wp_script_is( 'select2', 'registered' ) ) {
            wp_enqueue_script( 'select2' );
            wp_enqueue_style( 'select2' );
        } elseif ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
            wp_enqueue_script( 'wc-enhanced-select' );
            wp_enqueue_style( 'woocommerce_admin_styles' );
        } else {
            wp_enqueue_script( 'sh-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], '4.1.0', true );
            wp_enqueue_style( 'sh-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0' );
        }
    }

    /**
     * Region term meta kaydet.
     */
    public function saveRegionMeta( int $term_id ): void
    {
        if ( ! isset( $_POST['region_countries'] ) ) {
            delete_term_meta( $term_id, 'countries' );
            return;
        }
        $countries = array_map( 'sanitize_text_field', (array) $_POST['region_countries'] );
        update_term_meta( $term_id, 'countries', $countries );
    }

    /**
     * Regions listesine "Countries" kolonu ekle.
     */
    public function regionColumns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'name' ) {
                $new['countries'] = 'Countries';
            }
        }
        return $new;
    }

    /**
     * "Countries" kolon içeriğini render et.
     */
    public function regionColumnContent( string $content, string $column, int $term_id ): string
    {
        if ( $column !== 'countries' ) return $content;

        $selected = (array) get_term_meta( $term_id, 'countries', true );
        if ( empty( $selected ) ) return '<span style="color:#9ca3af">—</span>';

        $all = $this->getCountryOptions();
        $badges = [];
        foreach ( $selected as $code ) {
            $name     = $all[ $code ] ?? $code;
            $badges[] = '<span style="display:inline-block;background:#dbeafe;color:#1d4ed8;border-radius:4px;padding:1px 7px;font-size:11px;margin:1px">'
                . esc_html( $code )
                . ' <span style="color:#6b7280">' . esc_html( $name ) . '</span></span>';
        }
        return implode( ' ', $badges );
    }

    // ─── Query Filter ─────────────────────────────────────────────────────────

    public function filterRegionalPosts( \WP_Query $query ): void
    {
        if ( is_admin() || ! $query->is_main_query() ) return;

        $settings = \SaltHareket\Localization\LocationSettings::get();
        if ( empty( $settings['enable_regional_posts'] ) ) return;
        if ( empty( $settings['regional_post_settings'] ) ) return;

        // Kullanıcının ülkesini al — cookie varsa kullan, yoksa IP'den çöz
        $country_code = '';
        if ( ! empty( $_COOKIE['user_country_code'] ) ) {
            $country_code = strtoupper( sanitize_text_field( $_COOKIE['user_country_code'] ) );
        } else {
            $country_code = $this->ip2Country();
            if ( $country_code && ! headers_sent() ) {
                setcookie( 'user_country_code', $country_code, time() + YEAR_IN_SECONDS, '/' );
            }
        }

        // Post type bu mapping'de var mı?
        $post_type    = $query->get( 'post_type' ) ?: 'post';
        $mapped_types = $this->getRegionalPostTypes();
        $should_filter = false;

        if ( in_array( $post_type, $mapped_types, true ) ) $should_filter = true;
        if ( $query->is_search() ) $should_filter = true;
        if ( is_archive() ) {
            foreach ( $this->getRegionalTaxonomies() as $tax ) {
                if ( is_tax( $tax ) ) { $should_filter = true; break; }
            }
        }

        if ( ! $should_filter ) return;

        $user_region_ids = $country_code ? $this->getRegionByCountryCode( $country_code ) : [];

        $tax_query             = $query->get( 'tax_query' ) ?: [];
        $tax_query['relation'] = 'AND';

        if ( ! empty( $user_region_ids ) ) {
            // Kullanıcının region'ı var:
            // "Region atanmamış (global)" VEYA "kullanıcının region'ına atanmış"
            $tax_query[] = [
                'relation' => 'OR',
                [
                    'taxonomy' => 'region',
                    'operator' => 'NOT EXISTS',
                ],
                [
                    'taxonomy' => 'region',
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', $user_region_ids ),
                    'operator' => 'IN',
                ],
            ];
        } else {
            // Kullanıcının ülkesi hiçbir region'a atanmamış:
            // Sadece global içerik (region atanmamış) göster
            $tax_query[] = [
                'taxonomy' => 'region',
                'operator' => 'NOT EXISTS',
            ];
        }

        $query->set( 'tax_query', $tax_query );
    }

    // ─── Region Resolution ───────────────────────────────────────────────────

    /**
     * Country code → Region term ID'leri.
     * term_meta'dan okur — ACF/Timber bağımlılığı yok.
     */
    public function getRegionByCountryCode( string $code = '' ): array
    {
        if ( empty( $code ) ) return [];

        $code      = strtoupper( $code );
        $cache_key = 'sh_region_by_country_' . $code;
        $cached    = wp_cache_get( $cache_key, 'localization' );
        if ( $cached !== false ) return (array) $cached;

        $terms = get_terms( [
            'taxonomy'   => 'region',
            'hide_empty' => false,
        ] );

        $result = [];
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $countries = (array) get_term_meta( $term->term_id, 'countries', true );
                if ( in_array( $code, $countries, true ) ) {
                    $result[] = $term->term_id;
                }
            }
        }

        // Fallback: default region (boş döndüyse)
        if ( empty( $result ) ) {
            $settings = \SaltHareket\Localization\LocationSettings::get();
            $result   = array_map( 'intval', (array) ( $settings['region_main'] ?? [] ) );
        }

        wp_cache_set( $cache_key, $result, 'localization', 300 );
        return $result;
    }

    /**
     * Kullanıcının region'ını cookie'den al, yoksa IP'den tespit et.
     */
    public function resolveUserRegion(): array
    {
        if ( ! empty( $_COOKIE['user_region'] ) ) {
            $region = json_decode( stripslashes( $_COOKIE['user_region'] ), true );
            if ( is_array( $region ) && ! empty( $region ) ) return $region;
        }

        $country_code = $this->ip2Country();
        if ( ! $country_code ) return [];

        $region = $this->getRegionByCountryCode( $country_code );

        if ( ! empty( $region ) && ! headers_sent() ) {
            setcookie( 'user_region',       wp_json_encode( $region ), time() + YEAR_IN_SECONDS, '/' );
            setcookie( 'user_country_code', $country_code,             time() + YEAR_IN_SECONDS, '/' );
        }

        return $region;
    }

    // ─── Settings Helpers ────────────────────────────────────────────────────

    /**
     * Regional post type'ları döndür — static cache yok (init öncesi boş kalmaması için).
     */
    public function getRegionalPostTypes(): array
    {
        $settings = \SaltHareket\Localization\LocationSettings::get();
        return array_values( array_filter( array_unique(
            array_column( $settings['regional_post_settings'] ?? [], 'post_type' )
        ) ) );
    }

    /**
     * Regional taxonomy'leri döndür.
     */
    public function getRegionalTaxonomies(): array
    {
        $settings = \SaltHareket\Localization\LocationSettings::get();
        return array_values( array_filter( array_unique(
            array_column( $settings['regional_post_settings'] ?? [], 'taxonomy' )
        ) ) );
    }

    public static function getPostTypeTaxonomies( string $post_type ): array
    {
        $taxonomies = get_object_taxonomies( [ 'post_type' => $post_type ], 'objects' );
        return array_filter( $taxonomies, fn( $t ) => $t->public );
    }

    // ─── Post Editör Meta Box ─────────────────────────────────────────────────

    /**
     * Region meta box'ını post editöre ekle — sadece regional post type'larda.
     */
    public function addRegionMetaBox(): void
    {
        $post_types = $this->getRegionalPostTypes();
        if ( empty( $post_types ) ) return;

        foreach ( $post_types as $pt ) {
            // Classic editor: WP'nin varsayılan region taxonomy kutusunu kaldır
            remove_meta_box( 'regiondiv', $pt, 'side' );
            remove_meta_box( 'tagsdiv-region', $pt, 'side' );

            add_meta_box(
                'sh-region-selector',
                '🌍 Regions',
                [ $this, 'renderRegionMetaBox' ],
                $pt,
                'side',
                'high'  // sidebar'da en üste taşı
            );
        }

        // Block editor (Gutenberg): taxonomy panelini CSS ile gizle + meta box'ı üste taşı
        add_action( 'admin_head', function() {
            $screen = get_current_screen();
            if ( ! $screen ) return;
            $post_types = $this->getRegionalPostTypes();
            if ( ! in_array( $screen->post_type, $post_types, true ) ) return;
            ?>
            <style>
                /* Gutenberg'deki varsayılan Region taxonomy panelini gizle */
                .components-panel__body[aria-label*="Region"],
                [data-slug="region"] { display: none !important; }
                /* Classic editor'de sh-region-selector'ı en üste sabitle */
                #sh-region-selector { order: -1; }
            </style>
            <script>
            // Classic editor: meta box'ı sidebar'ın en üstüne taşı
            document.addEventListener('DOMContentLoaded', function() {
                var box = document.getElementById('sh-region-selector');
                var sidebar = document.getElementById('side-sortables');
                if (box && sidebar && sidebar.firstChild) {
                    sidebar.insertBefore(box, sidebar.firstChild);
                }
            });
            </script>
            <?php
        } );
    }

    /**
     * Region meta box — Select2 multi-select, seçilince ülke badge'leri göster.
     */
    public function renderRegionMetaBox( \WP_Post $post ): void
    {
        $selected_terms = wp_get_post_terms( $post->ID, 'region', [ 'fields' => 'ids' ] );
        $selected_ids   = is_wp_error( $selected_terms ) ? [] : $selected_terms;

        $all_regions = get_terms( [ 'taxonomy' => 'region', 'hide_empty' => false ] );
        if ( is_wp_error( $all_regions ) ) $all_regions = [];

        $all_countries = $this->getCountryOptions();

        // Her region için ülke verisini JS'e aktar
        $regions_data = [];
        foreach ( $all_regions as $term ) {
            $countries = (array) get_term_meta( $term->term_id, 'countries', true );
            $regions_data[ $term->term_id ] = [
                'name'      => $term->name,
                'slug'      => $term->slug,
                'countries' => $countries,
                'count'     => $term->count,
            ];
        }

        $this->enqueueSelect2();
        wp_nonce_field( 'sh_region_meta_box', 'sh_region_nonce' );
        ?>
        <style>
        #sh-region-wrap select { width:100% !important; }
        #sh-region-preview {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sh-region-preview-item {
            background: #f0f6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 7px 10px;
        }
        .sh-region-preview-item .sh-rp-name {
            font-weight: 600;
            font-size: 12px;
            color: #1d2327;
            margin-bottom: 4px;
        }
        .sh-region-preview-item .sh-rp-countries {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        .sh-rp-badge {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 8px;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .sh-rp-badge-more {
            background: #f3f4f6;
            color: #6b7280;
        }
        .sh-region-global-note {
            margin-top: 8px;
            font-size: 11px;
            color: #9ca3af;
            border-top: 1px solid #f3f4f6;
            padding-top: 8px;
        }
        </style>

        <div id="sh-region-wrap">

        <?php if ( empty( $all_regions ) ) : ?>
            <p style="font-size:12px;color:#9ca3af">
                Henüz region yok.
                <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=region' ) ); ?>">Region ekle →</a>
            </p>
        <?php else : ?>

            <select id="sh-region-select" name="tax_input[region][]" multiple style="width:100%">
                <?php foreach ( $all_regions as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>"
                        <?php selected( in_array( $term->term_id, $selected_ids, true ) ); ?>
                        data-id="<?php echo esc_attr( $term->term_id ); ?>">
                        <?php echo esc_html( $term->name ); ?>
                        <?php if ( $term->count ) : ?>(<?php echo $term->count; ?> post)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Seçili region'ların ülke önizlemesi -->
            <div id="sh-region-preview"></div>

            <div class="sh-region-global-note">
                ℹ️ Seçilmezse post <strong>herkese</strong> görünür (global).
                <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=region' ) ); ?>" style="color:#2271b1;display:block;margin-top:4px">Region yönetimi →</a>
            </div>

        <?php endif; ?>
        </div>

        <script>
        (function($){
            var regionsData = <?php echo wp_json_encode( $regions_data ); ?>;
            var allCountries = <?php echo wp_json_encode( $all_countries ); ?>;

            $('#sh-region-select').select2({
                width: '100%',
                placeholder: 'Region seç...',
                allowClear: true,
            });

            function renderPreview() {
                var selected = $('#sh-region-select').val() || [];
                var $preview = $('#sh-region-preview');
                $preview.empty();

                selected.forEach(function(slug) {
                    // slug'dan region bul
                    var region = null;
                    Object.values(regionsData).forEach(function(r) {
                        if (r.slug === slug) region = r;
                    });
                    if (!region) return;

                    var countries = region.countries || [];
                    var limit = 10;
                    var badges = '';
                    countries.slice(0, limit).forEach(function(code) {
                        var name = allCountries[code] || code;
                        badges += '<span class="sh-rp-badge" title="' + name + '">' + code + '</span>';
                    });
                    if (countries.length > limit) {
                        badges += '<span class="sh-rp-badge sh-rp-badge-more">+' + (countries.length - limit) + '</span>';
                    }
                    if (!badges) badges = '<span style="font-size:11px;color:#9ca3af">Ülke atanmamış</span>';

                    $preview.append(
                        '<div class="sh-region-preview-item">' +
                            '<div class="sh-rp-name">' + region.name + '</div>' +
                            '<div class="sh-rp-countries">' + badges + '</div>' +
                        '</div>'
                    );
                });
            }

            $('#sh-region-select').on('change', renderPreview);
            renderPreview(); // ilk yüklemede göster

        })(jQuery);
        </script>
        <?php
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Ülke listesi — WC varsa WC'den, yoksa PHP fallback.
     */
    private function getCountryOptions(): array
    {
        if ( function_exists( 'WC' ) && WC()->countries ) {
            return WC()->countries->get_countries() ?: [];
        }
        if ( class_exists( '\WC_Countries' ) ) {
            return ( new \WC_Countries() )->get_countries() ?: [];
        }
        return [
            'TR' => 'Turkey', 'US' => 'United States', 'DE' => 'Germany',
            'FR' => 'France', 'GB' => 'United Kingdom', 'IT' => 'Italy',
            'ES' => 'Spain', 'NL' => 'Netherlands', 'BE' => 'Belgium',
            'AT' => 'Austria', 'CH' => 'Switzerland', 'SE' => 'Sweden',
            'NO' => 'Norway', 'DK' => 'Denmark', 'PL' => 'Poland',
            'RU' => 'Russia', 'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia', 'JP' => 'Japan', 'CN' => 'China',
        ];
    }
}
