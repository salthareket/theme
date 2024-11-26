<?php
use MatthiasMullie\Minify;

function compile_files($enable_production = false, $is_development = true){

    $rules = compile_files_config();
    $assets_check = [];
    
    $last_update = "";
    $plugins_file = $rules["config"]["min"] . 'plugins.min.js';
    if(file_exists($plugins_file)){
        $last_update = filemtime($plugins_file);
    }
    error_log("last_update:".$last_update);
    

    
    // clear caches
    /*$css_cache = get_stylesheet_directory() . '/static/css/cache/';
    $js_cache = get_stylesheet_directory() . '/static/js/cache/';
    $cache = [$css_cache, $js_cache];
    foreach($cache as $dir){
        if (!is_dir($dir)) {
            continue;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = $dir . '/' . $file;
                if (is_dir($file_path)) {
                    delete_directory($file_path);
                } else {
                    unlink($file_path);
                }
            }
        }
        rmdir($dir);
    }*/


    // copy main.css to main-blocks.css
    $main_css = file_get_contents($rules["config"]["css"] . 'main.css');
    file_put_contents($rules["config"]["css"] . 'main-admin.css', $main_css);

    
    $rtl_list = array();
    $rtl_list[] = $rules["config"]["css"] . 'main.css';
    $rtl_list[] = $rules["config"]["css"] . 'blocks.css';

    if (!file_exists($rules["config"]["min"])){
        mkdir($rules["config"]["min"], 0777, true);
    }

    // header.min.css
    if ($rules["css"]["header"]){
        $counter = 0;
        foreach ($rules["css"]["header"] as $key => $item){
            if ($counter == 0){
                if(is_array($item)){
                    foreach($item as $item_key => $item_url){
                        if($item_key == 0){
                            $minify = new Minify\CSS($item_url);
                            $counter++;
                        }else{
                            $minify->add($item_url);
                            $counter++;
                        }
                    }
                }else{
                    $minify = new Minify\CSS($item);
                    $counter++;
                }
            }else{
                if(is_array($item)){
                    foreach($item as $item_url){
                        $minify->add($item_url);
                        $counter++;
                    }
                }else{
                    $minify->add($item);
                    $counter++;
                }
            }
        }
        if($counter > 0){
            $minify->minify($rules["config"]["css"] . 'header.css');
            $assets_check[] = $rules["config"]["css"] . 'header.css';
            //plugin_assets($rules, $rules["config"]["css"] . 'header.css');
            $rtl_list[] = $rules["config"]["css"] . 'header.css';            
        }
    }


    // header-admin.min.css
    if ($rules["css"]["header_admin"]){
        $counter = 0;
        foreach ($rules["css"]["header_admin"] as $item){
            if ($counter == 0){
                if(is_array($item)){
                    foreach($item as $key => $item_url){
                        if($key == 0){
                            $minify = new Minify\CSS($item_url);
                            $counter++;
                        }else{
                            $minify->add($item_url);
                            $counter++;
                        }
                    }
                }else{
                    $minify = new Minify\CSS($item);
                    $counter++;
                }
            }else{
                if(is_array($item)){
                    foreach($item as $item_url){
                        $minify->add($item_url);
                        $counter++;
                    }
                }else{
                    $minify->add($item);
                    $counter++;
                }
            }
        }
        if($counter > 0){
            $minify->minify($rules["config"]["css"] . 'header-admin.css');
        }
    }

    // css locale
    /*if (!file_exists($rules["config"]["css"]."locale/")) {
     mkdir($rules["config"]["locale"]."locale/", 0777, true);
    }*/
    if ($rules["css"]["locale"]){
        foreach ($rules["config"]["languages"] as $language){
            $minify = new Minify\JS(" ");
            foreach ($rules["css"]["locale"] as $item){
                if (isset($item[$language])){
                    $minify->add($item[$language]);
                }
            }
            $minify->minify($rules["config"]["css"] . "locale-" . $language . '.css');
        }
    }else{
        if ($rules["config"]["languages"]){
            foreach ($rules["config"]["languages"] as $language){
                file_put_contents($rules["config"]["css"] . "locale-" . $language . '.css', "");
            }
        }else{
            file_put_contents($rules["config"]["css"] . "locale-" . $rules["config"]["language"] . '.css', "");
        }
    }



    if(!$enable_production && $is_development){

        // header.min.js
        if ($rules["js"]["jquery"]){
            $counter = 0;
            foreach ($rules["js"]["jquery"] as $item){
                if ($counter == 0){
                    $minify = new Minify\JS($item);
                }else{
                    $minify->add($item);
                }
                $counter++;
            }
            $minify->minify($rules["config"]["min"] . 'jquery.min.js');
        }

        // header.min.js
        if ($rules["js"]["header"]){
            $counter = 0;
            foreach ($rules["js"]["header"] as $item){
                $item = removeComments($item);
                if ($counter == 0){
                    $minify = new Minify\JS($item);
                }else{
                    $minify->add($item);
                }
                $counter++;
            }
            $minify->minify($rules["config"]["min"] . 'header.min.js');
            //minify_forced($rules["config"]["min"] . 'header.min.js');
        }

        // locale files
        if (!file_exists($rules["config"]["locale"])){
            mkdir($rules["config"]["locale"], 0777, true);
        }
        if ($rules["js"]["locale"]){
            if ($rules["config"]["languages"]){
                foreach ($rules["config"]["languages"] as $language){
                    $counter = 0;
                    foreach ($rules["js"]["locale"] as $item){
                        if(isset($item["file"])){
                            $file = $item["file"];
                            if (isset($item["exception"][$language])){
                                $file = str_replace("{lang}", $item["exception"][$language], $file);
                            }else{
                                $file = str_replace("{lang}", $language, $file);
                            }
                            $file = removeComments($file);
                            if ($counter == 0){
                                $minify = new Minify\JS($file);
                            }else{
                                $minify->add($file);
                            }
                            $counter++;                            
                        }
                    }
                    if($counter>0){
                        $minify->minify($rules["config"]["locale"] . $language . '.js');
                    }
                }
            }else{
                $counter = 0;
                foreach ($rules["js"]["locale"] as $key => $item){
                    if(isset($item["file"])){
                        $file = $item["file"];
                        if ($item["exception"]){
                            if (isset($item["exception"][$rules["config"]["language"]])){
                                $file = str_replace("{lang}", $item["exception"][$rules["config"]["language"]], $file);
                            }
                        }else{
                            $file = str_replace("{lang}", $rules["config"]["language"], $file);
                        }
                        $file = removeComments($file);
                        if ($counter == 0){
                            $minify = new Minify\JS($file);
                        }else{
                            $minify->add($file);
                        }
                        $counter++;
                    }
                }
                if($counter>0){
                    $minify->minify($rules["config"]["locale"] . $rules["config"]["language"] . '.js');
                }
            }
        }else{
            if ($rules["config"]["languages"]){
                foreach ($rules["config"]["languages"] as $language){
                    file_put_contents($rules["config"]["locale"] . $language . '.js', "");
                }
            }else{
                file_put_contents($minify->minify($rules["config"]["locale"] . $rules["config"]["language"] . '.js') , "");
            }
        }

        // functions
        $folder = "functions";
        $minify = false;
        $file_minified = $rules["config"]["min"] . $folder . '.min.js';
        $function_files = array_slice(scandir($rules["config"]["prod"] . $folder . '/') , 2);

        if(!ENABLE_ECOMMERCE){
           /*if (isset($function_files["woo-filters.js"])){
               unset($function_files["woo-filters.js"]);
           }*/
           if (isset($function_files["wp-wc.js"])){
               unset($function_files["wp-wc.js"]);
           }
        }else{
           /*if (!ENABLE_FILTERS && isset($function_files["woo-filters.js"])){
              unset($function_files["woo-filters.js"]);
           }*/
           if (!ENABLE_CART && isset($function_files["wp-wc.js"])){
               unset($function_files["wp-wc.js"]);
           }
        }
        if (file_exists($file_minified)){
            $min_date = filemtime($file_minified);
            if ($function_files){
                foreach ($function_files as $key => $filename){
                    if (filemtime($rules["config"]["prod"] . $folder . '/' . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
        }else{
            $minify = true;
        }
        if ($function_files && $minify){
            foreach ($function_files as $key => $filename){
                $file_path = $rules["config"]["prod"] . $folder . '/' . $filename;
                $file_path = removeComments($file_path);
                if ($key == 0){
                    $minifier = new Minify\JS($file_path);
                }else{
                    $minifier->add($file_path);
                }
            }
            $minifier->minify($file_minified);
            //minify_forced($file_minified);
        }

        // main
        $folder = "main";
        $minify = false;
        $file_minified = $rules["config"]["min"] . $folder . '.min.js';
        $main_files = array_slice(scandir($rules["config"]["prod"] . $folder . '/') , 2);
        $theme_files = array_slice(scandir($rules["config"]["js_theme"]) , 2);
        if (file_exists($file_minified)){
            $min_date = filemtime($file_minified);
            if ($main_files){
                foreach ($main_files as $key => $filename){
                    if (filemtime($rules["config"]["prod"] . $folder . '/' . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
            if ($theme_files){
                foreach ($theme_files as $key => $filename){
                    if (filemtime($rules["config"]["js_theme"] . $filename) > $min_date){
                        $minify = true;
                        break;
                    }
                }
            }
        }else{
            $minify = true;
        }
        if(($main_files || $theme_files) && $minify){
            //$main_files = array_reverse($main_files);
            if ($main_files){
                foreach ($main_files as $key => $filename){
                    $file_path = $rules["config"]["prod"] . $folder . '/' . $filename;
                    $file_path = removeComments($file_path);
                    if ($key == 0){
                        $minifier = new Minify\JS($file_path);
                    }else{
                        $minifier->add($file_path);
                    }
                }
            }
            if($theme_files){
                foreach ($theme_files as $key => $filename){
                    $file_path = $rules["config"]["js_theme"] . $filename;
                    $file_path = removeComments($file_path);
                    if ($key == 0 && count($main_files)==0){
                        $minifier = new Minify\JS($file_path);
                    }else{
                        $minifier->add($file_path);
                    }
                }               
            }
            $minifier->minify($file_minified);
            //minify_forced($file_minified);
        }
        
        // plugins js
        if ($rules["js"]["plugins"]) {
            $counter = 0;
            $plugin_dir = $rules["config"]["min"] . 'plugins/';
            $plugin_init_dir = $rules["config"]["js"] . 'production/plugins-init/';
            if (is_dir($plugin_dir)) {
                $files = glob($plugin_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            } else {
                mkdir($plugin_dir, 0777, true); 
            }
            foreach ($rules["js"]["plugins"] as $key => $item) {
                if($item["c"]){
                    
                    $item = removeComments($item["url"]);
                    if(strpos($item, ".min.") === false){
                        $minify_individual = new Minify\JS($item);
                        $minify_individual->minify($plugin_dir . $key . '.js');
                        removeSourceMap($plugin_dir . $key . '.js', "file");
                    }else{
                        $content = file_get_contents($item);
                        $content = removeSourceMap($content, "source");
                        file_put_contents($plugin_dir . $key . '.js', $content);
                    }
                    if (!file_exists($plugin_init_dir . $key . '.js')) {
                        file_put_contents($plugin_init_dir . $key . '.js', '');
                    }
                    // init files
                    $item = removeComments($plugin_init_dir . $key . ".js" );
                    $minify_individual_init = new Minify\JS($item);
                    $minify_individual_init->minify($plugin_dir . $key . '-init.js');

                }else{

                    $item = removeComments($item["url"]);
                    if(strpos($item, ".min.") === false){
                        $minify_individual = new Minify\JS($item);
                        $minify_individual->minify($plugin_dir . $key . '.js');
                        removeSourceMap($plugin_dir . $key . '.js', "file");
                    }else{
                        $content = file_get_contents($item);
                        $content = removeSourceMap($content, "source");
                        file_put_contents($plugin_dir . $key . '.js', $content);
                    }
                    if (!file_exists($plugin_init_dir . $key . '.js')) {
                        file_put_contents($plugin_init_dir . $key . '.js', '');
                    }
                    // init files
                    $item = removeComments($plugin_init_dir . $key . ".js" );
                    $minify_individual_init = new Minify\JS($item);
                    $minify_individual_init->minify($plugin_dir . $key . '-init.js');

                    if ($counter == 0) {
                        $minify_combined = new Minify\JS($item); // İlk dosyayı başlat
                    } else {
                        $minify_combined->add($item); // Sonraki dosyaları ekle
                    }
                    $counter++;

                }
            }
            if($counter > 0){
                $minify_combined->minify($rules["config"]["min"] . 'plugins.min.js');
                //removeSourceMap($rules["config"]["min"] . 'plugins.min.js', "file");
                //minify_forced($rules["config"]["min"] . 'plugins.min.js');                
            }
        }
        
        // plugin css files to min
        if ($rules["js"]["plugins"]){
            $plugin_dir = $rules["config"]["min"] . 'plugins/';
            foreach ($rules["js"]["plugins"] as $key => $item){
                if($item["c"] && $item["css"]){
                    if(is_array($item["css"])){
                        $counter = 0;
                        foreach($item["css"] as $key_item => $item_url){
                            if($counter == 0){
                                $minify_individual = new Minify\CSS($item_url);
                            }else{
                                $minify_individual->add($item_url); 
                            }
                            $counter++;  
                        }
                        if($counter > 0){
                            $minify_individual->minify($plugin_dir . $key . '.css');
                            //plugin_assets($rules, $plugin_dir . $key . '.css');
                            $assets_check[] = $plugin_dir . $key . '.css';
                            $rtl_list[] = $plugin_dir . $key . '.css';
                        }
                    }else{
                        $minify_individual = new Minify\CSS($item["css"]);
                        $minify_individual->minify($plugin_dir . $key . '.css');
                        $assets_check[] = $plugin_dir . $key . '.css';
                        //plugin_assets($rules, $plugin_dir . $key . '.css');
                        $rtl_list[] = $plugin_dir . $key . '.css';
                    }
                }
            }
        }
        
        // plugins admin
        if ($rules["js"]["plugins_admin"]){
            $counter = 0;
            foreach ($rules["js"]["plugins_admin"] as $item){
                $item = removeComments($item);
                if ($counter == 0){
                    $minify = new Minify\JS($item);
                }else{
                    $minify->add($item);
                }
                $counter++;
            }
            if($counter > 0){
                $minify->minify($rules["config"]["min"] . 'plugins-admin.min.js');                
            }
        }

    }

    $updates = [];
    $updates_plugins = [];
    $updates_init = [];
    
    if($is_development){

        // plugins version
        if ($rules["js"]["plugins"]){
            foreach ($rules["js"]["plugins"] as $key => $item){
                $rules["js"]["plugins"][$key]["version"] = get_plugin_version($rules, $key);
            }
        }

        //save required js keys
        $js_list = [];
        $js_list_all = [];
        $js_list_conditional = [];
        $js_list_conditional_version = [];

        $js_list[] = "jquery";
        $js_list_all[] = "jquery";

        foreach($rules["js"]["plugins"] as $key => $plugin){
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
            $updates_plugins = check_plugin_updates($existing_meta, $js_list_conditional_version);
            error_log("updates_plugins:".json_encode($updates_plugins));
            $updates_init = check_plugin_init_updates($rules, $last_update);
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
    }

    //rtl process
    $rtl_list[] = $rules["config"]["css"] . 'blocks.css';
    if($rtl_list){
        //error_log(json_encode($rtl_list));
        foreach($rtl_list as $rtl_item){

            $file_name = str_replace(".css", "-rtl.css", $rtl_item);

            $css = file_get_contents($rtl_item);

            $parser = new Sabberworm\CSS\Parser($css);
            $tree = $parser->parse();
            $rtlcss = new PrestaShop\RtlCss\RtlCss($tree);
            $rtlcss->flip();
            $output = $tree->render();

            // minify
            $minify = new Minify\CSS($output);
            $minify->minify($file_name);
            $assets_check[] = $file_name;
            //file_put_contents($file_name, $output);
        }
    }

    if($assets_check){
        foreach($assets_check as $check){
            plugin_assets($rules, $check);
        }
    }

    return $updates;
}

function minify_forced($path=""){
    //$minifiedContent = file_get_contents($path);
    //$minifiedContent = preg_replace('!/\*.*?\*/!s', '', $minifiedContent);
    //$minifiedContent = preg_replace('/\s+/', ' ', $minifiedContent);
    //file_put_contents($path, $minifiedContent);
}

function plugin_assets($rules = array(), $css_file = "") {
    $assets_path = $rules["config"]["min"] . "assets/";
    $enable_publish = get_option("options_enable_publish");
    $publish_url = "";
    if($enable_publish){
        $publish_url = get_option("options_publish_url");
    } 

    /*if (is_dir($assets_path)) {
        $files = scandir($assets_path);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = $assets_path . '/' . $file;
                if (is_dir($file_path)) {
                    delete_directory($file_path);
                } else {
                    unlink($file_path);
                }
            }
        }
        //rmdir($assets_path);
    } else {
        // Klasörü oluştur
        mkdir($assets_path, 0755, true);
        error_log('Assets klasörü başarıyla oluşturuldu.');
    }*/

    // CSS içeriğini oku
    $css = file_get_contents($css_file);
    
    // URL'leri bulmak için regex kullan
    preg_match_all('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i', $css, $matches, PREG_PATTERN_ORDER);
    
    if ($matches) {
        $assets = array();
        
        foreach ($matches[3] as $key => $match) {
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
        }
        
        if ($assets) {

            foreach ($assets as $key => $asset) {
                // node_modules içindeki URL'leri kontrol et
                $copy_file = explode("/node_modules/", $asset["clean_url"]);
                
                if (isset($copy_file[1])) {
                    $copy_file = $copy_file[1];
                    $source_path = $rules["config"]["node"] . $copy_file;
                    
                    // Dosya mevcutsa kopyala
                    if (file_exists($source_path) && !is_dir($source_path)) {
                        copy($source_path, $assets_path . $asset["file"]);
                        // Fazladan '?' işaretlerini kaldır, sadece orijinal parametreyi koru
                        $clean_url = explode('?', $asset["url"])[0]; // Sadece URL kısmını al
                        $query = parse_url($asset["url"], PHP_URL_QUERY); // Query parametresini al
                        
                        $final_url = $rules["config"]["min_uri"] . "assets/" . $asset["file"] . ($query ? '?' . $query : '');
                        //$final_url = str_replace(get_template_directory_uri(), "var(--theme-url)", $final_url);
                        //$final_url = absolute_to_relative_url($final_url);

                        if(!empty($publish_url)){
                            $final_url = str_replace(home_url(), $publish_url, $final_url);
                        }

                        // URL'yi doğru şekilde yeniden oluştur
                        $css = str_replace($asset["url"], $final_url, $css);
                    }
                } else {
                    // Eğer CSS içinde bir URL varsa ve bu URL node_modules'dan gelmiyorsa, sadece kopyala
                    if (file_exists($asset["url"]) && !is_dir($asset["url"])) {
                        copy($asset["url"], $assets_path . $asset["file"]);
                        // Fazladan '?' işaretlerini kaldır, sadece orijinal parametreyi koru
                        $clean_url = explode('?', $asset["url"])[0]; // Sadece URL kısmını al
                        $query = parse_url($asset["url"], PHP_URL_QUERY); // Query parametresini al

                        $final_url = $rules["config"]["min_uri"] . "assets/" . $asset["file"] . ($query ? '?' . $query : '');
                        //$final_url = str_replace(get_template_directory_uri(), "var(--theme-url)", $final_url);
                        //$final_url = absolute_to_relative_url($final_url);
 
                        if(!empty($publish_url)){
                            $final_url = str_replace(home_url(), $publish_url, $final_url);
                        }
                        
                        // URL'yi doğru şekilde yeniden oluştur
                        $css = str_replace($asset["url"], $final_url, $css);
                    }
                }
            }
            // Güncellenmiş CSS'i kaydet
            file_put_contents($css_file, $css);
        }
    }
}

function get_plugin_version($rules=[], $plugin=""){
    $version = "1.0";
    $path = $rules["config"]["node"].$plugin."/package.json";
    if (file_exists($path)) {
        $package = file_get_contents($path);
        $package = json_decode($package, true);
        $version = $package["version"];
    }
    return $version; 
}

function check_plugin_updates($old="", $new=""){
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

function check_plugin_init_updates($rules = [], $last_update="") {
    $updates = [];
    $init_files_dir = $rules["config"]["js"] . 'production/plugins-init/';
    if (!empty($last_update)) {
        $init_files = glob($init_files_dir . '*.js');
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





function update_minified_file($file_minified){
    $filtered = slib_compress_script(file_get_contents($file_minified));
    file_put_contents($file_minified, $filtered);
}
function slib_compress_script($buffer){
    return $buffer;
    // JavaScript compressor by John Elliot <jj5@jj5.net>
    $replace = array(
        '#\'([^\n\']*?)/\*([^\n\']*)\'#' => "'\1/'+\'\'+'*\2'", // remove comments from ' strings
        '#\"([^\n\"]*?)/\*([^\n\"]*)\"#' => '"\1/"+\'\'+"*\2"', // remove comments from " strings
        '#/\*.*?\*/#s' => "", // strip C style comments
        '#[\r\n]+#' => "\n", // remove blank lines and \r's
        '#\n([ \t]*//.*?\n)*#s' => "\n", // strip line comments (whole line only)
        '#([^\\])//([^\'"\n]*)\n#s' => "\\1\n",
        // strip line comments
        // (that aren't possibly in strings or regex's)
        '#\n\s+#' => "\n", // strip excess whitespace
        '#\s+\n#' => "\n", // strip excess whitespace
        '#(//[^\n]*\n)#s' => "\\1\n", // extra line feed after any comments left
        // (important given later replacements)
        '#/([\'"])\+\'\'\+([\'"])\*#' => "/*", // restore comments in strings
        '~//[#@]\s(source(?:Mapping)?URL)=\s*(\S+)~' => ''
        //remoce source urls (by salthareket)
        
    );

    $search = array_keys($replace);
    $script = preg_replace($search, $replace, $buffer);

    $replace = array(
        "&&\n" => "&&",
        "||\n" => "||",
        "(\n" => "(",
        ")\n" => ")",
        "[\n" => "[",
        "]\n" => "]",
        "+\n" => "+",
        ",\n" => ",",
        "?\n" => "?",
        ":\n" => ":",
        ";\n" => ";",
        "{\n" => "{",
        //  "}\n"  => "}", (because I forget to put semicolons after function assignments)
        "\n]" => "]",
        "\n)" => ")",
        "\n}" => "}",
        "\n\n" => "\n"
    );

    $search = array_keys($replace);
    $script = str_replace($search, $replace, $script);

    return trim($script);
}

function removeSourceMap($input, $type) {
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

function removeComments($input) {
    return $input;
}

function removeComments_v1($input) {
    $input = file_get_contents($input);
    return preg_replace_callback('(
        (?:
            (^|[-+\([{}=,:;!%^&*|?~]|/(?![/*])|return|throw) # context before regexp
            (?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
            (/(?![/*])(?:
                \\\\[^\n]
                |[^[\n/\\\\]++
                |\[(?:\\\\[^\n]|[^]])++
            )+/) # regexp
            |(^
                |\'(?:\\\\.|[^\n\'\\\\])*\'
                |"(?:\\\\.|[^\n"\\\\])*"
                |([0-9A-Za-z_$]+)
                |([-+]+)
                |.
            )
        )(?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
    )sx', 'jsShrinkCallback', "$input\n");
}

function jsShrinkCallback($match) {
    static $last = '';
    $match += array_fill(1, 5, null); // avoid E_NOTICE
    list(, $context, $regexp, $result, $word, $operator) = $match;
    if ($word != '') {
        $result = ($last == 'word' ? "\n" : ($last == 'return' ? " " : "")) . $result;
        $last = ($word == 'return' || $word == 'throw' || $word == 'break' ? 'return' : 'word');
    } elseif ($operator) {
        $result = ($last == $operator[0] ? "\n" : "") . $result;
        $last = $operator[0];
    } else {
        if ($regexp) {
            $result = $context . ($context == '/' ? "\n" : "") . $regexp;
        }
        $last = '';
    }
    return $result;
}

function absolute_to_relative_url($url) {
    $site_url = site_url(); // WordPress'in ana URL'sini al
    if (strpos($url, $site_url) === 0) { // Eğer URL, site_url ile başlıyorsa
        return str_replace($site_url, '', $url); // Ana URL'yi kaldır
    }
    return $url; // Zaten relative ise olduğu gibi bırak
}