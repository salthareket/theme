<?php
function generateFontPreloadTags($font_faces_path) {
    // 1. GÜVENLİK VE YOL KONTROLÜ
    // $font_faces_path mutlaka fiziksel yol olmalı (C:/... veya /var/www/...)
    if (empty($font_faces_path) || !file_exists($font_faces_path)) {
        return '';
    }

    // 2. PERFORMANS: CACHING (Transient API)
    // Dosyanın son değişim zamanını alıyoruz, dosya güncellenirse cache otomatik düşer.
    $last_modified = filemtime($font_faces_path);
    $cache_key = 'font_preloads_' . md5($font_faces_path . $last_modified);
    
    $cached_output = get_transient($cache_key);
    if ($cached_output !== false) {
        return "\n" . $cached_output;
    }

    // 3. HIZ: DOSYAYI OKU
    $content = file_get_contents($font_faces_path);
    if (empty($content)) return '';

    // Regex iyileştirildi: tırnaklı/tırnaksız url() ve format() yapılarını kapsar.
    preg_match_all('/src:\s*url\([\'"]?([^)]+?)[\'"]?\)\s*format\([\'"]?([^"\')]+)[\'"]?\)/i', $content, $matches, PREG_SET_ORDER);

    $preloads = [];
    $raw_home_url = network_site_url(); 
    $current_home_path = rtrim(parse_url($raw_home_url, PHP_URL_PATH) ?: '', '/');

    foreach ($matches as $match) {
        $url = $match[1];
        
        // Varsa query stringleri temizle (font.woff2?v=1.2 -> font.woff2)
        $url = strtok($url, '?#');

        // 4. MANTIK: URL TEMİZLİĞİ
        // ../ gibi göreceli yolları temizle veya wp-content odaklı hale getir
        if (strpos($url, 'wp-content') !== false) {
            $url = strstr($url, '/wp-content');
        }

        // Dil kodlarını (/en/, /tr/) regex ile temizle (Sadece başta varsa)
        $url = preg_replace('/^\/[a-z]{2}\//', '/', $url);

        $final_url = $current_home_path . $url;
        $format = strtolower($match[2]);

        $type = match ($format) {
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'truetype', 'ttf', 'opentype' => 'font/ttf',
            default => 'font/woff2',
        };

        $preloads[] = sprintf(
            '<link rel="preload" as="font" href="%s" type="%s" crossorigin>',
            htmlspecialchars($final_url),
            $type
        );
    }

    $output = implode("\n", array_unique($preloads));

    // Sonucu 12 saat önbelleğe al (veya dosya değişene kadar)
    set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);

    return "\n" . $output;
}

add_action('wp_head', function () {
    $preload = generateFontPreloadTags(STATIC_PATH .'css/font-faces.css');
    if ($preload) {
        echo "\n<!-- Preload Font Faces -->\n" . $preload . "\n";
    }

    /*
	>>> We decided to use wp rocket's critical css function...
    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && !isset($_GET['fetch'])){
    	if(!empty(SITE_ASSETS["css_critical"])){
    		inline_css_add('css-critical', STATIC_PATH . SITE_ASSETS["css_critical"]);
    	}
    }
    */
}, 0); // 0 ile en başta bassın



function dequeue_theme_styles() {
    wp_dequeue_style('theme-style'); // 'theme-style' yerine kendi temanızın style.css dosyasının kayıt adını kullanın
    wp_deregister_style('theme-style');
    if (!is_admin() && !apply_filters('show_admin_bar', true)) {
        wp_deregister_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'dequeue_theme_styles', 999);

function remove_jquery_migrate($scripts) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];
        if ($script->deps) {
            $script->deps = array_diff($script->deps, array('jquery-migrate'));
        }
    }
}
add_action('wp_default_scripts', 'remove_jquery_migrate');


