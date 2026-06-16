<?php

namespace SaltHareket\Reactions;

/**
 * ReactionsSettings
 * Reaction tipleri ve konfigurasyonlarini yonetir.
 * wp_options'da 'sh_reactions_settings' key'i altinda saklanir.
 *
 * @version 1.2.0
 * @changelog
 *   1.2.0 - 2026-05-08
 *     - Change: defaultTypes() — toggle/exclusive kaldirildi, mode + limit eklendi
 *     - Add: mode — 'toggle' | 'additive' | 'cumulative'
 *     - Add: limit — cumulative modda kullanici basina max tik sayisi
 *     - Change: saveTypes() — syncReactionEvents cagrisi kaldirildi, filter ile cozuldu
 *   1.1.0 - 2026-05-07
 *     - Add: getButtonStyles()
 *     - Add: saveObjectTypes(), getAllObjectTypes()
 *   1.0.0 - 2026-05-06 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Tum reaction tiplerini al
 * ReactionsSettings::getTypes();
 *
 * // Belirli bir tip icin config
 * ReactionsSettings::getType('like');
 * // → ['label'=>'Like', 'mode'=>'toggle', 'limit'=>0, 'icon_off'=>'far fa-heart', ...]
 *
 * // Runtime'da yeni tip ekle (kod'dan)
 * ReactionsSettings::registerType('clap', [
 *     'label'    => 'Clap',
 *     'icon_off' => 'far fa-hands-clapping',
 *     'icon_on'  => 'fas fa-hands-clapping',
 *     'color'    => '#f59e0b',
 *     'mode'     => 'cumulative',
 *     'limit'    => 50,
 * ]);
 *
 * // Tipleri kaydet (admin'den)
 * ReactionsSettings::saveTypes($types);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $config = ReactionsSettings::getType('clap');
 *   echo $config['mode'];  // 'cumulative'
 *   echo $config['limit']; // 50
 *
 * @example
 *   $config = ReactionsSettings::getType('like');
 *   echo $config['mode'];  // 'toggle'
 *
 * @example
 *   // Cumulative type tanimla
 *   ReactionsSettings::registerType('clap', ['mode'=>'cumulative','limit'=>50,'label'=>'Clap','color'=>'#f59e0b']);
 *
 * @example
 *   // Additive type tanimla (sadece ekle, kaldir yok)
 *   ReactionsSettings::registerType('view', ['mode'=>'additive','label'=>'View','color'=>'#6b7280']);
 *
 * @example
 *   $styles = ReactionsSettings::getButtonStyles();
 *   // ['icon-only'=>'Icon Only', 'icon-count'=>'Icon + Count', ...]
 *
 * @package SaltHareket\Reactions
 */
class ReactionsSettings
{
    private const OPTION_KEY = 'sh_reactions_settings';

    // ─── DEFAULT REACTION TİPLERİ ────────────────────────────────────────────

    /**
     * Sistem genelinde tanımlı reaction tipleri.
     * Admin'den override edilebilir, kod'dan registerType() ile genişletilebilir.
     */
    public static function defaultTypes(): array
    {
        return [
            'like' => [
                'label'        => 'Like',
                'icon_off'     => 'far fa-heart',
                'icon_on'      => 'fas fa-heart',
                'color'        => '#e11d48',
                'mode'         => 'toggle',
                'limit'        => 0,
                'notify_event' => 'new-like',
            ],
            'follow' => [
                'label'        => 'Follow',
                'label_on'     => 'Following',
                'icon_off'     => 'far fa-bell',
                'icon_on'      => 'fas fa-bell',
                'color'        => '#2271b1',
                'mode'         => 'toggle',
                'limit'        => 0,
                'notify_event' => 'new-follower',
            ],
            'favorite' => [
                'label'        => 'Favorite',
                'label_on'     => 'Favorited',
                'icon_off'     => 'far fa-star',
                'icon_on'      => 'fas fa-star',
                'color'        => '#f59e0b',
                'mode'         => 'toggle',
                'limit'        => 0,
                'notify_event' => '',
            ],
            'bookmark' => [
                'label'        => 'Save',
                'label_on'     => 'Saved',
                'icon_off'     => 'far fa-bookmark',
                'icon_on'      => 'fas fa-bookmark',
                'color'        => '#6366f1',
                'mode'         => 'toggle',
                'limit'        => 0,
                'notify_event' => '',
            ],
        ];
    }

