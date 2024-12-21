<?php

// Sayfalardaki bazı gereksiz ve kullanılmayan bölümlerin kaldırılması
add_action( 'wp_loaded', function(){
    remove_action('wp_head', 'feed_links', 2); // Genel feed linkleri
    remove_action('wp_head', 'feed_links_extra', 3); // Ek feed linkleri (Kategori, Yazar, vb.)
    remove_action('wp_head', 'rsd_link'); // Really Simple Discovery (RSD) linki
    remove_action('wp_head', 'wlwmanifest_link'); // Windows Live Writer manifest linki
    remove_action('wp_head', 'wp_shortlink_wp_head'); // Kısa link (shortlink) linki
    remove_action('wp_head', 'wp_generator'); // WordPress sürüm bilgisi
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0); // Önceki ve sonraki yazı linkleri
    remove_action('wp_head', 'wp_oembed_add_discovery_links'); // OEmbed discovery linkleri
    remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

    // WordPress 5.4 ve sonraki sürümler için gizleme
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'wp_resource_hints', 2);

    if(ENABLE_ECOMMERCE){
        remove_action( 'wp_head', 'wc_generator_tag' ); // WooCommerce sürüm bilgisi
        remove_action( 'wp_head', 'wc_add_generator_meta_tag' ); // WooCommerce meta tag
        remove_action( 'wp_head', 'woocommerce_output_all_notices', 10 ); // WooCommerce hata mesajları
        remove_action( 'wp_head', 'wc_robots' ); // WooCommerce robots meta tag
        remove_action( 'wp_head', 'wc_oembed_add_admin_links' ); // WooCommerce oEmbed linkleri
        remove_action( 'wp_head', 'wc_oembed_add_discovery_links' ); // WooCommerce oEmbed discovery linkleri    
    }    
});





// otomatik olarak download edilip yüklenilmesi istenen pluginler burada tanımlanacak.
/*  Ornek: 
    $plugins = array(
       "contact-form-7/wp-contact-form-7.php",
       "acf-extended/acf-extended.php"
    );
*/
if(isset($GLOBALS["plugins"])){
    $required_plugins = array(
        // set plugins in here
    );
    if($required_plugins){
        $GLOBALS["plugins"] = array_merge($GLOBALS["plugins"], $required_plugins);
    }
}




// Üyelik oluştururken kullanılabilecek rollerin tanımlanması
$GLOBALS["membership_roles"] = array();


// Notification ile ilişkili post_type listesi
$GLOBALS["notification_post_types"] = array();



// Yeni block kategorilerinin tanımlanması
$GLOBALS['block_categories'] = array(
    array(
        'slug'  => sanitize_title( esc_html( get_bloginfo( 'name' ) ) ) . 'blocks',
        'title' => esc_html( get_bloginfo( 'name' ) ) . ' Blocks',
        'icon'  => 'dashicons-admin-site' // Dashicon simgesi
    )
);


$GLOBALS["breakpoints"] = array(
    'xs' => 575,
    'sm' => 576,
    'md' => 768,
    'lg' => 992,
    'xl' => 1200,
    'xxl' => 1400,
    'xxxl' => 1600
);


$GLOBALS["remove_global_styles"] = "auto"; // boolean || "auto" // block wp styles
$GLOBALS["remove_block_styles"] = "auto"; // boolean || "auto"
$GLOBALS["remove_classic_theme_styles"] = true;
$GLOBALS["remove_woocommerce_styles"] = true;


// Classic editor içine UI elemanları tanımlanması
/*$mce_styles = array(
        array(  
            'title' => 'List Unstyled',  
            'selector' => 'ul, ol',  
            'classes' => 'list-unstyled ms-4'             
        ),
);*/
$GLOBALS["mce_styles"] = array();

$colors_mce = [];
$colors_mce_file = get_template_directory() . '/theme/static/data/colors_mce.json';
if(file_exists($colors_mce_file)){
    $colors_mce = file_get_contents($colors_mce_file);
    $colors_mce = json_decode($colors_mce, true);    
}
$GLOBALS["mce_text_colors"] = $colors_mce;
/*array(
  //'#fff' => 'Color Name'
);*/




