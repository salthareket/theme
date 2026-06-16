<?php

 use Timber\Timber;

// required user activation
if(ENABLE_MEMBERSHIP_ACTIVATION){
    $activation_type = "";
    if(is_user_logged_in()){
        $user_id = get_current_user_id();
        $user = get_user_by( 'id', $user_id );
        $activation_type = MEMBERSHIP_ACTIVATION_TYPE;
        $user_activation_type = get_user_meta($user_id, "activation_type", true);
        if($user_activation_type){
            $activation_type = $user_activation_type;
        }
    }else{
        if(isset($_GET['activation-code']) && ENABLE_ACTIVATION_EMAIL_AUTOLOGIN){
            $activation_code = sanitize_text_field($_GET['activation-code']);
            require SH_CLASSES_PATH . "class.encrypt.php";
            $decrypt = new Encrypt();
            $data = $decrypt->decrypt($activation_code);
            if($data){
                $user_id = $data["id"];
                $user = get_user_by( 'id', $user_id );
                $activation_type = MEMBERSHIP_ACTIVATION_TYPE;
                $user_activation_type = get_user_meta($user_id, "activation_type", true);
                if($user_activation_type){
                    $activation_type = $user_activation_type;
                }
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);                
            }
        }
    }
    if($activation_type == "email"){
        $piority = is_user_logged_in()?99999:0;
        add_action( 'init', 'verify_user_code', $piority);
        function verify_user_code(){
            if(isset($_GET['activation-code'])){
                $activation_code = sanitize_text_field($_GET['activation-code']);
                $decrypt = new Encrypt();
                $data = $decrypt->decrypt($activation_code);
                if($data){
                    $user_id = $data["id"];
                    if (is_user_logged_in()) {
                        if($user_id == get_current_user_id()){
                            $user = get_user_by( 'id', $user_id );
                            $isActivated = get_user_meta($user_id , 'user_status', 1);
                            if($isActivated){?>
                               <script>
                                    var activation_code_response = "Your account is already verified!";
                                    var activation_code_status = true;
                               </script>
                            <?php
                            }else{
                                global $wpdb;
                                $code = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT user_activation_key FROM {$wpdb->users} WHERE ID = %d",
                                    (int) $user_id
                                ) );
                                if($code == $data['code']){
                                    update_user_meta($user_id, 'user_status', 1);
                                    $user = new User($user_id);
                                    $salt = Data::get("salt");
                                    if ($salt) $salt->log("activation screen", json_encode($user));
                                    if ($salt) $salt->user = $user;
                                    $role = $user->get_role();
                                    if ($salt) $salt->notification(
                                        $role."/new-account",
                                        array(
                                            "user"      => $user,
                                            "recipient" => $user->ID,
                                        )
                                    );
                                    ?>
                                       <script>
                                           var activation_code_response = "Your account is verified!"
                                           var activation_code_status = true;
                                       </script>
                                    <?php
                                }else{
                                    ?>
                                       <script>
                                           var activation_code_response = "Your activation code is invalid or expired!";
                                           var activation_code_status = false;
                                       </script>
                                    <?php
                                }            
                            }
                        }else{
                            ?>
                               <script>
                                   var activation_code_response = "1 Your activation code is invalid!";
                                   var activation_code_status = false;
                               </script>
                            <?php
                        }
                    }else{
                        ?>
                            <script>
                                var activation_code_response = "Please login before using your activation link.";
                                var activation_code_status = false;
                            </script>
                        <?php
                    }
                }else{
                    ?>
                       <script>
                           var activation_code_response = "2 Your activation code is invalid!";
                           var activation_code_status = false;
                       </script>
                    <?php
                }
                add_action( 'wp_head', 'verify_user_code_response' );
            }
        }
        function verify_user_code_response(){
            ?>
            <script>
            $( document ).ready(function() {
                if(typeof activation_code_response !== "undefined"){
                    _alert(activation_code_response);
                    if(activation_code_status){
                       removeQueryString("activation-code");
                    }
                }
            });
            </script>
            <?php
        }
    }
}