function inline_css($name = "", $url = "") {
    if (empty($url) || !is_string($url) || !file_exists($url)) {
        return '';
    }

    // --- PERFORMANS: CACHING ---
    // Dosya yolu ve son değişim zamanına göre benzersiz bir cache anahtarı oluşturuyoruz.
    $last_modified = filemtime($url);
    $cache_key = 'inline_css_' . md5($name . $url . $last_modified);
    
    // Eğer cache varsa direkt döndür, aşağıdakilere hiç girme.
    $cached_css = get_transient($cache_key);
    if ($cached_css !== false) {
        return "/* Cached $name */\n" . $cached_css;
    }

    $css = file_get_contents($url);
    if ($css === false) return '';

    // --- DİL EKİNDEN ARINDIRILMIŞ ANA DİZİN ALMA ---
    $raw_home_url = network_site_url(); 
    $subfolder = rtrim(parse_url($raw_home_url, PHP_URL_PATH) ?: '', '/');

    if ($name == "css-critical" && !empty(SITE_ASSETS["css"]) && (!isset($_GET['fetch']) && SEPERATE_CSS)) {
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'] . "/";
        $code = str_replace("{upload_url}", $upload_url, SITE_ASSETS["css"]);
        $code = str_replace("{home_url}", rtrim($raw_home_url, '/'), $code);
        $css .= $code;
    }

    $css = str_replace("[STATIC_URL]", STATIC_URL, $css);

    $theme_dir = wp_normalize_path(get_template_directory());
    $theme_name = basename($theme_dir);
    $base_path = wp_normalize_path(dirname($url));
    
    $processed_css = preg_replace_callback(
        '/url\((["\']?)(?!https?:|data:)([^)\'"]+)\1\)/i',
        function ($m) use ($base_path, $theme_dir, $subfolder, $theme_name) {
            $quote = $m[1];
            $url_path = $m[2];

            if (strpos($url_path, '/wp-content/') !== false) {
                $clean_path = str_replace('\\', '/', strstr($url_path, '/wp-content/'));
                return "url({$quote}{$subfolder}{$clean_path}{$quote})";
            }

            $original_path = wp_normalize_path($url_path);
            $abs_path = wp_normalize_path(realpath($base_path . DIRECTORY_SEPARATOR . $original_path));

            if (!$abs_path || !str_starts_with($abs_path, $theme_dir)) {
                return $m[0];
            }

            $rel_path = str_replace($theme_dir, '', $abs_path);
            $rel_path = ltrim(str_replace('\\', '/', $rel_path), '/');

            return "url({$quote}{$subfolder}/wp-content/themes/{$theme_name}/{$rel_path}{$quote})";
        },
        $css
    );

    // Gereksiz boşlukları temizleyerek CSS'i küçült (Minification - Opsiyonel)
    $processed_css = preg_replace('/\s+/', ' ', $processed_css);

    // Sonucu 12 saatliğine cache'e at
    set_transient($cache_key, $processed_css, 12 * HOUR_IN_SECONDS);

    return $processed_css;
}
function inline_css_add($name="", $url="", $rtl=false){
	if(empty($name) || empty($url)){
		return;
	}
	$name = $name.($rtl?"-rtl":"");
	wp_register_style( $name, false );
    wp_enqueue_style( $name );
	$code = inline_css($name, $url);
	wp_add_inline_style( $name, $code);
}
function inline_js_add($name = "", $url = "", $in_footer = true, $attrs = []) {
    if (empty($name) || empty($url)) {
        return;
    }
    $path = str_replace(get_template_directory_uri(), get_template_directory(), $url);
    if (!file_exists($path)) {
        return;
    }
    $code = file_get_contents($url);
    if($attrs){
    	//add_action('wp_footer', function() use ($code, $attrs){
		    $attr_str = '';
		    foreach ($attrs as $key => $value) {
		        if (is_bool($value)) {
		            $attr_str .= $value ? " {$key}" : '';
		        } else {
		            $attr_str .= " {$key}=\"{$value}\"";
		        }
		    }
		    echo "<script{$attr_str}>{$code}</script>";
		//});
    }else{
	    wp_register_script($name, false, [], false, $in_footer); // $in_footer true olursa footer'a eklenir
	    wp_enqueue_script($name);
	    wp_add_inline_script($name, $code);
    }
}

function delay_css_loading($tag, $handle, $href, $media) {
    //$async_handles = ['root', 'main', 'css-conditional', 'css-page', 'locale', 'common-css', 'newsletter'];
    $async_handles = ['locale', 'newsletter', 'sbi_styles'];

    if (in_array($handle, $async_handles)) {
        return "<link id='{$handle}' rel='preload' href='{$href}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\">\n" .
               "<noscript><link rel='stylesheet' href='{$href}'></noscript>\n";
    }
    return $tag;
}
add_filter('style_loader_tag', 'delay_css_loading', 10, 4);


