<?php

namespace SaltHareket\Notifications\Hooks;

/**
 * NotifyHooks
 * WordPress ve WooCommerce hook'larini Notifications::fire() ile baglar.
 * Tum default event tetikleyicileri burada tanimlidir.
 * Notifications app'i disina cikmaz — bootstrap.php'den yuklenir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-08 — Initial release
 *     - Add: Account hooks (new-account, account-activated, password-reset)
 *     - Add: WooCommerce hooks (order-placed, order-completed, order-cancelled, order-refunded, payment-completed)
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // bootstrap.php'de otomatik yuklenir:
 * NotifyHooks::register();
 *
 * // Bir hook'u devre disi birakmak icin:
 * add_filter('sh_notify_hooks_disabled', function($disabled) {
 *     $disabled[] = 'new-account';
 *     return $disabled;
 * });
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Yeni kullanici kaydolunca new-account event'i tetiklenir
 *   // Notifications > Rules'da new-account event'i icin rule tanimla
 *
 * @example
 *   // WooCommerce siparis tamamlaninca order-completed tetiklenir
 *   // Notifications > Rules'da order-completed event'i icin rule tanimla
 *
 * @example
 *   // Hook'u devre disi birak:
 *   add_filter('sh_notify_hooks_disabled', fn($d) => array_merge($d, ['order-cancelled']));
 *
 * @example
 *   // Kendi hook'unu ekle:
 *   add_action('my_custom_action', function($data) {
 *       Notifications::fire('my-custom-event', ['user' => get_userdata($data['user_id']), 'recipient' => $data['user_id']]);
 *   });
 *
 * @example
 *   // Test: bir event'i manuel tetikle
 *   Notifications::fire('new-account', ['user' => get_userdata(1), 'recipient' => 1]);
 */
class NotifyHooks
{
    /**
     * Tum hook'lari kaydet.
     * bootstrap.php'den cagrilir.
     */
    public static function register(): void
    {
        // ── PHP Event Registry — fire() çalışmadan önce event'ler tanımlı olmalı ──
        self::defineReviewEvents();

        // ── Account ──────────────────────────────────────────────────────────
        self::registerAccountHooks();

        // ── Reviews ───────────────────────────────────────────────────────────
        self::registerReviewHooks();

        // ── WooCommerce ───────────────────────────────────────────────────────
        if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE ) {
            self::registerWooCommerceHooks();
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * Hook devre disi mi kontrol et.
     * add_filter('sh_notify_hooks_disabled', fn($d) => [..., 'event-slug']) ile devre disi birakilabilir.
     */
    private static function isDisabled( string $event ): bool
    {
        $disabled = apply_filters( 'sh_notify_hooks_disabled', [] );
        return in_array( $event, (array) $disabled, true );
    }

    /**
     * Notifications::fire() cagrisini guvenli yap.
     * ENABLE_NOTIFICATIONS false ise sessizce gec.
     */
    private static function fire( string $event, array $data ): void
    {
        if ( ! defined( 'ENABLE_NOTIFICATIONS' ) || ! ENABLE_NOTIFICATIONS ) return;
        if ( self::isDisabled( $event ) ) return;
        if ( ! class_exists( 'Notifications' ) ) return;

        try {
            \Notifications::fire( $event, $data );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[NotifyHooks] fire() error for event "' . $event . '": ' . $e->getMessage() );
            }
        }
    }

    // ─── ACCOUNT HOOKS ───────────────────────────────────────────────────────

