<?php

/**
 * Download Log — Bootstrap
 *
 * Tüm sınıfları yükler, WP hook'larını kaydeder, Twig helper'larını,
 * AJAX handler'larını ve download endpoint'ini tanımlar.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *     - Add: GuestIdentity — sh_guest_id cookie, anonymous user tracking
 *     - Add: DownloadRules — repeater-based rule sistemi (post/term/post_type/global scope)
 *     - Add: HandlesLog — wp_download_log DB tablosu
 *     - Add: HandlesToken — signed token üret/doğrula/tek kullanım
 *     - Add: HandlesProtection — login/lead kontrolü, rule resolver
 *     - Add: HandlesLeadCapture — CF7 entegrasyonu, lead cookie, field mapping
 *     - Add: HandlesStream — dosya stream, Content-Disposition header
 *     - Add: DownloadManager — ana facade
 *     - Add: DownloadLogAdmin — admin UI (4 tab: Log | Rules | Settings | Analytics)
 *     - Add: DownloadLogAjax — AJAX handlers
 *     - Add: Twig function sh_download()
 *     - Add: WPML/Polylang/qTranslate-XT uyumu
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // variables.php'de:
 * include_once SH_INCLUDES_PATH . 'apps/download-log/bootstrap.php';
 *
 * // Twig'de:
 * {{ sh_download(post.brochure_file) }}
 * {{ sh_download(post.brochure_file, {'mode': 'lead_capture', 'label': 'PDF İndir'}) }}
 *
 * // PHP'de:
 * echo DownloadManager::renderButton($attachment_id);
 * echo DownloadManager::renderButton($attachment_id, ['mode' => 'login_required']);
 *
 * ──────────────────────────────────────────────────────────
 */

// ─── AUTOLOAD ────────────────────────────────────────────

require_once __DIR__ . '/DownloadRules.php';
require_once __DIR__ . '/Concerns/HandlesLog.php';
require_once __DIR__ . '/Concerns/HandlesToken.php';
require_once __DIR__ . '/Concerns/HandlesProtection.php';
require_once __DIR__ . '/Concerns/HandlesLeadCapture.php';
require_once __DIR__ . '/Concerns/HandlesStream.php';
require_once __DIR__ . '/DownloadManager.php';

// ─── ADMIN ───────────────────────────────────────────────

require_once __DIR__ . '/Admin/DownloadLogAdmin.php';
require_once __DIR__ . '/Admin/DownloadLogAjax.php';

add_action( 'admin_menu', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'addMenuPage' ], 25 );
add_action( 'admin_head', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'hideNotices' ] );
add_action( 'admin_enqueue_scripts', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'enqueueAssets' ] );
add_action( 'admin_post_sh_download_save_settings', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'saveSettings' ] );
add_action( 'wp_ajax_sh_download_clear_logs', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'clearLogs' ] );

// ─── TURBO API — FRONTEND ────────────────────────────────
// Frontend download işlemleri sistemin TurboAPI'si üzerinden çalışır.
// site.com/api/download_request  → token al, erişim kontrol
// site.com/api/download_lead     → lead form submit, cookie yaz, token al
// site.com/api/download_stream   → dosya stream (token doğrula, indir)

add_filter( 'turbo_api_handle', function ( $handled, string $method, array $vars ) {
    if ( $handled !== null ) return $handled;

    switch ( $method ) {

        case 'download_request':
            return \SaltHareket\DownloadLog\Admin\DownloadLogAjax::handleDownloadRequest( $vars );

        case 'download_lead':
            return \SaltHareket\DownloadLog\Admin\DownloadLogAjax::handleLeadCapture( $vars );

        case 'download_stream':
            // Token URL query param'dan gelir: site.com/api/download_stream?token=xxx
            // TurboAPI vars içinde de olabilir
            if ( empty( $vars['token'] ) && ! empty( $_GET['token'] ) ) {
                $vars['token'] = sanitize_text_field( $_GET['token'] );
            }
            \SaltHareket\DownloadLog\DownloadManager::stream( $vars['token'] ?? '' );
            return []; // stream() exit yapar, buraya gelmez

    }

    return null;
}, 10, 3 );

// ─── AJAX — ADMIN ONLY ───────────────────────────────────
// Admin panel işlemleri wp_ajax ile kalır (admin-ajax.php — sadece admin kullanır)

// Admin: post/term autocomplete
add_action( 'wp_ajax_sh_download_search_posts', [ \SaltHareket\DownloadLog\Admin\DownloadLogAjax::class, 'searchPosts' ] );
add_action( 'wp_ajax_sh_download_search_terms', [ \SaltHareket\DownloadLog\Admin\DownloadLogAjax::class, 'searchTerms' ] );

