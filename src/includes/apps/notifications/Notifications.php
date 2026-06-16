<?php

use SaltHareket\Notifications\NotifyDispatcher;
use SaltHareket\Notifications\NotifyEvent;
use SaltHareket\Notifications\NotifyPreferences;
use SaltHareket\Notifications\NotifyRegistry;
use SaltHareket\Notifications\NotifySchema;
use SaltHareket\Notifications\Carriers\WebPushCarrier;

/**
 * Notifications
 * Ana facade — geriye uyumlu API + yeni sistem.
 * Eski kod kırılmaz: $notif->on(), get_notifications(), get_unseen_notifications_count() çalışır.
 * Yeni API: Notifications::fire(), Notifications::queue(), Notifications::bulk()
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-04
 *     - Refactor: ACF bağımlılığı tamamen kaldırıldı
 *     - Add: PHP-first event registry (NotifyRegistry)
 *     - Add: 4 carrier: alert, email, sms, web push
 *     - Add: Throttle, digest, delay, priority
 *     - Add: User preferences + quiet hours
 *     - Add: Delivery log (wp_notify_log)
 *     - Add: Duplicate prevention
 *     - Add: Async queue (WP Cron)
 *     - Add: Bulk send
 *     - Add: Web Push (VAPID)
 *   1.0.0 - 2026-04-03 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // ── Event tanımla (tema başlangıcında, init öncesi) ──
 * NotifyEvent::define('order/new', [
 *     'label'     => 'New Order',
 *     'group'     => 'orders',
 *     'channels'  => ['alert', 'email', 'push'],
 *     'sender'    => '{{admin}}',
 *     'recipient' => '{{user}}',
 *     'alert'     => ['body' => 'New order from {{ data.user.name }}'],
 *     'email'     => ['subject' => 'New Order #{{ data.post.ID }}', 'body' => 'template'],
 *     'push'      => ['title' => 'New Order', 'body' => 'Order received!', 'url' => '{{ data.post.link }}'],
 * ]);
 *
 * // ── Tetikle ──
 * Notifications::fire('order/new', ['user' => $user, 'post' => $post]);
 *
 * // ── Async ──
 * Notifications::queue('order/shipped', $data);
 *
 * // ── Bulk ──
 * Notifications::bulk('promo/flash', [1, 2, 3], $data);
 *
 * // ── Eski API (geriye uyumlu) ──
 * $notif = new Notifications();
 * $notif->on('order/new', ['user' => $user, 'post' => $post]);
 * $notif->get_notifications(['page' => 1, 'posts_per_page' => 10]);
 * $notif->get_unseen_notifications_count();
 * $notif->get_unseen_notifications();
 * Notifications::delete_post_notifications($post_id);
 * Notifications::delete_user_notifications($user_id);
 *
 * // ── User preferences ──
 * Notifications::setPreference($user_id, 'order/new', 'email', false);
 * Notifications::setQuietHours($user_id, '23:00', '08:00');
 *
 * // ── Web Push ──
 * Notifications::savePushSubscription($user_id, $subscription_json);
 * Notifications::deletePushSubscription($user_id, $endpoint);
 * Notifications::getVapidPublicKey();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   Notifications::fire('order/new', ['user' => $user, 'post' => $post]);
 *
 * @example
 *   $notif = new Notifications($user_object);
 *   $count = $notif->get_unseen_notifications_count();
 *
 * @example
 *   NotifyEvent::define('comment/new', [
 *       'channels' => ['alert'],
 *       'digest'   => ['window' => 900, 'key' => 'post_id'],
 *       'alert'    => ['body' => '{{ data.digest_count }} new comments'],
 *   ]);
 *
 * @example
 *   Notifications::bulk('newsletter/weekly', $all_user_ids, ['content' => $html]);
 *
 * @example
 *   Notifications::setPreference(5, 'promo/flash', 'email', false);
 */
class Notifications
{
    /** Mevcut kullanıcı (eski API için) */
    public mixed $user;

    /** Debug modu */
    public bool  $debug       = false;
    public array $debug_output = [];

