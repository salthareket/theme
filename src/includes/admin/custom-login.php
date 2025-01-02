<?php // custom admin styling


function custom_login_logo_url() {
    return home_url(); // İsteğe bağlı olarak, logonun tıklanınca yönlendirileceği URL'yi belirleyebilirsiniz.
}

function custom_login_logo_url_title() {
    $admin_logo = get_field("admin_logo", "option");
    if($admin_logo){
        $admin_logo = '<img src="'.$admin_logo.'" class="img-fluid"">';
    }else{
        $admin_logo = '<img src="'. SH_STATIC_URL .'img/logo-login.png" class="img-fluid">';
    }
    return $admin_logo;
}
//add_action('login_head', 'custom_login_logo');
add_filter('login_headerurl', 'custom_login_logo_url');
add_filter('login_headertext', 'custom_login_logo_url_title');

function login_styles() {
    $admin_bg = get_field("admin_bg", "option");

    $bg_color = "#111";
    if(isset($admin_bg["color"]) && !empty($admin_bg["color"])){
        $bg_color = $admin_bg["color"];
    }

    $bg_image = SH_STATIC_URL .'img/bg-login-admin.jpg';
    if(isset($admin_bg["image"]) && !empty($admin_bg["image"])){
        $bg_image = $admin_bg["image"];
    }

    $bg_position_hr = "center";
    if(isset($admin_bg["position_hr"]) && !empty($admin_bg["position_hr"])){
        $bg_position_hr = $admin_bg["position_hr"];
    }
    $bg_position_vr = "center";
    if(isset($admin_bg["position_vr"]) && !empty($admin_bg["position_vr"])){
        $bg_position_vr = $admin_bg["position_vr"];
    }
    $bg_position = $bg_position_hr." ".$bg_position_vr;

    $bg_size = "auto";
    if(isset($admin_bg["size"]) && !empty($admin_bg["size"])){
        $bg_size = $admin_bg["size"];
    }

    $bg_repeat = "no-repeat";
    if(isset($admin_bg["repeat"]) && !empty($admin_bg["repeat"])){
        $bg_repeat = $admin_bg["repeat"];
    }


    $admin_text = get_field("admin_text", "option");

    $color = "#fff";
    if(isset($admin_text["color"]) && !empty($admin_text["color"])){
        $color = $admin_text["color"];
    }



    $admin_link = get_field("admin_link", "option");

    $link_color = "#fff";
    if(isset($admin_link["color"]) && !empty($admin_link["color"])){
        $link_color = $admin_link["color"];
    }
    $link_color_hover = "#fff";
    if(isset($admin_link["color_hover"]) && !empty($admin_link["color_hover"])){
        $link_color_hover = $admin_link["color_hover"];
    }



    $admin_button = get_field("admin_button", "option");

    $button_color = "#111";
    if(isset($admin_button["color"]) && !empty($admin_button["color"])){
        $button_color = $admin_button["color"];
    }
    $button_color_hover = "#111";
    if(isset($admin_button["color_hover"]) && !empty($admin_button["color_hover"])){
        $button_color_hover = $admin_button["color_hover"];
    }

    $button_bg = "yellow";
    if(isset($admin_button["bg"]) && !empty($admin_button["bg"])){
        $button_bg = $admin_button["bg"];
    }
    $button_bg_hover = "yellow";
    if(isset($admin_button["bg_hover"]) && !empty($admin_button["bg_hover"])){
        $button_bg_hover = $admin_button["bg_hover"];
    }

    $button_border = "#111";
    if(isset($admin_button["border"]) && !empty($admin_button["border"])){
        $button_border = $admin_button["border"];
    }
    $button_border_hover = "#111";
    if(isset($admin_button["border_hover"]) && !empty($admin_button["border_hover"])){
        $button_border_hover = $admin_button["border_hover"];
    }


    echo '<link rel="stylesheet" id="custom-admin-styles" href="'. get_bloginfo("template_directory") .'/static/css/admin-login.css" type="text/css" media="all">';
    echo "<style>body.login {
      background-color:  $bg_color!important;
      background-image: url($bg_image)!important;
      background-position: $bg_position!important;
      background-size: $bg_size!important;
      background-repeat: $bg_repeat!important;
      display:flex;
      justify-content:center;
      align-items:center;
      flex-direction:column;
    }
    .login form label,
    .login h1.admin-email__heading,
    p.admin-email__details,
    .login .description {
        color: $color!important;
        border-color: $button_border!important;
    }

    .login #nav a, 
    .login #backtoblog a{
        color: $link_color!important;
    }
    .login #nav a:hover, 
    .login #backtoblog a:hover{
        color: $link_color_hover!important;
    }

    .button:not(.button-secondary){
        background-color:$button_bg!important;
        border-color: $button_border!important;
        color: $button_color!important;
    }
    .button.button-primary{
        background-color:$button_bg!important;
        border-color: $button_border!important;
        color: $button_color!important;
    }
    .button.button-primary:hover{
        background-color:$button_bg_hover!important;
        border-color: $button_border_hover!important;
        color: $button_color_hover!important;
    }
    .button:disabled{
        opacity:.5;
        pointer-events:none;
    }