add_action( 'init', 'verify_user_email');
function verify_user_email(){
    if(isset($_GET['activation-email'])){
        $activation_email = sanitize_text_field($_GET['activation-email']);
        $decrypt = new Encrypt();
        $data = $decrypt->decrypt($activation_email);
        if($data){
            $user_id = $data["id"];
            if($user_id == get_current_user_id()){
                $user = get_user_by( 'id', $user_id );
                $email = $user->user_email;
                $email_temp = get_user_meta($user_id , '_email_temp', 1);
                if($email == $email_temp || empty($email_temp)){
                   delete_user_meta($user_id, '_email_temp');
                ?>
                   <script>
                        var activation_code_response = "Your email <b><?php echo $email_temp;?></b> is already verified!";
                        var activation_code_status = true;
                   </script>
                <?php
                }else{
                    if($email_temp  == $data['email']){
                        $user_data = array(
                            'ID' => $user_id,
                            'user_email'   => $email_temp
                        );
                        wp_update_user( $user_data );
                        update_user_meta( $user_id, 'user_email', $email_temp );
                        update_user_meta( $user_id, 'billing_email', $email_temp );
                        delete_user_meta( $user_id, '_email_temp');
                        ?>
                           <script>
                               var activation_code_response = "Your new email <b><?php echo $email_temp;?></b> is verified!"
                               var activation_code_status = true;
                           </script>
                        <?php
                    }else{
                        ?>
                           <script>
                               var activation_code_response = "Your activation code is invalid or expired!";
                               var activation_code_status = false;
                           </script>
                        <?php
                    }            
                }
            }else{
                ?>
                   <script>
                       var activation_code_response = "Your activation code is invalid!";
                       var activation_code_status = false;
                   </script>
                <?php
            }
        }else{
            ?>
               <script>
                   var activation_code_response = "Your activation code is invalid!";
                   var activation_code_status = false;
               </script>
            <?php
        }
        add_action( 'wp_head', 'verify_user_email_response' );
    }
}
function verify_user_email_response(){
    ?>
    <script>
    $( document ).ready(function() {
        if(typeof activation_code_response !== "undefined"){
            _alert(activation_code_response);
            if(activation_code_status){
               removeQueryString("activation-email");
            }
        }
    });
    </script>
    <?php
}


/*
Usage:
$req = array(
   array(
      "role"   => "role_name",
      "action" => "action_name"
   )
)
*/
function login_required($req=array()){
    if( !is_user_logged_in() ) {
        if($_SESSION){
           $_SESSION['referer_url'] = current_url();
        }
        wp_redirect(get_account_endpoint_url('my-account'));
        die;
    }else{
        if($req){
            //$user = new User(wp_get_current_user());
            $user = Timber::get_user();
            $user_role = $user->get_role();//get_user_role();
            $action = get_query_var("action");

            $index = array_search($user_role, array_column($req, 'role'));
            if($index == ""){
                if(isset($req["template"])){
                    return $req["template"];
                }else{
                    if(isset($req["redirect"])){
                       $redirect_url = $req["redirect"];
                    }else{
                       $redirect_url = get_account_endpoint_url( 'profile' );
                    }
                    //wp_safe_redirect($redirect_url);
                    //die;                    
                }

            }else{
                if(!empty($action) && array_key_exists("action", $req[$index])){
                    if(!empty($req[$index]["action"])){
                        if(!in_array($action, $req[$index]["action"])){
                           wp_safe_redirect(get_account_endpoint_url( 'profile' ));
                           die;
                        }                        
                    }
                }
            }
        }
    }
}

function get_login_url($redirect_to=""){
	if(ENABLE_ECOMMERCE){
        echo "111";
        return woo_login_url($redirect_to);
	}else{
		//$my_account_page = get_page_by_path('my-account');
		//$login_url = get_permalink( $my_account_page->ID );
        $login_url = get_permalink(get_option('options_myaccount_page_id'));
		if($redirect_to){
	        $_SESSION['referer_url'] = esc_url($redirect_to);
	    }
	    return $login_url;
	}
}

function getLogoutUrl($redirectUrl = ''){
    if(!$redirectUrl) $redirectUrl = site_url();
    $return = str_replace("&amp;", '&', wp_logout_url($redirectUrl));
    return $return;
}

function get_account_endpoint_url($endpoint=""){
	if(ENABLE_ECOMMERCE){
        return wc_get_account_endpoint_url($endpoint);
	}else{
		$base_url = get_permalink(get_option('options_myaccount_page_id'));//get_permalink(get_page_by_path('my-account'));
	    $endpoint_url = trailingslashit($base_url) . trailingslashit($endpoint);
	    return esc_url($endpoint_url);
	}
}

function get_current_endpoint($base=""){
	if(ENABLE_ECOMMERCE){
		return WC()->query->get_current_endpoint();
	}else{
		return getUrlEndpoint("", $base);
	}
}

function get_account_menu_items(){
    $account_menu = array();
    if(ENABLE_ECOMMERCE){
        return wc_get_account_menu_items();
    }else{
        return salt_get_account_menu_items();
    }
}

function get_account_menu_item_classes($endpoint=""){
    if(ENABLE_ECOMMERCE){
        return wc_get_account_menu_item_classes($endpoint);
    }else{
        return salt_get_account_menu_item_classes($endpoint);
    }
}

function get_account_menu(){
    $account_nav = array();
    foreach (get_account_menu_items() as $endpoint => $label) {
        $nav_item = [
            "type"   => $endpoint,
            "action" => $endpoint,
            "class"  => get_account_menu_item_classes($endpoint),
            "url"    => esc_url(get_account_endpoint_url($endpoint)),
            "title"  => esc_html($label),
            "count"  => 0,
        ];
        if ($endpoint == "messages" && ENABLE_CHAT) {
            $nav_item["count"] = Messenger::count();
        }
        if ($endpoint == "notifications" && ENABLE_NOTIFICATIONS) {
            $salt = Salt::get_instance();//new Salt();
            $nav_item["count"] = $salt->notification_count();
        }
        if ($endpoint == "favorites" && ENABLE_REACTIONS) {
            $nav_item["count"] = count(Data::get("favorites"));
        }
        if ($endpoint == "reviews" && !DISABLE_COMMENTS) {
            $salt = Salt::get_instance();//new Salt();
            $nav_item["count"] = $salt->user->get_reviews_count(0);
        }
        $account_nav[] = $nav_item;
    }
    return $account_nav;
}



