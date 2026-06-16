<?php

namespace SaltHareket\Notifications;

/**
 * NotifyEvent
 * Immutable value object — bir notification event'inin tüm konfigürasyonunu taşır.
 * ACF'e sıfır bağımlılık. Tüm tanımlar PHP kodunda yapılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Basit event tanımı
 * NotifyEvent::define('order/new', [
 *     'label'     => 'New Order',
 *     'channels'  => ['alert', 'email'],
 *     'sender'    => '{{admin}}',
 *     'recipient' => '{{user}}',
 *     'email'     => ['subject' => 'New order: {{ data.post.title }}', 'body' => 'template'],
 *     'alert'     => ['body' => 'You have a new order from {{ data.user.name }}.'],
 * ]);
 *
 * // Digest ile (15 dk içinde aynı post'a gelen yorumları birleştir)
 * NotifyEvent::define('comment/new', [
 *     'channels' => ['alert', 'email'],
 *     'digest'   => ['window' => 900, 'key' => 'post_id'],
 *     'alert'    => ['body' => '{{ data.digest_count }} new comments on {{ data.post.title }}'],
 * ]);
 *
 * // Throttle ile (24 saatte 1 kez)
 * NotifyEvent::define('promo/flash', [
 *     'channels'  => ['email', 'push'],
 *     'throttle'  => ['limit' => 1, 'window' => 86400],
 *     'priority'  => NotifyPriority::LOW,
 * ]);
 *
 * // Critical — throttle/quiet-hours bypass
 * NotifyEvent::define('account/security', [
 *     'channels'  => ['email', 'sms', 'push'],
 *     'priority'  => NotifyPriority::CRITICAL,
 * ]);
 *
 * // Delay ile (10 dk sonra gönder — iptal edilebilir)
 * NotifyEvent::define('order/shipped', [
 *     'channels' => ['email', 'push'],
 *     'delay'    => 600,
 * ]);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $event = NotifyEvent::make('order/new', [...]);
 *   $event->key;        // 'order/new'
 *   $event->channels;   // ['alert', 'email']
 *   $event->priority;   // NotifyPriority::NORMAL
 *
 * @example
 *   $event->getChannelConfig('email');
 *   // ['subject' => '...', 'body' => '...']
 *
 * @example
 *   $event->hasChannel('push'); // true/false
 *
 * @example
 *   $event->hasDigest();  // true/false
 *   $event->digestWindow; // 900 (saniye)
 *
 * @example
 *   $event->hasThrottle();   // true/false
 *   $event->throttleLimit;   // 1
 *   $event->throttleWindow;  // 86400
 */
final class NotifyEvent
{
    public readonly string         $key;
    public readonly string         $label;
    public readonly string         $group;
    public readonly array          $channels;
    public readonly string         $sender;
    public readonly string         $recipient;
    public readonly NotifyPriority $priority;
    public readonly bool           $userCanDisable;

    // Digest
    public readonly bool   $digest;
    public readonly int    $digestWindow;  // saniye
    public readonly string $digestKey;     // 'post_id' | 'user_id' | ''

    // Throttle
    public readonly bool $throttle;
    public readonly int  $throttleLimit;
    public readonly int  $throttleWindow; // saniye

    // Delay
    public readonly int $delay; // saniye, 0 = anında

    // Per-channel config: ['email' => [...], 'alert' => [...], ...]
    private array $channelConfigs;

    private function __construct( string $key, array $config )
    {
        $this->key      = $key;
        $this->label    = $config['label']    ?? $key;
        $this->group    = $config['group']    ?? 'general';
        $this->channels = $config['channels'] ?? ['alert'];
        $this->sender   = $config['sender']   ?? '{{admin}}';
        $this->recipient = $config['recipient'] ?? '{{user}}';
        $this->priority = $config['priority'] ?? NotifyPriority::NORMAL;
        $this->userCanDisable = $config['user_can_disable'] ?? true;

        // Digest
        $digest             = $config['digest'] ?? null;
        $this->digest       = ! empty( $digest );
        $this->digestWindow = (int) ( $digest['window'] ?? 900 );
        $this->digestKey    = (string) ( $digest['key'] ?? '' );

        // Throttle
        $throttle             = $config['throttle'] ?? null;
        $this->throttle       = ! empty( $throttle );
        $this->throttleLimit  = (int) ( $throttle['limit']  ?? 1 );
        $this->throttleWindow = (int) ( $throttle['window'] ?? 86400 );

        // Delay
        $this->delay = (int) ( $config['delay'] ?? 0 );

        // Channel configs
        $this->channelConfigs = [];
        foreach ( ['alert', 'email', 'sms', 'push'] as $ch ) {
            if ( isset( $config[$ch] ) ) {
                $this->channelConfigs[$ch] = $config[$ch];
            }
        }
    }

    /**
     * Event instance oluştur (registry kullanmadan).
     */
    public static function make( string $key, array $config ): self
    {
        return new self( $key, $config );
    }

    /**
     * Event'i global registry'e kaydet.
     * Kısa yol — NotifyRegistry::register() ile aynı.
     */
    public static function define( string $key, array $config ): self
    {
        $event = new self( $key, $config );
        NotifyRegistry::set( $key, $event );
        return $event;
    }

    public function hasChannel( string $channel ): bool
    {
        return in_array( $channel, $this->channels, true );
    }

    public function getChannelConfig( string $channel ): array
    {
        return $this->channelConfigs[$channel] ?? [];
    }

    public function hasDigest(): bool  { return $this->digest; }
    public function hasThrottle(): bool { return $this->throttle; }
    public function hasDelay(): bool    { return $this->delay > 0; }

    /**
     * Event'i array'e dönüştür (admin UI / JSON export için).
     */
    public function toArray(): array
    {
        return [
            'key'            => $this->key,
            'label'          => $this->label,
            'group'          => $this->group,
            'channels'       => $this->channels,
            'sender'         => $this->sender,
            'recipient'      => $this->recipient,
            'priority'       => $this->priority->value,
            'user_can_disable' => $this->userCanDisable,
            'digest'         => $this->digest ? ['window' => $this->digestWindow, 'key' => $this->digestKey] : null,
            'throttle'       => $this->throttle ? ['limit' => $this->throttleLimit, 'window' => $this->throttleWindow] : null,
            'delay'          => $this->delay,
            'channel_configs' => $this->channelConfigs,
        ];
    }
}
