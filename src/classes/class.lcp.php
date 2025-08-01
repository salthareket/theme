<?php

class Lcp{
	// lcp: type, tag, code, url, id
    Private $data = [];
    Private $view_type = "";
	public function __construct($data = []) {
		$this->view_type = "code";//"js";
		if($data){
			//error_log(" -> data var");
			$this->data = $data;
		}else{
			if(defined("SITE_ASSETS") && is_array(SITE_ASSETS)){
				if(isset(SITE_ASSETS["lcp"]["desktop"]) && SITE_ASSETS["lcp"]["desktop"] && isset(SITE_ASSETS["lcp"]["mobile"]) && SITE_ASSETS["lcp"]["mobile"]){
					$this->data = SITE_ASSETS["lcp"];				
				}
			}
		}
		if($this->data){
			if(isset($this->data["desktop"]["type"]) || isset($this->data["mobile"]["type"])){
               //error_log(" -> wp_head -> preloadCode");
			   add_action('wp_head', [$this, "preloadCode"], 0);
			}else{
				$this->no_cache();
			}
		}else{
			$this->no_cache();
		}
	}

	public function preloadCode(){
	    $preload = "";
	    $css = "";
	    $desktop = "";
	    $mobile = "";
	    
	    foreach($this->data as $key => $lcp){
	    	if($key == "mobile"){
	    		$mobile = $lcp["url"];
	    	}else{
	    		$desktop = $lcp["url"];
	    	}

	        if(!empty($lcp["code"])){
	            $css .= "@media (".($key=="mobile"?"max-width: 768px":"min-width: 769px").") {\n";
	            $css .= $lcp["code"] . "\n";
	            $css .= "}\n";
	        }
	        
	        if(!empty($lcp["url"]) && $this->view_type == "code"){
	            $preload .= '<link rel="preload" ';//' data-rocket-preload ';
	            $preload .= 'as="'.$lcp["type"].'" href="'.$lcp["url"].'" ';
	            $preload .= 'importance="high" fetchpriority="high" media="('.($key=="mobile"?"max-width: 768px":"min-width: 769px").')">' . "\n";               
	        }

	        /*if(!empty($lcp["url"])){
	            $preload .= '<link rel="preload" ';
	            $preload .= 'as="'.$lcp["type"].'" href="'.$lcp["url"].'" ';
	            $preload .= 'importance="high" fetchpriority="high">' . "\n";               
	        }*/
	    }
	    
	    if(!empty($preload)){
	        echo $preload;
	    }elseif(!empty($mobile) && !empty($desktop)){
	    	?>
	    	<script>
	    	(function() {
			    const link = document.createElement('link');
			    link.rel = 'preload';
			    link.as = 'image';
			    link.fetchPriority = 'high';

			    const isMobile = window.innerWidth <= 768; // kırılma noktan neyse

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

		error_log("class lcp -> no_cache");
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

		add_action('wp_footer', function(){
			?>
			    <script nowprocket>
				  (function () {
				    var script = document.createElement('script');
				    script.src = 'https://unpkg.com/web-vitals@4.2.4/dist/web-vitals.attribution.iife.js';
				    script.onload = function () {
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
		});
	}
	public function images(){
		$images = [];
		foreach($this->data as $key => $lcp){
			if($lcp["type"] != "css"){
				$images[] = [
					"id" => $lcp["id"],
					"url" => $lcp["url"]
				];
			}
		}
		return $images;
	}
	public function is_lcp($image) {
	    // Tüm LCP ID'lerini bir array'e çekelim.
	    $lcp_images = $this->images();
	    $lcp_ids    = array_map('intval', array_column($lcp_images, 'id'));
	    $lcp_urls   = array_column($lcp_images, 'url');
        
        error_log("image");
	    error_log(print_r($image, true));
	    error_log("lcps");
	    error_log(print_r($lcp_ids, true));

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