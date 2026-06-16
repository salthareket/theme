<?php

namespace SaltHareket\Notifications;

use SaltHareket\Notifications\Carriers\EmailCarrier;

/**
 * NotifyRenderer
 * Twig string ve HTML template'lerini render eder.
 * Payload'u render edilmiş versiyona dönüştürür.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Payload'u render et
 * $rendered = NotifyRenderer::render($payload, $emailCarrier);
 * // $rendered artık rendered_body ve rendered_subject dolu
 *
 * // Twig string render
 * $output = NotifyRenderer::renderString('Hello {{ data.user.name }}', $data);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $rendered = NotifyRenderer::render($payload, $emailCarrier);
 *   $rendered->rendered_body;    // 'Hello John'
 *   $rendered->rendered_subject; // 'New Order #42'
 *
 * @example
 *   // body = 'template' → HTML dosyasını yükler
 *   // body = Twig string → direkt render eder
 *
 * @example
 *   // Twig context'te otomatik mevcut:
 *   // {{ data.user }}, {{ data.post }}, {{ notify.event }}, {{ notify.channel }}
 *
 * @example
 *   NotifyRenderer::renderString('Order #{{ data.post.ID }}', ['post' => $post]);
 *
 * @example
 *   // HTML entity decode — ACF'den gelen &#039; gibi karakterler düzeltilir
 */
class NotifyRenderer
{
    /**
     * Payload'u render et — channel'a göre doğru template/string kullanır.
     */
    public static function render( NotifyPayload $payload, ?EmailCarrier $emailCarrier = null ): NotifyPayload
    {
        $config  = $payload->getChannelConfig();
        $channel = $payload->channel;
        $data    = $payload->data;
        $subject = '';
        $body    = '';

        switch ( $channel ) {
            case 'alert':
            case 'sms':
            case 'push':
                $body = self::renderString( $config['body'] ?? '', $data, $payload );
                break;

            case 'email':
                // Subject
                $raw_subject = $config['subject'] ?? '';
                $raw_subject = html_entity_decode( $raw_subject, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                $subject     = self::renderString( $raw_subject, $data, $payload );

                // Body
                $raw_body = $config['body'] ?? '';
                if ( $raw_body === 'template' && $emailCarrier ) {
                    $raw_body = $emailCarrier->loadTemplate( $payload->event->key );
                }
                $body = self::renderString( $raw_body, $data, $payload );
                break;
        }

        return $payload->withRendered( $body, $subject );
    }

    /**
     * Twig string'i render et.
     * Timber::compile_string() kullanır.
     *
     * @param string $template  Twig template string
     * @param array  $data      Event data
     * @param NotifyPayload|null $payload  Ek context için
     * @return string
     */
    public static function renderString( string $template, array $data, ?NotifyPayload $payload = null ): string
    {
        if ( empty( $template ) ) return '';
        if ( ! class_exists( '\\Timber\\Timber' ) ) return $template;

        $context           = \Timber\Timber::context();
        $context['data']   = self::normalizeData( $data );

        // notify context — template'de {{ notify.event }}, {{ notify.channel }} kullanılabilir
        if ( $payload ) {
            $context['notify'] = [
                'event'   => $payload->event->key,
                'channel' => $payload->channel,
                'sender'  => $payload->sender_id,
            ];
            // data.event, data.type, data.created_at — kolaylık için data içine de ekle
            $context['data']['event']      = $payload->event->key;
            $context['data']['channel']    = $payload->channel;
            $context['data']['type']       = $payload->data['_rule_type'] ?? 'info';
            $context['data']['created_at'] = current_time( 'mysql' );
        }

        try {
            return (string) \Timber\Timber::compile_string( $template, $context );
        } catch ( \Throwable $e ) {
            return $template; // render başarısız olursa ham template'i döndür
        }
    }

    /**
     * Data array'ini Twig-friendly hale getir.
     * WP_User → array, WP_Post → array, Timber objeleri olduğu gibi kalır.
     */
    private static function normalizeData( array $data ): array
    {
        $normalized = [];

        foreach ( $data as $key => $value ) {

            // WP_User — her türlü namespace'den
            $is_wp_user = ( $value instanceof \WP_User )
                || ( is_object( $value ) && get_class( $value ) === 'WP_User' );

            // WP_Post
            $is_wp_post = ( $value instanceof \WP_Post )
                || ( is_object( $value ) && get_class( $value ) === 'WP_Post' );

            if ( $is_wp_user ) {
                $normalized[ $key ] = [
                    'ID'         => (int) $value->ID,
                    'name'       => $value->display_name,
                    'first_name' => $value->first_name,
                    'last_name'  => $value->last_name,
                    'email'      => $value->user_email,
                    'login'      => $value->user_login,
                    'avatar_url' => get_avatar_url( $value->ID ),
                    'profile_url'=> get_author_posts_url( $value->ID ),
                ];
            } elseif ( $is_wp_post ) {
                $normalized[ $key ] = [
                    'ID'          => (int) $value->ID,
                    'title'       => html_entity_decode( wp_specialchars_decode( $value->post_title, ENT_QUOTES ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                    'link'        => get_permalink( $value->ID ) ?: '',
                    'excerpt'     => wp_strip_all_tags( get_the_excerpt( $value->ID ) ),
                    'author_name' => get_the_author_meta( 'display_name', $value->post_author ),
                    'thumbnail'   => get_the_post_thumbnail_url( $value->ID, 'medium' ) ?: '',
                    'post_type'   => $value->post_type,
                ];
            } elseif ( is_object( $value ) ) {
                // Timber User/Post veya başka obje — ID varsa user olarak dene
                $uid = (int) ( $value->ID ?? 0 );
                if ( $uid && isset( $value->user_email ) ) {
                    $wp_user = get_userdata( $uid );
                    $normalized[ $key ] = [
                        'ID'         => $uid,
                        'name'       => $wp_user ? $wp_user->display_name : '',
                        'first_name' => $wp_user ? $wp_user->first_name   : '',
                        'last_name'  => $wp_user ? $wp_user->last_name    : '',
                        'email'      => $wp_user ? $wp_user->user_email   : '',
                        'login'      => $wp_user ? $wp_user->user_login   : '',
                        'avatar_url' => get_avatar_url( $uid ),
                        'profile_url'=> get_author_posts_url( $uid ),
                    ];
                } else {
                    $normalized[ $key ] = $value;
                }
            } else {
                $normalized[ $key ] = $value;
            }
        }

        return $normalized;
    }
}
