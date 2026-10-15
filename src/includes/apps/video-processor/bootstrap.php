<?php

/**
 * Video Processor App Bootstrap
 *
 * @version 1.0.0
 * @package SaltHareket\VideoProcessor
 *
 * variables.php'de yüklenir:
 *   define('ENABLE_VIDEO_PROCESSOR', (bool) get_option('vp_enabled', false));
 *   if (ENABLE_VIDEO_PROCESSOR || $is_admin) {
 *       include_once SH_INCLUDES_PATH . 'apps/video-processor/bootstrap.php';
 *   }
 */

$vp_base = __DIR__ . '/';

// Concerns trait'leri — VideoProcessor.php'den ÖNCE yüklenmeli
require_once $vp_base . 'Concerns/HandlesConversion.php';
require_once $vp_base . 'Concerns/HandlesPoster.php';
require_once $vp_base . 'Concerns/HandlesThumbnails.php';

// Temel sınıflar
require_once $vp_base . 'VideoProcessorSettings.php';
require_once $vp_base . 'VideoProcessorLog.php';
require_once $vp_base . 'VideoProcessor.php';
require_once $vp_base . 'VideoProcessorQueue.php';

// Admin (sadece admin/ajax context'de)
if ( is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
    require_once $vp_base . 'Admin/VideoProcessorAdmin.php';
    \SaltHareket\VideoProcessor\Admin\VideoProcessorAdmin::register();
}

// ─── WP CRON ─────────────────────────────────────────────────────────────────
add_action(
    \SaltHareket\VideoProcessor\VideoProcessorQueue::CRON_HOOK,
    ['\SaltHareket\VideoProcessor\VideoProcessorQueue', 'processNext']
);

// ─── OTOMATİK İŞLEME (sadece ENABLE_VIDEO_PROCESSOR true ise) ────────────────
if ( defined('ENABLE_VIDEO_PROCESSOR') && ENABLE_VIDEO_PROCESSOR ) {
    add_filter(
        'wp_generate_attachment_metadata',
        function( array $metadata, int $attachment_id ): array {
            // Sadece video MIME türleri
            $post = get_post( $attachment_id );
            if ( ! $post || strpos( $post->post_mime_type, 'video/' ) !== 0 ) {
                return $metadata;
            }
            // FFmpeg mevcut mu?
            if ( ! \SaltHareket\VideoProcessor\VideoProcessor::isAvailable() ) {
                update_post_meta( $attachment_id, '_vp_status', 'ffmpeg_unavailable' );
                return $metadata;
            }
            // Zaten kuyrukta mı?
            if ( \SaltHareket\VideoProcessor\VideoProcessorQueue::isQueued( $attachment_id ) ) {
                return $metadata;
            }
            // Varsayılan görevlerle kuyruğa al
            $tasks = \SaltHareket\VideoProcessor\VideoProcessorSettings::getEnabledTasks();
            if ( ! empty( $tasks ) ) {
                \SaltHareket\VideoProcessor\VideoProcessorQueue::enqueue( $attachment_id, $tasks );
            }
            return $metadata;
        },
        20,
        2
    );
}

// ─── BLOCK KAYDETME HOOK (ACF block video işleme) ────────────────────────────
if ( defined('ENABLE_VIDEO_PROCESSOR') && ENABLE_VIDEO_PROCESSOR ) {
    add_action( 'acf/save_post', function( $post_id ) {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( $post_id === 'options' ) return;

        $content = get_post_field( 'post_content', $post_id );
        if ( empty( $content ) ) return;

        $blocks = parse_blocks( $content );
        foreach ( $blocks as $block ) {
            if ( ! isset( $block['attrs']['data']['video_file_desktop'] ) ) continue;
            $desktop = $block['attrs']['data']['video_file_desktop'] ?? null;
            if ( ! $desktop || ! is_numeric( $desktop ) ) continue;

            $id = (int) $desktop;
            if ( \SaltHareket\VideoProcessor\VideoProcessorQueue::isQueued( $id ) ) continue;

            // FFmpeg seçenekleri kontrol et
            $generate_sizes      = ! empty( $block['attrs']['data']['ffmpeg_available_generate_sizes'] );
            $generate_thumbnails = ! empty( $block['attrs']['data']['ffmpeg_available_generate_thumbnails'] );
            $generate_poster     = ! empty( $block['attrs']['data']['ffmpeg_available_generate_poster'] );

            if ( ! $generate_sizes && ! $generate_thumbnails && ! $generate_poster ) continue;

            $tasks = [];
            if ( $generate_sizes )      $tasks = array_merge( $tasks, ['resize_720', 'encode_480', 'encode_360'] );
            if ( $generate_poster )     $tasks[] = 'poster';
            if ( $generate_thumbnails ) $tasks[] = 'sprite_vtt';

            if ( ! empty( $tasks ) ) {
                \SaltHareket\VideoProcessor\VideoProcessorQueue::enqueue( $id, $tasks );
            }
        }
    }, 20 );
}

