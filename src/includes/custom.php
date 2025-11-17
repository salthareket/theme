<?php

use SaltHareket\Theme;

// class in twig example with "class_salt"
// {% set projects =  {"function": "ads", "action":"search", "work_type": data.work_type, "expertise": data.expertise, "user_id": user.id}|class_salt %}

class SaltBase{

    public $user;
    public $localization;
    public $search_history;
    public $extractor;
    private static $already_ran = false;

    private static $instance;
    
    public static function get_instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct($user=array()) {

        error_log("new Salt()");

        add_action('wp_login', [ $this, 'on_user_login' ], 10, 2);
        add_action('wp_logout', [ $this, 'on_user_logout' ], 10, 1);

        if(defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP && !is_admin()){
            add_action('wp', [ $this, 'update_online_users_status' ]);
            //add_action('wp_login', [ $this, 'on_user_login' ], 10, 2);
            add_action('wp_insert_comment', 'on_insert_comment', 10, 2);
        }
        
        if(defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP && !is_admin() && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') === false){
           //add_action('init', [ $this, 'start_session'], 1); // start global session for saving the referer url
           //add_action('wp_logout', [ $this, 'on_user_logout' ], 10, 1);
           //Bypass logout confirmation on nonce verification failure
           add_action('check_admin_referer', [ $this, 'logout_without_confirmation'], 1, 2);
           add_action('login_redirect', [ $this, 'on_login_redirect' ], 10, 3);
        }

        //disable revisions
        remove_action( 'post_updated', 'wp_save_post_revision' );

        if(ENABLE_MEMBERSHIP && !is_admin()){
            if(ENABLE_MEMBERSHIP_ACTIVATION){
                add_action('template_redirect', [ $this,'user_not_activated' ]);                
            }
            add_action('template_redirect', [ $this,'user_profile_not_completed' ]);
            add_action('template_redirect', [ $this,'redirect_to_profile' ]);
        }

        
        /*add_action( 'user_register', array( $this, 'send_activation' ), 10, 1 );
        add_action( 'woocommerce_created_customer', array( $this, 'send_activation' ), 10, 1 );
        add_action( 'register_new_user', array( $this, 'send_activation' ), 10, 1 );*/
        //add_filter('acf/settings/row_index_offset', '__return_zero');//'__return_zero');

        //hide fields from admin profile page
        if(ENABLE_ECOMMERCE){
            //add_action( 'init', 'hide_admin_shipping_details' );
            //add_action('admin_head','hide_personal_options');
            //add_filter('user_contactmethods', 'hide_contact_methods');
            //add_action( 'personal_options', array ( 'hide_biography', 'start' ) );
        }
        if (defined("WPSEO_FILE")) {
            //add_action('admin_head', 'hide_yoast_profile');
        }
        //remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

        //post types save event
        if (has_action('save_post', [ $this, 'on_post_published' ])) {
            remove_action('save_post', [ $this, 'on_post_published' ], 100);
        }
        add_action('save_post', [ $this, 'on_post_published'], 100, 3);
        add_action('save_post_product', [ $this, 'on_post_published'], 100, 3);
        add_action('publish_post', [ $this, 'on_post_published'], 100, 3);
        add_action('wp_insert_post_data', [$this, 'on_post_pre_update'], 10, 2 );

        add_action('created_term', [$this, 'on_term_published'], 10, 3);
        add_action('edited_term', [$this, 'on_term_published'], 10, 3);

        // unseen tour count to admin menu
        //add_action( 'load-post.php', 'custom_content_conversion' );

        // send notification mail to receiver on sent a message
        /*if (class_exists("Redq_YoBro")) {
            add_filter('yobro_after_store_message', [ $this, 'after_store_new_message' ]);
        }*/

        // user
        if(ENABLE_ECOMMERCE){
            add_action( 'user_register', [ $this, 'user_register_hook'], 10, 1 );
        }
        add_action( 'edit_user_profile_update', [ $this,'user_before_update_hook'] );
        add_action( 'profile_update', [ $this,'user_after_update_hook'], 10, 2 );
        add_action( 'update_user_meta', [ $this, 'user_after_meta_update_hook'], 10, 4);

        //delete
        add_action( 'before_delete_post', [ $this,'on_post_delete'], 10, 1 );
        add_action( 'delete_user', [ $this,'on_user_delete'], 10 );
        add_action( 'shutdown', [ $this,'delete_session_data']);
        
        //scripts
        if(!is_admin()){
            //add_action( 'wp_enqueue_scripts', [$this, 'site_config_js'], 20 );
        }else{
            add_filter('acf/update_value', [$this, 'clear_cached_option'], 10, 3);
            //add_action('admin_init', [$this, 'site_config_js'], 20 );       
        }

        //add_action('wp_footer', [$this, 'add_page_assets']);
        if (class_exists('Timber\Timber')) {
            if($user){
               $this->user = Timber\Timber::get_user($user);//wp_get_current_user();//new User($user);
            }else{
               $this->user = Timber\Timber::get_user(wp_get_current_user());//wp_get_current_user();//new User(wp_get_current_user());
            }
        }

        if((ENABLE_IP2COUNTRY && ENABLE_IP2COUNTRY_DB) || ENABLE_LOCATION_DB){
            $localization = new Localization();
            $localization->woocommerce_support = true;
            $this->localization = $localization;
        }else{
            $this->localization = [];
        }
        
        //if(is_admin()){
        //    $extractor = new PageAssetsExtractor();
        //    $this->extractor = $extractor;            
        //}

        if(defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY){
            $search_history = new SearchHistory();
            $this->search_history = $search_history;            
        }

        $timezone = $this->user->get_timezone();

        if($timezone){
            if(strpos($timezone, "/") > 0){
                //print_r($timezone);
                date_default_timezone_set($timezone);
            }
        }
    }

    public function response(){
        return array(
            "error"       => false,
            "message"     => '',
            "description" => '',
            "data"        =>  "",
            "resubmit"    => false,
            "redirect"    => "",
            "refresh"     => false,
            "html"        => "",
            "template"    => ""
        );
    }

    public static function get_cached_query($args = []) {
        $cache_key = 'custom_query_' . md5(serialize($args));
        //delete_transient('custom_query_' . md5(serialize($args)));
        $cached_data = get_transient($cache_key);
        if (false === $cached_data) {
            $query = new WP_Query($args);
            $cached_data = $query;
            set_transient($cache_key, $cached_data, HOUR_IN_SECONDS); // 1 saat cache
        }
        return $cached_data;
    }

    public static function get_cached_posts($args = []) {
        $cache_key = 'custom_query_' . md5(serialize($args));
        //delete_transient('custom_query_' . md5(serialize($args)));
        $cached_data = get_transient($cache_key);
        if (false === $cached_data) {
            $query = new WP_Query($args);
            $cached_data = $query->posts;
            set_transient($cache_key, $cached_data, HOUR_IN_SECONDS); // 1 saat cache
        }
        return $cached_data;
    }

    public static function get_cached_option($key) {
        $cache_key = 'acf_option_' . $key;
        $cached_value = get_transient($cache_key);

        if ($cached_value !== false) {
            return $cached_value;
        }

        $value = null; // 🟢 Başta tanımla

        if (function_exists("get_field")) {
            $value = get_field($key, 'option'); // ACF Option’dan veriyi çek
        }

        set_transient($cache_key, $value, YEAR_IN_SECONDS); // 1 yıl boyunca sakla

        return $value;
    }


