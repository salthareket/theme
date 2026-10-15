<?php

namespace SaltHareket\Reviews;

/**
 * ReviewsSettings
 * Reviews sistemi için tüm ayarları yönetir.
 * wp_options tablosunda 'sh_reviews_settings' key'i altında JSON olarak saklanır.
 *
 * @version 1.0.2
 * @changelog
 *   1.0.2 - 2026-06-17
 *     - Add: saveAll() sonrası do_action('sh/reviews/saved') + do_action('sh/reviews/setting_changed')
 *   1.0.1 - 2026-06-16
 *     - Add: isApproveDisabled() — DISABLE_REVIEW_APPROVE define için helper
 *   1.0.0 - 2026-05-05 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Ayar oku
 * ReviewsSettings::get('auto_approve_reviews');       // bool
 * ReviewsSettings::get('rating.max_rating');          // int (dot notation)
 * ReviewsSettings::get('reply.user_approves_reply');  // bool
 *
 * // Ayar yaz
 * ReviewsSettings::set('auto_approve_reviews', true);
 * ReviewsSettings::set('rating.max_rating', 10);
 *
 * // Tüm ayarlar
 * ReviewsSettings::all();
 *
 * // Sıfırla
 * ReviewsSettings::reset();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $auto = ReviewsSettings::get('auto_approve_reviews'); // false
 *
 * @example
 *   ReviewsSettings::set('rating.type', 'stars');
 *
 * @example
 *   $criteria = ReviewsSettings::get('rating.criteria'); // []
 *
 * @example
 *   $all = ReviewsSettings::all();
 *   echo $all['reply']['user_approves_reply']; // true
 *
 * @example
 *   ReviewsSettings::reset(); // factory defaults'a döner
 *
 * @package SaltHareket\Reviews
 */
class ReviewsSettings
{
    private const OPTION_KEY = 'sh_reviews_settings';

