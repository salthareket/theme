<?php

namespace SaltHareket\Membership;

/**
 * GuestIdentity
 *
 * Anonymous kullanıcı kimliği ve profil yönetimi.
 *
 * Cookie (sh_guest_id) sadece anlamlı bir event'te set edilir — her ziyaretçiye değil.
 * Event tetiklenince:
 *   1. Cookie set edilir (yoksa)
 *   2. wp_guests tablosuna row eklenir (yoksa)
 *   3. Lead data gelince profil güncellenir (email, name, phone, meta)
 *
 * Login/signup olunca:
 *   - wp_guests.merged_to = user_id set edilir
 *   - wp_download_log / wp_reactions → user_id güncellenir
 *   - Cookie silinir
 *
 * Tüm app'ler (Download Log, Reactions, Search History, Reviews...) bu sınıfı kullanır.
 * Her app kendi event'inde ensureGuest() çağırır.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-13
 *     - Add: wp_guests tablosu — ortak guest profil deposu
 *     - Add: ensureGuest() — cookie + DB row (lazy, sadece event'te)
 *     - Add: updateProfile() — email/name/phone/meta güncelle
 *     - Add: getProfile() — wp_guests row'unu döndür
 *     - Add: mergeToUser() — signup sonrası guest → user merge
 *     - Add: createTable() — wp_guests tablo kurulumu + migration
 *     - Change: init() kaldırıldı — cookie artık her sayfada set edilmiyor
 *     - Change: onLogin() → mergeToUser() ile birleştirildi
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Anlamlı event'te guest oluştur (cookie + DB row):
 * $guest_id = GuestIdentity::ensureGuest();
 *
 * // Lead data gelince profili güncelle:
 * GuestIdentity::updateProfile(['email' => 'a@b.com', 'name' => 'Ali']);
 *
 * // Mevcut guest profilini al:
 * $profile = GuestIdentity::getProfile();
 *
 * // Login/signup sonrası merge:
 * GuestIdentity::mergeToUser($user_id);
 *
 * // Sadece mevcut guest ID'yi oku (row oluşturmaz):
 * $guest_id = GuestIdentity::getId();
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Download event'inde:
 *   $guest_id = GuestIdentity::ensureGuest();
 *   // → "g_a1b2c3d4e5f6789abc" (cookie set edildi, DB row oluştu)
 *
 * @example
 *   // Lead form submit sonrası profil güncelle:
 *   GuestIdentity::updateProfile([
 *       'email' => 'ali@example.com',
 *       'name'  => 'Ali Veli',
 *       'phone' => '05001234567',
 *       'meta'  => ['company' => 'ACME', 'utm_source' => 'google'],
 *   ]);
 *
 * @example
 *   // Profili oku:
 *   $profile = GuestIdentity::getProfile();
 *   // → ['id' => 5, 'guest_id' => 'g_...', 'email' => 'ali@example.com', ...]
 *
 * @example
 *   // Signup sonrası merge:
 *   add_action('user_register', function($user_id) {
 *       GuestIdentity::mergeToUser($user_id);
 *   });
 *
 * @example
 *   // Reactions app'inde:
 *   if (!is_user_logged_in()) {
 *       $guest_id = GuestIdentity::ensureGuest();
 *   }
 *
 * @package SaltHareket\Membership
 */
class GuestIdentity {

    const COOKIE_KEY = 'sh_guest_id';
    const ID_PREFIX  = 'g_';

    /** @var string|null Runtime cache */
    private static ?string $current_id = null;

    // ─── ENSURE GUEST ────────────────────────────────────

    /**
     * Anlamlı event'te çağrılır.
     * Cookie yoksa üret + set et.
     * wp_guests'te row yoksa oluştur.
     * Mevcut guest_id'yi döndür.
     *
     * Login'li kullanıcılar için çağrılmamalı — caller kontrol etmeli.
     */
    public static function ensureGuest(): string {
        $guest_id = self::getId();

        if ( ! $guest_id ) {
            $guest_id = self::generate();
            self::setCookie( $guest_id );
            self::$current_id = $guest_id;
        }

        // DB row oluştur (yoksa)
        self::ensureRow( $guest_id );

        return $guest_id;
    }

    // ─── GET ─────────────────────────────────────────────

    /**
     * Mevcut guest ID'yi döndür.
     * Cookie'den okur — DB'ye dokunmaz, row oluşturmaz.
     * Yoksa boş string döner.
     */
    public static function getId(): string {
        if ( self::$current_id ) return self::$current_id;

        $from_cookie = self::getFromCookie();
        if ( $from_cookie && self::isValid( $from_cookie ) ) {
            self::$current_id = $from_cookie;
            return $from_cookie;
        }

        return '';
    }

    /**
     * Cookie'den guest ID'yi oku.
     */
    private static function getFromCookie(): string {
        return isset( $_COOKIE[ self::COOKIE_KEY ] )
            ? sanitize_text_field( $_COOKIE[ self::COOKIE_KEY ] )
            : '';
    }

