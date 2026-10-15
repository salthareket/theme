<?php

namespace SaltHareket\SocialShare;

/**
 * SocialShare
 *
 * Social Share buton render + URL build + settings.
 *
 * @version 1.0.0
 */
class SocialShare
{
    // ─── Network Tanımları ───────────────────────────────────────────────────

    /**
     * Tüm desteklenen ağlar.
     * url_pattern: {url}, {title}, {text}, {image}, {via}, {hashtags} placeholder'ları
     * icon / icon_style: fa_icon(icon, icon_style) çağrısı için
     * text_color: icon ve label rengi (varsayılan #fff, koyu arka planlarda #fff, açık arka planlarda #000)
     */
    public static function networks(): array
    {
        return [
            'instagram'   => [
                'label'       => 'Instagram',
                'icon'        => 'instagram',
                'icon_style'  => 'brands',
                'color'       => '#e1306c',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'whatsapp'    => [
                'label'       => 'WhatsApp',
                'icon'        => 'whatsapp',
                'icon_style'  => 'brands',
                'color'       => '#25d366',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://wa.me/?text={text}%20{url}',
                'params'      => ['url', 'text'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'youtube'     => [
                'label'       => 'YouTube',
                'icon'        => 'youtube',
                'icon_style'  => 'brands',
                'color'       => '#ff0000',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'facebook'    => [
                'label'       => 'Facebook',
                'icon'        => 'facebook-f',
                'icon_style'  => 'brands',
                'color'       => '#1877f2',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                'params'      => ['url'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'tiktok'      => [
                'label'       => 'TikTok',
                'icon'        => 'tiktok',
                'icon_style'  => 'brands',
                'color'       => '#010101',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'x'           => [
                'label'       => 'X (Twitter)',
                'icon'        => 'x-twitter',
                'icon_style'  => 'brands',
                'color'       => '#14171a',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://x.com/intent/post?url={url}&text={text}',
                'params'      => ['url', 'text', 'via', 'hashtags'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'linkedin'    => [
                'label'       => 'LinkedIn',
                'icon'        => 'linkedin-in',
                'icon_style'  => 'brands',
                'color'       => '#0a66c2',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://www.linkedin.com/shareArticle?mini=true&url={url}&title={title}&summary={text}',
                'params'      => ['url', 'title', 'text'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'telegram'    => [
                'label'       => 'Telegram',
                'icon'        => 'telegram',
                'icon_style'  => 'brands',
                'color'       => '#26a5e4',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://t.me/share/url?url={url}&text={text}',
                'params'      => ['url', 'text'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'pinterest'   => [
                'label'       => 'Pinterest',
                'icon'        => 'pinterest-p',
                'icon_style'  => 'brands',
                'color'       => '#e60023',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://pinterest.com/pin/create/button/?url={url}&media={image}&description={text}',
                'params'      => ['url', 'image', 'text'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'snapchat'    => [
                'label'       => 'Snapchat',
                'icon'        => 'snapchat',
                'icon_style'  => 'brands',
                'color'       => '#fffc00',
                'text_color'  => '#000000',
                'sharable'    => false,
            ],
            'spotify'     => [
                'label'       => 'Spotify',
                'icon'        => 'spotify',
                'icon_style'  => 'brands',
                'color'       => '#1db954',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'reddit'      => [
                'label'       => 'Reddit',
                'icon'        => 'reddit-alien',
                'icon_style'  => 'brands',
                'color'       => '#ff4500',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://www.reddit.com/submit?url={url}&title={title}',
                'params'      => ['url', 'title'],
                'tier'        => 2,
                'sharable'    => true,
            ],
            'threads'     => [
                'label'       => 'Threads',
                'icon'        => 'threads',
                'icon_style'  => 'brands',
                'color'       => '#1c1c1e',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://www.threads.net/intent/post?text={text}%20{url}',
                'params'      => ['text', 'url'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'discord'     => [
                'label'       => 'Discord',
                'icon'        => 'discord',
                'icon_style'  => 'brands',
                'color'       => '#5865f2',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'twitch'      => [
                'label'       => 'Twitch',
                'icon'        => 'twitch',
                'icon_style'  => 'brands',
                'color'       => '#9146ff',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'github'      => [
                'label'       => 'GitHub',
                'icon'        => 'github',
                'icon_style'  => 'brands',
                'color'       => '#24292e',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'vk'          => [
                'label'       => 'VK',
                'icon'        => 'vk',
                'icon_style'  => 'brands',
                'color'       => '#0077ff',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://vk.com/share.php?url={url}&title={title}&image={image}',
                'params'      => ['url', 'title', 'image'],
                'tier'        => 2,
                'sharable'    => true,
            ],
            'line'        => [
                'label'       => 'Line',
                'icon'        => 'line',
                'icon_style'  => 'brands',
                'color'       => '#00b900',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://social-plugins.line.me/lineit/share?url={url}',
                'params'      => ['url'],
                'tier'        => 2,
                'sharable'    => true,
            ],
            'bluesky'     => [
                'label'       => 'Bluesky',
                'icon'        => 'bluesky',
                'icon_style'  => 'brands',
                'color'       => '#0085ff',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://bsky.app/intent/compose?text={text}%20{url}',
                'params'      => ['text', 'url'],
                'tier'        => 1,
                'sharable'    => true,
            ],
            'tumblr'      => [
                'label'       => 'Tumblr',
                'icon'        => 'tumblr',
                'icon_style'  => 'brands',
                'color'       => '#35465c',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://www.tumblr.com/share/link?url={url}&name={title}&description={text}',
                'params'      => ['url', 'title', 'text'],
                'tier'        => 2,
                'sharable'    => true,
            ],
            'soundcloud'  => [
                'label'       => 'SoundCloud',
                'icon'        => 'soundcloud',
                'icon_style'  => 'brands',
                'color'       => '#ff5500',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'medium'      => [
                'label'       => 'Medium',
                'icon'        => 'medium',
                'icon_style'  => 'brands',
                'color'       => '#00ab6c',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'slack'       => [
                'label'       => 'Slack',
                'icon'        => 'slack',
                'icon_style'  => 'brands',
                'color'       => '#4a154b',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'mastodon'    => [
                'label'       => 'Mastodon',
                'icon'        => 'mastodon',
                'icon_style'  => 'brands',
                'color'       => '#6364ff',
                'text_color'  => '#ffffff',
                'url_pattern' => 'https://mastodon.social/share?text={text}%20{url}',
                'params'      => ['text', 'url'],
                'tier'        => 2,
                'sharable'    => true,
                'note'        => 'instance_picker',
            ],
            'website'     => [
                'label'       => 'Website',
                'icon'        => 'globe',
                'icon_style'  => 'solid',
                'color'       => '#374151',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'imdb'        => [
                'label'       => 'IMDb',
                'icon'        => 'imdb',
                'icon_style'  => 'brands',
                'color'       => '#f5c518',
                'text_color'  => '#000000',
                'sharable'    => false,
            ],
            'goodreads'   => [
                'label'       => 'Goodreads',
                'icon'        => 'goodreads',
                'icon_style'  => 'brands',
                'color'       => '#553b08',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'behance'     => [
                'label'       => 'Behance',
                'icon'        => 'behance',
                'icon_style'  => 'brands',
                'color'       => '#1769ff',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'dribbble'    => [
                'label'       => 'Dribbble',
                'icon'        => 'dribbble',
                'icon_style'  => 'brands',
                'color'       => '#ea4c89',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'patreon'     => [
                'label'       => 'Patreon',
                'icon'        => 'patreon',
                'icon_style'  => 'brands',
                'color'       => '#f96854',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'buymeacoffee'=> [
                'label'       => 'Buy Me a Coffee',
                'icon'        => 'mug-hot',
                'icon_style'  => 'solid',
                'color'       => '#ffdd00',
                'text_color'  => '#000000',
                'sharable'    => false,
            ],
            'kofi'        => [
                'label'       => 'Ko-fi',
                'icon'        => 'ko-fi',
                'icon_style'  => 'brands',
                'color'       => '#29abe0',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'substack'    => [
                'label'       => 'Substack',
                'icon'        => 'substack',
                'icon_style'  => 'brands',
                'color'       => '#ff6719',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'devto'       => [
                'label'       => 'Dev.to',
                'icon'        => 'dev',
                'icon_style'  => 'brands',
                'color'       => '#0a0a0a',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'producthunt' => [
                'label'       => 'Product Hunt',
                'icon'        => 'product-hunt',
                'icon_style'  => 'brands',
                'color'       => '#da552f',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'gitlab'      => [
                'label'       => 'GitLab',
                'icon'        => 'gitlab',
                'icon_style'  => 'brands',
                'color'       => '#fc6d26',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'xing'        => [
                'label'       => 'Xing',
                'icon'        => 'xing',
                'icon_style'  => 'brands',
                'color'       => '#026466',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'flickr'      => [
                'label'       => 'Flickr',
                'icon'        => 'flickr',
                'icon_style'  => 'brands',
                'color'       => '#ff0084',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            '500px'       => [
                'label'       => '500px',
                'icon'        => '500px',
                'icon_style'  => 'brands',
                'color'       => '#0099e5',
                'text_color'  => '#ffffff',
                'sharable'    => false,
            ],
            'email'       => [
                'label'       => 'E-posta',
                'icon'        => 'envelope',
                'icon_style'  => 'solid',
                'color'       => '#6b7280',
                'text_color'  => '#ffffff',
                'url_pattern' => 'mailto:?subject={title}&body={text}%0A{url}',
                'params'      => ['title', 'text', 'url'],
                'tier'        => 1,
                'target'      => '_self',
                'sharable'    => true,
            ],
            'copy'        => [
                'label'       => 'Linki Kopyala',
                'icon'        => 'link',
                'icon_style'  => 'solid',
                'color'       => '#374151',
                'text_color'  => '#ffffff',
                'url_pattern' => '',
                'params'      => [],
                'tier'        => 1,
                'js_action'   => 'copy',
                'sharable'    => true,
            ],
            'native'      => [
                'label'       => 'Paylaş',
                'icon'        => 'share-nodes',
                'icon_style'  => 'solid',
                'color'       => '#6366f1',
                'text_color'  => '#ffffff',
                'url_pattern' => '',
                'params'      => [],
                'tier'        => 1,
                'js_action'   => 'native',
                'mobile_only' => true,
                'sharable'    => true,
            ],
        ];
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public static function getSettings(): array
    {
        $defaults = [
            'enabled'              => true,
            'networks'             => ['facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'email', 'copy'],
            // Görünüm modu
            'view'                 => 'button',   // button | solid
            'style'                => 'icon-only',// icon-only | icon-text | text-only
            'size'                 => 'md',
            'layout'               => 'horizontal',
            // Button view renk ayarları
            'btn_bg'               => 'brand',    // brand | custom
            'btn_bg_color'         => '#374151',
            'btn_bg_hover'         => 'brand',    // brand | custom
            'btn_bg_hover_color'   => '',
            'btn_text'             => 'white',    // white | brand | custom
            'btn_text_color'       => '#ffffff',
            'btn_text_hover'       => 'white',    // white | brand | custom
            'btn_text_hover_color' => '#ffffff',
            // Solid view renk ayarları
            'solid_color'          => 'brand',    // brand | custom
            'solid_color_val'      => '',
            'solid_hover'          => 'brand',    // brand | custom
            'solid_hover_val'      => '',
            // Diğer
            'show_count'           => false,
            'ga4_tracking'         => true,
            'via'                  => '',
            'hashtags'             => '',
        ];

        $saved  = (array) get_option( 'sh_social_share_settings', [] );
        $merged = array_replace_recursive( $defaults, $saved );

        if ( ! in_array( $merged['view'],   ['button', 'solid'], true ) )                   $merged['view']   = 'button';
        if ( ! in_array( $merged['style'],  ['icon-only','icon-text','text-only'], true ) ) $merged['style']  = 'icon-only';
        if ( ! in_array( $merged['size'],   ['sm','md','lg'], true ) )                      $merged['size']   = 'md';
        if ( ! in_array( $merged['layout'], ['horizontal','vertical'], true ) )             $merged['layout'] = 'horizontal';

        return $merged;
    }

    // ─── URL Build ───────────────────────────────────────────────────────────

    public static function buildUrl( string $network, array $data ): string
    {
        $networks = self::networks();
        if ( ! isset( $networks[ $network ] ) ) return '';

        $def     = $networks[ $network ];
        $pattern = $def['url_pattern'] ?? '';
        if ( empty( $pattern ) ) return '';

        $url   = rawurlencode( $data['url']   ?? '' );
        $title = rawurlencode( $data['title'] ?? '' );
        $text  = rawurlencode( $data['text']  ?? $data['title'] ?? '' );
        $image = rawurlencode( $data['image'] ?? '' );
        $via   = rawurlencode( $data['via']   ?? '' );
        $tags  = rawurlencode( $data['hashtags'] ?? '' );

        return str_replace(
            [ '{url}', '{title}', '{text}', '{image}', '{via}', '{hashtags}' ],
            [ $url,    $title,    $text,    $image,    $via,    $tags        ],
            $pattern
        );
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public static function render( array $args = [] ): string
    {
        $settings = self::getSettings();
        if ( empty( $settings['enabled'] ) ) return '';

        $url      = $args['url']      ?? get_permalink();
        $title    = $args['title']    ?? get_the_title();
        $text     = $args['text']     ?? $title;
        $image    = $args['image']    ?? get_the_post_thumbnail_url( null, 'large' ) ?: '';
        $networks = $args['networks'] ?? $settings['networks'];
        $view     = $args['view']     ?? $settings['view'];
        $style    = $args['style']    ?? $settings['style'];
        $size     = $args['size']     ?? $settings['size'];
        $layout   = $args['layout']   ?? $settings['layout'];
        $ga4      = $args['ga4_tracking'] ?? $settings['ga4_tracking'];

        if ( ! class_exists( 'Timber\Timber' ) ) return '';

        return \Timber\Timber::compile( 'partials/social-share.twig', [
            'networks'     => $networks,
            'all_networks' => self::networks(),
            'share_data'   => compact( 'url', 'title', 'text', 'image' ),
            'view'         => $view,
            'style'        => $style,
            'size'         => $size,
            'layout'       => $layout,
            'ga4'          => $ga4,
            'via'          => $settings['via'],
            'hashtags'     => $settings['hashtags'],
            'show_count'   => $settings['show_count'],
            // Renk ayarları — twig'e geçir
            'color_cfg'    => [
                'btn_bg'               => $settings['btn_bg'],
                'btn_bg_color'         => $settings['btn_bg_color'],
                'btn_bg_hover'         => $settings['btn_bg_hover'],
                'btn_bg_hover_color'   => $settings['btn_bg_hover_color'],
                'btn_text'             => $settings['btn_text'],
                'btn_text_color'       => $settings['btn_text_color'],
                'btn_text_hover'       => $settings['btn_text_hover'],
                'btn_text_hover_color' => $settings['btn_text_hover_color'],
                'solid_color'          => $settings['solid_color'],
                'solid_color_val'      => $settings['solid_color_val'],
                'solid_hover'          => $settings['solid_hover'],
                'solid_hover_val'      => $settings['solid_hover_val'],
            ],
        ] );
    }

    public static function incrementCount( int $post_id, string $network ): void
    {
        if ( ! $post_id ) return;
        $meta_key = '_sh_share_' . sanitize_key( $network );
        $current  = (int) get_post_meta( $post_id, $meta_key, true );
        update_post_meta( $post_id, $meta_key, $current + 1 );
    }

    public static function getTotalCount( int $post_id ): int
    {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE '_sh_share_%%'",
            $post_id
        ) );
        return (int) $result;
    }
}
