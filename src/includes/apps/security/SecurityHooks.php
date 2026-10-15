<?php

namespace SaltHareket\Security;

/**
 * SecurityHooks
 *
 * Frontend hook'ları: security headers, file protection, WP hardening, spam/bot, CSP.
 * Eski konum: actions.php (add_cache_control_headers, CSP comment bloğu).
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-08-26
 *     - Add: Initial — tüm frontend security hook'ları SecuritySettings'ten okur
 */
class SecurityHooks
{
    public static function register(): void
    {
        add_action( 'send_headers',    [ self::class, 'sendSecurityHeaders' ] );
        add_action( 'init',            [ self::class, 'applyWpHardening' ] );
        add_action( 'wp_head',         [ self::class, 'removeGeneratorMeta' ], 1 );
        add_action( 'comment_form',    [ self::class, 'addCommentHoneypot' ] );
        add_action( 'preprocess_comment', [ self::class, 'checkCommentHoneypot' ] );
        add_filter( 'auth_cookie_expiration', [ self::class, 'sessionExpiry' ] );

        // CSP violation reporter — nopriv: frontend her kullanıcıdan gelir
        add_action( 'wp_ajax_sh_save_csp_violations',        [ self::class, 'saveCspViolations' ] );
        add_action( 'wp_ajax_nopriv_sh_save_csp_violations', [ self::class, 'saveCspViolations' ] );
    }

    // ─── CSP Violation Reporter ───────────────────────────────────────────────
    // Eski konum: class.lcp.php (Lcp::ajaxSaveCspViolations). Buraya taşındı —
    // security app'e ait, LCP ile ilgisi yok.

    public static function saveCspViolations(): void
    {
        $raw = isset( $_POST['violations'] ) ? stripslashes( $_POST['violations'] ) : '';
        if ( ! $raw ) { wp_send_json_success( 'empty' ); return; }

        $violations = json_decode( $raw, true );
        if ( ! is_array( $violations ) || empty( $violations ) ) {
            wp_send_json_success( 'no violations' ); return;
        }

        // ── csp_violation_reports: admin UI için ─────────────────────────
        $reports = get_option( 'csp_violation_reports', [] );
        if ( ! is_array( $reports ) ) $reports = [];

        foreach ( $violations as $v ) {
            $directive = sanitize_text_field( $v['directive'] ?? '' );
            $domain    = sanitize_text_field( $v['domain']    ?? '' );
            $page      = sanitize_text_field( $v['page']      ?? '' );
            if ( ! $directive || ! $domain ) continue;

            $key = $directive . '|' . $domain;
            if ( ! isset( $reports[ $key ] ) ) {
                $reports[ $key ] = [
                    'directive'  => $directive,
                    'domain'     => $domain,
                    'pages'      => [],
                    'count'      => 0,
                    'first_seen' => current_time( 'mysql' ),
                ];
            }
            $reports[ $key ]['count']++;
            $reports[ $key ]['last_seen'] = current_time( 'mysql' );
            if ( $page && ! in_array( $page, $reports[ $key ]['pages'], true ) ) {
                $reports[ $key ]['pages'][] = $page;
                if ( count( $reports[ $key ]['pages'] ) > 5 ) array_shift( $reports[ $key ]['pages'] );
            }
        }
        update_option( 'csp_violation_reports', $reports );

        // ── csp_detected_domains: buildCspString() otomatik merge eder ──
        $detected = get_option( 'csp_detected_domains', [] );
        if ( ! is_array( $detected ) ) $detected = [];

        foreach ( $violations as $v ) {
            $directive = sanitize_text_field( $v['directive'] ?? '' );
            $domain    = sanitize_text_field( $v['domain']    ?? '' );
            if ( ! $directive || ! $domain ) continue;
            if ( ! isset( $detected[ $directive ] ) ) $detected[ $directive ] = [];
            if ( ! in_array( $domain, $detected[ $directive ], true ) ) {
                $detected[ $directive ][] = $domain;
            }
        }
        update_option( 'csp_detected_domains', $detected );

        wp_send_json_success( [ 'saved' => count( $violations ) ] );
    }

    // ─── Security Headers ────────────────────────────────────────────────────