    /**
     * Default object type → reaction mapping.
     * Her object type için hangi reaction'ların aktif olduğu ve nasıl görüneceği.
     * Admin'den override edilebilir, post_type bazlı da tanımlanabilir.
     */
    public static function defaultObjectTypes(): array
    {
        return [
            // ── Post (tüm post type'lar için fallback) ──
            'post' => [
                'reactions' => [
                    ['type' => 'like',     'style' => 'icon-count', 'position' => 'inline'],
                    ['type' => 'favorite', 'style' => 'icon-only',  'position' => 'inline'],
                    ['type' => 'follow',   'style' => 'pill',       'position' => 'inline'],
                    ['type' => 'bookmark', 'style' => 'icon-only',  'position' => 'inline'],
                ],
            ],
            // ── User ──
            'user' => [
                'reactions' => [
                    ['type' => 'follow', 'style' => 'pill',       'position' => 'inline'],
                    ['type' => 'like',   'style' => 'icon-count', 'position' => 'inline'],
                ],
            ],
            // ── Comment / Review ──
            'comment' => [
                'reactions' => [
                    ['type' => 'like', 'style' => 'icon-count', 'position' => 'inline'],
                ],
            ],
            // ── Term (kategori, tag, custom taxonomy) ──
            'term' => [
                'reactions' => [
                    ['type' => 'follow',   'style' => 'pill',      'position' => 'inline'],
                    ['type' => 'favorite', 'style' => 'icon-only', 'position' => 'inline'],
                ],
            ],
        ];
    }

    // ─── READ ─────────────────────────────────────────────────────────────────

    /**
     * Tüm reaction tiplerini döndür — default + saved + runtime registered.
     */
    public static function getTypes(): array
    {
        static $runtime = [];
        $saved   = get_option( self::OPTION_KEY . '_types', [] );
        $merged  = array_merge( self::defaultTypes(), is_array( $saved ) ? $saved : [], $runtime );
        return $merged;
    }

    /**
     * Tek bir reaction tipi config'ini döndür.
     */
    public static function getType( string $type ): ?array
    {
        return self::getTypes()[ $type ] ?? null;
    }

    /**
     * Object type için aktif reaction'ları döndür.
     * Öncelik: post_type override → object_type default → boş
     *
     * @param string $object_type  'post' | 'user' | 'comment' | 'term'
     * @param string $subtype      post_type slug (örn: 'product', 'session')
     */
    public static function getForObject( string $object_type, string $subtype = '' ): array
    {
        $saved = get_option( self::OPTION_KEY . '_objects', [] );
        $saved = is_array( $saved ) ? $saved : [];

        // 1. Subtype override (örn: 'post:product')
        $subtype_key = $object_type . ':' . $subtype;
        if ( $subtype && isset( $saved[ $subtype_key ] ) ) {
            return $saved[ $subtype_key ]['reactions'] ?? [];
        }

        // 2. Saved object type
        if ( isset( $saved[ $object_type ] ) ) {
            return $saved[ $object_type ]['reactions'] ?? [];
        }

        // 3. Default
        return self::defaultObjectTypes()[ $object_type ]['reactions'] ?? [];
    }

    /**
     * Tüm object type config'lerini döndür.
     */
    public static function getAllObjectTypes(): array
    {
        $saved = get_option( self::OPTION_KEY . '_objects', [] );
        $saved = is_array( $saved ) ? $saved : [];
        return array_merge( self::defaultObjectTypes(), $saved );
    }

    // ─── WRITE ────────────────────────────────────────────────────────────────

    /**
     * Runtime'da yeni reaction tipi ekle (kod'dan).
     * Admin sayfası yenilenmeden önce geçerli.
     */
    public static function registerType( string $key, array $config ): void
    {
        // Static cache'e ekle — getTypes() bunu okur
        static $runtime = [];
        $runtime[ $key ] = $config;
    }

    /**
     * Reaction tiplerini kaydet.
     */
    public static function saveTypes( array $types ): void
    {
        update_option( self::OPTION_KEY . '_types', $types );
    }

    /**
     * Object type config'lerini kaydet.
     */
    public static function saveObjectTypes( array $objects ): void
    {
        update_option( self::OPTION_KEY . '_objects', $objects );
    }

    /**
     * Sıfırla.
     */
    public static function reset(): void
    {
        delete_option( self::OPTION_KEY . '_types' );
        delete_option( self::OPTION_KEY . '_objects' );
    }

    // ─── BUTTON STYLES ───────────────────────────────────────────────────────

    /**
     * Mevcut button stilleri.
     */
    public static function getButtonStyles(): array
    {
        return [
            'icon-only'  => 'Icon Only (♡)',
            'icon-count' => 'Icon + Count (♡ 42)',
            'icon-text'  => 'Icon + Text (♡ Like)',
            'text-only'  => 'Text Only (Like / Following)',
            'pill'       => 'Pill Button ([♡ Like])',
            'pill-count' => 'Pill + Count ([♡ 42])',
        ];
    }
}
