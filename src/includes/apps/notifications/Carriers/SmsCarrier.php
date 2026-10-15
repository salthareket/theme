<?php

namespace SaltHareket\Notifications\Carriers;

use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyResult;
use SaltHareket\Sms\SmsManager;
use SaltHareket\Sms\SmsSettings;
use SaltHareket\Sms\SmsApp;

/**
 * SmsCarrier — SMS notification carrier.
 * SmsManager (apps/sms) üzerinden aktif provider'ı kullanır.
 *
 * Bu dosya sadece ENABLE_SMS=true olduğunda yüklenir.
 * sms/bootstrap.php notifications/bootstrap.php'dan önce yükleniyor (variables.php).
 *
 * @version 2.1.0
 * @changelog
 *   2.1.0 - 2026-06-18
 *     - Change: SmsManager/SmsSettings → SaltHareket\Sms namespace
 *     - Change: Sadece ENABLE_SMS aktifken yükleniyor
 *   2.0.0 - 2026-05-14 — SmsManager + multi-provider
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * @package SaltHareket\Notifications\Carriers
 */
class SmsCarrier implements NotifyCarrier
{
    public function channel(): string
    {
        return 'sms';
    }

    public function handle( NotifyPayload $payload ): NotifyResult
    {
        // SMS app dependency check — SmsSettings'ten bağımsız, merkezi kontrol.
        if ( ! SmsApp::isAvailable() ) {
            return NotifyResult::skipped( 'sms', 'sms_unavailable' );
        }

        $settings = SmsSettings::get();
        if ( empty( $settings['enabled'] ) ) {
            return NotifyResult::skipped( 'sms', 'disabled' );
        }

        // \User Timber magic property'leri AJAX context'inde çalışmayabilir
        // get_user_meta() ile direkt WP native okuma yapıyoruz
        $phone_code = get_user_meta( $payload->receiver_id, 'billing_phone_code', true );
        $phone_num  = get_user_meta( $payload->receiver_id, 'billing_phone', true );
        $phone      = $phone_code . $phone_num;

        // Fallback: \User class ile dene
        if ( empty( trim( $phone ) ) ) {
            $user  = new \User( $payload->receiver_id );
            $phone = $user->get_phone();
        }

        if ( empty( trim( $phone ) ) ) {
            return NotifyResult::skipped( 'sms', 'no_phone' );
        }

        try {
            $result = SmsManager::driver()->send( [ $phone ], $payload->rendered_body );

            if ( ! empty( $result['error'] ) ) {
                return NotifyResult::fail( 'sms', $result['message'] ?? 'SMS send failed' );
            }
        } catch ( \Throwable $e ) {
            return NotifyResult::fail( 'sms', $e->getMessage() );
        }

        return NotifyResult::ok( 'sms' );
    }
}
