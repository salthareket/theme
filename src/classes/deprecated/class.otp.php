<?php

/**
 * @deprecated SMS işlemleri SmsManager'a taşındı.
 * Backward-compat shim — eski `new Sms([...])` çağrıları kırılmasın diye.
 *
 * Yeni kullanım:
 *   SaltHareket\Notifications\Carriers\SmsManager::driver()->send(['+905551234567'], 'Mesaj');
 *   SaltHareket\Notifications\Carriers\SmsManager::driver()->generateOtp('+905551234567', $user_id);
 */

// Eski Sms class'ı hâlâ kullanılıyorsa backward-compat wrapper
if ( ! class_exists( 'Sms' ) ) {
    class Sms {
        private array $vars;

        public function __construct( array $vars = [] ) {
            $this->vars = $vars;
        }

        /** SMS gönder */
        public function message( string $schedule_time = '' ): array {
            try {
                $recipients = $this->vars['recipients'] ?? [];
                $content    = $this->vars['content']    ?? '';
                return \SaltHareket\Notifications\Carriers\SmsManager::driver()->send( $recipients, $content );
            } catch ( \Throwable $e ) {
                return [ 'error' => true, 'message' => $e->getMessage(), 'data' => null ];
            }
        }

        /** OTP üret */
        public function generate(): array {
            try {
                $recipient = $this->vars['recipient'] ?? '';
                $user_id   = (int) ( $this->vars['user_id'] ?? 0 );
                $content   = $this->vars['content'] ?? '';
                return \SaltHareket\Notifications\Carriers\SmsManager::driver()->generateOtp( $recipient, $user_id, $content );
            } catch ( \Throwable $e ) {
                return [ 'error' => true, 'message' => $e->getMessage(), 'data' => null ];
            }
        }

        /** OTP doğrula */
        public function verify(): array {
            try {
                $otp_id   = $this->vars['otp_id']   ?? '';
                $otp_code = $this->vars['otp_code']  ?? '';
                return \SaltHareket\Notifications\Carriers\SmsManager::driver()->verifyOtp( $otp_id, $otp_code );
            } catch ( \Throwable $e ) {
                return [ 'error' => true, 'message' => $e->getMessage(), 'data' => null ];
            }
        }

        /** OTP yeniden gönder */
        public function resend(): array {
            try {
                $otp_id  = $this->vars['otp_id']  ?? '';
                $user_id = (int) ( $this->vars['user_id'] ?? 0 );
                return \SaltHareket\Notifications\Carriers\SmsManager::driver()->resendOtp( $otp_id, $user_id );
            } catch ( \Throwable $e ) {
                return [ 'error' => true, 'message' => $e->getMessage(), 'data' => null ];
            }
        }

        /** Bakiye sorgula */
        public function check_balance(): array {
            try {
                return \SaltHareket\Notifications\Carriers\SmsManager::driver()->checkBalance();
            } catch ( \Throwable $e ) {
                return [ 'error' => true, 'message' => $e->getMessage(), 'data' => null ];
            }
        }
    }
}
