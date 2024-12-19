<?php

use MatthiasMullie\Minify;

class SaltMinifier{
    
    public $enable_production = false;
    public $is_development = true; // in localhost
	public $rules = [];
	public $css_folder;
	public $js_folder;
	public $min_folder;
    public $min_uri;
	public $prod_folder;
	public $output = [];
	public $assets_check = [];
	public $plugins_update = "";
	public $rtl_list = [];
	public $functions = [];
	public $main_js_files = [];
	public $theme_js_files = [];

	function __construct($enable_production = false, $is_development = true){
		$this->enable_production = $enable_production;
		$this->is_development = $is_development;

		$this->rules = compile_files_config();
		$this->css_folder = $this->rules["config"]["css"];
		$this->js_folder = $this->rules["config"]["js"];
		$this->min_folder = $this->rules["config"]["min"];
        $this->min_uri = $this->rules["config"]["min_uri"];
		$this->prod_folder = $this->rules["config"]["prod"];
		$this->output = array(
			"header.css"           => $this->css_folder . 'header.css',
			"header_admin.css"     => $this->css_folder . 'header-admin.css',
			"main.css"             => $this->css_folder . 'main.css',
			"plugins.min.js"       => $this->min_folder . 'plugins.min.js',
			"plugins"              => $this->min_folder . 'plugins/',
            "plugin_assets"        => $this->min_folder . 'assets/',
            "plugin_assets_uri"    => $this->min_uri . 'assets/',
            "plugins_init"         => $this->js_folder  . 'production/plugins-init/',
            "plugins-admin.min.js" => $this->min_folder . 'plugins-admin.min.js', 
            'jquery.min.js'        => $this->min_folder . 'jquery.min.js',
            "header.min.js"        => $this->min_folder . 'header.min.js',
            "functions.min.js"     => $this->min_folder . 'functions.min.js',
            "main.min.js"          => $this->min_folder . 'main.min.js',

		);
	    if(file_exists($this->output["plugins"])){
	        $this->plugins_update = filemtime($this->output["plugins"]);
	    }
        
        // rtl css files
	    $this->rtl_list["main"] = $this->rules["config"]["css"] . 'main.css';
	    $this->rtl_list["blocks"] = $this->rules["config"]["css"] . 'blocks.css';

        if (!file_exists($this->rules["config"]["min"])){
	        mkdir($this->rules["config"]["min"], 0777, true);
	    }

	    if (!file_exists($this->rules["config"]["locale"])){
            mkdir($this->rules["config"]["locale"], 0777, true);
        }

	    if (is_dir($this->output["plugins"])) {
            $files = glob($this->output["plugins"] . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            mkdir($this->output["plugins"], 0777, true); 
        }

        $this->functions = array_slice(scandir($this->prod_folder . 'functions/'), 2);
        if(!ENABLE_ECOMMERCE){
           if (isset($this->functions["wp-wc.js"])){
               unset($this->functions["wp-wc.js"]);
           }
        }else{
           if (!ENABLE_CART && isset($this->functions["wp-wc.js"])){
               unset($function_files["wp-wc.js"]);
           }
        }

        $this->main_js_files = array_slice(scandir($this->prod_folder . 'main/') , 2);
        $this->theme_js_files = array_slice(scandir($this->rules["config"]["js_theme"]) , 2);
	}

	public function css(){
        
        //duplicate main.css to main-admin.css
		$main_css = file_get_contents($this->output["main.css"]);
        file_put_contents($this->rules["config"]["css"] . 'main-admin.css', $main_css);

        if ($this->rules["css"]["header"]){
        	$this->minify_css($this->rules["css"]["header"], $this->output["header.css"], "header");
        }

        if ($this->rules["css"]["header_admin"]){
        	$this->minify_css($this->rules["css"]["header_admin"], $this->output["header_admin.css"], "header_admin");
        }
        
        $this->locale_css();

	}

	public function locale_css(){
		if ($this->rules["css"]["locale"]){
	        foreach ($this->rules["config"]["languages"] as $language){
	            $minify = new Minify\JS(" ");
	            foreach ($this->rules["css"]["locale"] as $item){
	                if (isset($item[$language])){
	                    $minify->add($item[$language]);
	                }
	            }
	            $minify->minify($this->rules["config"]["css"] . "locale-" . $language . '.css');
	        }
	    }else{
	        if ($this->rules["config"]["languages"]){
	            foreach ($this->rules["config"]["languages"] as $language){
	                file_put_contents($this->rules["config"]["css"] . "locale-" . $language . '.css', "");
	            }
	        }else{
	            file_put_contents($this->rules["config"]["css"] . "locale-" . $this->rules["config"]["language"] . '.css', "");
	        }
	    }
	}

    public function get_rtl_folder($item){
        if(in_array($item, ["main", "blocks", "header", "header_admin"])){
            return $this->css_folder;
        }else{
            return $this->output["plugins"];
        }
    }

	public function rtl_css(){
		if($this->rtl_list){
	        foreach($this->rtl_list as $key => $rtl_item){
                error_log("rtl convert: ".$rtl_item);
	            $file_name = $key."-rtl.css";
	            $css = file_get_contents($rtl_item);
	            $parser = new Sabberworm\CSS\Parser($css);
	            $tree = $parser->parse();
	            $rtlcss = new PrestaShop\RtlCss\RtlCss($tree);
	            $rtlcss->flip();
	            $output = $tree->render();
	            // minify
	            $minify = new Minify\CSS($output);
	            $minify->minify( $this->get_rtl_folder($key) . $file_name );
	            $assets_check[] = $file_name;
	        }
	    }
	}

	public function js(){
		if ($this->rules["js"]["jquery"]){
			$this->minify_js($this->rules["js"]["jquery"], $this->output["jquery.min.js"]);
		}
		if ($this->rules["js"]["header"]){
			$this->minify_js($this->rules["js"]["header"], $this->output["header.min.js"]);
		}
		$this->locale_js();
		$this->functions_js();
		$this->main_js();
		$this->plugins();
        $this->rtl_css();
		return $this->plugin_settings();
	}
	public function locale_js(){
		if ($this->rules["js"]["locale"]){
            if ($this->rules["config"]["languages"]){
                foreach ($this->rules["config"]["languages"] as $language){
                    $counter = 0;
                    foreach ($this->rules["js"]["locale"] as $item){
                        if(isset($item["file"])){
                            $file = $item["file"];
                            if (isset($item["exception"][$language])){
                                $file = str_replace("{lang}", $item["exception"][$language], $file);
                            }else{
                                $file = str_replace("{lang}", $language, $file);
                            }
                            $file = $this->removeComments($file);
                            if ($counter == 0){
                                $minify = new Minify\JS($file);
                            }else{
                                $minify->add($file);
                            }
                            $counter++;                            
                        }
                    }
                    if($counter>0){
                        $minify->minify($this->rules["config"]["locale"] . $language . '.js');
                    }
                }
            }else{
                $counter = 0;
                foreach ($this->rules["js"]["locale"] as $key => $item){
                    if(isset($item["file"])){
                        $file = $item["file"];
                        if ($item["exception"]){
                            if (isset($item["exception"][$this->rules["config"]["language"]])){
                                $file = str_replace("{lang}", $item["exception"][$this->rules["config"]["language"]], $file);
                            }
                        }else{
                            $file = str_replace("{lang}", $this->rules["config"]["language"], $file);
                        }
                        $file = $this->removeComments($file);
                        if ($counter == 0){
                            $minify = new Minify\JS($file);
                        }else{
                            $minify->add($file);
                        }
                        $counter++;
                    }
                }
                if($counter>0){
                    $minify->minify($this->rules["config"]["locale"] . $this->rules["config"]["language"] . '.js');
                }
            }
        }else{
            if ($this->rules["config"]["languages"]){
                foreach ($this->rules["config"]["languages"] as $language){
                    file_put_contents($this->rules["config"]["locale"] . $language . '.js', "");
                }
            }else{
                file_put_contents($minify->minify($this->rules["config"]["locale"] . $this->rules["config"]["language"] . '.js') , "");
            }
        }
	}

	public function functions_js(){
		$minify = false;
		if (file_exists($this->output["functions.min.js"])){
            $min_date = filemtime($this->output["functions.min.js"]);
            if ($this->functions){
                foreach ($this->functions as $key => $filename){
                    if (filemtime($this->prod_folder . 'functions/' . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
        }else{
            $minify = true;
        }
        if ($this->functions && $minify){
        	$this->minify_js($this->functions, $this->output["functions.min.js"], $this->prod_folder . 'functions/');
        }
	}

	public function main_js(){
		$minify = false;
		$total_files = [];
		if (file_exists($this->output["main.min.js"])){
            $min_date = filemtime($this->output["main.min.js"]);
            
            if ($this->main_js_files){
                foreach($this->main_js_files as $key => $filename){
                	$total_files[] = $this->prod_folder . 'main/' . $filename;
                    if (filemtime($this->prod_folder . 'main/' . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
            if ($this->theme_js_files){
                foreach($this->theme_js_files as $key => $filename){
                	$total_files[] = $this->rules["config"]["js_theme"] . $filename;
                    if (filemtime($this->rules["config"]["js_theme"] . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
        }else{
            $minify = true;
        }
        if($total_files && $minify){
            if ($total_files){
            	$this->minify_js($total_files, $this->output["main.min.js"]);
            }
        }
	}

	public function plugins(){
		if ($this->rules["js"]["plugins"]) {
            foreach ($this->rules["js"]["plugins"] as $key => $item) {
            	$item["url"] = $this->removeComments($item["url"]);
                $item_local = $this->save_as_local($key, $item["url"]);
                $item_init = $this->output["plugins_init"] . $key . '.js';
                if (!file_exists($item_init)) {
                    file_put_contents($item_init, '');
                }
                $this->minify_js([$item_init], $this->output["plugins"] . $key . '-init.js');
                if(!$item["c"]){
	                $plugin_min_files[] = $item_local;
	            }else{
	            	if($item["css"]){
	            		$this->minify_css($item["css"], $this->output["plugins"] . $key . '.css', $key);
	            	}
	            }
            }
            if($plugin_min_files){
                $this->minify_js($plugin_min_files, $this->output["plugins.min.js"]);
            }
        }
        if ($this->rules["js"]["plugins_admin"]){
        	$this->minify_js($this->rules["js"]["plugins_admin"], $this->output["plugins-admin.min.js"]);
        }
	}

	public function minify_css($files=[], $output = "", $filename=""){
		$counter = 0;
		foreach ($files as $key => $item){
            if ($counter == 0){
                if(is_array($item)){
                    foreach($item as $item_key => $item_url){
                        if($item_key == 0){
                            $minify = new Minify\CSS($item_url);
                        }else{
                            $minify->add($item_url);
                        }
                        //$this->assets_check[] = $item_url;
                        //$this->rtl_list[$filename] = $item_url;
                    }
                }else{
                    $minify = new Minify\CSS($item);
                    //$this->assets_check[] = $item;
                    //$this->rtl_list[$filename] = $item;
                }
            }else{
                if(is_array($item)){
                    foreach($item as $item_url){
                        $minify->add($item_url);
                        //$this->assets_check[] = $item_url;
                        //$this->rtl_list[$filename] = $item_url;
                    }
                }else{
                    $minify->add($item);
                    //$this->assets_check[] = $item;
                    //$this->rtl_list[$filename] = $item;
                }
            }
            $counter++;
        }
        if($files){
            $minify->minify($output);
            $this->assets_check[] = $output;
            $this->rtl_list[$filename] = $output;
        }
	}

	public function minify_js($files=[], $output = "", $path_prefix = ""){
		if($files){
			$counter = 0;
			foreach ($files as $key => $item){
	            if ($counter == 0){
	                if(is_array($item)){
	                    foreach($item as $item_key => $item_url){
	                        if($item_key == 0){
	                            $minify = new Minify\JS($path_prefix.$item_url);
	                        }else{
	                            $minify->add($path_prefix.$item_url);
	                        }
	                    }
	                }else{
	                    $minify = new Minify\JS($path_prefix.$item);
	                }
	            }else{
	                if(is_array($item)){
	                    foreach($item as $item_url){
	                        $minify->add($path_prefix.$item_url);
	                    }
	                }else{
	                    $minify->add($path_prefix.$item);
	                }
	            }
	            $counter++;
	        }
	        if($files){
	            $minify->minify($output);   
	        }			
		}
	}

	public function save_as_local($plugin="", $item=""){
		if(strpos($item, ".min.") === false){
            $minify_individual = new Minify\JS($item);
            $minify_individual->minify($this->output["plugins"] . $plugin . '.js');
            $this->removeSourceMap($this->output["plugins"] . $plugin . '.js', "file");
        }else{
            $content = file_get_contents($item);
            $content = $this->removeSourceMap($content, "source");
            file_put_contents($this->output["plugins"] . $plugin . '.js', $content);
        }
        return $this->output["plugins"] . $plugin . '.js';
	}

	public function plugin_settings(){
		$this->set_plugin_versions();

		if($this->assets_check && $this->is_development){
			foreach($this->assets_check as $css){
				$this->plugin_assets($css);
			}
		}

		$js_list = [];
        $js_list_all = [];
        $js_list_conditional = [];
        $js_list_conditional_version = [];

        $js_list[] = "jquery";
        $js_list_all[] = "jquery";

        foreach($this->rules["js"]["plugins"] as $key => $plugin){
            if(!$plugin["c"]){
                $js_list[] = $key;
            }else{
                $js_list_conditional[] = $key;
                $js_list_conditional_version[] = $key."|".$plugin["version"];
            }
            $js_list_all[] = $key;
        }
        $js_list = json_encode($js_list);
        $js_json = get_stylesheet_directory() . '/static/js/js_files.json';
        file_put_contents($js_json, $js_list);

        $js_list_all = json_encode($js_list_all);
        $js_json = get_stylesheet_directory() . '/static/js/js_files_all.json';
        file_put_contents($js_json, $js_list_all);

        $existing_meta = get_option("assets_plugins_conditional"); // Var olan option'u kontrol et
        if ($existing_meta) {
            $updates_plugins = $this->check_plugin_updates($existing_meta, $js_list_conditional_version);
            error_log("updates_plugins:".json_encode($updates_plugins));
            $updates_init = $this->check_plugin_init_updates($this->plugins_update);
            error_log("updates_init:".json_encode($updates_init));
            $updates = array_merge($updates_plugins, $updates_init);
            $updates = array_unique($updates);
            update_option("assets_plugins_conditional", $js_list_conditional_version); // Güncelle
        } else {
            add_option("assets_plugins_conditional", $js_list_conditional_version); // Yeni ekle
        }

        $js_list_conditional = json_encode($js_list_conditional);
        $js_json = get_stylesheet_directory() . '/static/js/js_files_conditional.json';
        file_put_contents($js_json, $js_list_conditional);

        return $updates;
	}

	public function plugin_assets($css_file = "") {
        error_log("plugin_assets-----------------------------------");
	    $enable_publish = get_option("options_enable_publish");
	    $publish_url = "";
	    if($enable_publish){
	        $publish_url = get_option("options_publish_url");
	    } 
        error_log($css_file);
	    $css = file_get_contents($css_file);

        $css_dir = dirname($css_file);
	    preg_match_all('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i', $css, $matches, PREG_PATTERN_ORDER);
	    
	    if ($matches) {
	        $assets = array();
	        /*foreach ($matches[3] as $key => $match) {
	            if (substr($match, 0, 5) != "data:") {
	                // Parametreleri kaldır
	                $file_path = preg_replace('/\?.*$/', '', $match); // Parametreleri temizle
	                $file = basename($file_path); // Sadece dosya adını al
	                $assets[] = array(
	                    "code" => $matches[0][$key],
	                    "url" => $match,
	                    "file" => $file,
	                    "clean_url" => $file_path // Temiz URL
	                );
	            }
	        }*/

            foreach ($matches[3] as $key => $match) {
                if (substr($match, 0, 5) != "data:") {
                    error_log("match:".$match);
                    $relative_path = preg_replace('/\?.*$/', '', $match); // Parametreleri temizle
                    /*$absolute_path = realpath($css_dir . DIRECTORY_SEPARATOR . $relative_path);

                    
                    if (preg_match('/^[a-zA-Z]:\\\\|^\//', $relative_path)) { // Windows ve Unix için tam yol kontrolü
                        $absolute_path = realpath($relative_path);
                    } else {
                        $absolute_path = realpath($css_dir . DIRECTORY_SEPARATOR . $relative_path);
                    }


                    if ($absolute_path === false) {
                        // Hata günlüğü
                        error_log("Dosya bulunamadı: " . $css_dir . DIRECTORY_SEPARATOR . $relative_path);
                        continue;
                    }*/

                    $relative_path_parts = explode("node_modules", $relative_path);
                    $relative_path = get_home_path()."node_modules".$relative_path_parts[1];


                    $file = basename($relative_path); // Sadece dosya adını al
                    $assets[] = array(
                        "code" => $matches[0][$key],
                        "url" => $match,
                        "file" => $file,
                        "clean_url" => $relative_path // Temiz URL
                    );
                }
            }
            if ($assets) {
                error_log(json_encode($assets));
                foreach ($assets as $key => $asset) {
                    // Dosya mevcutsa kopyala
                    if (file_exists($asset["clean_url"]) && !is_dir($asset["clean_url"])) {
                        error_log("copy(".$asset["clean_url"].", ".$this->output["plugin_assets"] . $asset["file"].")");
                        copy($asset["clean_url"], $this->output["plugin_assets"] . $asset["file"]);
                        $query = parse_url($asset["url"], PHP_URL_QUERY);
                        $final_url = $this->output["plugin_assets_uri"] . $asset["file"] . ($query ? '?' . $query : '');
                        if (!empty($publish_url)) {
                            $final_url = str_replace(home_url(), $publish_url, $final_url);
                        }
                        error_log("str_replace(".$asset["url"].", ".$final_url.", css)");
                        $css = str_replace($asset["url"], $final_url, $css);
                    }else{
                        if (file_exists($asset["url"]) && !is_dir($asset["url"])) {
                             error_log("copy(".$asset["url"].", ".$this->output["plugin_assets"] . $asset["file"].")");
                            copy($asset["url"], $this->output["plugin_assets"] . $asset["file"]);
                            $clean_url = explode('?', $asset["url"])[0];
                            $query = parse_url($asset["url"], PHP_URL_QUERY);
                            $final_url = $this->output["plugin_assets_uri"] . $asset["file"] . ($query ? '?' . $query : '');
                            if(!empty($publish_url)){
                                $final_url = str_replace(home_url(), $publish_url, $final_url);
                            }
                            error_log("str_replace(".$asset["url"].", ".$final_url.", css)");
                            $css = str_replace($asset["url"], $final_url, $css);
                        }
                    }
                }
                file_put_contents($css_file, $css);
            }
	        /*if ($assets) {
                error_log(json_encode($assets));
	            foreach ($assets as $key => $asset) {
	                // node_modules içindeki URL'leri kontrol et
	                $copy_file = explode("/node_modules/", $asset["clean_url"]);
	                
	                if (isset($copy_file[1])) {
	                    $copy_file = $copy_file[1];
	                    $source_path = $this->rules["config"]["node"] . $copy_file;
	                    
	                    // Dosya mevcutsa kopyala
	                    if (file_exists($source_path) && !is_dir($source_path)) {
	                        copy($source_path, $this->output["plugin_assets"] . $asset["file"]);
	                        $clean_url = explode('?', $asset["url"])[0];
	                        $query = parse_url($asset["url"], PHP_URL_QUERY);
	                        $final_url = $this->rules["config"]["min_uri"] . "assets/" . $asset["file"] . ($query ? '?' . $query : '');
	                        if(!empty($publish_url)){
	                            $final_url = str_replace(home_url(), $publish_url, $final_url);
	                        }
	                        $css = str_replace($asset["url"], $final_url, $css);
	                    }
	                } else {
	                    // Eğer CSS içinde bir URL varsa ve bu URL node_modules'dan gelmiyorsa, sadece kopyala
	                    if (file_exists($asset["url"]) && !is_dir($asset["url"])) {
	                        copy($asset["url"], $this->output["plugin_assets"] . $asset["file"]);
	                        $clean_url = explode('?', $asset["url"])[0];
	                        $query = parse_url($asset["url"], PHP_URL_QUERY);
	                        $final_url = $this->rules["config"]["min_uri"] . "assets/" . $asset["file"] . ($query ? '?' . $query : '');
	                        if(!empty($publish_url)){
	                            $final_url = str_replace(home_url(), $publish_url, $final_url);
	                        }
	                        $css = str_replace($asset["url"], $final_url, $css);
	                    }
	                }
	            }
	            file_put_contents($css_file, $css);
	        }*/
	    }
	}

	public function set_plugin_versions(){
	    $version = "1.0";
	    $path = ABSPATH ."package.json";
	    if (file_exists($path)) {
	        $package = file_get_contents($path);
	        $package = json_decode($package, true);
	        $depencies = $package["dependencies"];
	        foreach($this->rules["js"]["plugins"] as $key => $plugin){
	        	if(isset($depencies[$key])){
	        	    $version = str_replace("^", "", $depencies[$key]);
	        	}else{
	        		$version = "1.0";
	        	}
	        	$this->rules["js"]["plugins"][$key]["version"] = $version;
	        }
	    }
	}

	public function check_plugin_updates($old="", $new=""){
	    $updates = [];
	    $diff = array_diff($old, $new);
	    if($diff){
	        foreach ($diff as $item) {
	            $parts = explode('|', $item);
	            $updates[] = $parts[0];
	        }
	    }
	    return $updates;
	}

	public function check_plugin_init_updates($last_update="") {
	    $updates = [];
	    if (!empty($last_update)) {
	        $init_files = glob($this->output["plugins_init"] . '*.js');
	        foreach ($init_files as $init_file) {
	            if (filemtime($init_file) > $last_update) {
	                $file_name = basename($init_file);
	                $file_without_extension = pathinfo($file_name, PATHINFO_FILENAME);
	                $updates[] = $file_without_extension; // Uzantısız dosya adını ekle
	            }
	        }
	    }
	    return $updates;
	}

	public function removeSourceMap($input, $type) {
	    if($type == "file"){
	        $source = file_get_contents($input);
	        $source = preg_replace('/\/\/# sourceMappingURL=.*\.map\s*/', '', $source);
	        file_put_contents($input, $source);
	    }else if($type == "source"){
	        return preg_replace('/\/\/# sourceMappingURL=.*\.map\s*/', '', $input);
	    }else{
	        return $input;
	    }
	}

	public function removeComments($input) {
	    return $input;
	}

	public function init(){
		$this->css();
		return $this->js();
	}

}