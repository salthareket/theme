<?php

function compile_files_config($enable_production=false){

	if (!function_exists("compile_files_plugins") && SH_THEME_EXISTS) {
        require THEME_INCLUDES_PATH . "minify-rules.php";
    }

	//setting languages
	$languages = array();
	if(ENABLE_MULTILANGUAGE){
		foreach($GLOBALS["languages"] as $language) {
			array_push($languages, $language["name"]);
		}
	}
	if(count($languages) == 0){
		$language = strtolower( substr( get_locale(), 0, 2 ) );
		array_push($languages, $language);
	}

	//setting paths
	$css_path = STATIC_PATH . 'css/';
	$css_path_uri = STATIC_URL . '/css/';

	$js_path = STATIC_PATH. 'js/';
	$js_path_uri = STATIC_URL . 'js/';

	$js_theme_path = THEME_STATIC_PATH . 'js/';
	$js_theme_path_uri = THEME_STATIC_URL . 'js/';

	$js_sh_path = SH_STATIC_PATH . 'js/';
	$js_sh_path_uri = SH_STATIC_URL . 'js/';

	$plugin_path = $js_path . 'plugins/';
	$plugin_path_uri = $js_path_uri . 'plugins/';

	$node_path = NODE_MODULES_PATH;
	$node_path_uri = site_url() .'/node_modules/';

	$prod_path = $js_sh_path.'production/';
	$prod_path_uri = $js_sh_path_uri.'production/';

	$locale_path = $js_path.'locale/';

	$config = array(
		"js" => $js_path,
		"js_uri" => $js_path_uri,
		"js_theme" => $js_theme_path,
		"js_theme_uri" => $js_theme_path_uri,
		"css" => $css_path,
		"css_uri" => $css_path_uri,
		"prod" => $prod_path,
		"prod_uri" => $prod_path_uri,
		"plugin" => $plugin_path,
		"plugin_uri" => $plugin_path_uri,
		"locale" => $locale_path,
		"languages" => $languages,
		"language" => $language,
		"node"  => $node_path,
		"node_uri" => $node_path_uri
	);

	if($enable_production){
	   $node_path = $node_path_uri;
	   $plugin_path = $plugin_path_uri;
	   $prod_path = $prod_path_uri;
	}

	$header_has_navigation = header_has_navigation();

	$plugins = array();
	$plugins['bootstrap'] = [
		"c"   => false,
		"admin" => false,
		"url" => [
			$node_path . 'bootstrap/dist/js/bootstrap.bundle.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"  => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['jquery-slinky'] = [
		"c"   => true,
		"admin" => false,
		"url" => [
			$node_path . 'jquery-slinky/dist/slinky.min.js'
		],
		"css" => [
			$node_path . 'jquery-slinky/dist/slinky.min.css'
		],
		"class" => ["slinky-menu"],
		"attrs" => [],
		"init"  => "init_slinky",
		"whitelist" => [
			".slinky-*",
		],
		"required" => []
	];
	$plugins['bootbox'] = [
		"c"   => true,
		"admin" => false,
		"url" => [
			$node_path . 'bootbox/dist/bootbox.all.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs"  => [
			'data-toggle="confirm"', 
			'data-toggle="alert"', 
			'data-ajax-method="form_modal"', 
			'data-ajax-method="page_modal"', 
			'data-ajax-method="iframe_modal"', 
			'data-ajax-method="map_modal"'
		],
		"init" => "init_bootbox",
		"whitelist" => [
			".ratio",
			".ratio-*"
		],
		"required" => []
	];
	$plugins['aos'] = [
		"c"   => true,
		"admin" => false,
		"url" => [
			$node_path . 'aos/dist/aos.js'
		],
		"css" => [
			$node_path . 'aos/dist/aos.css'
		],
		"class" => [],
		"attrs"  => ['data-aos='],
		"init" => "init_aos",
		"whitelist" => [
			".aos-animate"
		],
		"required" => []
	];
	$plugins['plyr'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'plyr/dist/plyr.min.js'
		],
		"css" => [
			$node_path . 'plyr/dist/plyr.css'
		],
		"class" => ["player"],
		"attrs" => [],
		"init"     => "plyr_init",
		"whitelist" => [],
		"required" => []
	];
	$plugins['swiper'] = [
		"c"   => true,
		"admin" => true,
		"url" => [
			$node_path . 'swiper/swiper-bundle.min.js'
		],
		"css" => [
			$node_path .'swiper/swiper-bundle.min.css'
		],
		"class" => ["swiper"],
		"attrs" => [],
		"init"     => "init_swiper",
		"whitelist" => [
			".swiper-*"
		],
		"required" => [
			"html-to-image"
		]
	];
	$plugins['html-to-image'] = [
		"c"   => true,
		"admin" => true,
		"url" => [
			$node_path . 'html-to-image/dist/html-to-image.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	
	$plugins['vanilla-lazyload'] = [
		"c"   => false,
		"admin" => true,
		"url" => [
			$node_path . 'vanilla-lazyload/dist/lazyload.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"  => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['justifiedGallery'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'justifiedGallery/dist/js/jquery.justifiedGallery.min.js'
		],
		"css" => [
			$node_path . 'justifiedGallery/dist/css/justifiedGallery.min.css'
		],
		"class" => ["justified-gallery"],
		"attrs" => ["data-gallery-type"],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['lightgallery'] = [
		"c"   => true,
		"admin" => false,
		"url" => [
			$node_path . 'lightgallery/lightgallery.min.js',
			$node_path . 'lightgallery/plugins/video/lg-video.min.js'
		],
		"css" => [
			$node_path . 'lightgallery/css/lightgallery-bundle.min.css'
		],
		"class" => ["lightgallery"],
		"attrs" => [],
		"init"     => "init_lightGallery",
		"whitelist" => [
			"lg-*"
		],
		"required" => []
	];
	$plugins['jquery-match-height'] = [
		"c" => true,
		"admin" => true,
		"url" => [
			$node_path . 'jquery-match-height/dist/jquery.matchHeight-min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [
			"data-mh", 
			"data-mh-all"
		],
		"init"     => "init_match_height",
		"whitelist" => [],
		"required" => []
	];
	/*$plugins['scrollpos-styler'] = [
		"c"	=> false,
		"admin" => false,
		"url" => [
			$node_path . 'scrollpos-styler/scrollPosStyler.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];*/
	$plugins['is-in-viewport'] = [
		"c"	=> false,
		"admin" => true,
		"url" => [
			$node_path . 'is-in-viewport/lib/isInViewport.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['letteringjs'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'letteringjs/jquery.lettering.js'
		],
		"css" => [],
		"class" => ["text-effect"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['textillate'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'textillate/jquery.textillate.js'
		],
		"css" => [
			$node_path . 'animate.css/animate.compat.css'
		],
		"class" => ["text-effect"],
		"attrs" => [],
		"init"     => "text_effect",
		"whitelist" => [],
		"required" => []
	];
    $plugins['jarallax'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'jarallax/dist/jarallax.min.js'
		],
		"css" => [
			$node_path .'jarallax/dist/jarallax.min.css'
		],
		"class" => ["jarallax", "jarallax-video"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
    $plugins['lenis'] = [
   		"c"	=> false,
   		"admin" => false,
		"url" => [
			$node_path . 'lenis/dist/lenis.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
    $plugins['jquery.simple-text-rotator'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path .'jquery.simple-text-rotator/jquery.simple-text-rotator.min.js'
		],
		"css" => [
			$node_path .'jquery.simple-text-rotator/simpletextrotator.css'
		],
		"class" => ["text-rotator"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['masonry-layout'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path .'masonry-layout/dist/masonry.pkgd.min.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => ["data-masonry"],
		"init"     => "",
		"whitelist" => [],
		"required" => []
	];
	$plugins['twig'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'twig/twig.min.js'
		],
		"css" => [],
		"class" => ["leaflet-custom", "googlemaps-custom", "container-story"],
		"attrs" => [],
		"condition" => get_option("options_map_view") == "js" ? 1: 0,
		"init"     => "init_twig",
		"whitelist" => [],
		"required" => []
	];
	$plugins['leaflet'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'leaflet/dist/leaflet.js',
			$node_path . 'leaflet.markercluster/dist/leaflet.markercluster.js'
		],
		"css" => [
			$node_path . 'leaflet/dist/leaflet.css',
			$node_path . 'leaflet.markercluster/dist/MarkerCluster.css',
			$node_path . 'leaflet.markercluster/dist/MarkerCluster.Default.css'
		],
		"class" => ["leaflet-custom"],
		"attrs" => [
            'data-ajax-method="map_modal"'
		],
		"condition" => get_option("options_map_view") == "js" && get_option("options_map_service") == "leaflet" ? 1: 0,
		"init"     => "init_leaflet",
		"whitelist" => [
			".leaflet-*"
		],
		"required" => []
	];
	$plugins['markerclusterer'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . '@googlemaps/markerclusterer/dist/index.umd.js'
		],
		"css" => [],
		"class" => ["googlemaps-custom"],
		"attrs"  => [
			'data-ajax-method="map_modal"'
		],
		"condition" => get_option("options_map_view") == "js" && get_option("options_map_service") == "google" ? 1: 0,
		"init"     => "init_google_maps",
		"whitelist" => [],
		"required" => []
	];

	$plugins['smarquee'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'smarquee/dist/smarquee.min.js'
		],
		"css" => [],
		"class" => ["smarquee"],
		"attrs" => [],
		"init"     => "init_smarquee",
		"whitelist" => [],
		"required" => [] 
	];

	$plugins['locomotive-scroll'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'locomotive-scroll/bundled/locomotive-scroll.min.js'
		],
		"css" => [
			$node_path . 'locomotive-scroll/bundled/locomotive-scroll.css',
		],
		"class" => [],
		"attrs" => ["data-scroll"],
		"init"     => "init_locomotive_scroll",
		"whitelist" => [],
		"required" => [] 
	];

	$plugins['jquery-zoom'] = [
		"c"	=> true,
		"admin" => false,
		"url" => [
			$node_path . 'jquery-zoom/jquery.zoom.js'
		],
		"css" => [],
		"class" => [],
		"attrs" => ["data-zoom"],
		"init"     => "init_jquery_zoom",
		"whitelist" => [],
		"required" => []  
	];

	$plugins['panzoom'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . '@panzoom/panzoom/dist/panzoom.min.js'
		],
		"css" => [],
		"class" => ["panzoom"],
		"attrs" => [],
		"init"     => "init_panzoom",
		"whitelist" => [
			".panzoom-*"
		],
		"required" => []
	];

	$plugins['simplebar'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'simplebar/dist/simplebar.min.js'
		],
		"css" => [
			$node_path . 'simplebar/dist/simplebar.min.css'
		],
		"class" => ["simplebar"],
		"attrs" => ["data-simplebar"],
		"init"     => "init_simplebar",
		"whitelist" => [
			".simplebar-*"
		],
		"required" => []
	];

    if(!isset($plugins["smartmenus"]) && $header_has_navigation){
	 	$plugins['smartmenus'] = [
	 		"c"   => true,
	 		"admin" => false,
			"url" => [
				$node_path . 'smartmenus/dist/jquery.smartmenus.min.js',
				$node_path . 'smartmenus/dist/addons/bootstrap-4/jquery.smartmenus.bootstrap-4.min.js'
			],
			"css" => [
				$node_path . 'smartmenus/dist/addons/bootstrap-4/jquery.smartmenus.bootstrap-4.css'
			],
			"class" => ["smartmenu"],
			"attrs" => [],
			"init"     => "init_smartmenus",
		    "whitelist" => [
		    	".highlighted"
		    ],
			"required" => []
		];
	}
    
    if(function_exists("compile_files_plugins")){
    	//error_log(" ----------------  compile_files_plugins");
		$theme_plugins = compile_files_plugins($enable_production);

		if($theme_plugins){
			$plugins = array_merge($plugins, $theme_plugins);
		}    	
    }

    //error_log(print_r($plugins, true));


   $header_css = array();
   //$header_css['animate.css'] = [$node_path . 'animate.css/animate.compat.css'];
   if($plugins){
   	foreach($plugins as $key => $plugin){
   		if(!$plugin["c"] && $plugin["css"]){
   			$header_css[$key] = $plugin["css"];
   		}
   	}
   }


   $header_css_admin = array();
   if($plugins){
   	foreach($plugins as $key => $plugin){
   		if($plugin["admin"] && $plugin["css"]){
   			$header_css_admin[$key] = $plugin["css"];
   		}
   	}
   }



   $jquery_js = array();
   $jquery_js['jquery'] = $node_path . 'jquery/dist/jquery.min.js';



	$header_js = array();
	$header_js['enquire'] = $node_path . 'enquire.js/dist/enquire.min.js';
	//$header_js['defaults'] = $prod_path .'defaults.js';
	
	//$header_js['intl'] = $node_path . 'intl/dist/Intl.min.js';
	//$header_js['modernizr'] = $plugin_path . 'modernizr/2.8.3/modernizr.min.js';
	//$header_js['current-device'] = $node_path . 'current-device/umd/current-device.min.js';
	//$header_js['jquery-ui'] = $plugin_path .'jquery-ui/jquery-ui.min.js';
	
	

	

	$locale = array();
	/*$locale["intl"] = array(
		"file" => $node_path . 'intl/locale-data/jsonp/{lang}.js',
		"exception" => array(
            "tr" => "tr-TR"
		)
	);
	$locale["bootstrap-datepicker"] = array(
		"file" => $node_path . 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.{lang}.min.js',
		"exception" => array(
	       "en" => "en-GB"
		)
	);
	$locale["fancybox"] = array(
		"file" => $node_path . '@fancyapps/ui/dist/fancybox/i10n/{lang}.umb.js'
	);*/

	$locale_css = array();
	/*$locale_css["bootstrap-rtl"] = array(
		"ar" => $node_path . 'bootstrap-v4-rtl/dist/css/bootstrap-rtl.min.css'
	);*/



   $plugins_admin = array();
   if($plugins){
   	foreach($plugins as $key => $plugin){
   		if($plugin["admin"]){
   			$plugins_admin[$key] = $plugin["url"];   			
   		}
   	}
   }


	$css = array(
		"header" => $header_css,
		"header_admin" => $header_css_admin,
		"locale" => $locale_css
	);

	$js = array(
		"jquery"  => $jquery_js,
	    "header"  => $header_js,
	    "locale"  => $locale,
	    "plugins" => $plugins,
	    "plugins_admin" => $plugins_admin
	);

	if($enable_production){
		$js["functions"] = array();
		$functions = array_slice(scandir($config["prod"].'functions/'), 2);
		if(!ENABLE_ECOMMERCE){
           if (isset($functions["wp-wc.js"])){
               unset($functions["wp-wc.js"]);
           }
      }else{
           if (!ENABLE_CART && isset($functions["wp-wc.js"])){
               unset($functions["wp-wc.js"]);
           }
      }
      if ((!defined('ENABLE_ECOMMERCE') || !ENABLE_ECOMMERCE) || (!defined('ENABLE_CART') || !ENABLE_CART)) {
		    $key = array_search('wp-wc.js', $functions);
		    if ($key !== false) { 
		        unset($functions[$key]); // Anahtar bulunduysa sil
		    }
		}
		
		foreach($functions as $file){
			$js["functions"][] = $prod_path_uri.'functions/'.$file;
		}
		$js["main"] = array();
		$main =  array_slice(scandir($config["prod"].'main/'), 2);
		//$main = array_reverse($main);
		foreach($main as $file){
			$js["main"][] = $prod_path_uri.'main/'.$file;
		}

        if(is_dir($config["js_theme"])){
			$theme_js =  array_slice(scandir($config["js_theme"]), 2);
			foreach($theme_js as $file){
				$js["main"][] = $config["js_theme_uri"].$file;
			}        	
        }

	}

	$minify = array(
		"config" => $config,
		"css"    => $css,
		"js"     => $js
	);

	//error_log(json_encode($minify));

	return $minify;
}

function combine_and_cache_files($type, $files) {
    if ($type !== 'css' && $type !== 'js') {
        return false;
    }

    sort($files);
    $file_names = implode(',', $files);
    $hash = md5($file_names);
    $cache_dir = get_stylesheet_directory() . '/static/' . $type . '/cache/';
    $cache_file = $cache_dir . $hash . '.' . $type;

    if (file_exists($cache_file)) {
        return get_stylesheet_directory_uri() . '/static/' . $type . '/cache/' . $hash . '.' . $type;
    } else {
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
    }

    $combined_content = '';
    foreach ($files as $file) {
        // Dosyanın tam yolunu kullan
        $file_system_path = get_stylesheet_directory() . '/static/js/plugins/' . basename($file);
        
        if (file_exists($file_system_path)) {
            $content = file_get_contents($file_system_path);
            if ($content !== false) {
                // İçeriği ekle ve sonuna yeni satır ekle
                $combined_content .= $content . PHP_EOL; // Sonuna yeni satır ekleniyor
            } else {
                error_log("Error reading file: $file_system_path");
            }
        } else {
            error_log("File does not exist: $file_system_path");
        }
    }

    // Birleştirilen içeriği dosyaya yaz
    file_put_contents($cache_file, trim($combined_content)); // Boş satırları önlemek için trim kullan
    return get_stylesheet_directory_uri() . '/static/' . $type . '/cache/' . $hash . '.' . $type;
}