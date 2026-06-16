<?php

namespace SaltHareket\DownloadLog\Concerns;

/**
 * HandlesLog
 *
 * wp_download_log tablosu — download kayıtları.
 * Lead data artık wp_guests tablosunda saklanır — her log row'unda tekrar tutulmaz.
 * Guest profili (email, name, phone, meta) GuestIdentity::updateProfile() ile güncellenir.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-13
 *     - Remove: lead_data, lead_email, lead_name kolonları — wp_guests'e taşındı
 *     - Remove: extractLeadIndex() — HandlesLeadCapture'a taşındı
 *     - Change: addLog() sadeleşti — lead data parametresi yok
 *     - Change: getLogs() wp_guests JOIN ile guest_email/name/phone/meta getiriyor
 *   1.1.0 - 2026-05-13 — lead_email, lead_name index kolonları
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Download kaydı ekle:
 * self::addLog([
 *     'file_id'     => 42,
 *     'file_name'   => 'brochure.pdf',
 *     'file_url'    => 'https://...',
 *     'user_id'     => 0,
 *     'guest_id'    => 'g_abc123',
 *     'source_post' => 15,
 *     'mode'        => 'lead_capture',
 *     'language'    => 'tr',
 * ]);
 *
 * // Lead data → wp_guests'e yaz (ayrıca):
 * GuestIdentity::updateProfile(['email' => 'a@b.com', 'name' => 'Ali']);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @package SaltHareket\DownloadLog\Concerns
 */
trait HandlesLog {

    // ─── TABLE ───────────────────────────────────────────

