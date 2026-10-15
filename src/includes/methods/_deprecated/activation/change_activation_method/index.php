<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
// Bu dosya geriye uyumluluk için korunuyor — compiled index.php hâlâ referans veriyor.
// Yeni metodlar membership/bootstrap.php'ye eklenmeli.
$required_setting = ENABLE_MEMBERSHIP_ACTIVATION;
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
echo json_encode( $mm->changeActivationMethod( $vars ) );
wp_die();
