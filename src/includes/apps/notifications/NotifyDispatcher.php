<?php

namespace SaltHareket\Notifications;

use SaltHareket\Notifications\Carriers\NotifyCarrier;
use SaltHareket\Notifications\Carriers\AlertCarrier;
use SaltHareket\Notifications\Carriers\EmailCarrier;
use SaltHareket\Notifications\Carriers\SmsCarrier;
use SaltHareket\Notifications\Carriers\WebPushCarrier;

/**
 * NotifyDispatcher
 * Notification pipeline'ının kalbi.
 * Event → Receiver çözümleme → Throttle/Digest/Preference kontrolü → Render → Carrier → Log
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Sync gönderim
 * $results = NotifyDispatcher::fire('order/new', [
 *     'user' => $user_object,
 *     'post' => $post_object,
 * ]);
 *
 * // Async gönderim (WP Cron kuyruğuna ekler)
 * NotifyDispatcher::queue('order/new', $data);
 *
 * // Bulk gönderim
 * NotifyDispatcher::bulk('promo/flash', [1, 2, 3, 4, 5], $data);
 *
 * // Custom carrier ekle
 * NotifyDispatcher::addCarrier(new MyCustomCarrier());
 *
 * // Carrier'ı kaldır
 * NotifyDispatcher::removeCarrier('my_channel');
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $results = NotifyDispatcher::fire('order/new', ['user' => $user, 'post' => $post]);
 *   // ['alert' => NotifyResult, 'email' => NotifyResult, ...]
 *
 * @example
 *   NotifyDispatcher::queue('order/shipped', $data);
 *   // WP Cron'a ekler, anında return eder
 *
 * @example
 *   NotifyDispatcher::bulk('promo/flash', [1,2,3], $data);
 *   // 3 kullanıcıya gönderir
 *
 * @example
 *   // Hook'lar
 *   add_action('sh_notify_before_fire', function($event, $data) { ... }, 10, 2);
 *   add_filter('sh_notify_receivers', function($ids, $event, $data) { return $ids; }, 10, 3);
 *   add_action('sh_notify_after_fire', function($event, $results) { ... }, 10, 2);
 *
 * @example
 *   // Test mode — gerçek gönderim yapmaz
 *   add_filter('sh_notify_test_mode', '__return_true');
 *   add_filter('sh_notify_test_receiver', fn() => 1); // admin'e gönder
 */
class NotifyDispatcher
{
    /** @var array<string, NotifyCarrier> */
    private static array $carriers = [];

    private static bool $initialized = false;

    /**
     * Default carrier'ları kaydet.
     */
    public static function init(): void
    {
        if ( self::$initialized ) return;
        self::$initialized = true;

        self::$carriers = [
            'alert' => new AlertCarrier(),
            'email' => new EmailCarrier(),
            'sms'   => new SmsCarrier(),
            'push'  => new WebPushCarrier(),
        ];
    }

    /**
     * Custom carrier ekle veya mevcut carrier'ı override et.
     */
    public static function addCarrier( NotifyCarrier $carrier ): void
    {
        self::$carriers[$carrier->channel()] = $carrier;
    }

    /**
     * Carrier'ı kaldır.
     */
    public static function removeCarrier( string $channel ): void
    {
        unset( self::$carriers[$channel] );
    }

    /**
     * Carrier'ı al.
     */
    public static function getCarrier( string $channel ): ?NotifyCarrier
    {
        self::init();
        return self::$carriers[$channel] ?? null;
    }

    // ─── ANA DISPATCH ────────────────────────────────────────────────────────

    /**
     * Event'i tetikle — sync gönderim.
     * Önce DB rules'ı kontrol eder, sonra PHP registry'i.
     *
     * @param  string $event_key  'order/new' gibi
     * @param  array  $data       ['user' => $user, 'post' => $post, ...]
     * @return array<string, NotifyResult[]>  channel => results[]
     */
    public static function fire( string $event_key, array $data = [] ): array
    {
        self::init();

        // Hook: gönderimden önce
        do_action( 'sh_notify_before_fire', $event_key, $data );

        $all_results = [];

        // 1. DB rules — admin'den tanımlanan kurallar
        $db_results = self::fireFromDb( $event_key, $data );
        foreach ( $db_results as $channel => $results ) {
            $all_results[$channel] = array_merge( $all_results[$channel] ?? [], $results );
        }

        // 2. PHP registry — NotifyEvent::define() ile tanımlananlar
        $event = NotifyRegistry::get( $event_key );
        if ( $event ) {
            $reg_results = self::fireFromRegistry( $event, $data );
            foreach ( $reg_results as $channel => $results ) {
                $all_results[$channel] = array_merge( $all_results[$channel] ?? [], $results );
            }
        }

        if ( empty( $all_results ) ) {
            $all_results['_error'] = [ NotifyResult::fail( '_', "No rules found for event '{$event_key}'." ) ];
        }

        // Hook: gönderimden sonra
        do_action( 'sh_notify_after_fire', $event_key, $all_results );

        return $all_results;
    }

