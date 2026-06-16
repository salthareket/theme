<?php

use SaltHareket\Theme;

// class in twig example with "class_salt"
// {% set projects =  {"function": "ads", "action":"search", "work_type": data.work_type, "expertise": data.expertise, "user_id": user.id}|class_salt %}

class SaltBase{

    public $user;
    public $localization;
    public $search_history;
    //public $extractor;

    // Cache'i kontrol etmek için özel bir placeholder tanımla
    const CACHE_PLACEHOLDER = 'SALT_EMPTY_VALUE';
    const CACHE_LIFETIME = MONTH_IN_SECONDS; // Cache süresi 1 yıl yerine 1 ay

    private static $already_ran = false;

    private static $instance;
    
    public static function get_instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct($user=array()) { // public -> protected yaptık
        if (self::$already_ran) return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        // Kalp atışı (Heartbeat) isteklerini de durdurabilirsin
        if (isset($_POST['action']) && $_POST['action'] == 'heartbeat') return;

        /*error_log(sprintf(
            "🚀 new Salt() - Request URL: %s | UA: %s | Method: %s",
            $_SERVER['REQUEST_URI'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'No UA',
            $_SERVER['REQUEST_METHOD'] ?? 'N/A'
        ));*/
      
        
        // 1. ÜYELİK SİSTEMİ — MembershipManager'a delegate edildi.
        // Hook'lar MembershipHooks::register()'da yönetiliyor (apps/membership/bootstrap.php).
        // SaltBase burada sadece user nesnesini yükler.
        // @see SaltHareket\Membership\MembershipManager

        // 2. SADECE ADMIN PANELİNDE ÇALIŞANLAR
        
            remove_action('post_updated', 'wp_save_post_revision');
            
            // LCP DATA RESET: Erken hook ile reset (priority 5 - diğer işlemlerden önce)
            add_action('save_post', [ $this, 'early_reset_lcp_on_save'], 5, 3);
            add_action('save_post_product', [ $this, 'early_reset_lcp_on_save'], 5, 3);
            
            // Post kayıt olaylarını sadece admin panelinde dinle
            add_action('save_post', [ $this, 'on_post_published'], 100, 3);
            add_action('save_post_product', [ $this, 'on_post_published'], 100, 3);
            add_action('wp_insert_post_data', [$this, 'on_post_pre_update'], 10, 2 );
            add_action('created_term', [$this, 'on_term_published'], 10, 3);
            add_action('edited_term', [$this, 'on_term_published'], 10, 3);
            add_action('edit_user_profile_update', [ $this, 'user_before_update_hook'] );
        if (is_admin()) {
            // admin-only hooks buraya eklenebilir
        }

        // 3. KRİTİK VERİ NESNELERİ (SADECE GEREKTİĞİNDE)
        if (class_exists('User')) {
            if ($user) {
                $this->user = new User($user);
            } elseif (is_user_logged_in()) {
                $this->user = new User(wp_get_current_user());
            }
        } elseif (class_exists('Timber\Timber')) {
            if ($user) {
                $this->user = Timber\Timber::get_user($user);
            } elseif (is_user_logged_in()) {
                $this->user = Timber\Timber::get_user(wp_get_current_user());
            }
        }

        // Global olaylar
        add_action('before_delete_post', [ $this, 'on_post_delete'], 10, 1 );
        add_action('delete_user', [ $this, 'on_user_delete'], 10 );
        add_action('shutdown', [ $this, 'delete_session_data']);

        // Localization ve Search History
        if ((defined('ENABLE_IP2COUNTRY') && ENABLE_IP2COUNTRY) || (defined('ENABLE_LOCATION_DB') && ENABLE_LOCATION_DB)) {
            $this->localization = \SaltHareket\Localization\LocationManager::getInstance();
        }

        if (defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY) {
            if ( class_exists( 'SearchHistory' ) ) {
                $this->search_history = new SearchHistory();
            }
        }
        
        if ($user) {
            $timezone = $this->user->get_timezone();
            if($timezone){
                if(strpos($timezone, "/") > 0){
                    date_default_timezone_set($timezone);
                }
            }
        }

        self::$already_ran = true;
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

    public function on_post_pre_update($data){
        /*if($data["post_type"] == "page"){
           $menu_order = wp_count_posts("page")->publish;
           $menu_order = $menu_order>0?$menu_order-1:0;
           $data['menu_order'] = $menu_order;            
        }*/
        return $data; 
    }
    
    /**
     * ERKEN LCP RESET: Post save edildiğinde LCP verilerini hemen sıfırla
     * Priority 5 ile çalışır - diğer tüm işlemlerden önce
     * Basit ve hızlı - sadece reset yapar, başka bir şey yapmaz
     */
    public function early_reset_lcp_on_save($post_id, $post, $update) {
        // Temel kontroller
        if (!$post_id || empty($post_id)) return;
        if (wp_is_post_revision($post_id)) return;
        if (wp_is_post_autosave($post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        // Post type kontrolü
        $post_types = get_post_types(['public' => true], 'names');
        if (!in_array($post->post_type, $post_types)) return;
        
        // LCP reset - $update bağımsız çalışır
        $this->reset_lcp_data_on_save($post_id);
    }

    public function on_post_published($post_id, $post, $update){

        if (!$post_id || empty($post_id)) return;
        if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
        if ( get_post_status( $post_id ) !== 'publish' ) return;

        if (get_transient('salt_purge_lock_' . $post_id)) return;
        set_transient('salt_purge_lock_' . $post_id, true, 10);

        // WP Rocket preload botu — cache oluşturmaya geldi, ağır işleri yapma
        if (
            ( isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'WP Rocket/Preload') !== false ) ||
            isset($_SERVER['HTTP_X_ROCKET_PRELOAD'])
        ) {
            return;
        }

        if (function_exists('rocket_is_crawling') && rocket_is_crawling()) return;

        // Gutenberg meta box loader isteği
        if (isset($_GET['meta-box-loader']) || isset($_GET['meta-box-loader-nonce'])) return;

        if (isset($_GET['action']) && $_GET['action'] == 'as_async_request_queue_runner') return;

        if (defined('REST_REQUEST') && REST_REQUEST && $_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $current_hook = current_filter();
        if (!in_array($current_hook, ['save_post', 'save_post_product'])) return;

        if (
            defined('DOING_AJAX') && DOING_AJAX &&
            (!isset($GLOBALS['salt_ai_doing_translate']) || !$GLOBALS['salt_ai_doing_translate'])
        ) return;

        if (
            defined('DOING_CRON') && DOING_CRON &&
            (!isset($GLOBALS['salt_ai_doing_translate']) || !$GLOBALS['salt_ai_doing_translate'])
        ) return;

        remove_action('save_post', [ $this, 'on_post_published'], 100);
        remove_action('save_post_product', [ $this, 'on_post_published'], 100);
        remove_action('publish_post', [ $this, 'on_post_published'], 100);

        $post_types = get_post_types(['public' => true], 'names');
        if (in_array($post->post_type, $post_types)) {

            // check & save has map block
            $has_map = post_has_block($post_id, "acf/map") ? true : false;
            update_post_meta( $post_id, 'has_map', $has_map );

            // check & save has core block
            $has_core_block = post_has_core_block($post_id) ? true : false;
            update_post_meta( $post_id, 'has_core_block', $has_core_block );
            
            acf_block_id_fields($post_id);

            if (class_exists('PageAssetsExtractor')) {
                $extractor = \PageAssetsExtractor::get_instance();
                $extractor->on_save_post($post_id, $post, $update);
            }
            
            $this->reset_lcp_data_on_save($post_id);

            // Featured image alt text — boşsa post title'ı kullan
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if (empty($alt_text)) {
                    update_post_meta($thumbnail_id, '_wp_attachment_image_alt', get_the_title($post_id));
                }
            }
            
            if(function_exists("pll_copy_post_languages")){
                pll_copy_post_languages($post_id);                
            }
        }

        add_action('save_post', [ $this, 'on_post_published'], 100, 3);
        add_action('save_post_product', [ $this, 'on_post_published'], 100, 3);
        add_action('publish_post', [ $this, 'on_post_published'], 100, 3); 
    }

    public function on_term_published($term_id, $tt_id, $taxonomy){
        $taxonomy_object = get_taxonomy($taxonomy);
        if ($taxonomy_object && $taxonomy_object->public) {
            update_term_featured_image($term_id, $tt_id, $taxonomy);
            $extractor = \PageAssetsExtractor::get_instance();//$this->extractor;//new PageAssetsExtractor();
            $extractor->on_save_term($term_id, $tt_id, $taxonomy);
            
            // LCP DATA RESET: Term save edildiğinde LCP verilerini sıfırla
            $this->reset_lcp_data_on_term_save($term_id, $taxonomy);
        }
    }
    
    /**
     * LCP verilerini sıfırla (post save edildiğinde)
     * İçerik değiştiğinde LCP elementi de değişmiş olabilir
     */
    private function reset_lcp_data_on_save($post_id) {
        // Assets meta verisini al
        $assets = get_post_meta($post_id, 'assets', true);
        
        if (!is_array($assets)) {
            return; // Assets yoksa zaten LCP yok
        }
        
        // LCP verisi var mı kontrol et
        if (empty($assets['lcp']['desktop']) && empty($assets['lcp']['mobile'])) {
            return; // LCP verisi yoksa reset'e gerek yok
        }
        
        // LCP verilerini sıfırla
        $assets['lcp'] = [];
        
        // Meta'yı güncelle
        update_post_meta($post_id, 'assets', $assets);
        error_log("✅ LCP data reset for post #{$post_id}");
    }
    
    /**
     * LCP verilerini sıfırla (term save edildiğinde)
     */
    private function reset_lcp_data_on_term_save($term_id, $taxonomy) {
        // Assets meta verisini al
        $assets = get_term_meta($term_id, 'assets', true);
        
        if (!is_array($assets)) {
            return; // Assets yoksa zaten LCP yok
        }
        
        // LCP verisi var mı kontrol et
        if (empty($assets['lcp']['desktop']) && empty($assets['lcp']['mobile'])) {
            return; // LCP verisi yoksa reset'e gerek yok
        }
        
        // LCP verilerini sıfırla
        $assets['lcp'] = [];
        
        // Meta'yı güncelle
        update_term_meta($term_id, 'assets', $assets);
        error_log("✅ LCP data reset for term #{$term_id} ({$taxonomy})");
    }
    
    /**
     * LCP verilerini sıfırla (user profile update edildiğinde)
     */
    public function reset_lcp_data_on_user_save($user_id) {
        // Assets meta verisini al
        $assets = get_user_meta($user_id, 'assets', true);
        
        if (!is_array($assets)) {
            return; // Assets yoksa zaten LCP yok
        }
        
        // LCP verisi var mı kontrol et
        if (empty($assets['lcp']['desktop']) && empty($assets['lcp']['mobile'])) {
            return; // LCP verisi yoksa reset'e gerek yok
        }
        
        // LCP verilerini sıfırla
        $assets['lcp'] = [];
        
        // Meta'yı güncelle
        update_user_meta($user_id, 'assets', $assets);
        error_log("✅ LCP data reset for user #{$user_id}");
    }
    
    /**
     * LCP verilerini sıfırla (archive/dynamic type'lar için - option based)
     * Archive: {post_type}_archive_{lang}
     * Dynamic: search_{lang}, 404_{lang}, woo_account_{endpoint}_{lang}
     */
    public function reset_lcp_data_for_option($option_name) {
        // Option'dan assets verisini al
        $assets = get_option($option_name);
        
        if (!is_array($assets)) {
            return; // Assets yoksa zaten LCP yok
        }
        
        // LCP verisi var mı kontrol et
        if (empty($assets['lcp']['desktop']) && empty($assets['lcp']['mobile'])) {
            return; // LCP verisi yoksa reset'e gerek yok
        }
        
        // LCP verilerini sıfırla
        $assets['lcp'] = [];
        
        // Option'ı güncelle
        update_option($option_name, $assets);
        error_log("✅ LCP data reset for option: {$option_name}");
    }

    public function on_post_delete( $post_id ){
        $post = get_post($post_id);
        if ( !isset($post->post_type) ) return;
        $notification_post_types = Data::get("notification_post_types");
        if( ENABLE_NOTIFICATIONS && !empty( $notification_post_types ) ){
            if( in_array($post->post_type, $notification_post_types) ){
                Notifications::delete_post_notifications($post_id);
            }
        }
        if(ENABLE_CHAT){
            Messenger::remove_by_post($post_id);            
        }
    }

    public function on_user_delete($user_id){
        if(ENABLE_NOTIFICATIONS){
            Notifications::delete_user_notifications($user_id);            
        }
        if(ENABLE_CHAT){
           Messenger::remove_by_user($user_id); 
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

    public function newsletter($action="", $email=""){
        global $wpdb;
        if(empty($email)){
           $email = $this->user->user_email ?? '';
        }
        switch($action){
            case "unsubscribe" :
                 $wpdb->update($wpdb->prefix . 'newsletter', array('status'=>'U'), array('email'=>$email));
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

    // ── MEMBERSHIP DELEGATES ──────────────────────────────────────────────────
    // Gerçek implementasyon: SaltHareket\Membership\MembershipManager
    // Bu metodlar geriye dönük uyumluluk için korunuyor.
    // TODO: methods/user/*/index.php güncellenince bu wrapper'lar kaldırılabilir.

    public function login( $vars = [], $callback = '', $role = '' ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->login( $vars, $role );
    }
    public function register( $vars = [], $callback = '', $role = 'author' ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->register( $vars, $callback, $role );
    }
    public function user_register_hook( $user_id, $vars = [] ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->userRegisterHook( (int) $user_id, is_array( $vars ) ? $vars : [] );
    }
    public function send_activation( $user_id = 0 ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->sendActivation( (int) $user_id );
    }
    public function verify_otp( $vars = [] ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->verifyOtp( $vars );
    }
    public function resend_otp( $vars = [] ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->resendOtp( $vars );
    }
    public function otp_status( $vars = [] ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->otpStatus( $vars );
    }
    public function change_activation_method( $vars = [] ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->changeActivationMethod( $vars );
    }
    public function password_recover( $vars = [], $callback = '' ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->passwordRecover( $vars );
    }
    public function update_profile( $vars = [], $callback = '' ): array {
        return \SaltHareket\Membership\MembershipManager::getInstance()->updateProfile( $vars );
    }
    public function on_user_login( $user_login, $user ): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->onUserLogin( $user_login, $user );
    }
    public function on_user_logout( $user_id = 0 ): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->onUserLogout( (int) $user_id );
    }
    public function logout_without_confirmation( $action, $result ): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->logoutWithoutConfirmation( (string) $action, $result );
    }
    public function update_online_users_status(): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->updateOnlineStatus();
    }
    public function update_online_users_status_logout( $user_id = 0 ): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->updateOnlineStatusLogout( (int) $user_id );
    }
    public static function user_is_online( $user_id ): bool {
        return \SaltHareket\Membership\MembershipManager::isUserOnline( (int) $user_id );
    }
    public function redirect_to_profile(): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->redirectToProfile();
    }
    public function user_not_activated(): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->redirectIfNotActivated();
    }
    public function user_profile_not_completed(): void {
        \SaltHareket\Membership\MembershipManager::getInstance()->redirectIfNotCompleted();
    }
    public function user_after_update_hook( $user_id, $old_user_data = null ): void {
        $this->update_user( $user_id );
        $this->reset_lcp_data_on_user_save( $user_id );
    }
    public function user_before_update_hook( $user_id ): void {
        if ( ! is_admin() ) $this->update_user( $user_id );

        // Admin profil sayfasından status güncelleme
        if ( ! isset( $_POST['sh_status_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['sh_status_nonce'], 'sh_update_user_status_' . $user_id ) ) return;
        if ( ! current_user_can( 'edit_users' ) ) return;

        $status = sanitize_text_field( $_POST['sh_user_status'] ?? '' );
        $valid  = [ 'pending', 'activated', 'approved', 'rejected' ];
        if ( in_array( $status, $valid, true ) ) {
            $mm = \SaltHareket\Membership\MembershipManager::getInstance();
            match ( $status ) {
                'approved'  => $mm->approveUser( $user_id ),
                'rejected'  => $mm->rejectUser( $user_id ),
                'activated' => $mm->activateUser( $user_id ),
                default     => update_user_meta( $user_id, 'user_status', $status ),
            };
        }
    }
    public function user_after_meta_update_hook( $meta_id, $user_id, $meta_key, $_meta_value ): void {
        if ( ! is_admin() ) $this->update_user( $user_id );
    }
    public function onUserAfterUpdate( $user_id, $old_user_data = null ): void {
        $this->user_after_update_hook( $user_id, $old_user_data );
    }
    public function onAdminUserUpdate( $user_id ): void {
        // Admin profil sayfasından status güncelleme
        if ( ! isset( $_POST['sh_status_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['sh_status_nonce'], 'sh_update_user_status_' . $user_id ) ) return;
        if ( ! current_user_can( 'edit_users' ) ) return;

        $status = sanitize_text_field( $_POST['sh_user_status'] ?? '' );
        $valid  = [ 'pending', 'activated', 'approved', 'rejected' ];
        if ( in_array( $status, $valid, true ) ) {
            $mm = \SaltHareket\Membership\MembershipManager::getInstance();
            match ( $status ) {
                'approved' => $mm->approveUser( $user_id ),
                'rejected' => $mm->rejectUser( $user_id ),
                'activated' => $mm->activateUser( $user_id ),
                default    => update_user_meta( $user_id, 'user_status', $status ),
            };
        }
    }
    public function on_login_redirect(): void {
        if ( isset( $_SESSION['referer_url'] ) ) {
            $url = $_SESSION['referer_url'];
            session_write_close();
            session_destroy();
            wp_redirect( $url );
        } else {
            wp_redirect( get_account_endpoint_url( 'dashboard' ) );
        }
    }
    public function notification_count(): int {
        return \SaltHareket\Membership\MembershipManager::getInstance()->getNotificationCount();
    }
    // ── END MEMBERSHIP DELEGATES ──────────────────────────────────────────────









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




    /**
     * Review oluştur/güncelle — AJAX endpoint.
     * Reviews class'ına delegate eder, notification'ı burada yapar.
     *
     * @deprecated Yeni kodda doğrudan Reviews class'ını kullanın.
     */
    public function comment_product( $vars = [] ) {
        $reviews      = new Reviews( $this->user->ID ?? 0 );
        $is_update    = ! empty( $vars['comment_id'] );
        $admin_review = ! empty( $vars['admin'] );
        $target_id    = (int) ( $vars['comment_profile'] ?? 0 );
        $post_id      = (int) ( $vars['product_id'] ?? 0 );

        if ( $is_update ) {
            // --- UPDATE ---
            $result = $reviews->update( (int) $vars['comment_id'], [
                'content'  => $vars['comment'] ?? '',
                'rating'   => (int) ( $vars['rating'] ?? 0 ),
                'approved' => ! empty( $vars['comment_approved'] ) || ( defined( 'DISABLE_REVIEW_APPROVE' ) && DISABLE_REVIEW_APPROVE ),
                'notify'   => false, // notification'ı aşağıda kendimiz yapacağız
            ] );

            if ( $result['success'] ) {
                $is_approved = (int) ( $vars['comment_approved'] ?? 0 ) || ( defined( 'DISABLE_REVIEW_APPROVE' ) && DISABLE_REVIEW_APPROVE );
                if ( $is_approved && $target_id > 0 ) {
                    $this->_send_review_notification( 'review-approved', $target_id, $this->user->ID ?? 0, $post_id );
                }
            }
        } else {
            // --- CREATE ---
            $result = $reviews->create( [
                'target_id'   => $target_id > 0 ? $target_id : $post_id,
                'target_type' => $target_id > 0 ? 'user' : 'post',
                'author_id'   => (int) ( $vars['user_id'] ?? $this->user->ID ?? 0 ),
                'rating'      => (int) ( $vars['rating'] ?? 0 ),
                'title'       => $vars['comment_session_title'] ?? '',
                'content'     => $vars['comment'] ?? '',
                'approved'    => ! empty( $vars['comment_approved'] ) || ( defined( 'DISABLE_REVIEW_APPROVE' ) && DISABLE_REVIEW_APPROVE ),
                'notify'      => false,
            ] );

            if ( $result['success'] && $target_id > 0 ) {
                $this->_send_review_notification( 'new-review', $target_id, $this->user->ID ?? 0, $post_id );
            }
        }

        $comment_id = $result['id'] ?? 0;

        return json_encode( [
            'error'    => ! $result['success'],
            'message'  => $result['message'],
            'data'     => $comment_id,
            'resubmit' => false,
            'redirect' => '',
            'refresh'  => $admin_review,
            'html'     => '',
        ] );
    }

    /**
     * Review detay — AJAX endpoint.
     */
    public function comment_product_detail( $vars = [] ) {
        $error   = false;
        $message = '';
        $comment = null;

        if ( ! empty( $vars['id'] ) ) {
            $reviews = new Reviews();
            $comment = $reviews->get( (int) $vars['id'] );
        }

        if ( ! $comment ) {
            $error   = true;
            $message = 'Comment ID is not found.';
        }

        $context            = Timber::context();
        $context['comment'] = $comment;

        return json_encode( [
            'error'    => $error,
            'message'  => $message,
            'data'     => '',
            'resubmit' => false,
            'redirect' => '',
            'html'     => Timber::compile( 'users/comment-modal.twig', $context ),
        ] );
    }

    /**
     * Review notification helper — role bazlı event gönderir.
     */
    private function _send_review_notification( string $action, int $target_user_id, int $author_id, int $post_id ): void {
        if ( ! defined( 'ENABLE_NOTIFICATIONS' ) || ! ENABLE_NOTIFICATIONS ) return;
        if ( $target_user_id < 1 ) return;

        $target_user = new User( $target_user_id );
        $author_user = new User( $author_id );
        $role        = $target_user->get_role() ?: 'client';
        $post_obj    = $post_id > 0 ? get_post( $post_id ) : null;

        $this->notification(
            $role . '/' . $action,
            [
                'user'      => $author_user,
                'recipient' => $target_user->ID,
                'post'      => $post_obj,
            ]
        );
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
            $marker = array();
            // Post'un kendi marker meta'sı varsa kullan
            $marker_data = $post->meta('map_marker') ?? null;
            if($marker_data && is_array($marker_data)){
                $marker = array(
                    "icon"   => $marker_data["url"] ?? '',
                    "width"  => $marker_data["width"] ?? 0,
                    "height" => $marker_data["height"] ?? 0,
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


    public static function log($functionName="", $description=""){
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
        $integrations = QueryCache::get_field("integrations", "options");
        if ($integrations) {
            foreach ($integrations as $integration) {
                if ($integration['name'] === $slug) {
                    return $integration['keys'];
                }
            }
        }
        return [];
    }

    public function measureLCP($url, $deviceType = 'desktop') {
        $api = self::get_integration("google_pagespeed_insights");
        if($api){
            $apiKey = $api["key"] ?? '';
            if(!empty($apiKey)){
                $endpoint = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=" . urlencode($url) . "&key=" . $apiKey . "&strategy=" . $deviceType;
                $response = wp_remote_get( $endpoint, [ 'timeout' => 60 ] );
                if ( ! is_wp_error( $response ) ) {
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );
                    return $data['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] ?? false;
                }
            }
        }
        return false;
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

if(!function_exists("pll_acfe_advanced_link")){
    function pll_acfe_advanced_link($data = []) {
        return $data;
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
   ////error_log(print_r(get_untranslated_terms("tr"), true));
   //translate_post_ai(4021, 'tr');
   //translate_post_ai(4161, 'tr');
});
*/
