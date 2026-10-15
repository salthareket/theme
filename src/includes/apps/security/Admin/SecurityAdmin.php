<?php

namespace SaltHareket\Security\Admin;

use SaltHareket\Security\SecuritySettings;
use SaltHareket\Security\SecurityHooks;

/**
 * SecurityAdmin
 *
 * General Settings → Security tab UI.
 * Bölümler: Headers, File Protection, WP Hardening, Spam & Bot, CSP, WP Hide.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-08-26
 *     - Add: Initial — tüm security bölümleri için UI + AJAX save
 *     - Note: WP Hide fonksiyonları acf-admin.php'den buraya taşındı
 */
class SecurityAdmin
{
    public static function register(): void
    {
        add_action( 'wp_ajax_sh_security_save',      [ self::class, 'ajaxSave' ] );
        add_action( 'wp_ajax_sh_security_wph',       [ self::class, 'ajaxWphApply' ] );
        add_action( 'wp_ajax_sh_security_csp_scan',  [ self::class, 'ajaxCspScan' ] );
    }

    // ─── AJAX ────────────────────────────────────────────────────────────────

    public static function ajaxSave(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $section = sanitize_key( $_POST['section'] ?? '' );
        $data    = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? $_POST['data'] : [];

        if ( ! $section ) {
            wp_send_json_error( 'Missing section' );
        }

        SecuritySettings::saveSection( $section, $data );

        // CSP özel işlemler
        if ( $section === 'csp' ) {
            // Violation approve → detected_domains'e ekle
            if ( ! empty( $data['approve_violation'] ) ) {
                $v = json_decode( $data['approve_violation'], true );
                if ( $v && ! empty( $v['directive'] ) && ! empty( $v['domain'] ) ) {
                    $detected = get_option( 'csp_detected_domains', [] );
                    if ( ! is_array( $detected ) ) $detected = [];
                    if ( ! isset( $detected[ $v['directive'] ] ) ) $detected[ $v['directive'] ] = [];
                    if ( ! in_array( $v['domain'], $detected[ $v['directive'] ], true ) ) {
                        $detected[ $v['directive'] ][] = sanitize_text_field( $v['domain'] );
                        update_option( 'csp_detected_domains', $detected );
                    }
                    // violation_reports'tan kaldır
                    $reports = get_option( 'csp_violation_reports', [] );
                    $key     = $v['directive'] . '|' . $v['domain'];
                    unset( $reports[ $key ] );
                    update_option( 'csp_violation_reports', $reports );
                }
            }
            // Clear violations
            if ( ! empty( $data['clear_violations'] ) ) {
                delete_option( 'csp_violation_reports' );
            }
        }

        wp_send_json_success( 'Saved' );
    }

    public static function ajaxWphApply(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        if ( ! file_exists( WP_PLUGIN_DIR . '/wp-hide-security-enhancer-pro/wp-hide.php' ) ) {
            wp_send_json_error( 'WP Hide plugin yüklü değil.' );
        }

        if ( ! class_exists( 'Update' ) || ! method_exists( 'Update', 'wph_load_settings' ) ) {
            wp_send_json_error( 'Update::wph_load_settings() bulunamadı.' );
        }

        \Update::wph_load_settings();
        SecuritySettings::saveWphApplied();
        wp_send_json_success( 'Settings applied!' );
    }

