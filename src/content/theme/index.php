<?php

include "includes/custom-extend.php";
include "includes/cron.php";
include "includes/rewrite.php";
include "includes/roles.php";
include "includes/blocks.php";

if(ENABLE_MEMBERSHIP){
	include "includes/my-account.php";
}
if(ENABLE_ECOMMERCE){
	include "includes/woocommerce.php";
}

include "functions.php";