function frontend_header_styles(){

	/*wp_register_style('root', STATIC_URL . 'css/root.css', array(), $version, '');
    wp_enqueue_style('root');

	return;*/

	$print_css = INLINE_CSS;
	if(isset($_GET['fetch'])){
		$print_css = false;
	}

	inline_css_add("font-faces", STATIC_PATH . '/css/font-faces.css');

	$css_path = get_stylesheet_directory() . '/static/css/main.css';
	$version = filemtime($css_path);
    $page_type = get_page_type();
    $has_core_block = false;

    if(in_array($page_type, ["post", "page", "home", "front"])){
    	global $post;
	    $has_core_block = get_post_meta($post->ID, 'has_core_block', true);
		if(isset($post->post_type) && ENABLE_ECOMMERCE){
	    	if($post->post_type != "product"){
	           wp_dequeue_style('woo-variation-swatches');
	           wp_deregister_script("woo-variation-swatches");
	    	}
	    }
    }

    $remove_global_styles = get_option("remove_global_styles");//get_field("remove_global_styles", "option");
    if(($remove_global_styles == "auto" || $remove_global_styles) && !$has_core_block){
    	wp_deregister_style('global-styles');
    	wp_deregister_style('global-styles-inline');
        wp_dequeue_style('global-styles');
		wp_dequeue_style( 'global-styles-inline' );
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
        remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
        remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
    }
    
    $remove_block_styles = get_option("remove_block_styles");//get_field("remove_block_styles", "option");
    if(($remove_block_styles == "auto" || $remove_block_styles) && !$has_core_block){
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wc-blocks-style' ); 
    }
     
    $remove_classic_theme_styles = get_option("remove_classic_theme_styles");//get_field("remove_classic_theme_styles", "option");
    if($remove_classic_theme_styles){
    	wp_deregister_style('classic-theme-styles-inline');
    	wp_deregister_style('classic-theme-styles');
        wp_dequeue_style('classic-theme-styles-inline');
    	wp_dequeue_style('classic-theme-styles');
    }

    if(ENABLE_ECOMMERCE){
	    $remove_woocommerce_styles = get_option("remove_woocommerce_styles");//get_field("remove_woocommerce_styles", "option");
		if($remove_woocommerce_styles){
			wp_dequeue_style('woocommerce-smallscreen');
		    wp_dequeue_style('woocommerce-inline');
		    wp_dequeue_style('woocommerce-layout');
	        wp_dequeue_style('woocommerce-general');
		}

	    wp_dequeue_style('ywdpd_owl');
	    wp_dequeue_style('yith_ywdpd_frontend');

	    if ( get_option( 'woocommerce_coming_soon' ) !== 'yes' ) {
	        wp_dequeue_style( 'woocommerce-coming-soon' );
	        wp_deregister_style( 'woocommerce-coming-soon' );
	    }

	    global $wpdb;
	    $taxonomy_exists = taxonomy_exists( 'product_brand' );
	    if ($taxonomy_exists ) {
		    $has_brand = $wpdb->get_var( "
		        SELECT term_taxonomy_id
		        FROM {$wpdb->term_taxonomy}
		        WHERE taxonomy = 'product_brand'
		        LIMIT 1
		    " );
		    if ( ! $has_brand ) {
		        wp_dequeue_style( 'brands-styles' );
		        wp_deregister_style( 'brands-styles' );
		    }
		}	
    }
	
	wp_dequeue_style('toggle-switch');
	wp_dequeue_style('font-awesome');
	wp_dequeue_style('font-for-body');
    wp_dequeue_style('font-for-new');
    wp_dequeue_style('google-fonts-roboto');

    $locale_file_path = STATIC_PATH . 'css/locale-' . $GLOBALS['language'] . '.css';
	if (file_exists($locale_file_path) && is_readable($locale_file_path) && filesize($locale_file_path) > 0) {
	    wp_register_style('locale', STATIC_URL . 'css/locale-' . $GLOBALS['language'] . '.css' , array(), $version, '');
	    wp_enqueue_style('locale');
	}

	$plugin_css = false;
	$css_page = false;
    $conditional_plugins = [];
    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && !isset($_GET['fetch'])){
        $conditional_plugins = isset(SITE_ASSETS["plugins"])?SITE_ASSETS["plugins"]:[];
    	if(isset(SITE_ASSETS['plugin_css']) && !empty(SITE_ASSETS['plugin_css']) && file_exists(STATIC_PATH . SITE_ASSETS["plugin_css"])){
    		$plugin_css = true;
    	}
    	if(isset(SITE_ASSETS['css_page']) && !empty(SITE_ASSETS['css_page']) && file_exists(STATIC_PATH . SITE_ASSETS["css_page"])){
    		$css_page = true;
    	}
    }

    $is_rtl = false;
    if(is_rtl() || $GLOBALS["language"] == "fa"){
    	$is_rtl = true;
    }

    wp_register_style('root', STATIC_URL . 'css/root.css', array(), $version, '');
    wp_enqueue_style('root');

    /*if($plugin_css && $css_page){
	    $files = compile_files_config(true);
	    $plugins = $files["js"]["plugins"];
	    foreach($plugins as $plugin => $file){
	    	if($file["c"] && !empty($file["css"]) && is_array($file["css"]) && count($file["css"]) > 0 && file_exists(STATIC_URL . 'js/plugins/'.$plugin.".css")){
	    		inline_css('plugin-'.$plugin, STATIC_URL . 'js/plugins/'.$plugin.".css");
	        }
		}
	}*/
  
	if($plugin_css || $css_page){
	    if(!$print_css){
	    	if($plugin_css){
	    		wp_register_style('css-conditional', STATIC_URL . SITE_ASSETS["plugin_css"], array(), $version, '');
	    		wp_register_style('css-conditional-rtl', STATIC_URL . SITE_ASSETS["plugin_css_rtl"], array(), $version, '');
	    	}
	        if($css_page){
		        wp_register_style('main', STATIC_URL . SITE_ASSETS["css_page"], array(), $version, '');
		        wp_register_style('main-rtl', STATIC_URL . SITE_ASSETS["css_page_rtl"], array(), $version, '');
		    }else{
		    	wp_register_style('main', STATIC_URL . 'css/main-combined.css', array(), $version, '');
		    	wp_register_style('main-rtl', STATIC_URL . 'css/main-combined-rtl.css', array(), $version, '');
		    }

		    if($is_rtl){
				if($plugin_css){
					//wp_enqueue_style('css-conditional-rtl');
				}
				//wp_enqueue_style('main-rtl');
		    }else{
				if($plugin_css){
			       //wp_enqueue_style('css-conditional');
			    }else{
                    foreach($conditional_plugins as $plugin){
                        //wp_enqueue_style('css-conditional-'.$plugin);
                    }
                }
				//wp_enqueue_style('main');
		    }
	    }else{
	    	if($plugin_css){
	    		inline_css_add('css-conditional', STATIC_PATH . SITE_ASSETS["plugin_css".($is_rtl?"_rtl":"")], $is_rtl);
	        }
	        if($css_page){
	        	inline_css_add('main', STATIC_PATH . SITE_ASSETS["css_page".($is_rtl?"_rtl":"")], $is_rtl);
	        }else{
	        	inline_css_add('main', STATIC_PATH . 'css/main-combined'.($is_rtl?"-rtl":"").'.css', $is_rtl);
	        }
	    }


        $packer = new \SaltHareket\Theme\AssetPacker(["css-conditional".($is_rtl?"-rtl":""), "main".($is_rtl?"-rtl":"")], 'css', 'global-bundle');
        $bundle_url = $packer->get_url();
        wp_enqueue_style('main-bundle', $bundle_url, array(), null);

	}else{
		if(!$print_css){
            if(!empty($conditional_plugins) && is_array($conditional_plugins)){
                if (!function_exists("compile_files_config")) {
                    require SH_INCLUDES_PATH . "minify-rules.php";
                }
                $files = compile_files_config(true);
                $plugins = $files["js"]["plugins"];
                foreach($plugins as $plugin => $file){
                    if(in_array($plugin, $conditional_plugins)){
                        if($file["c"] && !$file["css_only_local"] && !empty($file["css"]) && is_array($file["css"]) && count($file["css"]) > 0 && file_exists(STATIC_PATH . 'js/plugins/'.$plugin.".css")){
                            wp_register_style('css-conditional-'.$plugin.($is_rtl?'-rtl':''), STATIC_URL . 'js/plugins/'.$plugin.($is_rtl?'-rtl':'').".css", array(), $version, '');
                            wp_enqueue_style('css-conditional-'.$plugin.($is_rtl?'-rtl':''));
                        }                    
                    }
                }                
            }
			wp_register_style('main'.($is_rtl?'-rtl':''), STATIC_URL . 'css/main-combined'.($is_rtl?'-rtl':'').'.css', array(), $version, '');
		    wp_enqueue_style('main'.($is_rtl?'-rtl':''));
		}else{
			inline_css_add('main', STATIC_PATH . 'css/main-combined'.($is_rtl?"-rtl":"").'.css', $is_rtl);
		}
	}

	//wp_register_style('common-css', STATIC_URL . 'css/common-all'.($is_rtl?"-rtl":"").'.css', array(), $version, '');
    //wp_enqueue_style('common-css');
    inline_css_add('common-css', STATIC_PATH  . 'css/common-all'.($is_rtl?"-rtl":"").'.css', $is_rtl);
}

