<?php
use SaltHareket\Theme;

class StarterSite extends Timber\Site{
    function __construct(){
        add_filter("timber/context", [$this, "add_to_context"]);
        add_filter("timber/twig", [$this, "add_to_twig"]);
        add_filter('timber/twig/filters', [$this, "timber_twig_filters"]);
        add_filter('timber/twig/functions', [$this, "timber_twig_functions"]);
        add_filter('timber/post/image_extensions', [$this, "timber_post_image_extensions"]);
        add_action("timber/output", [$this, "timber_output"], 2, 9999);
        parent::__construct();
        
    }

    function add_to_context($context){

        //$this->language_settings();

        $ajax_process = false;
        if (defined('DOING_AJAX') && DOING_AJAX){
            $ajax_process = true;
        }
        if (defined('DOING_CRON') && DOING_CRON){
            $ajax_process = true;
        }

        $salt = $GLOBALS["salt"];
        $user = $GLOBALS["user"];

        $context["block_appendix"] = array();

        // settings
        $context["enable_membership"] = boolval(ENABLE_MEMBERSHIP);
        $context["enable_membership_activation"] = boolval(ENABLE_MEMBERSHIP_ACTIVATION);
        $context["membership_activation_type"] = MEMBERSHIP_ACTIVATION_TYPE;
        $context["enable_favorites"] = boolval(ENABLE_FAVORITES);
        $context["favorite_types"] = FAVORITE_TYPES;
        $context["enable_follow"] = boolval(ENABLE_FOLLOW);
        $context["follow_types"] = FOLLOW_TYPES;
        $context["enable_cart"] = boolval(ENABLE_CART);
        $context["enable_filters"] = boolval(ENABLE_FILTERS);
        $context["enable_chat"] = boolval(ENABLE_CHAT);
        $context["enable_notifications"] = boolval(ENABLE_NOTIFICATIONS);
        $context["enable_sms_notifications"] = boolval(ENABLE_NOTIFICATIONS) && boolval(ENABLE_SMS_NOTIFICATIONS);
        $context["enable_search_history"] = boolval(ENABLE_SEARCH_HISTORY);
        $context["disable_review_approve"] = boolval(DISABLE_REVIEW_APPROVE);
        $context["enable_postcode_validation"] = boolval(ENABLE_POSTCODE_VALIDATION);
        $context["enable_regional_posts"] = boolval(ENABLE_REGIONAL_POSTS);
        $context["activate_under_construction"] = boolval(ACTIVATE_UNDER_CONSTRUCTION);
        if(!defined("VISIBILITY_UNDER_CONSTRUCTION")){
            visibility_under_construction();
        }
        $context["visibility_under_construction"] = boolval(VISIBILITY_UNDER_CONSTRUCTION);

        $context["enable_ecommerce"] = boolval(ENABLE_ECOMMERCE);

        $context["enable_registration"] = boolval(ENABLE_REGISTRATION);
        $context["enable_remember_login"] = boolval(ENABLE_REMEMBER_LOGIN);
        $context["enable_lost_password"] = boolval(ENABLE_LOST_PASSWORD);
        $context["enable_password_recover"] = boolval(ENABLE_PASSWORD_RECOVER);
        $context["enable_social_login"] = boolval(ENABLE_SOCIAL_LOGIN);

        $context["seperate_css"] = boolval(SEPERATE_CSS);
        $context["seperate_js"]  = boolval(SEPERATE_JS);

        // domain
        $context["text_domain"] = TEXT_DOMAIN;
        
        //menus
        $menus = array();
        foreach (array_keys(get_registered_nav_menus()) as $location) {
            if (!has_nav_menu($location)) {
                continue;
            }
            $menus[$location] = \Timber::get_menu($location);
        }
        //print_r($menus);
        $context["menu"] = $menus;

        $upload_dir = wp_upload_dir();
        $this->upload_url = $upload_dir['baseurl']."/";

        // meta
        $context["site"] = $this;
        $context["is_home"] = boolval(is_front_page());
        $context["home_title"] = get_the_title(get_option("page_on_front"));
        $context["current_page_type"] = get_current_page_type();
        $context["page_type"] = get_page_type();
        
        // contacts
        if(function_exists("acf_get_contacts")){
            $contact_main = acf_get_contacts("main");
            $context["contact"] = $contact_main;
            $context["accounts"] = acf_get_accounts($contact_main);             
        }
           
        // logo
        $logo = SaltBase::get_cached_option("logo");//get_field("logo", "option");
        $context["logo"] = $logo;

        $logo_affix = SaltBase::get_cached_option("logo_affix");//get_field("logo_affix", "option");
        $context["logo_affix"] = $logo_affix;

        $logo_mobile = SaltBase::get_cached_option("logo_mobile");//get_field("logo_mobile", "option");
        $context["logo_mobile"] = $logo_mobile;

        $logo_footer = SaltBase::get_cached_option("logo_footer");//get_field("logo_footer", "option");
        $context["logo_footer"] = $logo_footer;

        $logo_icon = SaltBase::get_cached_option("logo_icon");//get_field("logo_icon", "option");
        $context["logo_icon"] = $logo_icon;
        
        if(!is_admin() && class_exists("ACF") && !$ajax_process){
            $header_footer_options = header_footer_options();
            $context["header_options"] = $header_footer_options["header"];
            $context["footer_options"] = $header_footer_options["footer"];
            $context["theme_styles"]   = acf_get_theme_styles();//get_field("theme_styles", "option");
        }
        
        if(!isset($GLOBALS["site_config"])){
           $GLOBALS["site_config"] = SaltHareket\Theme::get_site_config();
        }
        $context["site_config"] = $GLOBALS["site_config"];
            
        if (ENABLE_FAVORITES) {
            $context["favorites"] = $GLOBALS["favorites"];
        }

        if (ENABLE_SEARCH_HISTORY) {
            $context["search_history"] = $GLOBALS["search_history"];
        }

        if (class_exists("WPCF7")) {
            $context["forms"] = get_cf7_forms();
        }

        $context["woocommerce"] = 0;
        if (class_exists("WooCommerce")) {
            $context["woocommerce"] = 1;
        }

        if (ENABLE_MEMBERSHIP) {
            if (is_user_logged_in()) {
                $context["avatar"] = $user->get_avatar(); //$avatar;
                $context["avatar_url"] = str_replace(
                    "wp_user_avatar",
                    "mm",
                    $user->get_avatar_url()
                );
                //$context["main"] = new TimberMenu($user->role);
            }
        }

        $context["language"] = $GLOBALS["language"];
        //print_r($GLOBALS["language"]);
        if(ENABLE_MULTILANGUAGE){
            $context["languages"] = $GLOBALS["languages"];
            $context["language_default"] = $GLOBALS["language_default"];
            if(ENABLE_MULTILANGUAGE == "polylang"){
                $context["site"]->url = pll_home_url($GLOBALS["language"]);
            }
        }


        if(!$ajax_process){

            if (function_exists("yoast_breadcrumb") && get_option('wpseo_titles')['breadcrumbs-enable']){// && !is_front_page()) {
                $breadcrumb = WPSEO_Breadcrumbs::breadcrumb("" , "", 0);
                if($breadcrumb){
                    $breadcrumb = yoast_breadcrumb(
                        '<div class="breadcrumb-container {{breadcrumb_count}} {{class}}">',
                        "{{breadcrumb_h1}}</div>",
                        1
                    );
                    if(!isset($GLOBALS["breadcrumb_h1"])){
                        $GLOBALS["breadcrumb_h1"] = "";
                    }
                    if(!isset($GLOBALS["breadcrumb_count"] )){
                        $GLOBALS["breadcrumb_count"] = 0;
                    }
                    $breadcrumb = str_replace("{{breadcrumb_count}}", $GLOBALS["breadcrumb_count"]>0?"has-items":"", $breadcrumb);
                    $breadcrumb = str_replace("{{breadcrumb_h1}}", $GLOBALS["breadcrumb_h1"], $breadcrumb);
                }else{
                    if(isset($GLOBALS["breadcrumb_h1"])){
                       $breadcrumb = '<div class="breadcrumb-container">'.$GLOBALS["breadcrumb_h1"]."</div>";
                    }
                }
                $context["breadcrumb"] = $breadcrumb;
            }

            if (isset($GLOBALS["url_query_vars"])) {
                $context["url_query_vars"] = [];
                foreach ($GLOBALS["url_query_vars"] as $var) {
                    $context["url_query_vars"][$var] = get_query_var($var);
                }
            }
            $context["querystring"] = json_decode(queryStringJSON(), true);
            $context["endpoint"] = getUrlEndpoint();
            $context["url_parts"] = getUrlParts();
            $context["base_urls"] = isset($GLOBALS["base_urls"])?$GLOBALS["base_urls"]:[];
            $context["breakpoints"] = isset($GLOBALS["breakpoints"])?array_keys($GLOBALS["breakpoints"]):[];

            if (ENABLE_POSTCODE_VALIDATION) {
                $postcodes = json_decode(file_get_contents(SH_STATIC_PATH ."data/postcodes.json"), true);
                $context["postcodes"] = $postcodes;
            }

            global $post;
            if(ENABLE_ECOMMERCE){
                $woocommerce_shop_page_display = get_option("woocommerce_shop_page_display");
                if((is_shop() || is_product_category() || is_tax())) {
                   $post = get_post(get_option( 'woocommerce_shop_page_id' ));
                }
            }

            $post_under_construction = false;
            if(ACTIVATE_UNDER_CONSTRUCTION && !is_user_logged_in() && $user->role != "administrator"){
                if(isset($post->ID) && in_array($post->ID, WHITE_PAGES_UNDER_CONSTRUCTION)){
                    $post = Timber::get_post($post);//new Timber\Post($post);     
                }else{
                    $post_under_construction = true;
                    $post = Timber::get_post(intval(get_option('under-construction-page')));//Timber::get_post_by("slug", "under-construction");//new Timber\Post("under-construction");
                }
            }else{
                $post = Timber::get_post($post);//new Timber\Post($post);        
            }
            if(is_home() && !$post_under_construction){
                $post = Timber::get_post(get_option( 'page_for_posts' ));
            }

            $page_settings = array();
            if($post){
                if($post->post_type == "page"){
                    if($post->meta("custom_settings")){
                        $page_settings = $post->meta("page_settings");
                        if($page_settings){
                            $page_settings["classes"]["body"] = implode(" ", $page_settings["classes"]["body"] );
                            $page_settings["classes"]["main"] = implode(" ", $page_settings["classes"]["main"] );
                            $page_settings["classes"]["container"] = block_container($page_settings["classes"]["container"]);
                            if($page_settings["add_offcanvas"]){
                                $page_settings["offcanvas"]["id"] = $post->ID;
                                if(!empty($page_settings["offcanvas"]["filter_preset"])){
                                    $preset_id = slug2Id($page_settings["offcanvas"]["filter_preset"]);
                                    $preset_layout = get_post_meta($preset_id, "_layout", true);
                                    $page_settings["offcanvas"]["layout"] = $preset_layout;
                                }
                           }                        
                       }
                       $context["page_settings"] = $page_settings;
                    }
                }else{
                    if(is_post_type_archive()){
                        $custom_settings = get_field('custom_settings', $post->post_type.'_options');
                        if($custom_settings){
                           $page_settings = get_field('page_settings', $post->post_type.'_options');
                           $page_settings["classes"]["body"] = implode(" ", $page_settings["classes"]["body"] );
                           $page_settings["classes"]["main"] = implode(" ", $page_settings["classes"]["main"] );
                           $page_settings["classes"]["container"] = block_container($page_settings["classes"]["container"]);
                           if($page_settings["add_offcanvas"] && !$page_settings["offcanvas"]["archive"]){
                              unset($page_settings["offcanvas"]);
                           }
                           $context["page_settings"] = $page_settings;
                        }
                    }
                    if(is_single()){
                        if($post->meta("custom_settings")){
                           $page_settings = $post->meta("page_settings");
                           $page_settings["classes"]["body"] = implode(" ", $page_settings["classes"]["body"] );
                           $page_settings["classes"]["main"] = implode(" ", $page_settings["classes"]["main"] );
                           $page_settings["classes"]["container"] = block_container($page_settings["classes"]["container"]);
                           if($page_settings["add_offcanvas"] && $page_settings["offcanvas"]["single"]){
                              $context["page_settings_offcanvas"] =  array("add_offcanvas" => true, "offcanvas" => $page_settings["offcanvas"]);
                           }
                           $context["page_settings"] = $page_settings;
                        }
                    }
                    if(is_tag() || is_category()){
                        $prefix = is_tag()?"tax_post_tag":"tax_category";
                        $custom_settings = get_field('custom_settings',  $prefix.'_options');
                        if($custom_settings){
                            $page_settings = get_field('page_settings',  $prefix.'_options');
                            $page_settings["classes"]["body"] = implode(" ", $page_settings["classes"]["body"] );
                            $page_settings["classes"]["main"] = implode(" ", $page_settings["classes"]["main"] );
                            $page_settings["classes"]["container"] = block_container($page_settings["classes"]["container"]);
                            if($page_settings["add_offcanvas"]){
                                $page_settings["offcanvas"]["id"] = $post->ID;
                                if(!empty($page_settings["offcanvas"]["filter_preset"])){
                                   $preset_id = slug2Id($page_settings["offcanvas"]["filter_preset"]);
                                   $preset_layout = get_post_meta($preset_id, "_layout", true);
                                   $page_settings["offcanvas"]["layout"] = $preset_layout;
                                   $page_settings["offcanvas"]["id"] = get_queried_object_id();
                                }
                           }
                           $context["page_settings"] = $page_settings;
                        }
                    }
                    if(is_tax() || is_product_tag()){
                        global $wp_query;
                        $query_vars = $wp_query->query_vars;
                        if(array_key_exists("taxonomy", $query_vars)){
                           $taxonomy = $query_vars['taxonomy'];
                        }
                        $custom_settings = get_field('custom_settings',  'tax_'.$taxonomy.'_options');
                        if($custom_settings){
                            $page_settings = get_field('page_settings',  'tax_'.$taxonomy.'_options');
                            $page_settings["classes"]["body"] = implode(" ", $page_settings["classes"]["body"] );
                            $page_settings["classes"]["main"] = implode(" ", $page_settings["classes"]["main"] );
                            $page_settings["classes"]["container"] = block_container($page_settings["classes"]["container"]);
                            if($page_settings["add_offcanvas"]){
                                $page_settings["offcanvas"]["id"] = $post->ID;
                                if(!empty($page_settings["offcanvas"]["filter_preset"])){
                                   $preset_id = slug2Id($page_settings["offcanvas"]["filter_preset"]);
                                   $preset_layout = get_post_meta($preset_id, "_layout", true);
                                   $page_settings["offcanvas"]["layout"] = $preset_layout;
                                   $page_settings["offcanvas"]["id"] = get_queried_object_id();
                                }
                           }
                           $context["page_settings"] = $page_settings;
                        }
                    }
                }            
            }

            $current_url = current_url();
            global $wp;
            $query_string = add_query_arg( array(), $wp->request );
            if($query_string){
                if(strpos($query_string, "page/") > -1){
                    $query_string = explode("page/", $query_string);
                    $query_string = "page/".$query_string[1];
                    $current_url = str_replace($query_string."/", "", $current_url);
                }            
            }
            $context["current_url"] = $current_url;


            global $wp_query;
            $paged = 1;
            if(isset($wp_query->query_vars["paged"])){
                $paged = intval($wp_query->query_vars["paged"]);
            }
            $paged = $paged<1?1:$paged;
            $context['paged'] = $paged;

            // create salt key for pagination args and request
            $pagination_query = pagination_query();
            $context["query_pagination_vars"] = $pagination_query["vars"];
            $context["query_pagination_request"] = $pagination_query["request"];

            $block_search_results_posts_per_page = 10;
            
            // check blocks
            if(isset($page_settings["login_required"]) && $page_settings["login_required"] && (!$user->logged || (is_array($page_settings["allowed_roles"]) && !in_array($user->get_role(), $page_settings["allowed_roles"]) ))){

               // login required & blocks not render

            }else{
     
                if ((is_single() || is_page() || is_home() || is_tag() || is_category()) && !$ajax_process ) {

                    $hero_title = "";
                    $block_hero = "";
                    $block_id = "";
                    $GLOBALS["block_index"] = -1;

                    // Use posts page if posts page is defined
                    $post_blocks = array();
                    if(is_tag() || is_category()){
                        $posts_page_id = intval(get_option('page_for_posts'));
                        if($posts_page_id){
                            $post_blocks = get_blocks($posts_page_id);
                            $context["page"] = Timber::get_post($posts_page_id);
                            $hero_title = single_tag_title('', false);
                        }
                    }else{
                        if(isset($post->ID)){
                            $post_blocks = get_blocks($post->ID);
                        }
                    }

                    if($post_blocks){
                        foreach ( $post_blocks as $key => $block ) {
                            if ( isset( $block['attrs']['data']["block_settings_hero"] )) {
                                if ( $block['attrs']['data']["block_settings_hero"] == 1) {
                                    if(!empty($hero_title)){
                                        $block['attrs']["data"]["title"] = $hero_title;
                                    }
                                    if(isset($context["breadcrumb"])){
                                        $block['attrs']["data"]["breadcrumb"] = $context["breadcrumb"];
                                    }
                                    $block['attrs']["id"] = "block_hero";
                                    $block_hero = $block;
                                    $GLOBALS["block_index"] = $key;
                                    break;
                                }
                            }
                        }
                        foreach ( $post_blocks as $key => $block ) {
                            if($block["blockName"] == "acf/search-results"){
                               if ( isset( $block['attrs']['data']["posts_per_page"] )) {
                                   $block_search_results_posts_per_page = $block['attrs']['data']["posts_per_page"];
                                   break;
                               }
                            }
                        }
                    }
                    if(!empty($block_hero)){
                        $context["block_hero"] = $block_hero;//render_block($block_hero);
                    }
                } 
         
            }

            if(!empty(get_query_var("q")) || is_tag() || is_category() || is_home()){
                if(is_tag()){
                    $context['title'] = sprintf( trans('Posts tagged as %s'), '<strong>"'.single_tag_title('', false).'"</strong>' );
                }
                if(is_category()){
                    $context['title'] = sprintf( trans('Posts in %s category'), '<strong>"'.get_queried_object()->name.'"</strong>' );
                }
                if(!empty(get_query_var("q"))){
                    $context['title'] = sprintf( trans('%s için arama sonuçları'), '<strong>"'.get_query_var("q").'"</strong>' );
                }

                $found_posts = 0;

                if(!empty(get_query_var("q"))){

                    $qpt = get_query_var("qpt");
                    $qpt = empty($qpt)||$qpt=="search"||is_numeric($qpt)?"any":$qpt;
                    if (EXCLUDE_FROM_SEARCH && $qpt == "any") {
                        $post_types = get_post_types(['public' => true], 'names');
                        foreach (EXCLUDE_FROM_SEARCH as $post_type) {
                            if (in_array($post_type, $post_types)) {
                                unset($post_types[$post_type]);
                            }
                        }
                        $qpt = ($post_types);
                    }
                    $args = array(
                        'post_type' => $qpt,//$wp_query->query_vars["post_type"],
                        's' => urldecode(get_query_var("q")),
                        'paged' => $paged,
                        "posts_per_page" => $block_search_results_posts_per_page,
                    );
                    $posts = SaltBase::get_cached_query($args);
                    $posts = Timber::get_posts($args);
                    $context['posts'] = $posts;
                    $found_posts = $posts->found_posts;
                }
                $context['found_posts'] = $found_posts;
            }

        }

        $context["post_pagination"] = $GLOBALS["post_pagination"];

        if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
            error_log("add_to_context on startsite.php site_assets var");
            if(!empty(SITE_ASSETS["js"])){
                $js_data = SITE_ASSETS["js"];
                $code = str_replace("{upload_url}", $this->upload_url, $js_data);
                $code = str_replace("{home_url}", home_url("/"), $code);
                $context["site_assets_js"] = $code;
            }

            
            $lcp_data = SITE_ASSETS["lcp"];
            $lcp_images = [];
            if(isset($lcp_data["desktop"]["type"]) && $lcp_data["desktop"]["type"] == "preload" && !empty($lcp_data["desktop"]["id"])){
                //preg_match('/href="([^"]+)"/', SITE_ASSETS["desktop"]["code"], $matches);
                //if (isset($matches[1])) {
                    $lcp_images[] = $lcp_data["desktop"]["id"];//$matches[1];
                //}
            }
            if(isset($lcp_data["mobile"]["type"]) && $lcp_data["mobile"]["type"] == "preload" && !empty($lcp_data["mobile"]["id"])){
                //preg_match('/href="([^"]+)"/', SITE_ASSETS["mobile"]["code"], $matches);
                //if (isset($matches[1])) {
                    $lcp_images[] = $lcp_data["mobile"]["id"];//$matches[1];
                //}
            }
            $context["lcp_images"] = $lcp_images;
        }