// ─── NOTIFICATIONS EVENT KAYDI ───────────────────────────────────────────────
add_filter( 'sh_notify_events', function( array $events ): array {
    $events[] = [
        'slug'        => 'video-processor/job-complete',
        'title'       => 'Video İşleme Tamamlandı',
        'description' => 'Bir video işleme görevi başarıyla tamamlandığında tetiklenir.',
        'group'       => 'Video Processor',
    ];
    return $events;
} );

// ─── ACF FIELD HOOKS (video block field'ları) ─────────────────────────────────
// Eski konum: acf-admin.php (~satır 1280-1437). Buraya taşındı — video processor'a ait.

function delete_related_video_attachments( $post_id ) {
    if ( get_post_type( $post_id ) !== 'attachment' ) return;
    $mime_type = get_post_mime_type( $post_id );
    if ( strpos( $mime_type, 'video' ) === false ) return;

    $meta_keys = [ 'phone', 'tablet', 'poster', 'vtt', 'thumbnails' ];
    foreach ( $meta_keys as $meta_key ) {
        $related_id = get_post_meta( $post_id, $meta_key, true );
        if ( ! empty( $related_id ) ) {
            wp_delete_attachment( $related_id, true );
            delete_post_meta( $post_id, $meta_key );
        }
    }
}
add_action( 'before_delete_post', 'delete_related_video_attachments' );

function remove_attachment_from_video_metadata( $post_id ) {
    if ( get_post_type( $post_id ) !== 'attachment' ) return;

    $video_attachments = get_posts( [
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => -1,
        'meta_query'  => [
            'relation' => 'OR',
            [ 'key' => 'phone',      'value' => $post_id, 'compare' => '=' ],
            [ 'key' => 'tablet',     'value' => $post_id, 'compare' => '=' ],
            [ 'key' => 'vtt',        'value' => $post_id, 'compare' => '=' ],
            [ 'key' => 'poster',     'value' => $post_id, 'compare' => '=' ],
            [ 'key' => 'thumbnails', 'value' => $post_id, 'compare' => '=' ],
        ],
    ] );

    foreach ( $video_attachments as $video ) {
        foreach ( [ 'phone', 'tablet', 'poster', 'vtt', 'thumbnails' ] as $meta_key ) {
            if ( get_post_meta( $video->ID, $meta_key, true ) == $post_id ) {
                delete_post_meta( $video->ID, $meta_key );
            }
        }
    }
}
add_action( 'delete_attachment', 'remove_attachment_from_video_metadata' );

function acf_ffmpeg_available( $field ) {
    $ffmpeg = new \SaltHareket\VideoProcessor\VideoProcessor();
    $field['wrapper']['class'] = $ffmpeg->available ? '' : 'hidden';
    return $field;
}
add_filter( 'acf/load_field/name=ffmpeg_available', 'acf_ffmpeg_available' );

function acf_ffmpeg_not_available( $field ) {
    if ( ! function_exists( 'get_current_screen' ) ) return $field;
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'post' ) return $field;
    $ffmpeg = new \SaltHareket\VideoProcessor\VideoProcessor();
    $field['wrapper']['class'] = $ffmpeg->available ? 'hidden' : '';
    return $field;
}

