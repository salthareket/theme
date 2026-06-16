<?php

namespace SaltHareket\DownloadLog\Concerns;

use SaltHareket\DownloadLog\DownloadRules;
use SaltHareket\DownloadLog\GuestIdentity;

/**
 * HandlesProtection
 *
 * Erişim kontrolü — login/lead cookie kontrolü, rule resolver.
 * Download isteği geldiğinde bu trait hangi modun aktif olduğunu belirler.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *
 * @package SaltHareket\DownloadLog\Concerns
 */
trait HandlesProtection {

    // ─── ACCESS CHECK ────────────────────────────────────

    /**
     * Bir download isteğinin erişim durumunu kontrol et.
     *
     * @param int    $file_id
     * @param int    $source_post  Hangi sayfadan istek geldi
     * @param array  $override     Twig/PHP'den gelen override options
     * @return array {
     *   @type string $status   'allowed' | 'login_required' | 'lead_required' | 'lead_already_given'
     *   @type string $mode     public | login_required | lead_capture
     *   @type int    $form_id  CF7 form ID (lead_capture modunda)
     *   @type string $login_url
     * }
     */
    public static function checkAccess( int $file_id, int $source_post = 0, array $override = [] ): array {
        // Mode belirle — override > rule > default
        $mode    = '';
        $form_id = 0;
        $rule    = null;

        if ( ! empty( $override['mode'] ) ) {
            $mode    = sanitize_key( $override['mode'] );
            $form_id = (int) ( $override['form_id'] ?? 0 );
        } else {
            $rule    = $source_post > 0 ? DownloadRules::resolveForPost( $source_post ) : null;
            $mode    = $rule ? ( $rule['mode'] ?? 'public' ) : DownloadRules::getDefaultMode();
            $form_id = $rule ? (int) ( $rule['form_id'] ?? 0 ) : 0;
        }

        // Geçersiz mod → public
        if ( ! in_array( $mode, DownloadRules::MODES, true ) ) {
            $mode = 'public';
        }

        // Public — her zaman izin ver
        if ( $mode === 'public' ) {
            return [ 'status' => 'allowed', 'mode' => $mode, 'form_id' => 0, 'login_url' => '' ];
        }

        // Login Required
        if ( $mode === 'login_required' ) {
            if ( is_user_logged_in() ) {
                return [ 'status' => 'allowed', 'mode' => $mode, 'form_id' => 0, 'login_url' => '' ];
            }
            return [
                'status'    => 'login_required',
                'mode'      => $mode,
                'form_id'   => 0,
                'login_url' => self::getLoginUrl( get_permalink( $source_post ) ?: home_url() ),
            ];
        }

        // Lead Capture
        if ( $mode === 'lead_capture' ) {
            // Login'li kullanıcı — direkt izin ver
            if ( is_user_logged_in() ) {
                return [ 'status' => 'allowed', 'mode' => $mode, 'form_id' => $form_id, 'login_url' => '' ];
            }

            // Cookie var mı? (daha önce form doldurulmuş)
            if ( self::hasLeadCookie( $form_id ) ) {
                return [ 'status' => 'lead_already_given', 'mode' => $mode, 'form_id' => $form_id, 'login_url' => '' ];
            }

            // Form doldurulması gerekiyor
            return [ 'status' => 'lead_required', 'mode' => $mode, 'form_id' => $form_id, 'login_url' => '' ];
        }

        return [ 'status' => 'allowed', 'mode' => $mode, 'form_id' => $form_id, 'login_url' => '' ];
    }

    // ─── LEAD COOKIE ─────────────────────────────────────

    /**
     * Lead cookie var mı? (bu form için daha önce bilgi verilmiş mi?)
     */
    public static function hasLeadCookie( int $form_id ): bool {
        $key = 'sh_lead_' . $form_id;
        return ! empty( $_COOKIE[ $key ] );
    }

    /**
     * Lead cookie'yi set et.
     * Form doldurulduktan sonra çağrılır.
     */
    public static function setLeadCookie( int $form_id, array $lead_data = [] ): void {
        $key     = 'sh_lead_' . $form_id;
        $days    = (int) apply_filters( 'sh_download_lead_cookie_days', 365 );
        $expires = time() + $days * DAY_IN_SECONDS;

        $value = wp_json_encode( [
            'form_id'    => $form_id,
            'guest_id'   => GuestIdentity::getId(),
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
            'data'       => $lead_data,
        ] );

        setcookie( $key, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
        $_COOKIE[ $key ] = $value;
    }

    /**
     * Lead cookie'den data'yı oku.
     */
    public static function getLeadCookieData( int $form_id ): array {
        $key = 'sh_lead_' . $form_id;
        if ( empty( $_COOKIE[ $key ] ) ) return [];

        $data = json_decode( stripslashes( $_COOKIE[ $key ] ), true );
        return is_array( $data ) ? $data : [];
    }

    // ─── LOGIN URL ───────────────────────────────────────

    /**
     * Login URL'ini döndür — WC/custom/WP login.
     */
    private static function getLoginUrl( string $redirect = '' ): string {
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $url = wc_get_page_permalink( 'myaccount' );
            if ( $url ) {
                return $redirect ? add_query_arg( 'redirect_to', urlencode( $redirect ), $url ) : $url;
            }
        }
        if ( function_exists( 'get_account_endpoint_url' ) ) {
            return get_account_endpoint_url( 'my-account' );
        }
        return wp_login_url( $redirect ?: home_url() );
    }
}
