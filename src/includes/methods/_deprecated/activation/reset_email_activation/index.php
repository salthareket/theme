<?php
// DEPRECATED (2026-09-04) — membership/bootstrap.php turbo_api_handle filter'ına taşındı.
$required_setting = ENABLE_MEMBERSHIP;
$user_id = get_current_user_id();
delete_user_meta( $user_id, 'email_activation_pending' );
echo json_encode( [ 'error' => false, 'message' => 'Email activation reset.' ] );
wp_die();
