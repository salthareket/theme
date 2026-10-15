<?php

/**
 * Membership REST API
 *
 * Endpoint'ler: salt/v1/membership/*
 * Self-contained — tüm endpoint tanımları ve callback'ler burada.
 *
 * @package SaltHareket\Membership
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-26
 *     - Add: /membership/activate  — email/SMS kodu doğrula
 *     - Add: /membership/resend-activation — aktivasyon yeniden gönder
 *     - Add: /membership/status    — kullanıcı aktivasyon durumu
 *     - Add: /membership/approve   — admin: kullanıcıyı onayla
 *     - Add: /membership/reject    — admin: kullanıcıyı reddet
 *     - Add: /membership/online/{user_id} — kullanıcı online mı?
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {

    register_rest_route( 'salt/v1', '/membership/activate', [
        'methods'             => 'POST',
        'callback'            => 'salt_mm_activate',
        'permission_callback' => '__return_true',
        'args'                => [
            'code' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    register_rest_route( 'salt/v1', '/membership/resend-activation', [
        'methods'             => 'POST',
        'callback'            => 'salt_mm_resend',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( 'salt/v1', '/membership/status', [
        'methods'             => 'GET',
        'callback'            => 'salt_mm_status',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( 'salt/v1', '/membership/approve', [
        'methods'             => 'POST',
        'callback'            => 'salt_mm_approve',
        'permission_callback' => fn() => current_user_can( 'manage_options' ),
        'args'                => [
            'user_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
        ],
    ] );

    register_rest_route( 'salt/v1', '/membership/reject', [
        'methods'             => 'POST',
        'callback'            => 'salt_mm_reject',
        'permission_callback' => fn() => current_user_can( 'manage_options' ),
        'args'                => [
            'user_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            'reason'  => [ 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
        ],
    ] );

    register_rest_route( 'salt/v1', '/membership/online/(?P<user_id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'salt_mm_online',
        'permission_callback' => '__return_true',
        'args'                => [
            'user_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
        ],
    ] );

} );

// ─── CALLBACKS ───────────────────────────────────────────────────────────────

/**
 * POST /membership/activate
 * Email veya SMS aktivasyon kodunu doğrular.
 */
function salt_mm_activate( WP_REST_Request $r ): WP_REST_Response {
    $code = $r->get_param( 'code' );
    $uid  = get_current_user_id();

    if ( class_exists( 'SaltHareket\\Membership\\MembershipManager' ) ) {
        $mm  = \SaltHareket\Membership\MembershipManager::getInstance();
        $res = $mm->verifyEmailCode( $code, $uid );
        return new WP_REST_Response( $res, $res['success'] ? 200 : 400 );
    }

    // Fallback — MembershipManager yoksa direkt DB kontrolü
    global $wpdb;
    $data = json_decode( base64_decode( strtr( $code, '-_', '+/' ) ), true );

    if ( ! $data || empty( $data['id'] ) || empty( $data['key'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Geçersiz aktivasyon kodu.' ], 400 );
    }

    $tid = (int) $data['id'];
    $sk  = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_activation_key FROM {$wpdb->users} WHERE ID=%d", $tid
    ) );

    if ( $sk !== $data['key'] ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Aktivasyon kodu geçersiz veya süresi dolmuş.' ], 400 );
    }

    update_user_meta( $tid, 'user_status', 'activated' );
    update_user_meta( $tid, 'activated_at', time() );
    do_action( 'sh_account_activated', $tid );

    return new WP_REST_Response( [ 'success' => true, 'message' => 'Hesabınız başarıyla aktive edildi.' ], 200 );
}

/**
 * POST /membership/resend-activation
 * Aktivasyon email/SMS'ini yeniden gönderir.
 */