    private static function logTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'download_log';
    }

    /**
     * Tabloyu oluştur (yoksa).
     * admin_init hook'undan çağrılır.
     */
    public static function createTable(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::logTable();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            bigint(20)    NOT NULL AUTO_INCREMENT,
            file_id       bigint(20)    NOT NULL,
            file_name     varchar(255)  NOT NULL DEFAULT '',
            file_url      varchar(1000) NOT NULL DEFAULT '',
            user_id       bigint(20)    NULL,
            guest_id      varchar(64)   NULL,
            ip            varchar(45)   NOT NULL DEFAULT '',
            user_agent    varchar(500)  NOT NULL DEFAULT '',
            referer       varchar(1000) NOT NULL DEFAULT '',
            source_post   bigint(20)    NULL,
            mode          varchar(20)   NOT NULL DEFAULT 'public',
            language      varchar(10)   NOT NULL DEFAULT '',
            created_at    datetime      NOT NULL,
            PRIMARY KEY (id),
            KEY file_idx    (file_id),
            KEY user_idx    (user_id),
            KEY guest_idx   (guest_id(32)),
            KEY created_idx (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ─── WRITE ───────────────────────────────────────────

    /**
     * Download kaydı ekle.
     * Lead data bu tabloya yazılmaz — GuestIdentity::updateProfile() ile wp_guests'e yazılır.
     *
     * @param array $data {
     *   @type int    $file_id
     *   @type string $file_name
     *   @type string $file_url
     *   @type int    $user_id     (0 = guest)
     *   @type string $guest_id
     *   @type string $ip
     *   @type string $user_agent
     *   @type string $referer
     *   @type int    $source_post
     *   @type string $mode        public|login_required|lead_capture
     *   @type string $language
     * }
     * @return int  insert ID
     */
    public static function addLog( array $data ): int {
        global $wpdb;

        $log_ip = (bool) get_option( 'sh_download_log_ip', true );

        $row = [
            'file_id'     => (int) ( $data['file_id'] ?? 0 ),
            'file_name'   => sanitize_text_field( $data['file_name'] ?? '' ),
            'file_url'    => esc_url_raw( $data['file_url'] ?? '' ),
            'user_id'     => $data['user_id'] ? (int) $data['user_id'] : null,
            'guest_id'    => sanitize_text_field( $data['guest_id'] ?? '' ) ?: null,
            'ip'          => $log_ip ? sanitize_text_field( $data['ip'] ?? self::getClientIp() ) : '',
            'user_agent'  => substr( sanitize_text_field( $data['user_agent'] ?? ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 500 ),
            'referer'     => esc_url_raw( $data['referer'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ),
            'source_post' => $data['source_post'] ? (int) $data['source_post'] : null,
            'mode'        => sanitize_key( $data['mode'] ?? 'public' ),
            'language'    => sanitize_key( $data['language'] ?? \SaltHareket\DownloadLog\GuestIdentity::getCurrentLanguage() ),
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ];

        $wpdb->insert( self::logTable(), $row );

        $log_id = (int) $wpdb->insert_id;

        if ( ! $log_id ) {
            error_log( '[DL] INSERT FAILED: ' . $wpdb->last_error );
        }

        do_action( 'sh_download_logged', $log_id, $row );

        return $log_id;
    }

    // ─── READ ────────────────────────────────────────────

    /**
     * Download sayısını döndür.
     */
    public static function getCount( int $file_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::logTable() . " WHERE file_id = %d",
            $file_id
        ) );
    }

    /**
     * Log kayıtlarını döndür — admin tablosu için.
     * guest_id üzerinden wp_guests JOIN ile email/name de gelir.
     *
     * @param array $args {
     *   @type int    $per_page
     *   @type int    $page
     *   @type int    $file_id
     *   @type int    $user_id
     *   @type string $mode
     *   @type string $date_from  Y-m-d
     *   @type string $date_to    Y-m-d
     *   @type string $search     dosya adı, guest_id veya email
     * }
     * @return array { items: [], total: int }
     */
    public static function getLogs( array $args = [] ): array {
        global $wpdb;
        $table  = self::logTable();
        $gtable = $wpdb->prefix . 'guests';

        $per_page = max( 1, (int) ( $args['per_page'] ?? 50 ) );
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['file_id'] ) ) {
            $where   .= ' AND l.file_id = %d';
            $params[] = (int) $args['file_id'];
        }
        if ( ! empty( $args['user_id'] ) ) {
            $where   .= ' AND l.user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if ( ! empty( $args['mode'] ) ) {
            $where   .= ' AND l.mode = %s';
            $params[] = sanitize_key( $args['mode'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND DATE(l.created_at) >= %s';
            $params[] = sanitize_text_field( $args['date_from'] );
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND DATE(l.created_at) <= %s';
            $params[] = sanitize_text_field( $args['date_to'] );
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where   .= ' AND (l.file_name LIKE %s OR l.guest_id LIKE %s OR g.email LIKE %s OR g.name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // guests tablosu var mı kontrol et (ilk kurulumda olmayabilir)
        $guests_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$gtable}'" );
        $join = $guests_exists ? "LEFT JOIN {$gtable} g ON g.guest_id = l.guest_id" : '';
        $select = $guests_exists
            ? "l.*, g.email as guest_email, g.name as guest_name, g.phone as guest_phone, g.meta as guest_meta"
            : "l.*";

        $total_sql = "SELECT COUNT(*) FROM {$table} l {$join} WHERE {$where}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, ...$params ) ) // phpcs:ignore
            : (int) $wpdb->get_var( $total_sql );

        $data_sql = "SELECT {$select} FROM {$table} l {$join} WHERE {$where} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $items = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$params ) ); // phpcs:ignore

        return [ 'items' => $items ?: [], 'total' => $total ];
    }

    /**
     * Belirli bir kullanıcı/guest'in download geçmişi.
     *
     * @param string $type    'user' veya 'guest'
     * @param string $id      user_id veya guest_id
     * @return array
     */
    public static function getUserHistory( string $type, string $id ): array {
        global $wpdb;
        $table = self::logTable();

        if ( $type === 'user' || $type === 'logged' ) {
            $uid = (int) $id;
            return $wpdb->get_results(
                "SELECT id, file_id, file_name, file_url, mode, source_post, language, created_at
                 FROM {$table}
                 WHERE user_id = {$uid}
                 ORDER BY created_at DESC
                 LIMIT 500"
            ) ?: [];
        }

        $gid = esc_sql( $id );
        return $wpdb->get_results(
            "SELECT id, file_id, file_name, file_url, mode, source_post, language, created_at
             FROM {$table}
             WHERE guest_id = '{$gid}'
             ORDER BY created_at DESC
             LIMIT 500"
        ) ?: [];
    }

    /**
     * Analytics — en çok indirilen dosyalar.
     *
     * @return array  [file_id, file_name, count, last_at]
     */
    public static function getTopFiles( int $limit = 20, int $days = 0 ): array {
        global $wpdb;
        $table = self::logTable();

        if ( $days > 0 ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT file_id, file_name, COUNT(*) as cnt, MAX(created_at) as last_at
                 FROM {$table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY file_id, file_name
                 ORDER BY cnt DESC
                 LIMIT %d",
                $days, $limit
            ) ) ?: [];
        }

        // All time
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT file_id, file_name, COUNT(*) as cnt, MAX(created_at) as last_at
             FROM {$table}
             GROUP BY file_id, file_name
             ORDER BY cnt DESC
             LIMIT %d",
            $limit
        ) ) ?: [];
    }

    /**
     * Analytics — en çok indirenler.
     * Logged kullanıcılar user_id ile, guest'ler guest_id ile gruplandırılır.
     *
     * @return array  [identifier, display_name, email, type, count, last_at]
     */
    public static function getTopDownloaders( int $limit = 20, int $days = 0 ): array {
        global $wpdb;
        $table  = self::logTable();
        $gtable = $wpdb->prefix . 'guests';
        $utable = $wpdb->users;

        $guests_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$gtable}'" );
        $date_clause   = $days > 0 ? "AND l.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';

        if ( $guests_exists ) {
            $logged = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    l.user_id as identifier,
                    u.display_name,
                    u.user_email as email,
                    'logged' as type,
                    COUNT(*) as cnt,
                    MAX(l.created_at) as last_at
                 FROM {$table} l
                 INNER JOIN {$utable} u ON u.ID = l.user_id
                 WHERE l.user_id IS NOT NULL {$date_clause}
                 GROUP BY l.user_id
                 ORDER BY cnt DESC
                 LIMIT %d",
                $limit
            ) ) ?: [];

            $guests = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    l.guest_id as identifier,
                    COALESCE(g.name, l.guest_id) as display_name,
                    COALESCE(g.email, '') as email,
                    'guest' as type,
                    COUNT(*) as cnt,
                    MAX(l.created_at) as last_at
                 FROM {$table} l
                 LEFT JOIN {$gtable} g ON g.guest_id = l.guest_id
                 WHERE l.user_id IS NULL AND l.guest_id IS NOT NULL {$date_clause}
                 GROUP BY l.guest_id
                 ORDER BY cnt DESC
                 LIMIT %d",
                $limit
            ) ) ?: [];
        } else {
            $logged = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    l.user_id as identifier,
                    u.display_name,
                    u.user_email as email,
                    'logged' as type,
                    COUNT(*) as cnt,
                    MAX(l.created_at) as last_at
                 FROM {$table} l
                 INNER JOIN {$utable} u ON u.ID = l.user_id
                 WHERE l.user_id IS NOT NULL {$date_clause}
                 GROUP BY l.user_id
                 ORDER BY cnt DESC
                 LIMIT %d",
                $limit
            ) ) ?: [];
            $guests = [];
        }

        $all = array_merge( $logged, $guests );
        usort( $all, fn( $a, $b ) => (int) $b->cnt - (int) $a->cnt );
        return array_slice( $all, 0, $limit );
    }

    /**
     * Analytics — genel istatistikler.
     */
    public static function getStats(): array {
        global $wpdb;
        $table  = self::logTable();
        $gtable = $wpdb->prefix . 'guests';

        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $today   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()" );
        $week    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );
        $month   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
        $leads   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE mode = 'lead_capture'" );
        $guests  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE user_id IS NULL" );
        $logged  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE user_id IS NOT NULL" );

        // Conversion: guest → merge edilmiş (signup yapmış)
        $converted = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$gtable}'" ) ) {
            $converted = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT l.guest_id)
                 FROM {$table} l
                 INNER JOIN {$gtable} g ON g.guest_id = l.guest_id
                 WHERE g.merged_to IS NOT NULL AND l.guest_id IS NOT NULL"
            );
        }

        return compact( 'total', 'today', 'week', 'month', 'leads', 'guests', 'logged', 'converted' );
    }

    // ─── HELPERS ─────────────────────────────────────────

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
}
