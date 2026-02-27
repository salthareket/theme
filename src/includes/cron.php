<?php


/**
 * 2100 MODEL SMART GARBAGE COLLECTOR
 * Veritabanında (Meta/Options) karşılığı olmayan yetim assetleri temizler.

function salt_advanced_garbage_collector($limit = 150) {
    global $wpdb;

    // 1. ADIM: BEYAZ LİSTE (WHITELIST) OLUŞTURMA
    // Tüm aktif dosya isimlerini topluyoruz
    $active_files = [];

    // Taranacak tüm kaynaklar
    $sources = [
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'assets'",
        "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'assets'",
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'assets'",
        "SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_key = 'assets'",
        "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%_assets'"
    ];

    foreach ($sources as $query) {
        $results = $wpdb->get_col($query);
        foreach ($results as $raw_data) {
            $data = maybe_unserialize($raw_data);
            if (is_array($data)) {
                // Array içindeki tüm değerleri (css_page, plugin_js vb.) tara
                array_walk_recursive($data, function($value) use (&$active_files) {
                    if (is_string($value) && (str_contains($value, '.css') || str_contains($value, '.js'))) {
                        $active_files[] = basename($value);
                    }
                });
            }
        }
    }
    // Benzersiz hale getir (hız için)
    $active_files = array_flip(array_unique($active_files));

    // 2. ADIM: KLASÖR TARAMASI VE TEMİZLİK
    $folders = [
        STATIC_PATH . "css/cache/",
        STATIC_PATH . "js/cache/"
    ];

    $deleted_count = 0;
    $checked_count = 0;
    $one_day_ago = time() - 86400; // 24 saatlik güvenlik marjı

    foreach ($folders as $path) {
        if (!is_dir($path)) continue;

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || str_ends_with($file, '.php')) continue;

            $checked_count++;
            
            // Veritabanında YOKSA ve 24 saatten ESKİYSE sil
            if (!isset($active_files[$file])) {
                $full_path = $path . $file;
                if (filemtime($full_path) < $one_day_ago) {
                    unlink($full_path);
                    $deleted_count++;
                }
            }

            // Belirlenen limite ulaştıysak dur (Server sıkışmasın)
            if (($deleted_count + $checked_count) >= $limit) break 2;
        }
    }

    return "İşlem Tamam: {$checked_count} dosya kontrol edildi, {$deleted_count} yetim dosya silindi.";
}

// Gece çalışması için WP-Cron Hook'u (Opsiyonel)
if (!wp_next_scheduled('salt_daily_cleanup_hook')) {
    wp_schedule_event(time(), 'daily', 'salt_daily_cleanup_hook');
}
add_action('salt_daily_cleanup_hook', 'salt_advanced_garbage_collector'); */