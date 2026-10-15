<?php

namespace SaltHareket\PageSettings;

use SaltHareket\FieldRegistry\FieldChoices;

/**
 * PageSettings
 *
 * ACF-free page settings system.
 * Stores per-post/term/option settings in a single meta key.
 *
 * @version 1.0.0
 */
class PageSettings
{
    const META_KEY = '_page_settings';

    /**
     * Default values for page settings.
     */
    public static function defaults(): array
    {
        return [
            // Layout
            'header_template' => 'default',
            'footer_template' => 'default',
            'full_width'      => false,
            'container'       => '',
            'body_classes'    => [],
            'main_classes'    => [],

            // Components
            'modal' => [
                'template'  => 0,
                'view'      => 'always',
                'view_time' => 0,
                'size'      => '',
                'title'     => '',
            ],
            'offcanvas' => [
                'template'      => 0,
                'position'      => '',
                'visibility'    => '',
                // Sidebar override'lari (bos = theme/global default'a duser)
                'title'         => '',
                'width'         => '',
                'gap'           => '',
                'toggler_icon'  => '',
                'toggler_label' => '',
            ],

            // Access
            'login_required' => false,
            'allowed_roles'  => [],
        ];
    }

    /**
     * Get page settings for a post.
     *
     * @param int $post_id
     * @return array Merged with defaults
     */
    public static function get( int $post_id ): array
    {
        $raw = get_post_meta( $post_id, self::META_KEY, true );
        return self::normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Get page settings for a term.
     *
     * @param int $term_id
     * @return array Merged with defaults
     */
    public static function getForTerm( int $term_id ): array
    {
        $raw = get_term_meta( $term_id, self::META_KEY, true );
        return self::normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Get page settings for a post type archive (stored in options).
     *
     * @param string $post_type
     * @return array Merged with defaults
     */
    public static function getForArchive( string $post_type ): array
    {
        $raw = get_option( self::META_KEY . '_' . $post_type, [] );
        return self::normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Get page settings for a taxonomy archive (stored in options).
     *
     * @param string $taxonomy
     * @return array Merged with defaults
     */
    public static function getForTaxonomy( string $taxonomy ): array
    {
        $raw = get_option( self::META_KEY . '_tax_' . $taxonomy, [] );
        return self::normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Check if custom page settings are enabled for this post.
     */
    public static function isEnabled( int $post_id ): bool
    {
        return (bool) get_post_meta( $post_id, '_page_settings_enabled', true );
    }

    /**
     * Check if custom page settings are enabled for this term.
     */
    public static function isEnabledForTerm( int $term_id ): bool
    {
        return (bool) get_term_meta( $term_id, '_page_settings_enabled', true );
    }

    /**
     * Check if custom page settings are enabled for archive.
     */
    public static function isEnabledForArchive( string $post_type ): bool
    {
        return (bool) get_option( '_page_settings_enabled_' . $post_type, false );
    }

    /**
     * Check if custom page settings are enabled for taxonomy archive.
     */
    public static function isEnabledForTaxonomy( string $taxonomy ): bool
    {
        return (bool) get_option( '_page_settings_enabled_tax_' . $taxonomy, false );
    }

    /**
     * Save page settings for a post.
     *
     * @param int   $post_id
     * @param array $data
     * @param bool  $sync_langs  true ise ayni ayar tum dil versiyonlarina kopyalanir
     *                           (Polylang/WPML). Kopyalama sirasinda false ile cagrilir
     *                           (recursion engellenir).
     */
    public static function save( int $post_id, array $data, bool $sync_langs = false ): void
    {
        $clean = self::sanitize( $data );
        update_post_meta( $post_id, self::META_KEY, $clean );
        update_post_meta( $post_id, '_page_settings_enabled', true );
        // Sync tercihini sakla (metabox'ta checkbox durumu korunur)
        update_post_meta( $post_id, '_page_settings_sync_langs', $sync_langs ? 1 : 0 );

        // PAE (PageAssetsExtractor) icin force-rebuild sinyali:
        // page_settings degisimi container/sidebar gibi DOM class'larini degistirebilir.
        // PAE save_post @100'de fetch ederken bu flag'i gorup force_rebuild=true yapar,
        // boylece degisiklik ILK save'de dogru CSS uretir (yoksa 2. save gerekiyordu).
        self::flagAssetsRebuild( 'post', $post_id );

        if ( $sync_langs ) {
            self::syncPostToTranslations( $post_id, $clean );
        }
    }

    /**
     * PageAssetsExtractor'a "bu obje icin CSS'i force rebuild et" sinyali birakir.
     * PAE on_save_post/on_save_term bu flag'i okuyup force_rebuild=true yapar ve siler.
     */
    private static function flagAssetsRebuild( string $context, int $id ): void
    {
        if ( $context === 'term' ) {
            update_term_meta( $id, '_page_settings_force_asset_rebuild', 1 );
        } else {
            update_post_meta( $id, '_page_settings_force_asset_rebuild', 1 );
        }
    }

    /**
     * Ayni page_settings'i bir postun tum dil versiyonlarina kopyalar.
     * Polylang veya WPML aktifse calisir; degilse no-op.
     */
    private static function syncPostToTranslations( int $post_id, array $clean ): void
    {
        foreach ( self::getPostTranslationIds( $post_id ) as $tr_id ) {
            if ( $tr_id === $post_id ) continue;
            update_post_meta( $tr_id, self::META_KEY, $clean );
            update_post_meta( $tr_id, '_page_settings_enabled', true );
            update_post_meta( $tr_id, '_page_settings_sync_langs', 1 );
            self::flagAssetsRebuild( 'post', $tr_id );
        }
    }

    /**
     * Bir postun tum dil versiyon ID'lerini dondurur (kendisi dahil).
     * Polylang: pll_get_post_translations. WPML: icl_object_id ile diller gezilir.
     */
    private static function getPostTranslationIds( int $post_id ): array
    {
        $ids = [];

        // Polylang
        if ( function_exists( 'pll_get_post_translations' ) ) {
            $tr = pll_get_post_translations( $post_id );
            if ( is_array( $tr ) ) $ids = array_map( 'intval', array_values( $tr ) );
        }
        // WPML
        elseif ( function_exists( 'icl_object_id' ) && function_exists( 'icl_get_languages' ) ) {
            $post_type = get_post_type( $post_id );
            $langs     = icl_get_languages( 'skip_missing=0' );
            if ( is_array( $langs ) ) {
                foreach ( $langs as $lang ) {
                    $code = $lang['language_code'] ?? '';
                    if ( ! $code ) continue;
                    $tid = (int) icl_object_id( $post_id, $post_type, false, $code );
                    if ( $tid ) $ids[] = $tid;
                }
            }
        }

        $ids[] = $post_id;
        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Save page settings for a term.
     *
     * @param bool $sync_langs true ise term'in tum dil versiyonlarina kopyalar.
     */
    public static function saveForTerm( int $term_id, array $data, bool $sync_langs = false ): void
    {
        $clean = self::sanitize( $data );
        update_term_meta( $term_id, self::META_KEY, $clean );
        update_term_meta( $term_id, '_page_settings_enabled', true );
        update_term_meta( $term_id, '_page_settings_sync_langs', $sync_langs ? 1 : 0 );
        self::flagAssetsRebuild( 'term', $term_id );

        if ( $sync_langs ) {
            self::syncTermToTranslations( $term_id, $clean );
        }
    }

    /**
     * Ayni page_settings'i bir term'in tum dil versiyonlarina kopyalar.
     */
    private static function syncTermToTranslations( int $term_id, array $clean ): void
    {
        $ids = [];
        if ( function_exists( 'pll_get_term_translations' ) ) {
            $tr = pll_get_term_translations( $term_id );
            if ( is_array( $tr ) ) $ids = array_map( 'intval', array_values( $tr ) );
        }
        foreach ( $ids as $tr_id ) {
            if ( $tr_id === $term_id ) continue;
            update_term_meta( $tr_id, self::META_KEY, $clean );
            update_term_meta( $tr_id, '_page_settings_enabled', true );
            update_term_meta( $tr_id, '_page_settings_sync_langs', 1 );
            self::flagAssetsRebuild( 'term', $tr_id );
        }
    }

    /**
     * Sync tercihini oku (post).
     */
    public static function isSyncLangs( int $post_id ): bool
    {
        return (bool) get_post_meta( $post_id, '_page_settings_sync_langs', true );
    }

    /**
     * Sync tercihini oku (term).
     */
    public static function isSyncLangsForTerm( int $term_id ): bool
    {
        return (bool) get_term_meta( $term_id, '_page_settings_sync_langs', true );
    }

    /**
     * Save page settings for a post type archive.
     */
    public static function saveForArchive( string $post_type, array $data ): void
    {
        $clean = self::sanitize( $data );
        update_option( self::META_KEY . '_' . $post_type, $clean, false );
        update_option( '_page_settings_enabled_' . $post_type, true, false );
    }

    /**
     * Save page settings for a taxonomy archive.
     */
    public static function saveForTaxonomy( string $taxonomy, array $data ): void
    {
        $clean = self::sanitize( $data );
        update_option( self::META_KEY . '_tax_' . $taxonomy, $clean, false );
        update_option( '_page_settings_enabled_tax_' . $taxonomy, true, false );
    }

    /**
     * Resolve page settings for the current request.
     * Returns the twig-compatible array (same format as old ACF system) or empty array.
     *
     * @param mixed $post Timber\Post or null
     * @return array
     */
    public static function resolve( $post = null ): array
    {
        $ps = null;

        // 1. Taxonomy/Term pages
        if ( is_tax() || is_product_tag() || is_tag() || is_category() ) {
            $queried = get_queried_object();
            if ( $queried && is_a( $queried, 'WP_Term' ) ) {
                // Term-level override — her zaman kullanılır (kendi ayarı varsa)
                if ( self::isEnabledForTerm( $queried->term_id ) ) {
                    $ps = self::getForTerm( $queried->term_id );
                }
                // Fallback: taxonomy-level — sadece "apply to all terms" açıksa
                if ( ! $ps ) {
                    $taxonomy = $queried->taxonomy;
                    if ( is_tag() )      $taxonomy = 'post_tag';
                    if ( is_category() ) $taxonomy = 'category';
                    $apply_all = (bool) get_option( '_page_settings_apply_all_terms_' . $taxonomy, false );
                    if ( $apply_all && self::isEnabledForTaxonomy( $taxonomy ) ) {
                        $ps = self::getForTaxonomy( $taxonomy );
                    }
                }
            }
        }

        // 2. Post type archive
        if ( ! $ps && is_post_type_archive() ) {
            global $wp_query;
            $pt_raw = $wp_query->query_vars['post_type'] ?? null;
            $pt_key = is_array( $pt_raw ) ? $pt_raw[0] : $pt_raw;
            if ( $pt_key && self::isEnabledForArchive( $pt_key ) ) {
                $ps = self::getForArchive( $pt_key );
            }
        }

        // 3. Single post/page
        if ( ! $ps && $post && is_object( $post ) ) {
            $post_id = is_numeric( $post ) ? (int) $post : ( $post->ID ?? 0 );
            if ( $post_id > 0 && self::isEnabled( $post_id ) ) {
                $ps = self::get( $post_id );
            }
            // Fallback: post_type-level — sadece "apply to all singles" açıksa
            if ( ! $ps && is_single() ) {
                $post_type = is_object( $post ) ? ( $post->post_type ?? '' ) : get_post_type( $post_id );
                if ( $post_type ) {
                    $apply_all = (bool) get_option( '_page_settings_apply_all_singles_' . $post_type, false );
                    if ( $apply_all && self::isEnabledForArchive( $post_type ) ) {
                        $ps = self::getForArchive( $post_type );
                    }
                }
            }
        }

        if ( ! $ps ) {
            return [];
        }

        return self::toContext( $ps );
    }

    /**
     * Disable page settings for a post (revert to defaults).
     */
    public static function disable( int $post_id ): void
    {
        delete_post_meta( $post_id, self::META_KEY );
        delete_post_meta( $post_id, '_page_settings_enabled' );
        delete_post_meta( $post_id, '_page_settings_sync_langs' );
    }

    /**
     * Normalize settings: merge with defaults, ensure correct types.
     */
    public static function normalize( array $raw ): array
    {
        $defaults = self::defaults();
        $ps = array_replace_recursive( $defaults, $raw );

        // Ensure correct types
        $ps['full_width']     = (bool) ( $ps['full_width'] ?? false );
        $ps['login_required'] = (bool) ( $ps['login_required'] ?? false );
        $ps['body_classes']   = array_filter( (array) ( $ps['body_classes'] ?? [] ) );
        $ps['main_classes']   = array_filter( (array) ( $ps['main_classes'] ?? [] ) );
        $ps['allowed_roles']  = array_filter( (array) ( $ps['allowed_roles'] ?? [] ) );
        $ps['container']      = sanitize_key( $ps['container'] ?? '' );

        // Modal
        $ps['modal']['template']  = (int) ( $ps['modal']['template'] ?? 0 );
        $ps['modal']['view']      = in_array( $ps['modal']['view'] ?? '', ['always','scheduled'], true ) ? $ps['modal']['view'] : 'always';
        $ps['modal']['view_time'] = (int) ( $ps['modal']['view_time'] ?? 0 );
        $ps['modal']['size']      = sanitize_key( $ps['modal']['size'] ?? '' );
        $ps['modal']['title']     = sanitize_text_field( $ps['modal']['title'] ?? '' );

        // Offcanvas
        $ps['offcanvas']['template']      = (int) ( $ps['offcanvas']['template'] ?? 0 );
        $ps['offcanvas']['position']      = sanitize_key( $ps['offcanvas']['position'] ?? '' );
        $ps['offcanvas']['visibility']    = sanitize_key( $ps['offcanvas']['visibility'] ?? '' );
        $ps['offcanvas']['title']         = sanitize_text_field( $ps['offcanvas']['title'] ?? '' );
        $ps['offcanvas']['width']         = ( $ps['offcanvas']['width'] ?? '' ) === '' ? '' : (int) $ps['offcanvas']['width'];
        $ps['offcanvas']['gap']           = sanitize_text_field( $ps['offcanvas']['gap'] ?? '' );
        $ps['offcanvas']['toggler_icon']  = sanitize_text_field( $ps['offcanvas']['toggler_icon'] ?? '' );
        $ps['offcanvas']['toggler_label'] = sanitize_text_field( $ps['offcanvas']['toggler_label'] ?? '' );

        return $ps;
    }

    /**
     * Sanitize input data from $_POST.
     */
    public static function sanitize( array $data ): array
    {
        return [
            'header_template' => sanitize_text_field( $data['header_template'] ?? 'default' ),
            'footer_template' => sanitize_text_field( $data['footer_template'] ?? 'default' ),
            'full_width'      => ! empty( $data['full_width'] ),
            'container'       => sanitize_key( $data['container'] ?? '' ),
            'body_classes'    => array_map( 'sanitize_html_class', array_filter( (array) ( $data['body_classes'] ?? [] ) ) ),
            'main_classes'    => array_map( 'sanitize_html_class', array_filter( (array) ( $data['main_classes'] ?? [] ) ) ),
            'modal' => [
                'template'  => absint( $data['modal']['template'] ?? 0 ),
                'view'      => in_array( $data['modal']['view'] ?? '', [ 'always', 'scheduled' ], true ) ? $data['modal']['view'] : 'always',
                'view_time' => absint( $data['modal']['view_time'] ?? 0 ),
                'size'      => sanitize_key( $data['modal']['size'] ?? '' ),
                'title'     => sanitize_text_field( $data['modal']['title'] ?? '' ),
            ],
            'offcanvas' => [
                'template'      => absint( $data['offcanvas']['template'] ?? 0 ),
                'position'      => sanitize_key( $data['offcanvas']['position'] ?? '' ),
                'visibility'    => sanitize_key( $data['offcanvas']['visibility'] ?? '' ),
                'title'         => sanitize_text_field( $data['offcanvas']['title'] ?? '' ),
                'width'         => ( ( $data['offcanvas']['width'] ?? '' ) === '' ) ? '' : absint( $data['offcanvas']['width'] ),
                'gap'           => sanitize_text_field( $data['offcanvas']['gap'] ?? '' ),
                'toggler_icon'  => sanitize_text_field( $data['offcanvas']['toggler_icon'] ?? '' ),
                'toggler_label' => sanitize_text_field( $data['offcanvas']['toggler_label'] ?? '' ),
            ],
            'login_required' => ! empty( $data['login_required'] ),
            'allowed_roles'  => array_map( 'sanitize_key', array_filter( (array) ( $data['allowed_roles'] ?? [] ) ) ),
        ];
    }

    /**
     * Extract breakpoint from offcanvas visibility value.
     * 'offcanvas-md' → 'md', 'offcanvas' → '', '' → 'lg' (default)
     */
    private static function resolveOffcanvasBreakpoint( string $visibility ): string
    {
        if ( empty( $visibility ) ) return 'lg';
        if ( $visibility === 'offcanvas' ) return '';
        // 'offcanvas-md' → 'md'
        return str_replace( 'offcanvas-', '', $visibility );
    }

    /**
     * Get the container class string (for twig compatibility).
     * Converts stored value to the same format normalizePageSettings used.
     */
    public static function resolveContainerClass( array $ps ): string
    {
        if ( $ps['full_width'] ) return '';
        $container = $ps['container'] ?? '';
        if ( empty( $container ) ) return '';
        if ( function_exists( 'block_container' ) ) {
            return block_container( $container );
        }
        // Fallback: direct Bootstrap class
        return $container === 'fluid' ? 'container-fluid' : 'container-' . $container;
    }

    /**
     * Convert stored settings to the format base.twig expects.
     * This provides backward compatibility with the old ACF-based system.
     */
    public static function toContext( array $ps ): array
    {
        $offcanvas = self::resolveOffcanvasContext( $ps );

        return [
            'full'             => $ps['full_width'],
            'header_template'  => $ps['header_template'],
            'footer_template'  => $ps['footer_template'],
            'login_required'   => $ps['login_required'],
            'allowed_roles'    => $ps['allowed_roles'],
            'add_offcanvas'    => false,
            'classes' => [
                'body'      => implode( ' ', $ps['body_classes'] ),
                'main'      => implode( ' ', $ps['main_classes'] ),
                'container' => self::resolveContainerClass( $ps ),
                // gap sadece sidebar modda dolu gelir
                'gap'       => $offcanvas['is_sidebar'] ? $offcanvas['gap'] : '',
            ],
            'offcanvas' => $offcanvas,
            'modal' => [
                'template'  => $ps['modal']['template'],
                'view'      => $ps['modal']['view'] ?? 'always',
                'view_time' => $ps['modal']['view_time'] ?? 0,
                'size'      => $ps['modal']['size'] ?? '',
                'title'     => $ps['modal']['title'] ?? '',
            ],
        ];
    }

    /**
     * Build the offcanvas context array for base.twig.
     *
     * Modes:
     *   - is_standalone: visibility == 'offcanvas' → her zaman offcanvas (butonla açılır, lazy-load)
     *   - is_sidebar:    visibility == 'offcanvas-{bp}' → bp üstünde sidebar (server-side render), altında offcanvas
     *   - none:          visibility == '' veya template yok → sidebar/offcanvas yok
     */
    private static function resolveOffcanvasContext( array $ps ): array
    {
        $template_id = (int) ( $ps['offcanvas']['template'] ?? 0 );
        $visibility  = $ps['offcanvas']['visibility'] ?? '';
        $position    = $ps['offcanvas']['position'] ?: 'start';

        // Template yoksa hiçbir şey render etme
        $has_template = $template_id > 0;

        $is_standalone = $has_template && $visibility === 'offcanvas';
        $is_sidebar    = $has_template && $visibility !== '' && $visibility !== 'offcanvas';

        // breakpoint: 'offcanvas-md' → 'md'
        $breakpoint = $is_sidebar ? str_replace( 'offcanvas-', '', $visibility ) : '';

        // Twig template path (custom_offcanvas için)
        $twig_template = '';
        if ( $has_template ) {
            $tpl = get_post_meta( $template_id, 'template', true );
            if ( $tpl ) $twig_template = '_custom/' . $tpl;
        }

        // ── Sidebar degerleri: page override varsa onu kullan, bos ise global default ──
        // Page override alanlari resolve() ile normalize edilmis $ps'ten gelir.
        $oc = $ps['offcanvas'] ?? [];

        // Global default'lar (General Settings)
        $default_width         = (int) get_option( 'options_default_sidebar_width', 3 );
        if ( $default_width < 1 || $default_width > 11 ) $default_width = 3;
        $default_gap           = get_option( 'options_default_sidebar_gap', 'gx-lg-5' );
        $default_toggler_icon  = get_option( 'options_default_sidebar_toggler_icon', 'light:bars' );
        $default_toggler_label = get_option( 'options_default_sidebar_toggler_label', 'Menu' );

        // Width: page override ('' degilse) → yoksa global default
        $ov_width = $oc['width'] ?? '';
        $width    = ( $ov_width === '' || $ov_width === null ) ? $default_width : (int) $ov_width;
        if ( $width < 1 || $width > 11 ) $width = $default_width;

        // Gap / toggler: page override bos degilse kullan, yoksa global default
        $gap           = ! empty( $oc['gap'] )           ? $oc['gap']           : $default_gap;
        $toggler_icon  = ! empty( $oc['toggler_icon'] )  ? $oc['toggler_icon']  : $default_toggler_icon;
        $toggler_label = ! empty( $oc['toggler_label'] ) ? $oc['toggler_label'] : $default_toggler_label;

        // Title: page override bos degilse kullan, yoksa template basligi
        $ov_title = $oc['title'] ?? '';
        $title    = ! empty( $ov_title ) ? $ov_title : ( $has_template ? get_the_title( $template_id ) : '' );

        // Unique DOM id — button ile ayni merkezi uretici (sh-oc-...) → tutarli + cakismasiz.
        // Page-settings offcanvas'i "ps-{template_id}" tabaninda uretilir; boylece ayni
        // template'i kullanan button offcanvas'i ile CAKISMAZ (farkli base).
        $oc_id = 0;
        if ( $template_id ) {
            $base  = 'ps-' . $template_id;
            $oc_id = class_exists( '\OffcanvasRegistry' )
                ? \OffcanvasRegistry::makeId( $base )
                : 'sh-oc-' . $base;
        }

        // render_mode: page-settings'te henuz secim yok → standalone icin 'static'
        // (server-side partial render zaten iceriği basiyor). Sidebar da static (layout).
        $render_mode = 'static';

        // Standalone offcanvas'i OffcanvasRegistry'ye HOST olarak kaydet.
        // Boylece salt-offcanvas.js ayni motorla acar (button ile tutarli).
        // NOT: Sidebar (is_sidebar) base.twig grid kolonunda server-side render edilir,
        // registry'ye eklenmez (layout parcasi, JS-driven degil).
        if ( $is_standalone && $oc_id && class_exists( '\OffcanvasRegistry' ) ) {
            \OffcanvasRegistry::add( [
                'id'          => $oc_id,
                'template'    => $template_id,
                'render_mode' => $render_mode,   // standalone: static server-side render
                'method'      => '',
                'position'    => $position,
                'visibility'  => $visibility,
                'backdrop'    => true,
                'title'       => $title,
            ] );
        }

        return [
            'id'            => $oc_id,
            'template_id'   => $template_id,
            'template'      => $twig_template,
            'title'         => $title,
            'position'      => $position,
            'visibility'    => $visibility,
            'is_standalone' => $is_standalone,
            'is_sidebar'    => $is_sidebar,
            'breakpoint'    => $breakpoint,
            'width'         => $width,
            'gap'           => $gap,
            'toggler_icon'  => $toggler_icon,
            'toggler_label' => $toggler_label,
            'render_mode'   => $render_mode,
            // Backward-compat (base.twig eski alanlar)
            'size'          => $breakpoint,
            'individual'    => $is_standalone,
        ];
    }

    /**
     * Render modal and offcanvas components in wp_footer.
     * Called via hook in bootstrap.php.
     */
    public static function renderComponents(): void
    {
        if ( is_admin() ) return;

        $ps = null;

        // 1. Archive page (post type)
        if ( is_post_type_archive() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
            global $wp_query;
            $pt_raw = $wp_query->query_vars['post_type'] ?? null;
            $pt_key = is_array( $pt_raw ) ? $pt_raw[0] : $pt_raw;
            // WooCommerce shop
            if ( ! $pt_key && function_exists( 'is_shop' ) && is_shop() ) $pt_key = 'product';
            if ( $pt_key && self::isEnabledForArchive( $pt_key ) ) {
                $ps = self::getForArchive( $pt_key );
            }
        }

        // 2. Taxonomy archive
        if ( ! $ps && ( is_tax() || is_tag() || is_category() ) ) {
            $queried = get_queried_object();
            if ( $queried && is_a( $queried, 'WP_Term' ) ) {
                if ( self::isEnabledForTerm( $queried->term_id ) ) {
                    $ps = self::getForTerm( $queried->term_id );
                } else {
                    $taxonomy = $queried->taxonomy;
                    if ( self::isEnabledForTaxonomy( $taxonomy ) ) {
                        $ps = self::getForTaxonomy( $taxonomy );
                    }
                }
            }
        }

        // 3. Singular post/page
        if ( ! $ps ) {
            $post_id = 0;
            if ( is_singular() || is_front_page() ) {
                $post_id = get_queried_object_id();
            }
            if ( ! $post_id && is_home() ) {
                $post_id = (int) get_option( 'page_for_posts' );
            }
            if ( ! $post_id && function_exists( 'is_shop' ) && is_shop() ) {
                $post_id = (int) get_option( 'woocommerce_shop_page_id' );
            }
            if ( ! $post_id && get_option( 'show_on_front' ) === 'page' ) {
                $post_id = (int) get_option( 'page_on_front' );
            }
            if ( $post_id && self::isEnabled( $post_id ) ) {
                $ps = self::get( $post_id );
            }
        }

        if ( ! $ps || empty( $ps ) ) return;

        // Modal: custom_modal method + get_custom_template_data (aynı header button gibi)
        $modal_id = (int) ( $ps['modal']['template'] ?? 0 );
        if ( $modal_id > 0 ) {
            $modal_post = get_post( $modal_id );
            if ( $modal_post && $modal_post->post_status === 'publish' ) {
                $timber_post = \Timber::get_post( $modal_id );
                $attrs = '';
                if ( $timber_post && method_exists( $timber_post, 'get_custom_template_data' ) ) {
                    $attrs = $timber_post->get_custom_template_data( 'modal' );
                }

                // Page Settings size override — template'in kendi size'ını override eder
                $ps_size = $ps['modal']['size'] ?? '';
                if ( $ps_size !== '' ) {
                    // Mevcut data-size ve data-fullscreen attribute'larını temizle
                    $attrs = preg_replace( '/\sdata-size="[^"]*"/', '', $attrs );
                    $attrs = preg_replace( '/\sdata-fullscreen="[^"]*"/', '', $attrs );
                    // fullscreen variant mı normal size mı?
                    if ( strpos( $ps_size, 'fullscreen' ) !== false ) {
                        $attrs .= ' data-fullscreen="' . esc_attr( $ps_size ) . '"';
                    } else {
                        $attrs .= ' data-size="' . esc_attr( $ps_size ) . '"';
                    }
                }

                // Page Settings title override — bos ise template'in kendi basligi kalir.
                // Modal JS handler data-title'i okur ($btn.data('title')).
                // translate() ile cevir — extract sistemi bu string'i translate.php'ye yazdi.
                $ps_title = $ps['modal']['title'] ?? '';
                if ( $ps_title !== '' ) {
                    $attrs = preg_replace( '/\sdata-title="[^"]*"/', '', $attrs );
                    $translated = function_exists( 'trans' ) ? trans( $ps_title ) : __( $ps_title, wp_get_theme()->get( 'TextDomain' ) );
                    $attrs .= ' data-title="' . esc_attr( $translated ) . '"';
                }

                $modal_view = $ps['modal']['view'] ?? 'always';
                $modal_time = (int) ( $ps['modal']['view_time'] ?? 0 );

                echo '<a href="#" class="d-none ps-modal-auto-trigger"'
                    . ' data-ajax-method="custom_modal" ' . $attrs . '></a>';

                echo '<script>document.addEventListener("DOMContentLoaded",function(){'
                    . 'var v="' . esc_js( $modal_view ) . '",t=' . $modal_time . ',k="ps_modal_' . $modal_id . '";'
                    . 'function ok(){if(v==="always")return true;var n=localStorage.getItem(k);return !n||new Date(n)<=new Date();}'
                    . 'if(!ok())return;'
                    . 'var a=document.querySelector(".ps-modal-auto-trigger");'
                    . 'if(a)setTimeout(function(){a.click();},500);'
                    . 'if(v==="scheduled"&&t>0){var e=new Date();e.setMinutes(e.getMinutes()+t);localStorage.setItem(k,e.toISOString());}'
                    . '});</script>';
            }
        }

        // NOT: Offcanvas (standalone + sidebar) artık base.twig block sisteminde render ediliyor
        // (partials/page-settings/offcanvas.twig + sidebar.twig + toggler.twig).
        // Burada sadece modal render edilir çünkü modal wp_footer'a ait (layout dışı).
    }

    /**
     * Available choices for the UI dropdowns.
     */
    public static function choices(): array
    {
        $headers = [ 'default' => 'Default', 'static' => 'Static', 'none' => 'None' ];
        $footers = [ 'default' => 'Default', 'static' => 'Static', 'none' => 'None' ];

        if ( class_exists( FieldChoices::class ) ) {
            foreach ( FieldChoices::templatePosts( 'header' ) as $id => $title ) {
                $headers[ (string) $id ] = $title;
            }
            foreach ( FieldChoices::templatePosts( 'footer' ) as $id => $title ) {
                $footers[ (string) $id ] = $title;
            }
        }

        $containers = [
            ''     => 'Default',
            'sm'   => 'Small',
            'md'   => 'Medium',
            'lg'   => 'Large',
            'xl'   => 'XLarge',
            'xxl'  => 'XXLarge',
            'xxxl' => 'XXXLarge',
            'fluid' => 'Fluid',
            'no'   => 'No Container',
        ];

        $breakpoints = [
            ''    => 'Template Default',
            'xs'  => 'xs',
            'sm'  => 'sm',
            'md'  => 'md',
            'lg'  => 'lg',
            'xl'  => 'xl',
            'xxl' => 'xxl',
        ];

        $positions = [
            ''      => 'Template Default',
            'start' => 'Start (Left)',
            'end'   => 'End (Right)',
            'top'   => 'Top',
            'bottom' => 'Bottom',
        ];

        // Sidebar width (columns) — bos = Theme Default (global default'a duser)
        $sidebarWidths = [ '' => 'Theme Default' ];
        for ( $i = 1; $i <= 11; $i++ ) {
            $sidebarWidths[ (string) $i ] = $i . ' / 12';
        }

        // Sidebar gap — General Settings ile ayni degerler
        $sidebarGaps = [
            ''        => 'Theme Default',
            'g-0'     => 'None',
            'gx-3'    => 'Small',
            'gx-lg-5' => 'Medium',
            'gx-lg-6' => 'Large',
        ];

        // Modal templates (from FieldChoices)
        $modals = [ 0 => '— None —' ];
        if ( class_exists( FieldChoices::class ) ) {
            foreach ( FieldChoices::templatePosts( 'modal' ) as $id => $title ) {
                $modals[ $id ] = $title;
            }
        }

        // Offcanvas templates (from FieldChoices)
        $offcanvases = [ 0 => '— None —' ];
        if ( class_exists( FieldChoices::class ) ) {
            foreach ( FieldChoices::templatePosts( 'offcanvas' ) as $id => $title ) {
                $offcanvases[ $id ] = $title;
            }
        }

        // User roles
        $roles = [];
        foreach ( wp_roles()->roles as $slug => $role ) {
            $roles[ $slug ] = $role['name'];
        }

        // Body classes (theme header themes + utilities)
        $bodyClasses = [];
        if ( class_exists( FieldChoices::class ) ) {
            $styles = FieldChoices::themeStyles();
            if ( isset( $styles['header']['themes'] ) ) {
                foreach ( $styles['header']['themes'] as $t ) {
                    if ( ! in_array( $t['class'], ['body', 'html'], true ) ) {
                        $bodyClasses[ $t['class'] ] = $t['class'];
                    }
                }
            }
        }
        foreach ( ['m','mt','mb','ms','me','mx','my','p','pt','pb','ps','pe','px','py'] as $p ) {
            foreach ( range(0, 10) as $n ) $bodyClasses[$p.'-'.$n] = $p.'-'.$n;
        }

        // Main classes (utilities)
        $mainClasses = [];
        foreach ( ['m','mt','mb','ms','me','mx','my','p','pt','pb','ps','pe','px','py'] as $p ) {
            foreach ( range(0, 10) as $n ) $mainClasses[$p.'-'.$n] = $p.'-'.$n;
        }

        return compact( 'headers', 'footers', 'containers', 'breakpoints', 'positions', 'modals', 'offcanvases', 'roles', 'bodyClasses', 'mainClasses', 'sidebarWidths', 'sidebarGaps' );
    }
}