//add_action('wp_login', 'add_custom_cookie_admin');
function add_custom_cookie_admin() {
    setcookie('logged_in', 1, 3 * DAYS_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN ); // expire in a day
}
//add_action('wp_logout', 'remove_custom_cookie_admin');
function remove_custom_cookie_admin() {
    unset( $_COOKIE['logged_in'] );
    setcookie('logged_in', '', time() - 3600);
}


if(ENABLE_MEMBERSHIP){
    function salt_my_account_links(){
        $links = array();
        
        // Sadece custom endpoint'leri ekle - WooCommerce endpoint'leri menüde gösterilmeyecek
        if(ENABLE_CHAT){
            $links["messages"] = array(
               "roles" => array(),
               "title" => trans("Messages"),
               "menu" => trans("Messages"),
               "source" => "custom"
            );
        }
        if(ENABLE_NOTIFICATIONS){
            $links["notifications"] = array(
                "roles" => array(),
                "title" => trans("Notifications"),
                "menu" => trans("Notifications"),
                "source" => "custom"
            );
        }
        if(ENABLE_REACTIONS){
            $links["favorites"] = array(
                "roles" => array(),
                "title" => trans("Favorites"),
                "menu" => trans("Favorites"),
                "source" => "custom"
            );
        }
        if(ENABLE_MEMBERSHIP_ACTIVATION){
            $links["not-activated"] = array(
                "roles" => array(),
                "title" => trans("Your account is not activated"),
                "menu" => trans("Not Activated"),
                "source" => "custom"
            );
        }
        if(ENABLE_LOST_PASSWORD){
            $links["renew-password"] = array(
                "roles" => array(),
                "title" => trans("Reset your password"),
                "menu" => trans("Reset Password"),
                "source" => "custom"
            );
        }
        if(ENABLE_PASSWORD_RECOVER){
            $links["security"] = array(
                "roles" => array(),
                "title" => trans("Security"),
                "menu" => trans("Security"),
                "source" => "custom"
            );
        }
        if(!DISABLE_COMMENTS){
            $links["reviews"] = array(
                "roles" => array(),
                "title" => trans("Reviews"),
                "menu" => trans("Reviews"),
                "source" => "custom"
            );
        }

        $links["customer-logout"] = array(
            "roles" => array(),
            "title" => "",
            "menu" => "",
            "source" => "custom"
        );

        $links = array_merge($links, Data::get("my_account_links") ?: []);

        $user = Timber::get_user();
        if(!empty($user)){
            $role = $user->get_role();
            $links_tmp = array();
            foreach($links as $key => $link){
                if(empty($link["roles"]) || in_array($role, $link["roles"])){
                   $links_tmp[$key] = $link;
                }
            }
            $links = $links_tmp;
        }

        return $links;
    }

    function salt_get_account_menu_items(){
        $account_menu = array();
        $links = salt_my_account_links();
        if($links){
            foreach($links as $endpoint => $link){
                if(!empty($link["menu"])){
                    $account_menu[$endpoint] = $link["menu"];                    
                }
            }
        }
        return $account_menu;
    }

    function salt_get_account_menu_item_classes($endpoint){
        return "menu-item-$endpoint";
    }

    // Endpoints
    add_action( 'init', 'salt_add_my_account_endpoint' );
    function salt_add_my_account_endpoint() {
        $links = salt_my_account_links();
        if($links){
            $needs_flush = false;
            $rules = get_option('rewrite_rules', []);
            
            // WooCommerce'in kendi endpoint'leri - bunları atlayalım
            $wc_endpoints = [];//['orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'dashboard', 'customer-logout'];
            
            foreach(array_keys($links) as $endpoint){
                // WooCommerce endpoint'lerini atla - onlar zaten WC tarafından register ediliyor
                if(in_array($endpoint, $wc_endpoints)){
                    continue;
                }
                
                add_rewrite_endpoint( $endpoint, EP_PAGES );
                // Endpoint rewrite rule'da yoksa flush gerekli
                if (!empty($rules) && !isset($rules[$endpoint . '(/(.+))?/?$'])) {
                    $needs_flush = true;
                }
            }
            // Sadece yeni endpoint eklendiginde flush yap (her sayfa yuklemesinde degil)
            if ($needs_flush) {
                flush_rewrite_rules(false);
            }
        }
    }

    function my_account_content_not_activated() {
        $templates = array("my-account/not-activated.twig");
        $context = Timber::context();
        $context['type'] = "not-activated"; 
        $context['title'] = trans("Activation");
        $context['description'] = trans("Make sure you completed your profile fully. We will watch you with the right client requests according to your profile info.");
        Timber::render($templates , $context);
    }

    /*function my_account_content_not_completed() {
        $templates = array("my-account/not-completed.twig");
        $context = Timber::context();
        $context['type'] = "not-completed"; 
        $context['title'] = trans("Your profile is not completed");
        $context['description'] = trans("Make sure you completed your profile fully. We will watch you with the right client requests according to your profile info.");
        Timber::render($templates , $context);
    }*/

    function my_account_content_renew_password() {
        $templates = array("my-account/form-renew-password.twig");
        $context = Timber::context();
        $context['type'] = "renew-password"; 
        $context['title'] = trans("Renew Password");
        $context['description'] = "";
        Timber::render($templates , $context);
    }

    function my_account_content_profile() {
        login_required();
        $user = Data::get("user");
        $templates = array("my-account/profile.twig");
        $context = Timber::context();
        $type = "profile";
        $context['type'] = $type; 
        $context['title'] = trans("Your ".$user->get_role_name()." Profile");
        $context['description'] = trans("Make sure you completed your profile fully. We will watch you with the right client requests according to your profile info.");
        Timber::render($templates , $context);
    }

    function my_account_content_security() {
        login_required();
        $user = Data::get("user");
        if($user->get_status()){
           $templates = array("my-account/security.twig");
        }else{
           $templates = array("my-account/not-activated.twig");
        }
        $context = Timber::context();
        $context['type'] = "security"; 
        $context['title'] = trans("Security");
        $context['description'] = trans("Make sure you completed your profile fully. We will watch you with the right client requests according to your profile info.");
        Timber::render($templates , $context);
    }

    function my_account_content_reviews() {
        $user = Data::get("user");
        $templates = array("my-account/my-reviews.twig");
        $context = Timber::context();
        $context['type'] = "reviews"; 
        $context['title'] = trans("My Reviews");    
        //$context['comments'] = $comments;
        $statuses = array(
            array(
               "name" => "Approved",
               "slug" => "approved",
               "count" => $user->get_reviews_count(1)
            ),
            array(
               "name" => "Waiting Approval",
               "slug" => "waiting-approval",
               "count" => $user->get_reviews_count(0)
            )
        );
        $context['statuses'] = $statuses;
        $action = get_query_var("action");
        if(empty($action)){
           $action = "approved";
        }
        $context['action'] = $action;
        Timber::render($templates , $context);
    }

    function my_account_content_messages(){
        if(ENABLE_CHAT){
            $user = Data::get("user");
            $templates = array("my-account/my-messages.twig");
            $context = Timber::context();
            $context['type'] = "messages"; 
            $context['title'] = trans("Messages");
            $context['description'] = "";//trans("Description text here.");
            Timber::render($templates , $context);
        }
    }

    function my_account_content_notifications(){
        if(ENABLE_NOTIFICATIONS){
            $user = Data::get("user");
            $templates = array("my-account/notifications.twig");
            $context = Timber::context();
            $context['type'] = "notifications"; 
            $context['title'] = trans("Notifications");
            $context['description'] = "";//trans("Description text here.");
            Timber::render($templates , $context);
        }   
    }

    function my_account_content_favorites(){
        if (!ENABLE_REACTIONS) return;

        // Yeni Reactions sistemi — favorite tipindeki post ID'lerini al
        $fav_ids = \SaltHareket\Reactions\Reactions::getByUser(
            get_current_user_id(),
            'favorite',
            'post'
        );

        // Favorites'taki baskın post_type'ı tespit et
        $post_type = 'product';
        if (!empty($fav_ids)) {
            $type_counts = [];
            foreach ($fav_ids as $id) {
                $pt = get_post_type($id);
                if ($pt && $pt !== 'revision' && $pt !== 'attachment') {
                    // product_variation → product olarak say (pagination ayarları product'ta)
                    $pt = ($pt === 'product_variation') ? 'product' : $pt;
                    $type_counts[$pt] = ($type_counts[$pt] ?? 0) + 1;
                }
            }
            if (!empty($type_counts)) {
                arsort($type_counts);
                $post_type = array_key_first($type_counts);
            }
        }

        // Bu post_type için post_pagination ayarlarını al
        // Data::get timing sorunu olabilir — direkt ACF'den de dene
        $pagination_settings = function_exists('get_post_type_pagination')
            ? get_post_type_pagination($post_type)
            : [];

        // Fallback: Data::get boş geldiyse ACF'den direkt oku
        if (empty($pagination_settings) && function_exists('get_field')) {
            $raw = get_field('post_pagination', 'options');
            if (is_array($raw)) {
                foreach ($raw as $item) {
                    if (isset($item['post_type']) && $item['post_type'] === $post_type) {
                        $paged_on = !empty($item['paged']);
                        $cols     = max(1, (int) ($item['catalog_columns'] ?? 3));
                        $rows     = max(1, (int) ($item['catalog_rows'] ?? 2));
                        $pagination_settings = [
                            'paged'           => $paged_on,
                            'type'            => $item['type'] ?? 'default',
                            'posts_per_page'  => $paged_on ? $cols * $rows : -1,
                            'catalog_columns' => $cols,
                            'catalog_rows'    => $rows,
                        ];
                        break;
                    }
                }
            }
        }

        $paged          = max(1, (int) get_query_var('paged', 1));
        // paged kapalıysa tümünü getir, açıksa rows*cols hesapla
        $is_paged       = !empty($pagination_settings['paged']);
        $posts_per_page = $is_paged && !empty($pagination_settings['posts_per_page'])
            ? (int) $pagination_settings['posts_per_page']
            : ($is_paged ? 12 : -1);
        $catalog_columns = (int) ($pagination_settings['catalog_columns'] ?? 3);

        // loop_shop_columns override — woo_archive_grid() için
        add_filter('loop_shop_columns', function() use ($catalog_columns) {
            return $catalog_columns;
        }, 999);

        // loop_shop_per_page override — Timber query'sini etkilemesin diye geçici kaldır
        add_filter('loop_shop_per_page', function() use ($posts_per_page) {
            return $posts_per_page;
        }, 999);

        // Timber query — pagination nesnesi + ürün listesi
        // post_type: ['product','product_variation'] → her ikisini de getirir
        // iconic_ssv_exclude_variations: XT/WSSV plugin'inin bu query'ye dokunmaması için
        $timber_posts = [];
        if (!empty($fav_ids)) {
            $timber_posts = Timber::get_posts([
                'post_type'                    => ['product', 'product_variation'],
                'posts_per_page'               => $posts_per_page,
                'paged'                        => $paged,
                'post__in'                     => $fav_ids,
                'orderby'                      => 'post__in',
                'no_found_rows'                => false,
                'is_favorites'                 => true,
                'iconic_ssv_exclude_variations' => true,
                'xt_woovas_exclude'            => true,
            ]);
        }

        // WC loop HTML — ilk sayfa PHP'den render et, sonraki sayfalar AJAX'tan gelir
        $loop_html = '';
        if (!empty($timber_posts)) {
            global $wp_query, $woocommerce_loop;
            $original_query           = $wp_query;
            $woocommerce_loop['loop'] = 0;

            // Favorites context'ini content-product.php'ye geçir
            $GLOBALS['pae_tease_extra_ctx'] = ['variation_add_to_cart' => true];

            ob_start();
            foreach ($timber_posts as $timber_post) {
                $wc_product = wc_get_product($timber_post->ID);
                if (!$wc_product) continue;
                if ($wc_product->is_type('variation')) {
                    $parent = wc_get_product($wc_product->get_parent_id());
                    if (!$parent || $parent->get_status() !== 'publish') continue;
                } elseif ($timber_post->post_status !== 'publish') {
                    continue;
                }
                $GLOBALS['product'] = $wc_product;
                wc_setup_product_data($timber_post->ID);
                $woocommerce_loop['loop']++;
                wc_get_template_part('content', 'product');
            }
            wp_reset_postdata();
            $loop_html = ob_get_clean();

            $wp_query = $original_query;
            unset($GLOBALS['pae_tease_extra_ctx']);
        }

        // Pagination vars — pagination_ajax AJAX handler için
        $enc = class_exists('Encrypt') ? new Encrypt() : null;
        $query_pagination_vars = '';
        if ($enc && !empty($fav_ids)) {
            $query_pagination_vars = $enc->encrypt([
                'post_type'                     => ['product', 'product_variation'],
                'post__in'                      => $fav_ids,
                'orderby'                       => 'post__in',
                'posts_per_page'                => $posts_per_page,
                'paged'                         => 1,
                'is_favorites'                  => true,
                'is_woo_favorites'              => true,
                'iconic_ssv_exclude_variations' => true,
                'xt_woovas_exclude'             => true,
            ]);
        }

        // post_pagination context
        $post_pagination_ctx = Data::get('post_pagination') ?: [];
        if (empty($post_pagination_ctx[$post_type])) {
            $post_pagination_ctx[$post_type] = [
                'paged'           => true,
                'type'            => $pagination_settings['type'] ?? 'default',
                'posts_per_page'  => $posts_per_page,
                'catalog_columns' => $catalog_columns,
                'catalog_rows'    => $pagination_settings['catalog_rows'] ?? 3,
            ];
        } elseif (empty($post_pagination_ctx[$post_type]['posts_per_page'])) {
            $post_pagination_ctx[$post_type]['posts_per_page'] = $posts_per_page;
        }

        $context = Timber::context();
        $context['type']                     = 'my-favorites';
        $context['title']                    = trans("Favorites");
        $context['post_type']                = $post_type;
        $context['posts']                    = $timber_posts;
        $context['paged']                    = $paged;
        $context['favorites_empty']          = empty($fav_ids);
        $context['loop_html']                = $loop_html;
        $context['query_pagination_vars']    = $query_pagination_vars;
        $context['query_pagination_request'] = '';
        $context['post_pagination']          = $post_pagination_ctx;
        $context['variation_add_to_cart']    = true; // favorites'ta variation'lar için add to cart aktif

        Timber::render(['my-account/my-favorites.twig'], $context);
    }
        
    function my_account_content_customer_logout(){
        //$logout_url = get_permalink(get_page_by_path('my-account'));
        $logout_url = get_permalink(get_option('options_myaccount_page_id'));
        wp_logout();
        wp_safe_redirect($logout_url);
        exit();
    }

}
if(!ENABLE_ECOMMERCE && ENABLE_MEMBERSHIP){

    function salt_add_endpoint_query_vars( $vars ){
        $endpoints = my_account_endpoints();
        if($endpoints){
            $query_vars = $endpoints;
            foreach($query_vars as $query_var){
               $vars[] = $query_var;
            }
        }
        return $vars;
    }
    add_filter( 'query_vars', 'add_query_vars_filter' );

    function get_logout_url($redirect=""){
        $logout_url = wp_logout_url($redirect);
        return esc_url($logout_url);
    }

    function my_account_endpoints(){
        global $wp_rewrite;
        $endpoints = $wp_rewrite->endpoints;
        $my_account_endpoints = array_map(function ($item) {
            return end($item);
        }, $endpoints);
        return array_unique($my_account_endpoints);
    }

    function my_account_content($endpoint="") {
        if(!empty($endpoint)){
            $endpoints = my_account_endpoints();
            if(in_array($endpoint, $endpoints)){
                global $wp_query;
                if ( isset( $wp_query->query_vars[$endpoint] ) ) {
                    $endpoint = str_replace("-", "_", $endpoint);
                    $funcs = [
                        "my_account_custom_content_{$endpoint}",
                        "my_account_content_{$endpoint}"
                    ];
                    foreach($funcs as $func){
                        if(function_exists($func)){
                           call_user_func($func);
                           break;
                        }
                    }
                }
            }
        }
    }

}







