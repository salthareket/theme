<?php

namespace SaltHareket\DownloadLog;

use SaltHareket\DownloadLog\Concerns\HandlesLog;
use SaltHareket\DownloadLog\Concerns\HandlesToken;
use SaltHareket\DownloadLog\Concerns\HandlesProtection;
use SaltHareket\DownloadLog\Concerns\HandlesLeadCapture;
use SaltHareket\DownloadLog\Concerns\HandlesStream;

/**
 * DownloadManager
 *
 * Ana facade — download butonu render, token üretimi, erişim kontrolü.
 * Twig'den sh_download(), PHP'den DownloadManager::renderButton() ile kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-12 — Initial release
 *     - Add: renderButton() — token tabanlı download butonu HTML
 *     - Add: Trait'ler: HandlesLog, HandlesToken, HandlesProtection, HandlesLeadCapture, HandlesStream
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Twig (native syntax — function() wrapper yok):
 * {{ sh_download(post.brochure_file) }}
 * {{ sh_download(post.brochure_file, {'mode': 'lead_capture'}) }}
 * {{ sh_download(post.brochure_file, {'mode': 'login_required', 'label': 'PDF İndir', 'class': 'btn btn-primary'}) }}
 *
 * // PHP:
 * echo DownloadManager::renderButton(42);
 * echo DownloadManager::renderButton(42, ['mode' => 'lead_capture']);
 *
 * // Download sayısı:
 * {{ sh_download_count(post.brochure_file) }}
 * DownloadManager::getCount(42);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Public — direkt indir, sadece logla:
 *   {{ sh_download(post.pdf_file) }}
 *
 * @example
 *   // Login required — giriş yoksa login'e yönlendir:
 *   {{ sh_download(post.pdf_file, {'mode': 'login_required', 'label': 'Üye Girişi Gerekli'}) }}
 *
 * @example
 *   // Lead capture — modal açılır, CF7 form doldurulur, cookie yazılır:
 *   {{ sh_download(post.pdf_file, {'mode': 'lead_capture'}) }}
 *
 * @example
 *   // Rule'dan otomatik mod — source_post'a göre rule resolver çalışır:
 *   {{ sh_download(post.pdf_file) }}
 *
 * @example
 *   // Override — rule'u bypass et:
 *   {{ sh_download(post.pdf_file, {'mode': 'public'}) }}
 *
 * @package SaltHareket\DownloadLog
 */
class DownloadManager {

    use HandlesLog;
    use HandlesToken;
    use HandlesProtection;
    use HandlesLeadCapture;
    use HandlesStream;

    // ─── RENDER ATTRS ────────────────────────────────────

    /**
     * Sadece data attribute string'i döndür — custom element'ler için.
     * Gerçek dosya URL'i yok, JS tıklanınca token alır.
     *
     * Twig:
     *   <a href="#" {{ sh_download_attrs(post.brochure_file)|raw }}>PDF İndir</a>
     *   <video {{ sh_download_attrs(post.video_file)|raw }}>...</video>
     *   <div class="my-card" {{ sh_download_attrs(post.file)|raw }}>...</div>
     *
     * PHP:
     *   <a href="#" <?= DownloadManager::renderAttrs(42) ?>>İndir</a>
     *
     * @param int   $file_id
     * @param array $options  mode, source_post (label ve class yok — senin element'in)
     * @return string  'class="sh-download-btn [extra]" data-file-id="..." ...'
     */
    public static function renderAttrs( int $file_id, array $options = [] ): string {
        if ( $file_id < 1 ) return '';

        $source_post = (int) ( $options['source_post'] ?? 0 );
        if ( ! $source_post ) {
            global $post;
            $source_post = $post ? (int) $post->ID : 0;
        }

        $access = self::checkAccess( $file_id, $source_post, $options );

        // class — sadece girilmişse ekle (sh-download-btn artık zorunlu değil, JS data-file-id'ye bakıyor)
        $class_attr = '';
        if ( isset( $options['class'] ) ) {
            $class_attr = 'class="' . esc_attr( $options['class'] ) . '" ';
        }

        // data attrs — data-sh-download her zaman eklenir (JS selector)
        $attrs = sprintf(
            '%sdata-sh-download data-file-id="%d" data-source-post="%d" data-mode="%s" data-access="%s"',
            $class_attr,
            $file_id,
            $source_post,
            esc_attr( $access['mode'] ),
            esc_attr( $access['status'] )
        );

        if ( $access['mode'] === 'lead_capture' && $access['form_id'] ) {
            $attrs .= sprintf( ' data-form-id="%d"', $access['form_id'] );
        }

        if ( $access['status'] === 'login_required' ) {
            $attrs .= sprintf( ' data-login-url="%s"', esc_url( $access['login_url'] ) );
        }

        // label sadece girilmişse ekle — aria-label için
        if ( ! empty( $options['label'] ) ) {
            $attrs .= sprintf( ' aria-label="%s"', esc_attr( $options['label'] ) );
        }

        return $attrs;
    }