    private static function registerAccountHooks(): void
    {
        /**
         * Yeni kullanici kaydoldu.
         *
         * WooCommerce aktifse: woocommerce_created_customer
         * Degilse: user_register (WordPress native)
         *
         * NOT: SaltBase::user_register_hook() zaten eski notification sistemini cagiriyor.
         * Bu hook yeni Notifications::fire() sistemini kullanir.
         * Eski sistem ENABLE_NOTIFICATIONS false ise calisir, bu hook true ise calisir.
         * Cakismayi onlemek icin: add_filter('sh_notify_hooks_disabled', fn($d) => [..., 'new-account'])
         */
        if ( defined( 'ENABLE_ECOMMERCE' ) && ENABLE_ECOMMERCE ) {
            // WooCommerce uyelik sistemi
            add_action( 'woocommerce_created_customer', function ( int $customer_id ) {
                $user = get_userdata( $customer_id );
                if ( ! $user ) return;
                self::fire( 'new-account', [
                    'user'      => $user,
                    'recipient' => $customer_id,
                ] );
            }, 20 );
        } else {
            // WordPress native uyelik sistemi
            add_action( 'user_register', function ( int $user_id ) {
                // Activation aktifse new-account'u activation sonrasina birak
                if ( defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION ) return;
                $user = get_userdata( $user_id );
                if ( ! $user ) return;
                self::fire( 'new-account', [
                    'user'      => $user,
                    'recipient' => $user_id,
                ] );
            }, 20 );
        }

        /**
         * Hesap aktive edildi.
         * SaltBase activation flow'unda user_status meta'si 1 yapilinca tetiklenir.
         * Hook: sh_account_activated (custom action — membership-functions.php veya custom.php'de do_action ile tetiklenmeli)
         *
         * NOT: Mevcut kodda bu action yok, eklenmesi gerekiyor.
         * Simdilik user_meta update'i dinliyoruz.
         */
        add_action( 'sh_account_activated', function ( int $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) return;
            self::fire( 'account-activated', [
                'user'      => $user,
                'recipient' => $user_id,
            ] );
        } );

