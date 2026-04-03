<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme_Site_Exporter — Admin'den site/tema/DB export sistemi.
 *
 * KULLANIM:
 *   // Otomatik — admin_init'te instantiate edilir.
 *   // ACF Options Page "Development" sayfasındaki "Export" butonuna basılınca
 *   // terminal UI açılır ve step-by-step export başlar.
 *
 *   // Export modları:
 *   //   full  — DB + WP Core + Theme + wp-content → tek ZIP
 *   //   db    — Sadece DB dump → ZIP
 *   //   theme — Sadece aktif tema → ZIP
 *
 *   // Özellikler:
 *   //   - URL replace (current → target)
 *   //   - Table prefix değiştirme
 *   //   - wp-config.php DB credentials güncelleme
 *   //   - Serialized data safe replace
 *   //   - JSON data safe replace
 *   //   - Cancel desteği (async flag dosyası)
 *   //   - Chunk'lı DB export (memory koruması)
 *   //   - Nonce + capability koruması
 *   //   - Path traversal koruması
 *
 * @package SaltHareket
 * @since   2.0.0
 */

class Theme_Site_Exporter {

    private const AJAX_EXPORT  = 'theme_site_export_process';
    private const AJAX_DELETE  = 'theme_site_export_delete';
    private const AJAX_CANCEL  = 'theme_site_export_cancel';
    private const NONCE_ACTION = 'theme_site_export_nonce';
    private const NONCE_FIELD  = '_export_nonce';
    private const LAST_EXPORT  = 'theme_site_last_export_info';
    private const DB_CHUNK     = 500;

