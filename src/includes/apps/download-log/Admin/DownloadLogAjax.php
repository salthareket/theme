<?php

namespace SaltHareket\DownloadLog\Admin;

use SaltHareket\DownloadLog\DownloadManager;
use SaltHareket\DownloadLog\DownloadRules;
use SaltHareket\Membership\GuestIdentity;
use SaltHareket\DownloadLog\Concerns\HandlesProtection;
use SaltHareket\DownloadLog\Concerns\HandlesToken;
use SaltHareket\DownloadLog\Concerns\HandlesLog;
use SaltHareket\DownloadLog\Concerns\HandlesLeadCapture;

/**
 * DownloadLogAjax
 *
 * AJAX handler'ları — download request, lead capture, post/term autocomplete, rule CRUD.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * @package SaltHareket\DownloadLog\Admin
 */
class DownloadLogAjax {

    use HandlesProtection;
    use HandlesToken;
    use HandlesLog;
    use HandlesLeadCapture;

    // ─── DOWNLOAD REQUEST ────────────────────────────────

    /**
     * Frontend download butonu tıklandığında çağrılır.
     * TurboAPI: site.com/api/download_request
     * Erişim kontrolü yapar, token üretir, download URL döndürür.
     *
     * Guest kullanıcı + login gerektirmeyen mod → ensureGuest() çağrılır.
     * Lead data bu noktada yoktur — download_lead handler'ı günceller.
     *
     * @param array $vars  TurboAPI vars (file_id, source_post)
     */
    public static function handleDownloadRequest( array $vars = [] ): array {
        $file_id     = (int) ( $vars['file_id']     ?? $_POST['file_id']     ?? 0 );
        $source_post = (int) ( $vars['source_post'] ?? $_POST['source_post'] ?? 0 );

        if ( $file_id < 1 ) {
            return [ 'error' => true, 'message' => 'invalid_file_id' ];
        }

        // Erişim kontrolü
        $access = self::checkAccess( $file_id, $source_post );

        switch ( $access['status'] ) {

            case 'allowed':
            case 'lead_already_given':
                // Guest kullanıcı → wp_guests row oluştur (yoksa)
                $guest_id = '';
                if ( ! is_user_logged_in() ) {
                    $guest_id = GuestIdentity::ensureGuest();
                }

                $token = self::generateToken(
                    $file_id,
                    $source_post,
                    $access['mode'],
                    get_current_user_id(),
                    $guest_id ?: GuestIdentity::getId()
                );

                // Log yaz — lead_capture modunda login kullanıcı için de yaz
                // (lead_capture + allowed = login'li kullanıcı direkt geçti)
                $file = self::getFileInfo( $file_id );
                self::addLog( [
                    'file_id'     => $file_id,
                    'file_name'   => $file ? $file['name'] : '',
                    'file_url'    => $file ? $file['url']  : '',
                    'user_id'     => get_current_user_id(),
                    'guest_id'    => $guest_id ?: GuestIdentity::getId(),
                    'source_post' => $source_post,
                    'mode'        => $access['mode'],
                    'language'    => GuestIdentity::getCurrentLanguage(),
                ] );

                return [
                    'error'        => false,
                    'status'       => 'allowed',
                    'download_url' => self::buildDownloadUrl( $token ),
                ];

            case 'login_required':
                return [
                    'error'     => false,
                    'status'    => 'login_required',
                    'login_url' => $access['login_url'],
                ];

            case 'lead_required':
                $form_html = self::renderCF7Form( $access['form_id'] );
                return [
                    'error'     => false,
                    'status'    => 'lead_required',
                    'form_id'   => $access['form_id'],
                    'form_html' => $form_html,
                    'title'     => function_exists( 'trans' ) ? trans( 'Download' ) : 'Download',
                ];

            default:
                return [ 'error' => true, 'message' => 'access_denied' ];
        }
    }

    // ─── LEAD CAPTURE ────────────────────────────────────

