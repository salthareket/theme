<?php

namespace SaltHareket\Notifications\Carriers;

use Pelago\Emogrifier\CssInliner;
use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyResult;
use SaltHareket\Notifications\Carriers\Email\EmailManager;
use SaltHareket\Notifications\Carriers\Email\EmailSettings;

/**
 * EmailCarrier
 * EmailManager üzerinden email gönderir.
 * mode = 'wp'     → wp_mail() (plugin varsa onun üzerinden)
 * mode = 'custom' → SmtpProvider veya API provider
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-14 — EmailManager entegrasyonu
 *   1.1.0 - 2026-05-04 — Initial release
 */
class EmailCarrier implements NotifyCarrier
{
    private string $htmlPath;
    private string $cssPath;

    public function __construct()
    {
        $theme_dir      = get_stylesheet_directory();
        $this->htmlPath = $theme_dir . '/theme/templates/notifications/events/';
        $this->cssPath  = $theme_dir . '/static/css/email.css';
    }

    public function channel(): string
    {
        return 'email';
    }

    public function handle( NotifyPayload $payload ): NotifyResult
    {
        $config  = $payload->getChannelConfig();
        $subject = $payload->rendered_subject;
        $body    = $payload->rendered_body;

        if ( empty( $body ) ) {
            return NotifyResult::fail( 'email', 'Empty email body' );
        }

        $user = get_userdata( $payload->receiver_id );
        if ( ! $user || empty( $user->user_email ) ) {
            return NotifyResult::fail( 'email', 'No email address for user #' . $payload->receiver_id );
        }

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        // BCC modu
        $bcc_type = $config['type'] ?? '';
        $to       = $user->user_email;
        if ( ! empty( $bcc_type ) ) {
            $settings   = EmailSettings::get();
            $from_email = $settings['smtp']['from_email'] ?? get_option( 'admin_email' );
            $to         = $from_email;
            $headers[]  = $bcc_type . ': ' . $user->user_email;
        }

        $result = EmailManager::send( $to, $subject, $body, $headers );

        if ( ! empty( $result['error'] ) ) {
            return NotifyResult::fail( 'email', $result['message'] ?? 'Send failed' );
        }

        return NotifyResult::ok( 'email' );
    }

    /**
     * HTML email template'ini yükle, CSS inline et, cache'le.
     */
    public function loadTemplate( string $event_key ): string
    {
        $slug          = str_replace( '/', '-', $event_key );
        $parsed_path   = $this->htmlPath . $slug . '-parsed.html';
        $template_path = $this->htmlPath . $slug . '.html';

        if ( file_exists( $parsed_path ) ) {
            return (string) file_get_contents( $parsed_path );
        }

        if ( ! file_exists( $template_path ) ) return '';

        $html = (string) file_get_contents( $template_path );

        if ( file_exists( $this->cssPath ) ) {
            $css      = (string) file_get_contents( $this->cssPath );
            $rendered = CssInliner::fromHtml( $html )->inlineCss( $css )->render();
        } else {
            $rendered = $html;
        }

        $rendered = str_replace( [ '%7B%7B', '%7D%7D' ], [ '{{', '}}' ], $rendered );

        if ( ! is_dir( $this->htmlPath ) ) {
            wp_mkdir_p( $this->htmlPath );
        }
        file_put_contents( $parsed_path, $rendered );

        return $rendered;
    }

    public function clearTemplateCache( string $event_key ): void
    {
        $slug        = str_replace( '/', '-', $event_key );
        $parsed_path = $this->htmlPath . $slug . '-parsed.html';
        if ( file_exists( $parsed_path ) ) {
            unlink( $parsed_path );
        }
    }

    public function logMailerError( \WP_Error $error ): void
    {
        @file_put_contents(
            ABSPATH . '/mail.log',
            '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $error->get_error_message() . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

/**
 * EmailCarrier
 * wp_mail ile email gönderir. Emogrifier ile CSS inline eder.
 * BCC modu destekler. From header WP filter ile güvenilir şekilde set edilir.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-04
 *     - Fix: From header wp_mail_from/wp_mail_from_name filter ile set edilir
 *     - Fix: BCC modunda to boş bırakılmıyor, admin email kullanılıyor
 *     - Fix: phpmailer_init hook desteği eklendi
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * NotifyEvent::define('order/new', [
 *     'channels' => ['email'],
 *     'email'    => [
 *         'subject' => 'New order: {{ data.post.title }}',
 *         'body'    => 'template',
 *     ],
 * ]);
 *
 * // BCC modu — recipient {{users}} ise otomatik BCC
 * NotifyEvent::define('promo/flash', [
 *     'recipient' => '{{users}}',
 *     'email'     => ['subject' => 'Flash Sale!', 'body' => 'template'],
 * ]);
 *
 * // Mailer hata logu
 * add_action('wp_mail_failed', [new EmailCarrier(), 'logMailerError']);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // body = 'template' → HTML dosyasını yükler, CSS inline eder, cache'ler
 *   // Dosya: {theme}/theme/templates/notifications/events/{event-slug}.html
 *
 * @example
 *   // body = Twig string → direkt render edilir
 *   'body' => 'Hello {{ data.user.name }}'
 *
 * @example
 *   // CSS: {theme}/static/css/email.css — Emogrifier ile inline edilir
 *
 * @example
 *   add_action('wp_mail_failed', [new EmailCarrier(), 'logMailerError']);
 *
 * @example
 *   $result = $carrier->handle($payload);
 *   $result->success; // true/false
 */