    /**
     * CSP Scan — PAE'nin fetch ettiği sayfalardan toplanan csp_approved_domains'i döndür.
     * FetchesPages.php'deki detect_and_save_csp_domains() zaten bu option'ı dolduruyor.
     * Ek olarak yeniden tarama tetiklenebilir.
     */
    public static function ajaxCspScan(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $detected = get_option( 'csp_detected_domains', [] );
        if ( ! is_array( $detected ) ) $detected = [];

        wp_send_json_success( [
            'domains' => $detected,
            'count'   => array_sum( array_map( 'count', $detected ) ),
        ] );
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public static function renderTab( string $nonce, string $ajax ): void
    {
        $s        = SecuritySettings::get();
        $wph_ok   = file_exists( WP_PLUGIN_DIR . '/wp-hide-security-enhancer-pro/wp-hide.php' );
        $referrer_options = [
            'strict-origin-when-cross-origin' => 'strict-origin-when-cross-origin (Önerilen)',
            'no-referrer'                      => 'no-referrer',
            'no-referrer-when-downgrade'       => 'no-referrer-when-downgrade',
            'same-origin'                      => 'same-origin',
            'origin'                           => 'origin',
            'origin-when-cross-origin'         => 'origin-when-cross-origin',
            'unsafe-url'                       => 'unsafe-url',
        ];
        ?>
        <div id="sh-security-wrap" data-ajax="<?php echo esc_url( $ajax ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <!-- ── 1. Security Headers ───────────────────────────────────────── -->
        <div class="sh-security-section">
            <div class="sh-security-section-title">🔒 Security Headers</div>
            <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">HTTP response header'ları. Tarayıcıya sayfanın güvenlik kurallarını bildirir.</p>

            <div class="sh-security-grid">
                <?php self::renderToggle( 'headers', 'x_content_type_options', 'X-Content-Type-Options', 'nosniff — MIME type sniffing engelini aktifleştirir.', $s['headers']['x_content_type_options'] ); ?>
                <?php self::renderToggle( 'headers', 'x_frame_options', 'X-Frame-Options', 'SAMEORIGIN — Clickjacking saldırılarına karşı iframe gömmesini engeller.', $s['headers']['x_frame_options'] ); ?>
                <?php self::renderToggle( 'headers', 'cache_control', 'Cache-Control', 'Login kullanıcılara no-cache, misafirlere 1 saatlik cache gönderir.', $s['headers']['cache_control'] ); ?>
                <?php self::renderToggle( 'headers', 'hsts_enabled', 'HSTS (Strict-Transport-Security)', 'Sadece HTTPS bağlantıya zorlar. Yalnızca HTTPS sitenizde aktifleştirin.', $s['headers']['hsts_enabled'] ); ?>
            </div>

            <div class="sh-security-field sh-mt-sm">
                <label class="sh-label">Referrer-Policy</label>
                <select name="headers[referrer_policy]" class="sh-select sh-security-select" data-section="headers" data-key="referrer_policy" style="max-width:340px">
                    <?php foreach ( $referrer_options as $val => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['headers']['referrer_policy'], $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sh-security-field sh-mt-sm">
                <label class="sh-label">Permissions-Policy</label>
                <?php
                // Her API için: key = directive adı, label = gösterilen ad, default = self|none
                $pp_apis = [
                    'camera'          => [ 'label' => 'Camera',           'desc' => 'Kameraya erişim' ],
                    'microphone'      => [ 'label' => 'Microphone',       'desc' => 'Mikrofona erişim' ],
                    'geolocation'     => [ 'label' => 'Geolocation',      'desc' => 'Konum verisi' ],
                    'payment'         => [ 'label' => 'Payment',          'desc' => 'Ödeme API' ],
                    'usb'             => [ 'label' => 'USB',              'desc' => 'USB cihaz erişimi' ],
                    'bluetooth'       => [ 'label' => 'Bluetooth',        'desc' => 'Bluetooth erişimi' ],
                    'fullscreen'      => [ 'label' => 'Fullscreen',       'desc' => 'Tam ekran API' ],
                    'autoplay'        => [ 'label' => 'Autoplay',         'desc' => 'Otomatik medya oynatma' ],
                    'notifications'   => [ 'label' => 'Notifications',    'desc' => 'Bildirim izni' ],
                    'accelerometer'   => [ 'label' => 'Accelerometer',    'desc' => 'İvme sensörü' ],
                    'gyroscope'       => [ 'label' => 'Gyroscope',        'desc' => 'Jiroskop sensörü' ],
                    'screen-wake-lock'=> [ 'label' => 'Screen Wake Lock', 'desc' => 'Ekranı uyanık tut' ],
                ];
                // Mevcut değerleri parse et — "camera=(), microphone=(self)" formatından çöz
                $current_pp = $s['headers']['permissions_policy'] ?? '';
                $pp_parsed  = [];
                foreach ( explode( ',', $current_pp ) as $part ) {
                    $part = trim( $part );
                    if ( preg_match( '/^([\w\-]+)=\(\s*(.*?)\s*\)$/', $part, $m ) ) {
                        $pp_parsed[ $m[1] ] = trim( $m[2] );
                    }
                }
                $pp_options = [ '' => 'Kısıtlama yok (Varsayılan)', '()' => 'Engelle (none)', '(self)' => 'Sadece site içi (self)' ];
                ?>
                <p class="sh-text-xs sh-text-label" style="margin-bottom:10px">Tarayıcı API izinlerini kontrol et. Her API için "Kısıtlama yok", "Engelle" veya "Sadece site içi" seçebilirsin.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:8px">
                    <?php foreach ( $pp_apis as $api => $info ) :
                        $current_val = $pp_parsed[ $api ] ?? '';
                        // '()' = none, '(self)' = self, '' = not set = no restriction
                        $sel_val = '';
                        if ( isset( $pp_parsed[ $api ] ) ) {
                            $raw = $pp_parsed[ $api ];
                            $sel_val = $raw === '' ? '()' : '(' . $raw . ')';
                        }
                    ?>
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;display:flex;align-items:center;gap:10px">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:12px;font-weight:600;color:#1d2327"><?php echo esc_html( $info['label'] ); ?></div>
                            <div style="font-size:11px;color:#6b7280"><?php echo esc_html( $info['desc'] ); ?></div>
                        </div>
                        <select class="sh-security-pp-select" data-api="<?php echo esc_attr( $api ); ?>" style="font-size:11px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;min-width:140px">
                            <?php foreach ( $pp_options as $oval => $olbl ) : ?>
                            <option value="<?php echo esc_attr( $oval ); ?>" <?php selected( $sel_val, $oval ); ?>><?php echo esc_html( $olbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="sh-pp-value" data-section="headers" data-key="permissions_policy" value="<?php echo esc_attr( $current_pp ); ?>">
            </div>

            <div class="sh-security-field sh-mt-sm" id="sh-hsts-maxage-field" style="<?php echo empty( $s['headers']['hsts_enabled'] ) ? 'display:none' : ''; ?>">
                <label class="sh-label">HSTS Max-Age (saniye)</label>
                <input type="number" class="sh-input sh-security-input" data-section="headers" data-key="hsts_max_age"
                    value="<?php echo (int) $s['headers']['hsts_max_age']; ?>" min="0" style="width:160px">
                <p class="sh-text-xs sh-text-label" style="margin-top:4px">Önerilen: 31536000 (1 yıl)</p>
            </div>

            <div class="sh-flex sh-gap-sm sh-mt-sm">
                <button type="button" class="sh-btn sh-btn-primary sh-security-save-btn" data-section="headers">Kaydet</button>
                <span class="sh-security-msg" style="display:none;align-self:center;font-size:12px"></span>
            </div>
        </div>

        <!-- ── 2. File & Directory Protection ───────────────────────────── -->
        <div class="sh-security-section">
            <div class="sh-security-section-title">📁 File & Directory Protection</div>
            <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">WordPress dosya ve dizin erişim korumaları.</p>

            <div class="sh-security-grid">
                <?php self::renderToggle( 'file_protection', 'disable_xmlrpc', 'xmlrpc.php Engelle', 'Brute force ve DDoS saldırılarında hedef olan XML-RPC\'yi devre dışı bırakır.', $s['file_protection']['disable_xmlrpc'] ); ?>
                <?php self::renderToggle( 'file_protection', 'hide_wp_version', 'WP Versiyon Gizle', 'readme.txt ve RSS feed\'den WordPress versiyon bilgisini kaldırır.', $s['file_protection']['hide_wp_version'] ); ?>
                <?php self::renderToggle( 'file_protection', 'hide_readme', 'readme.txt / license.txt Engelle', 'readme.txt, license.txt ve wp-config.php\'ye doğrudan erişimi engeller (403).', $s['file_protection']['hide_readme'] ); ?>
                <?php self::renderToggle( 'file_protection', 'prevent_dir_listing', 'Dizin Listelemeyi Engelle', '.htaccess\'e Options -Indexes kuralı ekler.', $s['file_protection']['prevent_dir_listing'] ); ?>
            </div>

            <div class="sh-flex sh-gap-sm sh-mt-sm">
                <button type="button" class="sh-btn sh-btn-primary sh-security-save-btn" data-section="file_protection">Kaydet</button>
                <span class="sh-security-msg" style="display:none;align-self:center;font-size:12px"></span>
            </div>
        </div>

        <!-- ── 3. WordPress Hardening ────────────────────────────────────── -->
        <div class="sh-security-section">
            <div class="sh-security-section-title">🛡️ WordPress Hardening</div>
            <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">WordPress çekirdeğine yönelik güvenlik sertleştirmeleri.</p>

            <div class="sh-security-grid">
                <?php self::renderToggle( 'wp_hardening', 'disable_rest_users', 'REST API Users Endpoint Kapat', '/wp-json/wp/v2/users endpoint\'i gizler — kullanıcı adlarının ifşalanmasını önler.', $s['wp_hardening']['disable_rest_users'] ); ?>
                <?php self::renderToggle( 'wp_hardening', 'disable_author_enum', 'Author URL Enumeration Engelle', '/?author=1 ile kullanıcı adı tespitini engeller, ana sayfaya yönlendirir.', $s['wp_hardening']['disable_author_enum'] ); ?>
                <?php self::renderToggle( 'wp_hardening', 'remove_generator_meta', 'Generator Meta Kaldır', '<meta name="generator"> ve RSS\'ten WordPress versiyon bilgisini kaldırır.', $s['wp_hardening']['remove_generator_meta'] ); ?>
            </div>

            <div class="sh-security-field sh-mt-sm">
                <label class="sh-label">Oturum Süresi (gün)</label>
                <input type="number" class="sh-input sh-security-input" data-section="wp_hardening" data-key="session_expiry_days"
                    value="<?php echo (int) $s['wp_hardening']['session_expiry_days']; ?>" min="1" style="width:120px">
                <p class="sh-text-xs sh-text-label" style="margin-top:4px">Kullanıcı oturumunun geçerli kalacağı süre. Varsayılan: 365 gün.</p>
            </div>

            <div class="sh-flex sh-gap-sm sh-mt-sm">
                <button type="button" class="sh-btn sh-btn-primary sh-security-save-btn" data-section="wp_hardening">Kaydet</button>
                <span class="sh-security-msg" style="display:none;align-self:center;font-size:12px"></span>
            </div>
        </div>

        <!-- ── 4. Spam & Bot Protection ──────────────────────────────────── -->
        <div class="sh-security-section">
            <div class="sh-security-section-title">🤖 Spam & Bot Protection</div>
            <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">Bot ve spam saldırılarına karşı hafif önlemler.</p>

            <div class="sh-security-grid">
                <?php self::renderToggle( 'spam_bot', 'comment_honeypot', 'Yorum Honeypot', 'Yorum formuna gizli bir alan ekler. Botlar doldurduğunda yorum reddedilir.', $s['spam_bot']['comment_honeypot'] ); ?>
                <?php self::renderToggle( 'spam_bot', 'login_honeypot', 'Login Honeypot', 'Login formuna gizli bir alan ekler. Botlar doldurduğunda giriş engellenir.', $s['spam_bot']['login_honeypot'] ); ?>
            </div>

            <div class="sh-flex sh-gap-sm sh-mt-sm">
                <button type="button" class="sh-btn sh-btn-primary sh-security-save-btn" data-section="spam_bot">Kaydet</button>
                <span class="sh-security-msg" style="display:none;align-self:center;font-size:12px"></span>
            </div>
        </div>

        <!-- ── 5. Content Security Policy (CSP) ─────────────────────────── -->
        <div class="sh-security-section">
            <div class="sh-security-section-title">📋 Content Security Policy (CSP)</div>
            <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">
                XSS saldırılarına karşı hangi dış kaynaklardan içerik yüklenebileceğini tanımlar.
                PAE sayfaları fetch ederken dış domainleri otomatik saptar — bunlar CSP'ye <strong>otomatik eklenir</strong>.
                Sadece PAE'nin tespit edemediği domainleri (GTM, Analytics, runtime fetch'ler) manuel olarak ekle.
            </p>

            <div class="sh-security-grid" style="margin-bottom:16px">
                <?php self::renderToggle( 'csp', 'enabled', 'CSP Aktif', 'Content-Security-Policy header\'ını gönder.', $s['csp']['enabled'] ); ?>
                <?php self::renderToggle( 'csp', 'report_only', 'Report-Only Modu', 'Engelleme yapmadan sadece rapor gönderir. Test/geçiş için kullanılır.', $s['csp']['report_only'] ); ?>
            </div>

            <?php
            // ── Violation Reports — measure-lcp.js'in topladığı ihlaller ──
            $violation_reports = get_option( 'csp_violation_reports', [] );
            if ( ! is_array( $violation_reports ) ) $violation_reports = [];
            // Henüz detected_domains'e eklenmeyen olanları filtrele
            $detected_now = get_option( 'csp_detected_domains', [] );
            if ( ! is_array( $detected_now ) ) $detected_now = [];
            $pending = array_filter( $violation_reports, function( $r ) use ( $detected_now ) {
                $dir    = $r['directive'] ?? '';
                $domain = $r['domain']    ?? '';
                return ! in_array( $domain, $detected_now[ $dir ] ?? [], true );
            } );
            ?>
            <?php if ( ! empty( $pending ) ) : ?>
            <div style="border:1px solid #fbbf24;border-radius:6px;overflow:hidden;margin-bottom:16px;background:#fffbeb">
                <div style="background:#fef3c7;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #fbbf24">
                    <div>
                        <span style="font-size:12px;font-weight:700;color:#92400e">🚨 Tespit Edilen İhlaller</span>
                        <span style="font-size:11px;color:#78350f;margin-left:8px">CSP aktifken tarayıcının engellediği kaynaklar — onaylayarak detected listesine ekle</span>
                    </div>
                    <button type="button" id="sh-csp-violations-clear" class="sh-btn sh-btn-ghost" style="font-size:11px;padding:4px 10px;color:#92400e">Temizle</button>
                </div>
                <div style="padding:10px 14px">
                    <table style="width:100%;border-collapse:collapse;font-size:11px">
                        <thead>
                            <tr style="color:#78350f;font-weight:700;border-bottom:1px solid #fbbf24">
                                <th style="padding:4px 8px;text-align:left;font-family:monospace">Direktif</th>
                                <th style="padding:4px 8px;text-align:left;font-family:monospace">Domain</th>
                                <th style="padding:4px 8px;text-align:left">Sayfa</th>
                                <th style="padding:4px 8px;text-align:center">Sayı</th>
                                <th style="padding:4px 8px;text-align:center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $pending as $key => $r ) : ?>
                        <tr style="border-bottom:1px solid #fef3c7" data-vkey="<?php echo esc_attr( $key ); ?>">
                            <td style="padding:5px 8px;font-family:monospace;color:#374151"><?php echo esc_html( $r['directive'] ); ?></td>
                            <td style="padding:5px 8px;font-family:monospace;color:#1d4ed8"><?php echo esc_html( $r['domain'] ); ?></td>
                            <td style="padding:5px 8px;color:#6b7280;font-size:10px"><?php echo esc_html( implode( ', ', array_slice( $r['pages'] ?? [], 0, 2 ) ) ); ?></td>
                            <td style="padding:5px 8px;text-align:center;color:#6b7280"><?php echo (int) $r['count']; ?></td>
                            <td style="padding:5px 8px;text-align:center">
                                <button type="button" class="sh-btn sh-btn-primary sh-csp-approve-violation"
                                    data-directive="<?php echo esc_attr( $r['directive'] ); ?>"
                                    data-domain="<?php echo esc_attr( $r['domain'] ); ?>"
                                    style="font-size:10px;padding:2px 8px">
                                    + Ekle
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Saptanan Domainler — PAE otomatik topluyor, otomatik CSP'ye giriyor -->
            <div style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:16px">
                <div style="background:#f9fafb;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb">
                    <div>
                        <span style="font-size:12px;font-weight:700;color:#1d2327">🔍 Saptanan Domainler</span>
                        <span style="font-size:11px;color:#6b7280;margin-left:8px">PAE fetch'lerinden otomatik toplanan dış domainler — CSP'ye otomatik girer</span>
                    </div>
                    <button type="button" id="sh-csp-scan-btn" class="sh-btn sh-btn-ghost" style="font-size:11px;padding:4px 10px">Yenile</button>
                </div>
                <div id="sh-csp-detected-wrap" style="padding:12px 14px;min-height:48px">
                    <?php
                    $detected = get_option( 'csp_detected_domains', [] );
                    if ( ! is_array( $detected ) ) $detected = [];
                    ?>
                    <?php if ( ! empty( $detected ) ) : ?>
                        <?php foreach ( $detected as $directive => $domains ) : ?>
                        <div style="margin-bottom:8px">
                            <span style="font-size:11px;font-weight:700;color:#374151;font-family:monospace;margin-right:6px"><?php echo esc_html( $directive ); ?></span>
                            <?php foreach ( $domains as $domain ) : ?>
                            <span style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:1px 7px;font-size:11px;font-family:monospace;color:#166534;display:inline-block;margin:1px"><?php echo esc_html( $domain ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="font-size:12px;color:#9ca3af;margin:0">
                            Henüz saptanan domain yok. PAE ile sayfaları fetch ettikten sonra buraya gelir.
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sh-asset-manager&tab=page_assets' ) ); ?>" style="color:#2271b1">PAE sayfasına git →</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Manuel Eklenenler — PAE'nin göremediği domainler -->
            <?php
            $manual = $s['csp']['approved_domains'] ?? [];
            $has_manual = ! empty( array_filter( $manual ) );
            ?>
            <div style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:16px">
                <div style="background:#fafafa;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb">
                    <div>
                        <span style="font-size:12px;font-weight:700;color:#1d2327">✏️ Manuel Eklenenler</span>
                        <span style="font-size:11px;color:#6b7280;margin-left:8px">GTM, Analytics, wildcard, runtime fetch domainleri — PAE tespit edemez</span>
                    </div>
                    <button type="button" id="sh-csp-manual-toggle" class="sh-btn sh-btn-ghost" style="font-size:11px;padding:4px 10px">
                        <?php echo $has_manual ? 'Düzenle' : '+ Domain Ekle'; ?>
                    </button>
                </div>

                <!-- Manuel domain listesi (sadece dolu olanlar) -->
                <div id="sh-csp-manual-list" style="padding:10px 14px;<?php echo $has_manual ? '' : 'display:none'; ?>">
                    <?php
                    $directives_show = [ 'script-src', 'style-src', 'img-src', 'frame-src', 'connect-src', 'font-src', 'worker-src', 'media-src' ];
                    foreach ( $directives_show as $dir ) :
                        $dom_list = $manual[ $dir ] ?? [];
                        if ( empty( $dom_list ) ) continue;
                    ?>
                    <div style="margin-bottom:6px">
                        <span style="font-size:11px;font-weight:700;color:#374151;font-family:monospace;margin-right:6px"><?php echo esc_html( $dir ); ?></span>
                        <?php foreach ( $dom_list as $d ) : ?>
                        <span class="sh-csp-manual-tag" data-directive="<?php echo esc_attr( $dir ); ?>" data-domain="<?php echo esc_attr( $d ); ?>"
                            style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;padding:1px 7px;font-size:11px;font-family:monospace;display:inline-flex;align-items:center;gap:4px;margin:1px">
                            <?php echo esc_html( $d ); ?>
                            <span class="sh-csp-remove-tag" style="cursor:pointer;color:#9ca3af;font-size:10px;line-height:1" title="Kaldır">✕</span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add manually formu (toggle ile açılır) -->
                <div id="sh-csp-manual-form" style="padding:12px 14px;border-top:1px solid #e5e7eb;display:none">
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px">Direktif</label>
                            <select id="sh-csp-add-directive" style="font-size:12px;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px">
                                <?php foreach ( $directives_show as $dir ) : ?>
                                <option value="<?php echo esc_attr( $dir ); ?>"><?php echo esc_html( $dir ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;min-width:200px">
                            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px">Domain / Keyword</label>
                            <input type="text" id="sh-csp-add-domain" class="sh-input" style="width:100%"
                                placeholder="örn: https://*.googletagmanager.com veya 'unsafe-inline'">
                        </div>
                        <button type="button" id="sh-csp-add-btn" class="sh-btn sh-btn-primary" style="height:32px">Ekle</button>
                    </div>
                    <p style="margin:6px 0 0;font-size:11px;color:#9ca3af">
                        Wildcard: <code>https://*.example.com</code> &nbsp;|&nbsp;
                        Keyword: <code>'unsafe-inline'</code>, <code>'unsafe-eval'</code>, <code>data:</code>, <code>blob:</code>
                    </p>
                </div>
            </div>

            <div class="sh-flex sh-gap-sm sh-mt-sm">
                <button type="button" class="sh-btn sh-btn-primary sh-security-save-btn" data-section="csp">Kaydet</button>
                <span class="sh-security-msg" style="display:none;align-self:center;font-size:12px"></span>
            </div>
        </div>

        <!-- ── 6. WP Hide ────────────────────────────────────────────────── -->
        <div class="sh-security-section" <?php echo ! $wph_ok ? 'style="opacity:.7"' : ''; ?>>
            <div class="sh-security-section-title">🙈 WP Hide</div>
            <?php if ( $wph_ok ) : ?>
                <p class="sh-text-sm sh-text-label" style="margin-bottom:16px">
                    WP Hide Security Enhancer Pro aktif. <code>content/wph-settings.json</code> dosyasındaki ayarları veritabanına uygular.
                </p>
                <?php if ( ! empty( $s['wp_hide']['last_applied'] ) ) : ?>
                <p class="sh-text-sm" style="margin-bottom:12px;color:#50575e">
                    Son uygulama: <strong><?php echo esc_html( $s['wp_hide']['last_applied'] ); ?></strong>
                </p>
                <?php endif; ?>
                <button type="button" id="sh-wph-apply-btn" class="sh-btn sh-btn-primary">Apply Default Settings</button>
                <span id="sh-wph-msg" style="display:none;margin-left:12px;font-size:12px"></span>
            <?php else : ?>
                <div class="sh-notice sh-notice-warning" style="padding:12px;border-radius:6px;background:#fffbeb;border:1px solid #fbbf24;color:#92400e">
                    <strong>WP Hide Security Enhancer Pro</strong> plugin'i yüklü değil. Bu bölümü kullanmak için plugin'i yükleyin.
                </div>
            <?php endif; ?>
        </div>

        </div><!-- #sh-security-wrap -->

        <style>
        .sh-security-section { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:16px; }
        .sh-security-section-title { font-size:14px; font-weight:700; color:#1d2327; margin-bottom:8px; }
        .sh-security-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; margin-bottom:8px; }
        .sh-security-toggle-card { background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:12px; display:flex; align-items:flex-start; gap:10px; }
        .sh-security-toggle-card .sh-ps-toggle { flex-shrink:0; margin-top:2px; }
        .sh-security-toggle-card .sh-security-card-label { font-size:12px; font-weight:600; color:#1d2327; display:block; margin-bottom:3px; }
        .sh-security-toggle-card .sh-security-card-desc { font-size:11px; color:#6b7280; line-height:1.4; }
        .sh-security-field { }
        .sh-label { display:block; font-size:12px; font-weight:600; color:#1d2327; margin-bottom:6px; }
        .sh-input { border:1px solid #d1d5db; border-radius:4px; padding:6px 10px; font-size:12px; }
        .sh-input:focus { border-color:#2271b1; outline:none; box-shadow:0 0 0 1px #2271b1; }
        .sh-security-select { border:1px solid #d1d5db; border-radius:4px; padding:6px 10px; font-size:12px; }
        </style>

        <script>
        (function($){
            var $wrap = $('#sh-security-wrap');
            var ajax  = $wrap.data('ajax');
            var nonce = $wrap.data('nonce');

            function showMsg($btn, msg, ok) {
                var $msg = $btn.siblings('.sh-security-msg');
                $msg.text(msg).css('color', ok ? '#15803d' : '#dc2626').show();
                setTimeout(function(){ $msg.hide(); }, 3000);
            }

            // Permissions-Policy select'leri değişince hidden input'u güncelle
            function buildPermissionsPolicy() {
                var parts = [];
                $wrap.find('.sh-security-pp-select').each(function(){
                    var api = $(this).data('api');
                    var val = $(this).val();
                    if (val !== '') {
                        parts.push(api + '=' + val);
                    }
                });
                $('#sh-pp-value').val(parts.join(', '));
            }
            $wrap.on('change', '.sh-security-pp-select', buildPermissionsPolicy);

            // Toggle değişince auto-save (section bazlı)
            $wrap.on('change', '.sh-security-toggle', function(){
                var $t = $(this);
                var section = $t.data('section');
                var key     = $t.data('key');
                var val     = $t.is(':checked') ? 1 : 0;

                // HSTS max-age alanını göster/gizle
                if (key === 'hsts_enabled') {
                    $('#sh-hsts-maxage-field').toggle(!!val);
                }
            });

            // Kaydet butonu
            $wrap.on('click', '.sh-security-save-btn', function(){
                var $btn    = $(this);
                var section = $btn.data('section');
                var data    = {};

                // Toggle'ları topla
                $wrap.find('.sh-security-toggle[data-section="' + section + '"]').each(function(){
                    data[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
                });
                // Input'ları topla
                $wrap.find('.sh-security-input[data-section="' + section + '"]').each(function(){
                    data[$(this).data('key')] = $(this).val();
                });
                // Hidden input'ları topla (Permissions-Policy builder gibi)
                $wrap.find('input[type="hidden"][data-section="' + section + '"]').each(function(){
                    data[$(this).data('key')] = $(this).val();
                });
                // Select'leri topla
                $wrap.find('.sh-security-select[data-section="' + section + '"]').each(function(){
                    data[$(this).data('key')] = $(this).val();
                });
                // CSP — manuel domain'leri tag'lardan topla
                if (section === 'csp') {
                    var approved = {};
                    $wrap.find('.sh-csp-manual-tag').each(function(){
                        var dir = $(this).data('directive');
                        var dom = $(this).data('domain');
                        if (!dir || !dom) return;
                        if (!approved[dir]) approved[dir] = [];
                        if (approved[dir].indexOf(dom) === -1) approved[dir].push(dom);
                    });
                    data.approved_domains = approved;
                }

                $btn.prop('disabled', true).text('Kaydediliyor...');
                $.post(ajax, { action: 'sh_security_save', nonce: nonce, section: section, data: data }, function(res){
                    $btn.prop('disabled', false).text('Kaydet');
                    showMsg($btn, res.success ? '✓ Kaydedildi' : '✗ Hata: ' + (res.data || ''), res.success);
                });
            });

            // Manuel domain kaldır (✕ tıklama)
            $wrap.on('click', '.sh-csp-remove-tag', function(){
                $(this).closest('.sh-csp-manual-tag').remove();
            });

            // Manuel form toggle
            $('#sh-csp-manual-toggle').on('click', function(){
                var $form = $('#sh-csp-manual-form');
                var $list = $('#sh-csp-manual-list');
                $form.toggle();
                if ($form.is(':visible')) $list.show();
            });

            // Manuel domain ekle
            $('#sh-csp-add-btn').on('click', function(){
                var directive = $('#sh-csp-add-directive').val();
                var domain    = $.trim($('#sh-csp-add-domain').val());
                if (!domain) return;

                // Mevcut tag'lar arasında varsa ekleme
                var exists = false;
                $wrap.find('.sh-csp-manual-tag').each(function(){
                    if ($(this).data('directive') === directive && $(this).data('domain') === domain) {
                        exists = true; return false;
                    }
                });
                if (exists) { $('#sh-csp-add-domain').val('').focus(); return; }

                var $tag = $('<span class="sh-csp-manual-tag" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;padding:1px 7px;font-size:11px;font-family:monospace;display:inline-flex;align-items:center;gap:4px;margin:1px"></span>');
                $tag.data('directive', directive).attr('data-directive', directive);
                $tag.data('domain', domain).attr('data-domain', domain);
                $tag.html(document.createTextNode(domain));
                var $x = $('<span class="sh-csp-remove-tag" style="cursor:pointer;color:#9ca3af;font-size:10px;line-height:1" title="Kaldır">✕</span>');
                $tag.append($x);

                // O direktifin satırını bul veya yeni satır oluştur
                var $list = $('#sh-csp-manual-list');
                var $row = $list.find('[data-row-dir="' + directive + '"]');
                if (!$row.length) {
                    $row = $('<div data-row-dir="' + directive + '" style="margin-bottom:6px"></div>');
                    $row.append('<span style="font-size:11px;font-weight:700;color:#374151;font-family:monospace;margin-right:6px">' + $('<span>').text(directive).html() + '</span>');
                    $list.append($row);
                }
                $row.append($tag);
                $list.show();
                $('#sh-csp-add-domain').val('').focus();
            });
            // Enter key
            $('#sh-csp-add-domain').on('keydown', function(e){ if (e.which === 13) { e.preventDefault(); $('#sh-csp-add-btn').click(); } });

            // CSP Violation — "Ekle" butonu → detected_domains'e ekle
            $wrap.on('click', '.sh-csp-approve-violation', function(){
                var $btn      = $(this);
                var directive = $btn.data('directive');
                var domain    = $btn.data('domain');
                var $row      = $btn.closest('tr');

                $btn.prop('disabled', true).text('Ekleniyor...');
                $.post(ajax, {
                    action: 'sh_security_save',
                    nonce:  nonce,
                    section: 'csp',
                    data: { approve_violation: JSON.stringify({ directive: directive, domain: domain }) }
                }, function(res){
                    if (res.success) {
                        $row.fadeOut(300, function(){ $(this).remove(); });
                        // Saptanan domainler listesini de güncelle
                        var $dWrap = $('#sh-csp-detected-wrap');
                        var $group = $dWrap.find('[data-row-dir="' + directive + '"]');
                        if (!$group.length) {
                            $group = $('<div data-row-dir="' + directive + '" style="margin-bottom:8px"></div>');
                            $group.append('<span style="font-size:11px;font-weight:700;color:#374151;font-family:monospace;margin-right:6px">' + $('<span>').text(directive).html() + '</span>');
                            $dWrap.append($group);
                        }
                        var $tag = $('<span style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:1px 7px;font-size:11px;font-family:monospace;color:#166534;display:inline-block;margin:1px"></span>');
                        $tag.text(domain);
                        $group.append($tag);
                    } else {
                        $btn.prop('disabled', false).text('+ Ekle');
                    }
                });
            });

            // Violation listesini temizle
            $('#sh-csp-violations-clear').on('click', function(){
                if (!confirm('Tüm ihlal raporları temizlensin mi?')) return;
                $.post(ajax, { action: 'sh_security_save', nonce: nonce, section: 'csp', data: { clear_violations: '1' } }, function(res){
                    if (res.success) location.reload();
                });
            });

            // CSP Scan — saptanan domain'leri yenile
            $('#sh-csp-scan-btn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Taranıyor...');
                $.post(ajax, { action: 'sh_security_csp_scan', nonce: nonce }, function(res){
                    $btn.prop('disabled', false).text('Yenile');
                    if (!res.success) return;
                    var domains = res.data.domains;
                    var $dWrap  = $('#sh-csp-detected-wrap');
                    $dWrap.empty();
                    if (!domains || !Object.keys(domains).length) {
                        $dWrap.html('<p style="font-size:12px;color:#9ca3af;margin:0">Henüz saptanan domain yok.</p>');
                        return;
                    }
                    $.each(domains, function(directive, dlist){
                        var $group = $('<div style="margin-bottom:10px"></div>');
                        $group.append('<div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;font-family:monospace">' + $('<span>').text(directive).html() + '</div>');
                        var $tags = $('<div style="display:flex;flex-wrap:wrap;gap:4px"></div>');
                        $.each(dlist, function(i, domain){
                            var $ta = $wrap.find('.sh-security-csp-textarea[data-directive="' + directive + '"]');
                            var approved = $ta.length ? $ta.val().split('\n').map(function(l){return l.trim();}).filter(Boolean) : [];
                            var isOk = approved.indexOf(domain) !== -1;
                            var $t = $('<span class="sh-csp-domain-tag" style="background:#eff6ff;border:1px solid ' + (isOk?'#86efac':'#bfdbfe') + ';border-radius:4px;padding:2px 8px;font-size:11px;font-family:monospace;cursor:pointer;display:inline-flex;align-items:center;gap:4px"></span>');
                            $t.attr({'data-directive':directive,'data-domain':domain});
                            $t.html($('<span>').text(domain).html() + ' <span style="color:' + (isOk?'#15803d':'#9ca3af') + ';font-size:10px">' + (isOk?'✓':'+') + '</span>');
                            $tags.append($t);
                        });
                        $group.append($tags);
                        $dWrap.append($group);
                    });
                });
            });

            // WP Hide apply
            $('#sh-wph-apply-btn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Uygulanıyor...');
                $.post(ajax, { action: 'sh_security_wph', nonce: nonce }, function(res){
                    $btn.prop('disabled', false).text('Apply Default Settings');
                    var $msg = $('#sh-wph-msg');
                    $msg.text(res.success ? '✓ ' + res.data : '✗ ' + (res.data || 'Hata')).css('color', res.success ? '#15803d' : '#dc2626').show();
                    setTimeout(function(){ $msg.hide(); }, 4000);
                });
            });

        })(jQuery);
        </script>
        <?php
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private static function renderToggle( string $section, string $key, string $label, string $desc, bool $checked ): void
    {
        ?>
        <div class="sh-security-toggle-card">
            <label class="sh-ps-toggle" style="margin-top:2px">
                <input type="checkbox" class="sh-security-toggle"
                    data-section="<?php echo esc_attr( $section ); ?>"
                    data-key="<?php echo esc_attr( $key ); ?>"
                    <?php checked( $checked ); ?>>
                <span class="sh-ps-toggle-slider"></span>
            </label>
            <div>
                <span class="sh-security-card-label"><?php echo esc_html( $label ); ?></span>
                <span class="sh-security-card-desc"><?php echo esc_html( $desc ); ?></span>
            </div>
        </div>
        <?php
    }
}
