<?php

namespace SaltHareket\Notifications\Carriers;

use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyResult;

/**
 * SmsCarrier — SMS notification carrier.
 * SmsManager üzerinden aktif provider'ı kullanır.
 *
 * @version 2.0.0
 * @changelog
 *   2.0.0 - 2026-05-14 — SmsManager + multi-provider desteği
 *   1.0.0 - 2026-05-04 — Initial release
 */
class SmsCarrier implements NotifyCarrier
{
    public function channel(): string
    {
        return 'sms';
    }

    public function handle( NotifyPayload $payload ): NotifyResult
    {
        if ( ! defined( 'ENABLE_SMS_NOTIFICATIONS' ) || ! ENABLE_SMS_NOTIFICATIONS ) {
            return NotifyResult::skipped( 'sms', 'disabled' );
        }

        $settings = SmsSettings::get();
        if ( empty( $settings['enabled'] ) ) {
            return NotifyResult::skipped( 'sms', 'disabled' );
        }

        $user  = new \User( $payload->receiver_id );
        $phone = $user->get_phone();

        if ( empty( $phone ) ) {
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
