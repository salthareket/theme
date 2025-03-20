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
		"url" => $node_path . 'bootstrap/dist/js/bootstrap.bundle.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"  => "",
		"whitelist" => []
	];
	$plugins['jquery-slinky'] = [
		"c"   => false,
		"admin" => false,
		"url" => $node_path . 'jquery-slinky/dist/slinky.min.js',
		"css" => [
			$node_path . 'jquery-slinky/dist/slinky.min.css'
		],
		"class" => [],
		"attrs" => [],
		"init"  => "",
		"whitelist" => []
	];
	$plugins['bootbox'] = [
		"c"   => true,
		"admin" => false,
		"url" => $node_path . 'bootbox/dist/bootbox.all.min.js',
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
		"init" => "",
		"whitelist" => []
	];
	$plugins['aos'] = [
		"c"   => true,
		"admin" => false,
		"url" => $node_path . 'aos/dist/aos.js',
		"css" => [
			$node_path . 'aos/dist/aos.css'
		],
		"class" => [],
		"attrs"  => ['data-aos='],
		"init" => "",
		"whitelist" => [
			"aos-animate"
		]
	];
	$plugins['plyr'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'plyr/dist/plyr.min.js',
		"css" => [
			$node_path . 'plyr/dist/plyr.css'
		],
		"class" => ["player"],
		"attrs" => [],
		"init"     => "plyr_init",
		"whitelist" => [] 
	];
	$plugins['swiper'] = [
		"c"   => true,
		"admin" => true,
		"url" => $node_path . 'swiper/swiper-bundle.min.js',
		"css" => [
			$node_path .'swiper/swiper-bundle.min.css'
		],
		"class" => ["swiper"],
		"attrs" => [],
		"init"     => "init_swiper",
		"whitelist" => []
	];
	$plugins['vanilla-lazyload'] = [
		"c"   => false,
		"admin" => true,
		"url" => $node_path . 'vanilla-lazyload/dist/lazyload.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"  => "",
		"whitelist" => [] 
	];
	$plugins['justifiedGallery'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'justifiedGallery/dist/js/jquery.justifiedGallery.min.js',
		"css" => [
			$node_path . 'justifiedGallery/dist/css/justifiedGallery.min.css'
		],
		"class" => ["justified-gallery", "lightgallery"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['lightgallery'] = [
		"c"   => true,
		"admin" => false,
		"url" => $node_path . 'lightgallery/lightgallery.min.js',
		"css" => [
			$node_path . 'lightgallery/css/lightgallery-bundle.min.css'
		],
		"class" => ["lightgallery"],
		"attrs" => [],
		"init"     => "init_lightGallery",
		"whitelist" => []
	];
	$plugins['lightgallery-video'] = [
		"c"   => true,
		"admin" => false,
		"url" => $node_path . 'lightgallery/plugins/video/lg-video.min.js',
		"css" => [],
		"class" => ["lightgallery"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['jquery-match-height'] = [
		"c" => false,
		"admin" => true,
		"url" => $node_path . 'jquery-match-height/dist/jquery.matchHeight-min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['scrollpos-styler'] = [
		"c"	=> false,
		"admin" => false,
		"url" => $node_path . 'scrollpos-styler/scrollPosStyler.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['is-in-viewport'] = [
		"c"	=> false,
		"admin" => true,
		"url" => $node_path . 'is-in-viewport/lib/isInViewport.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['letteringjs'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'letteringjs/jquery.lettering.js',
		"css" => [],
		"class" => ["text-effect"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['textillate'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'textillate/jquery.textillate.js',
		"css" => [
			$node_path . 'animate.css/animate.compat.css'
		],
		"class" => ["text-effect"],
		"attrs" => [],
		"init"     => "text_effect",
		"whitelist" => []
	];
    $plugins['jarallax'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path . 'jarallax/dist/jarallax.min.js',
		"css" => [
			$node_path .'jarallax/dist/jarallax.min.css'
		],
		"class" => ["jarallax", "jarallax-video"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
    $plugins['lenis'] = [
   		"c"	=> false,
   		"admin" => false,
		"url" => $node_path . 'lenis/dist/lenis.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
    $plugins['jquery.simple-text-rotator'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path .'jquery.simple-text-rotator/jquery.simple-text-rotator.min.js',
		"css" => [
			$node_path .'jquery.simple-text-rotator/simpletextrotator.css'
		],
		"class" => ["text-rotator"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['masonry-layout'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path .'masonry-layout/dist/masonry.pkgd.min.js',
		"css" => [],
		"class" => [],
		"attrs" => ["data-masonry"],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['twig'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path . 'twig/twig.min.js',
		"css" => [],
		"class" => ["leaflet-custom", "googlemaps-custom"],
		"attrs" => [],
		"condition" => get_option("options_map_view") == "js" ? 1: 0,
		"init"     => "",
		"whitelist" => []
	];
	$plugins['leaflet'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path . 'leaflet/dist/leaflet.js',
		"css" => [
			$node_path . 'leaflet/dist/leaflet.css',
		],
		"class" => ["leaflet-custom"],
		"attrs" => [
            'data-ajax-method="map_modal"'
		],
		"condition" => get_option("options_map_view") == "js" && get_option("options_map_service") == "leaflet" ? 1: 0,
		"init"     => "init_leaflet",
		"whitelist" => []
	];
	$plugins['leaflet.markercluster'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path . 'leaflet.markercluster/dist/leaflet.markercluster.js',
		"css" => [
			$node_path . 'leaflet.markercluster/dist/MarkerCluster.css',
			$node_path . 'leaflet.markercluster/dist/MarkerCluster.Default.css'
		],
		"class" => ["leaflet-custom"],
		"attrs"  => [
			'data-ajax-method="map_modal"'
		],
		"condition" => get_option("options_map_view") == "js" && get_option("options_map_service") == "leaflet" ? 1: 0,
		"init"     => "",
		"whitelist" => []
	];
	$plugins['markerclusterer'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => $node_path . '@googlemaps/markerclusterer/dist/index.umd.js',
		"css" => [],
		"class" => ["googlemaps-custom"],
		"attrs"  => [
			'data-ajax-method="map_modal"'
		],
		"condition" => get_option("options_map_view") == "js" && get_option("options_map_service") == "google" ? 1: 0,
		"init"     => "init_google_maps",
		"whitelist" => []
	];

	$plugins['smarquee'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'smarquee/dist/smarquee.min.js',
		"css" => [],
		"class" => ["smarquee"],
		"attrs" => [],
		"init"     => "init_smarquee",
		"whitelist" => []  
	];

	$plugins['locomotive-scroll'] = [
		"c"	=> true,
		"admin" => false,
		"url" => $node_path . 'locomotive-scroll/bundled/locomotive-scroll.min.js',
		"css" => [
			$node_path . 'locomotive-scroll/bundled/locomotive-scroll.css',
		],
		"class" => [],
		"attrs" => ["data-scroll"],
		"init"     => "init_locomotive_scroll",
		"whitelist" => []  
	];


   if(!isset($plugins["smartmenu-bs"]) && $header_has_navigation){
	 	$plugins['smartmenus'] = [
	 		"c"   => false,
	 		"admin" => false,
			"url" => $node_path . 'smartmenus/dist/jquery.smartmenus.min.js',
			"css" => [],
			"class" => [],
			"attrs" => [],
			"init"     => "",
		    "whitelist" => []
		];
	   $plugins['smartmenus-bs'] = [
	   		"c"   => false, 
	   		"admin" => false,
			"url" => $node_path . 'smartmenus/dist/addons/bootstrap-4/jquery.smartmenus.bootstrap-4.min.js',
			"css" => [
				$node_path . 'smartmenus/dist/addons/bootstrap-4/jquery.smartmenus.bootstrap-4.css'
			],
			"class" => [],
			"attrs" => [],
			"init"     => "",
		    "whitelist" => []
		];
	}
    
    if(function_exists("compile_files_plugins")){
		$theme_plugins = compile_files_plugins($enable_production);
		if($theme_plugins){
			$plugins = array_merge($plugins, $theme_plugins);
		}    	
    }


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
	$header_js['defaults'] = $prod_path .'defaults.js';
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
		    $key = array_search('woo-filters.js', $functions);
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






	//$plugins['bootstrap-3-typeahead'] = $node_path . 'bootstrap-3-typeahead/bootstrap3-typeahead.js';
	//$plugins['bootstrap-select'] =     $node_path . 'bootstrap-select/dist/js/bootstrap-select.min.js';
	//$plugins['select2'] = $node_path .'select2/dist/js/select2.full.min.js';
	//$plugins['bootstrap-datepicker'] =  $node_path . 'bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js';
	//$plugins['timepicker'] =  $node_path . 'timepicker/jquery.timepicker.min.js';
	//$plugins['datepair.js'] =  $node_path . 'datepair.js/dist/jquery.datepair.min.js';
	//$plugins['imagesloaded'] = 	$node_path . 'imagesloaded/imagesloaded.pkgd.min.js';
	//$plugins['lazysizes-bgset'] = 	$node_path . 'lazysizes/plugins/bgset/ls.bgset.min.js';
	//$plugins['lazysizes'] =  $node_path . 'lazysizes/lazysizes.min.js';
	//$plugins['unitegallery'] = 	$node_path . 'unitegallery/dist/js/unitegallery.min.js';
	//$plugins['unitegallery-theme-tiles'] =	$node_path . 'unitegallery/dist/themes/tiles/ug-theme-tiles.js';
	//$plugins['js-cookie'] = 	$node_path . 'js-cookie/dist/js.cookie.min.js';
	//$plugins['hc-sticky'] =  $node_path . 'hc-sticky/dist/hc-sticky.js';
	//$plugins['autosize'] =   $node_path . 'autosize/dist/autosize.min.js';
	//$plugins['jquery-serializejson'] = 	$node_path . 'jquery-serializejson/jquery.serializejson.js';
	//$plugins['conditionize2'] = 	$node_path . 'conditionize2/jquery.conditionize2.min.js';
	//$plugins['jquery-validation'] =      	$node_path . 'jquery-validation/dist/jquery.validate.js';
	//$plugins['disableautofill'] =      	$node_path . 'disableautofill/src/jquery.disableAutoFill.min.js';
	//$plugins['inputmask'] =      	$node_path . 'inputmask/dist/jquery.inputmask.min.js';
	//$plugins['jquery.repeater'] = 	$node_path . 'jquery.repeater/jquery.repeater.min.js';
	//$plugins['aos'] = 	$node_path . 'aos/dist/aos.js';
	//$plugins['background-check'] =  $plugin_path . 'background-check/background-check.min.js';
	//$plugins['twig'] =  $node_path . 'twig/twig.min.js';
	//$plugins['bootstrap-input-spinner'] =  $node_path . 'bootstrap-input-spinner/src/bootstrap-input-spinner.js';
	//$plugins['image-uploader'] =  $plugin_path . 'image-uploader/dist/image-uploader.min.js';
	//$plugins['numeral'] =  $node_path . 'numeral/min/numeral.min.js';
	//$plugins['lodash'] = $node_path .'lodash/lodash.min.js';
	//$plugins['jquery-multiselect'] = $node_path .'@nobleclem/jquery-multiselect/jquery.multiselect.js';
	//$plugins['bootstrapv5-multiselect'] = $node_path .'bootstrapv5-multiselect/dist/js/bootstrap-multiselect.js';
	//$plugins['moment'] = $node_path . 'moment/min/moment.min.js';
	//$plugins['moment-timezone'] = $node_path . 'moment-timezone/moment-timezone.js';moment-timezone-with-data.js
	//$plugins['moment-timezone-data'] = $plugin_path . 'moment-timezone-data/moment-timezone-with-data.js';
   //$plugins['jquery-countdown'] = $node_path . 'jquery-countdown/dist/jquery.countdown.min.js';
   //$plugins['clndr'] = $node_path . 'clndr/clndr.min.js';
   //$plugins['leaflet'] =  $node_path . 'leaflet/dist/leaflet.js';
   //$plugins['leaflet-markercluster'] =  $node_path . 'leaflet.markercluster/dist/leaflet.markercluster.js';
   //$plugins['autocomplete-js'] =  $node_path . 'autocomplete-js/dist/autocomplete.min.js';
   //$plugins['simple-scrollbar'] =  $node_path . 'simple-scrollbar/simple-scrollbar.min.js';
   //$plugins['progressbar.js'] =  $node_path . 'progressbar.js/dist/progressbar.min.js';
   //$plugins['fancyapps'] =  $node_path . '@fancyapps/ui/dist/index.umd.js';
   //$plugins['fancybox'] =  $node_path . '@fancyapps/ui/dist/fancybox/fancybox.umd.js';
   //$plugins['slabtext'] =  $plugin_path . 'slabtext/js/jquery.slabtext.min.js';
   //$plugins['easyqrcodejs'] =  $node_path . 'easyqrcodejs/dist/easy.qrcode.min.js';
   //$plugins['print-this']   =  $node_path . 'print-this/printThis.js';
   //$plugins['sortablejs']   =  $node_path . 'sortablejs/Sortable.min.js';
   //$plugins['jquery-zoom']   =  $node_path . 'jquery-zoom/jquery.zoom.min.js';
   //$plugins['toast'] = $node_path .'jquery-toast-plugin/dist/jquery.toast.min.js';
   //$plugins['imgviewer2'] = $node_path .'imgviewer2/src/imgViewer2.js';
   //$plugins['scrollmagic']   =  $node_path . 'scrollmagic/scrollmagic/minified/ScrollMagic.min.js';
   if(ENABLE_FAVORITES || ENABLE_CART){
      //$plugins['simple-scrollbar'] = $node_path .'simple-scrollbar/simple-scrollbar.min.js';
   }


   //$header_css['bootstrap'] = $node_path . 'bootstrap/dist/css/bootstrap.css';
	//$header_css['smartmenu-bs'] = $node_path . 'smartmenus/dist/addons/bootstrap-4/jquery.smartmenus.bootstrap-4.css';
	//$header_css['unitegallery'] = $node_path . 'unitegallery/dist/css/unite-gallery.css';
	//$header_css['unitegallery-theme'] = $node_path . 'unitegallery/dist/themes/default/ug-theme-default.css';
	//$header_css['animate.css'] = $node_path . 'textillate/assets/animate.css';
	//$header_css['aos'] = 	$node_path . 'aos/dist/aos.css';
	//$header_css['jquery-ui'] = $plugin_path .'jquery-ui/jquery-ui.min.css';
	//$header_css['typeahead.js-bootstrap4-css'] = $node_path .'typeahead.js-bootstrap4-css/typeaheadjs.css';
	//$header_css['select2'] = $node_path .'select2/dist/css/select2.min.css';
	//$header_css['select2-bootstrap-5-theme'] = $node_path .'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
	//$header_css['image-uploader'] = $plugin_path .'image-uploader/dist/image-uploader.min.css';
	//$header_css['bootstrap-select'] =  $node_path . 'bootstrap-select/dist/css/bootstrap-select.min.css';
	//$header_css['multiselect'] = $node_path .'@nobleclem/jquery-multiselect/jquery.multiselect.css';
	//$header_css['bootstrapv5-multiselect'] = $node_path .'bootstrapv5-multiselect/dist/css/bootstrap-multiselect.min.css';
	//$header_css['timepicker'] =  $node_path . 'timepicker/jquery.timepicker.min.css';
	//$header_css['bootstrap-datepicker'] =  $node_path . 'bootstrap-datepicker/dist/css/bootstrap-datepicker3.standalone.min.css';
	//$header_css['leaflet'] =  $node_path . 'leaflet/dist/leaflet.css';
	//$header_css['leaflet-markercluster'] =  $node_path . 'leaflet.markercluster/dist/MarkerCluster.css';
	//$header_css['leaflet-markercluster-default'] =  $node_path . 'leaflet.markercluster/dist/MarkerCluster.Default.css';
	//$header_css['autocomplete'] =  $node_path . 'autocomplete-js/dist/autocomplete.min.css';
	//$header_css['simple-scrollbar'] =  $node_path . 'simple-scrollbar/simple-scrollbar.css';
	//$header_css['fancybox'] =  $node_path . '@fancyapps/ui/dist/fancybox/fancybox.css';
	//$header_css['slabtext'] =  $plugin_path . 'slabtext/css/slabtext.css';
	//$header_css['jquery-toast-plugin'] = $node_path .'jquery-toast-plugin/dist/jquery.toast.min.css';



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