function acf_ffmpeg_not_available_message( $field ) {
    if ( ! function_exists( 'get_current_screen' ) ) return $field;
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'post' ) return $field;
    $ffmpeg = new \SaltHareket\VideoProcessor\VideoProcessor();
    if ( ! $ffmpeg->available ) {
        ob_start();
        if ( $ffmpeg->supported ) {
            $url = esc_url( admin_url( 'admin.php?page=sh-video-processor' ) );
            echo '<div class="notice notice-warning is-dismissible" style="display:flex;align-items:center;padding:15px;margin:0"><span class="dashicons dashicons-warning" style="margin-right:10px;font-size:24px;color:#ffba00;"></span><p><strong>Attention:</strong> To automatically generate sizes, frame thumbnails, and video poster frames for your uploaded videos, you can install the FFMpeg plugin from the <a href="' . $url . '">Video Processor page.</a></p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible" style="display:flex;align-items:center;padding:15px;margin:0"><span class="dashicons dashicons-no" style="color:#e74c3c;font-size:24px;margin-right:10px;"></span><p><strong>Attention:</strong> Unfortunately, your system does not support the FFMpeg plugin. Therefore, the automatic video size, frame thumbnails, and poster frame generation features are disabled.</p></div>';
        }
        $field['message'] = ob_get_clean();
    }
    return $field;
}
add_filter( 'acf/load_field/key=field_679763ca89402',   'acf_ffmpeg_not_available' );
add_filter( 'acf/prepare_field/key=field_679763ca89402', 'acf_ffmpeg_not_available_message' );

function sync_acf_with_post_meta( $value, $post_id, $field ) {
    $meta_to_acf_mapping = [
        'field_6797647a4fd31' => 'generate_sizes',
        'field_679764d54fd32' => 'generate_thumbnails',
        'field_679764f74fd33' => 'generate_poster',
        'field_66f9e4c018b78' => 'tablet',
        'field_66f9e4ca18b79' => 'phone',
        'field_663f74df3ea87' => 'poster',
        'field_664210041992f' => 'vtt',
        'field_6798cd6f61a0f' => 'vtt',
    ];

    if ( ! array_key_exists( $field['key'], $meta_to_acf_mapping ) ) return $value;

    $related_post_id = get_field( 'field_66f9e48418b77', $post_id );
    if ( ! $related_post_id ) return $value;

    $meta_key   = $meta_to_acf_mapping[ $field['key'] ];
    $meta_value = get_post_meta( $related_post_id, $meta_key, true );

    $value = ( ! empty( $meta_value ) && empty( $value ) ) ? $meta_value : $value;
    if ( in_array( $meta_key, $meta_to_acf_mapping ) ) {
        $value = 0;
    }
    return $value;
}
add_filter( 'acf/load_value/key=field_66f9e4c018b78', 'sync_acf_with_post_meta', 10, 3 );
add_filter( 'acf/load_value/key=field_66f9e4ca18b79', 'sync_acf_with_post_meta', 10, 3 );
add_filter( 'acf/load_value/key=field_663f74df3ea87', 'sync_acf_with_post_meta', 10, 3 );
add_filter( 'acf/load_value/key=field_664210041992f', 'sync_acf_with_post_meta', 10, 3 );
add_filter( 'acf/load_value/key=field_6798cd6f61a0f', 'sync_acf_with_post_meta', 10, 3 );
add_filter( 'sh_apps_registry', function( array $apps ): array {
    $apps[] = [
        'id'          => 'video-processor',
        'label'       => 'Video Processor',
        'icon'        => '🎬',
        'enable_key'  => 'vp_enabled',
        'admin_url'   => admin_url( 'admin.php?page=sh-video-processor' ),
        'enabled'     => defined('ENABLE_VIDEO_PROCESSOR') && ENABLE_VIDEO_PROCESSOR,
        'description' => 'FFmpeg ile otomatik video optimizasyonu, kuyruk ve bildirim sistemi',
    ];
    return $apps;
} );
