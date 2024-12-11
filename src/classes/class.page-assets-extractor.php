<?php

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;

class PageAssetsExtractor {

    protected $multilang_plugin = null;
    public $type = null;
    public $mass = false;
    public $disable_hooks = false;
    public $home_url = "";
    public $home_url_encoded = "";
    public $upload_url = "";
    public $upload_url_encoded = "";

    public function __construct() {
        error_log("PageAssetsExtractor initialized in admin.");
        $this->home_url = home_url("/");
        $this->home_url_encoded = str_replace("/","\/", $this->home_url);
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl']."/";;
        $this->upload_url = $upload_url;
        $this->upload_url_encoded = str_replace("/","\/", $this->upload_url);
        if (defined('ENABLE_MULTILANGUGE') && ENABLE_MULTILANGUGE) {
            $this->multilang_plugin = ENABLE_MULTILANGUGE; // Multilanguage plugin adı
        }
    }

    // Post kaydedildiğinde URL'leri fetch eder
    public function on_save_post($post_id, $post, $update) {
        if ($post->post_status == 'publish' && $this->is_excluded_post_type($post->post_type) && $this->disable_hooks == false) {
            error_log("Saved post : " . $post_id . " with status: " . $post->post_status." post_type: ".$post->post_type);
            $this->type = "post"; 
            $this->fetch_post_url($post_id);
        }
    }

    // Term kaydedildiğinde URL'leri fetch eder
    public function on_save_term($term_id, $tt_id, $taxonomy) {
        error_log("on_save_term : " . $term_id . " tt_id: " . $tt_id." taxonomy: ".$taxonomy);
        if ($this->is_excluded_taxonomy($taxonomy) && $this->disable_hooks == false) {
            $this->type = "term"; 
            $this->fetch_term_url($term_id, $taxonomy);
        }
    }

    // Geçersiz post_type kontrolü
    private function is_excluded_post_type($post_type) {
        $excluded_post_types = get_post_types(['public' => true], 'objects');
        return !in_array($post_type, $excluded_post_types); // Geçersizse true döndür
    }

    // Geçersiz taxonomy kontrolü
    private function is_excluded_taxonomy($taxonomy) {
        $excluded_taxonomies = get_taxonomies(['public' => true]); // Tüm public taxonomy'leri al
        return in_array($taxonomy, $excluded_taxonomies); // Geçersizse true döndür
    }

    // Belirli bir post ID'den URL'yi fetch et
    public function fetch_post_url($post_id) {
        //$post = get_post($post_id);
        //$urls = $this->get_multilang_urls($post);
        //error_log("urls : ".json_encode($urls));

        $url = get_permalink($post_id);
        error_log("url : ".json_encode($url));

        //foreach ($urls as $url) {
            $this->fetch($url, $post_id);
        //}
    }

    // Belirli bir term ID'den URL'yi fetch et
    public function fetch_term_url($term_id, $taxonomy) {
        error_log("fetch_term_url->".$term_id." tax ".$taxonomy);
        $term = get_term($term_id, $taxonomy);
        $url = get_term_link($term);
        error_log("get_term_link->".$url);

        if (!is_wp_error($url)) {
            $this->fetch($url, $term_id);
        }
    }

    // URL'den HTML'yi fetch et
    public function fetch($url, $id) {

        if(in_array($this->type, ["post", "term"]) && $this->mass){
            acf_block_id_fields($id);
        }

        error_log("fetch->".$url." : ".$id." type:".$this->type);
        $fetch_url = $url. (strpos($url, '?') === false ? '?fetch&nocache=true' : '&fetch&nocache=true');

        if(get_page_status($fetch_url) != 200){
            return false;
        }
        
        $html_content = HtmlDomParser::file_get_html($fetch_url);
        
        if (!$html_content) {
            return false;
        }

        // <main> tagini bul
        $main_content = $html_content->findOne('main') ? $html_content->findOne('main')->outerHtml() : '';

        // block-* classına sahip divleri bul
        $block_content = "";
        $block = $html_content->findOne('.block--hero');
        if($block){
            $block_content = $block->outerHtml();
        }
        $html = HtmlDomParser::str_get_html($main_content . $block_content);
        return $this->extract_assets($html, $id);
    }