        /**
         * Hesap admin tarafindan onaylandi.
         * Hook: sh_account_approved (custom action)
         */
        add_action( 'sh_account_approved', function ( int $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) return;
            self::fire( 'account-approved', [
                'user'      => $user,
                'recipient' => $user_id,
            ] );
        } );

        /**
         * Hesap admin tarafindan reddedildi.
         * Hook: sh_account_rejected (custom action)
         */
        add_action( 'sh_account_rejected', function ( int $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) return;
            self::fire( 'account-rejected', [
                'user'      => $user,
                'recipient' => $user_id,
            ] );
        } );

        /**
         * Sifre sifirlama istegi.
         * WordPress native: retrieve_password action
         */
        add_action( 'retrieve_password', function ( string $user_login ) {
            $user = get_user_by( 'login', $user_login );
            if ( ! $user ) return;
            self::fire( 'password-reset', [
                'user'      => $user,
                'recipient' => $user->ID,
            ] );
        } );
    }

    // ─── WOOCOMMERCE HOOKS ───────────────────────────────────────────────────

    private static function registerWooCommerceHooks(): void
    {
        /**
         * Yeni siparis olusturuldu.
         * Hook: woocommerce_checkout_order_created (checkout sonrasi)
         */
        add_action( 'woocommerce_checkout_order_created', function ( \WC_Order $order ) {
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'order-placed', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order->get_id() ),
                'order'     => $order,
            ] );
        } );

        /**
         * Siparis tamamlandi.
         * Hook: woocommerce_order_status_completed
         */
        add_action( 'woocommerce_order_status_completed', function ( int $order_id ) {
            $order       = wc_get_order( $order_id );
            if ( ! $order ) return;
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'order-completed', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order_id ),
                'order'     => $order,
            ] );
        } );

        /**
         * Siparis iptal edildi.
         * Hook: woocommerce_order_status_cancelled
         */
        add_action( 'woocommerce_order_status_cancelled', function ( int $order_id ) {
            $order       = wc_get_order( $order_id );
            if ( ! $order ) return;
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'order-cancelled', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order_id ),
                'order'     => $order,
            ] );
        } );

        /**
         * Siparis iade edildi.
         * Hook: woocommerce_order_status_refunded
         */
        add_action( 'woocommerce_order_status_refunded', function ( int $order_id ) {
            $order       = wc_get_order( $order_id );
            if ( ! $order ) return;
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'order-refunded', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order_id ),
                'order'     => $order,
            ] );
        } );

        /**
         * Odeme tamamlandi.
         * Hook: woocommerce_payment_complete
         */
        add_action( 'woocommerce_payment_complete', function ( int $order_id ) {
            $order       = wc_get_order( $order_id );
            if ( ! $order ) return;
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'payment-completed', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order_id ),
                'order'     => $order,
            ] );
        } );

        /**
         * Siparis durumu degisti (genel).
         * Hook: woocommerce_order_status_changed
         * Sadece yukaridaki ozel hook'larda ele alinmayan durumlar icin.
         */
        add_action( 'woocommerce_order_status_changed', function ( int $order_id, string $from, string $to ) {
            $handled = [ 'completed', 'cancelled', 'refunded' ];
            if ( in_array( $to, $handled, true ) ) return; // Zaten yukarida ele alindi
            $order       = wc_get_order( $order_id );
            if ( ! $order ) return;
            $customer_id = $order->get_customer_id();
            if ( $customer_id < 1 ) return;
            self::fire( 'order-status-changed', [
                'user'      => get_userdata( $customer_id ),
                'recipient' => $customer_id,
                'post'      => get_post( $order_id ),
                'order'     => $order,
                'from'      => $from,
                'to'        => $to,
            ] );
        }, 10, 3 );
    }

    // ─── REVIEW EVENTS ───────────────────────────────────────────────────────

    /**
     * Reviews için PHP event registry tanımları.
     * NotifyEvent::define() ile kayıt yapılınca admin panelden kural tanımlanabilir
     * ve Notifications::fire() çağrısında "No rules found" hatası oluşmaz.
     *
     * Alert body'leri varsayılan (fallback) içerik sağlar — Notifications admin'den override edilebilir.
     */
    private static function defineReviewEvents(): void
    {
        if ( ! class_exists( \SaltHareket\Notifications\NotifyEvent::class ) ) return;

        $events = [

            // ── Profil/post'a yeni yorum yapıldı — yorum hedefine bildirim ──────
            'new-review' => [
                'label'            => 'New Review',
                'group'            => 'reviews',
                'channels'         => [ 'alert' ],
                'sender'           => '{{user}}',
                'recipient'        => '{{custom:sh_notify_review_recipient}}',
                'alert'            => [
                    'body' => '{{ data.user.display_name }} left you a review{% if data.post %} on "{{ data.post.post_title }}"{% endif %}.',
                    'url'  => '{% if data.post %}{{ data.post.guid }}{% endif %}',
                ],
                'throttle'         => [ 'limit' => 5, 'window' => 3600 ],
                'user_can_disable' => true,
            ],

            // ── Yorumun sahibine: yorumu onaylandı ──────────────────────────────
            'review-approved' => [
                'label'     => 'Review Approved',
                'group'     => 'reviews',
                'channels'  => [ 'alert' ],
                'sender'    => '{{admin}}',
                'recipient' => '{{custom:sh_notify_review_recipient}}',
                'alert'     => [
                    'body' => 'Your review{% if data.post %} on "{{ data.post.post_title }}"{% endif %} has been approved and is now visible.',
                    'url'  => '{% if data.post %}{{ data.post.guid }}{% endif %}',
                ],
            ],

            // ── Yorumun sahibine: yorumu reddedildi ─────────────────────────────
            'review-rejected' => [
                'label'     => 'Review Rejected',
                'group'     => 'reviews',
                'channels'  => [ 'alert' ],
                'sender'    => '{{admin}}',
                'recipient' => '{{custom:sh_notify_review_recipient}}',
                'alert'     => [
                    'body' => 'Your review{% if data.post %} on "{{ data.post.post_title }}"{% endif %} was not approved.',
                    'url'  => '{% if data.post %}{{ data.post.guid }}{% endif %}',
                ],
            ],

            // ── Yorum sahibine: yorumuna yanıt verildi ──────────────────────────
            'review-replied' => [
                'label'            => 'Review Replied',
                'group'            => 'reviews',
                'channels'         => [ 'alert' ],
                'sender'           => '{{user}}',
                'recipient'        => '{{custom:sh_notify_review_recipient}}',
                'alert'            => [
                    'body' => '{{ data.user.display_name }} replied to your review{% if data.post %} on "{{ data.post.post_title }}"{% endif %}.',
                    'url'  => '{% if data.post %}{{ data.post.guid }}{% endif %}',
                ],
                'user_can_disable' => true,
            ],

            // ── Yorum sahibine: yanıt onay bekliyor ─────────────────────────────
            'review-reply-pending' => [
                'label'     => 'Review Reply Pending Approval',
                'group'     => 'reviews',
                'channels'  => [ 'alert' ],
                'sender'    => '{{user}}',
                'recipient' => '{{custom:sh_notify_review_recipient}}',
                'alert'     => [
                    'body' => 'A reply to your review is waiting for your approval.',
                ],
            ],

            // ── Yoruma reaction geldi (review objesine like/dislike vb.) ─────────
            'review-reaction' => [
                'label'            => 'Review Got a Reaction',
                'group'            => 'reviews',
                'channels'         => [ 'alert' ],
                'sender'           => '{{user}}',
                'recipient'        => '{{custom:sh_notify_review_recipient}}',
                'alert'            => [
                    'body' => '{{ data.user.display_name }} reacted to your review{% if data.post %} on "{{ data.post.post_title }}"{% endif %}.',
                    'url'  => '{% if data.post %}{{ data.post.guid }}{% endif %}',
                ],
                'throttle'         => [ 'limit' => 3, 'window' => 3600 ],
                'user_can_disable' => true,
            ],

        ];

        foreach ( $events as $slug => $config ) {
            if ( ! self::isDisabled( $slug ) ) {
                \SaltHareket\Notifications\NotifyEvent::define( $slug, $config );
            }
        }

        // Custom recipient resolver — {{custom:sh_notify_review_recipient}}
        // $data['recipient'] doğrudan int olarak geçildiğinden bunu array'e çeviriyoruz.
        if ( ! function_exists( 'sh_notify_review_recipient' ) ) {
            function sh_notify_review_recipient( array $data ): array {
                $id = (int) ( $data['recipient'] ?? 0 );
                return $id > 0 ? [ $id ] : [];
            }
        }
    }

    /**
     * Reviews app ek hook'ları.
     * HandlesNotifications trait zaten new-review/approved/replied için Notifications::fire() çağırıyor.
     * Burada ekstra: admin'in yorumu reddetmesi (transition_comment_status).
     */
    private static function registerReviewHooks(): void
    {
        /**
         * Yorum reddedildi.
         * Reviews app'inde admin yorumu unapprove/spam yaptığında tetiklenir.
         * HandlesNotifications'da 'rejected' action yoktu — burada WP hook ile yakalıyoruz.
         */
        add_action( 'transition_comment_status', function ( string $new_status, string $old_status, \WP_Comment $comment ) {
            if ( ( $comment->comment_type ?? '' ) !== 'review' ) return;
            if ( ! in_array( $new_status, [ 'unapproved', 'spam' ], true ) ) return;
            if ( $old_status === $new_status ) return;

            $author_id = (int) $comment->user_id;
            if ( $author_id < 1 ) return;

            $author = get_userdata( $author_id );
            if ( ! $author ) return;

            self::fire( 'review-rejected', [
                'user'      => $author,
                'recipient' => $author_id,
                'post'      => $comment->comment_post_ID ? get_post( $comment->comment_post_ID ) : null,
                'comment'   => $comment,
            ] );
        }, 10, 3 );

        /**
         * review-reaction event'i — comment objesine reaction eklenince.
         * ReactionsSettings'te comment type'ı için notify_event tanımlıysa Reactions app kendi fire'ını yapıyor.
         * Burası sadece yoksa fallback olarak dinliyor.
         */
        add_action( 'sh_reaction_added', function ( string $type, int $object_id, string $object_type, int $user_id ) {
            if ( $object_type !== 'comment' ) return;

            // Reactions app kendi notify_event'ini zaten fire ediyor — sadece boş olanlar için devreye gir
            $type_config    = \SaltHareket\Reactions\ReactionsSettings::getTypeConfig( $type );
            $existing_event = $type_config['notify_event'] ?? '';
            if ( ! empty( $existing_event ) ) return; // Zaten Reactions app handle etti

            $comment = get_comment( $object_id );
            if ( ! $comment ) return;
            $owner_id = (int) $comment->user_id;
            if ( $owner_id < 1 || $owner_id === $user_id ) return;

            $actor = get_userdata( $user_id );
            if ( ! $actor ) return;

            self::fire( 'review-reaction', [
                'user'      => $actor,
                'recipient' => $owner_id,
                'post'      => $comment->comment_post_ID ? get_post( $comment->comment_post_ID ) : null,
                'reaction'  => $type,
                'comment'   => $comment,
            ] );
        }, 10, 4 );
    }
}
