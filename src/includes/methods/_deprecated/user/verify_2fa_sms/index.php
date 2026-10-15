<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
// turbo_api_handle bu metodu handle ettiği için bu dosya artık çalışmaz.
$required_setting = ENABLE_MEMBERSHIP;
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
echo json_encode( $mm->verify2FASMS( $vars ) );
wp_die();