    public function __construct( mixed $user = null, int $debug = 0 )
    {
        if ( $user && is_object( $user ) ) {
            $this->user = $user;
        } elseif ( class_exists( '\\Timber\\Timber' ) ) {
            $this->user = \Timber\Timber::get_user( wp_get_current_user() );
        } else {
            $this->user = wp_get_current_user();
        }

        $this->debug = (bool) $debug;

        // DB tablolarını kur (transient cache ile korumalı)
        NotifySchema::install();
    }

    // ─── YENİ API (STATIC) ───────────────────────────────────────────────────

    /**
     * Event'i tetikle — sync.
     */
    public static function fire( string $event_key, array $data = [] ): array
    {
        return NotifyDispatcher::fire( $event_key, $data );
    }

    /**
     * Event'i kuyruğa ekle — async (WP Cron).
     */
    public static function queue( string $event_key, array $data = [] ): void
    {
        NotifyDispatcher::queue( $event_key, $data );
    }

    /**
     * Birden fazla kullanıcıya bulk gönderim.
     *
     * @param int[] $user_ids
     */
    public static function bulk( string $event_key, array $user_ids, array $data = [] ): array
    {
        return NotifyDispatcher::bulk( $event_key, $user_ids, $data );
    }

    // ─── ESKİ API (GERIYE UYUMLU) ────────────────────────────────────────────

    /**
     * Event tetikle — eski API.
     * Yeni sisteme delege eder.
     */
    public function on( string $event_path, array $data = [] ): array
    {
        $results = NotifyDispatcher::fire( $event_path, $data );

        if ( $this->debug ) {
            $this->debug_output = $results;
        }

        // Eski format: ['alert' => 1, 'email' => 1, 'error' => '...']
        $legacy = [];
        foreach ( $results as $channel => $channel_results ) {
            if ( $channel === '_error' ) {
                $legacy['error'] = $channel_results[0]->error ?? 'unknown';
                continue;
            }
            foreach ( (array) $channel_results as $result ) {
                $legacy[$channel] = $result->success ? 1 : 0;
            }
        }

        return $legacy;
    }

    /**
     * Kullanıcının bildirimlerini al (paginated).
     */
    public function get_notifications( array $args = [] ): array
    {
        global $wpdb;

        $table         = $wpdb->prefix . 'notifications';
        $where_clauses = [ 'receiver_id = %d' ];
        $where_values  = [ isset( $args['user'] ) ? (int) $args['user'] : (int) $this->user->ID ];

        if ( isset( $args['post'] ) ) {
            $where_clauses[] = "JSON_EXTRACT(data, '$.post_id') = %d";
            $where_values[]  = (int) $args['post'];
        }
        if ( isset( $args['seen'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = $args['seen'] ? 'read' : 'unread';
        }
        if ( isset( $args['channel'] ) ) {
            $where_clauses[] = 'channel = %s';
            $where_values[]  = sanitize_text_field( $args['channel'] );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

        if ( isset( $args['get_count'] ) ) {
            $query    = $wpdb->prepare( "SELECT count(*) as count FROM {$table} {$where_sql}", $where_values ); // phpcs:ignore
            $paginate = new Paginate( $query );
            return [ 'data' => $paginate->get_totals() ];
        }

        $args['orderby']        = $args['orderby'] ?? 'created_at';
        $args['order']          = $args['order']   ?? 'desc';
        $args['page']           = $args['page']           ?? 1;
        $args['posts_per_page'] = $args['posts_per_page'] ?? 10;

        $query    = $wpdb->prepare( "SELECT * FROM {$table} {$where_sql}", $where_values ); // phpcs:ignore
        $paginate = new Paginate( $query, $args );
        $results  = $paginate->get_results();

        // Mark as seen
        if ( ! empty( $results['posts'] ) ) {
            $ids     = array_map( 'intval', wp_list_pluck( $results['posts'], 'id' ) );
            $ids_sql = implode( ',', $ids );

            if ( isset( $args['set_seen'] ) && $ids_sql ) {
                $wpdb->query( "UPDATE {$table} SET status = 'read' WHERE id IN ({$ids_sql})" ); // phpcs:ignore
            }
        }

        return $results;
    }

    /**
     * Okunmamış bildirim sayısı.
     */
    public function get_unseen_notifications_count(): int
    {
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}notifications WHERE receiver_id = %d AND status = 'unread' AND channel = 'alert'",
            (int) $this->user->ID
        ) );
        return $count;
    }

