<?php



function custom_message($message){
    echo "<script>alert('".$message."');</script>";
}
add_filter("woocommerce_add_notice", "custom_message", 1);





function my_woocommerce_membership_notice( $message="", $code=array() ) {
    /*if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }*/
    if ( ! session_id() ) {
        session_start();
    }
    $_SESSION["wp_notice"] = $message;
    add_action('wp_footer', function(){
        echo "<script>$( document ).ready(function() {_alert('".$_SESSION["wp_notice"]."');});</script>";
        unset($_SESSION["wp_notice"]);
    });
    return false;
}
add_filter( 'woocommerce_add_error', 'my_woocommerce_membership_notice');





function redirect_notice($message="", $type="success"){
	// Hata mesajını session'a kaydedelim
    /*if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }*/
    if ( ! session_id() ) {
        session_start();
    }
    if(!is_array($_SESSION['error_message'])){
        $_SESSION['error_message'] = array();
    }
    if(is_admin()){
       $_SESSION['error_message'][] = '<div class="notice notice-'.$type.' is-dismissible"><p>'.$message.'</p></div>';
    }else{
       $_SESSION['error_message'][] = $message;
    }
}
// Redirect sonrası hata mesajını göstermek için
function display_error_message() {
    /*if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }*/
    if ( ! session_id() ) {
        session_start();
    }
    if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'] && is_array($_SESSION['error_message']))) {
        foreach($_SESSION['error_message'] as $message){
            if(is_admin()){
                echo $message;
            }else{
                wc_print_notice($message, 'error');
            }            
        }
        unset($_SESSION['error_message']); // Hata mesajını temizle
    }
}
add_action('wp', 'display_error_message');
add_action('admin_notices', 'display_error_message');