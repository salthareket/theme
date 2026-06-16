<?php

namespace SaltHareket\DownloadLog;

/**
 * DownloadRules
 *
 * Download erişim kuralları — repeater tabanlı, scope bazlı öncelik sistemi.
 * Admin'de Rules tab'ından yönetilir.
 *
 * Scope öncelik sırası (en spesifik kazanır):
 *   post > term > post_type > global
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *     - Add: getRules() — tüm kuralları döndür
 *     - Add: saveRules() — kuralları kaydet
 *     - Add: resolveForPost() — bir post için geçerli kuralı bul
 *     - Add: getDefaultMode() — global fallback modu
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Bir post için geçerli kuralı bul:
 * $rule = DownloadRules::resolveForPost(42);
 * // → ['mode' => 'lead_capture', 'form_id' => 3, 'scope' => 'post']
 *
 * // Tüm kuralları al:
 * $rules = DownloadRules::getRules();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Post bazlı kural:
 *   ['mode' => 'login_required', 'scope' => 'post', 'post_id' => 42]
 *
 * @example
 *   // Term bazlı kural:
 *   ['mode' => 'lead_capture', 'scope' => 'term', 'tax' => 'product_cat', 'term_id' => 15, 'form_id' => 3]
 *
 * @example
 *   // Post type bazlı kural:
 *   ['mode' => 'lead_capture', 'scope' => 'post_type', 'post_type' => 'product', 'form_id' => 3]
 *
 * @example
 *   // Global kural (tüm site):
 *   ['mode' => 'public', 'scope' => 'global']
 *
 * @example
 *   // Dışarıdan kural ekle (filter):
 *   add_filter('sh_download_rules', function($rules) {
 *       $rules[] = ['id' => 'custom_1', 'mode' => 'login_required', 'scope' => 'post_type', 'post_type' => 'document'];
 *       return $rules;
 *   });
 *
 * @package SaltHareket\DownloadLog
 */
class DownloadRules {

    const OPTION_KEY = 'sh_download_rules';

    /** Geçerli modlar */
    const MODES = [ 'public', 'login_required', 'lead_capture' ];

    /** Scope öncelik sırası — düşük index = yüksek öncelik */
    const SCOPE_PRIORITY = [ 'post', 'term', 'post_type', 'global' ];

    // ─── CRUD ────────────────────────────────────────────

