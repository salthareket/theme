<?php

/**
 * App Settings Hooks
 *
 * ACF'den taşınan side effect'ler — her Settings class'ı
 * save() sonrası WP action fire eder, bu dosyada dinliyoruz.
 *
 * Sadece admin'de çalışır (actions-admin.php ile aynı kapsam).
 *
 * Hook pattern:
 *   sh/{app}/saved               ($settings, $previous)
 *   sh/{app}/setting_changed     ($key, $new_val, $old_val, $all_settings)
 *
 * Dışarıdan kullanım:
 *   add_action('sh/membership/setting_changed', function($key, $new, $old, $all) {
 *       if ($key === 'enable_membership' && $new) { ... }
 *   }, 10, 4);
 *
 * @version 1.0.0
 */

if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
    return;
}

// ─── MEMBERSHIP ───────────────────────────────────────────────────────────────

/**
 * Eski ACF hook karşılıkları:
 *   acf/update_value/name=enable_membership     → create/set my account page + createFiles
 *   acf/update_value/name=check_my_account_page → set_my_account_page + createFiles
 *   acf/update_value/name=enable_registration   → users_can_register + WC registration sync
 */
add_action( 'sh/membership/setting_changed', function( string $key, $new_val, $old_val, array $all ): void {

    // ── enable_membership ────────────────────────────────────────────────────
    if ( $key === 'enable_membership' && $new_val ) {
        // My Account sayfasını oluştur veya kontrol et
        if ( function_exists( 'set_my_account_page' ) ) {
            set_my_account_page( class_exists( 'WooCommerce' ) );
        } elseif ( function_exists( 'create_my_account_page' ) ) {
            $pid = (int) get_option( 'woocommerce_myaccount_page_id' )
                ?: (int) get_option( 'options_myaccount_page_id' );
            if ( ! $pid || ! get_post( $pid ) ) {
                create_my_account_page();
            }
        }

        // Frontend + admin method dosyalarını yeniden oluştur
        if ( ! class_exists( 'SaltHareket\MethodClass' ) ) {
            $methods_file = defined( 'SH_CLASSES_PATH' ) ? SH_CLASSES_PATH . 'class.methods.php' : '';
            if ( $methods_file && file_exists( $methods_file ) ) {
                require_once $methods_file;
            }
        }
        if ( class_exists( 'SaltHareket\MethodClass' ) ) {
            $m = new \SaltHareket\MethodClass();
            $m->createFiles( false );          // frontend
            $m->createFiles( false, 'admin' ); // admin
        }
    }

    // Kapanınca sayfa silmiyoruz — production'da veri kaybı riski

    // ── enable_registration ─────────────────────────────────────────────────
    if ( $key === 'enable_registration' ) {
        update_option( 'users_can_register', $new_val ? 1 : 0 );
        if ( class_exists( 'WooCommerce' ) ) {
            update_option( 'woocommerce_enable_myaccount_registration', $new_val ? 'yes' : 'no' );
        }
    }

}, 10, 4 );

// ─── LOCALIZATION ──────────────────────────────────────────────────────────────

/**
 * Eski ACF hook karşılığı:
 *   acf/update_value/name=enable_location_db
 *   → ip2country açık + source db olmak zorunda
 */
add_action( 'sh/localization/setting_changed', function( string $key, $new_val, $old_val, array $all ): void {

    if ( $key === 'enable_location_db' && $new_val ) {
        $current = \SaltHareket\Localization\LocationSettings::get();

        $updates = [];
        if ( ! $current['enable_ip2country'] ) {
            $updates['enable_ip2country'] = true;
        }
        if ( $current['ip2country_source'] !== 'db' ) {
            $updates['ip2country_source'] = 'db';
        }

        if ( $updates ) {
            \SaltHareket\Localization\LocationSettings::save( $updates );
        }
    }

}, 10, 4 );

// ─── REVIEWS ─────────────────────────────────────────────────────────────────
// Boş — bootstrap.php her page load'da enable_reviews'ı kontrol eder.
// Dışarıdan eklemek için: add_action('sh/reviews/setting_changed', ...)

// ─── ASSET MANAGER ───────────────────────────────────────────────────────────

