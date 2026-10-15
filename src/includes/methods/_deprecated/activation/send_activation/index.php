<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
$required_setting = ENABLE_MEMBERSHIP;
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
$user_id = (int) ( $vars['id'] ?? get_current_user_id() );
echo json_encode( $mm->sendActivation( $user_id ) );
wp_die();
