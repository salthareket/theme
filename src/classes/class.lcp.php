<?php

class Lcp{
	// lcp: type, tag, code, url, id
    Private $data = [];
    Private $view_type = "";

	public function __construct($data = []) {
	    $this->view_type = "code";

	    // Gelen data varsa ata yoksa SITE_ASSETS'den al
	    if($data){
	        $this->data = $data;
	        error_log("[LCP] Gelen data var, count: ".count($data));
	    } else {
	        if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
	            $this->data = SITE_ASSETS["lcp"] ?? [];
	            error_log("[LCP] SITE_ASSETS'den data çekildi: ".print_r($this->data, true));
	        } else {
	            error_log("[LCP] Data yok ve SITE_ASSETS tanımlı değil");
	        }
	    }

	    // WP Rocket filter içinde LCP kontrolü
	    if(function_exists('rocket_cache_reject_uri')){
	        add_filter('rocket_cache_reject_uri', function($urls){
	            $is_mobile = wp_is_mobile();
	            $desktop_data_exists = isset(SITE_ASSETS["lcp"]["desktop"]["type"]) && SITE_ASSETS["lcp"]["desktop"]["type"];
	            $mobile_data_exists = isset(SITE_ASSETS["lcp"]["mobile"]["type"]) && SITE_ASSETS["lcp"]["mobile"]["type"];

	            error_log("[LCP] rocket_cache_reject_uri -> is_mobile: ".$is_mobile);
	            error_log("[LCP] Desktop data: ".($desktop_data_exists?"VAR":"YOK"));
	            error_log("[LCP] Mobile data: ".($mobile_data_exists?"VAR":"YOK"));

	            // Mobil cihaz ve mobile data yoksa cache’den çıkar
	            if($is_mobile && !$mobile_data_exists){
	                $urls[] = $_SERVER['REQUEST_URI'];
	                error_log("[LCP] Mobil cache bypass yapıldı: ".$_SERVER['REQUEST_URI']);
	            }

	            // Desktop cihaz ve desktop datası yoksa cache’den çıkar
	            if(!$is_mobile && !$desktop_data_exists){
	                $urls[] = $_SERVER['REQUEST_URI'];
	                error_log("[LCP] Desktop cache bypass yapıldı: ".$_SERVER['REQUEST_URI']);
	            }

	            return $urls;
	        }, 10, 1);
	    }

