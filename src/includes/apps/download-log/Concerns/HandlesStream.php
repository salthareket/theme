<?php

namespace SaltHareket\DownloadLog\Concerns;

use SaltHareket\DownloadLog\GuestIdentity;

/**
 * HandlesStream
 *
 * Dosya stream — gerçek URL hiç frontend'e yazılmaz.
 * Token doğrulandıktan sonra PHP readfile() ile stream eder.
 * wp_ajax_sh_download action'ından tetiklenir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * @package SaltHareket\DownloadLog\Concerns
 */
trait HandlesStream {

    /**
     * Download stream endpoint.
     * TurboAPI: site.com/api/download_stream?token=xxx
     * wp_ajax fallback: /wp-admin/admin-ajax.php?action=sh_download&token=xxx
     *
     * Token doğrula → erişim kontrol → dosyayı stream et → log yaz.
     *
     * @param string $token  URL'den veya TurboAPI'den gelen token
     */
    public static function stream( string $token = '' ): void {
        // Token: parametre > GET > POST
        if ( ! $token ) {
            $token = sanitize_text_field( $_GET['token'] ?? $_POST['token'] ?? '' );
        }

        // Nonce kontrolü — TurboAPI zaten X-WP-Nonce ile doğruluyor
        // wp_ajax fallback için nonce kontrolü
        if ( ! $token && isset( $_GET['nonce'] ) ) {
            if ( ! check_ajax_referer( 'sh_download', 'nonce', false ) ) {
                self::streamError( 403, 'Invalid nonce' );
            }
        }
        $data  = self::validateToken( $token );
        if ( ! $data ) {
            self::streamError( 403, 'Invalid or expired token' );
        }

        $file_id     = (int) $data['file_id'];
        $source_post = (int) $data['source_post'];
        $mode        = $data['mode'];
        $user_id     = (int) $data['user_id'];
        $guest_id    = $data['guest_id'];

        // Erişim kontrolü — token'daki mode'a göre
        if ( $mode === 'login_required' && ! is_user_logged_in() ) {
            self::streamError( 403, 'Login required' );
        }

        // Dosya bilgilerini al
        $file = self::getFileInfo( $file_id );
        if ( ! $file ) {
            self::streamError( 404, 'File not found' );
        }

        // Dosya fiziksel olarak var mı?
        $use_readfile = $file['path'] && file_exists( $file['path'] );

        // Action hook
        do_action( 'sh_download_before_stream', $file_id, $file );

        // Rate limiting kontrolü
        if ( self::isRateLimited() ) {
            self::streamError( 429, 'Too many requests' );
        }

        // Stream et
        if ( $use_readfile ) {
            self::streamFile( $file );
        } else {
            // Fiziksel dosya yoksa (external URL, CDN vs.) redirect
            wp_redirect( $file['url'] );
            exit;
        }
    }

    /**
     * Dosyayı PHP ile stream et.
     * Gerçek URL hiç tarayıcıya gönderilmez.
     */
    private static function streamFile( array $file ): void {
        // Output buffer'ı temizle
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        // Headers
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . $file['mime'] );
        header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file['name'] ) . '"' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Pragma: public' );

        if ( $file['size'] > 0 ) {
            header( 'Content-Length: ' . $file['size'] );
        }

        // Büyük dosyalar için chunk'lı okuma
        $chunk_size = 1024 * 1024; // 1MB
        $handle     = fopen( $file['path'], 'rb' );

        if ( ! $handle ) {
            self::streamError( 500, 'Cannot open file' );
        }

        while ( ! feof( $handle ) ) {
            echo fread( $handle, $chunk_size ); // phpcs:ignore
            flush();
        }

        fclose( $handle );
        exit;
    }

    /**
     * Rate limiting — aynı IP'den çok fazla istek.
     */
    private static function isRateLimited(): bool {
        $enabled  = (bool) get_option( 'sh_download_rate_limit', false );
        if ( ! $enabled ) return false;

        $max      = (int) get_option( 'sh_download_rate_limit_max', 20 );
        $window   = (int) get_option( 'sh_download_rate_limit_window', 3600 ); // 1 saat
        $ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $key      = 'sh_dl_rate_' . md5( $ip );
        $count    = (int) get_transient( $key );

        if ( $count >= $max ) return true;

        set_transient( $key, $count + 1, $window );
        return false;
    }

    /**
     * Stream hatası — JSON veya HTTP error.
     */
    private static function streamError( int $code, string $message ): void {
        status_header( $code );
        if ( wp_doing_ajax() || ( $_SERVER['HTTP_ACCEPT'] ?? '' ) === 'application/json' ) {
            wp_send_json_error( $message, $code );
        }
        wp_die( esc_html( $message ), $code );
    }
}
