<?php

namespace SaltHareket\Notifications;

use SaltHareket\Notifications\Carriers\WebPushCarrier;

/**
 * NotifySchema
 * DB tablolarını oluşturur ve günceller.
 * Transient cache ile her request'te SHOW TABLES çalışmaz.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Tüm tabloları kur (variables.php'de bir kez çağrılır)
 * NotifySchema::install();
 *
 * // Tabloları zorla yeniden oluştur (güncelleme sonrası)
 * NotifySchema::install(force: true);
 *
 * // Sadece notifications tablosunu kontrol et
 * NotifySchema::ensureNotificationsTable();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   NotifySchema::install(); // transient cache ile korumalı
 *
 * @example
 *   NotifySchema::install(force: true); // cache'i bypass et
 *
 * @example
 *   // wp_notifications tablosu:
 *   // id, created_at, sender_id, receiver_id, event, channel,
 *   // message, status, priority, data (JSON), idempotency_key
 *
 * @example
 *   // wp_notify_log tablosu:
 *   // id, notification_id, channel, status, error, sent_at
 *
 * @example
 *   // wp_notify_push_subscriptions tablosu:
 *   // id, user_id, endpoint, p256dh, auth, content_encoding, created_at
 */
class NotifySchema
{
    private const CACHE_KEY = 'sh_notify_schema_v1';
    private const VERSION   = '1.1'; // 1.1: sent_at migration

    public static function install( bool $force = false ): void
    {
        if ( ! $force && get_transient( self::CACHE_KEY ) === self::VERSION ) return;

        self::createNotificationsTable();
        self::createLogTable();
        self::createPreferencesTable();
        self::createRulesTable();
        self::createEventsTable();
        WebPushCarrier::createTable();

        set_transient( self::CACHE_KEY, self::VERSION, 7 * DAY_IN_SECONDS );
        update_option( 'sh_notify_db_version', self::VERSION, false );
    }

    public static function ensureNotificationsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'notifications';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) return;
        self::createNotificationsTable();
    }

    // ─── TABLO TANIMLARI ─────────────────────────────────────────────────────

    private static function createNotificationsTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . 'notifications';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id               bigint(20)   NOT NULL AUTO_INCREMENT,
            created_at       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sender_id        bigint(20)   NOT NULL DEFAULT 0,
            receiver_id      bigint(20)   NOT NULL,
            event            varchar(100) NOT NULL DEFAULT '',
            channel          varchar(20)  NOT NULL DEFAULT 'alert',
            message          longtext     NOT NULL,
            status           varchar(20)  NOT NULL DEFAULT 'unread',
            priority         varchar(20)  NOT NULL DEFAULT 'normal',
            data             json         DEFAULT NULL,
            idempotency_key  varchar(64)  NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY receiver_status (receiver_id, status),
            KEY receiver_created (receiver_id, created_at),
            KEY event_key (event),
            UNIQUE KEY idempotency (idempotency_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'sh_notifications_version', self::VERSION, false );
    }

    private static function createLogTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . 'notify_log';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id              bigint(20)   NOT NULL AUTO_INCREMENT,
            notification_id bigint(20)   NOT NULL DEFAULT 0,
            event           varchar(100) NOT NULL DEFAULT '',
            channel         varchar(20)  NOT NULL DEFAULT '',
            receiver_id     bigint(20)   NOT NULL DEFAULT 0,
            status          varchar(20)  NOT NULL DEFAULT 'sent',
            error           text         DEFAULT NULL,
            sent_at         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_channel (event, channel),
            KEY receiver_id (receiver_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Migration: sent_at kolonu eski kurulumda eksik olabilir — ALTER TABLE ile ekle
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
        if ( ! in_array( 'sent_at', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER error" );
            $wpdb->query( "ALTER TABLE {$table} ADD KEY sent_at (sent_at)" );
        }
        // Migration: receiver_id kolonu eksikse ekle
        if ( ! in_array( 'receiver_id', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN receiver_id bigint(20) NOT NULL DEFAULT 0 AFTER channel" );
            $wpdb->query( "ALTER TABLE {$table} ADD KEY receiver_id (receiver_id)" );
        }
    }

    private static function createPreferencesTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . 'notify_preferences';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20)   NOT NULL AUTO_INCREMENT,
            user_id     bigint(20)   NOT NULL,
            event       varchar(100) NOT NULL DEFAULT '',
            channel     varchar(20)  NOT NULL DEFAULT '',
            enabled     tinyint(1)   NOT NULL DEFAULT 1,
            updated_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_event_channel (user_id, event, channel)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Admin'den oluşturulan notification kuralları.
     * Her kural: hangi event'te, hangi role için, hangi carrier'larla, ne gönderilecek.
     */
    private static function createRulesTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . 'notify_rules';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20)   NOT NULL AUTO_INCREMENT,
            role        varchar(100) NOT NULL DEFAULT '',
            event       varchar(100) NOT NULL DEFAULT '',
            type        varchar(50)  NOT NULL DEFAULT 'info',
            sender      varchar(100) NOT NULL DEFAULT '{{admin}}',
            recipient   varchar(100) NOT NULL DEFAULT '{{user}}',
            carriers    longtext     NOT NULL,
            active      tinyint(1)   NOT NULL DEFAULT 1,
            created_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_role (event, role),
            KEY active (active)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Admin'den tanımlanan event'ler (title + description + slug).
     */
    private static function createEventsTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = $wpdb->prefix . 'notify_events';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20)   NOT NULL AUTO_INCREMENT,
            slug        varchar(100) NOT NULL DEFAULT '',
            title       varchar(255) NOT NULL DEFAULT '',
            description text         DEFAULT NULL,
            created_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
