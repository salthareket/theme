<?php

namespace SaltHareket\Maintenance;

/**
 * Maintenance — Ana kontrol sınıfı.
 *
 * Sorumluluklar:
 *  - isVisible()          : Mevcut isteğin/kullanıcının siteyi görmesi gerekip gerekmediğini döner
 *  - handleRequest()      : template_redirect hook'undan çağrılır; maintenance sayfasını render eder, exit() yapar
 *  - getUserIp()          : Proxy-aware IP tespiti
 *
 * Bypass sırası (isVisible):
 *  1. DOING_CRON / DOING_AJAX / WP_CLI / REST_REQUEST
 *  2. is_admin() (WP admin paneli)
 *  3. manage_options yetkisi (administrator)
 *  4. bypass_roles listesindeki roller
 *  5. ip_whitelist
 *
 * @version 1.1.0
 * @package SaltHareket\Maintenance
 */
class Maintenance
{
    // ─── Visibility ──────────────────────────────────────────────────────────

    /**
     * Mevcut isteğin siteyi görmesi gerekip gerekmediğini döner.
     *
     * @return bool true = normal siteyi göster, false = maintenance sayfası göster
     */
    public static function isVisible(): bool
    {
        if ( ! MaintenanceSettings::isActive() ) return true;

        // Sistem/arka plan istekleri — hiçbir zaman bloklanmaz
        if ( defined( 'DOING_CRON' )    && DOING_CRON )    return true;
        if ( defined( 'DOING_AJAX' )    && DOING_AJAX )    return true;
        if ( defined( 'WP_CLI' )        && WP_CLI )        return true;
        if ( defined( 'REST_REQUEST' )  && REST_REQUEST )  return true;

        // TurboApi — /api/{method} istekleri: api_method query var set edilmişse bypass
        // (TurboApi handle_api_request()'te DOING_AJAX'ı kendisi define eder, bizden önce yapamaz)
        if ( get_query_var( 'api_method' ) ) return true;

        // WP admin paneli
        if ( is_admin() ) return true;

        // Administrator her zaman görür
        if ( current_user_can( 'manage_options' ) ) return true;

        // Bypass roles
        if ( is_user_logged_in() ) {
            $bypass_roles = MaintenanceSettings::getSetting( 'bypass_roles', [] );
            if ( ! empty( $bypass_roles ) ) {
                $user = wp_get_current_user();
                if ( array_intersect( (array) $user->roles, (array) $bypass_roles ) ) {
                    return true;
                }
            }
        }

        // IP whitelist
        $ip_list = MaintenanceSettings::getSetting( 'ip_whitelist', [] );
        if ( ! empty( $ip_list ) ) {
            $user_ip = self::getUserIp();
            if ( $user_ip && in_array( $user_ip, (array) $ip_list, true ) ) {
                return true;
            }
        }

        return false;
    }

    // ─── Request Handler ──────────────────────────────────────────────────────

    /**
     * template_redirect hook'undan çağrılır.
     * Maintenance gösterilmesi gereken isteklerde:
     *  - HTTP header'ı set eder
     *  - Maintenance sayfasını Timber ile render eder
     *  - exit() ile WP'nin normal akışını keser
     *
     * Bypass olan istekler (AJAX, admin, bypass roles, IP, white pages) dokunulmadan geçer.
     */
    public static function handleRequest(): void
    {
        // İstek bypass ediliyor mu?
        if ( self::isVisible() ) return;

        // White page kontrolü — WP bu noktada global $post'u set etmiş olabilir
        // Ayrıca URL'den de kontrol et (bazı sayfalarda $post henüz dolu olmayabilir)
        $page_id = self::resolveCurrentPageId();
        if ( $page_id > 0 && MaintenanceSettings::isWhitePage( $page_id ) ) return;

        $settings     = MaintenanceSettings::get();
        $http_status  = (int) ( $settings['http_status']  ?? 503 );
        $redirect_url = (string) ( $settings['redirect_url'] ?? '' );
        $maint_page   = (int) ( $settings['maintenance_page'] ?? 0 );

        // 301 Redirect
        if ( $http_status === 301 && ! empty( $redirect_url ) ) {
            wp_redirect( esc_url_raw( $redirect_url ), 301 );
            exit();
        }

        // HTTP Status
        if ( $http_status === 503 ) {
            status_header( 503 );
            header( 'Retry-After: 3600' );
        } elseif ( $http_status === 404 ) {
            status_header( 404 );
        }
        // 200: header gönderme (normal)

        // Maintenance sayfası seçilmişse Timber ile render et
        if ( $maint_page > 0 && class_exists( '\Timber\Timber' ) ) {
            $timber_post = \Timber\Timber::get_post( $maint_page );

            if ( $timber_post ) {
                // Global $post'u WP uyumu için güncelle
                global $post;
                $post = get_post( $maint_page );
                if ( $post ) {
                    setup_postdata( $post );
                }

                if ( ! defined( 'MAINTENANCE_PAGE_ID' ) ) {
                    define( 'MAINTENANCE_PAGE_ID', $maint_page );
                }

                $context         = \Timber\Timber::context();
                $context['post'] = $timber_post;

                \Timber\Timber::render(
                    [
                        'page-under-construction.twig',
                        'page-' . $timber_post->post_name . '.twig',
                        'page.twig',
                    ],
                    $context
                );
                exit();
            }
        }

        // Sayfa seçilmemiş veya bulunamadı — HTTP status header gönderildi, WP normal 503/404 sayfasını gösterir
        if ( ! defined( 'MAINTENANCE_PAGE_ID' ) ) {
            define( 'MAINTENANCE_PAGE_ID', 0 );
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Mevcut isteğin sayfa ID'sini döner.
     * Global $post henüz set edilmemişse URL'den çözmeyi dener.
     *
     * @return int  0 = bulunamadı
     */
    public static function resolveCurrentPageId(): int
    {
        global $post;

        if ( isset( $post->ID ) && $post->ID > 0 ) {
            return (int) $post->ID;
        }

        // Fallback: URL'den çöz
        if ( function_exists( 'url_to_postid' ) ) {
            $url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' )
                . '://' . ( $_SERVER['HTTP_HOST'] ?? '' )
                . ( $_SERVER['REQUEST_URI'] ?? '' );
            $id = url_to_postid( $url );
            if ( $id > 0 ) return $id;
        }

        return 0;
    }

    /**
     * Proxy-aware IP tespiti.
     * Sıra: Cloudflare → X-Forwarded-For → X-Real-IP → REMOTE_ADDR
     *
     * @return string  Geçerli IP adresi veya boş string
     */
    public static function getUserIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) continue;
            // X-Forwarded-For birden fazla IP içerebilir; ilkini al
            $ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return $ip;
            }
            // Local/private IP'lere de izin ver (localhost ortamı)
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return '';
    }
}