    // ─── RENDER BUTTON ───────────────────────────────────

    /**
     * Download butonu HTML'i üret.
     * Gerçek dosya URL'i HTML'e yazılmaz — token tabanlı.
     *
     * @param int   $file_id   WP attachment ID
     * @param array $options {
     *   @type string $mode        public|login_required|lead_capture (override — yoksa rule'dan gelir)
     *   @type string $label       Buton metni (default: dosya adı)
     *   @type string $class       Ekstra CSS class
     *   @type string $icon        FA icon class (default: fa-download)
     *   @type int    $source_post Hangi post'ta olduğu (rule resolver için, yoksa global $post)
     * }
     * @return string  HTML
     */
    public static function renderButton( int $file_id, array $options = [] ): string {
        if ( $file_id < 1 ) return '';

        // Source post — rule resolver için
        $source_post = (int) ( $options['source_post'] ?? 0 );
        if ( ! $source_post ) {
            global $post;
            $source_post = $post ? (int) $post->ID : 0;
        }

        // Erişim kontrolü — file bilgisi olmasa da çalışır
        $access = self::checkAccess( $file_id, $source_post, $options );

        // Dosya bilgisi — sadece label için, yoksa fallback
        $file      = self::getFileInfo( $file_id );
        $file_name = $file ? $file['name'] : 'file-' . $file_id;
        $file_size = $file ? $file['size'] : 0;

        // Buton label
        $label = sanitize_text_field( $options['label'] ?? '' );
        if ( ! $label ) {
            $label = function_exists( 'trans' ) ? trans( 'Download' ) : 'Download';
        }

        // CSS class
        $extra_class = esc_attr( $options['class'] ?? '' );
        $btn_class   = trim( 'sh-download-btn ' . $extra_class );

        // Icon
        $icon_class = esc_attr( $options['icon'] ?? 'fa-download' );
        $icon_html  = '<i class="fa ' . $icon_class . '" aria-hidden="true"></i> ';

        // Data attributes — gerçek URL yok, sadece file_id + source_post
        // data-sh-download: JS selector (data-file-id generic olduğu için çakışma riski var)
        $data = sprintf(
            'data-sh-download data-file-id="%d" data-source-post="%d" data-mode="%s" data-access="%s"',
            $file_id,
            $source_post,
            esc_attr( $access['mode'] ),
            esc_attr( $access['status'] )
        );

        // Lead capture modunda form_id ekle
        if ( $access['mode'] === 'lead_capture' && $access['form_id'] ) {
            $data .= sprintf( ' data-form-id="%d"', $access['form_id'] );
        }

        // Login required modunda login URL ekle
        if ( $access['status'] === 'login_required' ) {
            $data .= sprintf( ' data-login-url="%s"', esc_url( $access['login_url'] ) );
        }

        // Dosya boyutu (opsiyonel gösterim)
        $size_html = '';
        if ( ! empty( $options['show_size'] ) && $file_size > 0 ) {
            $size_html = ' <small class="sh-download-size">(' . size_format( $file_size ) . ')</small>';
        }

        return sprintf(
            '<button type="button" class="%s" %s aria-label="%s">%s%s</button>%s',
            esc_attr( $btn_class ),
            $data,
            esc_attr( $label ),
            $icon_html,
            esc_html( $label ),
            $size_html
        );
    }
}
