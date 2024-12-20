<?php

function add_theme_root_variable_to_head() {
    $theme_url = get_template_directory_uri();
    echo '<style>
        :root {
            --theme-url: ' . esc_url($theme_url) . ';
        }
    </style>';
}
add_action('wp_head', 'add_theme_root_variable_to_head', 1);


function dequeue_theme_styles() {
    wp_dequeue_style('theme-style'); // 'theme-style' yerine kendi temanızın style.css dosyasının kayıt adını kullanın
    wp_deregister_style('theme-style');
}

add_action('wp_enqueue_scripts', 'dequeue_theme_styles', 999);


function frontend_header_styles(){

	$css_path = get_stylesheet_directory() . '/static/css/main.css';
	$version = filemtime($css_path);

	global $post;

	// check blocks
	$content = get_post_field('post_content', get_the_ID());
    $has_core_blocks = false;
    if (has_blocks($content)) {
    	$blocks = parse_blocks($content);
    	if($blocks){
		    foreach ($blocks as $block) {
			    if(isset($block['blockName']) && !empty(isset($block['blockName']))){
				    if (strpos($block['blockName'], 'core/') !== false) {
				        $has_core_blocks = true;
				        continue;
				    }			        		
			    }
	        }    				
   		}
    }

	if(isset($post->post_type)){
    	if($post->post_type != "product"){
           wp_dequeue_style('woo-variation-swatches');
    	}
    }

    if(isset($GLOBALS["remove_global_styles"]) && !empty($GLOBALS["remove_global_styles"])){
    	if(($GLOBALS["remove_global_styles"] == "auto" && !$has_core_blocks) || (is_bool($GLOBALS["remove_global_styles"]) && $GLOBALS["remove_global_styles"])){
			wp_dequeue_style( 'global-styles-inline' );
       		wp_dequeue_style('global-styles');
    	}
    }

    if(isset($GLOBALS["remove_block_styles"]) && !empty($GLOBALS["remove_block_styles"])){
    	if(($GLOBALS["remove_block_styles"] == "auto" && !$has_core_blocks) || (is_bool($GLOBALS["remove_block_styles"]) && $GLOBALS["remove_block_styles"])){
		    wp_dequeue_style( 'wp-block-library' );
		    wp_dequeue_style( 'wc-blocks-style' ); 
    	}
    }

    if($GLOBALS["remove_classic_theme_styles"]){
    	//wp_deregister_style('classic-theme-styles-inline');
    	//wp_deregister_style('classic-theme-styles');
        wp_dequeue_style('classic-theme-styles-inline');
    	wp_dequeue_style('classic-theme-styles');
    	
    }
   
	if($GLOBALS["remove_woocommerce_styles"]){
		wp_dequeue_style('woocommerce-smallscreen');
	    wp_dequeue_style('woocommerce-inline');
	    wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-general');
	}

    wp_dequeue_style('ywdpd_owl');
    wp_dequeue_style('yith_ywdpd_frontend');
	
	wp_dequeue_style('toggle-switch');/**/
	wp_dequeue_style('font-awesome');
	wp_dequeue_style('font-for-body');
    wp_dequeue_style('font-for-new');
    wp_dequeue_style('google-fonts-roboto');
    
    //wp_register_style('fonts',  get_stylesheet_directory_uri() . '/static/css/fonts.css', array(), $version, '');


    //wp_register_style('icons',  get_stylesheet_directory_uri() . '/static/css/icons.css', array(), $version, '');
    //wp_register_style('icons-rtl',  get_stylesheet_directory_uri() . '/static/css/fontawesome-rtl.css', array(), $version, '');

    $locale_file_path = STATIC_PATH . 'css/locale-' . $GLOBALS['language'] . '.css';
	if (file_exists($locale_file_path) && is_readable($locale_file_path) && filesize($locale_file_path) > 0) {
	    wp_register_style('locale', STATIC_URL . 'css/locale-' . $GLOBALS['language'] . '.css' , array(), $version, '');
	}

    //wp_register_style('header', get_stylesheet_directory_uri() . '/static/css/header.css', array(), $version, '');
    //wp_register_style('header-rtl', get_stylesheet_directory_uri() . '/static/css/header-rtl.css', array(), $version, '');

    //$css_conditional = [];
	//$css_conditional_rtl = [];
	$plugin_css = false;
    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && !isset($_GET['fetch'])){
    	if(!empty(SITE_ASSETS["plugin_css"])){
    		wp_register_style('css-conditional', STATIC_URL . SITE_ASSETS["plugin_css"], array(), $version, '');
    		wp_register_style('css-conditional-rtl', STATIC_URL . SITE_ASSETS["plugin_css_rtl"], array(), $version, '');
    		$plugin_css = true;
    	}
    }

    //wp_register_style('main', get_stylesheet_directory_uri() . '/static/css/main.css', array(), $version, '');
    //wp_register_style('main-rtl', get_stylesheet_directory_uri() . '/static/css/main-rtl.css', array(), $version, '');
    //wp_register_style('blocks', get_stylesheet_directory_uri() . '/static/css/blocks.css', array(), $version, '');
    //wp_register_style('blocks-rtl', get_stylesheet_directory_uri() . '/static/css/blocks-rtl.css', array(), $version, '');

    wp_register_style('main', STATIC_URL . 'css/main-combined.css', array(), $version, '');
    wp_register_style('main-rtl', STATIC_URL . 'css/main-combined-rtl.css', array(), $version, '');

    //wp_enqueue_style('fonts');
    wp_enqueue_style('locale');
    
    if(is_rtl() || $GLOBALS["language"] == "fa"){
    	//wp_enqueue_style('icons');
        //wp_enqueue_style('header-rtl');
		if($plugin_css){
			wp_enqueue_style('css-conditional-rtl');
		}
		//wp_enqueue_style('main-rtl');
		//wp_enqueue_style('blocks-rtl');
		wp_enqueue_style('main-rtl');
    }else{
    	//wp_enqueue_style('icons');
		//wp_enqueue_style('header');
		if($plugin_css){
	       wp_enqueue_style('css-conditional');
	    }
		//wp_enqueue_style('main');
		//wp_enqueue_style('blocks');
		wp_enqueue_style('main');   	
    }


	/*$load_svg_files = "
	.grayscale,
	.grayscale-hover,
	.color-hover:hover{
	   filter: url(".get_stylesheet_directory_uri() . "/static/css/grayscale.svg#grayscale);
	}
	.no-grayscale,
	.grayscale-hover:hover{
	   filter: url(".get_stylesheet_directory_uri() . "/static/css/grayscale.svg#ungrayscale);
	}";
	wp_add_inline_style( 'main', $load_svg_files );*/	
}
function frontend_header_scripts(){
	wp_deregister_script('jquery');
	wp_register_script ('jquery', STATIC_URL . 'js/jquery.min.js', array(), '1.0.0', false);
	wp_enqueue_script('jquery');
    
   if(ENABLE_PRODUCTION){
		$header_files = compile_files_config(true)["js"]["header"];
		foreach($header_files as $key => $file){
		   wp_register_script('header-'.$key, $file, array(), '1.0.0', false);
		   wp_enqueue_script('header-'.$key);
		}
	}else{
	    wp_register_script ('header', STATIC_URL . 'js/header.min.js', array(), '1.0.0',false);
		wp_enqueue_script('header');	
	}

	/*$locale_script_path = get_stylesheet_directory() . '/theme/static/data/translates_' . $GLOBALS['language'] . '.js';
	if (file_exists($locale_script_path) && is_readable($locale_script_path) && filesize($locale_script_path) > 0) {
	    wp_register_script('translates', get_stylesheet_directory_uri() . '/theme/static/data/translates_' . $GLOBALS['language'] . '.js', array(), null, true);
	    wp_enqueue_script('translates');
	}*/	
}
function frontend_footer_scripts(){

    wp_deregister_script( 'wc_additional_variation_images_script');
    wp_deregister_script( 'ywdpd_owl');
    wp_dequeue_script( 'ywdpd_owl');
    wp_dequeue_script( 'ywdpd_popup');
    wp_dequeue_script( 'ywdpd_frontend');

    if(isset($GLOBALS['google_maps_api_key']) && !empty($GLOBALS['google_maps_api_key'])){
	    wp_register_script('googlemaps','https://maps.googleapis.com/maps/api/js?key='.$GLOBALS['google_maps_api_key'].'&language='.$GLOBALS['language'], array(),null,true);
	    wp_enqueue_script('googlemaps');    	
    }

    if (!is_admin()) { // Sadece frontend'de leaflet'i kaldır
        wp_deregister_script('acf-osm-frontend');
        wp_dequeue_script('acf-osm-frontend');
    }

    if (!function_exists("compile_files_config")) {
        require SH_INCLUDES_PATH . "minify-rules.php";
    }
    $files = compile_files_config(true);
    $init_functions = [];

    if(ENABLE_PRODUCTION){
    	
    	$functions = $files["js"]["functions"];
    	foreach($functions as $key => $file){
			wp_register_script('footer-'.$key, $file, array(), '1.0.0', true);
		    wp_enqueue_script('footer-'.$key);
		}

		
		$plugins = $files["js"]["plugins"];
    	foreach($plugins as $plugin => $file){
    		if(!$file["c"]){
				/*wp_register_script('plugins-'.$key, $file["url"], array(), '1.0.0', true);
			    wp_enqueue_script('plugins-'.$key);  			
			    if(!empty($file["init"])){
			    	$init_functions[$plugin] = $file["init"];
			    }*/
			    wp_register_script('plugin-'.$plugin, STATIC_URL . 'js/plugins/'.$plugin.".js", array(), null, true);
	            wp_enqueue_script('plugin-'.$plugin);
	            wp_register_script('plugin-'.$plugin."-init", STATIC_URL . 'js/plugins/'.$plugin."-init.js", array(), null, true);
	            wp_enqueue_script('plugin-'.$plugin."-init");
	            if(!empty($file["init"])){
			    	$init_functions[$plugin] = $file["init"];
			    }
    		}
		}

		$plugins_conditional = [];
	    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["plugins"])){
	    	$plugins_conditional = SITE_ASSETS["plugins"];//apply_filters("salt_conditional_plugins", []);
	    }

	    if($plugins_conditional){
	    	foreach($plugins_conditional as $plugin){
	    		wp_register_script('plugin-'.$plugin, STATIC_URL . 'js/plugins/'.$plugin.".js", array(), null, true);
	            wp_enqueue_script('plugin-'.$plugin);
	            wp_register_script('plugin-'.$plugin."-init", STATIC_URL . 'js/plugins/'.$plugin."-init.js", array(), null, true);
	            wp_enqueue_script('plugin-'.$plugin."-init");

	            if(!empty($plugins[$plugin]["init"])){
			    	$init_functions[$plugin] = $plugins[$plugin]["init"];
			    }
	    	}
	    }

		$main = $files["js"]["main"];
    	foreach($main as $key => $file){
			wp_register_script('main-'.$key, $file, array(), '1.0.0', true);
		    wp_enqueue_script('main-'.$key);
		    if ($key == 0) {
		        $inline_script = 'function init_plugins() {';
		        foreach ($init_functions as $plugin => $func) {
		            $inline_script .= 'function_secure("' . esc_js($plugin) . '", "' . esc_js($func) . '");';
		        }
		        $inline_script .= '}';
		        wp_add_inline_script('main-'.$key, $inline_script);
		    }
		}

    }else{

        wp_register_script('functions', STATIC_URL . 'js/functions.min.js', array( ), null, true);
		wp_enqueue_script('functions');

	    wp_register_script('plugins', STATIC_URL . 'js/plugins.min.js', array(), null, true);
	    wp_enqueue_script('plugins');

	    $plugins = $files["js"]["plugins"];
    	foreach($plugins as $key => $file){
    		if(!$file["c"]){
			    if(!empty($file["init"])){
			    	$init_functions[$plugin] = $file["init"];
			    }
    		}
		}

		$plugins_conditional = [];
	    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["plugins"])){
	    	$plugins_conditional = SITE_ASSETS["plugins"];//apply_filters("salt_conditional_plugins", []);
	    }
	    if($plugins_conditional){
	    	foreach($plugins_conditional as $plugin){
	            if(!empty($plugins[$plugin]["init"])){
			    	$init_functions[$plugin] = $plugins[$plugin]["init"];
			    }
	    	}
	    }

	    $inline_script = 'function init_plugins() {';
		foreach ($init_functions as $plugin => $func) {
		    $inline_script .= 'function_secure("' . esc_js($plugin) . '", "' . esc_js($func) . '");';
		}
		$inline_script .= '}';
		wp_add_inline_script('plugins', $inline_script);    


	    $plugin_js = "";
	    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["plugin_js"]) && !isset($_GET['fetch'])){
	    	$plugin_js = SITE_ASSETS["plugin_js"];//apply_filters("salt_conditional_plugins", []);
	    }

	    if(!empty($plugin_js)){
	        wp_register_script('plugins-conditional', STATIC_URL . $plugin_js, array('jquery' ), null, true);
	        wp_enqueue_script('plugins-conditional');
	    }

	    wp_register_script('main', STATIC_URL . 'js/main.min.js', array( ), null, true);
	    wp_enqueue_script('main');

    }

    //wp_register_script('locale', get_stylesheet_directory_uri() . '/static/js/min/locale/'.$GLOBALS['language'].'.js', array(), null, true);
	//wp_enqueue_script('locale');

	$locale_script_path = STATIC_PATH . 'js/locale/' . $GLOBALS['language'] . '.js';
	if (file_exists($locale_script_path) && is_readable($locale_script_path) && filesize($locale_script_path) > 0) {
	    wp_register_script('locale', STATIC_URL . 'js/locale/' . $GLOBALS['language'] . '.js', array(), null, true);
	    wp_enqueue_script('locale');
	}


	$map_style = get_field('google_maps_style', 'option');
	if($map_style != ''){
		$map_style = json_encode(json_decode(strip_tags($map_style)));
		$add_map_style = "var map_style = ".$map_style.";";
		wp_add_inline_script( 'googlemaps', $add_map_style );
    }
    /*$location_main = acf_main_location(get_field_wpml('locations', 'option'));
    if($location_main != ''){
    	$add_location_main = "var location_main = ".json_encode($location_main).";";
		wp_add_inline_script( 'functions', $add_location_main );
    }*/
}
function load_frontend_files() {
    frontend_header_styles();
    frontend_header_scripts();
    frontend_footer_scripts();
}


