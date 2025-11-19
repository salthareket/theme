<?php

class Theme_Site_Exporter {

    // **ACF Alan Sabitleri**
    const ACF_EXPORT_TYPE_FIELD = 'export_theme_options'; // Kullanıcının sağladığı değeri korundu
    const ACF_PUBLISH_URL_FIELD = 'publish_url';  // Kullanıcının sağladığı değeri korundu
    
    // AJAX Action Adı
    const AJAX_ACTION_NAME = 'theme_site_export_process';

    public function __construct() {
        add_action( 'wp_ajax_' . self::AJAX_ACTION_NAME, array( $this, 'handle_export_request' ) );
        add_action( 'admin_footer', array( $this, 'output_admin_scripts' ) );
    }

    // ---------------------- 1. ANA İŞ AKIŞI (PHP) ----------------------

    /**
     * AJAX isteği ile tetiklenen ana dışa aktarma fonksiyonu.
     */
    public function handle_export_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Yetkisiz erişim.' );
            wp_die();
        }
        set_time_limit( 0 );

        $export_type = get_field( self::ACF_EXPORT_TYPE_FIELD, 'option' );
        // get_option kullanımı, ACF options sayfasında kayıtlı veriyi çekerken daha güvenlidir.
        $target_url  = get_option( 'options_' . self::ACF_PUBLISH_URL_FIELD ); 
        $current_url = get_site_url();

        try {
            $temp_dir = $this->create_temp_directory();
        } catch ( Exception $e ) {
            wp_send_json_error( ['message' => 'Hata: Geçici klasör oluşturulamadı.'] );
            wp_die();
        }

        $zip_file_name = 'site-export-' . date( 'Ymd_His' ) . '.zip';
        $zip_path      = trailingslashit( $temp_dir ) . $zip_file_name;

        // İçerik Dışa Aktarma Mantığı
        try {
            if ( in_array($export_type, ['full', 'theme']) ) {
                $this->export_theme_folder( $temp_dir, $current_url, $target_url );
            }
            if ( in_array($export_type, ['full', 'db']) ) {
                $this->export_database( $temp_dir, $current_url, $target_url );
            }
            if ( !in_array($export_type, ['full', 'theme', 'db']) ) {
                 throw new Exception( 'Geçersiz dışa aktarma tipi seçildi.' );
            }
        } catch ( Exception $e ) {
            $this->cleanup_temp_directory( $temp_dir );
            wp_send_json_error( ['message' => 'Dışa aktarma hatası: ' . $e->getMessage()] );
            wp_die();
        }

        // Sıkıştırma (ZIP) İşlemi
        try {
            $this->create_zip_archive( $temp_dir, $zip_path );
        } catch ( Exception $e ) {
            $this->cleanup_temp_directory( $temp_dir );
            wp_send_json_error( ['message' => 'ZIP hatası: ' . $e->getMessage()] );
            wp_die();
        }

        // İndirme ve Temizlik
        $this->initiate_download( $zip_path, $temp_dir ); 
    }

    // ---------------------- 2. JAVASCRIPT ÇIKTISI (AJAX Tetikleyicisi) ----------------------

    public function output_admin_scripts() {
        if ( ! is_admin() || ! wp_script_is( 'jquery', 'done' ) ) {
            return;
        }

        $ajax_action_name = self::AJAX_ACTION_NAME; 
        $button_selector = '.acf-field[data-name="start"] button'; 
        ?>
        <script>
            jQuery(document).ready(function ($) {
                
                var $button = jQuery(<?php echo json_encode($button_selector); ?>);

                if (!$button.length) {
                    $button = $('.acf-field-button button');
                }
                
                $button.on('click', function (e) {
                    
                    e.preventDefault(); 
                    var $btn = $(this);
                    
                    $btn.prop('disabled', true).text('Dışa Aktarılıyor...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: '<?php echo esc_js($ajax_action_name); ?>'
                        },
                        xhrFields: {
                            responseType: 'blob' 
                        },
                        success: function (blob, status, xhr) {
                            
                            // 1. JSON Hata Kontrolü
                            var contentType = xhr.getResponseHeader('content-type');
                            if (contentType && contentType.indexOf('application/json') > -1) {
                                var reader = new FileReader();
                                reader.onload = function() {
                                    var response = JSON.parse(reader.result);
                                    if(response.data && response.data.message){
                                        alert('Hata: ' + response.data.message);
                                    } else {
                                        alert('Bilinmeyen bir hata oluştu (JSON yanıtı).');
                                    }
                                    $btn.prop('disabled', false).text('Hata Oluştu, Tekrar Dene');
                                };
                                reader.readAsText(blob);
                                return;
                            }
                            
                            // 2. Blob Boyutu Kontrolü (TypeError'ı önler)
                            if (blob.size === 0) {
                                alert('İndirme Başarısız: Sunucudan geçerli bir dosya alınamadı. Lütfen PHP loglarını kontrol edin.');
                                $btn.prop('disabled', false).text('Hata Oluştu, Tekrar Dene');
                                return;
                            }
                            
                            // 3. Başarılı ZIP indirme mantığı
                            var filename = "site-export.zip";
                            var disposition = xhr.getResponseHeader('Content-Disposition');
                            if (disposition) {
                                var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                                var matches = filenameRegex.exec(disposition);
                                if (matches != null && matches[1]) {
                                    filename = matches[1].replace(/['"]/g, '');
                                }
                            }
                            
                            var link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);

                            $btn.prop('disabled', false).text('Dışa Aktarma Başarılı! (Tekrar Başlat)');
                        },
                        error: function () {
                            alert('Beklenmedik bir sunucu hatası oluştu (AJAX).');
                            $btn.prop('disabled', false).text('Hata Oluştu, Tekrar Dene');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    // ---------------------- 3. YARDIMCI METODLAR ----------------------

    /**
     * Tüm URL kaçış varyasyonlarını (JSON ve SQL dahil) değiştiren mantık.
     */
    private function replace_urls_in_content(string $content, string $local_url, string $live_url): string {
        
        // 1. Normal replacement (http:// -> https://)
        $content = str_replace($local_url, $live_url, $content);

        // 2. JSON/PHP escaped replacement (http:\/\/ veya http:\\/\\/ formatlarını yakalar)
        // '\\/' kullanmak, PHP dizesinde literal olarak `\/` veya `\\/` aramak anlamına gelir, bu da JSON kaçışını doğru yakalar.
        $local_escaped = str_replace('/', '\\/', $local_url);
        $live_escaped = str_replace('/', '\\/', $live_url);
        $content = str_replace($local_escaped, $live_escaped, $content);

        // 3. Regex ile kalanları yakalama (daha az verimli, ancak kenar durumları kapsar)
        $pattern = preg_quote($local_url, '#');
        $content = preg_replace('#' . $pattern . '#', $live_url, $content);
        
        return $content;
    }

    /**
     * Aktif tema klasörünü kopyalar ve YALNIZCA belirtilen dosyalarda URL değiştirme yapar.
     */
    private function export_theme_folder( $temp_dir, $current_url, $target_url ) {
        $theme_slug = get_stylesheet();
        $source_dir = trailingslashit( get_stylesheet_directory() );
        $destination_dir = trailingslashit( $temp_dir ) . $theme_slug;
        $replace_required = ! empty( $target_url ) && ( $target_url !== $current_url );
        wp_mkdir_p( $destination_dir );

        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );

        foreach ( $iterator as $item ) {
            $sub_path  = $iterator->getSubPathName();
            $dest_path = $destination_dir . '/' . $sub_path;

            if ( $item->isDir() ) {
                wp_mkdir_p( $dest_path );
            } else {
                copy( $item->getRealPath(), $dest_path );
                // Yalnızca belirlenen hedeflerde URL değiştirme
                if ( $replace_required && $this->is_file_in_targeted_paths( $sub_path ) ) {
                    $content = file_get_contents( $dest_path );
                    $content = $this->replace_urls_in_content($content, $current_url, $target_url);
                    file_put_contents( $dest_path, $content );
                }
            }
        }
        return true;
    }

    /**
     * Kullanıcının belirttiği spesifik dosya ve klasörleri kontrol eder.
     */
    private function is_file_in_targeted_paths(string $relative_path): bool {
        $relative_path = str_replace('\\', '/', $relative_path); 

        // 1. theme/static/data/header-footer-options.json
        if ( $relative_path === 'theme/static/data/header-footer-options.json' ) return true;
        // 2. theme/static/data/theme-styles klasoru içindeki json dosyaları
        if ( preg_match('#^theme/static/data/theme-styles/.+\.json$#i', $relative_path) ) return true;
        // 3. theme/static/css klasorundeki css dosyaları 
        if ( preg_match('#^static/css/[^/]+\.css$#i', $relative_path) ) return true;
        // 4. theme/static/css/cache klasorundeki css dosyaları
        if ( preg_match('#^static/css/cache/.+\.css$#i', $relative_path) ) return true;
        return false;
    }
    
    /**
     * Veritabanını SQL dosyası olarak dışa aktarır ve URL'leri değiştirir.
     */
    private function export_database( $temp_dir, $current_url, $target_url ) {
        $sql_file_path = trailingslashit( $temp_dir ) . DB_NAME . '_export.sql';
        $db_name = DB_NAME; $db_user = DB_USER; $db_pass = DB_PASSWORD; $db_host = DB_HOST;
        $local_url = $current_url; $live_url = !empty($target_url) ? $target_url : $local_url;

        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,]);
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sql_dump = "-- WordPress MySQL Export\n-- Target URL: {$live_url}\n-- " . date("Y-m-d H:i:s") . "\n\n";

            foreach ($tables as $table) {
                $create_table_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n" . $create_table_stmt['Create Table'] . ";\n\n";
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    foreach ($rows as $row) {
                        $values = array_map(function ($val) use ($pdo, $local_url, $live_url) {
                            if (!isset($val)) return 'NULL'; $val = (string)$val; 
                            
                            // JSON/Serialized Verideki URL Değişimi
                            $json_decoded = json_decode($val, true);
                            if (is_array($json_decoded)) {
                                array_walk_recursive($json_decoded, function (&$item) use ($local_url, $live_url) {
                                    if (is_string($item) && str_contains($item, $local_url)) { 
                                        // Normal URL değişimi
                                        $item = str_replace($local_url, $live_url, $item); 
                                    }
                                });
                                // DÜZELTME: JSON_UNESCAPED_SLASHES kaldırıldı. 
                                // Bu, JSON'ın slashes (\/) kaçışını korumasını sağlar, bu da DB'den beklenen formattır.
                                $val = json_encode($json_decoded); 
                            } else { 
                                $val = str_replace($local_url, $live_url, $val); 
                            }
                            return $pdo->quote($val);
                        }, array_values($row));
                        $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
                    }
                    $sql_dump .= "\n";
                }
            }
            // Genel kaçış karakterli URL'ler için son bir kontrol
            $sql_dump = $this->replace_urls_in_content($sql_dump, $local_url, $live_url);
            
            // Collation düzeltmeleri 
            $unwanted_collations = [ 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin', 'utf8mb4_0900_ai_ci_520', 'utf8mb4_general_ci' ];
            $replacement_collation = 'utf8mb4_unicode_ci';
            foreach($unwanted_collations as $collation) { $sql_dump = str_ireplace($collation, $replacement_collation, $sql_dump); }
            
            file_put_contents($sql_file_path, $sql_dump);
            
        } catch (Exception $e) { throw new Exception("Veritabanı dökümü sırasında hata: " . $e->getMessage()); }
        return true;
    }
    
    // ... Diğer yardımcı metotlar (create_temp_directory, create_zip_archive, cleanup_temp_directory, initiate_download) eksiksiz olarak devam eder ...

    private function create_temp_directory() {
        $upload_dir = wp_upload_dir();
        $temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'site_exports/temp_' . uniqid(); 
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            throw new Exception( 'İzin hatası. Uploads klasörüne yazma izniniz olduğundan emin olun.' );
        }
        return $temp_dir;
    }

    private function create_zip_archive( $source_dir, $zip_path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new Exception( 'Sunucunuzda ZipArchive PHP modülü aktif değil.' );
        }
        $zip = new ZipArchive();
        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            throw new Exception( 'ZIP dosyası açılamadı/oluşturulamadı.' );
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $base_length = strlen( $source_dir . '/' );
        foreach ( $files as $name => $file ) {
            $relativePath = substr( $name, $base_length );
            if ( $file->isDir() ) {
                $zip->addEmptyDir( $relativePath );
            } else {
                $zip->addFile( $name, $relativePath );
            }
        }
        $zip->close();
    }
    
    private function cleanup_temp_directory( $dir ) {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( $object != "." && $object != ".." ) {
                    $path = $dir . DIRECTORY_SEPARATOR . $object;
                    if ( is_dir( $path ) && ! is_link( $path ) ) {
                        $this->cleanup_temp_directory( $path );
                    } else {
                        unlink( $path );
                    }
                }
            }
            rmdir( $dir ); 
        }
    }
    
    private function initiate_download( $zip_path, $temp_dir ) {
        if ( file_exists( $zip_path ) ) {
            if ( ob_get_level() ) {
                ob_end_clean();
            }
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
            header( 'Content-Length: ' . filesize( $zip_path ) );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
            readfile( $zip_path );
        }
        $this->cleanup_temp_directory( $temp_dir ); 
        exit;
    }
}

new Theme_Site_Exporter();