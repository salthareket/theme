<?php
$required_setting = ENABLE_MEMBERSHIP;

try {
    $salt = \Salt::get_instance();
    echo json_encode($salt->update_profile($vars));
} catch (\Throwable $e) {
    error_log('[update_profile] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
}
wp_die();