function admin_header_styles(){
	wp_enqueue_style('fontawesome','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css' , array(),'5.13.0','');
	wp_enqueue_style('bootstrap-admin', STATIC_URL . 'css/bootstrap-admin.css'); 
    wp_enqueue_style('acf-layouts', STATIC_URL . 'css/header-admin.css');
	wp_enqueue_style('main-admin', STATIC_URL . 'css/main-admin.css'); 
	wp_enqueue_style('blocks-admin', STATIC_URL . 'css/blocks-admin.css'); 
	wp_enqueue_style('admin-addon', STATIC_URL . 'css/admin-addon.css'); 
}
function admin_header_scripts(){
}
function admin_footer_scripts(){
	 //wp_register_script ("admin", get_stylesheet_directory_uri() . '/static/js/admin.js', array( 'jquery' ),'1.0.0',true);
	 //wp_register_script ("admin", get_stylesheet_directory_uri() . '/includes/admin/index.js', array( 'jquery' ),'1.0.0',true);
	//wp_register_script ("admin", SH_INCLUDES_URL . 'admin/index.js', array( 'jquery' ),'1.0.0',true);
	wp_register_script ("admin", STATIC_URL . 'js/admin.min.js', array( 'jquery' ), '1.0.0', true);
	
	wp_enqueue_script('admin');
	wp_register_script ("functions", STATIC_URL . 'js/functions.min.js', array( 'jquery' ),'1.0.0',true);
	wp_enqueue_script('functions');
	wp_register_script ("plugins-admin", STATIC_URL . 'js/plugins-admin.min.js', array( 'jquery' ),'1.0.0',true);
	wp_enqueue_script('plugins-admin');
}
function load_admin_files() {
	admin_header_styles();
    admin_header_scripts();
    admin_footer_scripts();
}