/**
 * AssetSettings save sonrası side effect'ler:
 *
 *  enable_twig_cache kapatılınca → twig cache dizini temizlenir
 *  enable_development değişince  → SaltMinifier cache temizlenir + SaltConfig regenerate
 *  seperate_css/js değişince   → PAE asset cache temizlenir (eski assets meta'ları geçersiz)
 */
add_action( 'sh/asset/setting_changed', function( string $key, $new_val, $old_val, array $all ): void {

    // ── Twig cache kapatıldı → cache klasörünü sil + opcache reset ──────────
    if ( $key === 'enable_twig_cache' && ! $new_val ) {
        $twig_cache_dir = defined( 'STATIC_PATH' ) ? rtrim( STATIC_PATH, '/' ) . '/twig_cache' : '';
        if ( $twig_cache_dir && is_dir( $twig_cache_dir ) ) {
            // Tüm alt dosya + klasörleri recursive sil (Twig'in 2-level hiyerarşisi)
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $twig_cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $iterator as $item ) {
                if ( $item->isDir() ) {
                    @rmdir( $item->getRealPath() );
                } else {
                    @unlink( $item->getRealPath() );
                }
            }
            @rmdir( $twig_cache_dir );
        }
        // OPcache'i sıfırla — compile edilmiş Twig PHP dosyaları RAM'de kalmış olabilir
        if ( function_exists( 'opcache_reset' ) ) {
            opcache_reset();
        }
    }

    // ── Development mode değişti → SaltConfig regenerate ──────────────────────
    if ( $key === 'enable_development' ) {
        // Development AÇILINCA → twig cache dizinini temizle (enable_twig_cache değiştirilmez)
        // twig.php'deki filter ENABLE_DEVELOPMENT=true iken cache üretmiyor zaten
        if ( $new_val ) {
            $twig_cache_dir = defined( 'STATIC_PATH' ) ? rtrim( STATIC_PATH, '/' ) . '/twig_cache' : '';
            if ( $twig_cache_dir && is_dir( $twig_cache_dir ) ) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator( $twig_cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ( $iterator as $item ) {
                    $item->isDir() ? @rmdir( $item->getRealPath() ) : @unlink( $item->getRealPath() );
                }
                @rmdir( $twig_cache_dir );
            }
            if ( function_exists( 'opcache_reset' ) ) {
                opcache_reset();
            }
        }

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }
        // NOT: css/cache temizleme KALDIRILDI — her zaman minify yapılıyor,
        // production toggle'ı minify stratejisini değiştirmiyor.
    }

    // ── Seperate CSS/JS değişti → PAE invalidate ─────────────────────────────
    if ( in_array( $key, [ 'seperate_css', 'seperate_js' ], true ) ) {
        // QueryCache wrap cache'lerini temizle (js_files_all_json vs.)
        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::purge_type( 'wrap' );
        }
    }

}, 10, 4 );

// AssetSettings::save() sh/asset/setting_changed fırlatmalı — henüz yoksa ekle
add_action( 'sh/asset/saved', function( array $settings, array $previous ): void {
    // SaltConfig cache'ini yenile
    if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
        \SaltHareket\SaltConfig::generate();
    }
    // AssetSettings kendi cache'ini zaten sıfırlıyor
}, 10, 2 );

// ─── SALTCONFIG REGENERATE — tüm Settings save'leri ─────────────────────────
// Settings sınıfları artık SaltConfig PHP cache'inden okuyor (target_keys'e eklendi).
// save() çağrıldığında SaltConfig cache'ini de tazele — bir sonraki request
// yeni değerleri DB'ye gitmeden PHP dosyasından okusun.

foreach ( [
    'sh/membership/saved',
    'sh/notifications/saved',
    'sh/reactions/saved',
    'sh/localization/saved',
    'sh/reviews/saved',
    'sh/sms/settings_saved',
    'sh/search_history/saved',
] as $_sh_hook ) {
    add_action( $_sh_hook, static function() {
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }
    }, 20 );
}
unset( $_sh_hook );

// ─── LOGO SYNC — General Settings ↔ WP Native ────────────────────────────────
//
// General Settings → WP:
//   options_logo  → custom_logo (wp_set_theme_mod)
//   options_logo_icon → site_icon
//
// WP → General Settings:
//   custom_logo  guncellenmesi → options_logo guncellenir
//   site_icon    guncellenmesi → options_logo_icon guncellenir
//
// Dongu onleme: update sirasinda flag set edilir.

