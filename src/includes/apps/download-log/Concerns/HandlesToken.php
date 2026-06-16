<?php

namespace SaltHareket\DownloadLog\Concerns;

/**
 * HandlesToken
 *
 * Signed download token sistemi.
 * Gerçek dosya URL'i frontend'e hiç yazılmaz.
 * Token: 10 dakika geçerli, tek kullanımlık.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * @package SaltHareket\DownloadLog\Concerns
 */
trait HandlesToken {

    const TOKEN_EXPIRY = 600; // 10 dakika (saniye)

    // ─── GENERATE ────────────────────────────────────────

    /**
     * Dosya için signed token üret.
     * wp_options'a kısa süreli saklanır.
     *
     * @param int    $file_id
     * @param int    $source_post  Hangi sayfadan istek geldi
     * @param string $mode         public|login_required|lead_capture
     * @param int    $user_id      0 = guest
     * @param string $guest_id
     * @return string  token
     */
    public static function generateToken(
        int    $file_id,
        int    $source_post = 0,
        string $mode        = 'public',
        int    $user_id     = 0,
        string $guest_id    = ''
    ): string {
        $token = bin2hex( random_bytes( 24 ) );

        $data = [
            'file_id'     => $file_id,
            'source_post' => $source_post,
            'mode'        => $mode,
            'user_id'     => $user_id,
            'guest_id'    => $guest_id,
            'expires'     => time() + self::TOKEN_EXPIRY,
            'used'        => false,
        ];

        // wp_options'a yaz — transient gibi ama daha kontrollü
        set_transient( 'sh_dl_token_' . $token, $data, self::TOKEN_EXPIRY );

        return $token;
    }

    // ─── VALIDATE ────────────────────────────────────────

    /**
     * Token'ı doğrula ve data'yı döndür.
     * Geçersiz veya süresi dolmuşsa null döner.
     * Tek kullanımlık — doğrulandıktan sonra silinir.
     *
     * @return array|null
     */
    public static function validateToken( string $token ): ?array {
        if ( empty( $token ) || ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) {
            return null;
        }

        $key  = 'sh_dl_token_' . $token;
        $data = get_transient( $key );

        if ( ! $data || ! is_array( $data ) ) {
            return null;
        }

        // Süre kontrolü (transient zaten süreli ama double-check)
        if ( ( $data['expires'] ?? 0 ) < time() ) {
            delete_transient( $key );
            return null;
        }

        // Tek kullanımlık — hemen sil
        delete_transient( $key );

        return $data;
    }

    // ─── DOWNLOAD URL ────────────────────────────────────

    /**
     * Frontend'e yazılacak download URL'ini üret.
     * wp_ajax endpoint kullanılır — binary stream için TurboAPI değil admin-ajax.php.
     * TurboAPI JSON wrapper içinde binary stream çıkamaz.
     *
     * @return string
     */
    public static function buildDownloadUrl( string $token ): string {
        return add_query_arg( [
            'action' => 'sh_download',
            'token'  => $token,
        ], admin_url( 'admin-ajax.php' ) );
    }

    /**
     * Dosya bilgilerini attachment ID'den al.
     *
     * @return array|null { id, url, path, name, mime, size }
     */
    public static function getFileInfo( int $file_id ): ?array {
        if ( $file_id < 1 ) return null;

        $url  = wp_get_attachment_url( $file_id );
        $path = get_attached_file( $file_id );
        $meta = wp_get_attachment_metadata( $file_id );
        $mime = get_post_mime_type( $file_id );
        $name = basename( $path ?: $url ?: '' );

        if ( ! $url ) return null;

        return [
            'id'   => $file_id,
            'url'  => $url,
            'path' => $path ?: '',
            'name' => $name,
            'mime' => $mime ?: 'application/octet-stream',
            'size' => $path && file_exists( $path ) ? filesize( $path ) : 0,
        ];
    }
}