//registration form add-ons

//add user role to registration
add_action( 'woocommerce_register_form_start', 'wc_extra_registation_fields' );
function wc_extra_registation_fields() {
        ?>
            <div class="form-group form-group-sm text-center text-white mb-4">
                <div class="btn-group-" role="group" aria-label="Basic checkbox toggle button group">
                    <input type="radio" id="client" name="role" class="btn-check" value="client" autocomplete="off">
                    <label class="btn btn-outline-primary" for="client">as a <?php _e('Client') ?></label>
                    <input type="radio" id="expert" name="role" class="btn-check" value="expert" autocomplete="off">
                    <label class="btn btn-outline-primary" for="expert">as an <?php _e('Expert') ?></label>
                </div>
            </div>
        <?php
}

if((ENABLE_MEMBERSHIP_ACTIVATION && MEMBERSHIP_ACTIVATION_TYPE == "sms") || (ENABLE_NOTIFICATIONS && ENABLE_SMS_NOTIFICATIONS)){
        //add user role to registration
        add_action( 'woocommerce_register_form', 'wc_extra_registation_fields_meta' );
        function wc_extra_registation_fields_meta() {
            $template = "partials/modals/fields-localization.twig";
            $context = Timber::context();
            echo Timber::compile($template, $context);
        }    
}



