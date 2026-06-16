<?php

namespace SaltHareket\Notifications\Carriers\Email;

/**
 * EmailContract — Her email provider'ının implement etmesi gereken interface.
 *
 * @version 1.0.0
 */
interface EmailContract
{
    /**
     * Provider metadata — admin UI için.
     *
     * @return array{
     *   label: string,
     *   type: 'smtp'|'api',
     *   auth_fields: array<string, array{label: string, type: string, placeholder: string}>,
     *   notes: string[]
     * }
     */
    public static function metadata(): array;

    /**
     * Email gönder.
     *
     * @param  string   $to       Alıcı email
     * @param  string   $subject  Konu
     * @param  string   $body     HTML body
     * @param  string[] $headers  Ek header'lar
     * @return array{error: bool, message: string}
     */
    public function send( string $to, string $subject, string $body, array $headers = [] ): array;
}