    public function clear_cached_option($value, $post_id, $field) {
        if ($post_id !== 'options') return $value;
        $cache_key = 'acf_option_' . $field['name'];
        //error_log("deleting: ".$cache_key); 
        delete_transient($cache_key); // Sadece ilgili option’ın cache’ini sil
        //error_log("-----------------------------------");
        return $value;
    }

    
    public function on_post_pre_update($data){
        /*if($data["post_type"] == "page"){
           $menu_order = wp_count_posts("page")->publish;
           $menu_order = $menu_order>0?$menu_order-1:0;
           $data['menu_order'] = $menu_order;            
        }*/
        return $data; 
    }

    public function on_post_published($post_id, $post, $update){
        
        if(defined("WP_ROCKET_VERSION") && function_exists("is_wp_rocket_crawling") && is_wp_rocket_crawling()) return;

        if (defined('REST_REQUEST') && REST_REQUEST) {
            error_log("❌ REST API isteği olduğu için işlem iptal edildi.");
            return;
        }

        $current_hook = current_filter();
            if (!in_array($current_hook, ['save_post'])) {
                error_log("❌ Hook $current_hook tarafından çağrıldı. İşlem yapılmadı.");
                return;
            }

        remove_action('save_post', [ $this, 'on_post_published'], 100);
        remove_action('save_post_product', [ $this, 'on_post_published'], 100);
        remove_action('publish_post', [ $this, 'on_post_published'], 100);

        if (
            defined('DOING_AJAX') && DOING_AJAX &&
            (!isset($GLOBALS['salt_ai_doing_translate']) || !$GLOBALS['salt_ai_doing_translate'])
        ) {
            return;
        }

        if (
            defined('DOING_CRON') && DOING_CRON &&
            (!isset($GLOBALS['salt_ai_doing_translate']) || !$GLOBALS['salt_ai_doing_translate'])
        ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( get_post_status( $post_id ) !== 'publish' ) {
            return;
        }

        $post_types = get_post_types(['public' => true], 'names');
        if (in_array($post->post_type, $post_types)) {

            //error_log("ZT-1");
            //error_log(did_action('save_post')."  ".did_action('publish_post'));

            if (did_action('save_post') > 1 || did_action('publish_post') > 1) {
                //return;
            }


            if (self::$already_ran) {
                return; // Eğer zaten çalıştıysa, çık
            }

            self::$already_ran = true; // Flag'i ayarla
            
            // check & save has map block
            $has_map = false;
            if(post_has_block($post_id, "acf/map")){
                $has_map = true;
            }
            update_post_meta( $post_id, 'has_map', $has_map );

            // check & save has core block
            $has_core_block = false;
            if(post_has_core_block($post_id)){
                $has_core_block = true;
            }
            update_post_meta( $post_id, 'has_core_block', $has_core_block );
            
            acf_block_id_fields($post_id);

            error_log("P O S T  S A V I N G  H O O K....".$post_id);
            
            /*$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            foreach ($backtrace as $trace) {
                $file = isset($trace['file']) ? $trace['file'] : '[Dosya Yok]';
                $line = isset($trace['line']) ? $trace['line'] : '[Satır Yok]';
                $function = isset($trace['function']) ? $trace['function'] : '[Fonksiyon Yok]';
                
                error_log("🔹 $function çağırdı --> Dosya: $file - Satır: $line");
            }*/

            $extractor = PageAssetsExtractor::get_instance();//$this->extractor;//new PageAssetsExtractor();
            $extractor->on_save_post($post_id, $post, $update);

            // post'un featured image'ının alt text'i eklenmemişse post'un title'ını alt text olarak kaydet.
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if (!$thumbnail_id) {
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if (empty($alt_text)) {
                    $post_title = get_the_title($post_id);
                    update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $post_title);
                }
            }
            
            self::$already_ran = false; // İşlem tamamlandı, flag'i sıfırla
            
        }
        add_action('save_post', [ $this, 'on_post_published'], 100, 3);
        add_action('save_post_product', [ $this, 'on_post_published'], 100, 3);
        add_action('publish_post', [ $this, 'on_post_published'], 100, 3); 
    }

    public function on_term_published($term_id, $tt_id, $taxonomy){
        $taxonomy_object = get_taxonomy($taxonomy);
        if ($taxonomy_object && $taxonomy_object->public) {
            $extractor = PageAssetsExtractor::get_instance();//$this->extractor;//new PageAssetsExtractor();
            $extractor->on_save_term($term_id, $tt_id, $taxonomy);
        }
    }

    public function on_post_delete( $post_id ){
        $post = get_post($post_id);
        if ( !isset($post->post_type) ) return;
        if(ENABLE_NOTIFICATIONS && $GLOBALS["notification_post_types"]){
            if(in_array($post->post_type, $GLOBALS["notification_post_types"])){
                Notifications::delete_post_notifications($post_id);
            }
        }
        if(ENABLE_CHAT){
            yobro_remove_conversation_by_post($post_id);            
        }
    }

    public function on_user_delete($user_id){
        if(ENABLE_NOTIFICATIONS){
            Notifications::delete_user_notifications($user_id);            
        }
        if(ENABLE_CHAT){
           yobro_remove_conversation_by_user($user_id); 
        }
    }


    /*function start_session() {
        if(!session_id()) {
            session_start();
        }
    }*/