    // ─── DEFAULT AYARLAR ─────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            'general' => [
                'enable_reviews'         => true,
                'disable_review_approve' => false,
                'auto_approve_reviews'   => false,
                'auto_approve_verified'  => true,
                'auto_approve_trusted'   => true,
                'trusted_threshold'      => 3,
                'require_login'          => true,
                'one_review_per_user'    => true,
                'rate_limit_per_hour'    => 5,   // saat başına max review sayısı (0 = devre dışı)
            ],
            'rating' => [
                'type'             => 'stars',    // stars | thumbs | nps | multi
                'max_rating'       => 5,          // 5 veya 10
                'allow_half'       => false,      // 4.5 gibi
                'show_breakdown'   => true,       // dağılım göster
                'criteria_display' => 'inline',   // inline | popover
                'criteria'         => [
                    // Global default — post type override yoksa bu kullanılır
                    'default'    => [],
                    // Post type bazlı kriterler
                    'post_types' => [],
                ],
            ],
            'reply' => [
                'enable_replies'           => true,
                'auto_approve_owner_reply' => true,  // içerik sahibi reply → otomatik
                'user_approves_reply'      => true,  // kullanıcı kendi reply'ını onaylar
                'max_depth'                => 1,     // kaç seviye iç içe (1 = sadece direkt reply)
            ],
            'helpful' => [
                'enable_helpful'         => true,
                'algorithm'              => 'simple',  // simple | wilson
                'require_login_to_vote'  => true,
            ],
            'media' => [
                'enable_media'   => true,
                'max_images'     => 5,
                'max_image_size' => 5,  // MB
            ],
            'notifications' => [
                'notify_on_new_review' => true,
                'notify_on_reply'      => true,
                'notify_on_approve'    => true,
            ],
            // ── Target Types — hangi object type'a review yapılabilir ──────────
            // key: object_type veya object_type:subtype (post:product, user:author vb.)
            // subtype için post → post_type slug, user → WP role slug
            'types' => [
                'post' => [
                    'enabled'       => false,
                    'modes'         => [ 'rate', 'comment' ],
                    'criteria'      => [],
                ],
                'user' => [
                    'enabled'       => true,
                    'modes'         => [ 'rate', 'comment' ],
                    'criteria'      => [],
                ],
                // Subtype override örnekleri — admin'den eklenir:
                // 'post:product' => [ 'enabled' => true, 'modes' => ['rate','comment','criteria'], 'criteria' => [...] ],
                // 'user:author'  => [ 'enabled' => true, 'modes' => ['rate','comment','criteria'], 'criteria' => [...] ],
            ],
        ];
    }

    // ─── READ ─────────────────────────────────────────────────────────────────

    /**
     * Ayar oku — dot notation destekli.
     * Örn: get('rating.max_rating') → 5
     */
    public static function get( string $key, mixed $default = null ): mixed
    {
        $all = self::all();

        // Dot notation
        if ( str_contains( $key, '.' ) ) {
            $parts = explode( '.', $key, 2 );
            $group = $parts[0];
            $sub   = $parts[1];
            return $all[ $group ][ $sub ] ?? $default;
        }

        // Flat key — önce top-level, sonra tüm gruplarda ara
        if ( isset( $all[ $key ] ) ) return $all[ $key ];

        foreach ( $all as $group ) {
            if ( is_array( $group ) && isset( $group[ $key ] ) ) {
                return $group[ $key ];
            }
        }

        return $default;
    }

    /**
     * Tüm ayarları döndür — defaults ile merge edilmiş.
     */
    public static function all(): array
    {
        static $cache = null;
        if ( $cache !== null ) return $cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $cache = self::deepMerge( self::defaults(), $saved );
        return $cache;
    }

    // ─── WRITE ────────────────────────────────────────────────────────────────

    /**
     * Ayar yaz — dot notation destekli.
     */
    public static function set( string $key, mixed $value ): void
    {
        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        if ( str_contains( $key, '.' ) ) {
            $parts           = explode( '.', $key, 2 );
            $saved[ $parts[0] ][ $parts[1] ] = $value;
        } else {
            $saved[ $key ] = $value;
        }

        update_option( self::OPTION_KEY, $saved );
        self::clearCache();
    }

    /**
     * Grup olarak kaydet.
     */
    public static function saveGroup( string $group, array $values ): void
    {
        $saved           = get_option( self::OPTION_KEY, [] );
        $saved           = is_array( $saved ) ? $saved : [];
        $saved[ $group ] = $values;
        update_option( self::OPTION_KEY, $saved );
        self::clearCache();
    }

    /**
     * Tüm ayarları kaydet.
     */
    public static function saveAll( array $settings ): void
    {
        $previous = self::all();
        update_option( self::OPTION_KEY, $settings );
        self::clearCache();

        do_action( 'sh/reviews/saved', $settings, $previous );
        foreach ( $settings as $group => $values ) {
            if ( ! is_array( $values ) ) continue;
            foreach ( $values as $key => $new_val ) {
                $old_val = $previous[ $group ][ $key ] ?? null;
                if ( $old_val !== $new_val ) {
                    do_action( 'sh/reviews/setting_changed', $group . '.' . $key, $new_val, $old_val, $settings );
                }
            }
        }
    }

    /**
     * Factory defaults'a sıfırla.
     */
    public static function reset(): void
    {
        delete_option( self::OPTION_KEY );
        self::clearCache();
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * DISABLE_REVIEW_APPROVE define'ı için — disable_review_approve field'ından okur.
     * true  = onay mekanizması devre dışı (yorumlar direkt yayınlanır)
     * false = admin onayı gerekli
     *
     * define("DISABLE_REVIEW_APPROVE", ReviewsSettings::isApproveDisabled());
     */
    public static function isApproveDisabled(): bool
    {
        return (bool) self::get( 'general.disable_review_approve' );
    }

    // ─── HELPERS (devam) ─────────────────────────────────────────────────────

    /**
     * Post type için aktif criteria'yı döndür.
     * Öncelik: post_types[$post_type] → default → []
     *
     * @return array{key: string, label: string, weight: float}[]
     */
    /**
     * Object type + subtype için aktif criteria'yı döndür.
     *
     * @param string $post_type  post_type slug (post için) veya WP role slug (user için)
     * @param string $context    'post' | 'user'
     */
    public static function getCriteria( string $post_type = '', string $context = 'post' ): array
    {
        $all      = self::all();
        $criteria = $all['rating']['criteria'] ?? [];

        if ( $context === 'user' ) {
            // User role bazlı override
            if ( $post_type && ! empty( $criteria['user_roles'][ $post_type ] ) ) {
                return $criteria['user_roles'][ $post_type ];
            }
            // User default
            if ( ! empty( $criteria['user_roles']['default'] ) ) {
                return $criteria['user_roles']['default'];
            }
            return [];
        }

        // Post type bazlı override var mı?
        if ( $post_type && ! empty( $criteria['post_types'][ $post_type ] ) ) {
            return $criteria['post_types'][ $post_type ];
        }

        // Global default
        return $criteria['default'] ?? [];
    }

    /**
     * Object type + subtype için review config döndür.
     * Öncelik: types[object_type:subtype] → types[object_type] → defaults
     *
     * @param string $object_type  'post' | 'user'
     * @param string $subtype      post_type slug veya WP role slug — boş = fallback
     * @return array{ enabled: bool, modes: string[], criteria: array[] }
     */
    public static function getTypeConfig( string $object_type, string $subtype = '' ): array
    {
        $types = self::all()['types'] ?? [];

        // 1. Subtype override (post:product, user:author vb.)
        if ( $subtype ) {
            $key = $object_type . ':' . $subtype;
            if ( isset( $types[ $key ] ) ) {
                return array_merge(
                    [ 'enabled' => false, 'modes' => [], 'criteria' => [] ],
                    $types[ $key ]
                );
            }
        }

        // 2. Object type fallback
        if ( isset( $types[ $object_type ] ) ) {
            return array_merge(
                [ 'enabled' => false, 'modes' => [], 'criteria' => [] ],
                $types[ $object_type ]
            );
        }

        return [ 'enabled' => false, 'modes' => [], 'criteria' => [] ];
    }

    /**
     * Belirtilen object type + subtype için review yapılabilir mi?
     */
    public static function canReviewType( string $object_type, string $subtype = '' ): bool
    {
        if ( ! self::get( 'general.enable_reviews' ) ) return false;
        return (bool) self::getTypeConfig( $object_type, $subtype )['enabled'];
    }

    /**
     * Criteria array'ini normalize et — her item'ın key/label/weight'i garantili olsun.
     *
     * @param  array $raw  [['key'=>'...','label'=>'...','weight'=>1.0], ...]
     * @return array
     */
    public static function normalizeCriteria( array $raw ): array
    {
        $result = [];
        foreach ( $raw as $item ) {
            $key = sanitize_key( $item['key'] ?? '' );
            if ( ! $key ) continue;
            $result[] = [
                'key'    => $key,
                'label'  => sanitize_text_field( $item['label'] ?? $key ),
                'weight' => max( 0.1, min( 5.0, (float) ( $item['weight'] ?? 1.0 ) ) ),
            ];
        }
        return $result;
    }

    private static function clearCache(): void
    {
        wp_cache_delete( self::OPTION_KEY, 'sh_reviews' );
    }

    private static function deepMerge( array $defaults, array $saved ): array
    {
        $result = $defaults;
        foreach ( $saved as $key => $value ) {
            if ( is_array( $value ) && isset( $result[ $key ] ) && is_array( $result[ $key ] ) ) {
                $result[ $key ] = self::deepMerge( $result[ $key ], $value );
            } else {
                $result[ $key ] = $value;
            }
        }
        return $result;
    }
}