// Upload için izin verilen dosya tipleri
$GLOBALS["upload_mimes"] = array(
    'svg'  => 'image/svg+xml',
    'svgz' => 'image/svg+xml',
    'webp' => 'image/webp',
    'php'  => 'text/html',
    'apk'  => 'application/vnd.android.package-archive',
    'avif' => 'image/avif'
);



// Download yapılmasına izin verilen dosya türleri
add_filter("download_allowed_file_types", function($types=array()){
    $types[] = "pdf";
    return $types;
});


// Bir post_type için yapılan görsel yüklemelerinin boyutlandırılması
$GLOBALS["upload_resize"] = array(
    /*'product'  => array(
        "width" => 780,
        "height" => 1200,
        "crop" => true,
        "compression" => 70
    )*/
);

// Tanımlı olan görsel boyutlarının kaldırılması
$GLOBALS["upload_sizes_remove"] = array(
    /* Native sizes */
    '1536x1536',
    '2048x2048',
    
    /* WooCommerce Sizes */
    'woocommerce_thumbnail',
    'woocommerce_single',
    'woocommerce_gallery_thumbnail',
    'user-mini',
    'shop_thumbnail',
    'shop_catalog',
    'shop_single',

    /*'sm',
    'md',
    'lg',
    'xl',
    'xxl',
    'xxxl'*/
);

// Yeni görsel boyutlarının tanımlanması
$GLOBALS["upload_sizes_add"] = array(
    /*'sm' => 576,
    'md' => 768,
    'lg' => 992,
    'xl' => 1200,
    'xxl' => 1400,
    'xxxl' => 1600*/
);



/* iptal
Tanımlanan post type larına post eklerken wp nin 2560px olarak sınırladığı çözünürlüğü devre dışı bırakır ve post kaydedildikten sonra tekrar aktifleştirir.
$GLOBALS["upload_disable_max_resolution"] = array(
  "marka"
);*/





$GLOBALS["twig_filters"] = [];
$GLOBALS["twig_functions"] = [];

$url_query_vars = [
    "action",
    "template",
    "filters",
    "activation-code",
    "activation-email",
    "activation-password",
    "token",
    "api",
    "author_role",
    "sortby",
    "orderby",
    "attribute",
    "file_id",
    "q",
    "qpt",
    "qpt_settings",
    "post_type"
];
if (ENABLE_CHAT) {
    $url_query_vars[] = "chat";
    $url_query_vars[] = "conversationId";
    $url_query_vars[] = "convId";
}
if (ENABLE_FILTERS) {
    $url_query_filter_vars = [
   
    ];
    $url_query_vars = array_merge($url_query_vars, $url_query_filter_vars);
}
$GLOBALS["url_query_vars"] = $url_query_vars;

$GLOBALS["templates"] = array(
    "favorites" => array(
        "user" => array(
            "archive" => "my-account/my-favorites.twig",
            "tease"   => "users/tease-md-twig"
        )
    )
);

// fix breadcrumb for types
$GLOBALS["breadcrumb_taxonomy"] = array(
    //"dosya-tipi"
);
$GLOBALS["breadcrumb_post_type"] = array(
    /*"acik-pozisyonlar",
    "oduller",
    "sertifikalar",
    "yonetim-kurulu"*/
);

// set featured image these fields (if gallery: chosen first image)
$GLOBALS["acf_featured_image_fields"] = array(
    "gallery"
);


$GLOBALS["base_urls"] = [
    "profile" => get_account_endpoint_url("profile"),
    "account" => get_permalink( get_page_by_path( "my-account" ) ),
    "logged_url" => home_url(),
];



$my_account_links = array(
   "profile" => array(
        "roles" => array(),
        "title" => trans("Your account is not activated"),
        "description" => trans("Your account is not activated"),
        "menu" => "Profile"
    ), /**/
);
$GLOBALS["my_account_links"] = $my_account_links;