    public function is_ip_changed() {
        /*if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }*/
        if (session_status() === PHP_SESSION_NONE){
            session_start();
        }
        $current_ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SESSION['user_ip'])) {
            $stored_ip = $_SESSION['user_ip'];
            if ($current_ip === $stored_ip) {
                return false;
            } else {
                $_SESSION['user_ip_'] = $current_ip;
                return true;
            }
        } else {
            $_SESSION['user_ip'] = $current_ip;
            return true;
        }
    }

    static function newsletter($action="", $email=""){
        global $wpdb;
        if(!isset($email)){
           $email = $this->user->user_email;
        }
        switch($action){
            case "unsubscribe" :
                 $wpdb->update('{$wpdb->prefix}newsletter', array('status'=>'U'), array('email'=>$email));
                 break;

            case "subscribe" :
                $error = false;
                //$user = get_user_by( "ID", $user_id );
                //$count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}newsletter WHERE email = %s", $email ) );    
                if( $this->newsletter("exist", $email) ) {
                    $error = true;
                } elseif( !defined( 'NEWSLETTER_VERSION' ) ) {
                    $error = true;
                } else {
                    $token =  wp_generate_password( rand( 10, 50 ), false );
                    $wpdb->insert( $wpdb->prefix . 'newsletter', array(
                                'email'         => $this->user->user_email,
                                //'sex'         => $fields['nx'],
                                'name'          => $this->user->first_name,
                                'surname'       => $this->user->last_name,
                                'status'        => "C",
                                //'list_1'        => $fields['list_1'],
                                //'http_referer'  => $fields['nhr'],
                                'token'         => $token,
                                'wp_user_id'    => $this->user->ID
                    ));
                    $opts = get_option('newsletter');
                    $opt_in = (int) $opts['noconfirmation'];
                    if ($opt_in == 0) {
                        $newsletter = Newsletter::instance();
                        $user = NewsletterUsers::instance()->get_user( $wpdb->insert_id );
                        NewsletterSubscription::instance()->mail($user->email, $newsletter->replace($opts['confirmation_subject'], $user), $newsletter->replace($opts['confirmation_message'], $user));
                    }
                    if ($opt_in == 1) {
                        $newsletter = Newsletter::instance();
                        $user = NewsletterUsers::instance()->get_user( $wpdb->insert_id );
                        NewsletterSubscription::instance()->mail($user->email, $newsletter->replace($opts['confirmed_subject'], $user), $newsletter->replace($opts['confirmed_message'], $user));
                    }
                }
                break;

            case "exist" :
                 return $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}newsletter WHERE email = %s", $email ) );
                 break;

            case "status" :
                $status = $wpdb->get_var( $wpdb->prepare("SELECT status FROM {$wpdb->prefix}newsletter WHERE email = %s", $email ) );
                return $status=="C"?true:false;
                break;

        }
    }

    public function update_user($user_id = 0){
        if($user_id > 0){
            $this->user = Timber::get_user($user_id);
        }
    }

    public function login($vars=array(), $callback="", $role=""){
        $response = $this->response();
        $info = array();
        $info['user_login'] = $vars['username'];
        $info['user_password'] = $vars['password'];
        $info['remember'] = true;

        if(isset($vars["role"])){
            $role = $vars["role"];
        }

        if(isset($role) && !empty($role)){
            $user_data = get_user_by( 'email', $info['user_login'] );
            if($user_data){
               if(!in_array($role, $user_data->roles)){
                  $response["error"] = true;
                  $response["message"] = 'Please use your '.$role.' account.';
               }
            }           
        }

        if(!$response["error"]){
            $user_signon = wp_signon( $info, false );
            if ( is_wp_error($user_signon) ){
                //print_r($user_signon);
                $response["error"] = true;
                $response["message"] = 'Wrong username or password.';
            } else {
                $response["message"] = 'Login successful.';
                wp_set_current_user($user_signon->ID);
                wp_set_auth_cookie($user_signon->ID);
                $this->user = $user_signon;
            }           
        }

        if(!$response["error"]){
            if(isset($callback) && !empty($callback)){
                /*if($callback == "publish_brief"){
                    $context = Timber::context();
                    $context['user'] = $this->user;
                    $context['vars'] = $vars;
                    $context['newsletter'] = $this->newsletter("status", $this->user->user_email);
                    $response["resubmit"] = true;
                    $response["html"] = Timber::compile( 'partials/form-user.twig', $context );
                }*/
            }else{
                if(isset($vars['redirect_url'])){
                   $redirect = $vars['redirect_url'];
                }else{
                    if(isset($GLOBALS["base_urls"]["logged_url"])){
                        $redirect = $GLOBALS["base_urls"]["logged_url"];
                    }else{
                        $redirect = $GLOBALS["base_urls"]["profile"];
                    }
                }
                $response["redirect"] = $redirect;
            }
        }
        
        return $response;
    }
    public function on_user_login( $user_login, $user) {
        update_user_meta( $user->ID, 'last_login', time() );
        $this->generate_robots_txt($user);
    }
    public function on_login_redirect() {
        if (isset($_SESSION['referer_url'])) {
            $url = $_SESSION['referer_url'];
            session_write_close();
            session_destroy();
            wp_redirect($url);
        } else {
            wp_redirect(get_account_endpoint_url('dashboard'));
        }
    }
    public function on_user_logout($user_id=0){
        $this->update_online_users_status_logout($user_id);
    }
    public function logout_without_confirmation($action, $result){
        if(!$result && ($action == 'log-out')){ 
            wp_safe_redirect(getLogoutUrl()); 
            exit(); 
        }
    }

    public function generate_robots_txt($user){
        if ( user_can($user, 'manage_options') ) {
            $content = "User-agent: *\nDisallow:\n";
            if ( function_exists('wpseo_sitemap_url') ) {
                $content .= 'Sitemap: ' . wpseo_sitemap_url() . "\n";
            } else {
                $content .= 'Sitemap: ' . home_url('/sitemap.xml') . "\n";
            }
            $extra_sitemaps = ['llms.txt', 'ssms.txt'];
            foreach ($extra_sitemaps as $file) {
                if ( file_exists(ABSPATH . $file) ) {
                    $content .= 'Sitemap: ' . home_url('/' . $file) . "\n";
                }
            }
            file_put_contents(ABSPATH . 'robots.txt', $content);
            error_log("Tolga'cım Debug - robots.txt dosyası oluşturuldu.");
        }
    }
    

    // Lost Password
    public function password_recover($vars=array(), $callback=""){
        switch(PASSWORD_RECOVER_TYPE){
            case "renew" :
                $response = $this->password_renew($vars, $callback);
            break;
            case "reset" :
                $response = $this->password_reset($vars, $callback);
            break;
        }
        return $response;
    }
    public function password_renew($vars=array(), $callback=""){
        $response = $this->response();
        $error = false;
        $message = "";
        $user_login = $vars['user_login'];
        //check_ajax_referer( 'ajax-forgot-nonce', 'security' );
        
        if( empty( $user_login ) ) {
            $error = true;
            $message = 'Enter your e-mail address.';
        } else {
            if(is_email( $user_login )) {
                if( email_exists($user_login) ){
                    $get_by = 'email';
                }else{
                    $error = true;
                    $message = 'There is no user registered with that email address.';                  
                }
            }else{
                $error = true;
                $message = 'Invalid e-mail address.';               
            }
        }
        if(!$error) {
            $mail = $this->send_password_activation($user_login);
            if( $mail ){
                $message = 'Check your email address for your password reset link.';
            }else{
                $error = true;
                $message = 'System is unable to send you mail containg your password reset link.'; 
            } 
        }   
        $response["error"] = $error;
        $response["message"] = $message;
        echo json_encode($response);
    }
    public function password_reset($vars=array(), $callback=""){
        $response = $this->response();
        $error = false;
        $message = "";
        $user_login = $vars['user_login'];
        if( empty( $user_login ) ) {
            $error = true;
            $message = 'Enter an username or e-mail address.';
        } else {
            if(is_email( $user_login )) {
                if( email_exists($user_login) ){
                    $get_by = 'email';
                }else{
                    $error = true;
                    $message = 'There is no user registered with that email address.';                  
                }   
            }else if (validate_username( $user_login )) {
                if( username_exists($user_login) ) {
                    $get_by = 'login';
                }else{
                    $error = true;
                    $message = 'There is no user registered with that username.';                   
                }
            }else{
                $error = true;
                $message = 'Invalid username or e-mail address.';               
            }
        }
        if(!$error) {
            $random_password = wp_generate_password();
            $user = get_user_by( $get_by, $user_login );    
            $update_user = wp_update_user( array ( 'ID' => $user->ID, 'user_pass' => $random_password ) );
            if( $update_user ) {
                $from = 'info@saran-group.com';
                if(!(isset($from) && is_email($from))) {        
                    $sitename = strtolower( $_SERVER['SERVER_NAME'] );
                    if ( substr( $sitename, 0, 4 ) == 'www.' ) {
                        $sitename = substr( $sitename, 4 );                 
                    }
                    $from = 'admin@'.$sitename; 
                }
                $to = $user->user_email;
                $subject = 'Your new password';
                $sender = 'From: '.get_option('name').' <'.$from.'>' . "\r\n";
                $message = 'Your new password is: '.$random_password;
                $headers[] = 'MIME-Version: 1.0' . "\r\n";
                $headers[] = 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                $headers[] = "X-Mailer: PHP \r\n";
                $headers[] = $sender;
                $mail = wp_mail( $to, $subject, $message, $headers );
                if( $mail ){
                    $message = 'Check your email address for you new password.';
                }else{
                    $error = true;
                    $message = 'System is unable to send you mail containg your new password.'; 
                }                   
            } else {
                $error = true;
                $message = 'Oops! Something went wrong while updaing your account.';
            }
        } 
        $response["error"] = $error;
        $response["message"] = $message; 
        echo json_encode($response);
    }
    public function get_password_activation_link($email){
        $data = array('type' => 'password', 'email' => $email);
        $encrypt = new Encrypt();
        $code = $encrypt->encrypt($data);
        return add_query_arg( array( 'activation-password' => $code ), $GLOBALS["base_urls"]["account"]);
    }
    public function send_password_activation($email){
        $activation_link = $this->get_password_activation_link($email);
        $from_name = get_bloginfo( 'name' );
        $from_email = get_bloginfo( 'admin_email' );
        $headers = array();
        $headers[] = 'From: '.$from_name.' <'. $from_email.'>';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Reply-To: '.$from_name.' <'. $from_email.'>';
        $mail = wp_mail( $email, $from_name.' Password Reset', 'Password Reset link : ' . $activation_link, $headers );
        return $mail;
    }


    public function register($vars=array(), $callback="", $role="author" ){
        $response = $this->response();

        $user_name = vars_fix($vars, "email");
        $first_name = vars_fix($vars, "first_name");
        $last_name = vars_fix($vars, "last_name");
        $email = vars_fix($vars, "email");
        $password = vars_fix($vars, "password");
        $nice_name = strtolower(vars_fix($vars, "email"));
        $user_role = vars_fix($vars, "role");
        $role = !empty($user_role)?$user_role:$role;
        $user_data = array(
            'user_login' => $user_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $email,
            'user_pass' => $password,
            'user_nicename' => $nice_name,
            'display_name' => $first_name.' '.$last_name,
            'role' => $role
        );

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            if (isset($user_id->errors['empty_user_login'])) {
                $response["error"] = true;
                $response["message"] = $user_id->errors['empty_user_login'][0];
            } elseif (isset($user_id->errors['existing_user_login'])) {
                $response["error"] = true;
                $response["message"] = $user_id->errors['existing_user_login'][0];
            }elseif (isset($user_id->errors['existing_user_email'])) {
                $response["error"] = true;
                $response["message"] = $user_id->errors['existing_user_email'][0];
            }else {
                $response["error"] = true;
                $response["message"] = 'Error occured, please fill up the sign up form carefully.';
            }
        }else{
            $this->user = new User($user_id);
            $response = $this->user_register_hook( $user_id );
        }
        if(!$response["error"] && isset($callback) && !empty($callback)){
            /*$this->create_person();
            $data["message"] = "";
            if($callback == "publish_brief"){
                $context = Timber::context();
                $context['user'] = $this->user;
                $context['vars'] = $vars;
                $data["html"] = Timber::compile( 'partials/form-user.twig', $context );
            }*/
        }
        return $response;
    }

    // User Activation by Email
    public function get_activation_link($user_id){
        global $wpdb; 
        $user = get_user_by( 'id', $user_id );
        $user_activation_key = md5(time());
        $data = array('type' => 'activation', 'id' => $user_id, 'code' => $user_activation_key);
        $encrypt = new Encrypt();
        $code = $encrypt->encrypt($data);
        $wpdb->update( 
            'wp_users',   
             array( 'user_activation_key' => $user_activation_key ),       
             array( 'ID' => $user_id )
        );
        return add_query_arg( array( 'activation-code' => $code ), $GLOBALS["base_urls"]["profile"]);
    }
    

    // User Activation by SMS
    public function verify_otp($vars=array()){
        $vars = array(
            "user_id"    => $this->user->ID,
            "otp_id"     => $vars["otp_id"],
            "otp_code"   => $vars["otp_code"],
        );
        $otp = new Sms($vars);
        $response = $otp->verify();
        if(isset($response["data"]["status"])){
            switch($response["data"]["status"] ){
                case "APPROVED" :
                    update_user_meta($this->user->ID, 'user_status', 1);
                    $role = $this->user->get_role();
                    $this->notification(
                        $role."/new-account",
                        array(
                            "user" => $this->user,
                            "recipient" => $this->user->ID
                        )
                    );
                break;
            }          
        }
        return $response;
    }
    public function otp_status($vars=array()){
        $vars = array(
            "user_id"    => $this->user->ID,
            "otp_id"     => $vars["otp_id"]
        );
        $otp = new Sms($vars);
        $response = $otp->otp_status();
        return $response;
    }
    public function resend_otp($vars=array()){
        $vars = array(
            "user_id"    => $this->user->ID,
            "otp_id"     => $vars["otp_id"]
        );
        $otp = new Sms($vars);
        $response = $otp->resend();

        if(isset($response["data"]["status"])){
            if($response["data"]["status"] == "EXPIRED"){
                $vars = array(
                    "user_id"   => $this->user->ID,
                    "recipient" => $this->user->get_phone(),
                    "content"   => "Your otp code is {}"
                );
                $otp = new Sms($vars);
                $response = $otp->generate();
                $response["refresh"] = true;
            }
        }
        return $response;
    }


    // New email activation
    static function get_email_activation_link($user_id){
        $email = get_user_meta($user_id, '_email_temp', true);
        $data = array('type' => 'email', 'id' => $user_id , 'email' => $email);
        $encrypt = new Encrypt();
        $code = $encrypt->encrypt($data);
        return add_query_arg( array( 'activation-email' => $code ), get_account_endpoint_url( 'profile' ));
    }
    static function send_email_activation($user_id){
        $email = get_user_meta($user_id, '_email_temp', true);
        $activation_link = Salt::get_email_activation_link($user_id);
        $from_name = get_bloginfo( 'name' );
        $from_email = get_bloginfo( 'admin_email' );
        $headers = array();
        $headers[] = 'From: '.$from_name.' <'. $from_email.'>';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Reply-To: '.$from_name.' <'. $from_email.'>';
        $mail = wp_mail( $email, $from_name.' Email Activation', 'Email Activation link : ' . $activation_link, $headers );
        return $mail;
    }
    static function reset_email_activation($user_id){
        delete_user_meta($user_id, "_email_temp");
        $email = get_user_meta($user_id, 'user_email', true);
        return array(
            "message" => "Email activation has been reset. Your current email is $email again.",
            "refresh" => true
        );
    }
    

    // send activation
    public function send_activation($user_id=0){
        $response = $this->response();

        if(ENABLE_MEMBERSHIP_ACTIVATION){

            $user = $this->user;

            $activation_type = MEMBERSHIP_ACTIVATION_TYPE;
            $user_activation_type = get_user_meta($user_id, "activation_type", true);
            if(!empty($user_activation_type)){
               $activation_type = $user_activation_type;
            }

            switch($activation_type){

                case "email" :
                    $activation_link = $this->get_activation_link($user_id);//add_query_arg( array( 'activation-code' => base64_encode(serialize($string)) ), get_account_endpoint_url( 'profile' ));
                    $from_name = get_bloginfo( 'name' );
                    $from_email = get_bloginfo( 'admin_email' );
                    $headers = array();
                    $headers[] = 'From: '.$from_name.' <'. $from_email.'>';
                    $headers[] = 'Content-Type: text/html; charset=UTF-8';
                    $headers[] = 'Reply-To: '.$from_name.' <'. $from_email.'>';
                    $mail = wp_mail( $user->user_email, $from_name.' Activation', 'Activation link : ' . $activation_link, $headers );
                    if(!$mail){
                        $response["error"] = true;
                        $response["message"] = "Activation link could not be sent.";
                    }else{
                        update_user_meta($user_id, "activation_type", "email");
                        $response["refresh"] = true;
                    }
                    return $response; 
                break;

                case "sms" :
                    $vars = array(
                      "user_id"   => $user_id,
                      "recipient" => $user->get_phone(),
                      "content"   => "Your otp code is {}"
                    );
                    $otp = new Sms($vars);
                    $response = $otp->generate();

                    if($response["error"]){
                        update_user_meta($user_id, "activation_type", "email");
                        return $this->send_activation($user_id);
                    }else{
                        update_user_meta($user_id, "activation_type", "sms");
                        return $response;
                    }
                break;

                default :
                   return $response;
                break;
            }
        }else{
            return $response;
        }
    }

    // change activation method
    public function change_activation_method($vars=array()){
        $method = $vars["activation_method"];
        $user_id = $vars["user_id"];
        switch($method){

            case "email" :
                update_user_meta($user_id, "activation_type", "email");
                $response = $this->send_activation($user_id);
                $response["refresh"] = true;
            break;

            case "sms" :
               update_user_meta($user_id, "activation_type", "sms");
               $response["refresh"] = true;
            break;

        }
        return $response;
    }


    // Form validations
    public function validate_phone($phone="", $country="", $phone_code=""){
        $error = false;
        $message = "";
        $response = $this->response();
        if(empty($phone) || empty($country)){
            $error = true;
            $message = "Phone number or country values is invalid.";
        }
        if(!empty($phone_code)){
            $phone = $phone_code.$phone;
        }
        if(strlen($phone) < 5){
            $error = true;
            $message = "Phone number is too short";
        }
        if(!$error){
            $url = PHONE_VALIDATOR_KEYS["url"];
            $url = str_replace("{phone}", $phone, $url);
            $url = str_replace("{country}", $country, $url);
            $args = array(
                'headers' => array(
                    'X-RapidAPI-Key' => PHONE_VALIDATOR_KEYS["X-RapidAPI-Key"],
                    'X-RapidAPI-Host' => PHONE_VALIDATOR_KEYS["X-RapidAPI-Host"],
                ),
            );
            $result = wp_remote_get( $url, $args );
            if ( is_wp_error( $result ) ) {
                $response["error"] = true;
                $response["message"] = $result;
                return $response;
            }
            $body = wp_remote_retrieve_body( $result );
            $data = json_decode( $body, true);
            if(isset($data["isValidNumber"])){
                if(!$data["isValidNumber"]){
                    $error = true;
                    $message = "Phone number is not valid."; 
                }else{
                    /*$data["carrier"]
                    $data["numberType"] : "MOBILE", "FIXED_LINE"*/                    
                }
            }else{
               $error = true;
               $message = "Phone number is not valid."; 
               if(isset($data["isPossibleNumberWithReason"])){
                   switch($data["isPossibleNumberWithReason"]){
                      case "TOO_SHORT" :
                          $message = "Phone number is too short."; 
                      break;
                      case "INVALID_LENGTH" :
                          $message = "Phone number is not valid."; 
                      break;
                   }
               }else{
                   $error = true;
                   $message = "Phone number is not valid."; 
               }
            }            
        }
        $response["error"] = $error;
        $response["message"] = $message;
        return $response;
    }
    static function user_exist($vars=array(), $callback=""){
        $email = $vars["email"];
        if(isset($vars["exclude"])){
           if($vars["exclude"] == $email){
              return false;
           }
        }
        $exists = email_exists($email);
        if ( $exists ){
          return "That E-mail is already registered.";
        }else{
          return false;//"That E-mail doesn't belong to any registered users on this site";
        }
    }
    static function nickname_exist($vars=array(), $callback=""){
        global $wpdb;
        $nickname = $vars["nickname"];
        if(isset($vars["exclude"])){
           if($vars["exclude"] == $nickname){
              return false;
           }
        }
        if(isset($vars["user_id"])){
            $user_id = $vars["user_id"];
        }else{
            $user_id = get_current_user_id();
        }
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->users as users, $wpdb->usermeta as meta WHERE users.ID = meta.user_id AND meta.meta_key = 'nickname' AND meta.meta_value = %s AND users.ID <> %d", $nickname, $user_id ) );
        if ( $exists ){
          return "That user name is already registered.";
        }else{
          return false;//"That E-mail doesn't belong to any registered users on this site";
        }
    }


    static function user_is_online($user_id) {
          $logged_in_users = get_transient('users_online');
          // online, if (s)he is in the list and last activity was less than 15 minutes ago
          return isset($logged_in_users[$user_id]) && ($logged_in_users[$user_id] > (current_time('timestamp') - (15 * 60)));
    }
    public function update_online_users_status(){
        if(is_user_logged_in()){

            // get the online users list
            if(($logged_in_users = get_transient('users_online')) === false) $logged_in_users = array();

            $current_user = wp_get_current_user();
            $current_user = $current_user->ID;  
            $current_time = current_time('timestamp');

            if(!isset($logged_in_users[$current_user]) || ($logged_in_users[$current_user] < ($current_time - (15 * 60)))){
                $logged_in_users[$current_user] = $current_time;
                set_transient('users_online', $logged_in_users, 30 * 60);
            }
        }
    }
    public function update_online_users_status_logout($user_id=0){
        if($user_id > 0){
            update_user_meta( $user_id, 'last_logout', time() );
            if(($logged_in_users = get_transient('users_online')) === false) $logged_in_users = array();
            unset($logged_in_users[$user_id]);
            set_transient('users_online', $logged_in_users, 30 * 60);
        }else{
            if (!is_user_logged_in()){
                //wp_safe_redirect(get_page_url('my-account'));
                wp_safe_redirect(get_permalink(get_option('options_myaccount_page_id')));
            }
        }
    }

    public function redirect_to_profile(){
        if (is_user_logged_in() && get_current_endpoint() == "my-account" && $this->user->get_status() ) {
            wp_safe_redirect(get_account_endpoint_url('profile'));
            exit();
        }
    }

    public function user_not_activated() {
        if(is_user_logged_in()){
            //$endpoint = WC()->query->get_current_endpoint();
            $endpoint = get_current_endpoint();
            if (!$this->user->get_status() && $endpoint != "not-activated") {
                wp_safe_redirect(get_account_endpoint_url('not-activated'));
                exit();
            }
            if ($this->user->get_status() && $endpoint == "not-activated") {
                wp_safe_redirect(get_account_endpoint_url('profile'));
                exit();
            }            
        }
    }

    public function user_profile_not_completed() {
        if(is_user_logged_in()){
            //$endpoint = WC()->query->get_current_endpoint();
            $endpoint = get_current_endpoint("my-account");
            $endpoint = empty($endpoint)?get_query_var("pagename"):$endpoint;
            $endpoint_not_allowed = array("sessions", "messages", "financials", "reviews");
            if($this->user->get_role() == "expert"){
                unset($endpoint_not_allowed["sessions"]);
            }
            if ($this->user->get_status() && !$this->user->profile_completed && in_array($endpoint, $endpoint_not_allowed)) {
                wp_safe_redirect(get_account_endpoint_url('not-completed'));
                exit();
            }
            if ($this->user->get_status() && $this->user->profile_completed && $endpoint == "not-completed") {
               wp_safe_redirect(get_account_endpoint_url('profile'));
               exit();
            } 
        }
    }

    public function notification($event="", $data=array()){
        if(ENABLE_NOTIFICATIONS){
            $user = $this->user;
            $notifications = new Notifications($user, false);
            //$notifications->load($event);
            $notifications->on($event, $data);//administrator/new-account
            if($notifications->debug){
                print_r($notifications->debug_output);
            }
        }
    }
    public function notification_count(){
        if(ENABLE_NOTIFICATIONS){
            $user = $this->user;
            $data = ["get_count" => true, "seen" => 0];
            $notifications = new Notifications($user);
            $result = $notifications->get_notifications($data);
            if(isset($result["data"]["total"])){
                return $result["data"]["total"];
            }
            return 0;
        }else{
            return 0;
        }
    }


    public function favorites($vars=array()){
        $response = $this->response();
        $favorites = new Favorites();
        if(isset($vars["action"])){
            switch($vars["action"]){
                case "get" :
                    $posts_per_page = isset($vars["posts_per_page"])?intval($vars["posts_per_page"]):0;
                    $page = isset($vars["page"])?intval($vars["page"]):1;
                    
                    if($favorites->count() > 0){
                        $args = array();
                        $args["include"] = $favorites->favorites;

                        $paginate = new Paginate($args, $vars);
                        $result = $paginate->get_results();
                        $response["posts"] = $result["posts"];
                        $response["data"] = $result["data"];
                    }else{
                        $response["posts"] = array();
                        $response["data"] = array(
                            "total" => 0,
                            "page" => $page,
                            "page_total" => 1
                        );
                    }
                break;

            }
        }else{
            $response["error"] = true;
            $response["message"] = "Action is not set";
        }
        return $response;
    }
    public function get_favorites_count(){
        $favorites = new Favorites();
        $count = $favorites->count();
        if (empty($count)) $count = 0;
        return $count;
    }






    /*
    RewriteCond %{REQUEST_METHOD} POST
    RewriteCond %{REQUEST_URI} ^/zitango/wp-admin/
    RewriteCond %{QUERY_STRING} action=up_asset_upload
    RewriteRule (.*) /zitango/index.php?ajax=query&method=message_upload [L,R=307]
    */
    public function send_message_upload($uploaded_file){
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['basedir'];
            $image_data = file_get_contents( $uploaded_file["tmp_name"]);
            $filename = basename($uploaded_file['name']);

            $file_type = wp_check_filetype($filename, null );

            $base_name = unique_code(12);

            $filename = $base_name.".".$file_type["ext"];
            $filename_thumb = $base_name."-thumb.".$file_type["ext"];

            if(wp_mkdir_p($upload_dir['path']))
                $file = $upload_dir['path'] . '/' . $filename;
            else
                $file = $upload_dir['basedir'] . '/' . $filename;
            file_put_contents($file, $image_data);

            $image_temp_url = $upload_dir['url'].'/'. $filename;
            $image_temp_url = str_replace("wp-content/uploads", "assets", $image_temp_url);

            $response = array(
                "url" => $image_temp_url
            );
            if(strpos($file_type["type"], "image")>-1){
                $image = wp_get_image_editor(  $upload_dir['basedir'] . '/' .$filename);
                if ( ! is_wp_error( $image ) ) {
                    $image->resize( 200, 200, true );
                    $image->save( $upload_dir['basedir'] . '/' . $filename_thumb );
                }
                $response["thumbnail_url"] = str_replace("wp-content/uploads", "assets", $upload_dir['url'].'/'.$filename_thumb);
            }
            return $response;
    }





    public function is_following($id=0, $type="user"){
        global $wpdb;
        return boolval($wpdb->get_var("SELECT meta_value FROM wp_usermeta WHERE user_id=".$this->user->ID." and meta_key='following_".$type."' and meta_value=".$id));
    }
    public function is_follows($id=0, $type="user"){
        global $wpdb;
        return boolval($wpdb->get_var("SELECT meta_value FROM wp_usermeta WHERE user_id=".$id." and meta_key='following_".$type."' and meta_value=".$this->user->ID));
    }
    public function get_followers_count($id=0, $type="user"){
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM wp_usermeta WHERE meta_key='following_".$type."' and meta_value=".$id);
    }
    public function get_followers($id=0, $type="user"){
        global $wpdb;
        $users = $wpdb->get_results("SELECT user_id FROM wp_usermeta WHERE meta_key='following_".$type."' and meta_value=".$id);
        return wp_list_pluck($users, "user_id");
    }
    public function follow($id=0, $type="user"){
        $response = $this->response();
        $meta_id = 0;
        if(is_user_logged_in() || $this->user->ID > 0){
            if(!$this->is_following($id, $type)){
               $meta_id = add_user_meta( $this->user->ID, "following_".$type, $id);
               $response["html"] = trans("Unfollow");
                $this->notification(
                    $this->user->get_role()."/new-follower",
                    array(
                        "post" => array(),
                        "user" => $this->user,
                        "recipient" => $id,
                    )
                );
            }else{
               $meta_id = delete_user_meta( $this->user->ID, "following_".$type, $id);
               $response["html"] = trans("Follow");
               $notifications = new Notifications($this->user, false);
               $notifications->delete_user_event_notification("new-follower", $id);
            }
        }
        $response["data"] = $meta_id;
        return $response;
    }
    /*public function unfollow($id=0, $type="user"){
        $response = $this->response();
        $meta_id = 0;
        if((is_user_logged_in() || $this->user->ID > 0) && !$this->is_following($id, $type)){
            $meta_id = delete_user_meta( $this->user->ID, "following_".$type, $id);
        }
        $response["data"] = $meta_id;
        $response["html"] = trans("Follow");
        return $response;
    }*/







    public function comment_product($vars=array()){
        $error = false;
        $message = "";
        
        $update = false;
        if(isset($vars["comment_id"])){
            if(!empty($vars["comment_id"])){
                $update = true;
            }
        }

        $rating = isset($vars["rating"])?$vars["rating"]:0;//get_star_vote($vars["product_id"], $this->user->ID);
        if(!$rating){
           $rating = 0;
        }
        $time = current_time('mysql');

        $admin_review = boolval(isset($vars["admin"])?$vars["admin"]:0);

        if($admin_review){
            unset($vars["admin"]);
            remove_action('transition_comment_status', 'comment_on_approved', 10);
        }

        if(DISABLE_REVIEW_APPROVE){
            $vars["comment_approved"] = 1;
        }

        $user_id = isset($vars["user_id"])?$vars["user_id"]:$this->user->ID;
        $first_name = isset($vars["first_name"])?$vars["first_name"]:$this->user->first_name;
        $last_name  = isset($vars["last_name"])?$vars["last_name"]:$this->user->last_name;
        $user_email  = isset($vars["user_email"])?$vars["user_email"]:$this->user->user_email;
        $comment_approved = isset($vars["comment_approved"])?$vars["comment_approved"]:0;
        
        $user_ip = "";
        $user_agent = "";
        $session_tokens = $this->user->session_tokens;
        $session_token_index = count($session_tokens)-1;
        $session_token_key = array_keys($session_tokens)[$session_token_index];
        foreach($session_tokens as $key => $session_token){
            if($key == $session_token_key ){
                $user_ip = $session_token["ip"];
                $user_agent = $session_token["ua"];             
            }
        }


        if($update){
            $comment_id = $vars["comment_id"];
            $data = array(
               'comment_ID' => $comment_id,
               'comment_content' => $vars["comment"],
               //'comment_author_IP' => $user_ip,
               //'comment_agent' => $user_agent,
               //'comment_date' => $time,
               //'comment_author' => ucfirst($first_name).' '.ucfirst($last_name[0]).'.',
               'comment_approved' => $comment_approved,
            );
            wp_update_comment( $data );
        }else{
            $data = array(
               'comment_post_ID' => $vars["product_id"],
               'comment_author' => ucfirst($first_name).' '.ucfirst($last_name[0]).'.',
               'comment_author_email' => $user_email,
               'comment_author_url' => '',
               'comment_content' => $vars["comment"],
               'comment_type' => 'review',
               'comment_parent' => 0,
               'user_id' => $user_id,
               'comment_author_IP' => $user_ip,
               'comment_agent' => $user_agent,
               'comment_date' => $time,
               'comment_approved' => $comment_approved,
            );
            $comment_id = wp_insert_comment($data);
        }

        if(empty($comment_id)){
            $error = true;
            $message = "Your message is not saved. Please try again later.";
        }else{
            //wp_update_comment(array("comment_ID" => $comment_id, "comment_approved" => 1));
            $comment_title = isset($vars["comment_session_title"])?$vars["comment_session_title"]:"";
            /*if(!empty($comment_title)){
                $comment_title = explode(" ", $comment_title);
                if($comment_title){
                   $comment_title_code = $comment_title[0];
                   $comment_title = implode (" ", $comment_title);  
                   $comment_title = trim(str_replace($comment_title_code, "", $comment_title));             
                }
            }*/
            if($update){
                update_comment_meta( $comment_id, 'rating', $rating);
                if($admin_review){
                    if($comment_approved || DISABLE_REVIEW_APPROVE){
                        $message = "<h3 class='font-weight-bold text-success'>Review has been updated!</h3>";
                    }else{
                        $message = "<h3 class='font-weight-bold text-success'>Review has been updated!</h3>Review will publish after your approval.";
                    }   
                }else{
                    if($comment_approved || DISABLE_REVIEW_APPROVE){
                        $message = "<h3 class='font-weight-bold text-success'>Your comment updated!</h3>";
                    }else{
                        $message = "<h3 class='font-weight-bold text-success'>Your comment updated!</h3>We will publish your comment after the approval.<br>Don't worry, also we'll notify you after the approval.";
                    }                   
                }

                if($comment_approved || DISABLE_REVIEW_APPROVE){
                    $comment_to = new User($vars["comment_profile"]);
                    $comment_from = new User($user_id);
                    $application = new Application($vars["product_id"]);
                    $session = $application->parent();
                    if($comment_to->get_role() == "expert"){
                        $this->notification(
                            "expert/review-approved",
                            array(
                                "user" => $comment_from,
                                "recipient" => $comment_to->ID,
                                "post" => $session
                            )
                        );
                    }else{
                        $this->notification(
                            "client/review-approved",
                            array(
                                "user" => $comment_from,
                                "recipient" => $comment_to->ID,
                                "post" => $session
                            )
                        );
                    }                    
                }

            }else{
                add_comment_meta( $comment_id, 'rating', $rating, true); 
                add_comment_meta( $comment_id, 'comment_title', $comment_title, true);
                add_comment_meta( $comment_id, 'comment_profile', $vars["comment_profile"], true);
                if($admin_review){
                    if($comment_approved || DISABLE_REVIEW_APPROVE){
                        $message = "<h3 class='font-weight-bold text-success'>Review has been added!</h3>";
                    }else{
                        $message = "<h3 class='font-weight-bold text-success'>Review has been added!</h3>Review will publish after your approval.";
                    }
                }else{
                    if($comment_approved || DISABLE_REVIEW_APPROVE){
                        $message = "<h3 class='font-weight-bold text-success'>Thanks for your comment!</h3>";
                    }else{
                        $message = "<h3 class='font-weight-bold text-success'>Thanks for your comment!</h3>We will publish your comment after the approval. <br>Don't worry, also we'll notify you after the approval.";
                    }
                }

                
                $rating_stars = "";
                $rating_stars_emoji = "";
                switch($rating){
                    case "1" :
                       $rating_stars = "★☆☆☆☆";
                       $rating_stars_emoji = "⭐";
                    break;
                    case "2" :
                       $rating_stars = "★★☆☆☆";
                       $rating_stars_emoji = "⭐⭐";
                    break;
                    case "3" :
                       $rating_stars = "★★★☆☆";
                       $rating_stars_emoji = "⭐⭐⭐";
                    break;
                    case "4" :
                       $rating_stars = "★★★★☆";
                       $rating_stars_emoji = "⭐⭐⭐⭐";
                    break;
                    case "5" :
                       $rating_stars = "★★★★★";
                       $rating_stars_emoji = "⭐⭐⭐⭐⭐";
                    break;
                }
                $comment_to = new User($vars["comment_profile"]);
                $comment_from = new User($user_id);
                $application = new Application($vars["product_id"]);
                $session = $application->parent();
                $session->rating = $rating_stars;
                //$comment = new TimberComment($comment_id);
                if($comment_to->get_role() == "expert"){
                    $this->notification(
                        "expert/new-review",
                        array(
                            "user" => $comment_from,
                            "recipient" => $comment_to->ID,
                            "post" => $session
                        )
                    );
                }else{
                    $this->notification(
                        "client/new-review",
                        array(
                            "user" => $comment_from,
                            "recipient" => $comment_to->ID,
                            "post" => $session
                        )
                    );
                }
                        
            }

            $user_item = new User($vars["comment_profile"]);
            if($comment_approved || DISABLE_REVIEW_APPROVE){
                $rating = $user_item->get_rating();
                update_user_meta($user_item->ID, '_user_rating', $rating->point);
            }

            iF($admin_review){
                add_action('transition_comment_status', 'comment_on_approved', 10, 3);
            }

        }
        $data = array(
                        "error"   => $error,
                        "message" => $message,
                        "data"    => $comment_id,
                        "resubmit" => false,
                        "redirect" => "",
                        "refresh" => $admin_review,
                        "html"    => ""
        );
        return json_encode($data);
    }
    public function comment_product_detail($vars=array()){
        $error = false;
        $message = "";
        if(isset($vars["id"])){
            $comment = new Timber\Comment(intval($vars["id"]));
        }else{
            $error = true;
            $message = "Comment ID is not found.";
            $comment = array();
        }
        $context = Timber::context();
        $context["comment"] = $comment;
        $data = array(
                        "error"   => $error,
                        "message" => $message,
                        "data"    => '',
                        "resubmit" => false,
                        "redirect" => "",
                        "html"    => Timber::compile( 'users/comment-modal.twig', $context )
        );
        return json_encode($data);
    }
    static function get_comment_product($product_id, $approved=1){
        global $wpdb;
        return $wpdb->get_results("SELECT wpc.comment_ID, wpc.comment_approved, wpc.comment_author,wpc.comment_author_email,wpc.comment_date,wpc.comment_content,wpcm.meta_value AS rating FROM " . $wpdb->prefix . "comments AS wpc INNER JOIN " . $wpdb->prefix . "commentmeta AS wpcm ON wpcm.comment_id = wpc.comment_id " . ($approved?" AND wpc.comment_approved = ".$approved : "") ." AND wpc.comment_type = 'review' AND wpcm.meta_key = 'rating' WHERE wpc.comment_post_id = " . $product_id);
    }
    public function on_insert_comment($comment_id, $comment_object) {
        // Yorumun yazarı olan kullanıcı ID'sini al
        $user_id = $comment_object->user_id;
        
        // Kullanıcı ID'si varsa, kullanıcı rollerini al
        if ($user_id) {
            $user = get_userdata($user_id);
            $roles = $user->roles; // Kullanıcının rollerini al
            $role = !empty($roles) ? array_shift($roles) : ''; // İlk rolü al
            
            // Yorumun meta verilerine user_role ekle
            add_comment_meta($comment_id, 'user_role', $role);
        }
    }




    /*// events
    public function after_store_new_message($message){
        //print_r($message);
        $attributes = $message["attributes"];
        //print_r($attributes);
        $conversation = yobro_get_conversation_row($attributes["conv_id"]);

            $user = $GLOBALS["user"];
            $attributes = $message["attributes"];
            $conversation = yobro_get_conversation_row($attributes["conv_id"]);
            if($conversation->reciever == $user->ID){
                $other_id = $conversation->sender;
            }else{
                $other_id = $conversation->reciever;
            }
            global $wpdb;
            $wpdb->query("UPDATE wp_yobro_messages SET reciever_id=".$other_id." WHERE id=".$attributes['id']);

            $salt = new Salt();
            $reciever_is_online = $salt->user_is_online($other_id);
            
            if(!$reciever_is_online){
                $reciever = get_user_by( 'id', $other_id );
                $reciever = new User($other_id);
                $sender = get_user_by( 'id', $other_id);
                $sender = $user;
                
                $attrs = array(
                    "conv_id"  => $attributes["conv_id"],
                    "sender"   => $sender,
                    "user"     => $reciever,
                    "message"  => $attributes["message"]
                );
                $this->notification($reciever->get_role()."/new-message", $attrs);                
            }

        return $message;
    }*/

    // autologin after registration
    public function user_register_hook( $user_id ) {
        $response = $this->response();

        $register_type = "email";
        $querystring = json_decode(queryStringJSON(), true);
        if(isset($querystring["loginSocial"])){
            $register_type = $querystring["loginSocial"];
        }
        update_user_meta( $user_id, 'register_type', $register_by );

        $vars = isset($_POST['vars'])?$_POST['vars']:$_POST;

        $role = "default";
        if(isset($vars["role"])){
            $role = sanitize_text_field($vars['role']);
        }

        $user_id = wp_update_user( array( 'ID' => $user_id, 'role' => $role ) );
        $user_data = new WP_User($user_id);
        $user_data->set_role($role);

        $this->update_user($user_id);
        
        $password_set = false;
        if($role != "default"){
            $password_set = true;
            if(ENABLE_MEMBERSHIP_ACTIVATION || ENABLE_SMS_NOTIFICATIONS){
                if( MEMBERSHIP_ACTIVATION_TYPE == "sms" || ENABLE_SMS_NOTIFICATIONS ){
                    $vars["action"] = "save_sms_requirements";
                    $vars["refresh"] = true;
                    $response = $this->update_profile($vars);
                    $this->user = new User($user_id);
                    if(!$response["error"] && !ENABLE_MEMBERSHIP_ACTIVATION){
                        $this->notification(
                            $role."/new-account",
                            array(
                                "user"      => $this->user,
                                "recipient" => $this->user->ID
                            )
                        );
                    }
                }else{
                    $response = $this->send_activation($user_id);
                }
            }else{
                $this->notification(
                    $role."/new-account",
                    array(
                        "user"      => $this->user,
                        "recipient" => $this->user->ID
                    )
                );
            }            
        }

        update_user_meta( $user_id, 'password_set', $password_set );

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return $response;
        }else{
            wp_redirect( get_account_endpoint_url("profile") );
            exit();             
        }
    }
    public function user_after_update_hook($user_id, $old_user_data){
        if(!is_admin()){
            $this->update_user($user_id);
        }
    }
    public function user_before_update_hook($user_id) {
        if(!is_admin()){
            $this->update_user($user_id);
        }
    }
    public function user_after_meta_update_hook($meta_id, $user_id, $meta_key, $_meta_valu){
        if(!is_admin()){
            $this->update_user($user_id);
        }
    }

    

    public function remove_cart_content(){
        global $woocommerce;
        if ( $woocommerce->cart->get_cart_contents_count() > 0 ) {
            $items = $woocommerce->cart->get_cart();
            foreach($items as $item => $values) {
                $key = $values['key'];
                $woocommerce->cart->remove_cart_item($key);
            }
        }
    }
    public function add_to_cart($post_id){
        global $woocommerce;
        $woocommerce->cart->add_to_cart( $post_id );
    }


    public function get_menu($args){
        return Timber::get_menu($args);
    }



    public function get_markers($posts=array(), $popup = false){
        $data = array();
        foreach($posts as $post){
            $marker_data = get_field($type."_".$status, "istasyon_options");
            $marker = array();
            if($marker_data){
                $marker = array(
                    "icon"   => $marker_data["url"],
                    "width"  => $marker_data["width"],
                    "height" => $marker_data["height"],
                );                
            }
            $data[] = array(
                "id"       => $post->ID,
                "title"    => $post->title(),
                "marker"   => $marker,
                "lat"      => $post->lat,
                "lng"      => $post->lng,
                "country"  => $post->country_name,//("iso2", $post->country),
                "state"    => $post->state_name,//("woo", $post->state),
                "city"     => $post->city_name,//("id", $post->city),
                "distance" => $post->distance
            );
            if($popup){
               //$data["popup"] = esc_html($this->get_map_popup());
            } 
        }
        return $data;
    }



    static function duplicate_post($args = []) {

        $defaults = [
            'post_id'    => 0,
            'status'     => 'draft',
            'title'      => '',
            'content'    => '',
            'post_type'  => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $post_id   = $args['post_id'];
        $status    = $args['status'];
        $title     = $args['title'];
        $content   = $args['content'];
        $post_type = $args['post_type'];

        $oldpost = get_post($post_id);
        if (!$oldpost) {
            return 0; // Geçersiz post
        }

        if (empty($title)) {
            $title = get_the_title($post_id);
        }

        if (empty($content)) {
            $content = $oldpost->post_content;
        }

        if (empty($post_type)) {
            $post_type = $oldpost->post_type;
        }

        $post = [
            'post_title'   => $title,
            'post_name'    => sanitize_title($title),
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => $post_type,
        ];

        $new_post_id = wp_insert_post($post);

        // Meta kopyalama
        $data = get_post_custom($post_id);
        foreach ($data as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_post_id, $key, maybe_unserialize($value));
            }
        }

        // Taxonomy kopyalama
        $taxonomies = get_post_taxonomies($post_id);
        if ($taxonomies) {
            foreach ($taxonomies as $taxonomy) {
                wp_set_object_terms(
                    $new_post_id,
                    wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']),
                    $taxonomy
                );
            }
        }

        return $new_post_id;
    }


    public function log($functionName="", $description=""){
        if(class_exists("Logger")){
            $log = new Logger();
            $log->logAction($functionName, $description);
        }
    }

    public function delete_session_data() {
        if (isset($_SESSION['wp_notice'])) {
            unset($_SESSION['wp_notice']);
        }
        if (isset($_SESSION['error_message'])) {
            unset($_SESSION['error_message']);
        }
    }

    public static function get_integration($slug=""){
        $integrations = self::get_cached_option("integrations"); // Option page'den repeater'ı çek
        if ($integrations) {
            foreach ($integrations as $integration) {
                if ($integration['name'] === $name) {
                    return $integration['keys']; // Eğer eşleşirse keys alanını döndür
                }
            }
        }
        return []; // Eğer bulunamazsa null döndür
    }

    public function measureLCP($url, $deviceType = 'desktop') {
        $api = Salt::get_integration("google_pagespeed_insights");
        if($api){
            $apiKey = $api["key"];
            if(!empty($apiKey)){
                $endpoint = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$url&key=$apiKey&strategy=$deviceType";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
                if ($response) {
                    $data = json_decode($response, true);
                    $lcp = $data['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'];
                    return $lcp;
                }             
            }
        }
        return false; // Hata durumunda
    }
}




// prevent woo functions

if(!function_exists("is_shop")){
    function is_shop(){
        return false;
    }
}

if(!function_exists("is_product")){
    function is_product(){
        return false;
    }
}

if(!function_exists("is_product_category")){
    function is_product_category(){
        return false;
    }
}

if(!function_exists("is_product_tag")){
    function is_product_tag(){
        return false;
    }
}

if(!function_exists("is_account_page")){
    function is_account_page(){
        return false;
    }
}

if(!function_exists("is_checkout")){
    function is_checkout(){
        return false;
    }
}

if(!function_exists("is_cart")){
    function is_cart(){
        return false;
    }
}





/*
add_action("admin_init", function(){
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
   //error_log(print_r(get_untranslated_terms("tr"), true));
   //translate_post_ai(4021, 'tr');
   //translate_post_ai(4161, 'tr');
});
*/