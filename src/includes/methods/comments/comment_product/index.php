<?php
$salt = \Salt::get_instance();
echo $salt->comment_product($vars);
wp_die();