    /**
     * Okunmamış alert'leri al ve okundu işaretle.
     */
    public function get_unseen_notifications( array $args = [] ): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'notifications';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE receiver_id = %d AND status = 'unread' AND channel = 'alert'
             ORDER BY created_at DESC LIMIT 50",
            (int) $this->user->ID
        ) );

        if ( empty( $rows ) ) return [];

        // Okundu işaretle
        $ids     = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
        $ids_sql = implode( ',', $ids );
        $wpdb->query( "UPDATE {$table} SET status = 'read' WHERE id IN ({$ids_sql})" ); // phpcs:ignore

        $messages = [];
        $timeAgo  = class_exists( '\\Westsworld\\TimeAgo' ) ? new \Westsworld\TimeAgo() : null;

        foreach ( $rows as $row ) {
            $sender = new User( $row->sender_id );
            $url    = '';

            if ( function_exists( 'notification_url_map' ) ) {
                $data    = json_decode( $row->data ?? '{}', true );
                $post_id = (int) ( $data['post_id'] ?? 0 );
                $user_id = (int) ( $data['user_id'] ?? 0 );
                $url     = notification_url_map( $row->event, $post_id, $user_id );
            }

            $time = $row->created_at;
            if ( $timeAgo && method_exists( $this->user, 'get_local_date' ) ) {
                $time = $timeAgo->inWordsFromStrings(
                    $this->user->get_local_date( $row->created_at, $sender->get_timezone(), $this->user->get_timezone() )
                );
            }

            $messages[] = [
                'id'      => $row->id,
                'type'    => 'notification',
                'event'   => $row->event,
                'title'   => '',
                'sender'  => [
                    'image' => get_avatar( $sender->ID, 32, 'mystery', $sender->get_title() ),
                    'name'  => $sender->get_title(),
                ],
                'message' => function_exists( 'truncate' ) ? truncate( strip_tags( $row->message ), 150 ) : substr( strip_tags( $row->message ), 0, 150 ),
                'url'     => $url,
                'time'    => $time,
            ];
        }

        return $messages;
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function delete_user_event_notification( string $event = '', int $user_id = 0 ): void
    {
        global $wpdb;
        if ( ! empty( $event ) && $user_id > 0 ) {
            $wpdb->delete(
                $wpdb->prefix . 'notifications',
                [ 'receiver_id' => $user_id, 'event' => $event ]
            );
        }
    }

    public static function delete_post_notifications( int $post_id = 0 ): void
    {
        global $wpdb;
        if ( $post_id > 0 ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}notifications WHERE JSON_EXTRACT(data, '$.post_id') = %d",
                $post_id
            ) );
        }
    }

    public static function delete_user_notifications( int $user_id = 0 ): void
    {
        global $wpdb;
        if ( $user_id > 0 ) {
            $table = $wpdb->prefix . 'notifications';
            $wpdb->delete( $table, [ 'sender_id'   => $user_id ] );
            $wpdb->delete( $table, [ 'receiver_id' => $user_id ] );
        }
    }

    // ─── PREFERENCES ─────────────────────────────────────────────────────────

    public static function setPreference( int $user_id, string $event, string $channel, bool $enabled ): void
    {
        NotifyPreferences::set( $user_id, $event, $channel, $enabled );
    }

    public static function setQuietHours( int $user_id, string $start, string $end, string $timezone = '' ): void
    {
        NotifyPreferences::setQuietHours( $user_id, $start, $end, $timezone );
    }

    // ─── WEB PUSH ────────────────────────────────────────────────────────────

    public static function savePushSubscription( int $user_id, string $subscription_json ): bool
    {
        return WebPushCarrier::saveSubscription( $user_id, $subscription_json );
    }

    public static function deletePushSubscription( int $user_id, string $endpoint ): void
    {
        WebPushCarrier::deleteSubscription( $user_id, $endpoint );
    }

    public static function getVapidPublicKey(): string
    {
        return WebPushCarrier::getPublicKey();
    }
}