function salt_mm_resend( WP_REST_Request $r ): WP_REST_Response {
    $uid = get_current_user_id();

    if ( class_exists( 'SaltHareket\\Membership\\MembershipManager' ) ) {
        $mm  = \SaltHareket\Membership\MembershipManager::getInstance();
        return new WP_REST_Response( $mm->sendActivation( $uid ), 200 );
    }

    // Fallback
    $user = get_userdata( $uid );
    if ( ! $user ) {
        return new WP_REST_Response( [ 'success' => false ], 404 );
    }

    $key = wp_generate_password( 20, false );
    global $wpdb;
    $wpdb->update( $wpdb->users, [ 'user_activation_key' => $key ], [ 'ID' => $uid ] );

    $data = base64_encode( json_encode( [ 'id' => $uid, 'key' => $key ] ) );
    $link = add_query_arg( 'activation-code', $data, home_url( '/my-account/activate' ) );
    $sent = wp_mail(
        $user->user_email,
        get_bloginfo( 'name' ) . ' — Hesap Aktivasyonu',
        'Hesabınızı aktive etmek için tıklayın: ' . $link
    );

    return new WP_REST_Response( [
        'success' => $sent,
        'message' => $sent ? 'Aktivasyon emaili gönderildi.' : 'Email gönderilemedi.',
    ], 200 );
}

/**
 * GET /membership/status
 * Giriş yapmış kullanıcının aktivasyon durumunu döndürür.
 */
function salt_mm_status( WP_REST_Request $r ): WP_REST_Response {
    $uid = get_current_user_id();
    $s   = get_user_meta( $uid, 'user_status', true );

    // Normalize
    if ( $s === '' || $s === false )  $s = 'pending';
    if ( $s === '1' || $s === 1 )    $s = 'approved';
    if ( $s === '0' || $s === 0 )    $s = 'pending';

    $req  = defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION;
    $type = get_user_meta( $uid, 'activation_type', true ) ?: 'email';

    return new WP_REST_Response( [
        'user_id'              => $uid,
        'status'               => $s,
        'is_active'            => in_array( $s, [ 'activated', 'approved' ], true ) || ! $req,
        'activation_required'  => $req,
        'activation_type'      => $type,
    ], 200 );
}

/**
 * POST /membership/approve
 * Admin: kullanıcıyı onayla.
 */
function salt_mm_approve( WP_REST_Request $r ): WP_REST_Response {
    $uid = $r->get_param( 'user_id' );

    if ( class_exists( 'SaltHareket\\Membership\\MembershipManager' ) ) {
        return new WP_REST_Response(
            \SaltHareket\Membership\MembershipManager::getInstance()->approveUser( $uid ),
            200
        );
    }

    update_user_meta( $uid, 'user_status', 'approved' );
    update_user_meta( $uid, 'approved_at', time() );
    update_user_meta( $uid, 'approved_by', get_current_user_id() );
    do_action( 'sh_account_approved', $uid );

    return new WP_REST_Response( [ 'success' => true, 'message' => 'Hesap onaylandı.' ], 200 );
}

/**
 * POST /membership/reject
 * Admin: kullanıcıyı reddet.
 */
function salt_mm_reject( WP_REST_Request $r ): WP_REST_Response {
    $uid    = $r->get_param( 'user_id' );
    $reason = $r->get_param( 'reason' );

    if ( class_exists( 'SaltHareket\\Membership\\MembershipManager' ) ) {
        return new WP_REST_Response(
            \SaltHareket\Membership\MembershipManager::getInstance()->rejectUser( $uid, $reason ),
            200
        );
    }

    update_user_meta( $uid, 'user_status', 'rejected' );
    update_user_meta( $uid, 'rejected_at', time() );
    if ( $reason ) update_user_meta( $uid, 'rejection_reason', $reason );
    do_action( 'sh_account_rejected', $uid );

    return new WP_REST_Response( [ 'success' => true, 'message' => 'Hesap reddedildi.' ], 200 );
}

/**
 * GET /membership/online/{user_id}
 * Kullanıcının online olup olmadığını döndürür.
 */
function salt_mm_online( WP_REST_Request $r ): WP_REST_Response {
    $uid = $r->get_param( 'user_id' );

    if ( class_exists( 'SaltHareket\\Membership\\MembershipManager' ) ) {
        return new WP_REST_Response( [
            'user_id' => $uid,
            'online'  => \SaltHareket\Membership\MembershipManager::isUserOnline( $uid ),
        ], 200 );
    }

    // Fallback — transient tabanlı online tracking
    $users = get_transient( 'sh_users_online' ) ?: [];
    return new WP_REST_Response( [
        'user_id' => $uid,
        'online'  => isset( $users[ $uid ] ) && $users[ $uid ] > ( time() - 15 * MINUTE_IN_SECONDS ),
    ], 200 );
}