function frontend_header_scripts(){
	wp_deregister_script('jquery');
	wp_register_script ('jquery', STATIC_URL . 'js/jquery.min.js', array(), '1.0.0', false);
	wp_enqueue_script('jquery');

	// Script'i kaydet
	wp_register_script('image-sizes', SH_STATIC_URL .'js/image-sizes.js' , array(), '1.0.0', false);
	wp_enqueue_script('image-sizes');

	add_filter('script_loader_tag', function ($tag, $handle) {
	    if (strpos($handle, 'image-sizes') !== false) { // header- ile başlayan scriptlere uygula
	        $tag = str_replace('src=', 'defer src=', $tag);
	    }
	    return $tag;
	}, 10, 2);
    
   /*if(ENABLE_PRODUCTION){
		$header_files = compile_files_config(true)["js"]["header"];
		foreach($header_files as $key => $file){
		   wp_register_script('header-'.$key, $file, array(), '1.0.0', false);
		   wp_enqueue_script('header-'.$key);
		}
	}else{
	    wp_register_script ('header', STATIC_URL . 'js/header.min.js', array(), '1.0.0',false);
		wp_enqueue_script('header');	
	}*/

}
function frontend_footer_scripts(){

    wp_deregister_script( 'wc_additional_variation_images_script');
    wp_deregister_script( 'ywdpd_owl');
    wp_dequeue_script( 'ywdpd_owl');
    wp_dequeue_script( 'ywdpd_popup');
    wp_dequeue_script( 'ywdpd_frontend');

    if(ENABLE_ECOMMERCE){
    	if(!ENABLE_CART){
    		wp_deregister_script("wc-order-attribution");
    		//wp_deregister_script("wc-add-to-cart-variation");
    	}
    }

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

		$pre = $files["js"]["pre"];
    	foreach($pre as $key => $file){
			wp_register_script('pre-'.$key, $file, array(), '1.0.0', true);
		    wp_enqueue_script('pre-'.$key);
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
	            if(!empty($file["init"])){
	            	wp_register_script('plugin-'.$plugin."-init", STATIC_URL . 'js/plugins/'.$plugin."-init.js", array(), null, true);
	            	wp_enqueue_script('plugin-'.$plugin."-init");
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
	            if(!empty($plugins[$plugin]["init"])){
	            	wp_register_script('plugin-'.$plugin."-init", STATIC_URL . 'js/plugins/'.$plugin."-init.js", array(), null, true);
	            	wp_enqueue_script('plugin-'.$plugin."-init");
			    	$init_functions[$plugin] = $plugins[$plugin]["init"];
			    }
	    	}
	    }

		$main = $files["js"]["main"];
    	foreach($main as $key => $file){
    		wp_register_script('main-'.$key, $file, array(), '1.0.0', true);
		    if ($key == 0 && $init_functions) {
		        $inline_script = 'function init_plugins() {';
		        foreach ($init_functions as $plugin => $func) {
		            $inline_script .= 'function_secure("' . esc_js($plugin) . '", "' . esc_js($func) . '");';
		        }
		        $inline_script .= '}';
		        wp_add_inline_script('main-'.$key, $inline_script);
		    }

		    wp_enqueue_script('main-'.$key);
		}

    }else{

	    $print_js = INLINE_JS;
		if(isset($_GET['fetch'])){
			$print_js = false;
		}

  		wp_register_script('pre', STATIC_URL . 'js/pre-combined.min.js', array(), null, true);
//	    wp_enqueue_script('pre');

        //wp_register_script('functions', STATIC_URL . 'js/functions.min.js', array(), null, true);
		//wp_enqueue_script('functions');

	    //wp_register_script('plugins', STATIC_URL . 'js/plugins.min.js', array(), null, true);
	    //wp_enqueue_script('plugins');
	    
	    $plugin_js = "";
	    if(defined("SITE_ASSETS") && is_array(SITE_ASSETS) && isset(SITE_ASSETS["plugin_js"]) && !isset($_GET['fetch'])){
	    	$plugin_js = SITE_ASSETS["plugin_js"];//apply_filters("salt_conditional_plugins", []);
	    }

	    if(!empty($plugin_js)){
	    	if(!$print_js){
		        wp_register_script('plugins-conditional', STATIC_URL . $plugin_js, array('jquery' ), null, true);
		        wp_enqueue_script('plugins-conditional');
                add_filter('script_loader_tag', function($tag, $handle, $src) {
                    // Sadece bizim belirlediğimiz script handle'ı için işlem yap
                    if ('plugins-conditional' !== $handle) {
                        return $tag;
                    }

                    // Mevcut <script tagını modül tipine çeviriyoruz
                    $tag = '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
                    
                    return $tag;
                }, 10, 3);
	    	}else{
	    		inline_js_add('plugins-conditional', STATIC_URL . $plugin_js); 
	    	}
	    }

	    wp_register_script('main', STATIC_URL . 'js/main-combined.min.js', array(), null, true);
	    //wp_enqueue_script('main');

        
        /*if(!$print_js){
		    wp_register_script('main', STATIC_URL . 'js/main.min.js', array( ), null, true);
		    wp_enqueue_script('main');
		}else{
			inline_js_add('main', STATIC_URL . 'js/main.min.js');  
		}*/

	    $plugins = $files["js"]["plugins"];
    	foreach($plugins as $plugin => $file){
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
	    if($init_functions){
		    $inline_script = 'function init_plugins() {';
			foreach ($init_functions as $plugin => $func) {
			    $inline_script .= 'function_secure("' . esc_js($plugin) . '", "' . esc_js($func) . '");';
			}
			$inline_script .= '}';
			wp_add_inline_script('main', $inline_script); 	    	
	    }
    }

	$locale_script_path = STATIC_PATH . 'js/locale/' . $GLOBALS['language'] . '.js';
	if (file_exists($locale_script_path) && is_readable($locale_script_path) && filesize($locale_script_path) > 0) {
	    wp_register_script('locale', STATIC_URL . 'js/locale/' . $GLOBALS['language'] . '.js', array(), null, true);
	    wp_enqueue_script('locale');
	}

	$map_style = get_option('google_maps_style');//get_field('google_maps_style', 'option');
	if($map_style != ''){
		$map_style = json_encode(json_decode(strip_tags($map_style)));
		$add_map_style = "var map_style = ".$map_style.";";
		wp_add_inline_script( 'googlemaps', $add_map_style );
    }
}
function load_frontend_files() {
    frontend_header_styles();
    frontend_header_scripts();
    frontend_footer_scripts();
}

function admin_header_styles(){
	wp_enqueue_style('fontawesome','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css' , array(),'5.13.0','');
	wp_enqueue_style('bootstrap-admin', STATIC_URL . 'css/bootstrap-admin.css'); 
    wp_enqueue_style('root', STATIC_URL . 'css/root.css');
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

/*
>>> We decided to use wp rocket's critical css function...
add_action('wp_footer', function () {
    ?>
    <script nowprocket>
    window.addEventListener('load', () => {
        const criticalStyle = document.getElementById('css-critical-inline-css');
        if (criticalStyle && criticalStyle.parentNode) {
            criticalStyle.parentNode.removeChild(criticalStyle);
        }
    });
    </script>
    <?php
}, 100);
*/



/**
 * JS dosyasını modül olarak sisteme dahil et
 
function tema_scripts_ekle() {
    // text.js dosyasını her zamanki gibi ekle
    wp_enqueue_script('text-module', get_template_directory_uri() . '/static/js/test.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'tema_scripts_ekle');
function script_tagini_module_yap($tag, $handle, $src) {
    // Eğer handle 'text-module' ise tag'i değiştir
    if ('text-module' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'script_tagini_module_yap', 10, 3);*/