// Admin: rule save/delete
add_action( 'wp_ajax_sh_download_save_rule',   [ \SaltHareket\DownloadLog\Admin\DownloadLogAjax::class, 'saveRule' ] );
add_action( 'wp_ajax_sh_download_delete_rule', [ \SaltHareket\DownloadLog\Admin\DownloadLogAjax::class, 'deleteRule' ] );

// Admin: user history modal
add_action( 'wp_ajax_sh_download_user_history', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'ajaxUserHistory' ] );

// Admin: top tables AJAX refresh
add_action( 'wp_ajax_sh_download_top_files',       [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'ajaxTopFiles' ] );
add_action( 'wp_ajax_sh_download_top_downloaders', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'ajaxTopDownloaders' ] );

// Admin: per-user export
add_action( 'admin_post_sh_download_export_user', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'handleUserExport' ] );

// Admin: settings save
add_action( 'admin_post_sh_download_save_settings', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'saveSettings' ] );

// ─── DB INSTALL ──────────────────────────────────────────

add_action( 'admin_init', function () {
    \SaltHareket\DownloadLog\DownloadManager::createTable();
}, 5 );

// ─── DOWNLOAD STREAM ─────────────────────────────────────
// Dosya stream — TurboAPI üzerinden: site.com/api/download_stream?token=xxx
// Token doğrula → erişim kontrol → PHP readfile → gerçek URL hiç frontend'e yazılmaz
// wp_ajax_sh_download geriye uyumluluk için de kalır

add_action( 'wp_ajax_sh_download',        [ \SaltHareket\DownloadLog\DownloadManager::class, 'stream' ] );
add_action( 'wp_ajax_nopriv_sh_download', [ \SaltHareket\DownloadLog\DownloadManager::class, 'stream' ] );

// ─── FRONTEND ASSETS ─────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
    // sh-modal.js — ortak modal utility
    $modal_path = __DIR__ . '/sh-modal.js';
    $modal_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/download-log/sh-modal.js';
    if ( file_exists( $modal_path ) ) {
        wp_enqueue_script( 'sh-modal', $modal_url, [], filemtime( $modal_path ), true );
    }

    // download-log.js — sh-modal'a depend eder
    $js_path = __DIR__ . '/download-log.js';
    $js_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/download-log/download-log.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script( 'sh-download-log', $js_url, [ 'jquery', 'sh-modal' ], filemtime( $js_path ), true );
        wp_localize_script( 'sh-download-log', 'shDownloadLog', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sh_download' ),
            'api_url' => trailingslashit( home_url() ) . 'api/',
            'strings' => [
                'downloading'    => function_exists( 'trans' ) ? trans( 'Downloading...' ) : 'Downloading...',
                'error'          => function_exists( 'trans' ) ? trans( 'Download failed.' ) : 'Download failed.',
                'login_required' => function_exists( 'trans' ) ? trans( 'Please login to download.' ) : 'Please login to download.',
                'download'       => function_exists( 'trans' ) ? trans( 'Download' ) : 'Download',
            ],
        ] );
    }
} );

// ─── TWIG HELPERS ────────────────────────────────────────

add_filter( 'timber/twig', function ( \Twig\Environment $twig ) {

    /**
     * Download butonu render et.
     *
     * @example {{ sh_download(post.brochure_file) }}
     * @example {{ sh_download(post.brochure_file, {'mode': 'lead_capture', 'label': 'PDF İndir'}) }}
     * @example {{ sh_download(123, {'mode': 'login_required', 'class': 'btn btn-primary'}) }}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'sh_download',
        function ( $file_id, array $options = [] ): string {
            return \SaltHareket\DownloadLog\DownloadManager::renderButton( (int) $file_id, $options );
        },
        [ 'is_safe' => [ 'html' ] ]
    ) );

    /**
     * Sadece data attribute'larını döndür — custom element'ler için.
     *
     * @example <a href="#" {{ sh_download_attrs(post.brochure_file)|raw }}>PDF İndir</a>
     * @example <video {{ sh_download_attrs(post.video_file)|raw }} controls>...</video>
     * @example <div class="card" {{ sh_download_attrs(post.file, {'mode': 'lead_capture'})|raw }}>...</div>
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'sh_download_attrs',
        function ( $file_id, array $options = [] ): string {
            return \SaltHareket\DownloadLog\DownloadManager::renderAttrs( (int) $file_id, $options );
        },
        [ 'is_safe' => [ 'html' ] ]
    ) );

    /**
     * Download sayısını döndür.
     *
     * @example {{ sh_download_count(post.brochure_file) }}
     */
    $twig->addFunction( new \Twig\TwigFunction(
        'sh_download_count',
        function ( $file_id ): int {
            return \SaltHareket\DownloadLog\DownloadManager::getCount( (int) $file_id );
        }
    ) );

    return $twig;
} );

