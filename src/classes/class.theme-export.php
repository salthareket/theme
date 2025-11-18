<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Doğrudan erişimi engelle
}

class Theme_Site_Exporter {

    // **ACF Alan Sabitleri**
    const ACF_EXPORT_TYPE_FIELD = 'export_theme_options';
    const ACF_PUBLISH_URL_FIELD = 'export_theme_url';
    
    // AJAX Action Adı
    const AJAX_ACTION_NAME = 'theme_site_export_process';

    public function __construct() {
        // AJAX çağrılarını dinle
        add_action( 'wp_ajax_' . self::AJAX_ACTION_NAME, array( $this, 'handle_export_request' ) );
        // JavaScript/CSS çıktısını yönetici footer'ına bas
        add_action( 'admin_footer', array( $this, 'output_admin_scripts' ) );
    }

    // ---------------------- 1. ANA İŞ AKIŞI (PHP - ADIM ADIM) ----------------------

    /**
     * AJAX isteği ile tetiklenen ana dışa aktarma fonksiyonu.
     * Step parametresine göre sıradaki işlemi belirler.
     */
    public function handle_export_request() {
        // 1. Yetki ve zaman limiti ayarı
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Yetkisiz erişim.' );
        }
        set_time_limit( 0 );

        // 2. POST verilerini temizleme ve alma
        $step     = isset( $_POST['step'] ) ? sanitize_text_field( $_POST['step'] ) : 'init';
        $temp_dir = isset( $_POST['temp_dir'] ) ? sanitize_text_field( $_POST['temp_dir'] ) : null;
        $zip_path = isset( $_POST['zip_path'] ) ? sanitize_text_field( $_POST['zip_path'] ) : null;

        // 3. ACF/Option verilerini alma
        $export_type = get_field( self::ACF_EXPORT_TYPE_FIELD, 'option' );
        $target_url  = get_option( 'options_' . self::ACF_PUBLISH_URL_FIELD ); // Hedef URL
        $current_url = get_site_url();

