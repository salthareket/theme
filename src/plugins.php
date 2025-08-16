<?php
// main, multilanguage, ecommerce, contact-forms, membership, social-share, automation
$required_plugins = array(
    array(
        "type" => ["main"],
        "name" => 'acf-extended/acf-extended.php'
    ),
    array(
        "type" => ["main"],
        "name" => 'favicon-by-realfavicongenerator/favicon-by-realfavicongenerator.php'
    ),
    array(
        "type" => ["main"],
        "name" => 'google-site-kit/google-site-kit.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'post-type-archive-links/post-type-archive-links.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'simple-custom-post-order/simple-custom-post-order.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'yabe-webfont/yabe-webfont.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'wordpress-seo/wp-seo.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'underconstruction/underConstruction.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'acf-openstreetmap-field/index.php',
    ),
    array(
        "type" => ["main"],
        "name" => "advanced-custom-fields-table-field/acf-table.php"
    ),
    array(
        "type" => ["main", "ai"],
        "name" => "website-llms-txt/website-llms-txt.php"
    ),
    array(
        "type" => ["multilanguage"],
        "name" => "acf-options-for-polylang/bea-acf-options-for-polylang.php",
    ),
    array(
        "type" => ["multilanguage"],
        "name" => "duplicate-content-addon-for-polylang/duplicate-content-addon-for-polylang.php",
    ),
    array(
        "type" => ["multilanguage"],
        "name" => "loco-translate/loco.php",
    ),
    array(
        "type" => ["multilanguage", "contact-forms"],
        "name" => "multilingual-contact-form-7-with-polylang/plugin.php",
    ),
    array(
        "type" => ["membership"],
        "name" => 'one-user-avatar/one-user-avatar.php',
    ),
    array(
        "type" => ["membership"],
        "name" => 'nextend-facebook-connect/nextend-facebook-htmleditorconnect.php',
        "depency" => "nextend-social-login-pro/nextend-social-login-pro.php"
    ),
    array(
        "type" => ["contact-forms"],
        "name" => 'contact-form-7/wp-contact-form-7.php'
    ),
    array(
        "type" => ["contact-forms"],
        "name" => 'fluent-smtp/fluent-smtp.php'
    ),
    array(
        "type" => ["ecommerce"],
        "name" => 'woocommerce/woocommerce.php'
    ),
    array(
        "type" => ["social-share"],
        "name" => 'wp-socializer/wpsr.php'
    ),
    array(
        "type" => ["newsletter"],
        "name" => 'newsletter/plugin.php'
    ),
    array(
        "type" => ["automation"],
        "name" => "advanced-cron-manager/advanced-cron-manager.php"
    ),
    array(
        "type" => ["automation"],
        "name" => "bulk-add-terms/bulk-add-terms.php"
    ),
    array(
        "type" => ["cookie"],
        "name" => 'complianz-gdpr/complianz-gpdr.php'
    ),
    array(
        "type" => ["security"],
        "name" => 'wordfence/wordfence.php'
    ),
);
if(ACTIVATE_UNDER_CONSTRUCTION){
    //$required_plugins[] = 'underconstruction/underConstruction.php';
}
if(ENABLE_MEMBERSHIP){
    //$required_plugins[] = 'one-user-avatar/one-user-avatar.php';
}
$GLOBALS["plugins"] = $required_plugins;


$required_plugins_local = array();
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "6.5.0.1",
    "name" => "advanced-custom-fields-pro/acf.php",
    "file" => "advanced-custom-fields-pro"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0",
    "name" => "acf-bs-breakpoints/index.php",
    "file" => "acf-bs-breakpoints"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0",
    "name" => "acf-query-field/acf-query-field.php",
    "file" => "acf-query-field"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0",
    "name" => "acf-unit-field/index.php",
    "file" => "acf-unit-field"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0",
    "name" => "tinymce-shortcut-plugin/index.php",
    "file" => "tinymce-shortcut-plugin"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "3.19.3",
    "name" => "wp-rocket/wp-rocket.php",
    "file" => 'wp-rocket'
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.2",
    "name" => "ajaxflow/ajaxflow.php",
    "file" => 'ajaxflow'
);
$required_plugins_local[] = array(
    "type" => ["multilanguage"],
    "v" => "3.7.3",
    "name" => "polylang-pro/polylang.php",
    "file" => "polylang-pro"
);
$required_plugins_local[] = array(
    "type" => ["membership"],
    "v" => "2.4",
    "name" => "yobro/yobro.php",
    "file" => "yobro"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce", "multilanguage"],
    "v" => "2.1",
    "name" => "polylang-wc/polylang-wc.php",
    "file" => "polylang-wc"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "0.2.0",
    "name" => "clear-cache-for-timber/clear-cache-for-timber.php",
    "file" => "clear-cache-for-timber"
);
$required_plugins_local[] = array(
    "type" => ["security"],
    "v" => "7.7.2",
    "name" => "wp-hide-security-enhancer-pro/wp-hide.php",
    "file" => "wp-hide-security-enhancer-pro"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "2.3.2",
    "name" => "woocommerce-additional-variation-images/woocommerce-additional-variation-images.php",
    "file" => "woocommerce-additional-variation-images"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "1.0",
    "name" => "woocommerce-variation-links/plugin.php",
    "file" => "woocommerce-variation-links"
);
$required_plugins_local[] = array(
    "type" => ["membership"],
    "v" => "3.1.18",
    "name" => "nextend-social-login-pro/nextend-social-login-pro.php",
    "file" => "nextend-social-login-pro",
    "depency" => 'nextend-facebook-connect/nextend-facebook-connect.php'
);
$required_plugins_local[] = array(
    "type" => ["search", "ecommerce"],
    "v" => "1.30.1",
    "name" => "ajax-search-for-woocommerce-premium/ajax-search-for-woocommerce.php",
    "file" => "ajax-search-for-woocommerce-premium"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "8.4.0",
    "name" => "woocommerce-product-bundles/woocommerce-product-bundles.php",
    "file" => "woocommerce-product-bundles"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "1.25.0",
    "name" => "iconic-woo-show-single-variations/iconic-woo-show-single-variations.php",
    "file" => "iconic-woo-show-single-variations"
);
$required_plugins_local[] = array(
    "type" => ["search", "ecommerce"],
    "v" => "5.5.0",
    "name" => "yith-woocommerce-ajax-product-filter-premium/init.php",
    "file" => "yith-woocommerce-ajax-product-filter-premium"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0.0",
    "name" => "gradient-picker-for-acf/gradient-picker-for-acf.php",
    "file" => "gradient-picker-for-acf"
);

$GLOBALS["plugins_local"] = $required_plugins_local;
