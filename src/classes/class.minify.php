<?php

use MatthiasMullie\Minify;
use Irmmr\RTLCss\Parser as RTLParser;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SaltMinifier{
    
    public $enable_production = false;
    public $is_development = true; // in localhost
    public $rules = [];

    public $css_folder;
    public $js_folder;
    public $js_uri;
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
        $this->js_uri = $this->rules["config"]["js_uri"];
        $this->prod_folder = $this->rules["config"]["prod"];
        $this->output = array(
            "header.css"           => $this->css_folder   . 'header.css',
            "header_admin.css"     => $this->css_folder   . 'header-admin.css',
            "main.css"             => $this->css_folder   . 'main.css',
            "plugins.min.js"       => $this->js_folder    . 'plugins.min.js',
            "plugins"              => $this->js_folder    . 'plugins/',
            "plugin_assets"        => $this->js_folder    . 'assets/',
            "plugin_assets_uri"    => $this->js_uri       . 'assets/',
            "plugins_init"         => $this->prod_folder  . 'plugins-init/',
            "plugins-admin.min.js" => $this->js_folder    . 'plugins-admin.min.js', 
            'jquery.min.js'        => $this->js_folder    . 'jquery.min.js',
            "header.min.js"        => $this->js_folder    . 'header.min.js',
            "functions.min.js"     => $this->js_folder    . 'functions.min.js',
            "main.min.js"          => $this->js_folder    . 'main.min.js',
            "main-combined.min.js" => $this->js_folder    . 'main-combined.min.js',

        );
        if(file_exists($this->output["plugins"])){
            $this->plugins_update = filemtime($this->output["plugins"]);
        }
        
        // rtl css files
        $this->rtl_list["main"] = $this->rules["config"]["css"] . 'main.css';
        $this->rtl_list["blocks"] = $this->rules["config"]["css"] . 'blocks.css';
        $this->rtl_list["common"] = $this->rules["config"]["css"] . 'common.css';


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
        if(in_array($item, ["main", "blocks", "header", "header_admin", "common"])){
            return $this->css_folder;
        }else{
            return $this->output["plugins"];
        }
    }
    public function rtl_css(){
        if($this->rtl_list){
            foreach($this->rtl_list as $key => $rtl_item){
                $file_name = $key."-rtl.css";
                $css = file_get_contents($rtl_item);
                
                //extract and store font-faces to preserve manupulation
                $fonts = "";
                preg_match_all('/@font-face\s*{([^}]+)}/s', $css, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $font = trim($match[0]);
                    $css = str_replace($match[0], "", $css);
                    $fonts .= $font;
                }
                
                // flip css for rtl
                $parser = new Sabberworm\CSS\Parser($css);
                $tree = $parser->parse();

                /*$rtlcss = new PrestaShop\RtlCss\RtlCss($tree);
                $rtlcss->flip();
                $css = $tree->render();*/

                $rtlcss = new RTLParser($tree);
                $rtlcss->flip();
                $css = $tree->render();

                // minify
                $minify = new Minify\CSS($css);
                $css = $minify->minify();
                
                // put back font faces and save
                $css = $fonts.$css;
                file_put_contents($this->get_rtl_folder($key) . $file_name, $css);
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
        $this->mergeJs([
            $this->output["functions.min.js"],
            $this->output["plugins.min.js"],
            $this->output["main.min.js"],
        ]);
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
            //$min_date = filemtime($this->output["functions.min.js"]);
            if ($this->functions){
                foreach ($this->functions as $key => $filename){
                    //if (filemtime($this->prod_folder . 'functions/' . $filename) > $min_date){
                        $minify = true;
                        //break;
                    //}
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
            //$min_date = filemtime($this->output["main.min.js"]);
            
            if ($this->main_js_files){
                foreach($this->main_js_files as $key => $filename){
                    $total_files[] = $this->prod_folder . 'main/' . $filename;
                    //if (filemtime($this->prod_folder . 'main/' . $filename) > $min_date){
                        $minify = true;
                        //break;
                    //}
                }
            }
            if ($this->theme_js_files){
                foreach($this->theme_js_files as $key => $filename){
                    $total_files[] = $this->rules["config"]["js_theme"] . $filename;
                    //if (filemtime($this->rules["config"]["js_theme"] . $filename) > $min_date){
                        $minify = true;
                        //break;
                    //}
                }
            }
        }else{
            if ($this->main_js_files){
                foreach($this->main_js_files as $key => $filename){
                    $total_files[] = $this->prod_folder . 'main/' . $filename;
                }
            }
            if ($this->theme_js_files){
                foreach($this->theme_js_files as $key => $filename){
                    $total_files[] = $this->rules["config"]["js_theme"] . $filename;
                }
            }
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
            $plugin_min_files = [];
            foreach ($this->rules["js"]["plugins"] as $key => $item) {
                
                $content = "";
                //$is_min = false;
                foreach ($item["url"] as $url_key => $url) {
                    error_log($url);
                    $url = str_replace(STATIC_URL, STATIC_PATH, $url);
                    if (!file_exists($url)) {
                        continue;
                    }
                    error_log("added");
                    if (strpos($url, '.min.js') !== false) {
                    //    $is_min = true;
                    }
                    //$url = $this->removeComments($url);
                    $content .= file_get_contents($url);
                }
                $ext = "";//$is_min ? '.min' : '';
                $file_path = $this->output["plugins"] . $key . $ext . '.js';
                file_put_contents($file_path, $content);

                $item_local = $this->save_as_local($key, $file_path); //
                if(!$item["c"]){
                    $plugin_min_files[] = $file_path;//$item_local;
                }

                $item_init = $this->output["plugins_init"] . $key . '.js';
                if (!file_exists($item_init)) {
                    file_put_contents($item_init, '');
                }
                $this->minify_js([$item_init], $this->output["plugins"] . $key . '-init.js');
                if(!$item["c"]){
                    //$plugin_min_files[] = $item_local;
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

    public function mergeJs($files = []) {
        if (empty($files)) return false;
        $combined = '';
        foreach ($files as $file_path) {
            if (file_exists($file_path)) {
                $combined .= file_get_contents($file_path)."\n\r";
            }
        }
        $target_file = $this->output["main-combined.min.js"];
        file_put_contents($target_file, $combined);
        @chmod($target_file, 0644);
       // return content_url(str_replace(WP_CONTENT_DIR, '', $target_file));
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
                    }
                }else{
                    $minify = new Minify\CSS($item);
                }
            }else{
                if(is_array($item)){
                    foreach($item as $item_url){
                        $minify->add($item_url);
                    }
                }else{
                    $minify->add($item);
                }
            }
            $counter ++;
        }
        if($files){
            $minify->minify($output);
            $this->compile_nested_css($output, true);
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

    /*public function compile_nested_css($css, $save = false) {
        if (!is_string($css)) {
            throw new \InvalidArgumentException("CSS parametresi string olmalı.");
        }

        $isFile = is_file($css);
        $content = $isFile ? file_get_contents($css) : $css;
        if ($isFile && $content === false) {
            throw new \RuntimeException("CSS dosyası okunamadı: " . $css);
        }
        $path = $isFile ? $css : null;

        // Nested CSS kontrolü
        if (!preg_match('/\{[^}]*[&>]\s*[\w.#:]/m', $content)) {
            return $content; // zaten normal CSS
        }

        // Temp dosya oluştur
        $tmpIn  = tempnam(sys_get_temp_dir(), 'css_in_') . '.css';
        $tmpOut = tempnam(sys_get_temp_dir(), 'css_out_') . '.css';
        $tmpIn  = str_replace('\\', '/', $tmpIn);
        $tmpOut = str_replace('\\', '/', $tmpOut);
        file_put_contents($tmpIn, $content);

        try {
            // Symfony Process ayarları
            $workingDir = get_stylesheet_directory();
            $currentUser = getenv('USERNAME') ?: getenv('USER');
            $nodeJsPath = 'C:\Program Files\nodejs';
            $npmPath = 'C:\Users\\' . $currentUser . '\AppData\Roaming\npm';

            $command = ['npx', 'postcss', $tmpIn, '--use', 'postcss-nested', '-o', $tmpOut];
            $process = new Process($command, $workingDir);
            $process->setEnv([
                'PATH' => getenv('PATH') . ';' . $nodeJsPath . ';' . $npmPath,
            ]);
            $process->setTimeout(null);
            $process->mustRun();

            $compiled = file_get_contents($tmpOut);

            // Kaydetme opsiyonu sadece dosya için geçerli
            if ($save && $path) {
                file_put_contents($path, $compiled);
                return true;
            }

            return $compiled;

        } catch (ProcessFailedException $e) {
            throw new \RuntimeException("CSS compile hatası: " . $e->getMessage());
        } finally {
            // Temp dosya temizliği
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }*/

    public function compile_nested_css($css, $save = false) {
        if (!is_string($css)) {
            throw new \InvalidArgumentException("CSS parametresi string olmalı.");
        }

        $isFile = is_file($css);
        $content = $isFile ? file_get_contents($css) : $css;
        if ($isFile && $content === false) {
            throw new \RuntimeException("CSS dosyası okunamadı: " . $css);
        }
        $path = $isFile ? $css : null;

        // Nested CSS kontrolü
        if (!preg_match('/\{[^}]*[&>]\s*[\w.#:]/m', $content)) {
            return $content; // zaten normal CSS
        }

        // Temp dosya oluştur
        $tmpIn  = tempnam(sys_get_temp_dir(), 'css_in_') . '.css';
        $tmpOut = tempnam(sys_get_temp_dir(), 'css_out_') . '.css';
        $tmpIn  = str_replace('\\', '/', $tmpIn);
        $tmpOut = str_replace('\\', '/', $tmpOut);
        file_put_contents($tmpIn, $content);

        try {
            // Symfony Process ile Node script çalıştır
            $workingDir = get_stylesheet_directory();
            $nodeScript = SH_PATH . 'js/compile-nested-css.js'; // Node script path

            $command = ['node', $nodeScript, $tmpIn, $tmpOut];
            $process = new Process($command, $workingDir);
            $process->setTimeout(null);
            $process->mustRun();

            $compiled = file_get_contents($tmpOut);

            // Kaydetme opsiyonu sadece dosya için geçerli
            if ($save && $path) {
                file_put_contents($path, $compiled);
                return true;
            }

            return $compiled;

        } catch (ProcessFailedException $e) {
            throw new \RuntimeException("CSS compile hatası: " . $e->getMessage());
        } finally {
            // Temp dosya temizliği
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    public function save_as_local($plugin="", $item=""){
        /*if(strpos($item, ".min.") === false){
            error_log("save_as_local 1.");
            $minify_individual = new Minify\JS($item);
            $minify_individual->minify($this->output["plugins"] . $plugin . '.js');
            $this->removeSourceMap($this->output["plugins"] . $plugin . '.js', "file");
        }else{*/
             error_log("save_as_local 2.");
            $content = file_get_contents($item);
            $content = $this->removeSourceMap($content, "source");
            file_put_contents($this->output["plugins"] . $plugin . '.js', $content);
        //}

        return $this->output["plugins"] . $plugin . '.js';
    }
    /*public function save_as_local($plugin = "", $item = "") {
        if (strpos($item, ".min.") === false) {
            // Eğer min değilse → .min.js olarak kaydet
            $minified_path = $this->output["plugins"] . $plugin . '.min.js';
            $minify_individual = new Minify\JS($item);
            $minify_individual->minify($minified_path);
            $this->removeSourceMap($minified_path, "file");
            return $minified_path;
        } else {
            // Zaten .min.js ise sadece temizle ve aynısını kullan
            $content = file_get_contents($item);
            $content = $this->removeSourceMap($content, "source");
            file_put_contents($this->output["plugins"] . $plugin . '.min.js', $content);
            return $this->output["plugins"] . $plugin . '.min.js';
        }
    }*/


    public function plugin_settings(){
        $this->set_plugin_versions();

        if($this->assets_check && $this->is_development){
            foreach($this->assets_check as $css){
                $this->plugin_assets($css);
            }
        }

        $updates = [];

        $js_list = [];
        $js_list_all = [];
        $js_list_conditional = [];
        $js_list_conditional_version = [];
        $js_list_conditional_set = [];

        $js_list[] = "jquery";
        $js_list_all[] = "jquery";

        foreach($this->rules["js"]["plugins"] as $key => $plugin){
            if(!$plugin["c"]){
                $js_list[] = $key;
            }else{
                $js_list_conditional[] = $key;
                $js_list_conditional_version[] = $key."|".$plugin["version"];
                
                $js_list_conditional_set[$key] = [
                    "js" =>       !empty($plugin["url"]) ? $this->rules["config"]["plugin_uri"] . $key.".js" : "",
                    "css" =>      !empty($plugin["css"]) ? $this->rules["config"]["plugin_uri"] . $key.".css" : "",
                    "js_init" =>  !empty($plugin["url"]) ? $this->rules["config"]["plugin_uri"] . $key."-init.js" : "",
                    "init" =>     !empty($plugin["init"]) ? $plugin["init"] : ""
                ];
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
            //error_log("updates_plugins:".json_encode($updates_plugins));
            $updates_init = $this->check_plugin_init_updates($this->plugins_update);
            //error_log("updates_init:".json_encode($updates_init));
            $updates = array_merge($updates_plugins, $updates_init);
            $updates = array_unique($updates);
            update_option("assets_plugins_conditional", $js_list_conditional_version); // Güncelle
        } else {
            add_option("assets_plugins_conditional", $js_list_conditional_version); // Yeni ekle
        }

        $js_list_conditional = json_encode($js_list_conditional);
        $js_json = get_stylesheet_directory() . '/static/js/js_files_conditional.json';
        file_put_contents($js_json, $js_list_conditional);


        $js_list_conditional_set = json_encode($js_list_conditional_set);
        $js_json = get_stylesheet_directory() . '/static/js/js_files_conditional_set.json';
        file_put_contents($js_json, $js_list_conditional_set);


        $this->rtl_css();

        return $updates;
    }

    public function plugin_assets($css_file = "") {
        //error_log("plugin_assets-----------------------------------");
        $enable_publish = get_option("options_enable_publish");
        $publish_url = "";
        if($enable_publish){
            $publish_url = get_option("options_publish_url");
        } 
        //error_log($css_file);
        $css = file_get_contents($css_file);

        $css_dir = dirname($css_file);

        preg_match_all('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i', $css, $matches, PREG_PATTERN_ORDER);

        if ($matches) {
            $assets = [];
            
            foreach ($matches[3] as $key => $match) {
                if (substr($match, 0, 5) != "data:") {
                    $relative_path = preg_replace('/\?.*$/', '', $match); // Parametreleri temizle
                    $file = basename($relative_path); // Dosya adını al

                    // Uzantı kontrolü yap
                    if (!preg_match('/\.[a-zA-Z0-9]+$/', $file)) {
                        // Eğer uzantı yoksa, sadece ilgili `url(...)` kısmını CSS'den temizle
                        $css = preg_replace('/\b[\w-]+\s*:\s*' . preg_quote($matches[0][$key], '/') . '\s*;?/', '', $css);
                    } else {
                        // Eğer uzantı varsa, işlemleri yap
                        $relative_path_parts = explode("node_modules", $relative_path);
                        $clean_url = get_home_path()."node_modules".$relative_path_parts[1];

                        $assets[] = array(
                            "code" => $matches[0][$key],
                            "url" => $match,
                            "file" => $file,
                            "clean_url" => $clean_url
                        );
                    }
                }
            }
            if ($assets) {
                if (!is_dir($this->output["plugin_assets"])) {
                    mkdir($this->output["plugin_assets"], 0755, true); 
                }
                foreach ($assets as $key => $asset) {
                    if (file_exists($asset["clean_url"]) && !is_dir($asset["clean_url"])) {
                        copy($asset["clean_url"], $this->output["plugin_assets"] . $asset["file"]);
                        $query = parse_url($asset["url"], PHP_URL_QUERY);
                        $final_url = $this->output["plugin_assets_uri"] . $asset["file"] . ($query ? '?' . $query : '');
                        if (!empty($publish_url)) {
                            //$final_url = str_replace(home_url(), $publish_url, $final_url);
                        }
                        $final_url = str_replace(STATIC_URL, "[STATIC_URL]", $final_url);
                        $css = str_replace($asset["url"], $final_url, $css);
                    }else{
                        if (file_exists($asset["url"]) && !is_dir($asset["url"])) {
                            copy($asset["url"], $this->output["plugin_assets"] . $asset["file"]);
                            $clean_url = explode('?', $asset["url"])[0];
                            $query = parse_url($asset["url"], PHP_URL_QUERY);
                            $final_url = $this->output["plugin_assets_uri"] . $asset["file"] . ($query ? '?' . $query : '');
                            if(!empty($publish_url)){
                                //$final_url = str_replace(home_url(), $publish_url, $final_url);
                            }
                            $final_url = str_replace(STATIC_URL, "[STATIC_URL]", $final_url);
                            $css = str_replace($asset["url"], $final_url, $css);
                        }
                    }
                }
            }
            file_put_contents($css_file, $css);
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

    public function removeSourceMap_v1($input, $type) {
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
    public function removeSourceMap_v2($input, $type) {
        if ($type === "file") {
            $source = file_get_contents($input);
            // Sadece "//# sourceMappingURL=" ile başlayan satırları sil
            $source = preg_replace('/^[ \t]*\/\/# sourceMappingURL=.*\.map\s*$/m', '', $source);
            file_put_contents($input, $source);
        } else if ($type === "source") {
            // Sadece "//# sourceMappingURL=" ile başlayan satırları sil
            return preg_replace('/^[ \t]*\/\/# sourceMappingURL=.*\.map\s*$/m', '', $input);
        } else {
            return $input;
        }
    }
    public function removeSourceMap($input, $type) {
    $pattern = '/\/\/[#@]\s*sourceMappingURL=.*?(\r?\n|$|(?=[;!]))/i';

    if ($type === "file") {
        $source = file_get_contents($input);
        $source = preg_replace($pattern, '', $source);
        file_put_contents($input, $source);
    } else if ($type === "source") {
        return preg_replace($pattern, '', $input);
    } else {
        return $input;
    }
}



    public function removeComments($input) {
        return $input;
    }

    public function extractFontFaces($icons_css_path, $font_faces_css_path) {
        if (!file_exists($icons_css_path)) {
            echo "icons.css bulunamadı: $icons_css_path";
            return;
        }

        $css_content = file_get_contents($icons_css_path);

        // font-face bloklarını yakala
        preg_match_all('/@font-face\s*{[^}]+}/i', $css_content, $matches);
        $font_faces = $matches[0] ?? [];

        if (!empty($font_faces)) {
            $cleaned_faces = array_map(function ($face) {
                // URL'leri düzelt: Windows path → URL path
                return preg_replace_callback('/url\((["\']?)([^)]+?)\1\)/i', function ($m) {
                    $url = str_replace('\\', '/', $m[2]); // ters slash düzelt
                    // Dosya sistemindeki path'ten site kökü çıkar
                    $theme_path = str_replace('\\', '/', get_template_directory());
                    $site_subfolder = getSiteSubfolder();
                    $relative_path = str_replace($theme_path, '', $url);
                    return "url('{$site_subfolder}wp-content/themes/" . get_template() . "{$relative_path}')";
                }, $face);
            }, $font_faces);

            // font-faces.css olarak kaydet
            file_put_contents($font_faces_css_path, implode("\n\n", $cleaned_faces));

            // icons.css içinden font-face'leri çıkar
            $icons_css_clean = str_replace($font_faces, '', $css_content);
            file_put_contents($icons_css_path, $icons_css_clean);
        }
    }
    public function relocateFontFaces($font_faces_css_path) {
        if (!file_exists($font_faces_css_path)) {
            echo "font-faces.css bulunamadı: $font_faces_css_path";
            return;
        }

        $css_content = file_get_contents($font_faces_css_path);

        $theme_path = str_replace('\\', '/', get_template_directory());
        $theme_uri = str_replace('\\', '/', get_template_directory_uri());
        $theme_slug = basename($theme_path);
        $site_subfolder = rtrim(getSiteSubfolder(), '/'); // mesela "/xekos"

        // URL'leri dönüştür
        $updated = preg_replace_callback(
            '/url\((["\']?)([^)]+?)\1\)/i',
            function ($m) use ($theme_path, $theme_uri, $theme_slug, $site_subfolder) {
                $raw_url = str_replace('\\', '/', $m[2]);

                // Fiziksel dizin bazlı path
                if (strpos($raw_url, $theme_path) === 0) {
                    $rel_path = str_replace($theme_path, '', $raw_url);
                    return "url('{$site_subfolder}/wp-content/themes/{$theme_slug}{$rel_path}')";
                }

                // URL içeriyorsa
                if (strpos($raw_url, $theme_uri) === 0) {
                    $rel_path = str_replace($theme_uri, '', $raw_url);
                    return "url('{$site_subfolder}/wp-content/themes/{$theme_slug}{$rel_path}')";
                }

                // ../fonts/... varsa
                if (preg_match('#\.\./fonts/([^\'")]+)#', $raw_url, $match)) {
                    return "url('{$site_subfolder}/wp-content/themes/{$theme_slug}/static/fonts/{$match[1]}')";
                }

                return $m[0]; // dokunma
            },
            $css_content
        );

        file_put_contents($font_faces_css_path, $updated);
    }
    public function clearFontfaces($css_url) {
        if (!file_exists($css_url)) {
            error_log("clearFontfaces: Dosya bulunamadı → $css_url");
            return false;
        }

        $content = file_get_contents($css_url);
        if (!$content) {
            error_log("clearFontfaces: Dosya okunamadı → $css_url");
            return false;
        }

        // @font-face bloklarını komple sil
        $cleaned_content = preg_replace('/@font-face\s*{[^}]+}/i', '', $content);

        // Artık başa tekrar ekleme yok — sadece kalan içerik
        file_put_contents($css_url, trim($cleaned_content));

        error_log("✓ Font-face blokları tamamen kaldırıldı → $css_url");
        return true;
    }

    function purge_page_assets_manifest() {
        $cache_manifest = rtrim(defined('STATIC_PATH') ? STATIC_PATH : __DIR__.'/', '/').'/cache-manifest/assets-manifest.json';
        if (file_exists($cache_manifest)) {
            unlink($cache_manifest); // cache sil
        }
        if (class_exists('PageAssetsExtractor')) {
            $extractor = new PageAssetsExtractor();
            $extractor->force_rebuild = true;
            $extractor->remove_purge_css();
            $extractor->remove_critical_css();
        }
    }


    public function init(){
        $this->css();
        return $this->js();
    }
}