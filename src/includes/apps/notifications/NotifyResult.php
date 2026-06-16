<?php

namespace SaltHareket\Notifications;

/**
 * NotifyResult
 * Carrier'ın gönderim sonucunu taşır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Başarılı sonuç
 * $result = NotifyResult::ok('email', 42);
 *
 * // Başarısız sonuç
 * $result = NotifyResult::fail('email', 'SMTP connection refused');
 *
 * // Atlandı (throttle/preference/quiet-hours)
 * $result = NotifyResult::skipped('push', 'throttle');
 *
 * // Kontrol
 * $result->success;  // true/false
 * $result->channel;  // 'email'
 * $result->error;    // hata mesajı veya ''
 * $result->reason;   // skip sebebi veya ''
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = NotifyResult::ok('alert', 7);
 *   $result->success;     // true
 *   $result->insert_id;   // 7
 *
 * @example
 *   $result = NotifyResult::fail('sms', 'No phone number');
 *   $result->success; // false
 *   $result->error;   // 'No phone number'
 *
 * @example
 *   $result = NotifyResult::skipped('email', 'user_preference');
 *   $result->skipped; // true
 *   $result->reason;  // 'user_preference'
 *
 * @example
 *   $result->toArray();
 *   // ['channel' => 'email', 'success' => false, 'error' => '...', ...]
 *
 * @example
 *   // Toplu sonuç kontrolü
 *   $results = ['alert' => NotifyResult::ok('alert'), 'email' => NotifyResult::fail('email', 'err')];
 *   $allOk = array_all($results, fn($r) => $r->success);
 */
final class NotifyResult
{
    public readonly string $channel;
    public readonly bool   $success;
    public readonly bool   $skipped;
    public readonly string $error;
    public readonly string $reason;
    public readonly int    $insert_id;

    private function __construct(
        string $channel,
        bool   $success,
        bool   $skipped   = false,
        string $error     = '',
        string $reason    = '',
        int    $insert_id = 0,
    ) {
        $this->channel   = $channel;
        $this->success   = $success;
        $this->skipped   = $skipped;
        $this->error     = $error;
        $this->reason    = $reason;
        $this->insert_id = $insert_id;
    }

    public static function ok( string $channel, int $insert_id = 0 ): self
    {
        return new self( $channel, true, false, '', '', $insert_id );
    }

    public static function fail( string $channel, string $error ): self
    {
        return new self( $channel, false, false, $error );
    }

    public static function skipped( string $channel, string $reason ): self
    {
        return new self( $channel, false, true, '', $reason );
    }

    public function toArray(): array
    {
        return [
            'channel'   => $this->channel,
            'success'   => $this->success,
            'skipped'   => $this->skipped,
            'error'     => $this->error,
            'reason'    => $this->reason,
            'insert_id' => $this->insert_id,
        ];
    }
}