    /**
     * Guest ID formatı geçerli mi?
     * "g_" prefix + 16+ hex karakter
     */
    private static function isValid( string $id ): bool {
        return (bool) preg_match( '/^g_[a-f0-9]{16,}$/', $id );
    }

    // ─── GENERATE ────────────────────────────────────────

    /**
     * Yeni benzersiz guest ID üret.
     * Format: g_{random_hex_32}
     */
    public static function generate(): string {
        if ( function_exists( 'random_bytes' ) ) {
            return self::ID_PREFIX . bin2hex( random_bytes( 16 ) );
        }
        return self::ID_PREFIX . md5( uniqid( '', true ) . wp_rand() );
    }

    // ─── COOKIE ──────────────────────────────────────────

    /**
     * Cookie'yi set et.
     */
    private static function setCookie( string $id ): void {
        if ( headers_sent() ) return;

        $days    = (int) apply_filters( 'sh_guest_id_cookie_days', 365 );
        $expires = time() + $days * DAY_IN_SECONDS;

        setcookie( self::COOKIE_KEY, $id, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
        $_COOKIE[ self::COOKIE_KEY ] = $id;
    }

    /**
     * Cookie'yi sil.
     */
    public static function clearCookie(): void {
        if ( ! headers_sent() ) {
            setcookie( self::COOKIE_KEY, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        }
        unset( $_COOKIE[ self::COOKIE_KEY ] );
        self::$current_id = null;
    }

    // ─── DB — TABLE ──────────────────────────────────────

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'guests';
    }

    /**
     * wp_guests tablosunu oluştur (yoksa).
     * admin_init hook'undan çağrılır.
     */
    public static function createTable(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::table();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20)    NOT NULL AUTO_INCREMENT,
            guest_id    varchar(64)   NOT NULL,
            email       varchar(200)  NULL,
            name        varchar(200)  NULL,
            phone       varchar(50)   NULL,
            meta        longtext      NULL,
            ip          varchar(45)   NULL,
            language    varchar(10)   NULL,
            merged_to   bigint(20)    NULL,
            created_at  datetime      NOT NULL,
            updated_at  datetime      NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY guest_id_idx (guest_id),
            KEY email_idx     (email(100)),
            KEY merged_to_idx (merged_to),
            KEY created_idx   (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ─── DB — ROW ────────────────────────────────────────

    /**
     * wp_guests'te row yoksa oluştur.
     * Varsa dokunma.
     */
    private static function ensureRow( string $guest_id ): void {
        global $wpdb;
        $table = self::table();

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE guest_id = %s LIMIT 1",
            $guest_id
        ) );

        if ( $exists ) return;

        $now = gmdate( 'Y-m-d H:i:s' );

        $wpdb->insert( $table, [
            'guest_id'   => $guest_id,
            'ip'         => self::getClientIp(),
            'language'   => self::getCurrentLanguage(),
            'created_at' => $now,
            'updated_at' => $now,
        ] );
    }

    // ─── PROFILE ─────────────────────────────────────────

