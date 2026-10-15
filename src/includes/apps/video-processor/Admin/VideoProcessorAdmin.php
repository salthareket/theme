<?php

namespace SaltHareket\VideoProcessor\Admin;

use SaltHareket\VideoProcessor\VideoProcessor;
use SaltHareket\VideoProcessor\VideoProcessorQueue;
use SaltHareket\VideoProcessor\VideoProcessorSettings;
use SaltHareket\VideoProcessor\VideoProcessorLog;

/**
 * VideoProcessorAdmin — Video Processor yönetim sayfası.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-19 — Initial release
 *
 * @package SaltHareket\VideoProcessor\Admin
 */
class VideoProcessorAdmin
{
    public static function register(): void
    {
        // add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 26 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        // Media Library butonu
        add_filter( 'media_row_actions',     [ self::class, 'addMediaLibraryButton' ], 10, 2 );
        // AJAX handler'lar
        add_action( 'wp_ajax_vp_optimize_single', [ self::class, 'ajaxOptimizeSingle' ] );
        add_action( 'wp_ajax_vp_queue_status',    [ self::class, 'ajaxQueueStatus' ] );
        add_action( 'wp_ajax_vp_save_settings',   [ self::class, 'ajaxSaveSettings' ] );
        add_action( 'wp_ajax_vp_reprocess',       [ self::class, 'ajaxReprocess' ] );
        add_action( 'wp_ajax_vp_process_all',     [ self::class, 'ajaxProcessAll' ] );
        add_action( 'wp_ajax_vp_clear_log',       [ self::class, 'ajaxClearLog' ] );
        // FFmpeg kurulum (update.php'den taşındı)
        add_action( 'wp_ajax_install_ffmpeg_vp',  [ self::class, 'ajaxInstallFfmpeg' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '🎬 Video Processor',
            '🎬 Video Processor',
            'manage_options',
            'sh-video-processor',
            [ self::class, 'renderPage' ]
        );
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-video-processor' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-video-processor' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    // ─── ANA SAYFA ────────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetkisiz erişim' );
        $tab       = sanitize_key( $_GET['tab'] ?? 'queue' );
        $nonce     = wp_create_nonce( 'vp_nonce' );
        $ajax_url  = esc_url( admin_url( 'admin-ajax.php' ) );
        $enabled   = defined('ENABLE_VIDEO_PROCESSOR') && ENABLE_VIDEO_PROCESSOR;
        $available = VideoProcessor::isAvailable();
        $supported = VideoProcessor::isSupported();
        ?>
        <div class="sh-wrap" id="sh-vp-page">

        <div class="sh-toolbar">
            <h1 style="display:inline-flex;align-items:center;gap:8px;">&#127916; Video Processor
            </h1>
            <div class="sh-toolbar-right">
                <a href="?page=sh-video-processor&tab=queue"    class="sh-tab-btn <?php echo $tab==='queue'   ?'active':''; ?>">⏳ Kuyruk</a>
                <a href="?page=sh-video-processor&tab=videos"   class="sh-tab-btn <?php echo $tab==='videos'  ?'active':''; ?>">🎥 Videolar</a>
                <a href="?page=sh-video-processor&tab=settings" class="sh-tab-btn <?php echo $tab==='settings'?'active':''; ?>">⚙️ Ayarlar</a>
            </div>
        </div>

        <?php if ( $supported && ! $available ) : ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;max-width:900px">
            <div style="flex:1">
                <strong style="color:#991b1b">FFmpeg kurulu değil</strong>
                <span style="font-size:13px;color:#7f1d1d;margin-left:8px">Video işleme için FFmpeg gereklidir. Lütfen kurun.</span>
            </div>
            <span id="vp-ffmpeg-status" style="font-size:12px;color:#666"></span>
            <button class="sh-btn" onclick="shVpInstallFfmpeg()"
                    style="background:#dc2626;color:#fff;border-color:#dc2626;white-space:nowrap">
                FFmpeg Kur (<?php echo stristr(PHP_OS,'WIN')?'Windows':'Linux'; ?>)
            </button>
        </div>
        <?php endif; ?>
        <?php
        if ( $tab === 'videos' )       self::renderVideosTab( $nonce, $ajax_url );
        elseif ( $tab === 'settings' ) self::renderSettingsTab( $nonce, $ajax_url );
        else                           self::renderQueueTab( $nonce, $ajax_url );
        ?>
        <div id="sh-toast"></div>
        </div>
        <?php
        self::renderToastScript( $nonce, $ajax_url );
    }

    // ─── KUYRUK SEKMESİ ───────────────────────────────────────────────────────

    private static function renderQueueTab( string $nonce, string $ajax_url ): void
    {
        $status = VideoProcessorQueue::getStatus();
        $queue  = get_option( VideoProcessorQueue::OPTION_QUEUE, [] );

        $task_labels = [
            'resize_720' => '720p yeniden boyutlandır',
            'encode_480' => '480p tablet versiyonu',
            'encode_360' => '360p telefon versiyonu',
            'poster'     => 'Poster frame',
            'sprite_vtt' => 'Thumbnail sprite + VTT',
        ];
        ?>
        <div class="sh-layout"><div class="sh-main">

        <?php if ( $status['is_running'] || ! empty( $queue ) ) : ?>
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">İşlem Durumu</h3></div>
            <div class="sh-card-body">
                <div id="vp-progress-bar-wrap" style="background:#f3f4f6;border-radius:8px;overflow:hidden;height:24px;margin-bottom:12px">
                    <div id="vp-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width .3s;display:flex;align-items:center;padding:0 8px;color:#fff;font-size:12px"></div>
                </div>
                <p id="vp-queue-info" style="font-size:13px;color:#6b7280;margin:0">
                    Kuyrukta <strong><?php echo count( $queue ); ?></strong> video bekliyor
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="sh-card">
            <div class="sh-card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Kuyruk</h3>
                <button class="sh-btn sh-btn-primary" onclick="shVpProcessAll()">▶ Tümünü İşle</button>
            </div>
            <div class="sh-card-body">
            <?php if ( empty( $queue ) ) : ?>
                <div style="text-align:center;padding:40px;color:#9ca3af">
                    <p style="font-size:32px;margin:0 0 8px">✅</p>
                    <p style="margin:0">Kuyruk boş, tüm videolar işlendi.</p>
                </div>
            <?php else : ?>
                <?php foreach ( $queue as $idx => $job ) :
                    $id         = (int) ( $job['attachment_id'] ?? 0 );
                    $filename   = basename( get_attached_file( $id ) ?? '' );
                    $job_tasks  = (array) ( $job['tasks'] ?? [] );
                    $is_current = $status['is_running'] && $idx === 0;
                    $log        = VideoProcessorLog::get( $id );
                    // Log'dan tamamlanan task'ları çıkar
                    $completed_tasks = [];
                    foreach ( $log as $entry ) {
                        if ( $entry['result'] === 'success' ) $completed_tasks[] = $entry['task'];
                    }
                ?>
                <div style="padding:14px;border-bottom:1px solid #f3f4f6;<?php echo $is_current ? 'background:#f0f6ff;' : ''; ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div>
                            <strong style="font-size:13px"><?php echo esc_html( $filename ?: "ID: {$id}" ); ?></strong>
                            <?php if ( $is_current ) : ?>
                            <span style="margin-left:8px;font-size:11px;color:#2271b1;font-weight:600">
                                <span style="display:inline-block;width:8px;height:8px;background:#2271b1;border-radius:50%;animation:vp-pulse 1s infinite;margin-right:4px"></span>
                                İşleniyor...
                            </span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:11px;color:#9ca3af">#<?php echo $id; ?></span>
                    </div>
                    <!-- Task listesi -->
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                        <?php foreach ( $job_tasks as $task ) :
                            $done    = in_array( $task, $completed_tasks, true );
                            $is_next = $is_current && ! $done && ! array_filter( $job_tasks, fn($t) => $t !== $task && ! in_array($t, $completed_tasks) && array_search($t, $job_tasks) < array_search($task, $job_tasks) );
                            $label   = $task_labels[$task] ?? $task;
                            if ( $done ) {
                                $icon = '✅'; $color = '#00a32a'; $bg = '#dcfce7';
                            } elseif ( $is_current && count($completed_tasks) === array_search($task, $job_tasks) ) {
                                $icon = '⏳'; $color = '#2271b1'; $bg = '#dbeafe';
                            } else {
                                $icon = '🕐'; $color = '#9ca3af'; $bg = '#f3f4f6';
                            }
                        ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;background:<?php echo $bg; ?>;color:<?php echo $color; ?>">
                            <?php echo $icon; ?> <?php echo esc_html( $label ); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        </div></div>
        <style>@keyframes vp-pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>
        <?php
    }

    // ─── VİDEOLAR SEKMESİ ────────────────────────────────────────────────────

    private static function renderVideosTab( string $nonce, string $ajax_url ): void
    {
        $paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $perpage = 20;
        $query   = new \WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'video',
            'posts_per_page' => $perpage,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $status_labels = [
            'none'               => ['label' => 'İşlenmedi',  'color' => '#9ca3af'],
            'queued'             => ['label' => 'Kuyrukta',   'color' => '#d97706'],
            'processing'         => ['label' => 'İşleniyor',  'color' => '#2271b1'],
            'done'               => ['label' => 'Tamamlandı', 'color' => '#00a32a'],
            'failed'             => ['label' => 'Başarısız',  'color' => '#d63638'],
            'ffmpeg_unavailable' => ['label' => 'FFmpeg Yok', 'color' => '#9ca3af'],
        ];
        ?>
        <div class="sh-layout"><div class="sh-main">
        <div class="sh-card">
            <div class="sh-card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Tüm Videolar (<?php echo $query->found_posts; ?>)</h3>
                <button class="sh-btn sh-btn-primary" onclick="shVpProcessAll()">▶ İşlenmemişleri İşle</button>
            </div>
            <div class="sh-card-body" style="padding:0">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="background:#f9fafb">
                    <th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280">Video</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280">Boyut</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280">Tarih</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280">Durum</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280">İşlem</th>
                </tr></thead>
                <tbody>
                <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post() ;
                    $id       = get_the_ID();
                    $file     = get_attached_file( $id );
                    $size     = $file && file_exists( $file ) ? size_format( filesize( $file ) ) : '—';
                    $status   = get_post_meta( $id, '_vp_status', true ) ?: 'none';
                    $badge    = $status_labels[$status] ?? $status_labels['none'];
                    $log      = VideoProcessorLog::get( $id );
                ?>
                <tr id="vp-row-<?php echo $id; ?>" style="border-bottom:1px solid #f3f4f6">
                    <td style="padding:10px 16px">
                        <div style="font-weight:600;font-size:13px"><?php echo esc_html( get_the_title() ); ?></div>
                        <div style="font-size:11px;color:#9ca3af"><?php echo esc_html( basename( $file ?? '' ) ); ?></div>
                    </td>
                    <td style="padding:10px 16px;font-size:13px;color:#6b7280"><?php echo $size; ?></td>
                    <td style="padding:10px 16px;font-size:13px;color:#6b7280"><?php echo get_the_date('d.m.Y'); ?></td>
                    <td style="padding:10px 16px">
                        <span class="vp-status-badge" id="vp-badge-<?php echo $id; ?>"
                              style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $badge['color']; ?>22;color:<?php echo $badge['color']; ?>">
                            <?php echo $badge['label']; ?>
                        </span>
                    </td>
                    <td style="padding:10px 16px">
                        <button class="sh-btn sh-btn-sm" onclick="shVpReprocess(<?php echo $id; ?>)" title="Yeniden İşle">🔄</button>
                        <?php if ( ! empty( $log ) ) : ?>
                        <button class="sh-btn sh-btn-sm" onclick="shVpToggleLog(<?php echo $id; ?>)" title="Logu Görüntüle">📋</button>
                        <button class="sh-btn sh-btn-sm" onclick="shVpClearLog(<?php echo $id; ?>)" title="Logu Temizle" style="color:#d63638">🗑</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( ! empty( $log ) ) : ?>
                <tr id="vp-log-<?php echo $id; ?>" style="display:none;background:#fafafa">
                    <td colspan="5" style="padding:10px 16px">
                        <?php foreach ( $log as $entry ) :
                            $icon  = $entry['result'] === 'success' ? '✅' : '❌';
                            $dur   = VideoProcessorLog::formatDuration( $entry['duration'] ?? 0 );
                        ?>
                        <div style="font-size:12px;padding:4px 0;border-bottom:1px solid #f0f0f0">
                            <?php echo $icon; ?>
                            <strong><?php echo esc_html( $entry['task'] ?? '' ); ?></strong>
                            — <?php echo $dur; ?>
                            <?php if ( $entry['message'] ) : ?>
                            <span style="color:#d63638"> — <?php echo esc_html( $entry['message'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endwhile; wp_reset_postdata(); endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div></div>
        <?php
    }

    // ─── AYARLAR SEKMESİ ──────────────────────────────────────────────────────

    private static function renderSettingsTab( string $nonce, string $ajax_url ): void
    {
        $s         = VideoProcessorSettings::get();
        $available = VideoProcessor::isAvailable();
        $supported = VideoProcessor::isSupported();
        $tasks     = [
            'resize_720' => '720p\'ye küçült (orijinalin yerini alır)',
            'encode_480' => '480p tablet versiyonu üret',
            'encode_360' => '360p telefon versiyonu üret',
            'poster'     => 'Poster frame çıkar (5. saniyeden)',
            'sprite_vtt' => 'Thumbnail sprite + VTT üret',
        ];
        ?>
        <div class="sh-layout"><div class="sh-main">

        <!-- FFmpeg durumu artık sayfanın üstündeki banner'da gösteriliyor -->

        <!-- Varsayılan Görevler -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">Varsayılan Görevler</h3></div>
            <div class="sh-card-body">
                <p style="font-size:13px;color:#6b7280;margin-top:0">Video yüklendiğinde otomatik olarak hangi işlemler çalışsın?</p>
                <?php foreach ( $tasks as $key => $label ) : ?>
                <div style="margin-bottom:10px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="vp-task-<?php echo $key; ?>" <?php checked( ! empty( $s[$key] ) ); ?> style="width:16px;height:16px">
                        <span style="font-size:13px"><?php echo esc_html( $label ); ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bildirimler -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">Bildirimler</h3></div>
            <div class="sh-card-body">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:14px">
                    <input type="checkbox" id="vp-notify-on-done"
                           <?php checked( ! empty( $s['notify_on_done'] ) ); ?>
                           onchange="shVpToggleNotifyDetails(this.checked)"
                           style="width:16px;height:16px">
                    <span style="font-size:13px;font-weight:600">İşlem tamamlandığında bildir</span>
                </label>

                <div id="vp-notify-details" style="<?php echo empty($s['notify_on_done']) ? 'display:none' : ''; ?>;margin-top:12px">

                    <!-- Kanallar -->
                    <div style="margin-bottom:14px">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px">Bildirim Kanalları</label>
                        <div style="display:flex;flex-wrap:wrap;gap:14px">
                            <label style="display:flex;align-items:center;gap:6px">
                                <input type="checkbox" checked disabled style="width:14px;height:14px">
                                <span style="font-size:13px">Email <span style="color:#9ca3af;font-size:11px">(zorunlu)</span></span>
                            </label>
                            <?php if ( defined('ENABLE_SMS') && ENABLE_SMS ) : ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="checkbox" id="vp-notify-sms" <?php checked( ! empty( $s['notify_channels']['sms'] ) ); ?> style="width:14px;height:14px">
                                <span style="font-size:13px">SMS</span>
                            </label>
                            <?php endif; ?>
                            <?php if ( defined('ENABLE_WEB_PUSH') && ENABLE_WEB_PUSH ) : ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="checkbox" id="vp-notify-push" <?php checked( ! empty( $s['notify_channels']['push'] ) ); ?> style="width:14px;height:14px">
                                <span style="font-size:13px">Web Push</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alıcı -->
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px">Bildirim Alıcısı</label>
                        <select id="vp-notify-recipient" class="sh-select" style="width:100%;max-width:300px">
                            <option value="admin"    <?php selected( $s['notify_recipient'] ?? 'admin', 'admin' ); ?>>Admin</option>
                            <option value="uploader" <?php selected( $s['notify_recipient'] ?? '', 'uploader' ); ?>>Videoyu Yükleyen</option>
                            <option value="both"     <?php selected( $s['notify_recipient'] ?? '', 'both' ); ?>>Her İkisi (aynı kişiyse tek bildirim gider)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center">
            <button class="sh-btn sh-btn-primary" onclick="shVpSaveSettings()">💾 Kaydet</button>
            <span id="vp-settings-status" style="font-size:12px;color:#666"></span>
        </div>

        </div></div>
        <?php
    }

    // ─── MEDIA LIBRARY BUTONU ─────────────────────────────────────────────────

    public static function addMediaLibraryButton( array $actions, \WP_Post $post ): array
    {
        if ( strpos( $post->post_mime_type, 'video/' ) !== 0 ) return $actions;
        $nonce   = wp_create_nonce( 'vp_nonce' );
        $actions['vp_optimize'] = sprintf(
            '<a href="#" onclick="shVpOptimizeSingle(%d,\'%s\');return false;" title="Video Optimize Et">⚡ Optimize</a>',
            $post->ID, $nonce
        );
        return $actions;
    }

    // ─── AJAX HANDLER'LAR ─────────────────────────────────────────────────────

    public static function ajaxOptimizeSingle(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        if ( ! VideoProcessor::isAvailable() ) {
            wp_send_json_error( [
                'code'    => 'ffmpeg_unavailable',
                'message' => 'FFmpeg kurulu değil. Video işleme için FFmpeg gereklidir.',
            ] );
        }
        $id = (int) ( $_POST['attachment_id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error( 'Geçersiz ID' );
        if ( VideoProcessorQueue::isQueued( $id ) ) wp_send_json_error( 'Zaten kuyrukta' );
        $tasks = VideoProcessorSettings::getEnabledTasks();
        if ( empty( $tasks ) ) {
            wp_send_json_error( [
                'code'    => 'no_tasks',
                'message' => 'Hiçbir görev aktif değil. Ayarlar sekmesinden görev seçin.',
            ] );
        }
        VideoProcessorQueue::enqueue( $id, $tasks );
        wp_send_json_success( [ 'status' => 'queued' ] );
    }

    public static function ajaxQueueStatus(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        $status = VideoProcessorQueue::getStatus();
        // Son 50 video'nun durumlarını ekle
        $query = new \WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'video',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ] );
        $durumlar = [];
        foreach ( $query->posts as $vid_id ) {
            $durumlar[$vid_id] = get_post_meta( $vid_id, '_vp_status', true ) ?: 'none';
        }
        wp_send_json_success( array_merge( $status, [ 'statuses' => $durumlar ] ) );
    }

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        VideoProcessorSettings::save( $_POST );
        wp_send_json_success( 'Kaydedildi' );
    }

    public static function ajaxReprocess(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        if ( ! VideoProcessor::isAvailable() ) {
            wp_send_json_error( [
                'code'    => 'ffmpeg_unavailable',
                'message' => 'FFmpeg kurulu değil. Video işleme için FFmpeg gereklidir.',
            ] );
        }
        $id = (int) ( $_POST['attachment_id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error( 'Geçersiz ID' );
        if ( VideoProcessorQueue::isQueued( $id ) ) wp_send_json_error( 'Zaten kuyrukta' );
        update_post_meta( $id, '_vp_status', 'queued' );
        $tasks = VideoProcessorSettings::getEnabledTasks();
        VideoProcessorQueue::enqueue( $id, $tasks );
        wp_send_json_success( [ 'status' => 'queued' ] );
    }

    public static function ajaxProcessAll(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        if ( ! VideoProcessor::isAvailable() ) {
            wp_send_json_error( [
                'code'    => 'ffmpeg_unavailable',
                'message' => 'FFmpeg kurulu değil. Video işleme için FFmpeg gereklidir.',
            ] );
        }
        $tasks = VideoProcessorSettings::getEnabledTasks();
        if ( empty( $tasks ) ) wp_send_json_error( 'Aktif görev yok' );
        $query = new \WP_Query( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'video',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ 'relation' => 'OR',
                [ 'key' => '_vp_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_vp_status', 'value'   => 'none' ],
                [ 'key' => '_vp_status', 'value'   => 'failed' ],
            ],
        ] );
        $count = 0;
        foreach ( $query->posts as $id ) {
            if ( ! VideoProcessorQueue::isQueued( $id ) ) {
                VideoProcessorQueue::enqueue( $id, $tasks );
                $count++;
            }
        }
        wp_send_json_success( [ 'enqueued' => $count ] );
    }

    public static function ajaxClearLog(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        $id = (int) ( $_POST['attachment_id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error( 'Geçersiz ID' );
        VideoProcessorLog::clear( $id );
        wp_send_json_success( 'Log temizlendi' );
    }

    public static function ajaxInstallFfmpeg(): void
    {
        check_ajax_referer( 'vp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz' );
        // update.php'deki install_ffmpeg() mantığını çağır
        if ( method_exists( 'Update', 'install_ffmpeg' ) ) {
            \Update::install_ffmpeg();
        } else {
            wp_send_json_error( 'FFmpeg kurulum metodu bulunamadı' );
        }
    }

    // ─── JS & TOAST ───────────────────────────────────────────────────────────

    private static function renderToastScript( string $nonce, string $ajax_url ): void
    {
        ?>
        <style>
        .vp-badge-queued    { background:#fef3c7;color:#d97706;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600 }
        .vp-badge-done      { background:#dcfce7;color:#00a32a;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600 }
        .vp-badge-failed    { background:#fee2e2;color:#d63638;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600 }
        .vp-badge-processing{ background:#dbeafe;color:#2271b1;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600 }
        </style>
        <script>
        var vpNonce   = '<?php echo esc_js($nonce); ?>';
        var vpAjaxUrl = '<?php echo esc_js($ajax_url); ?>';
        var vpPolling = null;

        function shVpOptimizeSingle(id, nonce) {
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=vp_optimize_single&nonce='+nonce+'&attachment_id='+id})
            .then(r=>r.json()).then(function(res) {
                var msg = res.success ? '✅ Kuyruğa eklendi' : '❌ '+(res.data?.message || res.data || 'Hata');
                shVpToast(msg);
                if(res.success) shVpStartPolling();
            });
        }

        function shVpReprocess(id) {
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=vp_reprocess&nonce='+vpNonce+'&attachment_id='+id})
            .then(r=>r.json()).then(function(res) {
                var msg = res.success ? '✅ Yeniden kuyruğa eklendi' : '❌ '+(res.data?.message || res.data || 'Hata');
                shVpToast(msg);
                if(res.success) shVpStartPolling();
            });
        }

        function shVpProcessAll() {
            <?php if ( ! $available ) : ?>
            shVpToast('❌ FFmpeg kurulu değil. Önce FFmpeg kurulumunu tamamlayın.');
            return;
            <?php endif; ?>
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=vp_process_all&nonce='+vpNonce})
            .then(r=>r.json()).then(function(res) {
                shVpToast(res.success ? '✅ '+res.data.enqueued+' video kuyruğa eklendi' : '❌ '+(res.data||'Hata'));
                if(res.success) shVpStartPolling();
            });
        }

        function shVpToggleLog(id) {
            var row = document.getElementById('vp-log-'+id);
            if(row) row.style.display = row.style.display === 'none' ? '' : 'none';
        }

        function shVpClearLog(id) {
            if(!confirm('Logu temizlemek istediğinize emin misiniz?')) return;
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=vp_clear_log&nonce='+vpNonce+'&attachment_id='+id})
            .then(r=>r.json()).then(function(res) {
                shVpToast(res.success ? '✅ Log temizlendi' : '❌ Hata');
            });
        }

        function shVpToggleNotifyDetails(checked) {
            var d = document.getElementById('vp-notify-details');
            if(d) d.style.display = checked ? '' : 'none';
        }

        function shVpSaveSettings() {
            var data = 'action=vp_save_settings&nonce='+vpNonce;
            ['resize_720','encode_480','encode_360','poster','sprite_vtt'].forEach(function(k) {
                var el = document.getElementById('vp-task-'+k);
                if(el) data += '&'+k+'='+(el.checked?1:0);
            });
            // notify
            var nd = document.getElementById('vp-notify-on-done');
            if(nd) data += '&notify_on_done='+(nd.checked?1:0);
            var sms  = document.getElementById('vp-notify-sms');
            var push = document.getElementById('vp-notify-push');
            if(sms)  data += '&notify_channels[sms]='+(sms.checked?1:0);
            if(push) data += '&notify_channels[push]='+(push.checked?1:0);
            var rec = document.getElementById('vp-notify-recipient');
            if(rec) data += '&notify_recipient='+encodeURIComponent(rec.value);
            var st = document.getElementById('vp-settings-status');
            if(st) st.textContent = 'Kaydediliyor...';
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data})
            .then(r=>r.json()).then(function(res) {
                if(st) { st.textContent = res.success ? '✅ Kaydedildi' : '❌ Hata'; st.style.color = res.success?'#00a32a':'#d63638'; setTimeout(()=>{st.textContent='';},3000); }
            });
        }

        function shVpInstallFfmpeg() {
            var st = document.getElementById('vp-ffmpeg-status');
            if(st) st.textContent = '⏳ Kuruluyor...';
            fetch(vpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=install_ffmpeg_vp&nonce='+vpNonce})
            .then(r=>r.json()).then(function(res) {
                if(st) st.textContent = res.success ? '✅ Kuruldu, sayfayı yenileyin' : '❌ '+(res.data?.message||'Hata');
                if(res.success) setTimeout(()=>location.reload(), 1500);
            });
        }

        function shVpStartPolling() {
            if(vpPolling) return;
            vpPolling = setInterval(function() {
                fetch(vpAjaxUrl+'?action=vp_queue_status&nonce='+vpNonce)
                .then(r=>r.json()).then(function(res) {
                    if(!res.success) return;
                    var d = res.data;
                    // Badge güncelle
                    if(d.statuses) Object.keys(d.statuses).forEach(function(id) {
                        var badge = document.getElementById('vp-badge-'+id);
                        if(!badge) return;
                        var map = {none:'Not Processed',queued:'Queued',processing:'Processing',done:'Done',failed:'Failed'};
                        var colors = {none:'#9ca3af',queued:'#d97706',processing:'#2271b1',done:'#00a32a',failed:'#d63638'};
                        var s = d.statuses[id] || 'none';
                        badge.textContent = map[s] || s;
                        badge.style.background = (colors[s]||'#9ca3af')+'22';
                        badge.style.color = colors[s]||'#9ca3af';
                    });
                    // Progress bar
                    var pb = document.getElementById('vp-progress-bar');
                    var info = document.getElementById('vp-queue-info');
                    if(pb && d.total > 0) {
                        var pct = Math.round(((d.total-d.pending)/d.total)*100);
                        pb.style.width = pct+'%';
                        pb.textContent = pct+'%';
                    }
                    if(info) info.innerHTML = 'Queue: <strong>'+d.pending+'</strong> videos waiting';
                    // Polling'i durdur
                    if(!d.is_running && d.pending === 0) {
                        clearInterval(vpPolling);
                        vpPolling = null;
                    }
                });
            }, 3000);
        }

        function shVpToast(msg) {
            var t = document.getElementById('sh-toast');
            if(!t) return;
            t.textContent = msg;
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;z-index:99999;background:#f0fdf4;color:#166534;border:1px solid #86efac';
            setTimeout(function(){t.style.opacity='0';setTimeout(function(){t.style.cssText='';},300);},3000);
        }

        // Sayfa yüklenince polling başlat (kuyruk doluysa)
        document.addEventListener('DOMContentLoaded', function() {
            fetch(vpAjaxUrl+'?action=vp_queue_status&nonce='+vpNonce)
            .then(r=>r.json()).then(function(res) {
                if(res.success && (res.data.is_running || res.data.pending > 0)) shVpStartPolling();
            });
        });
        </script>
        <?php
    }
}
