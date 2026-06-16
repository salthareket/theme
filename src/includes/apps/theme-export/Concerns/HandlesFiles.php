<?php

namespace SaltHareket\ThemeExport\Concerns;

/**
 * HandlesFiles
 *
 * Dosya kopyalama, ZIP oluşturma, temizlik, güvenlik.
 *
 * @version 1.0.0
 */
trait HandlesFiles
{
    /**
     * Export klasörü — theme/static/data/exports/
     * Web'den erişilemez (.htaccess ile korunur).
     */
    public static function getExportDir(): string
    {
        return untrailingslashit( get_stylesheet_directory() ) . '/theme/static/data/exports';
    }

    /**
     * Export klasörünü oluştur ve güvenlik dosyalarını yaz.
     */
    public static function ensureExportDir(): string
    {
        $dir = self::getExportDir();

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // .htaccess — tüm erişimi engelle
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
        }

        // index.php — PHP erişimini engelle
        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, '<?php // Silence is golden.' );
        }

        return $dir;
    }

    /**
     * Export dosyasını PHP üzerinden indir (direkt URL erişimi yok).
     */
    public static function streamDownload( string $file_path ): void
    {
        if ( ! file_exists( $file_path ) ) {
            wp_die( 'Export file not found.' );
        }

        $filename = basename( $file_path );
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $file_path ) );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        flush();
        readfile( $file_path );
        exit();
    }

    /**
     * Recursive dosya kopyalama — URL replace ile.
     * Büyük dizinler için SplFileInfo kullanır.
     */
    protected function copyRecursive(
        string $src,
        string $dst,
        bool   $replace = false,
        string $cur     = '',
        string $tar     = '',
        string $tmp_dir = '',
        array  $exclude = []
    ): void {
        if ( $tmp_dir ) $this->checkCancel( $tmp_dir );
        if ( ! is_dir( $dst ) ) wp_mkdir_p( $dst );

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $src, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            if ( $tmp_dir ) $this->checkCancel( $tmp_dir );

            $relative = str_replace( rtrim( $src, '/\\' ) . DIRECTORY_SEPARATOR, '', $item->getRealPath() );
            $relative = str_replace( '\\', '/', $relative );

            // Exclude kontrolü
            foreach ( $exclude as $pattern ) {
                if ( fnmatch( $pattern, $relative ) || fnmatch( $pattern, basename( $relative ) ) ) {
                    continue 2;
                }
            }

            $dest_path = $dst . '/' . $relative;

            if ( $item->isDir() ) {
                if ( ! is_dir( $dest_path ) ) wp_mkdir_p( $dest_path );
                continue;
            }

            // Dosyayı kopyala
            @copy( $item->getRealPath(), $dest_path );

            // URL replace (sadece metin dosyaları)
            if ( $replace && $cur && $tar ) {
                $ext = strtolower( $item->getExtension() );
                if ( in_array( $ext, [ 'php', 'css', 'json', 'sql', 'js', 'html', 'htm', 'xml', 'txt' ], true ) ) {
                    $content = @file_get_contents( $dest_path );
                    if ( $content !== false && str_contains( $content, $cur ) ) {
                        $content = str_replace(
                            [ $cur, str_replace( '/', '\\/', $cur ) ],
                            [ $tar, str_replace( '/', '\\/', $tar ) ],
                            $content
                        );
                        @file_put_contents( $dest_path, $content );
                    }
                }
            }
        }
    }

    /**
     * Klasörü ZIP'e ekle.
     */
    protected function createZip( string $src_dir, string $zip_path, string $tmp_dir ): void
    {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \Exception( 'ZipArchive extension is not installed.' );
        }

        $z = new \ZipArchive();
        if ( $z->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            throw new \Exception( 'Cannot create ZIP file: ' . $zip_path );
        }

        $root  = realpath( $src_dir );
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $files as $file ) {
            $this->checkCancel( $tmp_dir );
            if ( ! $file->isReadable() ) continue;

            $real     = $file->getRealPath();
            $relative = str_replace( '\\', '/', substr( $real, strlen( $root ) + 1 ) );

            $file->isDir() ? $z->addEmptyDir( $relative ) : $z->addFile( $real, $relative );
        }

        $z->close();
    }

    /**
     * Klasörü recursive sil.
     */
    protected function rmdirRecursive( string $dir ): void
    {
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

    /**
     * Cancel flag kontrolü.
     */
    protected function checkCancel( string $tmp_dir ): void
    {
        if ( empty( $tmp_dir ) || ! is_dir( $tmp_dir ) ) return;
        if ( file_exists( trailingslashit( $tmp_dir ) . '.cancel_flag' ) ) {
            $this->rmdirRecursive( $tmp_dir );
            throw new \Exception( 'CANCELLED: Export stopped by user.' );
        }
    }

    /**
     * Path güvenlik kontrolü — export klasörü dışına çıkamaz.
     */
    protected function sanitizePath( string $path ): string
    {
        if ( empty( $path ) ) return '';

        $export_dir = realpath( self::getExportDir() );
        $resolved   = realpath( $path );

        // Mevcut değilse parent'ı kontrol et
        if ( ! $resolved ) {
            $parent = realpath( dirname( $path ) );
            if ( $export_dir && $parent && str_starts_with( $parent, $export_dir ) ) {
                return $path;
            }
            return '';
        }

        if ( $export_dir && ! str_starts_with( $resolved, $export_dir ) ) {
            return '';
        }

        return $resolved;
    }

    /**
     * Varsayılan exclude pattern'leri.
     */
    protected function getDefaultExcludes(): array
    {
        return [
            'node_modules',
            '.git',
            '.svn',
            'vendor',
            '.DS_Store',
            'Thumbs.db',
            '*.log',
            'site_exports',
            'exports',
            '.cache',
            '__pycache__',
        ];
    }
}