    // Tüm URL'lerden fetch işlemi (manuel çalıştırılacak)
    public function fetch_all() {
        $urls = $this->get_all_urls();
        $results = [];
        foreach ($urls as $id => $url) {
            //error_log(json_encode($url));
            $this->type = $url["type"];
            $results[$url["url"]] = $this->fetch($url["url"], $id);
        }
        return $results;
    }

    public function fetch_urls($urls) {
        $results = [];
        foreach ($urls as $id => $url) {
            //error_log(json_encode($url));
            $this->type = $url["type"];
            $results[$url["url"]] = $this->fetch($url["url"], $id);
        }
        return $results;
    }

    // JS ve CSS assetlerini bul
    public function extract_assets($html, $id) {
        $js = [];
        $css = [];
        $plugins = [];
        $plugin_js = "";
        $plugin_css = "";
        $plugin_css_rtl = "";

        // <style> ve <script> etiketlerini $main ve $block içinde ara
        if ($html) {
            $scripts = $html->findMulti('script');
            $scripts_filtered = [];
            foreach ($scripts as $script) {
                if (!isset($script->attr['data-inline']) || $script->attr['data-inline'] !== 'true') {
                    $scripts_filtered[] = $script;
                }
            }
            $scripts = $scripts_filtered;
            foreach ($scripts as $script) {
                $code = $script->innerHtml();
                $js[] = $code;
            }
            if($js){
                $js = array_unique($js);
                $js = implode("\n", $js);
                $minifier = new Minify\JS();
                $minifier->add($js);
                $js = $minifier->minify();
                
                $js = str_replace($this->upload_url, "{upload_url}", $js);
                $js = str_replace($this->upload_url_encoded, "{upload_url}", $js);
                $js = str_replace($this->home_url, "{home_url}", $js);
                $js = str_replace($this->home_url_encoded, "{home_url}", $js);
            }

            $styles = $html->findMulti('style');
            $styles_filtered = [];
            foreach ($styles as $style) {
                if (!isset($style->attr['data-inline']) || $style->attr['data-inline'] !== 'true') {
                    $styles_filtered[] = $style;
                }
            }
            $styles = $styles_filtered;
            foreach ($styles as $style) {
                $code = $style->innerHtml();
                $css[] = $code;
            }
            if($css){
                $css = array_unique($css);
                $css = implode("\n", $css);
                $minifier = new Minify\CSS();
                $minifier->add($css);
                $css = $minifier->minify();
                $css = str_replace($this->upload_url, "{upload_url}", $css);
                $css = str_replace($this->upload_url_encoded, "{upload_url}", $css);
                $css = str_replace($this->home_url, "{home_url}", $css);
                $css = str_replace($this->home_url_encoded, "{home_url}", $css);
            }
        }

        // Plugin konfigürasyonunu kontrol et
        if (!function_exists("compile_files_config")) {
            require THEME_INCLUDES_PATH . "minify-rules.php";
        }
        $files = compile_files_config(true);

        // Plugin kontrolü
        if (!empty($files["js"]["plugins"])) {
            foreach ($files["js"]["plugins"] as $key => $plugin) {
                if ($plugin['c'] === true) {
                    $condition = 1;
                    if(isset($plugin['condition'])){
                        $condition = $plugin['condition'];
                    }
                    foreach ($plugin['class'] as $class) {
                        //error_log($key." için ".$class." varmı = ".(strpos($html, $class) !== false));
                        
                        if (strpos($html, $class) !== false && $condition) {
                            $plugins[] = $key;
                            break;
                        }
                    }
                    foreach ($plugin['attrs'] as $attr) {
                        if (strpos($html, $attr) !== false && $condition) {
                            $plugins[] = $key;
                            break;
                        }
                    }
                }
            }

            if($plugins){
                $plugins = array_unique($plugins);
            }

            $this->delete_existing_assets($id);

            if($plugins){

                //plugin css
                $plugin_files_css = [];
                $plugin_files_css_rtl = [];
                foreach($plugins as $plugin){
                    if($files["js"]["plugins"][$plugin]["css"]){
                        $plugin_files_css[] = get_stylesheet_directory() . '/static/js/min/plugins/'.$plugin.".css"; 
                    }
                }
                foreach($plugins as $plugin){
                    if($files["js"]["plugins"][$plugin]["css"]){
                        $plugin_files_css_rtl[] = get_stylesheet_directory() . '/static/js/min/plugins/'.$plugin."-rtl.css"; 
                    }
                }
                if($plugin_files_css){
                    $plugin_css = $this->combine_and_cache_files("css", $plugin_files_css);
                    $plugin_css = str_replace(get_stylesheet_directory_uri(), '', $plugin_css);
                }
                if($plugin_files_css_rtl){
                    $plugin_css_rtl = $this->combine_and_cache_files("css", $plugin_files_css_rtl);
                    $plugin_css_rtl = str_replace(get_stylesheet_directory_uri(), '', $plugin_css_rtl);
                }

                //plugin js
                $plugin_files_js = [];
                foreach($plugins as $plugin){
                    $plugin_files_js[] = get_stylesheet_directory() . '/static/js/min/plugins/'.$plugin.".js";
                }
                foreach($plugins as $plugin){
                    $plugin_files_js[] = get_stylesheet_directory() . '/static/js/min/plugins/'.$plugin."-init.js";
                }
                if($plugin_files_js){
                    //error_log(json_encode($plugin_files_js));
                    $plugin_js = $this->combine_and_cache_files("js", $plugin_files_js);
                    $plugin_js = str_replace(get_stylesheet_directory_uri(), '', $plugin_js);
                }

            }
        }

        $result = array(
            "js" => $js, //on page save
            "css" => $css, //on page save
            "plugins" => $plugins, //on page save
            "plugin_js" => $plugin_js,
            "plugin_css" => $plugin_css,
            "plugin_css_rtl" => $plugin_css_rtl
        );
        //error_log(json_encode($result));
        return $this->save_meta($result, $id);
    }