    public static function sendSecurityHeaders(): void
    {
        if ( is_admin() || headers_sent() ) return;

        $s = SecuritySettings::getSection( 'headers' );

        if ( ! empty( $s['x_content_type_options'] ) ) {
            header( 'X-Content-Type-Options: nosniff' );
        }
        if ( ! empty( $s['x_frame_options'] ) ) {
            header( 'X-Frame-Options: SAMEORIGIN' );
        }
        if ( ! empty( $s['referrer_policy'] ) ) {
            header( 'Referrer-Policy: ' . sanitize_text_field( $s['referrer_policy'] ) );
        }
        if ( ! empty( $s['permissions_policy'] ) ) {
            header( 'Permissions-Policy: ' . $s['permissions_policy'] );
        }
        if ( ! empty( $s['hsts_enabled'] ) && is_ssl() ) {
            $max_age = (int) ( $s['hsts_max_age'] ?? 31536000 );
            header( 'Strict-Transport-Security: max-age=' . $max_age . '; includeSubDomains' );
        }

        // CSP
        $csp = SecuritySettings::getSection( 'csp' );
        if ( ! empty( $csp['enabled'] ) ) {
            $csp_string = self::buildCspString( $csp['approved_domains'] ?? [] );
            if ( $csp_string ) {
                $header_name = ! empty( $csp['report_only'] )
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy';
                header( $header_name . ': ' . $csp_string );
            }
        }

        // MP3 streaming headers
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( stripos( $request_uri, '.mp3' ) !== false ) {
            header( 'Cache-Control: public, max-age=31536000, immutable' );
            header( 'Content-Type: audio/mpeg' );
            header( 'Accept-Ranges: bytes' );
            header( 'Content-Disposition: inline' );
            return;
        }

        // Cache-Control
        if ( ! empty( $s['cache_control'] ) ) {
            // my-account ve alt sayfaları — login durumundan bağımsız no-store
            $is_account_page = function_exists( 'is_account_page' ) && is_account_page();
            if ( $is_account_page || is_user_logged_in() ) {
                header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
                header( 'Pragma: no-cache' );
            } else {
                header( 'Cache-Control: public, max-age=3600, must-revalidate' );
            }
        }
    }

    // ─── CSP Builder ─────────────────────────────────────────────────────────

