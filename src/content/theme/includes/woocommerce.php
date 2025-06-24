<?php

include_once  "woocommerce/functions.php";
include_once 'woocommerce/admin.php';

if (ENABLE_MEMBERSHIP) {
    include_once  "woocommerce/hooks/redirect.php";
    include_once "woocommerce/hooks/my-account.php";
}

include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/product.php';
include_once 'woocommerce/hooks/loop.php';
include_once 'woocommerce/hooks/single-product.php';
include_once 'woocommerce/hooks/product-category.php';

if(ENABLE_CART){
    include_once 'woocommerce/custom-thankyou.php'; 
    include_once 'woocommerce/hooks/checkout.php';
    include_once 'woocommerce/hooks/cart.php';
}

if(!DISABLE_COMMENTS){
    //include_once 'woocommerce/hooks/comments.php';   
}