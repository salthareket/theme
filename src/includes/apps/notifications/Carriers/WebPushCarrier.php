<?php

namespace SaltHareket\Notifications\Carriers;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyPriority;
use SaltHareket\Notifications\NotifyResult;

/**
 * WebPushCarrier
 * Browser push notification gönderir.
 * minishlink/web-push (^10.0) kullanır.
 * VAPID key'leri wp_options'da saklanır.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-04
 *     - Fix: content_encoding default 'aes128gcm' (RFC 8291 zorunlu)
 *     - Fix: flushPooled() kullanılıyor (2x hız)
 *     - Fix: TTL, urgency, topic header'ları eklendi
 *     - Fix: setReuseVAPIDHeaders(true) eklendi
 *     - Fix: VAPID::createVapidKeys() doğru sınıftan çağrılıyor
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * NotifyEvent::define('order/new', [
 *     'channels' => ['alert', 'push'],
 *     'push'     => [
 *         'title'   => 'New Order',
 *         'body'    => 'Order #{{ data.post.ID }} received.',
 *         'icon'    => '/static/img/icon-192.png',
 *         'url'     => '{{ data.post.link }}',
 *         'image'   => '',           // büyük görsel (opsiyonel)
 *         'actions' => [             // action butonları (opsiyonel)
 *             ['action' => 'view',    'title' => 'View Order'],
 *             ['action' => 'dismiss', 'title' => 'Dismiss'],
 *         ],
 *         'ttl'     => 3600,         // saniye (default: 4 hafta)
 *         'urgency' => 'normal',     // very-low|low|normal|high
 *     ],
 * ]);
 *
 * // VAPID key üret (bir kez, otomatik yapılır):
 * WebPushCarrier::generateVapidKeys();
 *
 * // Subscription kaydet (AJAX'tan):
 * WebPushCarrier::saveSubscription($user_id, $subscription_json);
 *
 * // Subscription sil:
 * WebPushCarrier::deleteSubscription($user_id, $endpoint);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // HTTPS zorunlu — HTTP'de çalışmaz (localhost hariç)
 *   // PHP gmp veya mbstring extension gerekli
 *
 * @example
 *   // Subscription JSON (browser'dan gelir):
 *   // {"endpoint":"https://...","keys":{"p256dh":"...","auth":"..."}}
 *
 * @example
 *   // Geçersiz subscription (410 Gone) otomatik silinir
 *
 * @example
 *   // urgency: 'high' → CRITICAL/HIGH priority event'lerde otomatik set edilir
 *
 * @example
 *   $result = $carrier->handle($payload);
 *   $result->success;    // true — en az 1 cihaza gitti
 *   $result->insert_id;  // gönderilen cihaz sayısı
 */
class WebPushCarrier implements NotifyCarrier
{
    private const VAPID_OPTION  = 'sh_notify_vapid_keys';
    private const TABLE_SUFFIX  = 'notify_push_subscriptions';

    // Priority → urgency mapping
    private const URGENCY_MAP = [
        'critical' => 'high',
        'high'     => 'high',
        'normal'   => 'normal',
        'low'      => 'low',
    ];

    public function channel(): string
    {
        return 'push';
    }

    public function handle( NotifyPayload $payload ): NotifyResult
    {
        if ( ! class_exists( WebPush::class ) ) {
            return NotifyResult::fail( 'push', 'minishlink/web-push not installed. Run: composer require minishlink/web-push' );
        }

        $vapid = $this->getVapidKeys();
        if ( ! $vapid ) {
            return NotifyResult::fail( 'push', 'VAPID keys not configured.' );
        }

        $subscriptions = self::getSubscriptions( $payload->receiver_id );
        if ( empty( $subscriptions ) ) {
            return NotifyResult::skipped( 'push', 'no_subscription' );
        }

        $config  = $payload->getChannelConfig();
        $message = wp_json_encode( [
            'title'   => $config['title']   ?? get_bloginfo( 'name' ),
            'body'    => $payload->rendered_body,
            'icon'    => $config['icon']    ?? '',
            'image'   => $config['image']   ?? '',
            'badge'   => $config['badge']   ?? '',
            'url'     => $config['url']     ?? home_url(),
            'tag'     => $payload->event->key,
            'actions' => $config['actions'] ?? [],
        ] );

        // TTL: event config'den al, yoksa priority'ye göre belirle
        $ttl = (int) ( $config['ttl'] ?? $this->defaultTtl( $payload->priority ) );

        // Urgency: event config'den al, yoksa priority'den map'le
        $urgency = $config['urgency'] ?? self::URGENCY_MAP[$payload->priority->value] ?? 'normal';

        // Topic: event key'den türet (max 32 char, URL-safe)
        $topic = substr( preg_replace( '/[^a-zA-Z0-9_-]/', '-', $payload->event->key ), 0, 32 );

        try {
            $webPush = new WebPush( [
                'VAPID' => [
                    'subject'    => home_url(),
                    'publicKey'  => $vapid['public'],
                    'privateKey' => $vapid['private'],
                ],
            ] );

            // Aynı push service'e giden mesajlar için JWT token'ı yeniden kullan
            $webPush->setReuseVAPIDHeaders( true );

            $sent    = 0;
            $expired = [];

            foreach ( $subscriptions as $sub ) {
                $subscription = Subscription::create( [
                    'endpoint'        => $sub->endpoint,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                    'keys'            => [
                        'p256dh' => $sub->p256dh,
                        'auth'   => $sub->auth,
                    ],
                ] );

                $webPush->queueNotification( $subscription, $message, [
                    'TTL'     => $ttl,
                    'urgency' => $urgency,
                    'topic'   => $topic,
                ] );
            }

            // flush: Generator tabanlı — her notification için MessageSentReport döner
            foreach ( $webPush->flush() as $report ) {
                if ( $report->isSuccess() ) {
                    $sent++;
                } elseif ( $report->isSubscriptionExpired() ) {
                    $expired[] = $report->getEndpoint();
                } else {
                    // Debug: hata nedenini logla
                    error_log( '[WebPush] Delivery failed: ' . $report->getReason() . ' | endpoint: ' . substr( $report->getEndpoint(), 0, 60 ) );
                }
            }

            // Geçersiz subscription'ları temizle
            if ( ! empty( $expired ) ) {
                self::deleteExpiredSubscriptions( $payload->receiver_id, $expired );
            }

        } catch ( \Throwable $e ) {
            return NotifyResult::fail( 'push', $e->getMessage() );
        }

        if ( $sent === 0 ) {
            return NotifyResult::fail( 'push', 'All push deliveries failed' );
        }

        return NotifyResult::ok( 'push', $sent );
    }