        try {
            // 🚨 KRİTİK KONTROL BURADA 🚨
            if ( empty( $target_url ) ) {
                throw new Exception( 'Hedef URL (Yayınlama URL\'si) boş olamaz.' );
            }

            switch ( $step ) {
                case 'init':
                    $response = $this->step_initiate_export( $export_type, $current_url, $target_url );
                    break;
                case 'db_dump':
                    $response = $this->step_export_database( $temp_dir, $current_url, $target_url, $export_type );
                    break;
                case 'theme_export':
                    $response = $this->step_export_theme( $temp_dir, $current_url, $target_url, $export_type );
                    break;
                case 'zip_download':
                    // Bu adım doğrudan indirmeyi tetikler ve exit çağırır. JSON yanıtı beklenmez.
                    $this->step_create_zip_and_download( $temp_dir, $zip_path ); 
                    break;
                default:
                    throw new Exception( 'Bilinmeyen dışa aktarma adımı.' );
            }

            // zip_download haricindeki adımlar JSON yanıtı döndürmelidir
            if ($step !== 'zip_download') {
                wp_send_json_success( $response );
            }
        } catch ( Exception $e ) {
            // Hata durumunda geçici dizin temizlenir
            if ( $temp_dir ) {
                 $this->cleanup_temp_directory( $temp_dir );
            }
            // Hata mesajını JavaScript'e gönder
            wp_send_json_error( [ 'message' => 'Dışa aktarma hatası: ' . $e->getMessage() ] );
        }
        wp_die();
    }
    
    // ---------------------- ADIM FONKSİYONLARI ----------------------

    private function step_initiate_export( $export_type, $current_url, $target_url ) {
        $temp_dir = $this->create_temp_directory();
        $zip_file_name = 'site-export-' . date( 'Ymd_His' ) . '.zip';
        $zip_path      = trailingslashit( $temp_dir ) . $zip_file_name;

        // İlk veri işleme adımını belirle (Seçime göre DB veya Tema)
        if ( in_array( $export_type, [ 'full', 'db' ] ) ) {
             $next_step = 'db_dump';
        } elseif ( in_array( $export_type, [ 'full', 'theme' ] ) ) {
             $next_step = 'theme_export';
        } else {
             throw new Exception( 'Geçersiz dışa aktarma tipi seçildi. (Hiçbir şey seçilmedi)' );
        }

        return [
            'message'  => 'Dışa aktarma başlatıldı.',
            'next_step' => $next_step,
            'temp_dir'  => $temp_dir,
            'zip_path'  => $zip_path,
        ];
    }
    
    private function step_export_database( $temp_dir, $current_url, $target_url, $export_type ) {
        if ( in_array( $export_type, [ 'full', 'db' ] ) ) {
            // DB işlemini yap
            $this->export_database( $temp_dir, $current_url, $target_url );
        }

        // DB işi bitti (veya atlandı), sıradaki adım Tema'dır
        if ( in_array( $export_type, [ 'full', 'theme' ] ) ) {
             $next_step = 'theme_export';
        } else {
             // Tema da dahil değilse, doğrudan ZIP'e git
             $next_step = 'zip_download';
        }

        return [
            'message'  => 'Veritabanı dökümü tamamlandı (ya da atlandı).',
            'next_step' => $next_step,
        ];
    }

    private function step_export_theme( $temp_dir, $current_url, $target_url, $export_type ) {
        if ( in_array( $export_type, [ 'full', 'theme' ] ) ) {
            // Tema işlemini yap
            $this->export_theme_folder( $temp_dir, $current_url, $target_url );
        }
        
        // Tema işi bitti (veya atlandı), sıradaki adım ZIP'tir
        $next_step = 'zip_download';

        return [
            'message'  => 'Tema dosyaları dışa aktarıldı ve güncellendi (ya da atlandı).',
            'next_step' => $next_step,
        ];
    }

    private function step_create_zip_and_download( $temp_dir, $zip_path ) {
        // Sıkıştırma (ZIP) İşlemi
        $this->create_zip_archive( $temp_dir, $zip_path );

        // İndirme ve Temizlik (Bu, exit çağırır ve JS indirmesini tetikler)
        $this->initiate_download( $zip_path, $temp_dir ); 
    }

    // ---------------------- 2. JAVASCRIPT & CSS (PROGRESS BAR UX) ----------------------

    /**
     * Progress Bar'ı, CSS'i ve Sequential AJAX mantığını admin footer'a basar.
     */
    public function output_admin_scripts() {
        if ( ! is_admin() || ! wp_script_is( 'jquery', 'done' ) ) {
            return;
        }

        $ajax_action_name = self::AJAX_ACTION_NAME; 
        $button_selector = '.acf-field[data-name="start"] button'; 
        // ACF alanını hedefleyen selector
        $field_selector = '.acf-field[data-name="start"]'; 
        ?>
        <style>
            /* TALEP: Boş tablo başlığını gizle */
            .acf-field[data-name='export_theme'] table thead {
                display: none;
            }
            .theme-exporter-progress-container {
                display: none;
                margin-top: 0px;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                background-color: #f7f7f7;
            }
            .theme-exporter-progress-bar {
                height: 20px;
                background-color: #e0e0e0;
                border-radius: 3px;
                margin-bottom: 10px;
                overflow: hidden;
            }
            .theme-exporter-progress-bar-fill {
                height: 100%;
                width: 0%;
                background-color: #007cba;
                transition: width 0.4s ease;
                text-align: center;
                color: white;
                line-height: 20px;
                font-size: 11px;
                font-weight: bold;
            }
            .theme-exporter-status-text {
                font-weight: bold;
                color: #333;
                margin-bottom: 5px;
            }
            .theme-exporter-restart {
                display: inline-block;
                margin-top: 10px;
                color: #dc3545;
                text-decoration: underline;
                cursor: pointer;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                
                var $button = jQuery(<?php echo json_encode($button_selector); ?>);
                var $field = jQuery(<?php echo json_encode($field_selector); ?>);
                var ajaxAction = '<?php echo esc_js($ajax_action_name); ?>';
                
                // Progress Bar HTML'i ekle
                var progressHtml = '<div class="theme-exporter-progress-container">' +
                                   '<div class="theme-exporter-status-text"></div>' +
                                   '<div class="theme-exporter-progress-bar"><div class="theme-exporter-progress-bar-fill">0%</div></div>' +
                                   '<p class="theme-exporter-action-text"></p>' +
                                   '<a href="#" class="theme-exporter-restart" style="display:none;">Yeniden Başlat</a>' +
                                   '</div>';
                
                $field.append(progressHtml);
                var $progressContainer = $('.theme-exporter-progress-container');
                var $progressBarFill = $('.theme-exporter-progress-bar-fill');
                var $statusText = $('.theme-exporter-status-text');
                var $actionText = $('.theme-exporter-action-text');
                var $restartLink = $('.theme-exporter-restart');

                var exportState = {
                    temp_dir: null,
                    zip_path: null
                };

                // Adımları tanımla ve progress bar yüzdelerini ayarla
                var steps = {
                    'init': { text: '1/4: Dışa aktarma başlatılıyor...', progress: 10 },
                    'db_dump': { text: '2/4: Veritabanı dökümü alınıyor (URL\'ler güncelleniyor)...', progress: 35 },
                    'theme_export': { text: '3/4: Tema dosyaları kopyalanıyor (Hedef URL\'ler değiştiriliyor)...', progress: 65 },
                    'zip_download': { text: '4/4: Dosyalar ZIPleniyor ve indirme hazırlanıyor...', progress: 95 },
                    'finished': { text: 'Tamamlandı!', progress: 100 }
                };


                function updateProgress(stepName) {
                    var stepData = steps[stepName] || steps['init'];
                    var percentage = stepData.progress;
                    
                    $progressBarFill.css('width', percentage + '%').text(percentage + '%');
                    $statusText.text(stepData.text);
                    $actionText.text(''); // İşlem metnini her adımda temizle
                }
                
                function restartExport() {
                    $progressContainer.hide();
                    $restartLink.hide();
                    $button.show();
                    exportState.temp_dir = null;
                    exportState.zip_path = null;
                    $progressBarFill.css('background-color', '#007cba');
                    updateProgress('init'); 
                }

                function runExportStep(stepName, data) {
                    
                    if (stepName === 'finished') {
                        updateProgress('finished');
                        $progressBarFill.css('background-color', '#28a745'); // Yeşil yap
                        $restartLink.show().text('Yeniden Başlat');
                        $actionText.text('Site dışa aktarma işlemi başarıyla tamamlandı. ZIP dosyası indiriliyor.');
                        return;
                    }

                    // Bir önceki adımdan gelen verileri koru
                    var postData = $.extend({
                        action: ajaxAction,
                        step: stepName,
                        temp_dir: exportState.temp_dir,
                        zip_path: exportState.zip_path
                    }, data);

                    updateProgress(stepName);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: postData,
                        // ZIP indirme adımında responseType'ı blob olarak ayarla
                        xhrFields: (stepName === 'zip_download' ? { responseType: 'blob' } : {}),
                        
                        success: function (response, status, xhr) {
                            if (stepName === 'zip_download') {
                                // ZIP İndirme Adımı
                                handleDownload(response, xhr);
                                
                            } else if (response.success) {
                                // Normal AJAX Adımları
                                
                                // Yeni temp_dir ve zip_path'i kaydet (Sadece 'init' adımında gelir)
                                if (response.data.temp_dir) {
                                    exportState.temp_dir = response.data.temp_dir;
                                    exportState.zip_path = response.data.zip_path;
                                }

                                // Bir sonraki adımı çalıştır
                                var nextStep = response.data.next_step;
                                runExportStep(nextStep, {});

                            } else {
                                // PHP'den gelen JSON hata yanıtı
                                handleError(response.data.message || 'Bilinmeyen bir hata oluştu (JSON yanıtı).');
                            }
                        },
                        error: function (xhr, status, error) {
                            handleError('Beklenmedik bir sunucu hatası oluştu. Durum: ' + status + ' (' + error + ')');
                        }
                    });
                }

                function handleDownload(blob, xhr) {
                    // JSON Hata Kontrolü
                    var contentType = xhr.getResponseHeader('content-type');
                    if (contentType && contentType.indexOf('application/json') > -1) {
                        var reader = new FileReader();
                        reader.onload = function() {
                            var response = JSON.parse(reader.result);
                            handleError(response.data && response.data.message ? response.data.message : 'Bilinmeyen bir hata oluştu (JSON yanıtı).');
                        };
                        reader.readAsText(blob);
                        return;
                    }
                    
                    // Geçerli dosya boyutu kontrolü
                    if (blob.size === 0) {
                        handleError('İndirme Başarısız: Sunucudan geçerli bir dosya alınamadı. PHP loglarını kontrol edin.');
                        return;
                    }

                    // Başarılı ZIP indirme mantığı
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

                    runExportStep('finished', {});
                }

                function handleError(message) {
                    $progressBarFill.css('background-color', '#dc3545'); // Kırmızı
                    $statusText.text('HATA OLUŞTU!');
                    $actionText.text('Detay: ' + message);
                    $restartLink.show().text('Hata Oluştu, Yeniden Dene');
                }

                // Olay Dinleyicileri
                $button.on('click', function (e) {
                    e.preventDefault(); 
                    $button.hide();
                    $progressContainer.show();
                    $progressBarFill.css('background-color', '#007cba');
                    $restartLink.hide();
                    
                    // İlk adımı başlat
                    runExportStep('init', {});
                });
                
                $restartLink.on('click', function(e) {
                    e.preventDefault();
                    restartExport();
                });
                
                // Başlangıç durumunu ayarla
                restartExport();
            });
        </script>
        <?php
    }
    
    // ---------------------- 3. YARDIMCI METODLAR (DB VE URL DEĞİŞİMİ) ----------------------

    /**
     * SQL, JSON (kaçış karakterli) ve genel metinlerde URL değiştirme.
     */
    private function replace_urls_in_content(string $content, string $local_url, string $live_url): string {
        
        // 1. Normal URL'ler
        $content = str_replace($local_url, $live_url, $content);

        // 2. JSON kaçış karakterli URL'ler (http:\/\/ formatı)
        $local_escaped = str_replace('/', '\\/', $local_url);
        $live_escaped = str_replace('/', '\\/', $live_url);
        $content = str_replace($local_escaped, $live_escaped, $content);

        // 3. RegEx ile diğer varyasyonları yakalama (daha az verimli, ancak kenar durumları kapsar)
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
        if ( $relative_path === 'static/data/header-footer-options.json' ) return true;
        // 2. theme/static/data/theme-styles klasoru içindeki json dosyaları
        if ( preg_match('#^static/data/theme-styles/.+\.json$#i', $relative_path) ) return true;
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
                                        $item = str_replace($local_url, $live_url, $item); 
                                    }
                                });
                                // JSON_UNESCAPED_SLASHES kaldırıldı: JSON'ın kaçış karakterlerini (\/) korur.
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
    
    // ---------------------- 4. FİZİKSEL DOSYA YARDIMCILARI ----------------------

    private function create_temp_directory() {
        $upload_dir = wp_upload_dir();
        $temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'site_exports/temp_' . uniqid(); 
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            throw new Exception( 'İzin hatası. Uploads klasörüne yazma izniniz olduğundan emin olun.' );
        }
        return $temp_dir;
    }

    /**
     * Geçici klasördeki içeriği ZIP arşivine sıkıştırır.
     */
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
    
    /**
     * Bir klasörü (ve tüm içeriğini) recursive olarak siler.
     */
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
            @rmdir( $dir ); // Hata oluşsa bile devam et
        }
    }
    
    /**
     * İndirmeyi tetikler ve geçici klasörü temizler.
     */
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