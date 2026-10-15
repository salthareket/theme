<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
$required_setting = ENABLE_MEMBERSHIP;
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
echo json_encode( $mm->resendOtp( $vars ) );
wp_die();
