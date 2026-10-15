<?php
/**
 * WooCommerce Login Form — Timber/Twig override.
 * Bizim form-login-woo.twig template'ini render eder.
 * 2FA step, passwordless step ve register form dahil.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0 (override)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Timber\Timber' ) ) {
    // Timber yoksa WC'nin default template'ini yükle
    wc_get_template( 'myaccount/form-login.php' );
    return;
}

$context = \Timber\Timber::context();

// Login Security flags — startersite add_to_context'te ekleniyor
// Ama bu template doğrudan çağrıldığında context eksik olabilir, güvence için ekle
if ( ! isset( $context['enable_passwordless'] ) ) {
    $context['enable_passwordless'] = defined('ENABLE_PASSWORDLESS') && ENABLE_PASSWORDLESS;
}
if ( ! isset( $context['login_2fa_method'] ) ) {
    $context['login_2fa_method'] = defined('LOGIN_2FA_METHOD') ? LOGIN_2FA_METHOD : 'none';
}
if ( ! isset( $context['enable_social_login'] ) ) {
    $context['enable_social_login'] = defined('ENABLE_SOCIAL_LOGIN') && ENABLE_SOCIAL_LOGIN;
}
if ( ! isset( $context['enable_lost_password'] ) ) {
    $context['enable_lost_password'] = defined('ENABLE_LOST_PASSWORD') && ENABLE_LOST_PASSWORD;
}
if ( ! isset( $context['enable_remember_login'] ) ) {
    $context['enable_remember_login'] = defined('ENABLE_REMEMBER_LOGIN') && ENABLE_REMEMBER_LOGIN;
}

\Timber\Timber::render( 'my-account/form-login-woo.twig', $context );