    /**
     * Priority'ye göre default TTL belirle.
     */
    private function defaultTtl( NotifyPriority $priority ): int
    {
        return match( $priority ) {
            NotifyPriority::CRITICAL => 0,          // Sadece online ise gönder
            NotifyPriority::HIGH     => 3600,        // 1 saat
            NotifyPriority::NORMAL   => 86400,       // 1 gün
            NotifyPriority::LOW      => 604800,      // 1 hafta
        };
    }

    // ─── VAPID KEY YÖNETİMİ ──────────────────────────────────────────────────

    /**
     * VAPID key çifti üret ve wp_options'a kaydet.
     * Bir kez çalıştırılır — bootstrap'ta otomatik tetiklenir.
     */
    public static function generateVapidKeys(): array
    {
        if ( ! class_exists( VAPID::class ) ) {
            throw new \RuntimeException( 'minishlink/web-push not installed.' );
        }

        $keys = VAPID::createVapidKeys();
        update_option( self::VAPID_OPTION, [
            'public'  => $keys['publicKey'],
            'private' => $keys['privateKey'],
        ], false );

        return $keys;
    }

    /**
     * VAPID public key'i döner (frontend'e verilir).
     */
    public static function getPublicKey(): string
    {
        $keys = get_option( self::VAPID_OPTION, [] );
        return $keys['public'] ?? '';
    }

    private function getVapidKeys(): ?array
    {
        $keys = get_option( self::VAPID_OPTION, [] );
        if ( empty( $keys['public'] ) || empty( $keys['private'] ) ) return null;
        return $keys;
    }

    // ─── SUBSCRIPTION YÖNETİMİ ───────────────────────────────────────────────

    /**
     * Kullanıcının push subscription'ını kaydet.
     * content_encoding: modern tarayıcılar 'aes128gcm' kullanır (RFC 8291).
     *
     * @param int    $user_id
     * @param string $subscription_json  Browser'dan gelen JSON string
     */
    public static function saveSubscription( int $user_id, string $subscription_json ): bool
    {
        global $wpdb;

        $data = json_decode( $subscription_json, true );
        if ( empty( $data['endpoint'] ) ) return false;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // Aynı endpoint varsa güncelle
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND endpoint = %s",
            $user_id,
            $data['endpoint']
        ) );

        $row = [
            'user_id'          => $user_id,
            'endpoint'         => $data['endpoint'],
            'p256dh'           => $data['keys']['p256dh'] ?? '',
            'auth'             => $data['keys']['auth']   ?? '',
            // aes128gcm: RFC 8291 zorunlu, modern tüm tarayıcılar destekler
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'created_at'       => gmdate( 'Y-m-d H:i:s' ),
        ];

        if ( $existing ) {
            return (bool) $wpdb->update( $table, $row, [ 'id' => (int) $existing ] );
        }

        return (bool) $wpdb->insert( $table, $row );
    }

    /**
     * Kullanıcının belirli bir endpoint'ini sil.
     */
    public static function deleteSubscription( int $user_id, string $endpoint ): void
    {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . self::TABLE_SUFFIX,
            [ 'user_id' => $user_id, 'endpoint' => $endpoint ]
        );
    }

    /**
     * Kullanıcının tüm subscription'larını al.
     */
    public static function getSubscriptions( int $user_id ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        ) ) ?: [];
    }

    /**
     * Geçersiz (410 Gone) subscription'ları sil.
     */
    private static function deleteExpiredSubscriptions( int $user_id, array $endpoints ): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        foreach ( $endpoints as $endpoint ) {
            $wpdb->delete( $table, [ 'user_id' => $user_id, 'endpoint' => $endpoint ] );
        }
    }

    // ─── DB SCHEMA ───────────────────────────────────────────────────────────

    public static function createTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . self::TABLE_SUFFIX;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id               bigint(20)   NOT NULL AUTO_INCREMENT,
            user_id          bigint(20)   NOT NULL,
            endpoint         text         NOT NULL,
            p256dh           text         NOT NULL,
            auth             varchar(255) NOT NULL,
            content_encoding varchar(20)  NOT NULL DEFAULT 'aes128gcm',
            created_at       datetime     NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