// Validate WooCommerce registration form custom fields.
add_action( 'woocommerce_register_post', 'wc_validate_reg_form_fields', 10, 3 );
function wc_validate_reg_form_fields($username, $email, $validation_errors) {
    if (isset($_POST['role']) && empty($_POST['role']) ) {
        $validation_errors->add('role_error', __('Role required!', 'woocommerce'));
    }
    return $validation_errors;
}


add_filter( 'woocommerce_default_address_fields', 'customising_checkout_fields', 1000, 1 );
function customising_checkout_fields( $address_fields ) {
    return $address_fields;
}

add_filter('woocommerce_checkout_fields', 'custom_checkout_billing_fields', 1000, 1);
function custom_checkout_billing_fields( $fields ) {
    return $fields;
}

add_filter('woocommerce_billing_fields', 'custom_billing_fields', 1000, 1);
function custom_billing_fields( $fields ) {
    return $fields;
}

//remove second adress fields from forms
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
add_filter( 'woocommerce_billing_fields' , 'custom_override_billing_fields' );
add_filter( 'woocommerce_shipping_fields' , 'custom_override_shipping_fields' );
function custom_override_checkout_fields( $fields ) {
  unset($fields['billing']['billing_address_2']);
  unset($fields['shipping']['shipping_address_2']);
  return $fields;
}
function custom_override_billing_fields( $fields ) {
  unset($fields['billing_address_2']);
  //unset($fields['billing_email']);
  return $fields;
}
function custom_override_shipping_fields( $fields ) {
  unset($fields['shipping_address_2']);
  return $fields;
}


