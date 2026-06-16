<?php

namespace SaltHareket\ThemeExport;

use SaltHareket\ThemeExport\Concerns\HandlesDatabase;
use SaltHareket\ThemeExport\Concerns\HandlesFiles;

/**
 * ThemeExporter
 *
 * Site/tema/DB export sistemi. ACF bağımsız, kendi admin sayfası.
 * Export dosyaları theme/static/data/exports/ altında — web erişimi kapalı.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: Initial release — class.theme-export.php refactored
 *     - Add: ACF bağımsız admin sayfası (Theme Settings altında)
 *     - Add: Export klasörü theme/static/data/exports/ — .htaccess korumalı
 *     - Add: PHP stream download — direkt URL erişimi yok
 *     - Add: Export geçmişi (son 10 kayıt)
 *     - Add: Exclude patterns (node_modules, .git, vendor vs.)
 *     - Add: Scheduled export (WP Cron, opsiyonel)
 *     - Add: sh-admin.css entegrasyonu — inline style yok
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // bootstrap.php'de otomatik yüklenir:
 * if ($is_admin) include_once SH_INCLUDES_PATH . 'apps/theme-export/bootstrap.php';
 *
 * // Export modları:
 * //   full  — DB + WP Core + Theme + wp-content → tek ZIP
 * //   db    — Sadece DB dump → ZIP
 * //   theme — Sadece aktif tema → ZIP
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Manuel export tetikle (WP CLI veya cron):
 *   $exporter = new ThemeExporter();
 *   $result = $exporter->runStep('init', [], []);
 *
 * @example
 *   // Export geçmişini al:
 *   $history = ThemeExporter::getHistory();
 *
 * @example
 *   // Export dosyasını indir (admin-ajax.php üzerinden):
 *   ThemeExporter::streamDownload($file_path);
 *
 * @example
 *   // Exclude pattern ekle:
 *   add_filter('sh_export_excludes', function($excludes) {
 *       $excludes[] = 'my-temp-folder';
 *       return $excludes;
 *   });
 *
 * @example
 *   // Export tamamlandı hook:
 *   add_action('sh_export_completed', function($zip_path, $mode) {
 *       // bildirim gönder, log tut vs.
 *   }, 10, 2);
 */
class ThemeExporter
{
    use HandlesDatabase;
    use HandlesFiles;

    private const AJAX_EXPORT  = 'sh_export_process';
    private const AJAX_DELETE  = 'sh_export_delete';
    private const AJAX_CANCEL  = 'sh_export_cancel';
    private const AJAX_DOWNLOAD = 'sh_export_download';
    private const NONCE_ACTION = 'sh_export_nonce';
    private const NONCE_FIELD  = '_export_nonce';
    private const HISTORY_KEY  = 'sh_export_history';
    private const SETTINGS_KEY = 'sh_export_settings';
    private const MAX_HISTORY  = 10;

    // ─── AJAX Handlers ───────────────────────────────────────────────────────