	    // Data varsa preload veya ölçüm logic’i çalışacak
	    if($this->data){
	        $desktop_data_exists = !empty($this->data["desktop"]["type"]);
	        $mobile_data_exists = !empty($this->data["mobile"]["type"]);
	        $is_mobile = wp_is_mobile();

	        error_log("[LCP] Device: ".($is_mobile?"MOBILE":"DESKTOP"));
	        error_log("[LCP] Desktop data: ".($desktop_data_exists?"VAR":"YOK"));
	        error_log("[LCP] Mobile data: ".($mobile_data_exists?"VAR":"YOK"));

	        //error_log(print_r($GLOBALS["site_config"], true));

	        // Eğer cache yoksa -> server-side ayrım
	        if(!isset($GLOBALS["site_config"]) || empty($GLOBALS["site_config"]["cached"])){
	            error_log("[LCP] Cache yok, server-side kontrol");
	            if(($is_mobile && !$mobile_data_exists) || (!$is_mobile && !$desktop_data_exists)){
	                error_log("[LCP] LCP ölçümü tetiklenecek");
	                $this->no_cache(); // LCP ölçümü tetikle
	            } else {
	                error_log("[LCP] Preload code çalışacak");
	                add_action('wp_head', [$this, "preloadCode"], 0);
	            }
	        } else {
	            // Cache’li sayfa -> client-side JS ile kontrol
	            error_log("[LCP] Cache var, preload code client-side ile çalışacak");
	            add_action('wp_head', [$this, "preloadCode"], 0);
	        }
	    } else {
	        error_log("[LCP] Data yok, LCP ölçümü tetiklenecek");
	        $this->no_cache();
	    }
	}

	public function preloadCode(){
	    $preload = "";
	    $css = "";
	    $desktop = "";
	    $mobile = "";

	    foreach($this->data as $key => $lcp){
	        $url = isset($lcp["url"]) ? $lcp["url"] : '';
	        if($key == "mobile"){
	            $mobile = $url;
	        }else{
	            $desktop = $url;
	        }

	        if(!empty($lcp["code"])){
	            $css .= "@media (".($key=="mobile"?"max-width: 768px":"min-width: 769px").") {\n";
	            $css .= $lcp["code"] . "\n";
	            $css .= "}\n";
	        }

	        if(!empty($url) && $this->view_type == "code"){
	            $preload .= '<link rel="preload" ';
	            $preload .= 'as="'.$lcp["type"].'" href="'.$url.'" ';
	            $preload .= 'importance="high" fetchpriority="high" media="('.($key=="mobile"?"max-width: 768px":"min-width: 769px").')">' . "\n";               
	        }
	    }

	    if(!empty($preload)){
	        echo $preload;
	    } elseif(!empty($mobile) && !empty($desktop)){
	        ?>
	        <script>
	        (function() {
	            const link = document.createElement('link');
	            link.rel = 'preload';
	            link.as = 'image';
	            link.fetchPriority = 'high';

	            const isMobile = window.innerWidth <= 768;

	            link.href = isMobile
	                ? "<?php echo $mobile; ?>"
	                : "<?php echo $desktop; ?>";

	            document.head.appendChild(link);
	        })();
	        </script>
	        <?php
	    }

	    if(!empty($css)){
	        echo "<style type='text/css'>\n";
	        echo $css;
	        echo "</style>\n";
	    }
	}

	private function no_cache(){
		if (wp_script_is('measure-lcp', 'enqueued') || did_action('wp_enqueue_scripts') > 0) {
	        return;
	    }

	    error_log("[LCP]->no_cache: Sayfa cache'lenmeyecek.");

		add_filter('rocket_override_cache_during_dev', '__return_true');
		if(!defined('DONOTCACHEPAGE')){
			define('DONOTCACHEPAGE', true);
		}
		if(!defined('DONOTCACHEOBJECT')){
			define('DONOTCACHEOBJECT', true);
		}
		if(!defined('DONOTCACHEDB')){
			define('DONOTCACHEDB', true);
		}
		add_filter('rocket_cache_reject_uri', function ($urls) {
		    $urls[] = $_SERVER['REQUEST_URI'];
		    return $urls;
		});
		add_action( 'send_headers', function() {
	        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
   			header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
    		header("Pragma: no-cache");
		});
		
		add_action("wp_enqueue_scripts", function(){
			if (!wp_script_is('measure-lcp', 'registered')) {
		        wp_register_script('measure-lcp', SH_STATIC_URL .'js/measure-lcp.js' , array(), '1.0.0', false);
				wp_enqueue_script('measure-lcp');
			}
		}, 20);
		add_filter('script_loader_tag', function ($tag, $handle) {
			if (strpos($handle, 'measure-lcp') !== false || strpos($handle, 'web-vitals') !== false) { // header- ile başlayan scriptlere uygula
		        $tag = str_replace('src=', 'defer nowprocket src=', $tag);
		    }
		    return $tag;
		}, 10, 2);

		/*add_action('wp_footer', function(){
			?>
			    <script nowprocket>
				  (function () {
				    var script = document.createElement('script');
				    script.src = 'https://unpkg.com/web-vitals@4.2.4/dist/web-vitals.attribution.iife.js';
				    script.onload = function () {
				    	let checkAllPlatforms = true;
				        webVitals.onLCP((metric) => {
					        console.log(metric);
					        console.log(metric.attribution);
					        console.log(metric.attribution.element);
					        console.log(metric.attribution.url);
					        if (window.innerWidth > 450) {
						        <?php 
						        if(!isset(SITE_ASSETS["lcp"]["desktop"]["type"])){ ?>
		                           lcp_data_save(metric, "desktop");
						        <?php
						        }
						        if(!isset(SITE_ASSETS["lcp"]["mobile"]["type"])){ ?>
		                           lcp_for_mobile("<?php echo(current_url());?>");
						        <?php
						        }
						        ?>
						    }else{
						    	<?php 
						          if(!isset(SITE_ASSETS["lcp"]["mobile"]["type"])){ ?>
		                              lcp_data_save(metric, "mobile");
						        <?php
						        }
						        ?>
						    }
				        });
				        document.body.click(); 
				    };
				    document.head.appendChild(script);
				  })();
				</script>
			<?php
		});*/
		add_action('wp_footer', function(){
		    ?>
		    <script nowprocket>
		      (function () {
		        var script = document.createElement('script');
		        script.src = 'https://unpkg.com/web-vitals@4.2.4/dist/web-vitals.attribution.iife.js';
		        script.onload = function () {

		            // Sen burayı kontrol edeceksin
		            let checkAllPlatforms = false; 

		            webVitals.onLCP((metric) => {
		                const isMobile = window.innerWidth <= 450; // userAgent da eklenebilir
		                console.log("Check mode:", checkAllPlatforms ? "ALL" : "CURRENT");
		                console.log(metric);

		                if (checkAllPlatforms) {
		                    // --- Mevcut mantık: her iki platformu da kontrol et ---
		                    if (!isMobile) {
		                        <?php if(!isset(SITE_ASSETS["lcp"]["desktop"]["type"])){ ?>
		                            lcp_data_save(metric, "desktop");
		                        <?php } ?>
		                        <?php if(!isset(SITE_ASSETS["lcp"]["mobile"]["type"])){ ?>
		                            lcp_for_mobile("<?php echo(current_url());?>");
		                        <?php } ?>
		                    } else {
		                        <?php if(!isset(SITE_ASSETS["lcp"]["mobile"]["type"])){ ?>
		                            lcp_data_save(metric, "mobile");
		                        <?php } ?>
		                    }
		                } else {
		                    // --- Yeni mantık: bulunduğun platforma göre sadece 1 ölçüm yap ---
		                    if (!isMobile) {
		                        <?php if(!isset(SITE_ASSETS["lcp"]["desktop"]["type"])){ ?>
		                            lcp_data_save(metric, "desktop");
		                        <?php } ?>
		                    } else {
		                        <?php if(!isset(SITE_ASSETS["lcp"]["mobile"]["type"])){ ?>
		                            lcp_data_save(metric, "mobile");
		                        <?php } ?>
		                    }
		                }
		            });

		            document.body.click(); 
		        };
		        document.head.appendChild(script);
		      })();
		    </script>
		    <?php
		});
	}
	public function images(){
	    $images = [];
	    if($this->data){
	        foreach($this->data as $key => $lcp){
	            // "type" anahtarının varlığını kontrol et
	            if(isset($lcp["type"]) && $lcp["type"] != "css"){
	                // "id" ve "url" anahtarlarının varlığını kontrol et
	                if(isset($lcp["id"]) && isset($lcp["url"])){
	                    $images[] = [
	                        "id" => $lcp["id"],
	                        "url" => $lcp["url"]
	                    ];
	                }
	            }
	        }
	    }
	    return $images;
	}
	public function is_lcp($image) {
	    // Tüm LCP ID'lerini bir array'e çekelim.
	    $lcp_images = $this->images();
	    if(!$lcp_images){
	    	return false;
	    }
	    $lcp_ids    = array_map('intval', array_column($lcp_images, 'id'));
	    $lcp_urls   = array_column($lcp_images, 'url');
        
        //error_log("image");
	    //error_log(print_r($image, true));
	    //error_log("lcps");
	    //error_log(print_r($lcp_ids, true));

	    // Eğer string (URL) verilmişse
	    if (is_string($image)) {
	        return in_array($image, $lcp_urls, true);
	    }

	    // Eğer numeric (attachment ID) verilmişse
	    if (is_numeric($image)) {
	        return in_array((int)$image, $lcp_ids, true);
	    }

	    // Eğer object (image objesi) verilmişse
	    if (is_object($image) && isset($image->id)) {
	        return in_array((int)$image->id, $lcp_ids, true);
	    }

	    // Eğer array (breakpoint'li olabilir) verilmişse
	    if (is_array($image)) {
	        foreach ($image as $item) {
	            if (is_numeric($item) && in_array((int)$item, $lcp_ids, true)) {
	                return true;
	            } elseif (is_array($item) && isset($item['id']) && in_array((int)$item['id'], $lcp_ids, true)) {
	                return true;
	            }
	        }
	    }

	    return false;
	}

}