    public function combine_and_cache_files($type, $files) {
        if ($type !== 'css' && $type !== 'js') {
            return false;
        }
        
        if($type == "js"){
            $containsInit = "";
            $containsInit = array_filter($files, function($file) {
                return preg_match('/-init\.js$/', $file);
            });
            if(!empty($containsInit)){
                $initFiles = array_filter($files, function($file) {
                    return preg_match('/-init\.js$/', $file);
                });
                $otherFiles = array_filter($files, function($file) {
                    return !preg_match('/-init\.js$/', $file);
                });
                sort($initFiles);
                sort($otherFiles);
                $mergedFiles = array_merge($initFiles, $otherFiles);                
            }else{
                sort($files);
            }
        } else {
            sort($files);
        }

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
            $file_system_path = get_stylesheet_directory() . '/static/js/min/plugins/' . basename($file);
            
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
        $combined_content = str_replace("(function($) {", "", $combined_content);
        $combined_content = str_replace("(function($){", "", $combined_content);
        $combined_content = str_replace("})(jQuery)", "", $combined_content);
        $combined_content = str_replace("}(jQuery))", "", $combined_content);
        file_put_contents($cache_file, trim($combined_content)); // Boş satırları önlemek için trim kullan
        //return get_stylesheet_directory_uri() . '/static/' . $type . '/cache/' . $hash . '.' . $type;
        return '/static/' . $type . '/cache/' . $hash . '.' . $type;
    }

    public function save_meta($result, $id) {
        if($this->type != "archive"){
            $meta_function_get = "get_{$this->type}_meta";
            $meta_function_update = "update_{$this->type}_meta";
            $meta_function_add = "add_{$this->type}_meta";

            $existing_meta = call_user_func($meta_function_get, $id, 'assets', true);
            if ($existing_meta) {
                $return = call_user_func($meta_function_update, $id, 'assets', $result); // Güncelle
            } else {
                $return = call_user_func($meta_function_add, $id, 'assets', $result); // Yeni ekle
            }

        }else{
            //"post_type_${lang}_assets";
            $option_name = $id . '_assets'; // Option name oluştur
            $existing_meta = get_option($option_name); // Var olan option'u kontrol et
            if ($existing_meta) {
                $return = update_option($option_name, $result); // Güncelle
            } else {
                $return = add_option($option_name, $result); // Yeni ekle
            }
        }
        if($this->type == "post" && !$this->mass){
            $this->save_post_terms( $id );
        }
        $this->disable_hooks = false;
        return $result;
    }