    /**
     * CF7 form submit sonrası lead data'yı al, cookie yaz, token üret.
     * TurboAPI: site.com/api/download_lead
     *
     * Guest kullanıcı → ensureGuest() çağrılır (cookie + wp_guests row).
     * Lead data (email, name, phone, meta) → GuestIdentity::updateProfile() ile wp_guests'e yazılır.
     * wp_download_log'a sadece file/user/mode bilgisi yazılır — lead data yok.
     *
     * @param array $vars  TurboAPI vars (file_id, source_post, form_id, lead_data)
     */
    public static function handleLeadCapture( array $vars = [] ): array {
        $file_id     = (int) ( $vars['file_id']     ?? $_POST['file_id']     ?? 0 );
        $source_post = (int) ( $vars['source_post'] ?? $_POST['source_post'] ?? 0 );
        $form_id     = (int) ( $vars['form_id']     ?? $_POST['form_id']     ?? 0 );
        $raw_data    = $vars['lead_data'] ?? $_POST['lead_data'] ?? [];

        if ( $file_id < 1 ) {
            return [ 'error' => true, 'message' => 'invalid_file_id' ];
        }

        // lead_data JSON string olarak gelebilir
        if ( is_string( $raw_data ) ) {
            $raw_data = json_decode( wp_unslash( $raw_data ), true ) ?: [];
        }

        $lead_data = self::extractLeadData( is_array( $raw_data ) ? $raw_data : [] );

        $valid = self::validateLeadData( $lead_data );
        if ( $valid !== true ) {
            return [ 'error' => true, 'message' => $valid ];
        }

        // Guest kullanıcı → cookie + wp_guests row oluştur
        $guest_id = '';
        if ( ! is_user_logged_in() ) {
            $guest_id = GuestIdentity::ensureGuest();

            // Lead data'yı wp_guests profiline yaz
            // extractLeadIndex ile email/name tespit et, geri kalanı meta'ya koy
            $index = self::extractLeadIndex( $lead_data );
            $meta  = array_diff_key( $lead_data, array_flip( [ 'email', 'eposta', 'e-posta', 'e_posta', 'mail', 'your-name', 'name', 'isim', 'ad', 'adi', 'ad-soyad', 'adsoyad', 'full-name', 'fullname' ] ) );

            GuestIdentity::updateProfile( [
                'email' => $index['email'] ?? '',
                'name'  => $index['name']  ?? '',
                'phone' => $lead_data['phone'] ?? $lead_data['telefon'] ?? $lead_data['tel'] ?? '',
                'meta'  => ! empty( $meta ) ? $meta : [],
            ] );
        }

        // Cookie yaz (lead_already_given kontrolü için)
        self::setLeadCookie( $form_id, $lead_data );

        // Token üret
        $token = self::generateToken(
            $file_id,
            $source_post,
            'lead_capture',
            get_current_user_id(),
            $guest_id ?: GuestIdentity::getId()
        );

        // Log yaz — lead data yok, sadece file/user/mode
        $file = self::getFileInfo( $file_id );
        $log_id = self::addLog( [
            'file_id'     => $file_id,
            'file_name'   => $file ? $file['name'] : '',
            'file_url'    => $file ? $file['url'] : '',
            'user_id'     => get_current_user_id(),
            'guest_id'    => $guest_id ?: GuestIdentity::getId(),
            'source_post' => $source_post,
            'mode'        => 'lead_capture',
            'language'    => GuestIdentity::getCurrentLanguage(),
        ] );

        self::fireLeadNotification( $log_id, $lead_data );

        return [
            'error'        => false,
            'status'       => 'allowed',
            'download_url' => self::buildDownloadUrl( $token ),
        ];
    }

    // ─── AUTOCOMPLETE ────────────────────────────────────

