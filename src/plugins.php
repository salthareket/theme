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
        "name" => 'yabe-webfont/jooosi-fon.php', // Plugin Yabe Webfont → Jooosi Fon olarak yeniden adlandırıldı (2026-08-26)
    ),
    array(
        "type" => ["main"],
        "name" => 'wordpress-seo/wp-seo.php',
    ),
    array(
        "type" => ["main"],
        "name" => 'acf-openstreetmap-field/index.php',
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
        "dependency" => "nextend-social-login-pro/nextend-social-login-pro.php"
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
    array(
        "type" => ["main"],
        "name" => 'flush-opcache/flush-opcache.php'
    ),
    array(
        "type" => ["ecommerce"],
        "name" => 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php'
    ),
);
Data::set("plugins", $required_plugins);

$required_plugins_local = array();
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "6.8.10",
    "name" => "advanced-custom-fields-pro/acf.php",
    "file" => "advanced-custom-fields-pro"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "3.23.3.3",
    "name" => "wp-rocket/wp-rocket.php",
    "file" => 'wp-rocket'
);
$required_plugins_local[] = array(
    "type" => ["multilanguage"],
    "v" => "3.8.5",
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
    "v" => "2.2.2",
    "name" => "polylang-wc/polylang-wc.php",
    "file" => "polylang-wc"
);
$required_plugins_local[] = array(
    "type" => ["security"],
    "v" => "7.7.2",
    "name" => "wp-hide-security-enhancer-pro/wp-hide.php",
    "file" => "wp-hide-security-enhancer-pro"
);
/*$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "2.3.3",
    "name" => "woocommerce-additional-variation-images/woocommerce-additional-variation-images.php",
    "file" => "woocommerce-additional-variation-images"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "1.1",
    "name" => "woocommerce-variation-links/woocommerce-variation-links.php",
    "file" => "woocommerce-variation-links"
);*/
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "2.5.1",
    "name" => "woo-variation-swatches-pro/woo-variation-swatches-pro.php",
    "file" => "woo-variation-swatches-pro"
);
$required_plugins_local[] = array(
    "type" => ["membership"],
    "v" => "3.1.25",
    "name" => "nextend-social-login-pro/nextend-social-login-pro.php",
    "file" => "nextend-social-login-pro",
    "dependency" => 'nextend-facebook-connect/nextend-facebook-connect.php'
);
$required_plugins_local[] = array(
    "type" => ["search", "ecommerce"],
    "v" => "1.33.0",
    "name" => "ajax-search-for-woocommerce-premium/ajax-search-for-woocommerce.php",
    "file" => "ajax-search-for-woocommerce-premium"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "8.5.12",
    "name" => "woocommerce-product-bundles/woocommerce-product-bundles.php",
    "file" => "woocommerce-product-bundles"
);
$required_plugins_local[] = array(
    "type" => ["main", "ecommerce"],
    "v" => "1.1",
    "name" => "yith-activator/yith-activator.php",
    "file" => "yith-activator"
);
$required_plugins_local[] = array(
    "type" => ["search", "ecommerce"],
    "v" => "5.23.0",
    "name" => "yith-woocommerce-ajax-product-filter-premium/init.php",
    "file" => "yith-woocommerce-ajax-product-filter-premium"
);
$required_plugins_local[] = array(
    "type" => ["seo", "ecommerce"],
    "v" => "16.8",
    "name" => "wpseo-woocommerce/wpseo-woocommerce.php",
    "file" => "wpseo-woocommerce"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "2.0.0",
    "name" => "acf-bs-breakpoints/acf-bs-breakpoints.php",
    "file" => "acf-bs-breakpoints"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.2.4",
    "name" => "acf-query-field/acf-query-field.php",
    "file" => "acf-query-field"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.1.0",
    "name" => "acf-unit-field/acf-unit-field.php",
    "file" => "acf-unit-field"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0.2",
    "name" => "tinymce-shortcut-plugin/tinymce-shortcut-plugin.php",
    "file" => "tinymce-shortcut-plugin"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.0.2",
    "name" => "gradient-picker-for-acf/gradient-picker-for-acf.php",
    "file" => "gradient-picker-for-acf"
);
$required_plugins_local[] = array(
    "type" => ["main"],
    "v" => "1.3.31",
    "name" => "salt-table-for-acf/salt-table-for-acf.php",
    "file" => "salt-table-for-acf"
);
$required_plugins_local[] = array(
    "type" => ["multilanguage", "ai"],
    "v" => "3.4.0",
    "name" => "salt-ai-translator/salt-ai-translator.php",
    "file" => "salt-ai-translator"
);
$required_plugins_local[] = array(
    "type" => ["multilanguage"],
    "v" => "1.0.3",
    "name" => "acf-polylang-text-field/acf-polylang-text-field.php",
    "file" => "acf-polylang-text-field"
);
$required_plugins_local[] = array(
    "type" => ["ecommerce"],
    "v" => "1.2.5",
    "name" => "xt-woo-variations-as-singles/xt-woo-variations-as-singles.php",
    "file" => "xt-woo-variations-as-singles"
);

Data::set("plugins_local", $required_plugins_local);