// ─── GLOBAL PHP HELPERS ──────────────────────────────────

if ( ! function_exists( 'sh_download' ) ) {
    /**
     * @example sh_download(42);
     * @example sh_download(42, ['mode' => 'lead_capture']);
     */
    function sh_download( int $file_id, array $options = [] ): void {
        echo \SaltHareket\DownloadLog\DownloadManager::renderButton( $file_id, $options ); // phpcs:ignore
    }
}

if ( ! function_exists( 'sh_download_attrs' ) ) {
    /**
     * Sadece data attribute string'i döndür — custom element'ler için.
     * @example <a href="#" <?= sh_download_attrs(42) ?>>İndir</a>
     * @example <img src="..." <?= sh_download_attrs(42, ['mode' => 'lead_capture']) ?> alt="...">
     */
    function sh_download_attrs( int $file_id, array $options = [] ): string {
        return \SaltHareket\DownloadLog\DownloadManager::renderAttrs( $file_id, $options );
    }
}

if ( ! function_exists( 'sh_download_count' ) ) {
    function sh_download_count( int $file_id ): int {
        return \SaltHareket\DownloadLog\DownloadManager::getCount( $file_id );
    }
}

// ─── CF7 MAIL INTERCEPT ──────────────────────────────────
// Lead capture modunda kullanılan CF7 formları mail göndermemeli.
// wpcf7_before_send_mail filter ile submission abort edilir.
// status: 'aborted' döner → wpcf7submit event tetiklenir → JS data'yı yakalar.
// CF7'nin "gönderim iptal edildi" mesajı wpcf7_form_response_output ile gizlenir.
// Validation hataları (invalid_fields) etkilenmez — normal çalışır.

add_filter( 'wpcf7_before_send_mail', function ( \WPCF7_ContactForm $contact_form, bool &$abort, \WPCF7_Submission $submission ): \WPCF7_ContactForm {
    $form_id = $contact_form->id();

    $rules = \SaltHareket\DownloadLog\DownloadRules::getRules();
    foreach ( $rules as $rule ) {
        if (
            ( $rule['mode']    ?? '' ) === 'lead_capture' &&
            (int) ( $rule['form_id'] ?? 0 ) === $form_id
        ) {
            $abort = true;
            return $contact_form;
        }
    }

    return $contact_form;
}, 10, 3 );

// CF7 "aborted" mesajını gizle — sadece lead capture formlarında
add_filter( 'wpcf7_form_response_output', function ( string $output, string $class, string $content, \WPCF7_ContactForm $contact_form ): string {
    // Validation hatası varsa dokunma — hata mesajları görünsün
    if ( strpos( $class, 'invalid' ) !== false || strpos( $class, 'unaccepted' ) !== false || strpos( $class, 'spam' ) !== false ) {
        return $output;
    }

    $form_id = $contact_form->id();
    $rules   = \SaltHareket\DownloadLog\DownloadRules::getRules();

    foreach ( $rules as $rule ) {
        if (
            ( $rule['mode']    ?? '' ) === 'lead_capture' &&
            (int) ( $rule['form_id'] ?? 0 ) === $form_id
        ) {
            return ''; // mesajı tamamen gizle
        }
    }

    return $output;
}, 10, 4 );

add_filter( 'sh_notify_events', function ( array $events ): array {
    $events[] = [
        'slug'        => 'file-downloaded',
        'title'       => 'File Downloaded',
        'description' => 'Bir dosya indirildiğinde tetiklenir.',
        'group'       => 'Downloads',
    ];
    $events[] = [
        'slug'        => 'lead-captured',
        'title'       => 'Lead Captured',
        'description' => 'Download lead formu doldurulduğunda tetiklenir.',
        'group'       => 'Downloads',
    ];
    return $events;
} );

// ─── CRON ────────────────────────────────────────────────

add_filter( 'cron_schedules', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'registerCronSchedules' ] );
add_action( 'sh_download_report_cron', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'sendScheduledReport' ] );

// Export handler — admin-post.php üzerinden çalışır
add_action( 'admin_post_sh_download_export', [ \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::class, 'handleExport' ] );

// Test report
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['test_report'] ) ) return;
    if ( ! check_admin_referer( 'sh_dl_test_report' ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    \SaltHareket\DownloadLog\Admin\DownloadLogAdmin::sendScheduledReport();
    wp_redirect( add_query_arg( [ 'page' => 'salt-download-log', 'tab' => 'settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
} );
