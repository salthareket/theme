<?php

/**
 * PAE Cron Cleanup Task Trait
 *
 * WordPress Cron entegrasyonu ve yetim (orphan) asset temizliği.
 * Günlük otomatik temizlik + manuel AJAX tetikleyici içerir.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor
 * @version    1.0.0
 * @since      1.9.7
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrıldı
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sınıfı içinde kullanılır.
 *   Dışarıdan doğrudan çağrılmaz — sınıf constructor'ı hook'ları kaydeder.
 *
 *   Manuel test (admin-ajax):
 *     POST /wp-admin/admin-ajax.php?action=pae_test_cleanup
 *
 *   Cron zamanlaması:
 *     Her gün 03:00'te 'my_daily_assets_cleanup' hook'u tetiklenir.
 *
 * @example Cron'u manuel tetikle (PHP):
 *   PageAssetsExtractor::run_cleanup_task();
 *
 * @example Cron zamanlamasını kontrol et:
 *   $next = wp_next_scheduled('my_daily_assets_cleanup');
 *   echo date('Y-m-d H:i:s', $next);
 *
 * @example AJAX ile test (JS):
 *   jQuery.post(ajaxurl, { action: 'pae_test_cleanup' }, function(res) {
 *       console.log(res.data.message);
 *   });
 */
trait CleanupTask {

    // =========================================================
    //  CRON SİSTEMİ — OTOMATİK TEMİZLİK
    // =========================================================

