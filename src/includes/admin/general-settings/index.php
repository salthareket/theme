<?php
use Timber\Timber;
use Timber\Loader;
use SaltHareket\Theme;


if(class_exists("underConstruction")){
    add_filter( 'option_underConstructionActivationStatus', function( $status ){
        if($status == "1"){
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
        }
        return $status;
    });    
}


// on global settings changed
function acf_general_settings_rewrite( $value, $post_id, $field, $original ) {
    $old = get_field($field["name"], "option");
    if( $value != $old) {
        flush_rewrite_rules();
    }
    return $value;
}
add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_membership_activation', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_chat', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_notifications', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_favorites', 'acf_general_settings_rewrite', 10, 4);



function acf_general_settings_enable_membership( $value, $post_id, $field, $original ) {
    $old = get_field($field["name"], "option");
    if( $value ) {
       create_my_account_page(); 
    }else{
       $my_account_page = get_option("woocommerce_myaccount_page_id");//get_page_by_path('my-account');
       if ($my_account_page) {
           wp_delete_post($my_account_page, true);
           //wp_delete_post($my_account_page->ID, true);
       }
    }
    return $value;
}
add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_enable_membership', 10, 4);



function acf_general_settings_enable_location_db( $value, $post_id, $field, $original ) {
    $ip2country = get_field("enable_ip2country", "option");
    $settings = get_field("ip2country_settings", "option");
    if( $ip2country && $settings == "db") {
        $value = 1;
    }
    return $value;
}
add_filter('acf/update_value/name=enable_location_db', 'acf_general_settings_enable_location_db', 10, 4);



function acf_general_settings_registration( $value, $post_id, $field, $original ) {
    update_option("users_can_register", $value);
    update_option("woocommerce_enable_myaccount_registration", $value?"yes":"no");
    return $value;
}
add_filter('acf/update_value/name=enable_registration', 'acf_general_settings_registration', 10, 4);


function plugins_activated($plugin, $network_activation) {
    if($plugin == "woocommerce/woocommerce.php"){
        $page_on_front = get_option( 'page_on_front' );
        set_my_account_page(true);
        $woo_pages = array(
            array(
                "endpoint" => "shop",
                "title"    => "Mağaza",
                "content"  => "",
                "template" => "template-shop.php"
            ),
            array(
                "endpoint" => "cart",
                "title"    => "Sepet",
                "content"  => "[woocommerce_cart]",
                "template" => "template-cart.php"
            ),
            array(
                "endpoint" => "checkout",
                "title"    => "Ödeme",
                "content"  => "[woocommerce_checkout]",
                "template" => "template-checkout.php"
            ),
            array(
                "endpoint" => "refund_returns",
                "title"    => "Geri Ödeme ve İade Politikası",
                "content"  => "",
                "template" => ""
            ),
            array(
                "endpoint" => "order_received",
                "title"    => "Sipariş Tamamlandı",
                "content"  => "",
                "template" => ""
            )
        );
        foreach($woo_pages as $page){
            $page_id = get_option( "woocommerce_".$page["endpoint"]."_page_id");
            if ( FALSE === get_post_status( $page_id ) ){
                $args = array(
                    'post_title'    => $page["title"],
                    'post_content'  => $page["content"],
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                );
                if(!empty($page["template"])){
                   $args["page_template"] = $page["template"];
                }
                $page_id = wp_insert_post($args);
                update_option("woocommerce_".$page["endpoint"]."_page_id", $page_id);
                if(empty($page_on_front) && $page["endpoint"] == "shop"){
                    update_option( 'page_on_front', $page_id );
                    update_option( 'show_on_front', 'page' );
                }
            }
        }
        acf_development_methods_settings(1);
    }
    if($plugin == "underconstruction/underConstruction.php"){
        $args = array(
            'post_title'    => 'Under Construction',
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'page_template' => 'under-construction.php'
        );
        $page_id = wp_insert_post($args);
        if (get_option('under-construction-page') === false) {
            add_option('under-construction-page', $page_id);
        } else {
            update_option('under-construction-page', $page_id);
        }
    }
}
function plugins_deactivated($plugin, $network_activation) {
    if($plugin == "woocommerce/woocommerce.php"){
        set_my_account_page(false);
        foreach(['shop', 'cart', 'checkout', 'refund_returns', 'order_received'] as $page){
            wp_delete_post(wc_get_page_id( $page ), true);
        }
        acf_development_methods_settings(1);
    }
    if($plugin == "underconstruction/underConstruction.php"){
        if (get_option('under-construction-page') != false) {
            $page = intval(get_option('under-construction-page'));
            if($page){
                wp_delete_post($page, true);
                delete_option('under-construction-page');
            }
        }
    }
}
add_filter('activated_plugin', 'plugins_activated', 10, 2);
add_filter('deactivated_plugin', 'plugins_deactivated', 10, 2);





function create_my_account_page(){
    $my_account_page = class_exists("WooCommerce")?get_option("woocommerce_myaccount_page_id"):get_option("options_myaccount_page_id");//get_page_by_path('my-account');
    if (!$my_account_page) {
        $args = array(
            'post_title'    => 'My Account',
            'post_content'  => '[salt_my_account]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'page_template' => 'template-my-account.php'
            //'page_template' => 'template-my-account-native.php'
        );
        if(class_exists("WooCommerce")){
            $args["post_content"] = "[woocommerce_my_account]";
            //$args["page_template"] = 'template-my-account.php';
        }
        $my_account_page = wp_insert_post($args);
        if (!is_wp_error($my_account_page)) {
            if(class_exists("WooCommerce")){
                update_option('woocommerce_myaccount_page_id', $my_account_page);
            }else{
                update_option('options_myaccount_page_id', $my_account_page);
            }
        }
        return $my_account_page;
    }else{
        return $my_account_page;//$my_account_page->ID;
    }
}
function set_my_account_page($enabled_ecommerce=true){
        // Create My Account Page if membership is enabled but woocommerce is not exist
        $my_account_page = $enabled_ecommerce?get_option("woocommerce_myaccount_page_id"):get_option("options_myaccount_page_id");//get_page_by_path('my-account');
        if (!$my_account_page) {
            //$my_account_page_id = 
            create_my_account_page();
        }else{
            $args = array(
                'ID'            => $my_account_page,//$my_account_page->ID,
                //'page_template' => 'template-my-account-native.php',
                'post_content'  => '[salt_my_account]'
            );
            if(class_exists("WooCommerce") && $enabled_ecommerce){
                $args["post_content"] = "[woocommerce_my_account]";
                //$args["page_template"] = 'template-my-account.php';
                //$woo_my_account_page_id = get_option("woocommerce_myaccount_page_id");
                //wp_delete_post($woo_my_account_page_id, true);
                update_option("woocommerce_myaccount_page_id", $my_account_page);//$my_account_page->ID);
            }
            wp_update_post($args);
        }
}
function check_my_account_page( $value, $post_id, $field, $original ){
    if($field["name"] == "enable_membership" && $value == 1){
        if($value){
            set_my_account_page();
        }
        if(!class_exists("SaltHareket\MethodClass")){
            require_once SH_CLASSES_PATH . "class.methods.php";
        }
        $methods = new SaltHareket\MethodClass();
        $methods->createFiles(false); 
        $methods->createFiles(false, "admin");
        if(function_exists("redirect_notice")){
            redirect_notice("Frontend/Backend methods compiled!", "success");
        }
    }
    return $value;
}
add_filter('acf/update_value/name=enable_membership', 'check_my_account_page', 10, 4);