    public function delete_existing_assets($id) {
        switch($this->type) {
            case "post" :
                $existing_meta = get_post_meta($id, 'assets', true);
                delete_post_meta($id, "assets");
            break;

            case "term" :
                $existing_meta = get_term_meta($id, 'assets', true);
                delete_term_meta($id, "assets");
            break;

            case "user" :
                $existing_meta = get_user_meta($id, 'assets', true);
                delete_user_meta($id, "assets");
            break;

            case "comment" :
                $existing_meta = get_comment_meta($id, 'assets', true);
                delete_comment_meta($id, "assets");
            break;

            case "archive" :
                $option_name = $id . '_assets'; // Option name oluştur
                $existing_meta = get_option($option_name); // Var olan option'u kontrol et
                delete_option($option_name);
            break;
        }
        if (is_array($existing_meta)) {
            $keys_to_check = ['plugin_js', 'plugin_css', 'plugin_css_rtl'];
            foreach ($keys_to_check as $key) {
                if (!empty($existing_meta[$key])) {
                    $file_path = get_stylesheet_directory() . str_replace(get_stylesheet_directory_uri(), '', $existing_meta[$key]);
                    //error_log("kayıt var..");
                    if (file_exists($file_path)) {
                        unlink($file_path); // Dosyayı sil
                        //error_log("Dosya silindi: " . $file_path); // İsteğe bağlı, log ekleyebilirsin
                    }
                }
            }
        }
    }

