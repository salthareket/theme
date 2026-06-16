<?php

namespace SaltHareket\Notifications;

/**
 * NotifyRegistry
 * PHP-first event registry. ACF'e sıfır bağımlılık.
 * Tüm event tanımları burada saklanır, runtime'da okunur.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Event tanımla (NotifyEvent::define() kısa yolunu kullan)
 * NotifyEvent::define('order/new', [...]);
 *
 * // Direkt registry'e yaz
 * NotifyRegistry::set('order/new', $event);
 *
 * // Event'i al
 * $event = NotifyRegistry::get('order/new');
 *
 * // Tüm event'leri al
 * $all = NotifyRegistry::all();
 *
 * // Gruba göre filtrele
 * $orders = NotifyRegistry::byGroup('orders');
 *
 * // Event var mı?
 * NotifyRegistry::has('order/new'); // true/false
 *
 * // Admin override'ı uygula (DB'den gelen per-event overrides)
 * NotifyRegistry::applyOverrides([
 *     'order/new' => ['channels' => ['alert']] // email'i kapat
 * ]);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   NotifyEvent::define('order/new', ['label' => 'New Order', 'channels' => ['alert', 'email']]);
 *   $event = NotifyRegistry::get('order/new');
 *
 * @example
 *   NotifyRegistry::all(); // ['order/new' => NotifyEvent, ...]
 *
 * @example
 *   NotifyRegistry::byGroup('orders'); // sadece orders grubundaki event'ler
 *
 * @example
 *   NotifyRegistry::has('order/new'); // true
 *
 * @example
 *   NotifyRegistry::keys(); // ['order/new', 'comment/new', ...]
 */
final class NotifyRegistry
{
    /** @var array<string, NotifyEvent> */
    private static array $events = [];

    /** @var array<string, array> Admin'den gelen per-event override'lar */
    private static array $overrides = [];

    private static bool $overridesLoaded = false;

    /**
     * Event'i registry'e kaydet.
     */
    public static function set( string $key, NotifyEvent $event ): void
    {
        self::$events[$key] = $event;
    }

    /**
     * Event'i al. Override varsa merge edilmiş versiyonu döner.
     */
    public static function get( string $key ): ?NotifyEvent
    {
        self::maybeLoadOverrides();

        if ( ! isset( self::$events[$key] ) ) return null;

        // Override varsa yeni event instance oluştur
        if ( isset( self::$overrides[$key] ) ) {
            $base   = self::$events[$key]->toArray();
            $merged = array_merge( $base, self::$overrides[$key] );
            return NotifyEvent::make( $key, $merged );
        }

        return self::$events[$key];
    }

    /**
     * Event var mı?
     */
    public static function has( string $key ): bool
    {
        return isset( self::$events[$key] );
    }

    /**
     * Tüm event'leri döner (override'lar uygulanmış).
     *
     * @return array<string, NotifyEvent>
     */
    public static function all(): array
    {
        self::maybeLoadOverrides();
        $result = [];
        foreach ( array_keys( self::$events ) as $key ) {
            $result[$key] = self::get( $key );
        }
        return $result;
    }

    /**
     * Tüm event key'lerini döner.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys( self::$events );
    }

    /**
     * Gruba göre event'leri filtrele.
     *
     * @return array<string, NotifyEvent>
     */
    public static function byGroup( string $group ): array
    {
        return array_filter(
            self::all(),
            fn( NotifyEvent $e ) => $e->group === $group
        );
    }

    /**
     * Admin'den gelen override'ları uygula.
     * Format: ['event/key' => ['channels' => [...], 'email' => [...]]]
     */
    public static function applyOverrides( array $overrides ): void
    {
        self::$overrides      = $overrides;
        self::$overridesLoaded = true;
    }

    /**
     * Override'ları DB'den yükle (lazy, bir kez).
     * wp_options'da 'sh_notify_overrides' key'inde JSON olarak saklanır.
     */
    private static function maybeLoadOverrides(): void
    {
        if ( self::$overridesLoaded ) return;
        self::$overridesLoaded = true;

        $raw = get_option( 'sh_notify_overrides', '' );
        if ( empty( $raw ) ) return;

        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            self::$overrides = $decoded;
        }
    }

    /**
     * Override'ları DB'ye kaydet.
     */
    public static function saveOverrides( array $overrides ): void
    {
        self::$overrides = $overrides;
        update_option( 'sh_notify_overrides', wp_json_encode( $overrides ), false );
    }

    /**
     * Tek event için override kaydet.
     */
    public static function saveEventOverride( string $key, array $override ): void
    {
        self::maybeLoadOverrides();
        self::$overrides[$key] = $override;
        update_option( 'sh_notify_overrides', wp_json_encode( self::$overrides ), false );
    }

    /**
     * Registry'i temizle (test için).
     */
    public static function flush(): void
    {
        self::$events          = [];
        self::$overrides       = [];
        self::$overridesLoaded = false;
    }
}