if (defined("WPSEO_FILE")) {
    /*remove "home page" from breadcrumb*/
    add_filter('wpseo_breadcrumb_links', 'remove_home_from_breadcrumb', 10, 1 ); 

    /*fix & add taxonomy hierarch breadcrumb*/
    //add_filter('wpseo_breadcrumb_links', 'fix_tax_hierarchy_on_breadcrumb', 10, 1 ); 

    /*remove current page/post from breadcrumb*/
    //add_filter('wpseo_breadcrumb_links', 'remove_current_from_breadcrumb', 10, 1 ); 

    //add_filter('wpseo_breadcrumb_links', 'change_shopping_link_on_breadcrumb', 10, 1 ); 

    //add_filter('wpseo_breadcrumb_links', 'fix_translate_on_breadcrumb', 10, 1 );

    //add_parents_to_post_breadcrumb
    //add_filter( 'wpseo_breadcrumb_links', 'add_parents_to_post_breadcrumb', 10, 1 );

    if ( class_exists( 'WooCommerce' ) ) {
        /*add "brand" to breadcrumb*/
        // add_filter('wpseo_breadcrumb_links', 'add_brand_to_breadcrumb', 10, 1 ); 
        /*add single product's category to breadcrumb*/
        //add_filter('wpseo_breadcrumb_links', 'add_category_to_breadcrumb', 10, 1 ); 
    }
}

$GLOBALS["sitemap_exclude_post_ids"] = array();

$GLOBALS["sitemap_exclude_term_ids"] = array();


function add_to_twig_extras($context){
    return $context;
}
add_action("timber/context", "add_to_twig_extras", 9999);



function timber_output($output, $data, $file){
    // wrap tease posts with col class
    if(strpos($file, "tease")>-1){
        $folder = explode("/", $file);
        if($folder){
            $folder = $folder[0];
            if($folder != "woo"){
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
add_action("timber/output", "timber_output", 2, 9999);


function notification_url_map($action, $post_id, $user_id){
    switch($action){
        case "new-request":
        case "new-session":
        case "payment-completed":
        case "approved-application":
        case "started-session":
            return get_permalink($post_id);
        break;
        case "new-follower":
            $user = new User($user_id);
            return $user->link;
        break;
        case "new-review":
        case "review-approved":
            return $GLOBALS["base_urls"]["reviews"];
        break;
    }
}
function notification_update_map($notification){
    $url     = wp_get_referer();
    $post_id = url_to_postid( $url ); 
    $post = get_post($post_id);
    if($notification["type"] == "message"){
        if($post->post_type == "session"){
            set_query_var( 'conversationId', $notification["id"] );
            return array(
                "container" => "#messages > .card-body:not(.started)",
                "html"      => "<iframe src='".home_url()."/chat-module/?conversationId=".$notification["id"]."&receiver=".$notification["sender"]["id"]."' id='chat-inbox' scrolling='no' frameborder='0' style='width: 1px;min-width: 100%;'></irame>"
            );
        }
    }
    /*if($notification["type"] == "notification"){
        if($post->post_type == "session"){
            return array(
                "container" => "#session-live > .card-body:not(.started)",
                "html"      => "<iframe src='".home_url()."/chat-module/?conversationId=".$notification["id"]."&receiver=".$notification["sender"]["id"]."' id='chat-inbox' scrolling='no' frameborder='0' style='width: 1px;min-width: 100%;'></irame>"
            );
        }
    }*/
}




$GLOBALS["custom_shortcodes"] = [];
/*
Example:
[
    [
        'name' => 'Search Field',
        'shortcode' => 'search_field',
        'callback' => function($atts) {
            $context = Timber::context();
            $context["atts"] = $atts;
            if (defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY) {
                $context["salt"] = $GLOBALS["salt"];
            }
            return Timber::compile("partials/snippets/search-field.twig", $context);
        },
        'atts' => [
            'size' => [
                'label' => 'Field Size',
                'ui' => 'select',
                'value' => [
                     'small' => "Small",
                     'large' => "Large"
                 ],
                'func' => ''
            ],
            'button' => [
                'label' => 'Button',
                'ui' => 'text',
                'value' => [],
                'func' => ''
            ],
            'roles' => [
                'label' => 'Role',
                'ui' => 'checkbox',
                'value' => [],
                'func' => 'user_roles'
            ]
        ]
    ]
];
*/