    /**
     * Post autocomplete — admin Rules tab için.
     * POST: keyword, post_type
     */
    public static function searchPosts(): void {
        check_ajax_referer( 'sh_download_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $keyword   = sanitize_text_field( $_POST['keyword'] ?? '' );
        $post_type = sanitize_key( $_POST['post_type'] ?? 'any' );

        $args = [
            'post_type'      => $post_type === 'any' ? get_post_types( [ 'public' => true ] ) : $post_type,
            'posts_per_page' => 20,
            'post_status'    => 'publish',
        ];
        if ( $keyword ) $args['s'] = $keyword;

        $posts   = get_posts( $args );
        $results = [];
        foreach ( $posts as $post ) {
            $results[] = [
                'id'   => $post->ID,
                'text' => $post->post_title . ' (' . $post->post_type . ')',
            ];
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    /**
     * Term autocomplete — admin Rules tab için.
     * POST: keyword, taxonomy
     */
    public static function searchTerms(): void {
        check_ajax_referer( 'sh_download_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $keyword  = sanitize_text_field( $_POST['keyword'] ?? '' );
        $taxonomy = sanitize_key( $_POST['taxonomy'] ?? '' );

        if ( ! $taxonomy ) wp_send_json_error( 'missing_taxonomy' );

        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => 20,
        ];
        if ( $keyword ) $args['search'] = $keyword;

        $terms   = get_terms( $args );
        $results = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $results[] = [
                    'id'   => $term->term_id,
                    'text' => $term->name . ' (' . $term->taxonomy . ')',
                ];
            }
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    // ─── RULE CRUD ───────────────────────────────────────

    /**
     * Kural kaydet.
     * POST: rule (JSON)
     */
    public static function saveRule(): void {
        check_ajax_referer( 'sh_download_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $raw  = json_decode( wp_unslash( $_POST['rule'] ?? '{}' ), true );
        if ( ! is_array( $raw ) ) wp_send_json_error( 'invalid_data' );

        $rule = DownloadRules::sanitizeRule( $raw );
        $id   = DownloadRules::saveRule( $rule );

        wp_send_json_success( [ 'id' => $id, 'rule' => $rule ] );
    }

    /**
     * Kural sil.
     * POST: rule_id
     */
    public static function deleteRule(): void {
        check_ajax_referer( 'sh_download_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $rule_id = sanitize_key( $_POST['rule_id'] ?? '' );
        if ( ! $rule_id ) wp_send_json_error( 'missing_rule_id' );

        DownloadRules::deleteRule( $rule_id );
        wp_send_json_success( [ 'id' => $rule_id ] );
    }

    // ─── EXPORT ──────────────────────────────────────────

    /**
     * Log export — CSV veya XLSX.
     * GET: format (csv|xlsx), date_from, date_to, mode, nonce
     */
    public static function exportLogs(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        if ( ! check_admin_referer( 'sh_download_export' ) ) wp_die( 'Invalid nonce', 403 );

        $format    = sanitize_key( $_GET['format'] ?? 'csv' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
        $mode      = sanitize_key( $_GET['mode'] ?? '' );

        $result = self::getLogs( [
            'per_page'  => 99999,
            'page'      => 1,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'mode'      => $mode,
        ] );

        $items    = $result['items'];
        $filename = 'download-log-' . ( $date_from ?: date( 'Y-m-d' ) ) . '-to-' . ( $date_to ?: date( 'Y-m-d' ) );

        if ( $format === 'xlsx' ) {
            self::exportXlsx( $items, $filename );
        } else {
            self::exportCsv( $items, $filename );
        }
    }

    /**
     * CSV export.
     */
    private static function exportCsv( array $items, string $filename ): void {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );

        // BOM — Excel Türkçe karakter uyumu
        fputs( $out, "\xEF\xBB\xBF" );

        // Header
        fputcsv( $out, [ 'ID', 'File', 'User', 'Guest ID', 'Mode', 'Lead Email', 'Lead Name', 'Lead Phone', 'IP', 'Source Post', 'Language', 'Date' ] );

        foreach ( $items as $row ) {
            fputcsv( $out, [
                $row->id,
                $row->file_name,
                $row->user_id ? ( get_userdata( $row->user_id )->display_name ?? $row->user_id ) : '',
                $row->guest_id ?? '',
                $row->mode,
                $row->guest_email ?? '',
                $row->guest_name  ?? '',
                $row->guest_phone ?? '',
                $row->ip,
                $row->source_post ? get_the_title( $row->source_post ) : '',
                $row->language,
                $row->created_at,
            ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * XLSX export — PhpSpreadsheet yoksa CSV'ye fallback.
     */
    private static function exportXlsx( array $items, string $filename ): void {
        // PhpSpreadsheet varsa kullan
        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            self::exportXlsxNative( $items, $filename );
            return;
        }

        // Fallback: HTML table → .xls (Excel açar)
        header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
        header( 'Pragma: no-cache' );

        echo "\xEF\xBB\xBF";
        echo '<table border="1">';
        echo '<tr><th>ID</th><th>File</th><th>User</th><th>Guest ID</th><th>Mode</th><th>Lead Email</th><th>Lead Name</th><th>Lead Phone</th><th>IP</th><th>Source Post</th><th>Language</th><th>Date</th></tr>';

        foreach ( $items as $row ) {
            $user = $row->user_id ? ( get_userdata( $row->user_id )->display_name ?? $row->user_id ) : '';
            echo '<tr>';
            echo '<td>' . esc_html( $row->id ) . '</td>';
            echo '<td>' . esc_html( $row->file_name ) . '</td>';
            echo '<td>' . esc_html( $user ) . '</td>';
            echo '<td>' . esc_html( $row->guest_id ?? '' ) . '</td>';
            echo '<td>' . esc_html( $row->mode ) . '</td>';
            echo '<td>' . esc_html( $row->guest_email ?? '' ) . '</td>';
            echo '<td>' . esc_html( $row->guest_name  ?? '' ) . '</td>';
            echo '<td>' . esc_html( $row->guest_phone ?? '' ) . '</td>';
            echo '<td>' . esc_html( $row->ip ) . '</td>';
            echo '<td>' . esc_html( $row->source_post ? get_the_title( $row->source_post ) : '' ) . '</td>';
            echo '<td>' . esc_html( $row->language ) . '</td>';
            echo '<td>' . esc_html( $row->created_at ) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        exit;
    }

    /**
     * XLSX native — PhpSpreadsheet ile.
     */
    private static function exportXlsxNative( array $items, string $filename ): void {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = [ 'ID', 'File', 'User', 'Guest ID', 'Mode', 'Lead Email', 'Lead Name', 'Lead Phone', 'IP', 'Source Post', 'Language', 'Date' ];
        $sheet->fromArray( $headers, null, 'A1' );

        // Header stil
        $headerStyle = [
            'font'      => [ 'bold' => true, 'color' => [ 'rgb' => 'FFFFFF' ] ],
            'fill'      => [ 'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => [ 'rgb' => '2271B1' ] ],
            'alignment' => [ 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER ],
        ];
        $sheet->getStyle( 'A1:L1' )->applyFromArray( $headerStyle );

        $row_num = 2;
        foreach ( $items as $row ) {
            $user = $row->user_id ? ( get_userdata( $row->user_id )->display_name ?? $row->user_id ) : '';
            $sheet->fromArray( [
                $row->id,
                $row->file_name,
                $user,
                $row->guest_id ?? '',
                $row->mode,
                $row->guest_email ?? '',
                $row->guest_name  ?? '',
                $row->guest_phone ?? '',
                $row->ip,
                $row->source_post ? get_the_title( $row->source_post ) : '',
                $row->language,
                $row->created_at,
            ], null, 'A' . $row_num );
            $row_num++;
        }

        // Auto width
        foreach ( range( 'A', 'L' ) as $col ) {
            $sheet->getColumnDimension( $col )->setAutoSize( true );
        }

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
        header( 'Cache-Control: max-age=0' );

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( 'php://output' );
        exit;
    }
}
