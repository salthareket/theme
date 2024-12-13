<?php

$required_plugins = array(
    'acf-extended/acf-extended.php',
    //'contact-form-7/wp-contact-form-7.php',
    //'post-smtp/postman-smtp.php',
    //'favicon-by-realfavicongenerator/favicon-by-realfavicongenerator.php',
    //'featured-image-admin-thumb-fiat/featured-image-admin-thumb.php',
    //'google-site-kit/google-site-kit.php',
    'post-type-archive-links/post-type-archive-links.php',
    'simple-custom-post-order/simple-custom-post-order.php',
    //'webp-converter-for-media/webp-converter-for-media.php',
    'yabe-webfont/yabe-webfont.php',
    'wordpress-seo/wp-seo.php',
);
if(ACTIVATE_UNDER_CONSTRUCTION){
    $required_plugins[] = 'underconstruction/underConstruction.php';
}
if(ENABLE_MEMBERSHIP){
    $required_plugins[] = 'one-user-avatar/one-user-avatar.php';
}
if(ENABLE_PUBLISH){
    $required_plugins[] = 'wp-scss/wp-scss.php';
}
$GLOBALS["plugins"] = $required_plugins;


$required_plugins_local = array();
$required_plugins_local[] = array(
    "v" => "6.3.11",
    "name" => "advanced-custom-fields-pro/acf.php",
    "file" => "advanced-custom-fields-pro"
);
$required_plugins_local[] = array(
    "v" => "1.0",
    "name" => "acf-bs-breakpoints/index.php",
    "file" => "acf-bs-breakpoints"
);
$required_plugins_local[] = array(
    "v" => "1.0",
    "name" => "acf-query-field/acf-query-field.php",
    "file" => "acf-query-field"
);
$required_plugins_local[] = array(
    "v" => "1.0",
    "name" => "acf-unit-field/index.php",
    "file" => "acf-unit-field"
);
$required_plugins_local[] = array(
    "v" => "1.0",
    "name" => "tinymce-shortcut-plugin/index.php",
    "file" => "tinymce-shortcut-plugin"
);
$required_plugins_local[] = array(
    "v" => "3.6.5",
    "name" => "polylang-pro/polylang.php",
    "file" => "polylang-pro"
);
$required_plugins_local[] = array(
    "v" => "3.17.3.1",
    "name" => "wp-rocket/wp-rocket.php",
    "file" => 'wp-rocket'
);
$required_plugins_local[] = array(
    "v" => "1.2",
    "name" => "ajaxflow/ajaxflow.php",
    "file" => 'ajaxflow'
);
$GLOBALS["plugins_local"] = $required_plugins_local;