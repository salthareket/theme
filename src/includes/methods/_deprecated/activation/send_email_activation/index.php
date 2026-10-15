<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
$required_setting = ENABLE_MEMBERSHIP;
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
$user_id = (int) ( $vars['user_id'] ?? get_current_user_id() );
update_user_meta( $user_id, 'activation_type', 'email' );
echo json_encode( $mm->sendActivation( $user_id ) );
wp_die();