if (!function_exists('woocommerce_form_field')) {
function woocommerce_form_field( $key, $args, $value = null ) {
        $defaults = array(
            'type'              => 'text',
            'label'             => '',
            'description'       => '',
            'placeholder'       => '',
            'maxlength'         => false,
            'required'          => false,
            'autocomplete'      => false,
            'id'                => $key,
            'class'             => array(),
            'label_class'       => array(),
            'input_class'       => array(),
            'container_class'   => array(),
            'return'            => false,
            'options'           => array(),
            'custom_attributes' => array(),
            'validate'          => array(),
            'default'           => '',
            'autofocus'         => '',
            'priority'          => '',
        );

        $args = wp_parse_args( $args, $defaults );
        $args = apply_filters( 'woocommerce_form_field_args', $args, $key, $value );

        if ( $args['required'] ) {
            $args['class'][] = 'validate-required';
            $required        = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';
        } else {
            $required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
        }

        if ( is_string( $args['label_class'] ) ) {
            $args['label_class'] = array( $args['label_class'] );
        }

        if ( is_string( $args['container_class'] ) ) {
            $args['container_class'] = array( $args['container_class'] );
        }

        if ( is_null( $value ) ) {
            $value = $args['default'];
        }

        // Custom attribute handling.
        $custom_attributes         = array();
        $args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

        if($args['required']){
            $args['custom_attributes']["required"] = "";
        }

        if ( $args['maxlength'] ) {
            $args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
        }

        if ( ! empty( $args['autocomplete'] ) ) {
            $args['custom_attributes']['autocomplete'] = $args['autocomplete'];
        }

        if ( true === $args['autofocus'] ) {
            $args['custom_attributes']['autofocus'] = 'autofocus';
        }

        if ( $args['description'] ) {
            $args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
        }

        if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
            foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
            }
        }

        if ( ! empty( $args['validate'] ) ) {
            foreach ( $args['validate'] as $validate ) {
                $args['class'][] = 'validate-' . $validate;
            }
        }

        $field           = '';
        $label_id        = $args['id'];
        $sort            = $args['priority'] ? $args['priority'] : '';
        $field_container = '';
        if(count($args['container_class'])>0){
           $field_container .= '<div class="'.implode( ' ', $args['container_class'] ).'">';
        }
        $field_container .= '<div class="form-group form-row-- %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</div>';
        if(count($args['container_class'])>0){
           $field_container .= '</div>';
        }

        switch ( $args['type'] ) {
            case 'country':
                $countries = 'shipping_country' === $key ? WC()->countries->get_shipping_countries() : WC()->countries->get_allowed_countries();

                if ( 1 === count( $countries ) ) {

                    $field .= '<strong>' . current( array_values( $countries ) ) . '</strong>';

                    $field .= '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . current( array_keys( $countries ) ) . '" ' . implode( ' ', $custom_attributes ) . ' class="country_to_state" readonly="readonly" />';

                } else {

                    $field = '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="country_to_state country_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . '><option value="">' . esc_html__( 'Select a country&hellip;', 'woocommerce' ) . '</option>';

                    foreach ( $countries as $ckey => $cvalue ) {
                        $field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . $cvalue . '</option>';
                    }

                    $field .= '</select>';

                    $field .= '<noscript><button type="submit" name="btn btn-base btn-sm btn-extend woocommerce_checkout_update_totals" value="' . esc_attr__( 'Update country', 'woocommerce' ) . '">' . esc_html__( 'Update country', 'woocommerce' ) . '</button></noscript>';

                }

                break;
            case 'state':
                /* Get country this state field is representing */
                $for_country = isset( $args['country'] ) ? $args['country'] : WC()->checkout->get_value( 'billing_state' === $key ? 'billing_country' : 'shipping_country' );
                $states      = WC()->countries->get_states( $for_country );

                if ( is_array( $states ) && empty( $states ) ) {

                    $field_container = '<p class="form-group form-row-- %1$s" id="%2$s" style="display: none">%3$s</p>';

                    $field .= '<input type="hidden" class="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '" readonly="readonly" data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';

                } elseif ( ! is_null( $for_country ) && is_array( $states ) ) {

                    $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="state_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ? $args['placeholder'] : esc_html__( 'Select an option&hellip;', 'woocommerce' ) ) . '"  data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '">
                        <option value="">' . esc_html__( 'Select an option&hellip;', 'woocommerce' ) . '</option>';

                    foreach ( $states as $ckey => $cvalue ) {
                        $field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . $cvalue . '</option>';
                    }

                    $field .= '</select>';

                } else {

                    $field .= '<input type="text" class="form-control input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';

                }

                break;
            case 'textarea':
                $field .= '<textarea name="' . esc_attr( $key ) . '" class="form-control input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . ( empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '' ) . ( empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '' ) . implode( ' ', $custom_attributes ) . '>' . esc_textarea( $value ) . '</textarea>';

                break;
            case 'checkbox':
                $field = '<label class="checkbox ' . implode( ' ', $args['label_class'] ) . '" ' . implode( ' ', $custom_attributes ) . '>
                        <input type="' . esc_attr( $args['type'] ) . '" class="input-checkbox ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( $value, 1, false ) . ' /> ' . $args['label'] . $required . '</label>';

                break;
            case 'text':
            case 'password':
            case 'datetime':
            case 'datetime-local':
            case 'date':
            case 'month':
            case 'time':
            case 'week':
            case 'number':
            case 'email':
            case 'url':
            case 'tel':

                if($key == "billing_postcode" || $key == "shipping_postcode"){
                    $custom_attributes[] = "data-remote='postcode_validation'";
                    $custom_attributes[] = "data-remote-param='postcode'";
                    $custom_attributes[] = "data-remote-objs='{'country':'billing_country'}'";
                }
                $field .= '<input type="' . esc_attr( $args['type'] ) . '" class="form-control input-text-- ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

                break;
            case 'select':
                $field   = '';
                $options = '';

                if ( ! empty( $args['options'] ) ) {
                    foreach ( $args['options'] as $option_key => $option_text ) {
                        if ( '' === $option_key ) {
                            // If we have a blank option, select2 needs a placeholder.
                            if ( empty( $args['placeholder'] ) ) {
                                $args['placeholder'] = $option_text ? $option_text : __( 'Choose an option', 'woocommerce' );
                            }
                            $custom_attributes[] = 'data-allow_clear="true"';
                        }
                        $options .= '<option value="' . esc_attr( $option_key ) . '" ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
                    }

                    $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '">
                            ' . $options . '
                        </select>';
                }

                break;
            case 'radio':
                $label_id .= '_' . current( array_keys( $args['options'] ) );

                if ( ! empty( $args['options'] ) ) {
                    foreach ( $args['options'] as $option_key => $option_text ) {
                        $field .= '<input type="radio" class="input-radio ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" ' . implode( ' ', $custom_attributes ) . ' id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
                        $field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) . '">' . $option_text . '</label>';
                    }
                }

                break;
        }

        if ( ! empty( $field ) ) {
            $field_html = '';

            if ( $args['label'] && 'checkbox' !== $args['type'] ) {
                $field_html .= '<label for="' . esc_attr( $label_id ) . '" class="form-label form-label-md mb-2' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
            }

            $field_html .= '<span class="woocommerce-input-wrapper">' . $field;
            //$field_html .= $field;

            if ( $args['description'] ) {
                $field_html .= '<span class="description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
            }

            $field_html .= '</span>';

            $container_class = esc_attr( implode( ' ', $args['class'] ) );
            $container_id    = esc_attr( $args['id'] ) . '_field';
            $field           = sprintf( $field_container, $container_class, $container_id, $field_html );
        }

        /**
         * Filter by type.
         */
        $field = apply_filters( 'woocommerce_form_field_' . $args['type'], $field, $key, $args, $value );

        /**
         * General filter on form fields.
         *
         * @since 3.4.0
         */
        $field = apply_filters( 'woocommerce_form_field', $field, $key, $args, $value );

        if ( $args['return'] ) {
            return $field;
        } else {
            echo $field; // WPCS: XSS ok.
        }
}
} // end if (!function_exists('woocommerce_form_field'))





