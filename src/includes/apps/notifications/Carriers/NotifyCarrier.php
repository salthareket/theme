<?php

namespace SaltHareket\Notifications\Carriers;

use SaltHareket\Notifications\NotifyPayload;
use SaltHareket\Notifications\NotifyResult;

/**
 * NotifyCarrier
 * Tüm carrier'ların implement etmesi gereken interface.
 * Yeni carrier eklemek = bu interface'i implement eden 1 sınıf yazmak.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Yeni carrier yaz
 * class MyCarrier implements NotifyCarrier {
 *     public function channel(): string { return 'my_channel'; }
 *     public function handle(NotifyPayload $payload): NotifyResult {
 *         // gönder
 *         return NotifyResult::ok('my_channel');
 *     }
 * }
 *
 * // Registry'e ekle
 * NotifyDispatcher::addCarrier(new MyCarrier());
 *
 * ──────────────────────────────────────────────────────────
 */
interface NotifyCarrier
{
    /**
     * Bu carrier'ın channel adı.
     * NotifyEvent::define() içindeki 'channels' array'iyle eşleşmeli.
     *
     * @return string  'alert' | 'email' | 'sms' | 'push' | custom
     */
    public function channel(): string;

    /**
     * Bildirimi gönder.
     *
     * @param  NotifyPayload $payload  Render edilmiş, hazır payload
     * @return NotifyResult
     */
    public function handle( NotifyPayload $payload ): NotifyResult;
}