    /**
     * CSP direktiflerini string'e çevirir.
     * Temel (hardcoded) direktifler + approved_domains birleştirilir.
     */
    public static function buildCspString( array $approved_domains = [] ): string
    {
        $directives = [
            'default-src' => [ "'self'" ],
            'style-src'   => [ "'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://unpkg.com', 'https://maps.googleapis.com', 'https://maps.gstatic.com', 'https://cdnjs.cloudflare.com', 'https://cdn.plyr.io' ],
            'script-src'  => [ "'self'", "'unsafe-inline'", "'unsafe-eval'", 'blob:', 'https://unpkg.com', 'https://maps.googleapis.com', 'https://maps.gstatic.com', 'https://www.youtube.com', 'https://player.vimeo.com', 'https://www.googletagmanager.com', 'https://www.google-analytics.com', 'https://connect.facebook.net' ],
            'worker-src'  => [ "'self'", 'blob:' ],
            'img-src'     => [ "'self'", 'data:', 'blob:', 'https://*.google.com', 'https://*.googleapis.com', 'https://*.gstatic.com', 'https://img.youtube.com', 'https://i.ytimg.com', 'https://*.tile.openstreetmap.org', 'https://s.w.org', 'https://secure.gravatar.com', 'https://*.vimeocdn.com' ],
            'font-src'    => [ "'self'", 'data:', 'https://fonts.gstatic.com', 'https://cdnjs.cloudflare.com' ],
            'object-src'  => [ "'none'" ],
            'base-uri'    => [ "'self'" ],
            'frame-ancestors' => [ "'self'" ],
            'frame-src'   => [ "'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com', 'https://*.google.com', 'https://www.openstreetmap.org' ],
            'connect-src' => [ "'self'", 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com', 'https://*.googleapis.com', 'https://*.gstatic.com', 'https://*.tile.openstreetmap.org' ],
        ];

        // Manuel eklenen (approved_domains) merge et
        foreach ( $approved_domains as $directive => $domains ) {
            if ( is_array( $domains ) ) {
                if ( ! isset( $directives[ $directive ] ) ) {
                    $directives[ $directive ] = [];
                }
                foreach ( $domains as $domain ) {
                    if ( ! in_array( $domain, $directives[ $directive ], true ) ) {
                        $directives[ $directive ][] = $domain;
                    }
                }
            }
        }

        // PAE'nin saptadığı domainleri otomatik merge et (csp_detected_domains)
        $detected = get_option( 'csp_detected_domains', [] );
        if ( is_array( $detected ) ) {
            foreach ( $detected as $directive => $domains ) {
                if ( is_array( $domains ) ) {
                    if ( ! isset( $directives[ $directive ] ) ) {
                        $directives[ $directive ] = [];
                    }
                    foreach ( $domains as $domain ) {
                        // Scheme ekle — PAE sadece host kaydediyor
                        $entry = ( strpos( $domain, 'http' ) === 0 ) ? $domain : 'https://' . $domain;
                        if ( ! in_array( $entry, $directives[ $directive ], true ) ) {
                            $directives[ $directive ][] = $entry;
                        }
                    }
                }
            }
        }

        $parts = [];
        foreach ( $directives as $key => $values ) {
            $parts[] = $key . ' ' . implode( ' ', $values );
        }
        return implode( '; ', $parts );
    }

    // ─── WP Hardening ────────────────────────────────────────────────────────

    public static function applyWpHardening(): void
    {
        $s = SecuritySettings::getSection( 'wp_hardening' );

        // REST API /users endpoint'ini kapat
        if ( ! empty( $s['disable_rest_users'] ) ) {
            add_filter( 'rest_endpoints', function ( $endpoints ) {
                if ( isset( $endpoints['/wp/v2/users'] ) ) {
                    unset( $endpoints['/wp/v2/users'] );
                }
                if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
                    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
                }
                return $endpoints;
            } );
        }

        // Author enum engelle — /?author=1 yönlendirme
        if ( ! empty( $s['disable_author_enum'] ) ) {
            add_action( 'template_redirect', function () {
                if ( ! is_admin() && isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) {
                    wp_redirect( home_url( '/' ), 301 );
                    exit;
                }
            } );
        }

        // xmlrpc.php engelle
        $fp = SecuritySettings::getSection( 'file_protection' );
        if ( ! empty( $fp['disable_xmlrpc'] ) ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
        }

        // readme.txt / license.txt erişim engelle
        if ( ! empty( $fp['hide_readme'] ) ) {
            add_action( 'template_redirect', function () {
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if ( preg_match( '/\/(readme\.txt|license\.txt|wp-config\.php)/i', $uri ) ) {
                    wp_die( 'Forbidden', 'Forbidden', [ 'response' => 403 ] );
                }
            } );
        }
    }

    // ─── Generator Meta ──────────────────────────────────────────────────────

    public static function removeGeneratorMeta(): void
    {
        $s = SecuritySettings::getSection( 'wp_hardening' );
        if ( empty( $s['remove_generator_meta'] ) ) return;

        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );
    }

    // ─── Session Expiry ──────────────────────────────────────────────────────

    public static function sessionExpiry( int $expirein ): int
    {
        $s    = SecuritySettings::getSection( 'wp_hardening' );
        $days = max( 1, (int) ( $s['session_expiry_days'] ?? 365 ) );
        return $days * DAY_IN_SECONDS;
    }

    // ─── Comment Honeypot ────────────────────────────────────────────────────

    public static function addCommentHoneypot(): void
    {
        $s = SecuritySettings::getSection( 'spam_bot' );
        if ( empty( $s['comment_honeypot'] ) ) return;
        echo '<p style="display:none!important" aria-hidden="true">'
           . '<label for="sh_hp_comment">Leave empty</label>'
           . '<input type="text" name="sh_hp_comment" id="sh_hp_comment" value="" autocomplete="off" tabindex="-1">'
           . '</p>';
    }

    public static function checkCommentHoneypot( array $commentdata ): array
    {
        $s = SecuritySettings::getSection( 'spam_bot' );
        if ( empty( $s['comment_honeypot'] ) ) return $commentdata;

        if ( ! empty( $_POST['sh_hp_comment'] ) ) {
            wp_die( 'Spam detected.', 'Error', [ 'response' => 403 ] );
        }
        return $commentdata;
    }
}