// disable using email adresss on regisration
// This will suppress empty email errors when submitting the user form
add_action('user_profile_update_errors', 'my_user_profile_update_errors', 10, 3 );
function my_user_profile_update_errors($errors, $update, $user) {
    $errors->remove('empty_email');
}

// This will remove javascript required validation for email input
// It will also remove the '(required)' text in the label
// Works for new user, user profile and edit user forms
add_action('user_new_form', 'my_user_new_form', 10, 1);
add_action('show_user_profile', 'my_user_new_form', 10, 1);
add_action('edit_user_profile', 'my_user_new_form', 10, 1);
function my_user_new_form($form_type) {
    ?>
    <script type="text/javascript">
        jQuery('#email').closest('tr').removeClass('form-required').find('.description').remove();
        // Uncheck send new user email option by default
        <?php if (isset($form_type) && $form_type === 'add-new-user') : ?>
            jQuery('#send_user_notification').removeAttr('checked');
        <?php endif; ?>
    </script>
    <?php
}

//Disable the new user notification sent to the site admin
function smartwp_disable_new_user_notifications() {
    //Remove original use created emails
    remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
    remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10, 2 );
    //Add new function to take over email creation
    add_action( 'register_new_user', 'smartwp_send_new_user_notifications' );
    add_action( 'edit_user_created_user', 'smartwp_send_new_user_notifications', 10, 2 );
}
function smartwp_send_new_user_notifications( $user_id, $notify = 'user' ) {
    if ( empty($notify) || $notify == 'admin' ) {
        return;
    }elseif( $notify == 'both' ){
        //Only send the new user their email, not the admin
        $notify = 'user';
    }
    wp_send_new_user_notifications( $user_id, $notify );
}
add_action( 'init', 'smartwp_disable_new_user_notifications' );