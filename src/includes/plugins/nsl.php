<?php

/**
 * NextEnd Social Login — Login error mesajı + registration hook'ları.
 */

// ─── Registration Role (Opsiyonel) ──────────────────────────

// add_filter('nsl_register_new_user', 'social_registration_set_role', 999999, 2);
function social_registration_set_role($user_id, $provider) {
    $user = new User($user_id);
    $log  = new Logger();
    $log->logAction('nsl_register_new_user', $provider);
}

// add_filter('nsl_login', 'social_registration_login', 199990, 2);
function social_registration_login($user_id, $provider) {
    $log = new Logger();
    $log->logAction('nsl_login', json_encode($provider));
}

// ─── Login Error — Sosyal hesap bilgisi göster ──────────────

add_filter('login_errors', 'nsl_login_error_message');
function nsl_login_error_message($error) {
    $email = isset($_POST['username']) ? sanitize_email($_POST['username']) : '';
    if (empty($email)) return $error;

    $user = get_user_by('email', $email);
    if (!$user || !isset($user->ID)) return $error;

    $user_obj  = new User($user->ID);
    $providers = $user_obj->get_social_login_providers();
    if (empty($providers)) return $error;

    $list = '<b>' . implode('</b> or <b>', $providers) . '</b>';
    return 'Since you registered using your ' . $list . ' account' . (count($providers) > 1 ? 's' : '') .
           ', please log in using those accounts.<br><br>' .
           'Then, define your password through the <u>Profile → Security</u> page to log in with email and password.';
}
