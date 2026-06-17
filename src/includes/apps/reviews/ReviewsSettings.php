<?php

namespace SaltHareket\Reviews;

/**
 * ReviewsSettings
 * Reviews sistemi için tüm ayarları yönetir.
 * wp_options tablosunda 'sh_reviews_settings' key'i altında JSON olarak saklanır.
 *
 * @version 1.0.1
 * @changelog
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
                'disable_review_approve' => false,  // true = yorum onayı devre dışı (otomatik yayın)
                'auto_approve_reviews'   => false,   // false = admin onayı gerekli
                'auto_approve_verified'  => true,    // verified kullanıcı → otomatik
                'auto_approve_trusted'   => true,    // güvenilir kullanıcı → otomatik
                'trusted_threshold'      => 3,       // kaç onaylı review sonra güvenilir
                'require_login'          => true,
                'one_review_per_user'    => true,
            ],
            'rating' => [
                'type'           => 'stars',    // stars | thumbs | nps | multi
                'max_rating'     => 5,          // 5 veya 10
                'allow_half'     => false,      // 4.5 gibi
                'show_breakdown' => true,       // dağılım göster
                'criteria'       => [
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

        $saved   = get_option( self::OPTION_KEY, [] );
        $saved   = is_array( $saved ) ? $saved : [];
        $cache   = self::deepMerge( self::defaults(), $saved );
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
    public static function getCriteria( string $post_type = '' ): array
    {
        $all      = self::all();
        $criteria = $all['rating']['criteria'] ?? [];

        // Post type bazlı override var mı?
        if ( $post_type && ! empty( $criteria['post_types'][ $post_type ] ) ) {
            return $criteria['post_types'][ $post_type ];
        }

        // Global default
        return $criteria['default'] ?? [];
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