    /**
     * DB rules'dan gönderim.
     */
    private static function fireFromDb( string $event_key, array $data ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'notify_rules';
        $rules = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE event = %s AND active = 1",
            $event_key
        ) );

        if ( empty( $rules ) ) return [];

        $all_results = [];

        foreach ( $rules as $rule ) {
            $carriers = json_decode( $rule->carriers ?? '{}', true ) ?: [];

            // Sender/recipient çöz
            $sender_id    = self::resolveSender( $rule->sender, $data );
            $receiver_ids = self::resolveReceivers( $rule->recipient, $data );
            $receiver_ids = array_values( array_filter( $receiver_ids, fn( $id ) => $id > 0 ) );

            if ( empty( $receiver_ids ) ) continue;

            // Test mode
            if ( apply_filters( 'sh_notify_test_mode', defined( 'SH_NOTIFY_TEST_MODE' ) && SH_NOTIFY_TEST_MODE ) ) {
                $test_receiver = (int) apply_filters( 'sh_notify_test_receiver', get_current_user_id() );
                if ( $test_receiver ) $receiver_ids = [ $test_receiver ];
            }

            foreach ( $receiver_ids as $receiver_id ) {
                foreach ( $carriers as $channel => $cfg ) {
                    if ( empty( $cfg['active'] ) ) continue;
                    $carrier = self::$carriers[$channel] ?? null;
                    if ( ! $carrier ) continue;

                    // DB rule'dan NotifyEvent benzeri bir yapı oluştur
                    $event = \SaltHareket\Notifications\NotifyEvent::make( $event_key, [
                        'label'     => $event_key,
                        'channels'  => [ $channel ],
                        'sender'    => $rule->sender,
                        'recipient' => $rule->recipient,
                        $channel    => [
                            'body'     => $cfg['body']     ?? '',
                            'subject'  => $cfg['subject']  ?? '',
                            'template' => ! empty( $cfg['template'] ),
                            'title'    => $cfg['title']    ?? '',
                            'icon'     => $cfg['icon']     ?? '',
                            'url'      => $cfg['url']      ?? '',
                        ],
                    ] );

                    $payload = new \SaltHareket\Notifications\NotifyPayload(
                        event:       $event,
                        channel:     $channel,
                        sender_id:   $sender_id,
                        receiver_id: $receiver_id,
                        data:        array_merge( $data, [ '_rule_type' => $rule->type ?? 'info' ] ),
                    );

                    // Email template desteği
                    $emailCarrier = ( $channel === 'email' ) ? ( self::$carriers['email'] ?? null ) : null;
                    $payload      = \SaltHareket\Notifications\NotifyRenderer::render( $payload, $emailCarrier );
                    $payload      = apply_filters( 'sh_notify_payload', $payload );
                    $result       = $carrier->handle( $payload );

                    self::log( $payload, $result );

                    $all_results[$channel][] = $result;
                }
            }
        }

        return $all_results;
    }

    /**
     * PHP registry'den gönderim.
     */
    private static function fireFromRegistry( \SaltHareket\Notifications\NotifyEvent $event, array $data ): array
    {
        $sender_id    = self::resolveSender( $event->sender, $data );
        $receiver_ids = self::resolveReceivers( $event->recipient, $data );
        $receiver_ids = apply_filters( 'sh_notify_receivers', $receiver_ids, $event, $data );
        $receiver_ids = array_values( array_filter( $receiver_ids, fn( $id ) => $id > 0 ) );

        if ( empty( $receiver_ids ) ) return [];

        if ( apply_filters( 'sh_notify_test_mode', defined( 'SH_NOTIFY_TEST_MODE' ) && SH_NOTIFY_TEST_MODE ) ) {
            $test_receiver = (int) apply_filters( 'sh_notify_test_receiver', get_current_user_id() );
            if ( $test_receiver ) $receiver_ids = [ $test_receiver ];
        }

        $all_results = [];
        foreach ( $receiver_ids as $receiver_id ) {
            $results = self::dispatchToReceiver( $event, $sender_id, $receiver_id, $data );
            foreach ( $results as $channel => $result ) {
                $all_results[$channel][] = $result;
            }
        }
        return $all_results;
    }

    /**
     * Event'i WP Cron kuyruğuna ekle — async gönderim.
     */
    public static function queue( string $event_key, array $data = [] ): void
    {
        wp_schedule_single_event(
            time(),
            'sh_notify_process_queued',
            [ $event_key, $data ]
        );
    }

    /**
     * Birden fazla kullanıcıya bulk gönderim.
     *
     * @param int[] $user_ids
     */
    public static function bulk( string $event_key, array $user_ids, array $data = [] ): array
    {
        self::init();

        $event = NotifyRegistry::get( $event_key );
        if ( ! $event ) return [];

        $sender_id   = self::resolveSender( $event->sender, $data );
        $all_results = [];

        // Batch'ler halinde işle — memory-safe
        $chunks = array_chunk( $user_ids, 50 );
        foreach ( $chunks as $chunk ) {
            foreach ( $chunk as $receiver_id ) {
                $results = self::dispatchToReceiver( $event, $sender_id, (int) $receiver_id, $data );
                foreach ( $results as $channel => $result ) {
                    $all_results[$channel][] = $result;
                }
            }
        }

        return $all_results;
    }

    // ─── RECEIVER DISPATCH ───────────────────────────────────────────────────

    /**
     * Tek receiver için tüm channel'lara gönder.
     *
     * @return array<string, NotifyResult>
     */
    private static function dispatchToReceiver(
        NotifyEvent $event,
        int         $sender_id,
        int         $receiver_id,
        array       $data
    ): array {
        $results = [];

        foreach ( $event->channels as $channel ) {
            $carrier = self::$carriers[$channel] ?? null;
            if ( ! $carrier ) {
                $results[$channel] = NotifyResult::fail( $channel, "Carrier '{$channel}' not registered." );
                continue;
            }

            // Payload oluştur
            $payload = new NotifyPayload(
                event:       $event,
                channel:     $channel,
                sender_id:   $sender_id,
                receiver_id: $receiver_id,
                data:        $data,
            );

            // ── Kontroller ──────────────────────────────────────────────────

            // 1. Duplicate prevention
            if ( self::isDuplicate( $payload ) ) {
                $results[$channel] = NotifyResult::skipped( $channel, 'duplicate' );
                continue;
            }

            // 2. Throttle
            if ( $event->hasThrottle() && ! $event->priority->bypassesThrottle() ) {
                if ( self::isThrottled( $event, $receiver_id, $channel ) ) {
                    $results[$channel] = NotifyResult::skipped( $channel, 'throttle' );
                    continue;
                }
            }

            // 3. User preference
            if ( $event->userCanDisable && ! NotifyPreferences::isEnabled( $receiver_id, $event->key, $channel ) ) {
                $results[$channel] = NotifyResult::skipped( $channel, 'user_preference' );
                continue;
            }

            // 4. Quiet hours (alert ve push her zaman geçer, email/sms bekler)
            if ( in_array( $channel, [ 'email', 'sms' ], true ) && ! $event->priority->bypassesQuietHours() ) {
                if ( NotifyPreferences::isQuietHour( $receiver_id ) ) {
                    $results[$channel] = NotifyResult::skipped( $channel, 'quiet_hours' );
                    continue;
                }
            }

            // ── Render ──────────────────────────────────────────────────────
            $emailCarrier = ( $channel === 'email' ) ? ( self::$carriers['email'] ?? null ) : null;
            $payload      = NotifyRenderer::render( $payload, $emailCarrier );

            // ── Gönder ──────────────────────────────────────────────────────
            $payload  = apply_filters( 'sh_notify_payload', $payload );
            $result   = $carrier->handle( $payload );

            // ── Log ─────────────────────────────────────────────────────────
            self::log( $payload, $result );

            // ── Throttle kaydı ──────────────────────────────────────────────
            if ( $result->success && $event->hasThrottle() ) {
                self::recordThrottle( $event, $receiver_id, $channel );
            }

            $results[$channel] = $result;
        }

        return $results;
    }

    // ─── YARDIMCI METODLAR ───────────────────────────────────────────────────

    /**
     * Sender placeholder'ını user ID'ye çevir.
     */
    private static function resolveSender( string $placeholder, array $data ): int
    {
        return match( $placeholder ) {
            '{{admin}}'          => self::getAdminId(),
            '{{me}}'             => (int) get_current_user_id(),
            '{{user}}'           => (int) ( $data['user']->ID ?? 0 ),
            '{{author}}'         => (int) ( $data['post']->post_author ?? 0 ),
            default              => is_numeric( $placeholder ) ? (int) $placeholder : 0,
        };
    }

    /**
     * Recipient placeholder'ını user ID array'ine çevir.
     *
     * @return int[]
     */
    private static function resolveReceivers( string $placeholder, array $data ): array
    {
        // Callable placeholder: '{{custom:my_function}}'
        if ( str_starts_with( $placeholder, '{{custom:' ) ) {
            $fn = trim( str_replace( [ '{{custom:', '}}' ], '', $placeholder ) );
            if ( function_exists( $fn ) ) {
                $result = $fn( $data );
                return is_array( $result ) ? array_map( 'intval', $result ) : [ (int) $result ];
            }
        }

        // Role placeholder: '{{role:editor}}'
        if ( str_starts_with( $placeholder, '{{role:' ) ) {
            $role  = trim( str_replace( [ '{{role:', '}}' ], '', $placeholder ) );
            $users = get_users( [ 'role' => $role, 'fields' => 'ID', 'number' => 500 ] );
            return array_map( 'intval', $users );
        }

        return match( $placeholder ) {
            '{{admin}}'  => [ self::getAdminId() ],
            '{{me}}'     => [ (int) get_current_user_id() ],
            '{{user}}'   => [ (int) ( $data['user']->ID ?? $data['recipient'] ?? 0 ) ],
            '{{users}}'  => is_array( $data['recipient'] ?? null )
                                ? array_map( 'intval', $data['recipient'] )
                                : [ (int) ( $data['recipient'] ?? 0 ) ],
            '{{author}}' => [ (int) ( $data['post']->post_author ?? 0 ) ],
            default      => is_numeric( $placeholder ) ? [ (int) $placeholder ] : [],
        };
    }

    /**
     * Site admin ID'sini transient cache ile al.
     */
    private static function getAdminId(): int
    {
        $cached = get_transient( 'sh_notify_admin_id' );
        if ( $cached ) return (int) $cached;

        $users = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
        $id    = (int) ( $users[0] ?? 0 );
        if ( $id ) set_transient( 'sh_notify_admin_id', $id, DAY_IN_SECONDS );
        return $id;
    }

    /**
     * Duplicate kontrolü — aynı idempotency_key son 5 dakikada var mı?
     */
    private static function isDuplicate( NotifyPayload $payload ): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'notifications';
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE idempotency_key = %s
               AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $payload->idempotency_key
        ) );
        return $count > 0;
    }

    /**
     * Throttle kontrolü.
     */
    private static function isThrottled( NotifyEvent $event, int $receiver_id, string $channel ): bool
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'notify_log';
        $window = $event->throttleWindow;
        $limit  = $event->throttleLimit;

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE event = %s AND channel = %s AND receiver_id = %d
               AND status = 'sent'
               AND sent_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $event->key,
            $channel,
            $receiver_id,
            $window
        ) );

        return $count >= $limit;
    }

    /**
     * Throttle kaydı oluştur.
     */
    private static function recordThrottle( NotifyEvent $event, int $receiver_id, string $channel ): void
    {
        // Log tablosuna zaten yazılıyor — throttle için ayrı kayıt gerekmez
        // isThrottled() log tablosunu okur
    }

    /**
     * Gönderim sonucunu log tablosuna yaz.
     */
    private static function log( NotifyPayload $payload, NotifyResult $result ): void
    {
        if ( $result->skipped ) return; // Skip'leri loglamıyoruz

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'notify_log', [
            'notification_id' => $result->insert_id,
            'event'           => $payload->event->key,
            'channel'         => $payload->channel,
            'receiver_id'     => $payload->receiver_id,
            'status'          => $result->success ? 'sent' : 'failed',
            'error'           => $result->success ? null : $result->error,
            'sent_at'         => gmdate( 'Y-m-d H:i:s' ),
        ] );
    }
}