        $plugins_file = get_stylesheet_directory() ."/static/js/js_files_all.json";
        if(file_exists($plugins_file)){
            $plugins = file_get_contents($plugins_file);
            $plugins = json_encode($plugins, true);
        }else{
            $plugins = [];
        }
        $context["all_js"] = $plugins;

        $context["fetch"] = isset($_GET["fetch"])?true:false;

        $context["salt"] = $salt;
        $context["user"] = $user;

        return $context;
    }

    function add_to_twig($twig){
        $twig->addExtension(new  \App\Twig\AppExtension());
        return $twig;
    }

    function timber_output($output, $data, $file){
        // wrap tease posts with col class
        if(strpos($file, "tease")>-1){
            $folder = explode("/", $file);
            if($folder){
                $folder = $folder[0];
                // woocommerce akrif ve urun tease template ise col ekelem, cunku default olarak var.
                if(!(ENABLE_ECOMMERCE && $folder == "product")){ 
                    $post_types = $data["post_pagination"];
                    if($post_types){
                        $post_types = array_keys($post_types);
                        if(in_array($folder, $post_types)){
                            $page = "";
                            if(isset($GLOBALS["pagination_page"]) && !empty($GLOBALS["pagination_page"])){
                                $page = "data-page='".$GLOBALS["pagination_page"]."'";
                            }
                            $output = "<div class='col' ".$page.">".$output."</div>";
                            //$parser = \WyriHaximus\HtmlCompress\Factory::construct();
                            $parser = \WyriHaximus\HtmlCompress\Factory::constructFastest();
                            $output = $parser->compress($output);
                        }
                    }                
                }
            }
        }
        return $output;
    }
    
    function timber_twig_filters($filters) {
        $default = [
                "trans",
                "trans_arr",
                "printf_array",
                "trans_plural",
                "trans_static",
                "uppertr",
                "get_option",
                "phone_link",
                "email_link",
                "url_link",
                "list_social_accounts",
                "json_decode",
                "woo_product",
                "wrap_last",
                "variation_url_rewrite",
                "_addClass",
                "array2List",
                "class_salt",
                "ucwords",
                "array_find_replace",
                "str_replace_arr",
                "br2Nl",
                "secure_string",
                "pluralize",
                "html_entity_decode",
                "boolval",
                "inline_svg",
                "is_current_url",
                "boolstr",
                "acf_get_contacts",
                "acf_layouts_container",
                "acf_layouts_row",
                "array2Attrs",
                "masked_text",
                "acf_dynamic_container",
                "array_merge_recursive_items",
                "getMonthName",
                "translateContent",
                "unit_value",
                "urldecode",
                "hex2rgbValues",
                "json_attr"
        ];
        foreach ($default as $filter) {
            $filters[$filter] = [
                'callable' => $filter,
            ];
        }
        if(isset($GLOBALS["twig_filters"])){
            $twig_filters = $GLOBALS["twig_filters"];
            foreach ($twig_filters as $filter) {
                $filters[$filter] = [
                    'callable' => $filter,
                ];
            }        
        }
        return $filters;
    }

    function timber_twig_functions($functions) {
        $functions['offcanvas_toggler'] = [
            'callable' => 'get_offcanvas_toggler',
        ];
        $functions['img'] = [
            'callable' => 'get_image_set',
        ];
        $functions['video'] = [
            'callable' => 'get_video',
        ];
        $functions['get_post_by'] = [
            'callable' => 'Timber::get_post_by',
        ];
        $functions['translate'] = [
            'callable' => 'trans',
        ];
        $functions['translate_n_noop'] = [
            'callable' => 'trans_n_noop',
        ];
        $functions['remove_params'] = [
            'callable' => 'remove_params_from_url',
        ];
        $functions['get_title_from_url'] = [
            'callable' => 'get_title_from_url',
        ];
        $functions['get_map_config'] = [
            'callable' => 'get_map_config',
        ];
        $functions['lightGallerySource'] = [
            'callable' => 'lightGallerySource',
        ];
        $functions['get_page'] = [
            'callable' => '_get_page',
        ];
        
        if(isset($GLOBALS["twig_functions"])){
            $twig_functions = $GLOBALS["twig_functions"];
            foreach ($twig_functions as $key => $function) {
                $functions[$key] = [
                    'callable' => $function,
                ];
            }        
        }
        return $functions;
    }

    function timber_post_image_extensions($extensions) {
        if(isset($GLOBALS["upload_mimes"])){
            foreach($GLOBALS["upload_mimes"] as $key => $mime){
                $extensions[] = $key;
            }
        }
        return $extensions;
    }
}