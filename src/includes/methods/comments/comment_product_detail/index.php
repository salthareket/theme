<?php
$salt = \Salt::get_instance();
echo $salt->comment_product_detail($vars);
wp_die();