</style>";
}
add_action('login_head', 'login_styles');

// removing adminbuttons
function remove_menus () {
global $menu;
    $restricted = array(__('Links'), __('Comments')); // for extra removal use: $restricted = array(__('Links'), __('Media'), __('etc etc'));
    end ($menu);
    while (prev($menu)){
      $value = explode(' ',$menu[key($menu)][0]);
      if(in_array($value[0] != NULL?$value[0]:"" , $restricted)){unset($menu[key($menu)]);}
    }
}
add_action('admin_menu', 'remove_menus');

// wrapping div around oembed for responsiveness
add_filter('embed_oembed_html', 'my_embed_oembed_html', 99, 4);
function my_embed_oembed_html($html, $url, $attr, $post_id) {
  return '<div class="video-wrapper"><div class="video-wrap">' . $html . '</div></div>';
}

// Attach a class to linked images' parent anchors e.g. a img => a.fancy img
add_filter('the_content', 'addlightboxrel_replace');
function addlightboxrel_replace ($content)
{	global $post;
	$pattern = "/<a(.*?)href=('|\")(.*?).(bmp|gif|jpeg|jpg|png)('|\")(.*?)>/i";
  	$replacement = '<a$1class="fancy" href=$2$3.$4$5$6</a>';
    $content = preg_replace($pattern, $replacement, $content);
    return $content;
}

// remove wrapping <p> from images
function filter_ptags_on_images($content){
    return preg_replace('/<p>\\s*?(<a .*?><img.*?><\\/a>|<img.*?>)?\\s*<\\/p>/s', '\1', $content);
}
add_filter('the_content', 'filter_ptags_on_images');

// remove dashboardwidgets
function remove_dashboard_widgets() {
  remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
  remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
  remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
  remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
} 
add_action('wp_dashboard_setup', 'remove_dashboard_widgets' );

function remove_admin_bar_links() {
  global $wp_admin_bar;
  $wp_admin_bar->remove_menu('wp-logo');          // Remove the WordPress logo
  $wp_admin_bar->remove_menu('about');            // Remove the about WordPress link
  $wp_admin_bar->remove_menu('wporg');            // Remove the WordPress.org link
  $wp_admin_bar->remove_menu('documentation');    // Remove the WordPress documentation link
  $wp_admin_bar->remove_menu('support-forums');   // Remove the support forums link
  $wp_admin_bar->remove_menu('feedback');         // Remove the feedback link
  $wp_admin_bar->remove_menu('customize');        // Remove the customiser link
  //$wp_admin_bar->remove_menu('site-name');      // Remove the site name menu
  //$wp_admin_bar->remove_menu('view-site');      // Remove the view site link
  $wp_admin_bar->remove_menu('updates');          // Remove the updates link
  $wp_admin_bar->remove_menu('comments');         // Remove the comments link
  $wp_admin_bar->remove_menu('new-content');      // Remove the content link
  $wp_admin_bar->remove_menu('w3tc');             // If you use w3 total cache remove the performance link
  //$wp_admin_bar->remove_menu('my-account');     // Remove the user details tab
  $wp_admin_bar->remove_menu('wpseo-menu');       // Remove the Yoast SEO link
}
add_action( 'wp_before_admin_bar_render', 'remove_admin_bar_links' );

/*
// get the the role object
$role_object = get_role( 'editor' );

// add $cap capability to this role object
$role_object->add_cap( 'edit_theme_options' );
$role_object->remove_cap( 'edit_themes' );
*/