    public function handleExport(): void
    {
        $this->verifyRequest();

        @ini_set( 'memory_limit', '2048M' );
        set_time_limit( 0 );

        $step   = sanitize_key( $_POST['step'] ?? 'init' );
        $tmp    = $this->sanitizePath( $_POST['temp_dir'] ?? '' );
        $zip    = $this->sanitizePath( $_POST['zip_path'] ?? '' );
        $config = $this->sanitizeConfig( $_POST['config_data'] ?? [] );

        try {
            if ( $step !== 'init' ) $this->checkCancel( $tmp );

            $res = match ( $step ) {
                'init'         => $this->stepInit( $config ),
                'db_dump'      => $this->stepDb( $tmp, $config ),
                'core_files'   => $this->stepCore( $tmp, $config ),
                'theme_export' => $this->stepTheme( $tmp, $config ),
                'zip_create'   => $this->stepZip( $tmp, $zip, $config ),
                default        => throw new \Exception( 'Unknown step: ' . $step ),
            };

            wp_send_json_success( $res );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public function handleDelete(): void
    {
        $this->verifyRequest();

        $id      = sanitize_text_field( $_POST['export_id'] ?? '' );
        $history = self::getHistory();

        foreach ( $history as $key => $item ) {
            if ( ( $item['id'] ?? '' ) === $id ) {
                $file = $item['path'] ?? '';
                if ( $file && file_exists( $file ) ) @unlink( $file );
                unset( $history[ $key ] );
                break;
            }
        }

        update_option( self::HISTORY_KEY, array_values( $history ), false );
        wp_send_json_success();
    }

    public function handleCancel(): void
    {
        $this->verifyRequest();
        $tmp = $this->sanitizePath( $_POST['temp_dir'] ?? '' );
        if ( $tmp && is_dir( $tmp ) ) {
            touch( trailingslashit( $tmp ) . '.cancel_flag' );
        }
        wp_send_json_success();
    }

    public function handleDownload(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        if ( ! check_admin_referer( self::NONCE_ACTION ) ) wp_die( 'Invalid nonce.' );

        $id      = sanitize_text_field( $_GET['export_id'] ?? '' );
        $history = self::getHistory();

        foreach ( $history as $item ) {
            if ( ( $item['id'] ?? '' ) === $id ) {
                $file = $item['path'] ?? '';
                if ( $file && file_exists( $file ) ) {
                    self::streamDownload( $file );
                }
                break;
            }
        }

        wp_die( 'Export file not found.' );
    }

    // ─── Steps ───────────────────────────────────────────────────────────────

    private function stepInit( array $config ): array
    {
        $export_dir = self::ensureExportDir();
        $mode       = $config['export_mode'] ?? 'full';
        $token      = date( 'Ymd_His' );
        $tmp        = $export_dir . '/tmp_' . $token;

        wp_mkdir_p( $tmp );

        $steps = [ 'init' ];
        if ( in_array( $mode, [ 'full', 'db' ], true ) ) $steps[] = 'db_dump';
        if ( $mode === 'full' ) $steps[] = 'core_files';
        $steps[] = 'theme_export';
        $steps[] = 'zip_create';

        $zip_path = $export_dir . '/export_' . $mode . '_' . $token . '.zip';

        return [
            'next_step'    => $mode === 'theme' ? 'theme_export' : 'db_dump',
            'temp_dir'     => $tmp,
            'zip_path'     => $zip_path,
            'active_steps' => $steps,
            'log'          => 'INIT: Export workspace ready. Mode: ' . strtoupper( $mode ),
            'log_type'     => 'info',
        ];
    }

    private function stepDb( string $tmp, array $config ): array
    {
        $mode     = $config['export_mode'] ?? 'full';
        $cur_url  = get_site_url();
        $tar_url  = $config['url'] ?? '';
        $prefix   = $config['table_prefix'] ?? '';
        $slug     = get_stylesheet();
        $filename = $slug . '-db-' . date( 'Ymd-His' ) . '.sql';
        $sql_path = $tmp . '/' . $filename;

        $this->exportDatabase( $sql_path, $cur_url, $tar_url, $tmp, $prefix );

        $size = size_format( filesize( $sql_path ), 2 );
        $next = $mode === 'db' ? 'zip_create' : 'core_files';

        return [
            'next_step' => $next,
            'log'       => "DATABASE: {$filename} exported ({$size})" . ( $prefix ? " — prefix: {$prefix}" : '' ),
            'log_type'  => 'ok',
        ];
    }

    private function stepCore( string $tmp, array $config ): array
    {
        $mode = $config['export_mode'] ?? 'full';
        if ( $mode !== 'full' ) {
            return [ 'next_step' => 'theme_export', 'log' => 'CORE: Skipped.', 'log_type' => 'sys' ];
        }

        $abs  = untrailingslashit( ABSPATH );
        $logs = [];

        if ( ! empty( $config['root_files'] ) ) {
            foreach ( scandir( $abs ) as $f ) {
                if ( $f === '.' || $f === '..' ) continue;
                if ( is_file( "{$abs}/{$f}" ) && $f !== 'wp-config.php' ) {
                    copy( "{$abs}/{$f}", "{$tmp}/{$f}" );
                }
            }
            $logs[] = 'ROOT: Root files copied.';
        }

        if ( ! empty( $config['wp_config'] ) ) {
            $c = file_get_contents( "{$abs}/wp-config.php" );
            foreach ( [ 'DB_NAME' => 'db', 'DB_USER' => 'user', 'DB_PASSWORD' => 'pass' ] as $const => $key ) {
                if ( ! empty( $config[ $key ] ) ) {
                    $c = preg_replace(
                        "/(define\s*\(\s*['\"]" . $const . "['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/",
                        '${1}' . addslashes( $config[ $key ] ) . '${3}',
                        $c
                    );
                }
            }
            if ( ! empty( $config['table_prefix'] ) ) {
                $c = preg_replace(
                    "/(\\\$table_prefix\s*=\s*['\"])(.*?)(['\"]\s*;)/",
                    '${1}' . $config['table_prefix'] . '${3}',
                    $c
                );
            }
            file_put_contents( "{$tmp}/wp-config.php", $c );
            $logs[] = 'CONFIG: wp-config.php updated.';
        }

        if ( ! empty( $config['wp_admin'] ) ) {
            $this->copyRecursive( "{$abs}/wp-admin", "{$tmp}/wp-admin", false, '', '', $tmp );
            $logs[] = 'CORE: /wp-admin copied.';
        }

        if ( ! empty( $config['wp_includes'] ) ) {
            $this->copyRecursive( "{$abs}/wp-includes", "{$tmp}/wp-includes", false, '', '', $tmp );
            $logs[] = 'CORE: /wp-includes copied.';
        }

        return [
            'next_step' => 'theme_export',
            'log'       => implode( "\n", $logs ),
            'log_type'  => 'ok',
        ];
    }

    private function stepTheme( string $tmp, array $config ): array
    {
        $mode    = $config['export_mode'] ?? 'full';
        $cur_url = get_site_url();
        $tar_url = $config['url'] ?? '';
        $exclude = apply_filters( 'sh_export_excludes', $this->getDefaultExcludes() );
        $logs    = [];

        if ( $mode === 'full' && ! empty( $config['wp_content'] ) ) {
            $this->copyRecursive(
                trailingslashit( WP_CONTENT_DIR ),
                "{$tmp}/wp-content",
                true, $cur_url, $tar_url, $tmp, $exclude
            );
            $logs[] = 'CONTENT: /wp-content copied with URL replacements.';
        } elseif ( $mode === 'theme' ) {
            $slug = get_stylesheet();
            $this->copyRecursive(
                trailingslashit( get_stylesheet_directory() ),
                "{$tmp}/{$slug}",
                true, $cur_url, $tar_url, $tmp, $exclude
            );
            $logs[] = "THEME: {$slug} copied.";
        }

        return [
            'next_step' => 'zip_create',
            'log'       => implode( "\n", $logs ) ?: 'THEME: Step complete.',
            'log_type'  => 'ok',
        ];
    }

    private function stepZip( string $tmp, string $zip_path, array $config ): array
    {
        $mode = $config['export_mode'] ?? 'full';

        if ( empty( $zip_path ) ) {
            $zip_path = $tmp . '.zip';
        }

        $this->checkCancel( $tmp );
        $this->createZip( $tmp, $zip_path, $tmp );
        $this->rmdirRecursive( $tmp );

        $size = file_exists( $zip_path ) ? size_format( filesize( $zip_path ), 2 ) : '?';
        $id   = uniqid( 'exp_', true );

        // Geçmişe ekle
        $this->addToHistory( [
            'id'   => $id,
            'path' => $zip_path,
            'name' => basename( $zip_path ),
            'mode' => $mode,
            'date' => wp_date( 'd.m.Y H:i:s' ),
            'size' => $size,
        ] );

        do_action( 'sh_export_completed', $zip_path, $mode );

        return [
            'export_id' => $id,
            'next_step' => 'done',
            'log'       => "ZIP: " . basename( $zip_path ) . " created ({$size}).\nSYSTEM: Cleanup done. Export complete.",
            'log_type'  => 'ok',
        ];
    }

    // ─── History ─────────────────────────────────────────────────────────────

    public static function getHistory(): array
    {
        $history = get_option( self::HISTORY_KEY, [] );
        if ( ! is_array( $history ) ) return [];

        // Dosyası silinmiş kayıtları temizle
        return array_values( array_filter( $history, function ( $item ) {
            return ! empty( $item['path'] ) && file_exists( $item['path'] );
        } ) );
    }

    private function addToHistory( array $item ): void
    {
        $history   = self::getHistory();
        $history[] = $item;

        // Max kayıt sayısını aş
        $max = (int) ( self::getSettings()['max_history'] ?? self::MAX_HISTORY );
        if ( count( $history ) > $max ) {
            // En eski fazla kayıtları sil
            $to_remove = array_slice( $history, 0, count( $history ) - $max );
            foreach ( $to_remove as $old ) {
                if ( ! empty( $old['path'] ) && file_exists( $old['path'] ) ) {
                    @unlink( $old['path'] );
                }
            }
            $history = array_slice( $history, -$max );
        }

        update_option( self::HISTORY_KEY, array_values( $history ), false );
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public static function getSettings(): array
    {
        $defaults = [
            'max_history'      => 10,
            'default_mode'     => 'full',
            'memory_limit'     => '2048M',
            'scheduled_export' => false,
            'schedule_freq'    => 'weekly',
        ];

        $saved = get_option( self::SETTINGS_KEY, [] );
        return wp_parse_args( $saved, $defaults );
    }

    public static function saveSettings( array $data ): void
    {
        $settings = [
            'max_history'      => max( 1, min( 50, (int) ( $data['max_history'] ?? 10 ) ) ),
            'default_mode'     => in_array( $data['default_mode'] ?? '', [ 'full', 'db', 'theme' ], true ) ? $data['default_mode'] : 'full',
            'memory_limit'     => sanitize_text_field( $data['memory_limit'] ?? '2048M' ),
            'scheduled_export' => ! empty( $data['scheduled_export'] ),
            'schedule_freq'    => in_array( $data['schedule_freq'] ?? '', [ 'daily', 'weekly', 'monthly' ], true ) ? $data['schedule_freq'] : 'weekly',
        ];

        update_option( self::SETTINGS_KEY, $settings, false );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function verifyRequest(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        if ( ! check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD, false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid security token. Please refresh the page.' ] );
        }
    }

    private function sanitizeConfig( array $raw ): array
    {
        $text = [ 'export_mode', 'db', 'user', 'pass', 'url', 'table_prefix' ];
        $bool = [ 'wp_includes', 'wp_admin', 'wp_content', 'root_files', 'wp_config' ];
        $clean = [];

        foreach ( $text as $f ) {
            $clean[ $f ] = isset( $raw[ $f ] ) ? sanitize_text_field( $raw[ $f ] ) : '';
        }
        foreach ( $bool as $f ) {
            $clean[ $f ] = ! empty( $raw[ $f ] ) && $raw[ $f ] === 'true';
        }

        return $clean;
    }

    public static function getNonceAction(): string { return self::NONCE_ACTION; }
    public static function getNonceField(): string  { return self::NONCE_FIELD; }
    public static function getAjaxExport(): string  { return self::AJAX_EXPORT; }
    public static function getAjaxDelete(): string  { return self::AJAX_DELETE; }
    public static function getAjaxCancel(): string  { return self::AJAX_CANCEL; }
    public static function getAjaxDownload(): string { return self::AJAX_DOWNLOAD; }
}