/**
 * General Settings Logos kaydedilince WP native logo/icon'u guncelle.
 * ajaxSaveLogos() cagrisinin hemen ardindan tetiklenmesi icin
 * 'updated_option' hook'unu kullaniyoruz.
 *
 * Bu yaklasiim: her options_logo veya options_logo_icon option'i yazilinca calisir.
 */
add_action( 'updated_option', function ( string $option, $old_value, $new_value ): void {

    // Dongu onleme — WP tarafinda guncelleme yapilirken bu hook tekrar tetiklenmesin
    if ( ! empty( $GLOBALS['_sh_logo_sync_running'] ) ) {
        return;
    }

    if ( $option === 'options_logo' ) {
        $id = is_array( $new_value ) ? (int) ( $new_value['ID'] ?? $new_value['id'] ?? 0 ) : 0;
        if ( $id > 0 ) {
            $GLOBALS['_sh_logo_sync_running'] = true;
            set_theme_mod( 'custom_logo', $id );
            unset( $GLOBALS['_sh_logo_sync_running'] );
        } elseif ( empty( $new_value ) ) {
            $GLOBALS['_sh_logo_sync_running'] = true;
            remove_theme_mod( 'custom_logo' );
            unset( $GLOBALS['_sh_logo_sync_running'] );
        }
    }

    if ( $option === 'options_logo_icon' ) {
        $id = is_array( $new_value ) ? (int) ( $new_value['ID'] ?? $new_value['id'] ?? 0 ) : 0;
        $GLOBALS['_sh_logo_sync_running'] = true;
        update_option( 'site_icon', $id > 0 ? $id : 0 );
        unset( $GLOBALS['_sh_logo_sync_running'] );
    }

}, 10, 3 );

/**
 * WP native custom_logo degisince General Settings options_logo'yu guncelle.
 */
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), function ( $old_value, $new_value ): void {

    if ( ! empty( $GLOBALS['_sh_logo_sync_running'] ) ) {
        return;
    }

    // custom_logo theme_mod degisti mi?
    $old_logo = $old_value['custom_logo'] ?? 0;
    $new_logo = $new_value['custom_logo'] ?? 0;

    if ( (int) $old_logo !== (int) $new_logo ) {
        $GLOBALS['_sh_logo_sync_running'] = true;
        if ( $new_logo > 0 ) {
            $meta = wp_get_attachment_metadata( (int) $new_logo );
            $url  = wp_get_attachment_url( (int) $new_logo ) ?: '';
            update_option( 'options_logo', [
                'ID'     => (int) $new_logo,
                'id'     => (int) $new_logo,
                'url'    => $url,
                'width'  => (int) ( $meta['width']  ?? 0 ),
                'height' => (int) ( $meta['height'] ?? 0 ),
            ], false );
        } else {
            update_option( 'options_logo', '', false );
        }
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
            \SaltHareket\SaltConfig::generate();
        }
        unset( $GLOBALS['_sh_logo_sync_running'] );
    }

}, 10, 2 );

/**
 * WP native site_icon degisince General Settings options_logo_icon'u guncelle.
 */
add_action( 'update_option_site_icon', function ( $old_value, $new_value ): void {

    if ( ! empty( $GLOBALS['_sh_logo_sync_running'] ) ) {
        return;
    }

    $new_id = (int) $new_value;
    $GLOBALS['_sh_logo_sync_running'] = true;

    if ( $new_id > 0 ) {
        $meta = wp_get_attachment_metadata( $new_id );
        $url  = wp_get_attachment_url( $new_id ) ?: '';
        update_option( 'options_logo_icon', [
            'ID'     => $new_id,
            'id'     => $new_id,
            'url'    => $url,
            'width'  => (int) ( $meta['width']  ?? 0 ),
            'height' => (int) ( $meta['height'] ?? 0 ),
        ], false );
    } else {
        update_option( 'options_logo_icon', '', false );
    }

    if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) {
        \SaltHareket\SaltConfig::generate();
    }

    unset( $GLOBALS['_sh_logo_sync_running'] );

}, 10, 2 );
