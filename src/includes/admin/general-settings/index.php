<?php

use Timber\Timber;
use SaltHareket\Theme;

// Under Construction Cache Flush
if (class_exists('underConstruction')) {
    add_filter('option_underConstructionActivationStatus', function($status) {
        if ($status == '1' && function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        return $status;
    });
}

// ACF Setting Change Hooks
foreach (['enable_membership', 'enable_membership_activation', 'enable_chat', 'enable_notifications', 'enable_favorites'] as $fn) {
    add_filter("acf/update_value/name={$fn}", 'acf_general_settings_rewrite', 10, 4);
}
function acf_general_settings_rewrite($value, $post_id, $field, $original) {
    return $value;
}


add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_enable_membership', 10, 4);
function acf_general_settings_enable_membership($value, $post_id, $field, $original) {
    if ($value) {
        create_my_account_page();
    } else {
        $pid = get_option('woocommerce_myaccount_page_id');
        if ($pid) wp_delete_post($pid, true);
    }
    return $value;
}

add_filter('acf/update_value/name=enable_location_db', 'acf_general_settings_enable_location_db', 10, 4);
function acf_general_settings_enable_location_db($value, $post_id, $field, $original) {
    $ip2country = get_field('enable_ip2country', 'option');
    $settings   = get_field('ip2country_settings', 'option');
    return ($ip2country && $settings === 'db') ? 1 : $value;
}

add_filter('acf/update_value/name=enable_registration', 'acf_general_settings_registration', 10, 4);
function acf_general_settings_registration($value, $post_id, $field, $original) {
    update_option('users_can_register', $value);
    update_option('woocommerce_enable_myaccount_registration', $value ? 'yes' : 'no');
    return $value;
}

// Plugin Activation / Deactivation
add_filter('activated_plugin', 'admin_plugins_activated', 10, 2);
add_filter('deactivated_plugin', 'admin_plugins_deactivated', 10, 2);

function admin_plugins_activated($plugin, $network_activation) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        $page_on_front = get_option('page_on_front');
        set_my_account_page(true);
        $woo_pages = [
            ['endpoint' => 'shop',           'title' => 'Shop',     'content' => '',                       'template' => 'template-shop.php'],
            ['endpoint' => 'cart',           'title' => 'Cart',     'content' => '[woocommerce_cart]',     'template' => 'template-cart.php'],
            ['endpoint' => 'checkout',       'title' => 'Checkout', 'content' => '[woocommerce_checkout]', 'template' => 'template-checkout.php'],
            ['endpoint' => 'refund_returns', 'title' => 'Refund',   'content' => '',                       'template' => ''],
            ['endpoint' => 'order_received', 'title' => 'Order OK', 'content' => '',                       'template' => ''],
        ];
        foreach ($woo_pages as $p) {
            $pid = get_option('woocommerce_' . $p['endpoint'] . '_page_id');
            if (get_post_status($pid) === false) {
                $args = ['post_title' => $p['title'], 'post_content' => $p['content'], 'post_status' => 'publish', 'post_type' => 'page'];
                if (!empty($p['template'])) $args['page_template'] = $p['template'];
                $pid = wp_insert_post($args);
                update_option('woocommerce_' . $p['endpoint'] . '_page_id', $pid);
                if (empty($page_on_front) && $p['endpoint'] === 'shop') {
                    update_option('page_on_front', $pid);
                    update_option('show_on_front', 'page');
                }
            }
        }
        if (function_exists('acf_development_methods_settings')) acf_development_methods_settings(1);
    }
    if ($plugin === 'underconstruction/underConstruction.php') {
        $pid = wp_insert_post(['post_title' => 'Under Construction', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page', 'page_template' => 'under-construction.php']);
        update_option('under-construction-page', $pid);
    }
}

function admin_plugins_deactivated($plugin, $network_activation) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        set_my_account_page(false);
        foreach (['shop', 'cart', 'checkout', 'refund_returns', 'order_received'] as $ep) {
            wp_delete_post(wc_get_page_id($ep), true);
        }
        if (function_exists('acf_development_methods_settings')) acf_development_methods_settings(1);
    }
    if ($plugin === 'underconstruction/underConstruction.php') {
        $pid = (int) get_option('under-construction-page');
        if ($pid) { wp_delete_post($pid, true); delete_option('under-construction-page'); }
    }
}


// My Account Page Management
function create_my_account_page() {
    $key = class_exists('WooCommerce') ? 'woocommerce_myaccount_page_id' : 'options_myaccount_page_id';
    $pid = get_option($key);
    if ($pid) return $pid;

    $pid = wp_insert_post([
        'post_title'    => 'My Account',
        'post_content'  => class_exists('WooCommerce') ? '[woocommerce_my_account]' : '[salt_my_account]',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'page_template' => 'template-my-account.php',
    ]);
    if (!is_wp_error($pid)) update_option($key, $pid);
    return $pid;
}

function set_my_account_page($ecommerce = true) {
    $key = $ecommerce ? 'woocommerce_myaccount_page_id' : 'options_myaccount_page_id';
    $pid = get_option($key);
    if (!$pid) { create_my_account_page(); return; }

    $content = (class_exists('WooCommerce') && $ecommerce) ? '[woocommerce_my_account]' : '[salt_my_account]';
    wp_update_post(['ID' => $pid, 'post_content' => $content]);
    if (class_exists('WooCommerce') && $ecommerce) update_option('woocommerce_myaccount_page_id', $pid);
}

add_filter('acf/update_value/name=enable_membership', 'check_my_account_page', 10, 4);
function check_my_account_page($value, $post_id, $field, $original) {
    if ($field['name'] !== 'enable_membership' || !$value) return $value;
    set_my_account_page();
    if (!class_exists('SaltHareket\MethodClass')) require_once SH_CLASSES_PATH . 'class.methods.php';
    $m = new SaltHareket\MethodClass();
    $m->createFiles(false);
    $m->createFiles(false, 'admin');
    if (function_exists('redirect_notice')) redirect_notice('Frontend/Backend methods compiled!', 'success');
    return $value;
}