<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Theme_Site_Exporter {

    const ACF_GROUP_NAME        = 'export_theme';
    const ACF_PUBLISH_URL_FIELD = 'url';
    const ACF_OPTIONS_FIELD     = 'options';
    const AJAX_ACTION_NAME      = 'theme_site_export_process';
    const LAST_EXPORT_OPTION    = 'theme_site_last_export_info';

    public function __construct() {
        add_action( 'wp_ajax_theme_site_export_process', array( $this, 'handle_export_request' ) );
        add_action( 'wp_ajax_theme_site_export_delete', array( $this, 'handle_delete_export' ) );
        add_action( 'wp_ajax_theme_site_export_cancel', array( $this, 'handle_cancel_request' ) );
        if ( isset($_GET['page']) && $_GET['page'] === 'development' ) {
            add_action( 'admin_footer', array( $this, 'output_admin_scripts' ) );
        }
    }

    public function handle_cancel_request() {
        $temp_dir = $_POST['temp_dir'] ?? '';
        if ($temp_dir && is_dir($temp_dir)) {
            touch(trailingslashit($temp_dir) . '.cancel_flag');
        }
        wp_send_json_success();
    }

    private function check_cancelation($temp_dir) {
        if (!$temp_dir || !is_dir($temp_dir)) return;
        $cancel_file = trailingslashit($temp_dir) . '.cancel_flag';
        if (file_exists($cancel_file)) {
            $this->rmdir_r($temp_dir); 
            throw new Exception('TERMINATED: İşlem kullanıcı tarafından durduruldu.');
        }
    }

    public function handle_export_request() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkisiz!' );
        @ini_set('memory_limit', '2048M');
        set_time_limit( 0 );

        $step        = $_POST['step'] ?? 'init';
        $temp_dir    = $_POST['temp_dir'] ?? null;
        $zip_path    = $_POST['zip_path'] ?? null;
        $config_data = $_POST['config_data'] ?? null;

        $export_type = $config_data['export_mode'] ?? 'full';
        $target_url  = $config_data[self::ACF_PUBLISH_URL_FIELD] ?? '';
        $current_url = get_site_url();

        try {
            if($step !== 'init') $this->check_cancelation($temp_dir);

            switch ( $step ) {
                case 'init': $res = $this->step_initiate($export_type); break;
                case 'db_dump': $res = $this->step_db($temp_dir, $current_url, $target_url, $export_type, $config_data); break;
                case 'core_files': $res = $this->step_core($temp_dir, $config_data, $export_type); break;
                case 'theme_export': $res = $this->step_theme($temp_dir, $current_url, $target_url, $export_type, $config_data); break;
                case 'zip_download': $res = $this->step_finalize($temp_dir, $zip_path, $export_type); break;
                default: throw new Exception('Adım bulunamadı.');
            }
            wp_send_json_success($res);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    private function step_initiate($type) {
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']) . 'site_exports';
        if (!is_dir($base_dir)) wp_mkdir_p($base_dir);

        $token = date('Ymd_His');
        $tmp = $base_dir . '/temp_' . $token;
        wp_mkdir_p($tmp);
    
        $steps = ['init'];
        if ($type === 'full' || $type === 'db') $steps[] = 'db_dump';
        if ($type === 'full') $steps[] = 'core_files';
        $steps[] = 'theme_export';
        $steps[] = 'zip_download';

        $next = ($type === 'theme') ? 'theme_export' : 'db_dump';

        return [
            'next_step' => $next,
            'temp_dir'  => $tmp,
            'zip_path'  => $base_dir . '/export_' . $token . '.zip',
            'active_steps' => $steps,
            'log'       => "SİSTEM: Çalışma alanı hazır. Mod: $type"
        ];
    }

    private function step_db($tmp, $cur, $tar, $type, $data) {
        $theme_slug = get_stylesheet();
        $db_filename = $theme_slug . '-' . date('Ymd-His') . '.sql';
        $sql_path = $tmp . '/' . $db_filename;
        
        $target_prefix = !empty($data['table_prefix']) ? $data['table_prefix'] : 'wp_';
        
        $this->export_db_logic($sql_path, $cur, $tar, $tmp, $target_prefix);
        $next = ($type === 'db') ? 'zip_download' : 'core_files';
        return ['next_step' => $next, 'log' => "DATABASE: [OK] $db_filename oluşturuldu." . ($target_prefix ? " (Prefix: $target_prefix)" : "")];
    }

    private function step_core($tmp, $data, $type) {
        if ($type !== 'full') return ['next_step' => 'theme_export', 'log' => 'SİSTEM: Core adımı atlandı.'];
        $abs = untrailingslashit(ABSPATH);
        $logs = [];
        if ( !empty($data['root_files']) && $data['root_files'] === 'true' ){
            foreach (scandir($abs) as $f) {
                if (is_file("$abs/$f") && !in_array($f, ['wp-config.php'])) copy("$abs/$f", "$tmp/$f");
            }
            $logs[] = "ROOT: Ana dizin dosyaları kopyalandı.";
        }
        if ( !empty($data['wp_config']) && $data['wp_config'] === 'true' ){
            $c = file_get_contents("$abs/wp-config.php");
            if (!empty($data['db'])) $c = preg_replace("/(define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/", "$1".$data['db']."$3", $c);
            if (!empty($data['user'])) $c = preg_replace("/(define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/", "$1".$data['user']."$3", $c);
            if (!empty($data['pass'])) $c = preg_replace("/(define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/", "$1".$data['pass']."$3", $c);
            
            // table_prefix Güncellemesi
            if (!empty($data['table_prefix'])) {
                $c = preg_replace("/(\$table_prefix\s*=\s*['\"])(.*?)(['\"]\s*;)/", "$1".$data['table_prefix']."$3", $c);
            }

            file_put_contents("$tmp/wp-config.php", $c);
            $logs[] = "CONFIG: wp-config.php güncellendi.";
        }
        if ( !empty($data['wp_admin']) && $data['wp_admin'] === 'true' ) {
            $this->copy_r("$abs/wp-admin", "$tmp/wp-admin", false, '', '', $tmp);
            $logs[] = "CORE: /wp-admin kopyalandı.";
        }
        if ( !empty($data['wp_includes']) && $data['wp_includes'] === 'true' ) {
            $this->copy_r("$abs/wp-includes", "$tmp/wp-includes", false, '', '', $tmp);
            $logs[] = "CORE: /wp-includes kopyalandı.";
        }
        return ['next_step' => 'theme_export', 'log' => implode("\n", $logs)];
    }

    private function step_theme($tmp, $cur, $tar, $type, $data) {
        $logs = [];
        if ($type === 'full') {
            if ( !empty($data['wp_content']) && $data['wp_content'] === 'true' ){
                $this->copy_r(trailingslashit(WP_CONTENT_DIR), "$tmp/wp-content", true, $cur, $tar, $tmp);
                $logs[] = "CONTENT: /wp-content ve URL değişimleri tamam.";
            }
        } elseif ($type === 'theme') {
            $slug = get_stylesheet();
            $this->copy_r(trailingslashit(get_stylesheet_directory()), "$tmp/$slug", true, $cur, $tar, $tmp);
            $logs[] = "THEME: Sadece tema ($slug) kopyalandı.";
        }
        return ['next_step' => 'zip_download', 'log' => implode("\n", $logs)];
    }

    private function step_finalize($tmp, $zip_p, $type) {
        if ($tmp) $this->check_cancelation($tmp); 
        $z = new ZipArchive();
        if ($z->open($zip_p, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new Exception("Zip hatası.");
        $rootPath = realpath($tmp);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            if ($tmp) $this->check_cancelation($tmp); 
            if (!$file->isReadable()) continue;
            $filePath = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($filePath, strlen($rootPath) + 1));
            $file->isDir() ? $z->addEmptyDir($relativePath) : $z->addFile($filePath, $relativePath);
        }
        $z->close();
        $this->rmdir_r($tmp); 

        $old = get_option(self::LAST_EXPORT_OPTION);
        if ($old && !empty($old['url'])) {
            $old_f = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $old['url']);
            if (file_exists($old_f)) @unlink($old_f);
        }
        $upload_url = wp_upload_dir()['baseurl'] . '/site_exports/' . basename($zip_p);
        update_option(self::LAST_EXPORT_OPTION, ['url' => $upload_url, 'type' => $type, 'date' => wp_date('d.m.Y H:i:s')]);
        return ['zip_url' => $upload_url, 'next_step' => 'done', 'log' => "> ZIP: Paketleme bitti.\n> SİSTEM: Temizlik tamam."];
    }

    private function copy_r($s, $d, $rep=false, $cur='', $tar='', $tmp_dir='') {
        if ($tmp_dir) $this->check_cancelation($tmp_dir); 
        if(!is_dir($d)) wp_mkdir_p($d);
        foreach (scandir($s) as $f) {
            if ($tmp_dir) $this->check_cancelation($tmp_dir); 
            if ($f === '.' || $f === '..' || $f === 'site_exports') continue;
            $src = "$s/$f"; $dst = "$d/$f";
            if (is_dir($src)) {
                $this->copy_r($src, $dst, $rep, $cur, $tar, $tmp_dir);
            } else {
                copy($src, $dst);
                if($rep && $cur && $tar) {
                    $ext = pathinfo($dst, PATHINFO_EXTENSION);
                    if(in_array($ext, ['php', 'css', 'json', 'sql', 'js'])) {
                        $c = @file_get_contents($dst);
                        if($c) {
                            $c = str_replace([$cur, str_replace('/','\\/',$cur)], [$tar, str_replace('/','\\/',$tar)], $c);
                            @file_put_contents($dst, $c);
                        }
                    }
                }
            }
        }
    }

    /*private function export_db_logic($path, $cur, $tar, $tmp, $target_prefix = '') {
        global $wpdb;
        $db_host = DB_HOST; $db_name = DB_NAME; $db_user = DB_USER; $db_pass = DB_PASSWORD;
        $current_prefix = $wpdb->prefix;
        $live_url = !empty($tar) ? $tar : $cur;

        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $header = "-- WordPress MySQL Export\n-- Target URL: {$live_url}\n-- Prefix Change: {$current_prefix} -> {$target_prefix}\n-- " . date("Y-m-d H:i:s") . "\n\n";
            file_put_contents($path, $header);
            $unwanted = ['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin', 'utf8mb4_0900_ai_ci_520', 'utf8mb4_general_ci'];
            
            foreach ($tables as $table) {
                $this->check_cancelation($tmp);
                
                // Tablo ismini değiştir (Eğer prefix değişimi istendiyse)
                $target_table_name = $table;
                if (!empty($target_prefix) && strpos($table, $current_prefix) === 0) {
                    $target_table_name = $target_prefix . substr($table, strlen($current_prefix));
                }

                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $create_sql = $create['Create Table'];
                
                // CREATE TABLE içinde tablo ismini ve collation'ları düzelt
                $table_sql = "DROP TABLE IF EXISTS `$target_table_name`;\n";
                $create_sql = str_replace("CREATE TABLE `$table`", "CREATE TABLE `$target_table_name`", $create_sql);
                
                foreach($unwanted as $coll) { $create_sql = str_ireplace($coll, 'utf8mb4_unicode_ci', $create_sql); }
                $table_sql .= $create_sql . ";\n\n";
                
                file_put_contents($path, $table_sql, FILE_APPEND);
                
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
                foreach ($rows as $row) {
                    $this->check_cancelation($tmp);
                    
                    // Buradaki array_map artık hem key hem value alıyor (Internal Prefix Fix için)
                    $keys = array_keys($row);
                    $values = array_map(function ($val, $key) use ($pdo, $cur, $tar, $current_prefix, $target_prefix, $table) {
                        if ($val === null) return 'NULL';
                        $val = (string)$val;

                        //usermeta ve options tablolarında, verinin kendisi prefix ile başlıyorsa (wp_capabilities gibi)
                        // onu yeni prefix ile değiştiriyoruz ki yetki sorunu yaşanmasın.
                        if (!empty($target_prefix)) {
                            $is_meta_table = (strpos($table, 'usermeta') !== false);
                            $is_options_table = (strpos($table, 'options') !== false);

                            if ($is_meta_table || $is_options_table) {
                                // meta_key veya option_name sütunundaki veriyi kontrol et
                                if (strpos($val, $current_prefix) === 0) {
                                    $val = $target_prefix . substr($val, strlen($current_prefix));
                                }
                            }
                        }

                        // URL Değişim işlemleri (Standart ve JSON)
                        $json = json_decode($val, true);
                        if (is_array($json)) {
                            array_walk_recursive($json, function (&$i) use ($cur, $tar) {
                                if (is_string($i)) $i = str_replace($cur, $tar, $i);
                            });
                            $val = json_encode($json);
                        } else {
                            $val = str_replace($cur, $tar, $val);
                        }
                        $val = str_replace(str_replace('/', '\\/', $cur), str_replace('/', '\\/', $tar), $val);
                        
                        return $pdo->quote($val);
                    }, $row, $keys);
                    
                    $insert_sql = "INSERT INTO `$target_table_name` VALUES (" . implode(", ", $values) . ");\n";
                    foreach($unwanted as $coll) { $insert_sql = str_ireplace($coll, 'utf8mb4_unicode_ci', $insert_sql); }
                    file_put_contents($path, $insert_sql, FILE_APPEND);
                }
                file_put_contents($path, "\n", FILE_APPEND);
            }
        } catch (Exception $e) { throw new Exception("DB: " . $e->getMessage()); }
    }*/

    private function export_db_logic($path, $cur, $tar, $tmp, $target_prefix = '') {
        global $wpdb;
        $db_host = DB_HOST; $db_name = DB_NAME; $db_user = DB_USER; $db_pass = DB_PASSWORD;
        $current_prefix = $wpdb->prefix;
        $live_url = !empty($tar) ? $tar : $cur;

        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $header = "-- WordPress MySQL Export\n-- Target URL: {$live_url}\n-- Prefix Change: {$current_prefix} -> {$target_prefix}\n-- " . date("Y-m-d H:i:s") . "\n\n";
            file_put_contents($path, $header);
            $unwanted = ['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin', 'utf8mb4_0900_ai_ci_520', 'utf8mb4_general_ci'];
            
            foreach ($tables as $table) {
                $this->check_cancelation($tmp);
                
                $target_table_name = $table;
                if (!empty($target_prefix) && strpos($table, $current_prefix) === 0) {
                    $target_table_name = $target_prefix . substr($table, strlen($current_prefix));
                }

                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $create_sql = $create['Create Table'];
                $table_sql = "DROP TABLE IF EXISTS `$target_table_name`;\n";
                $create_sql = str_replace("CREATE TABLE `$table`", "CREATE TABLE `$target_table_name`", $create_sql);
                foreach($unwanted as $coll) { $create_sql = str_ireplace($coll, 'utf8mb4_unicode_ci', $create_sql); }
                $table_sql .= $create_sql . ";\n\n";
                file_put_contents($path, $table_sql, FILE_APPEND);
                
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
                foreach ($rows as $row) {
                    $this->check_cancelation($tmp);
                    
                    $keys = array_keys($row);
                    $values = array_map(function ($val, $key) use ($pdo, $cur, $tar, $current_prefix, $target_prefix, $table) {
                        if ($val === null) return 'NULL';
                        
                        if (!empty($target_prefix)) {
                            $is_meta_table = (strpos($table, 'usermeta') !== false);
                            $is_options_table = (strpos($table, 'options') !== false);
                            if ($is_meta_table || $is_options_table) {
                                if (is_string($val) && strpos($val, $current_prefix) === 0) {
                                    $val = $target_prefix . substr($val, strlen($current_prefix));
                                }
                            }
                        }

                        $val = $this->safe_recursive_replace_internal($cur, $tar, $val);
                        return $pdo->quote($val);
                    }, $row, $keys);
                    
                    $insert_sql = "INSERT INTO `$target_table_name` VALUES (" . implode(", ", $values) . ");\n";
                    foreach($unwanted as $coll) { $insert_sql = str_ireplace($coll, 'utf8mb4_unicode_ci', $insert_sql); }
                    file_put_contents($path, $insert_sql, FILE_APPEND);
                }
                file_put_contents($path, "\n", FILE_APPEND);
            }
        } catch (Exception $e) { throw new Exception("DB: " . $e->getMessage()); }
    }

    private function safe_recursive_replace_internal($search, $replace, $data) {
        if (empty($data) || is_numeric($data)) return $data;

        // 1. String ise Serialized veya JSON kontrolü yap
        if (is_string($data)) {
            // Serialized kontrolü
            if ($this->is_serialized_internal($data)) {
                $unserialized = @unserialize($data);
                if ($unserialized !== false) {
                    return serialize($this->safe_recursive_replace_internal($search, $replace, $unserialized));
                }
            }

            // JSON kontrolü
            $json = json_decode($data, true);
            if (is_array($json) && (json_last_error() == JSON_ERROR_NONE)) {
                array_walk_recursive($json, function (&$i) use ($search, $replace) {
                    if (is_string($i)) $i = str_replace($search, $replace, $i);
                });
                return json_encode($json);
            }

            // Düz String ise replace yap
            $data = str_replace($search, $replace, $data);
            $data = str_replace(str_replace('/', '\\/', $search), str_replace('/', '\\/', $replace), $data);
            return $data;
        }

        // 2. Array ise içini dön
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->safe_recursive_replace_internal($search, $replace, $value);
            }
            return $data;
        }

        // 3. Object ise (Hata buradaydı, artık nesneler json_decode'a girmeyecek)
        if (is_object($data)) {
            $new_data = clone $data;
            foreach ($data as $key => $value) {
                $new_data->$key = $this->safe_recursive_replace_internal($search, $replace, $value);
            }
            return $new_data;
        }

        return $data;
    }

    private function is_serialized_internal($data) {
        if (!is_string($data)) return false;
        $data = trim($data);
        if ('N;' === $data) return true;
        if (!preg_match('/^([adObis]):/', $data, $badions)) return false;
        switch ($badions[1]) {
            case 'a': case 'O': case 's':
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) return true;
                break;
            case 'b': case 'i': case 'd':
                if (preg_match("/^{$badions[1]}:[0-9.E+-]+;\$/", $data)) return true;
                break;
        }
        return false;
    }

    private function rmdir_r($d) {
        if(!is_dir($d)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        @rmdir($d);
    }

    public function handle_delete_export() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $last = get_option(self::LAST_EXPORT_OPTION);
        if ($last && !empty($last['url'])) {
            $f = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $last['url']);
            if (file_exists($f)) unlink($f);
        }
        delete_option(self::LAST_EXPORT_OPTION);
        wp_send_json_success();
    }

    public function output_admin_scripts() {
        $last = get_option(self::LAST_EXPORT_OPTION);
        ?>
        <style>
            #export-ui-wrap { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:999999; justify-content:center; align-items:center; color:#00ff00; font-family:monospace; }
            .export-box { background:#0a0a0a; padding:30px; border:1px solid #00ff00; width:750px; }
            .status-list { background:#000; padding:15px; border:1px solid #222; height:350px; overflow-y:auto; margin:20px 0; font-size:12px; }
            .bar-bg { background:#111; height:8px; border:1px solid #00ff00; margin-bottom:15px; }
            .bar-fill { width:0; height:100%; background:#00ff00; transition:0.3s; }
            .btns { display:flex; gap:15px; justify-content: flex-end; }
            .btn { padding:10px 25px; cursor:pointer; text-decoration:none; border:1px solid #00ff00; background:transparent; color:#00ff00; font-size:12px; font-weight:bold; }
            .btn:hover { background:#00ff00; color:#000; }
            .btn-cancel { border-color:#ff0000; color:#ff0000; margin-right:auto; }
            .btn-cancel:hover { background:#ff0000; color:#fff; }
            .last-info-box { font-size: 13px; color:#666; border-top:1px solid #333; margin-top:10px; padding-top:10px; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            const lastHtml = <?php echo $last ? json_encode('<div class="last-info-box">> SON EXPORT: '.$last['date'].' - <a href="'.$last['url'].'" style="color:#00ff00;">İNDİR</a> | <a href="#" class="delete-last-export" style="color:#ff0000;">SİL</a></div>') : '""'; ?>;
            if(lastHtml !== "") $('.acf-field[data-name="start"] .acf-input').append(lastHtml);

            $(document).on('click', '.delete-last-export', function(e) {
                e.preventDefault();
                if(confirm('Dosya silinsin mi?')) $.post(ajaxurl, { action: 'theme_site_export_delete' }, () => window.location.reload());
            });

            $(document).on('click', '.btn-cancel', function() {
                if(confirm('İŞLEM DURDURULSUN MU?')) {
                    $.post(ajaxurl, { action: 'theme_site_export_cancel', temp_dir: window.eTmp });
                    $('.status-list').append('<div style="color:red">> DURDURMA EMRİ GÖNDERİLDİ...</div>');
                }
            });

            $(document).on('click', '.acf-field[data-name="start"] button, .acf-field[data-name="start"] a', function(e) {
                e.preventDefault();
                if(!$('#export-ui-wrap').length) {
                    $('body').append('<div id="export-ui-wrap"><div class="export-box"><h3>> TERMINAL_V4</h3><div class="bar-bg"><div class="bar-fill"></div></div><div class="status-list"></div><div class="btns"><button class="btn btn-cancel">CANCEL</button><a href="#" class="btn dl-link" target="_blank" style="display:none">DOWNLOAD ZIP</a><button class="btn close-ui" style="display:none">CLOSE</button></div></div></div>');
                }
                $('#export-ui-wrap').fadeIn(300).css('display','flex');
                $('.btn-cancel').show(); $('.dl-link, .close-ui').hide();
                $('.status-list').empty();

                const runStep = (step) => {
                    $.post(ajaxurl, { 
                        action: 'theme_site_export_process', 
                        step: step, 
                        temp_dir: window.eTmp || '', 
                        zip_path: window.eZip || '',
                        config_data: {
                            export_mode: $('[data-name="options"] select').val(),
                            wp_includes: $('[data-name="wp-includes"] input').is(':checked'),
                            wp_admin:    $('[data-name="wp-admin"] input').is(':checked'),
                            wp_content:  $('[data-name="wp-content"] input').is(':checked'),
                            root_files:  $('[data-name="root-files"] input').is(':checked'),
                            wp_config:   $('[data-name="wp-config"] input').is(':checked'),
                            db:   $('[data-name="database"] input').val(),
                            user: $('[data-name="user"] input').val(),
                            pass: $('[data-name="pass"] input').val(),
                            url:  $('[data-name="url"] input').val(),
                            table_prefix: $('[data-name="table_prefix"] input').val() // Yeni Alan
                        }
                    }, (r) => {
                        if(r.success) {
                            if(step === 'init') {
                                window.activeSteps = r.data.active_steps;
                                window.eTmp = r.data.temp_dir;
                                window.eZip = r.data.zip_path;
                            }
                            if(window.activeSteps) {
                                let currentIndex = window.activeSteps.indexOf(step);
                                let progress = Math.round(((currentIndex + 1) / window.activeSteps.length) * 100);
                                $('.bar-fill').css('width', progress + '%');
                            }
                            if(r.data.log) {
                                r.data.log.split('\n').forEach(line => { $('.status-list').append(`<div>> ${line}</div>`); });
                                $('.status-list').scrollTop(99999);
                            }
                            if(r.data.next_step === 'done') {
                                $('.btn-cancel').hide(); $('.dl-link').attr('href', r.data.zip_url).show(); $('.close-ui').show();
                            } else runStep(r.data.next_step);
                        } else {
                            $('.status-list').append(`<div style="color:red">> HATA: ${r.data.message || 'Bilinmeyen hata'}</div>`);
                            $('.btn-cancel').hide(); $('.close-ui').show();
                        }
                    }).fail(() => {
                        $('.status-list').append(`<div style="color:red">> SİSTEM HATASI: AJAX isteği başarısız.</div>`);
                        $('.btn-cancel').hide(); $('.close-ui').show();
                    });
                };
                runStep('init');
            });
            $(document).on('click', '.close-ui', function() { window.location.reload(); });
        });
        </script>
        <?php
    }
}

add_action('admin_init', function() {
    //if ( isset($_GET['page']) && $_GET['page'] === 'development' ) {
        if (class_exists('Theme_Site_Exporter')) {
            new Theme_Site_Exporter();
        }
    //}
});