    /**
     * WordPress CRON sistemine günlük temizlik görevi ekler.
     * Constructor'da add_action('wp', ...) ile tetiklenir.
     *
     * @return void
     *
     * @example
     *   // Otomatik — constructor'da kayıtlı:
     *   add_action('wp', [__CLASS__, 'schedule_cleanup_event']);
     */
    public static function schedule_cleanup_event(): void {
        if (!wp_next_scheduled('my_daily_assets_cleanup')) {
            $timestamp = strtotime('tomorrow 03:00:00');
            wp_schedule_event($timestamp, 'daily', 'my_daily_assets_cleanup');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAE] CRON zamanlandı: my_daily_assets_cleanup - Her gün 03:00');
            }
        }
    }

    /**
     * CRON görevi tarafından çağrılan ana temizlik fonksiyonu.
     *
     * Güvenlik önlemleri:
     * - Sadece 24 saatten eski dosyaları siler
     * - Veritabanında kayıtlı dosyalara dokunmaz
     * - Manifest'te kayıtlı dosyalara dokunmaz
     * - Limit ile server yükünü kontrol eder
     *
     * @return void
     *
     * @example
     *   // Manuel çalıştır:
     *   PageAssetsExtractor::run_cleanup_task();
     */
    public static function run_cleanup_task(): void {
        $instance = self::get_instance(true);

        $instance->error_log('======================================== CRON TEMİZLİK BAŞLADI ========================================', 'CRON');

        try {
            $instance->error_log('1. Manifest tabanlı temizlik başlıyor...', 'CRON');
            $orphan_count = $instance->purge_orphan_assets();
            $instance->error_log("Manifest temizlik: {$orphan_count} dosya silindi", 'CRON');

            $instance->error_log('2. Veritabanı tabanlı temizlik başlıyor...', 'CRON');
            $result = $instance->cleanup_db_based(500);
            $instance->error_log("Veritabanı temizlik: {$result}", 'CRON');

            $instance->error_log('======================================== CRON TEMİZLİK TAMAMLANDI ========================================', 'CRON');
        } catch (\Exception $e) {
            $instance->error_log('CRON HATA: ' . $e->getMessage(), 'CRON');
        }
    }

    /**
     * AJAX endpoint — Manuel CRON testi için.
     * Sadece manage_options yetkisine sahip kullanıcılar çağırabilir.
     *
     * @return void
     *
     * @example JS:
     *   jQuery.post(ajaxurl, {
     *       action: 'pae_test_cleanup',
     *       _ajax_nonce: paeNonce
     *   }, function(res) { console.log(res.data.message); });
     */
    public static function ajax_test_cleanup(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Yetkiniz yok!']);
            return;
        }

        self::run_cleanup_task();

        wp_send_json_success([
            'message'   => 'Temizlik tamamlandı! Detaylar için error_log kontrol edin.',
            'timestamp' => current_time('mysql'),
        ]);
    }

    // =========================================================
    //  ORPHAN ASSET TEMİZLİĞİ
    // =========================================================

    /**
     * Manifest'te kayıtlı olmayan (yetim) CSS/JS dosyalarını siler.
     * manifest_write() içinde %2 ihtimalle otomatik tetiklenir.
     *
     * @return int Silinen dosya sayısı
     *
     * @example
     *   $count = PageAssetsExtractor::get_instance()->purge_orphan_assets();
     *   echo "{$count} yetim dosya silindi.";
     */
    public function purge_orphan_assets(): int {
        $active_hashes = [];

        if (!empty($this->manifest)) {
            if (isset($this->manifest['templates']) && is_array($this->manifest['templates'])) {
                foreach ($this->manifest['templates'] as $tpl_data) {
                    if (!empty($tpl_data['css']))          $active_hashes[] = basename($tpl_data['css'], '.css');
                    if (!empty($tpl_data['css_rtl']))      $active_hashes[] = basename($tpl_data['css_rtl'], '.css');
                    if (!empty($tpl_data['critical_css'])) $active_hashes[] = basename($tpl_data['critical_css'], '.css');
                }
            }

            if (isset($this->manifest['plugins']) && is_array($this->manifest['plugins'])) {
                foreach ($this->manifest['plugins'] as $pk => $plg_data) {
                    if (!empty($plg_data['css']))     $active_hashes[] = basename($plg_data['css'], '.css');
                    if (!empty($plg_data['css_rtl'])) $active_hashes[] = basename($plg_data['css_rtl'], '.css');
                    if (!empty($plg_data['js']))      $active_hashes[] = basename($plg_data['js'], '.js');

                    if (isset($plg_data['contents']) && is_array($plg_data['contents'])) {
                        $valid_contents = array_filter($plg_data['contents'], function($ck) use ($pk) {
                            return isset($this->manifest['content_usage'][$ck])
                                && ($this->manifest['content_usage'][$ck]['plugins_key'] ?? '') === $pk;
                        });
                        $this->manifest['plugins'][$pk]['contents'] = array_values($valid_contents);
                    }
                }
            }
        }

        if (empty($active_hashes)) {
            return 0;
        }

        $active_hashes  = array_unique($active_hashes);
        $deleted_count  = 0;

        foreach (['css', 'js'] as $t) {
            $cache_dir = rtrim(STATIC_PATH, '/') . '/' . $t . '/cache/';
            if (!is_dir($cache_dir)) continue;

            $files = glob($cache_dir . '*.' . $t) ?: [];
            foreach ($files as $file) {
                $file_name = basename($file, '.' . $t);
                if (!in_array($file_name, $active_hashes, true) && strpos($file_name, 'manifest') === false) {
                    if (@unlink($file)) {
                        $deleted_count++;
                        $this->error_log('[PAE] Orphan silindi: ' . basename($file));
                    }
                }
            }
        }

        $this->error_log("[PAE] purge_orphan_assets: {$deleted_count} dosya silindi.");
        return $deleted_count;
    }

    /**
     * Veritabanı tabanlı derin temizlik.
     * Tüm meta tablolarını tarayıp aktif dosyaları tespit eder,
     * 24 saatten eski yetim dosyaları siler.
     *
     * @param int $limit Maksimum kontrol edilecek dosya sayısı (server yükü için)
     * @return string Sonuç mesajı
     *
     * @example
     *   $result = PageAssetsExtractor::get_instance()->cleanup_db_based(500);
     *   echo $result; // "450 dosya kontrol edildi, 12 yetim dosya silindi."
     */
    public function cleanup_db_based(int $limit = 150): string {
        global $wpdb;

        $active_files = [];
        $sources = [
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'assets'",
            "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'assets'",
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'assets'",
            "SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_key = 'assets'",
            "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%_assets'",
        ];

        foreach ($sources as $query) {
            $results = $wpdb->get_col($query);
            foreach ($results as $raw_data) {
                $data = maybe_unserialize($raw_data);
                if (is_array($data)) {
                    array_walk_recursive($data, function($value) use (&$active_files) {
                        if (is_string($value) && (str_contains($value, '.css') || str_contains($value, '.js'))) {
                            $active_files[] = basename($value);
                        }
                    });
                }
            }
        }

        $active_files  = array_flip(array_unique($active_files));
        $folders       = [
            STATIC_PATH . 'css/cache/',
            STATIC_PATH . 'js/cache/',
        ];
        $deleted_count = 0;
        $checked_count = 0;
        $one_day_ago   = time() - 86400;

        foreach ($folders as $path) {
            if (!is_dir($path)) continue;

            foreach (scandir($path) as $file) {
                if ($file === '.' || $file === '..' || str_ends_with($file, '.php')) continue;

                $checked_count++;

                if (!isset($active_files[$file])) {
                    $full_path = $path . $file;
                    if (filemtime($full_path) < $one_day_ago) {
                        unlink($full_path);
                        $deleted_count++;
                    }
                }

                if (($deleted_count + $checked_count) >= $limit) break 2;
            }
        }

        $msg = "{$checked_count} dosya kontrol edildi, {$deleted_count} yetim dosya silindi.";
        $this->error_log($msg);
        return $msg;
    }
}
