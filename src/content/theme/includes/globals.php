<?php

// otomatik olarak download edilip yüklenilmesi istenen pluginler burada tanımlanacak.
/*  Ornek: 
    $plugins = array(
       "contact-form-7/wp-contact-form-7.php",
       "acf-extended/acf-extended.php"
    );
*/

// Download yapılmasına izin verilen dosya türleri
add_filter("download_allowed_file_types", function($types=array()){
    $types[] = "pdf";
    return $types;
});

$breakpoints = array(
        'xs' => 575,
        'sm' => 576,
        'md' => 768,
        'lg' => 992,
        'xl' => 1200,
        'xxl' => 1400,
        'xxxl' => 1600
);
if($breakpoints){
    Data::merge("breakpoints", $breakpoints);
}

if(Data::get("plugins")){
    $required_plugins = array(
        // set plugins in here
    );
    if($required_plugins){
        Data::merge("plugins", $required_plugins);
    }
}

// Upload için izin verilen dosya tipleri
$upload_mimes = array(
        'svg'  => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'webp' => 'image/webp',
        'php'  => 'text/html',
        'apk'  => 'application/vnd.android.package-archive',
        'avif' => 'image/avif'
);
Data::merge("upload_mimes", $upload_mimes);

add_filter("init", function(){

    //redirects
    $woo_redirect_empty_cart = "";
    if($woo_redirect_empty_cart){
        Data::aet("woo_redirect_empty_cart", $woo_redirect_empty_cart);
    }
    $woo_redirect_not_logged = get_account_endpoint_url('my-account');
    if($woo_redirect_not_logged){
        Data::set("woo_redirect_not_logged", $woo_redirect_not_logged);
    }

    // Üyelik oluştururken kullanılabilecek rollerin tanımlanması
    $membership_roles = array();
    if($membership_roles){
        Data::merge("membership_roles", $membership_roles);
    }

    // Notification ile ilişkili post_type listesi
    $notification_post_types = array();
    if($notification_post_types){
        Data::extend("notification_post_types", $notification_post_types);
    }

    // Yeni block kategorilerinin tanımlanması
    $block_categories = array(
        array(
            'slug'  => TEXT_DOMAIN,
            'title' => esc_html( ucwords(str_replace('-', ' ', TEXT_DOMAIN)) ) . ' Blocks',
            'icon'  => 'dashicons-admin-site' // Dashicon simgesi
        )
    );
    if($block_categories){
        Data::extend("block_categories", $block_categories);
    }

    // Classic editor içine UI elemanları tanımlanması
    /*$mce_styles = array(
            array(  
                'title' => 'List Unstyled',  
                'selector' => 'ul, ol',  
                'classes' => 'list-unstyled ms-4'             
            ),
    );*/
    $mce_styles = array();
    if($mce_styles){
        Data::extend("mce_styles", $mce_styles);
    }

    $colors_mce = [];
    $colors_mce_file = get_template_directory() . '/theme/static/data/colors_mce.json';
    if(file_exists($colors_mce_file)){
        $colors_mce = file_get_contents($colors_mce_file);
        $colors_mce = json_decode($colors_mce, true);    
    }
    $mce_text_colors = $colors_mce;
    /*array(
      //'#fff' => 'Color Name'
    );*/
    if($mce_text_colors){
        Data::extend("mce_text_colors", $mce_text_colors);
    }


    // Bir post_type için yapılan görsel yüklemelerinin boyutlandırılması
    $upload_resize = array(
        /*'product'  => array(
            "width" => 780,
            "height" => 1200,
            "crop" => true,
            "compression" => 70
        )*/
    );
    if($upload_resize){
        Data::merge("upload_resize", $upload_resize);
    }

    // Tanımlı olan görsel boyutlarının kaldırılması
    $upload_sizes_remove = array(
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
    if($upload_sizes_remove){
        Data::merge("upload_sizes_remove", $upload_sizes_remove);
    }

    // Yeni görsel boyutlarının tanımlanması
    $upload_sizes_add = array(
        /*'sm' => 576,
        'md' => 768,
        'lg' => 992,
        'xl' => 1200,
        'xxl' => 1400,
        'xxxl' => 1600*/
    );
    if($upload_sizes_add){
        Data::merge("upload_sizes_add", $upload_sizes_add);
    }





    $twig_filters = [];
    if($twig_filters){
        Data::merge("twig_filters", $twig_filters);
    }

    $twig_functions = [];
    if($twig_functions){
        Data::merge("twig_functions", $twig_functions);
    }

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
    if($url_query_vars){
        Data::merge("url_query_vars", $url_query_vars);
    }

    $templates = array(
        "favorites" => array(
            "user" => array(
                "archive" => "my-account/my-favorites.twig",
                "tease"   => "users/tease-md-twig"
            )
        )
    );
    if($templates){
        Data::merge("templates", $templates);
    }

    $base_urls = [
        "profile" => get_account_endpoint_url("profile"),
        "account" => get_permalink(get_option("woocommerce_myaccount_page_id")),//get_permalink( get_page_by_path( "my-account" ) ),
        "logged_url" => home_url(),
    ];
    if($base_urls){
        Data::merge("base_urls", $base_urls);
    }


    $my_account_links = array(
       "profile" => array(
            "roles" => array(),
            "title" => trans("Your account is not activated"),
            "description" => trans("Your account is not activated"),
            "menu" => "Profile"
        ),
    );
    if($my_account_links){
        Data::merge("my_account_links", $my_account_links);
    }

    $sitemap_exclude_post_ids = array();
    if($sitemap_exclude_post_ids){
        Data::merge("sitemap_exclude_post_ids", $sitemap_exclude_post_ids);
    }

    $sitemap_exclude_term_ids = array();
    if($sitemap_exclude_term_ids){
        Data::merge("sitemap_exclude_term_ids", $sitemap_exclude_term_ids);
    }

});

function add_to_twig_extras($context){
    return $context;
}
add_action("timber/context", "add_to_twig_extras", 9999);

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
            return Data::get("base_urls.reviews");
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




$custom_shortcodes = [];
if($custom_shortcodes){
        Data::merge("custom_shortcodes", $custom_shortcodes);
    }
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