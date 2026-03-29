<?php
$required_setting = ENABLE_MEMBERSHIP;

echo userSearchTermsRemove(wp_get_current_user());
wp_die();