    /**
     * Mevcut guest'in profilini döndür.
     * Cookie'den guest_id alır, DB'den row çeker.
     * Guest yoksa null döner.
     *
     * @return array|null  wp_guests row (assoc array) veya null
     */
    public static function getProfile(): ?array {
        $guest_id = self::getId();
        if ( ! $guest_id ) return null;

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE guest_id = %s LIMIT 1",
            $guest_id
        ), ARRAY_A );

        return $row ?: null;
    }

    /**
     * Guest profilini güncelle.
     * Sadece dolu gelen alanları günceller — boş gelenler mevcut değeri korur.
     * meta alanı: mevcut JSON ile merge edilir.
     *
     * @param array $data {
     *   @type string $email
     *   @type string $name
     *   @type string $phone
     *   @type array  $meta   Ekstra key/value (UTM, form fields vs.)
     * }
     */
    public static function updateProfile( array $data ): void {
        $guest_id = self::getId();
        if ( ! $guest_id ) return;

        global $wpdb;
        $table = self::table();

        // Mevcut row'u al
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE guest_id = %s LIMIT 1",
            $guest_id
        ), ARRAY_A );

        if ( ! $existing ) return;

        $update = [ 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ];

        if ( ! empty( $data['email'] ) ) {
            $clean = sanitize_email( trim( $data['email'] ) );
            if ( is_email( $clean ) ) {
                $update['email'] = $clean;
            }
        }

        if ( ! empty( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( trim( $data['name'] ) );
        }

        if ( ! empty( $data['phone'] ) ) {
            $update['phone'] = sanitize_text_field( trim( $data['phone'] ) );
        }

        // meta: mevcut JSON ile merge
        if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
            $existing_meta = [];
            if ( ! empty( $existing['meta'] ) ) {
                $existing_meta = json_decode( $existing['meta'], true ) ?: [];
            }
            $merged = array_merge( $existing_meta, $data['meta'] );
            $update['meta'] = wp_json_encode( $merged, JSON_UNESCAPED_UNICODE );
        }

        if ( count( $update ) <= 1 ) return; // sadece updated_at varsa güncelleme

        $wpdb->update( $table, $update, [ 'guest_id' => $guest_id ] );
    }

    /**
     * Email ile guest profilini bul.
     * Fallback lookup — cookie yoksa veya farklı cihazdan gelince.
     *
     * @return array|null  wp_guests row veya null
     */
    public static function getByEmail( string $email ): ?array {
        if ( ! is_email( $email ) ) return null;

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE email = %s AND merged_to IS NULL ORDER BY created_at DESC LIMIT 1",
            sanitize_email( $email )
        ), ARRAY_A );

        return $row ?: null;
    }

    // ─── MERGE ───────────────────────────────────────────

    /**
     * Guest → User merge.
     * Login veya signup sonrası çağrılır.
     *
     * 1. wp_guests.merged_to = user_id
     * 2. wp_download_log: guest_id eşleşen, user_id null olan kayıtları güncelle
     * 3. wp_reactions: user_id=0 olan kayıtları güncelle
     * 4. Cookie sil
     * 5. sh_guest_merged action fire et
     *
     * @param int $user_id  Hedef WP user ID
     */
    public static function mergeToUser( int $user_id ): void {
        if ( $user_id < 1 ) return;

        $guest_id = self::getId();
        if ( ! $guest_id ) return;

        global $wpdb;

        // 1. wp_guests: merged_to set et
        $wpdb->update(
            self::table(),
            [
                'merged_to'  => $user_id,
                'updated_at' => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ 'guest_id' => $guest_id ]
        );

        // 2. wp_download_log: guest kayıtları user_id ile güncelle
        $dl_table = $wpdb->prefix . 'download_log';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$dl_table}'" ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$dl_table} SET user_id = %d WHERE guest_id = %s AND user_id IS NULL",
                $user_id,
                $guest_id
            ) );
        }

        // 3. wp_reactions: guest kayıtları user_id ile güncelle
        $rx_table = $wpdb->prefix . 'reactions';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rx_table}'" ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$rx_table} SET user_id = %d WHERE guest_id = %s AND user_id = 0",
                $user_id,
                $guest_id
            ) );
        }

        // 4. Cookie sil
        self::clearCookie();

        // 5. Action — dışarıdan hook'lanabilir (CRM, email automation vs.)
        do_action( 'sh_guest_merged', $user_id, $guest_id );
    }

    // ─── CLEANUP ─────────────────────────────────────────

    /**
     * Inactive guest'leri temizle.
     *
     * Silinecek guest kriterleri (OR):
     *   1. merged_to IS NOT NULL (user'a merge edilmiş) — her zaman silinebilir
     *   2. updated_at < $days gün önce AND email IS NULL (hiç lead data gelmemiş)
     *
     * @param int $days  Kaç gün işlem yapmayan guest silinsin (default: option'dan)
     * @return int  Silinen guest sayısı
     */
    public static function cleanup( int $days = 0 ): int {
        global $wpdb;
        $table = self::table();

        if ( $days < 1 ) {
            $days = (int) get_option( 'sh_guest_cleanup_days', 90 );
        }
        if ( $days < 1 ) $days = 90;

        // Merged olanlar — her zaman sil
        $merged_ids = $wpdb->get_col(
            "SELECT guest_id FROM {$table} WHERE merged_to IS NOT NULL"
        );

        // Inactive + no lead data
        $inactive_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT guest_id FROM {$table}
             WHERE merged_to IS NULL
               AND email IS NULL
               AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        $all_ids = array_unique( array_merge( $merged_ids ?: [], $inactive_ids ?: [] ) );

        if ( empty( $all_ids ) ) return 0;

        $count = 0;
        // 200'er batch — büyük sitelerde timeout önlemi
        foreach ( array_chunk( $all_ids, 200 ) as $batch ) {
            $placeholders = implode( ',', array_fill( 0, count( $batch ), '%s' ) );
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE guest_id IN ({$placeholders})",
                    ...$batch
                )
            );
            $count += (int) $deleted;
        }

        do_action( 'sh_guests_cleaned', $count, $days );

        return $count;
    }

    /**
     * Guest istatistiklerini döndür.
     *
     * @return array { total, merged, inactive, with_email, with_lead }
     */
    public static function getStats(): array {
        global $wpdb;
        $table = self::table();

        $days = (int) get_option( 'sh_guest_cleanup_days', 90 );
        if ( $days < 1 ) $days = 90;

        $total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $merged     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE merged_to IS NOT NULL" );
        $with_email = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE email IS NOT NULL" );
        $inactive   = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE merged_to IS NULL AND email IS NULL
               AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        $purgeable  = $merged + $inactive;

        return compact( 'total', 'merged', 'with_email', 'inactive', 'purgeable', 'days' );
    }

    /**
     * Client IP adresini döndür.
     */
    private static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Aktif dili döndür.
     * ml_get_current_language() her durumu handle eder — plugin kontrolü gerekmez.
     */
    public static function getCurrentLanguage(): string {
        return function_exists( 'ml_get_current_language' ) ? (string) ml_get_current_language() : '';
    }
}