    public function get_all_urls($sitemap_url = null, $urls = []) {
        // İlk çağrıda ana sitemap url'sini ayarla
        if ($sitemap_url === null) {
            $sitemap_url = site_url('/sitemap_index.xml'); // Yoast XML sitemap URL'si
        }

        //error_log("-----------".$sitemap_url);

        // Sitemap URL'sini çek
        $sitemap_content = file_get_contents($sitemap_url);

        if (!$sitemap_content) {
            return [];
        }

        $xml = simplexml_load_string($sitemap_content);

        // Namespace kullanılıyorsa, SimpleXML'de bunu belirtmemiz gerekiyor
        $namespaces = $xml->getDocNamespaces(true);
        if (isset($namespaces[''])) {
            $xml->registerXPathNamespace('ns', $namespaces['']);
        } else {
            $xml->registerXPathNamespace('ns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        }

        // Eğer bu bir sitemapindex ise (içinde <sitemap> etiketleri varsa)
        if ($xml->xpath('//ns:sitemap')) {
            foreach ($xml->xpath('//ns:sitemap/ns:loc') as $sitemap_loc) {
                $sub_sitemap_url = (string)$sitemap_loc;

                // Bu fonksiyonu tekrar çağırarak alt sitemap'i işle
                $urls = $this->get_all_urls($sub_sitemap_url, $urls);
            }
        } else {
            // Eğer bu bir urlset ise (içinde <url> etiketleri varsa)
            foreach ($xml->xpath('//ns:url/ns:loc') as $url_loc) {
                $url_string = (string)$url_loc;

                // Sitemap dosya adını al ve "-sitemap.xml" kısmını çıkar
                $sitemap_file_name = basename($sitemap_url, '-sitemap.xml');
                //error_log("Sitemap type: " . $sitemap_file_name);

                switch($sitemap_file_name){

                        case "page" :
                        case "post" :
                            $post_id = url_to_postid($url_string);
                            if ($post_id === 0 && function_exists('pll_get_post')) {
                                if (strpos($url_string, $this->home_url) === 0) {
                                    // Anasayfanın diline göre post ID'yi al
                                    $lang = str_replace($this->home_url, "", $url_string);
                                    $lang = str_replace("/", "", $lang);
                                    //error_log("--- yabancı sayfa : ".$lang);
                                    $post_id = pll_get_post(get_option('page_on_front'), $lang);
                                }
                            }
                            if ($post_id) {
                                $urls[$post_id] = [
                                    "type" => "post",
                                    "post_type" => get_post_type($post_id),
                                    "url" => $url_string
                                ];
                            }
                        break;

                        case "post_tag" :
                        case "category" :
                        case "format" :
                            $term_slug = basename($url_string);
                            $term = get_term_by('slug', $term_slug, $sitemap_file_name);
                            if ($term) {
                                $urls[$term->term_id] = [
                                    "type" => "term",
                                    "post_type" => $sitemap_file_name,
                                    "url" => $url_string
                                ];
                            }
                        break;

                        case "comment" :
                            $author_name = basename($url_string);
                            $author = get_user_by('slug', $author_name);
                            if ($author) {
                                $urls[$author->ID] = [
                                    "type" => "comment",
                                    "post_type" => "comment",
                                    "url" => $url_string
                                ];
                            }
                        break;

                        default :
                            $post_id = url_to_postid($url_string);
                            if($post_id == 0){
                                $term_slug = basename($url_string);
                                $is_archive = $this->is_post_type_archive($term_slug);
                                if(!empty($is_archive)){ // arşiv
                                    $urls[$sitemap_file_name."_".$is_archive] = [
                                        "type" => "archive",
                                        "post_type" => "archive",
                                        "url" => $url_string
                                    ];
                                }else{
                                    $lang = $this->get_url_language($url_string);
                                    $term = get_term_by('slug', $term_slug, $sitemap_file_name);
                                    $term_id = pll_get_term( $term->term_id, $lang);
                                    if ($term_id) {
                                        $urls[$term_id] = [
                                            "type" => "term",
                                            "post_type" => $sitemap_file_name,
                                            "url" => $url_string
                                        ];
                                    }                          
                                }
                            }else{
                                if(in_array($sitemap_file_name, $this->get_roles())){
                                    $author_name = basename($url_string);
                                    $author = get_user_by('slug', $author_name);
                                    if ($author) {
                                        $urls[$author->ID] = [
                                            "type" => "user",
                                            "post_type" => $sitemap_file_name,
                                            "url" => $url_string
                                        ];
                                    }
                                }else{
                                    $urls[$post_id] = [
                                        "type" => "post",
                                        "post_type" => get_post_type($post_id),
                                        "url" => $url_string
                                    ];                                    
                                }
                            }

                        break; 
                }

            }
        }

        return $urls; // [id => url]
    }

    public function is_post_type_archive($slug) {
        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $key => $post_type) {
            if (!empty($post_type->has_archive)) {
                $archive_slug = trim($post_type->has_archive === true ? $post_type->rewrite['slug'] : $post_type->has_archive);
                if ($archive_slug == $slug) {
                    return pll_default_language();
                }else{
                    foreach (pll_the_languages(['raw' => 1]) as $language) {
                        if(pll_translate_string($key, $language["slug"]) == $slug){
                            return $language["slug"];
                        }
                    }
                }    
            }
        }
        return "";
    }

    public function get_post_terms_urls( $post_id ) {
        if ( ! get_post( $post_id ) ) {
            return [];
        }
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
        $urls = [];
        foreach ( $taxonomies as $taxonomy => $details ) {
             if ( ! $details->public ) {
                continue; // Sadece public olan taxonomy'leri işlem yap
            }
            $terms = get_the_terms( $post_id, $taxonomy );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $urls[$term->term_id] = get_term_link( $term );
                }
            }
        }
        return ! empty( $urls ) ? $urls : [];
    }

    public function save_post_terms( $post_id ) {
        if ( ! get_post( $post_id ) ) {
            return [];
        }
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
        foreach ( $taxonomies as $taxonomy => $details ) {
             if ( ! $details->public ) {
                continue; // Sadece public olan taxonomy'leri işlem yap
            }
            $terms = get_the_terms( $post_id, $taxonomy );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $args = array(
                        'name' => $term->name,
                        'slug' => $term->slug
                    );
                    wp_update_term( $term->term_id, $taxonomy, $args );
                }
            }
        }
    }


    function get_roles() {
        global $wp_roles;
        $roles = [];
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        foreach ($wp_roles->roles as $role_key => $role_details) {
            $name = $role_details['name'];
            $roles[] = $role_key;
        }
        return $roles;
    }

    function get_url_language($url = "") {
        $url = str_replace($this->home_url, "", $url);
        if (preg_match('#^([a-z]{2})/#', $url, $matches)) {
            return $matches[1];
        } else {
            return pll_default_language();
        }
    }

    public function get_multilang_urls($post) {
        $urls = [];

        if ($this->multilang_plugin === 'qtranslate-xr') {
            global $q_config;
            $langs = $q_config['enabled_languages'];
            foreach ($langs as $lang) {
                if ($q_config['default_language'] === $lang && $q_config['hide_default_language']) {
                    $urls[] = get_permalink($post->ID);
                } else {
                    $urls[] = qtranxf_convertURL(get_permalink($post->ID), $lang);
                }
            }
        } elseif ($this->multilang_plugin === 'polylang' || $this->multilang_plugin === 'wpml') {
            $langs = pll_languages_list();
            foreach ($langs as $lang) {
                $urls[] = get_permalink(pll_get_post($post->ID, $lang));
            }
        } else {
            $urls[] = get_permalink($post->ID);
        }

        return $urls;
    }
}