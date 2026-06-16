<?php

namespace SaltHareket\Notifications;

/**
 * NotifyPayload
 * Immutable DTO — bir notification gönderiminin tüm verisini taşır.
 * Carrier'lara bu nesne geçilir, carrier sadece okur.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-04
 *     - Fix: readonly clone PHP 8.2 fatal error — rendered alanlar nullable yapıldı
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $payload = new NotifyPayload(
 *     event:       $event,
 *     channel:     'email',
 *     sender_id:   1,
 *     receiver_id: 5,
 *     data:        ['user' => $user, 'post' => $post],
 * );
 *
 * // Render edilmiş versiyonu al (immutable — yeni instance döner)
 * $rendered = $payload->withRendered('Hello World', 'Subject');
 *
 * $payload->event->key;       // 'order/new'
 * $payload->channel;          // 'email'
 * $payload->rendered_body;    // render sonrası dolu
 * $payload->rendered_subject; // email için
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $payload->event->hasChannel('push');
 *
 * @example
 *   $payload->getChannelConfig();
 *
 * @example
 *   $rendered = $payload->withRendered($body, $subject);
 *
 * @example
 *   $payload->post_id;
 *   $payload->user_id;
 *
 * @example
 *   $payload->isAsync;
 */
final class NotifyPayload
{
    public readonly string         $channel;
    public readonly int            $sender_id;
    public readonly int            $receiver_id;
    public readonly array          $data;
    public readonly NotifyEvent    $event;
    public readonly NotifyPriority $priority;
    public readonly bool           $isAsync;
    public readonly string         $idempotency_key;
    public readonly int            $post_id;
    public readonly int            $user_id;

    // Render sonrası dolar — readonly DEĞİL, withRendered() için mutable
    private string $rendered_body    = '';
    private string $rendered_subject = '';

    public function __construct(
        NotifyEvent     $event,
        string          $channel,
        int             $sender_id,
        int             $receiver_id,
        array           $data        = [],
        bool            $isAsync     = false,
        ?NotifyPriority $priority    = null,
    ) {
        $this->event        = $event;
        $this->channel      = $channel;
        $this->sender_id    = $sender_id;
        $this->receiver_id  = $receiver_id;
        $this->data         = $data;
        $this->isAsync      = $isAsync;
        $this->priority     = $priority ?? $event->priority;

        $this->post_id = (int) ( $data['post']->ID ?? 0 );
        $this->user_id = (int) ( $data['user']->ID ?? 0 );

        $this->idempotency_key = md5(
            $event->key . '|' . $channel . '|' . $receiver_id . '|' . $this->post_id
        );
    }

    public function __get( string $name ): string
    {
        return match( $name ) {
            'rendered_body'    => $this->rendered_body,
            'rendered_subject' => $this->rendered_subject,
            default            => throw new \RuntimeException( "Unknown property: {$name}" ),
        };
    }

    /**
     * Render edilmiş içerikle yeni immutable instance döner.
     * PHP 8.2 readonly clone sorununu önlemek için constructor ile yeni nesne oluşturur.
     */
    public function withRendered( string $body, string $subject = '' ): self
    {
        $clone                   = new self(
            $this->event,
            $this->channel,
            $this->sender_id,
            $this->receiver_id,
            $this->data,
            $this->isAsync,
            $this->priority,
        );
        $clone->rendered_body    = $body;
        $clone->rendered_subject = $subject;
        return $clone;
    }

    public function getChannelConfig(): array
    {
        return $this->event->getChannelConfig( $this->channel );
    }
}
