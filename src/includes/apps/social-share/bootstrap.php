<?php

/**
 * Social Share — Bootstrap
 *
 * @version 1.0.0
 */

namespace SaltHareket\SocialShare;

defined( 'ABSPATH' ) || exit;

// ── Autoload ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/SocialShare.php';
require_once __DIR__ . '/Admin/SocialShareAdmin.php';

use SaltHareket\SocialShare\Admin\SocialShareAdmin;

// ── Admin ─────────────────────────────────────────────────────────────────────

SocialShareAdmin::register();

// ── Shortcode ─────────────────────────────────────────────────────────────────

add_shortcode( 'sh_social_share', function( $atts ) {
    $atts = shortcode_atts( [
        'url'      => '',
        'title'    => '',
        'text'     => '',
        'image'    => '',
        'networks' => '',   // comma separated
        'style'    => '',
        'size'     => '',
        'theme'    => '',
        'layout'   => '',
    ], $atts, 'sh_social_share' );

    if ( $atts['networks'] ) {
        $atts['networks'] = array_map( 'trim', explode( ',', $atts['networks'] ) );
    }
    // Boş string'leri kaldır — defaults devreye girsin
    $atts = array_filter( $atts, fn( $v ) => $v !== '' );

    return SocialShare::render( $atts );
} );

// ── Action Hook ───────────────────────────────────────────────────────────────

/**
 * Twig'den:  {% do action('sh_social_share') %}
 * Twig'den:  {% do action('sh_social_share_for', post.id) %}
 */
add_action( 'sh_social_share', function( $post_id = null ) {
    $args = [];
    if ( $post_id ) {
        $args['url']   = get_permalink( $post_id );
        $args['title'] = get_the_title( $post_id );
        $args['image'] = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
    }
    echo SocialShare::render( $args ); // phpcs:ignore
} );

// ── AJAX: Share Count ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_sh_social_share_count',        'SaltHareket\SocialShare\_ajax_share_count' );
add_action( 'wp_ajax_nopriv_sh_social_share_count', 'SaltHareket\SocialShare\_ajax_share_count' );

function _ajax_share_count(): void {
    $post_id = absint( $_POST['post_id'] ?? 0 );
    $network = sanitize_key( $_POST['network'] ?? '' );
    if ( ! $post_id || ! $network ) wp_send_json_error();

    $settings = SocialShare::getSettings();
    if ( ! empty( $settings['show_count'] ) ) {
        SocialShare::incrementCount( $post_id, $network );
    }

    wp_send_json_success();
}

// ── Twig Helper ───────────────────────────────────────────────────────────────

add_filter( 'timber/twig/functions', function( array $fns ): array {
    $fns['sh_social_share'] = [
        'callable' => function( array $args = [] ) {
            return new \Twig\Markup( SocialShare::render( $args ), 'UTF-8' );
        },
    ];
    return $fns;
} );