    public function __construct() {
        add_action( 'wp_ajax_' . self::AJAX_EXPORT, [ $this, 'handle_export' ] );
        add_action( 'wp_ajax_' . self::AJAX_DELETE, [ $this, 'handle_delete' ] );
        add_action( 'wp_ajax_' . self::AJAX_CANCEL, [ $this, 'handle_cancel' ] );

        if ( isset( $_GET['page'] ) && $_GET['page'] === 'development' ) {
            add_action( 'admin_footer', [ $this, 'render_ui' ] );
        }
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public function handle_export(): void {
        $this->verify_request();

        @ini_set( 'memory_limit', '2048M' );
        set_time_limit( 0 );

        $step   = sanitize_key( $_POST['step'] ?? 'init' );
        $tmp    = $this->sanitize_path( $_POST['temp_dir'] ?? '' );
        $zip    = $this->sanitize_path( $_POST['zip_path'] ?? '' );
        $config = $this->sanitize_config( $_POST['config_data'] ?? [] );

        $type = $config['export_mode'] ?? 'full';
        $cur  = get_site_url();
        $tar  = $config['url'] ?? '';

        try {
            if ( $step !== 'init' ) $this->check_cancel( $tmp );

            $res = match ( $step ) {
                'init'         => $this->step_init( $type ),
                'db_dump'      => $this->step_db( $tmp, $cur, $tar, $type, $config ),
                'core_files'   => $this->step_core( $tmp, $config, $type ),
                'theme_export' => $this->step_theme( $tmp, $cur, $tar, $type, $config ),
                'zip_download' => $this->step_zip( $tmp, $zip, $type ),
                default        => throw new \Exception( 'Bilinmeyen adım: ' . $step ),
            };

            wp_send_json_success( $res );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public function handle_delete(): void {
        $this->verify_request();
        $last = get_option( self::LAST_EXPORT );
        if ( $last && ! empty( $last['url'] ) ) {
            $file = $this->url_to_path( $last['url'] );
            if ( $file && file_exists( $file ) ) @unlink( $file );
        }
        delete_option( self::LAST_EXPORT );
        wp_send_json_success();
    }

    public function handle_cancel(): void {
        $this->verify_request();
        $tmp = $this->sanitize_path( $_POST['temp_dir'] ?? '' );
        if ( $tmp && is_dir( $tmp ) ) {
            touch( trailingslashit( $tmp ) . '.cancel_flag' );
        }
        wp_send_json_success();
    }

    // =========================================================================
    // STEPS
    // =========================================================================

    private function step_init( string $type ): array {
        $base = trailingslashit( wp_upload_dir()['basedir'] ) . 'site_exports';
        if ( ! is_dir( $base ) ) wp_mkdir_p( $base );

        $token = date( 'Ymd_His' );
        $tmp   = $base . '/temp_' . $token;
        wp_mkdir_p( $tmp );

        $steps = [ 'init' ];
        if ( $type === 'full' || $type === 'db' ) $steps[] = 'db_dump';
        if ( $type === 'full' ) $steps[] = 'core_files';
        $steps[] = 'theme_export';
        $steps[] = 'zip_download';

        $next = $type === 'theme' ? 'theme_export' : 'db_dump';

        return [
            'next_step'    => $next,
            'temp_dir'     => $tmp,
            'zip_path'     => $base . '/export_' . $token . '.zip',
            'active_steps' => $steps,
            'log'          => "SYSTEM: Workspace ready. Mode: {$type}",
        ];
    }

    private function step_db( string $tmp, string $cur, string $tar, string $type, array $cfg ): array {
        $slug     = get_stylesheet();
        $filename = $slug . '-' . date( 'Ymd-His' ) . '.sql';
        $sql_path = $tmp . '/' . $filename;
        $prefix   = $cfg['table_prefix'] ?? 'wp_';

        $this->export_database( $sql_path, $cur, $tar, $tmp, $prefix );

        $next = $type === 'db' ? 'zip_download' : 'core_files';
        return [ 'next_step' => $next, 'log' => "DATABASE: {$filename} created." . ( $prefix ? " (Prefix: {$prefix})" : '' ) ];
    }

    private function step_core( string $tmp, array $cfg, string $type ): array {
        if ( $type !== 'full' ) {
            return [ 'next_step' => 'theme_export', 'log' => 'SYSTEM: Core step skipped.' ];
        }

        $abs  = untrailingslashit( ABSPATH );
        $logs = [];

        if ( ! empty( $cfg['root_files'] ) ) {
            foreach ( scandir( $abs ) as $f ) {
                if ( is_file( "{$abs}/{$f}" ) && $f !== 'wp-config.php' ) {
                    copy( "{$abs}/{$f}", "{$tmp}/{$f}" );
                }
            }
            $logs[] = 'ROOT: Root files copied.';
        }

        if ( ! empty( $cfg['wp_config'] ) ) {
            $c = file_get_contents( "{$abs}/wp-config.php" );
            foreach ( [ 'DB_NAME' => 'db', 'DB_USER' => 'user', 'DB_PASSWORD' => 'pass' ] as $const => $key ) {
                if ( ! empty( $cfg[ $key ] ) ) {
                    $c = preg_replace(
                        "/(define\s*\(\s*['\"]" . $const . "['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/",
                        '${1}' . addslashes( $cfg[ $key ] ) . '${3}',
                        $c
                    );
                }
            }
            if ( ! empty( $cfg['table_prefix'] ) ) {
                $c = preg_replace( "/(\\\$table_prefix\s*=\s*['\"])(.*?)(['\"]\s*;)/", '${1}' . $cfg['table_prefix'] . '${3}', $c );
            }
            file_put_contents( "{$tmp}/wp-config.php", $c );
            $logs[] = 'CONFIG: wp-config.php updated.';
        }

        if ( ! empty( $cfg['wp_admin'] ) ) {
            $this->copy_recursive( "{$abs}/wp-admin", "{$tmp}/wp-admin", false, '', '', $tmp );
            $logs[] = 'CORE: /wp-admin copied.';
        }
        if ( ! empty( $cfg['wp_includes'] ) ) {
            $this->copy_recursive( "{$abs}/wp-includes", "{$tmp}/wp-includes", false, '', '', $tmp );
            $logs[] = 'CORE: /wp-includes copied.';
        }

        return [ 'next_step' => 'theme_export', 'log' => implode( "\n", $logs ) ];
    }

    private function step_theme( string $tmp, string $cur, string $tar, string $type, array $cfg ): array {
        $logs = [];

        if ( $type === 'full' && ! empty( $cfg['wp_content'] ) ) {
            $this->copy_recursive( trailingslashit( WP_CONTENT_DIR ), "{$tmp}/wp-content", true, $cur, $tar, $tmp );
            $logs[] = 'CONTENT: /wp-content copied with URL replacements.';
        } elseif ( $type === 'theme' ) {
            $slug = get_stylesheet();
            $this->copy_recursive( trailingslashit( get_stylesheet_directory() ), "{$tmp}/{$slug}", true, $cur, $tar, $tmp );
            $logs[] = "THEME: {$slug} copied.";
        }

        return [ 'next_step' => 'zip_download', 'log' => implode( "\n", $logs ) ];
    }

    private function step_zip( string $tmp, string $zip_path, string $type ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \Exception( 'ZipArchive extension is not installed.' );
        }

        // zip_path boşsa otomatik oluştur
        if ( empty( $zip_path ) ) {
            $zip_path = $tmp . '.zip';
        }

        $this->check_cancel( $tmp );

        $z = new \ZipArchive();
        if ( $z->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            throw new \Exception( 'Cannot create ZIP file.' );
        }

        $root  = realpath( $tmp );
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $files as $file ) {
            $this->check_cancel( $tmp );
            if ( ! $file->isReadable() ) continue;
            $real     = $file->getRealPath();
            $relative = str_replace( '\\', '/', substr( $real, strlen( $root ) + 1 ) );
            $file->isDir() ? $z->addEmptyDir( $relative ) : $z->addFile( $real, $relative );
        }

        $z->close();
        $this->rmdir_recursive( $tmp );

        // Eski export'u sil
        $old = get_option( self::LAST_EXPORT );
        if ( $old && ! empty( $old['url'] ) ) {
            $old_file = $this->url_to_path( $old['url'] );
            if ( $old_file && file_exists( $old_file ) ) @unlink( $old_file );
        }

        $url = wp_upload_dir()['baseurl'] . '/site_exports/' . basename( $zip_path );
        update_option( self::LAST_EXPORT, [
            'url'  => $url,
            'type' => $type,
            'date' => wp_date( 'd.m.Y H:i:s' ),
            'size' => size_format( filesize( $zip_path ), 2 ),
        ] );

        return [
            'zip_url'   => $url,
            'next_step' => 'done',
            'log'       => "ZIP: Package created.\nSYSTEM: Cleanup done.",
        ];
    }

    // =========================================================================
    // DATABASE EXPORT — Chunk'lı, safe replace
    // =========================================================================

    private function export_database( string $path, string $cur, string $tar, string $tmp, string $target_prefix = '' ): void {
        global $wpdb;
        $current_prefix = $wpdb->prefix;
        $live_url       = ! empty( $tar ) ? $tar : $cur;

        if ( ! class_exists( 'PDO' ) ) {
            throw new \Exception( 'PDO extension is not installed.' );
        }

        $pdo = new \PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASSWORD,
            [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC ]
        );

        $tables = $pdo->query( 'SHOW TABLES' )->fetchAll( \PDO::FETCH_COLUMN );

        $header = "-- WordPress MySQL Export\n-- Target URL: {$live_url}\n"
                . "-- Prefix: {$current_prefix} -> {$target_prefix}\n"
                . "-- Date: " . date( 'Y-m-d H:i:s' ) . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        file_put_contents( $path, $header );

        $bad_collations = [ 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin' ];

        foreach ( $tables as $table ) {
            $this->check_cancel( $tmp );

            $target_table = $table;
            if ( $target_prefix && str_starts_with( $table, $current_prefix ) ) {
                $target_table = $target_prefix . substr( $table, strlen( $current_prefix ) );
            }

            // CREATE TABLE
            $create     = $pdo->query( "SHOW CREATE TABLE `{$table}`" )->fetch();
            $create_sql = $create['Create Table'];
            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$target_table}`", $create_sql );
            foreach ( $bad_collations as $coll ) {
                $create_sql = str_ireplace( $coll, 'utf8mb4_unicode_ci', $create_sql );
            }

            $sql = "DROP TABLE IF EXISTS `{$target_table}`;\n{$create_sql};\n\n";
            file_put_contents( $path, $sql, FILE_APPEND );

            // DATA — chunk'lı fetch
            $total  = (int) $pdo->query( "SELECT COUNT(*) FROM `{$table}`" )->fetchColumn();
            $offset = 0;
            $buffer = '';

            while ( $offset < $total ) {
                $this->check_cancel( $tmp );

                $rows = $pdo->query( "SELECT * FROM `{$table}` LIMIT " . self::DB_CHUNK . " OFFSET {$offset}" )->fetchAll();

                foreach ( $rows as $row ) {
                    $values = [];
                    foreach ( $row as $key => $val ) {
                        if ( $val === null ) {
                            $values[] = 'NULL';
                            continue;
                        }

                        // Prefix replace (usermeta/options meta_key/option_name)
                        if ( $target_prefix ) {
                            $is_meta = str_contains( $table, 'usermeta' ) || str_contains( $table, 'options' );
                            if ( $is_meta && is_string( $val ) && str_starts_with( $val, $current_prefix ) ) {
                                $val = $target_prefix . substr( $val, strlen( $current_prefix ) );
                            }
                        }

                        // URL replace (serialized/JSON safe)
                        $val = $this->safe_replace( $cur, $tar, $val );
                        $values[] = $pdo->quote( $val );
                    }

                    $buffer .= "INSERT INTO `{$target_table}` VALUES (" . implode( ', ', $values ) . ");\n";
                }

                // Buffer'ı diske yaz
                if ( $buffer !== '' ) {
                    foreach ( $bad_collations as $coll ) {
                        $buffer = str_ireplace( $coll, 'utf8mb4_unicode_ci', $buffer );
                    }
                    file_put_contents( $path, $buffer, FILE_APPEND );
                    $buffer = '';
                }

                $offset += self::DB_CHUNK;
            }

            file_put_contents( $path, "\n", FILE_APPEND );
        }

        file_put_contents( $path, "SET FOREIGN_KEY_CHECKS = 1;\n", FILE_APPEND );
    }


    // =========================================================================
    // SAFE REPLACE — Serialized + JSON + plain string
    // =========================================================================

    private function safe_replace( string $search, string $replace, mixed $data ): mixed {
        if ( empty( $data ) || is_numeric( $data ) || empty( $search ) ) return $data;

        if ( is_string( $data ) ) {
            // Serialized
            if ( $this->is_serialized( $data ) ) {
                $unserialized = @unserialize( $data );
                if ( $unserialized !== false || $data === 'b:0;' ) {
                    return serialize( $this->safe_replace( $search, $replace, $unserialized ) );
                }
            }

            // JSON
            $json = json_decode( $data, true );
            if ( is_array( $json ) && json_last_error() === JSON_ERROR_NONE ) {
                array_walk_recursive( $json, function ( &$v ) use ( $search, $replace ) {
                    if ( is_string( $v ) ) $v = str_replace( $search, $replace, $v );
                } );
                return json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }

            // Plain string
            $data = str_replace( $search, $replace, $data );
            $data = str_replace( str_replace( '/', '\\/', $search ), str_replace( '/', '\\/', $replace ), $data );
            return $data;
        }

        if ( is_array( $data ) ) {
            foreach ( $data as $k => $v ) {
                $data[ $k ] = $this->safe_replace( $search, $replace, $v );
            }
            return $data;
        }

        if ( is_object( $data ) ) {
            // Timber\PostArrayObject gibi readonly objeler için array'e çevir
            if ( $data instanceof \Timber\PostArrayObject || $data instanceof \ArrayObject ) {
                $arr = (array) $data;
                foreach ( $arr as $k => $v ) {
                    $arr[ $k ] = $this->safe_replace( $search, $replace, $v );
                }
                return $arr;
            }
            $clone = clone $data;
            foreach ( get_object_vars($data) as $k => $v ) {
                $clone->$k = $this->safe_replace( $search, $replace, $v );
            }
            return $clone;
        }

        return $data;
    }

    private function is_serialized( string $data ): bool {
        $data = trim( $data );
        if ( $data === 'N;' ) return true;
        if ( ! preg_match( '/^([adObis]):/', $data, $m ) ) return false;
        return match ( $m[1] ) {
            'a', 'O', 's' => (bool) preg_match( "/^{$m[1]}:[0-9]+:.*[;}]\$/s", $data ),
            'b', 'i', 'd' => (bool) preg_match( "/^{$m[1]}:[0-9.E+-]+;\$/", $data ),
            default        => false,
        };
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function verify_request(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        if ( ! check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD, false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid security token. Please refresh the page.' ] );
        }
    }

    private function sanitize_config( array $raw ): array {
        $clean = [];
        $text_fields = [ 'export_mode', 'db', 'user', 'pass', 'url', 'table_prefix' ];
        $bool_fields = [ 'wp_includes', 'wp_admin', 'wp_content', 'root_files', 'wp_config' ];

        foreach ( $text_fields as $f ) {
            $clean[ $f ] = isset( $raw[ $f ] ) ? sanitize_text_field( $raw[ $f ] ) : '';
        }
        foreach ( $bool_fields as $f ) {
            $clean[ $f ] = ! empty( $raw[ $f ] ) && $raw[ $f ] === 'true';
        }

        return $clean;
    }

    private function sanitize_path( string $path ): string {
        if ( empty( $path ) ) return '';
        // realpath sadece mevcut dosyalar için çalışır — zip henüz oluşturulmamış olabilir
        $resolved = realpath( $path );
        $check    = $resolved ?: $path;
        $base_dir = realpath( wp_upload_dir()['basedir'] );
        // Mevcut dosya yoksa parent dir'i kontrol et
        if ( ! $resolved ) {
            $parent = realpath( dirname( $path ) );
            if ( $base_dir && $parent && str_starts_with( $parent, $base_dir ) ) {
                return $path; // Parent uploads içinde, path güvenli
            }
        }
        if ( $base_dir && $resolved && ! str_starts_with( $resolved, $base_dir ) ) {
            return '';
        }
        return $check;
    }

    private function url_to_path( string $url ): string {
        $upload = wp_upload_dir();
        return str_replace( $upload['baseurl'], $upload['basedir'], $url );
    }

    private function check_cancel( string $tmp ): void {
        if ( empty( $tmp ) || ! is_dir( $tmp ) ) return;
        $flag = trailingslashit( $tmp ) . '.cancel_flag';
        if ( file_exists( $flag ) ) {
            $this->rmdir_recursive( $tmp );
            throw new \Exception( 'CANCELLED: Operation stopped by user.' );
        }
    }

    private function copy_recursive( string $src, string $dst, bool $replace = false, string $cur = '', string $tar = '', string $tmp = '' ): void {
        if ( $tmp ) $this->check_cancel( $tmp );
        if ( ! is_dir( $dst ) ) wp_mkdir_p( $dst );

        foreach ( scandir( $src ) as $f ) {
            if ( $f === '.' || $f === '..' || $f === 'site_exports' ) continue;
            if ( $tmp ) $this->check_cancel( $tmp );

            $s = "{$src}/{$f}";
            $d = "{$dst}/{$f}";

            if ( is_dir( $s ) ) {
                $this->copy_recursive( $s, $d, $replace, $cur, $tar, $tmp );
            } else {
                copy( $s, $d );
                if ( $replace && $cur && $tar ) {
                    $ext = pathinfo( $d, PATHINFO_EXTENSION );
                    if ( in_array( $ext, [ 'php', 'css', 'json', 'sql', 'js' ], true ) ) {
                        $c = @file_get_contents( $d );
                        if ( $c ) {
                            $c = str_replace(
                                [ $cur, str_replace( '/', '\\/', $cur ) ],
                                [ $tar, str_replace( '/', '\\/', $tar ) ],
                                $c
                            );
                            @file_put_contents( $d, $c );
                        }
                    }
                }
            }
        }
    }

    private function rmdir_recursive( string $dir ): void {
        if ( ! is_dir( $dir ) ) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $it as $f ) {
            $f->isDir() ? @rmdir( $f->getRealPath() ) : @unlink( $f->getRealPath() );
        }
        @rmdir( $dir );
    }

    // =========================================================================
    // ADMIN UI — Terminal modal + progress
    // =========================================================================

    public function render_ui(): void {
        $last  = get_option( self::LAST_EXPORT );
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        ?>
        <style>
            #export-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:999999;justify-content:center;align-items:center;font-family:'JetBrains Mono',Consolas,monospace;color:#c8d6e5}
            .export-terminal{background:#0d1117;border:1px solid #30363d;border-radius:12px;width:780px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.5)}
            .export-header{display:flex;align-items:center;gap:8px;padding:14px 20px;border-bottom:1px solid #21262d;background:#161b22;border-radius:12px 12px 0 0}
            .export-header .dot{width:12px;height:12px;border-radius:50%}
            .export-header .dot-red{background:#ff5f57}.export-header .dot-yellow{background:#febc2e}.export-header .dot-green{background:#28c840}
            .export-header .title{margin-left:12px;font-size:13px;color:#8b949e;flex:1}
            .export-header .close-x{cursor:pointer;color:#8b949e;font-size:18px;padding:0 4px;border:none;background:none;display:none}
            .export-header .close-x:hover{color:#f85149}
            .export-progress{padding:16px 20px 0}
            .export-bar-bg{background:#21262d;height:6px;border-radius:3px;overflow:hidden}
            .export-bar-fill{width:0;height:100%;background:linear-gradient(90deg,#238636,#3fb950);transition:width .4s ease;border-radius:3px}
            .export-bar-label{font-size:11px;color:#8b949e;margin-top:6px;text-align:right}
            .export-log{flex:1;overflow-y:auto;padding:16px 20px;font-size:12px;line-height:1.7;min-height:300px;max-height:400px}
            .export-log .log-ok{color:#3fb950}.export-log .log-err{color:#f85149}.export-log .log-info{color:#58a6ff}.export-log .log-warn{color:#d29922}.export-log .log-sys{color:#8b949e}
            .export-footer{display:flex;gap:12px;padding:16px 20px;border-top:1px solid #21262d;justify-content:flex-end}
            .export-footer .ebtn{padding:8px 20px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #30363d;background:#21262d;color:#c8d6e5;text-decoration:none;transition:all .2s}
            .export-footer .ebtn:hover{background:#30363d}
            .export-footer .ebtn-cancel{border-color:#f85149;color:#f85149;margin-right:auto}.export-footer .ebtn-cancel:hover{background:#f85149;color:#fff}
            .export-footer .ebtn-dl{border-color:#238636;color:#3fb950}.export-footer .ebtn-dl:hover{background:#238636;color:#fff}
            .export-last{font-size:12px;color:#8b949e;margin-top:8px;padding:8px 12px;background:#161b22;border-radius:6px;border:1px solid #21262d}
            .export-last a{color:#58a6ff;text-decoration:none}.export-last a:hover{text-decoration:underline}
            .export-last .del-link{color:#f85149;margin-left:12px}
        </style>

        <script>
        jQuery(document).ready(function($){
            const NONCE = '<?php echo esc_js( $nonce ); ?>';
            let eTmp='', eZip='', activeSteps=[];

            // Last export info
            <?php if ( $last ) : ?>
            const lastHtml = '<div class="export-last">Last export: <?php echo esc_js( $last['date'] ); ?> (<?php echo esc_js( $last['size'] ?? '' ); ?>) &mdash; <a href="<?php echo esc_url( $last['url'] ); ?>" target="_blank">Download</a><a href="#" class="del-link delete-last-export">Delete</a></div>';
            $('.acf-field[data-name="start"] .acf-input').append(lastHtml);
            <?php endif; ?>

            $(document).on('click','.delete-last-export',function(e){
                e.preventDefault();
                if(!confirm('Delete export file?')) return;
                $.post(ajaxurl,{action:'<?php echo self::AJAX_DELETE; ?>',<?php echo self::NONCE_FIELD; ?>:NONCE},()=>$('.export-last').fadeOut(300,function(){$(this).remove()}));
            });

            function createModal(){
                if($('#export-overlay').length) return;
                $('body').append(`
                <div id="export-overlay">
                    <div class="export-terminal">
                        <div class="export-header">
                            <span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span>
                            <span class="title">Site Export Terminal</span>
                            <button class="close-x" title="Close">&times;</button>
                        </div>
                        <div class="export-progress">
                            <div class="export-bar-bg"><div class="export-bar-fill"></div></div>
                            <div class="export-bar-label">Initializing...</div>
                        </div>
                        <div class="export-log"></div>
                        <div class="export-footer">
                            <button class="ebtn ebtn-cancel">Cancel</button>
                            <a href="#" class="ebtn ebtn-dl" target="_blank" style="display:none">Download ZIP</a>
                        </div>
                    </div>
                </div>`);
            }

            function log(msg, type='sys'){
                const cls = {ok:'log-ok',err:'log-err',info:'log-info',warn:'log-warn',sys:'log-sys'}[type]||'log-sys';
                msg.split('\n').forEach(line=>{
                    if(!line.trim()) return;
                    let t='sys';
                    if(/^\[OK\]|OK|created|copied|updated|done/i.test(line)) t='ok';
                    else if(/ERROR|HATA|CANCEL|failed/i.test(line)) t='err';
                    else if(/SYSTEM|MODE|INIT|ZIP/i.test(line)) t='info';
                    else if(/WARN|skip/i.test(line)) t='warn';
                    const c = {ok:'log-ok',err:'log-err',info:'log-info',warn:'log-warn',sys:'log-sys'}[t];
                    $('.export-log').append(`<div class="${c}"><span style="opacity:.5">$</span> ${line}</div>`);
                });
                $('.export-log').scrollTop(99999);
            }

            function progress(step){
                if(!activeSteps.length) return;
                const i = activeSteps.indexOf(step);
                const pct = Math.round(((i+1)/activeSteps.length)*100);
                $('.export-bar-fill').css('width',pct+'%');
                $('.export-bar-label').text(step.replace(/_/g,' ').toUpperCase()+' — '+pct+'%');
            }

            function done(zipUrl){
                $('.ebtn-cancel').hide();
                $('.ebtn-dl').attr('href',zipUrl).show();
                $('.close-x').show();
                $('.export-bar-fill').css('width','100%');
                $('.export-bar-label').text('COMPLETE — 100%');
            }

            function fail(msg){
                log(msg,'err');
                $('.ebtn-cancel').hide();
                $('.close-x').show();
                $('.export-bar-label').text('FAILED');
                $('.export-bar-fill').css({'width':'100%','background':'#f85149'});
            }

            function runStep(step){
                progress(step);
                $.post(ajaxurl,{
                    action:'<?php echo self::AJAX_EXPORT; ?>',
                    <?php echo self::NONCE_FIELD; ?>:NONCE,
                    step:step, temp_dir:eTmp, zip_path:eZip,
                    config_data:{
                        export_mode: $('[data-name="options"] select').val()||'full',
                        wp_includes: $('[data-name="wp-includes"] input').is(':checked'),
                        wp_admin:    $('[data-name="wp-admin"] input').is(':checked'),
                        wp_content:  $('[data-name="wp-content"] input').is(':checked'),
                        root_files:  $('[data-name="root-files"] input').is(':checked'),
                        wp_config:   $('[data-name="wp-config"] input').is(':checked'),
                        db:   $('[data-name="database"] input').val()||'',
                        user: $('[data-name="user"] input').val()||'',
                        pass: $('[data-name="pass"] input').val()||'',
                        url:  $('[data-name="url"] input').val()||'',
                        table_prefix: $('[data-name="table_prefix"] input').val()||''
                    }
                },function(r){
                    if(r.success){
                        if(step==='init'){activeSteps=r.data.active_steps;eTmp=r.data.temp_dir;eZip=r.data.zip_path;}
                        if(r.data.log) log(r.data.log,'ok');
                        if(r.data.next_step==='done') done(r.data.zip_url);
                        else runStep(r.data.next_step);
                    } else fail(r.data?.message||'Unknown error');
                }).fail(()=>fail('AJAX request failed.'));
            }

            // Start button
            $(document).on('click','.acf-field[data-name="start"] button, .acf-field[data-name="start"] a',function(e){
                e.preventDefault();
                createModal();
                $('#export-overlay').fadeIn(200).css('display','flex');
                $('.ebtn-cancel').show();$('.ebtn-dl,.close-x').hide();
                $('.export-log').empty();
                $('.export-bar-fill').css({'width':'0','background':''});
                eTmp='';eZip='';activeSteps=[];
                runStep('init');
            });

            // Cancel
            $(document).on('click','.ebtn-cancel',function(){
                if(!confirm('Cancel export?')) return;
                $.post(ajaxurl,{action:'<?php echo self::AJAX_CANCEL; ?>',<?php echo self::NONCE_FIELD; ?>:NONCE,temp_dir:eTmp});
                log('CANCEL signal sent...','warn');
            });

            // Close — sadece modal'ı gizle, sayfa refresh YOK
            $(document).on('click','.close-x',function(){
                $('#export-overlay').fadeOut(200);
            });
        });
        </script>
        <?php
    }
}

// Init
add_action( 'admin_init', static function () {
    if ( class_exists( 'Theme_Site_Exporter' ) ) {
        new Theme_Site_Exporter();
    }
} );
