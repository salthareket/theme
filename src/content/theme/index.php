<?php

if (defined('THEME_INCLUDES_LOADED')) {
    return; // Eğer daha önce yüklendiyse tekrar yükleme
}

include_once "includes/custom-extend.php";
include_once "includes/cron.php";
include_once "includes/rewrite.php";
include_once "includes/roles.php";
include_once "includes/blocks.php";

if(ENABLE_MEMBERSHIP){
	include_once "includes/my-account.php";
}
if(ENABLE_ECOMMERCE){
	include_once "includes/woocommerce.php";
}
include_once "includes/project.php";
include_once "functions.php";

define('THEME_INCLUDES_LOADED', true);
