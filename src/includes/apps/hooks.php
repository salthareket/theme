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