    /**
     * Tüm kuralları döndür.
     * Filter ile dışarıdan kural eklenebilir.
     *
     * @return array[]
     */
    public static function getRules(): array {
        $rules = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $rules ) ) $rules = [];
        return apply_filters( 'sh_download_rules', $rules );
    }

    /**
     * Kuralları kaydet.
     */
    public static function saveRules( array $rules ): void {
        update_option( self::OPTION_KEY, $rules, false );
    }

    /**
     * Tek kural ekle veya güncelle.
     */
    public static function saveRule( array $rule ): string {
        $rules = self::getRules();

        if ( empty( $rule['id'] ) ) {
            $rule['id'] = 'rule_' . time() . '_' . wp_rand( 1000, 9999 );
        }

        $found = false;
        foreach ( $rules as &$r ) {
            if ( ( $r['id'] ?? '' ) === $rule['id'] ) {
                $r     = $rule;
                $found = true;
                break;
            }
        }
        unset( $r );

        if ( ! $found ) {
            $rules[] = $rule;
        }

        self::saveRules( $rules );
        return $rule['id'];
    }

    /**
     * Kural sil.
     */
    public static function deleteRule( string $rule_id ): void {
        $rules = self::getRules();
        $rules = array_values( array_filter( $rules, fn( $r ) => ( $r['id'] ?? '' ) !== $rule_id ) );
        self::saveRules( $rules );
    }

    // ─── RESOLVER ────────────────────────────────────────

    /**
     * Bir post için geçerli kuralı bul.
     * Öncelik: post > term > post_type > global
     * Eşleşme yoksa null döner (public kabul edilir).
     *
     * @param int $source_post_id  Dosyanın bulunduğu sayfa/post ID'si
     * @return array|null
     */
    public static function resolveForPost( int $source_post_id ): ?array {
        if ( $source_post_id < 1 ) return null;

        $rules     = self::getRules();
        $post_type = get_post_type( $source_post_id );
        $term_ids  = self::getPostTermIds( $source_post_id );

        // Scope'a göre grupla
        $by_scope = [];
        foreach ( $rules as $rule ) {
            $scope = $rule['scope'] ?? 'global';
            $by_scope[ $scope ][] = $rule;
        }

        // 1. Post bazlı — en yüksek öncelik
        foreach ( $by_scope['post'] ?? [] as $rule ) {
            if ( (int) ( $rule['post_id'] ?? 0 ) === $source_post_id ) {
                return $rule;
            }
        }

        // 2. Term bazlı
        foreach ( $by_scope['term'] ?? [] as $rule ) {
            $rule_term_id = (int) ( $rule['term_id'] ?? 0 );
            if ( $rule_term_id && in_array( $rule_term_id, $term_ids, true ) ) {
                return $rule;
            }
        }

        // 3. Post type bazlı
        foreach ( $by_scope['post_type'] ?? [] as $rule ) {
            if ( ( $rule['post_type'] ?? '' ) === $post_type ) {
                return $rule;
            }
        }

        // 4. Global
        if ( ! empty( $by_scope['global'] ) ) {
            return $by_scope['global'][0];
        }

        return null;
    }

    /**
     * Bir post'un tüm term ID'lerini döndür (tüm taxonomiler).
     *
     * @return int[]
     */
    private static function getPostTermIds( int $post_id ): array {
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
        if ( empty( $taxonomies ) ) return [];

        $term_ids = [];
        foreach ( $taxonomies as $tax ) {
            $terms = get_the_terms( $post_id, $tax );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
        }

        return $term_ids;
    }

    /**
     * Global fallback modu.
     * Hiçbir kural eşleşmezse bu mod kullanılır.
     */
    public static function getDefaultMode(): string {
        return (string) get_option( 'sh_download_default_mode', 'public' );
    }

    // ─── SANITIZE ────────────────────────────────────────

    /**
     * Kural verisini sanitize et.
     */
    public static function sanitizeRule( array $raw ): array {
        $rule = [
            'id'    => sanitize_key( $raw['id'] ?? '' ),
            'mode'  => in_array( $raw['mode'] ?? '', self::MODES, true ) ? $raw['mode'] : 'public',
            'scope' => in_array( $raw['scope'] ?? '', self::SCOPE_PRIORITY, true ) ? $raw['scope'] : 'global',
        ];

        // Form ID — sadece lead_capture modunda
        if ( $rule['mode'] === 'lead_capture' ) {
            $rule['form_id'] = (int) ( $raw['form_id'] ?? 0 );
        }

        // Scope'a göre ek alanlar
        switch ( $rule['scope'] ) {
            case 'post':
                $rule['post_id']   = (int) ( $raw['post_id'] ?? 0 );
                $rule['post_title'] = sanitize_text_field( $raw['post_title'] ?? '' );
                break;
            case 'term':
                $rule['tax']        = sanitize_key( $raw['tax'] ?? '' );
                $rule['term_id']    = (int) ( $raw['term_id'] ?? 0 );
                $rule['term_name']  = sanitize_text_field( $raw['term_name'] ?? '' );
                break;
            case 'post_type':
                $rule['post_type'] = sanitize_key( $raw['post_type'] ?? '' );
                break;
        }

        return $rule;
    }

    // ─── CF7 FORMS ───────────────────────────────────────

    /**
     * CF7 formlarını döndür — admin select için.
     * WPML/Polylang/qTranslate-XT uyumlu.
     *
     * @return array  [id => title]
     */
    public static function getCF7Forms(): array {
        if ( ! class_exists( 'WPCF7' ) ) return [];

        $lang_args = [];

        // Multilanguage: tüm dillerdeki formları getir
        if ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE ) {
            switch ( ENABLE_MULTILANGUAGE ) {
                case 'polylang':
                    if ( function_exists( 'pll_current_language' ) ) {
                        $lang_args['lang'] = ''; // tüm diller
                    }
                    break;
                case 'wpml':
                    // WPML suppress_filters ile tüm dilleri getir
                    $lang_args['suppress_filters'] = true;
                    break;
            }
        }

        $forms = get_posts( array_merge( [
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ], $lang_args ) );

        $result = [];
        foreach ( $forms as $form ) {
            $result[ $form->ID ] = $form->post_title;
        }

        return $result;
    }

    // ─── POST TYPES ──────────────────────────────────────

    /**
     * Public post type'ları döndür — admin select için.
     *
     * @return array  [slug => label]
     */
    public static function getPostTypes(): array {
        $types  = get_post_types( [ 'public' => true ], 'objects' );
        $result = [];
        foreach ( $types as $type ) {
            $result[ $type->name ] = $type->labels->singular_name . ' (' . $type->name . ')';
        }
        return $result;
    }

    /**
     * Public taxonomileri döndür — admin select için.
     *
     * @return array  [slug => label]
     */
    public static function getTaxonomies(): array {
        $taxes  = get_taxonomies( [ 'public' => true ], 'objects' );
        $result = [];
        foreach ( $taxes as $tax ) {
            $result[ $tax->name ] = $tax->labels->singular_name . ' (' . $tax->name . ')';
        }
        return $result;
    }
}
