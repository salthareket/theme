<?php

/**
 * SMS App Bootstrap
 *
 * @version 1.0.0
 * @package SaltHareket\Sms
 *
 * variables.php'de yüklenir:
 *   define('ENABLE_SMS', get_sh_config('options_enable_sms'));
 *   if (ENABLE_SMS) include_once SH_INCLUDES_PATH . 'apps/sms/bootstrap.php';
 */

$sms_base = __DIR__ . '/';

// Contracts
require_once $sms_base . 'Contracts/SmsContract.php';

// Core
require_once $sms_base . 'SmsEncoding.php';
require_once $sms_base . 'SmsSettings.php';

// Providers
require_once $sms_base . 'Providers/D7Provider.php';
require_once $sms_base . 'Providers/TwilioProvider.php';
require_once $sms_base . 'Providers/VonageProvider.php';
require_once $sms_base . 'Providers/InfobipProvider.php';
require_once $sms_base . 'Providers/SinchProvider.php';
require_once $sms_base . 'Providers/NetgsmProvider.php';

// Manager
require_once $sms_base . 'SmsManager.php';

// Dependency facade
require_once $sms_base . 'SmsApp.php';

// Admin (sadece admin/ajax/rest context'de)
if ( is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
    require_once $sms_base . 'Admin/SmsAdmin.php';
    \SaltHareket\Sms\Admin\SmsAdmin::register();
}

// Settings değişince SmsManager cache'ini temizle
add_action( 'sh/sms/settings_saved', function() {
    \SaltHareket\Sms\SmsManager::reset();
} );

// Apps Dashboard registry
add_filter( 'sh_apps_registry', function( array $apps ): array {
    $apps[] = [
        'id'         => 'sms',
        'label'      => 'SMS',
        'icon'       => '📱',
        'enable_key' => 'options_enable_sms',
        'admin_url'  => admin_url( 'admin.php?page=sh-sms' ),
        'enabled'    => defined('ENABLE_SMS') && ENABLE_SMS,
        'description'=> 'SMS provider yönetimi, OTP, toplu gönderim',
    ];
    return $apps;
} );
