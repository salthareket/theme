<?php

/**
 * SaltNotify Bootstrap
 * Tüm notification sınıflarını yükler, hook'ları register eder.
 * variables.php'de şu şekilde include edilir:
 *
 *   if (ENABLE_NOTIFICATIONS) include_once SH_INCLUDES_PATH . 'apps/notifications/bootstrap.php';
 *
 * @version 1.0.0
 */

use SaltHareket\Notifications\Admin\NotificationsAdmin;
use SaltHareket\Notifications\Admin\NotificationsAjax;
use SaltHareket\Notifications\Cron\NotifyWorker;
use SaltHareket\Notifications\NotifyDispatcher;

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

$notify_base = __DIR__ . '/';

require_once $notify_base . 'NotifyPriority.php';
require_once $notify_base . 'NotifyRegistry.php';
require_once $notify_base . 'NotifyEvent.php';
require_once $notify_base . 'NotifyPayload.php';
require_once $notify_base . 'NotifyResult.php';
require_once $notify_base . 'NotifyRenderer.php';
require_once $notify_base . 'NotifySchema.php';
require_once $notify_base . 'NotifyPreferences.php';
require_once $notify_base . 'NotifyDispatcher.php';
require_once $notify_base . 'Carriers/NotifyCarrier.php';
require_once $notify_base . 'Carriers/AlertCarrier.php';
require_once $notify_base . 'Carriers/EmailCarrier.php';
// SMS — multi-provider
require_once $notify_base . 'Carriers/SmsContract.php';
require_once $notify_base . 'Carriers/SmsEncoding.php';
require_once $notify_base . 'Carriers/SmsSettings.php';
require_once $notify_base . 'Carriers/Providers/D7Provider.php';
require_once $notify_base . 'Carriers/Providers/TwilioProvider.php';
require_once $notify_base . 'Carriers/Providers/VonageProvider.php';
require_once $notify_base . 'Carriers/Providers/InfobipProvider.php';
require_once $notify_base . 'Carriers/Providers/SinchProvider.php';
require_once $notify_base . 'Carriers/Providers/NetgsmProvider.php';
require_once $notify_base . 'Carriers/SmsManager.php';
require_once $notify_base . 'Carriers/SmsCarrier.php';
// Email — multi-provider
require_once $notify_base . 'Carriers/Email/EmailContract.php';
require_once $notify_base . 'Carriers/Email/EmailSettings.php';
require_once $notify_base . 'Carriers/Email/Providers/SmtpProvider.php';
require_once $notify_base . 'Carriers/Email/Providers/SendGridProvider.php';
require_once $notify_base . 'Carriers/Email/Providers/MailgunProvider.php';
require_once $notify_base . 'Carriers/Email/Providers/BrevoProvider.php';
require_once $notify_base . 'Carriers/Email/Providers/PostmarkProvider.php';
require_once $notify_base . 'Carriers/Email/EmailManager.php';
// ─── Web Push ─────────────────────────────────────────────────────────────────
require_once $notify_base . 'Carriers/WebPushCarrier.php'; // Her zaman yükle — Admin sayfası VAPID key göstermek için kullanır
require_once $notify_base . 'Admin/NotificationsAdmin.php';
require_once $notify_base . 'Admin/NotificationsAjax.php';
require_once $notify_base . 'Cron/NotifyWorker.php';
require_once $notify_base . 'Notifications.php';
require_once $notify_base . 'Hooks/NotifyHooks.php';

// ─── INIT ────────────────────────────────────────────────────────────────────

// Sms backward-compat shim — SmsManager yüklendikten sonra
if ( defined( 'ENABLE_MEMBERSHIP' ) && ENABLE_MEMBERSHIP ) {
    //require_once SH_CLASSES_PATH . 'class.otp.php';
}

// Dispatcher'ı başlat (carrier'ları register et)
NotifyDispatcher::init();

// Admin sayfası
NotificationsAdmin::register();

// DB tablolarını kur + default event'leri seed et
// admin_init'te çalışır — tablo garantili hazır olur
add_action( 'admin_init', function () {
    static $done = false;
    if ( $done ) return;
    $done = true;
    // 1. Tabloları kur (transient cache ile korumalı — her request'te çalışmaz)
    \SaltHareket\Notifications\NotifySchema::install();
}, 5 ); // priority 5 — diger admin_init hook'larindan once

// AJAX handler'lar
NotificationsAjax::register();

// Cron worker
NotifyWorker::register();

// Default event hook'larini kaydet
\SaltHareket\Notifications\Hooks\NotifyHooks::register();

// VAPID key yoksa otomatik üret — sadece ENABLE_WEB_PUSH aktifse
add_action( 'init', function () {
    if ( ! defined( 'ENABLE_WEB_PUSH' ) || ! ENABLE_WEB_PUSH ) return;
    if ( ! class_exists( '\\Minishlink\\WebPush\\VAPID' ) ) return;
    if ( get_option( 'sh_notify_vapid_keys' ) ) return;
    try {
        \SaltHareket\Notifications\Carriers\WebPushCarrier::generateVapidKeys();
    } catch ( \Throwable $e ) {
        // sessizce geç — VAPID olmadan sistem çalışmaya devam eder
    }
}, 99 );

// ─── WP NONCE — Frontend'e aktar ─────────────────────────────────────────────

add_action( 'wp_head', function () {
    if ( ! is_user_logged_in() ) return;

    $data = [
        'nonce'   => wp_create_nonce( 'sh_notify_nonce' ),
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'userId'  => get_current_user_id(),
    ];

    // Web Push verileri sadece ENABLE_WEB_PUSH aktifse eklenir
    if ( defined( 'ENABLE_WEB_PUSH' ) && ENABLE_WEB_PUSH ) {
        $data['pushNonce'] = wp_create_nonce( 'sh_notify_push' );
        $data['vapidKey']  = \SaltHareket\Notifications\Carriers\WebPushCarrier::getPublicKey();
    }

    echo '<script>window.shNotify=' . wp_json_encode( $data ) . ';</script>' . "\n";
}, 1 );
