<?php

use SaltHareket\Image;

class Post extends Timber\Post{

    public function get_archive_link(){
        return get_post_type_archive_link($this->post_type);
    }
    public function author(){
        $author = Timber::get_user($this->post_author);
        return $author;
    }
    public function is_owner() {
        $owner = false;
        if($this->post_author == get_current_user_id()){
           $owner = true;
        }
        return $owner;
    }

    public function get_files(){
        $files = $this->get_field("files");
        $list = array();
        if ($files) {
            foreach ($files as $item) {
                $file_type = $item['file_type'];
                if (!isset($list[$file_type])) {
                    $term = get_term($file_type);
                    $list[$file_type] = array(
                        "title" => $term->name,
                        "files" => array()
                    );
                }
                $list[$file_type]["files"][] = $item;
            }
        }
        return $list;
    }

    public function get_map_data($popup=false){
        $data = array();
        $map_service = SaltBase::get_cached_option("map_service");//get_field("map_service", "option");
        $location_data = $this->contact["map_".$map_service];
        if($location_data){
            $map_marker = $this->contact["map_marker"];
            if($map_marker){
                $marker = $map_marker;
            }else{
                $marker = SaltBase::get_cached_option("map_marker");//get_field("map_marker", "option");
            }
            if(!$marker){
                 $marker = SaltBase::get_cached_option("logo_marker");//get_field("logo_marker", "option");
            }
            $data = array(
                "id"        => $this->ID,
                "title"     => $this->title(),
                //"image"   =>  $this->thumbnail->src('thumbnail'),
                //"marker"  => array(),
                "lat"       => $location_data["lat"],
                "lng"       => $location_data["lng"],
                "zoom"      => $location_data["zoom"],
            );
            if($marker){
                $data["marker"] = array(
                    "icon" => isset($marker["url"])?$marker["url"]:"",
                    "width" => isset($marker["width"])?$marker["width"]:0,
                    "height" => isset($marker["height"])?$marker["height"]:0,
                );
            }
            if(isset($location_data["map_url"]) && !empty($location_data["map_url"])){
                $data["url"] = $location_data["map_url"];
            }
            if($popup){
               $data["popup"] = esc_html($this->get_map_popup());
            }            
        }
        return $data;
    }
    public function get_map_config($buttons = [], $popup = [], $callback = ""){
        $config = [
            'locations' => [],
            'buttons'   => $buttons,
            'popup'     => $popup,
            'callback'  => $callback
        ];
        $location_data = [];
        $map_data = $this->get_map_data();
        if($map_data){
           $map_data["id"] = $this->ID;
           $location_data = array_merge($location_data, [$map_data]);
        }
        $config = array_merge($config, ["locations" => $location_data]);
        return $config;
    }
    public function get_map_embed(){
        $code = "";
        $map_data = $this->get_map_data();
        if($map_data && !empty($map_data["map_url"])){
            $html ='<iframe
                src="'.$map_data["map_url"].'"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen>
              </iframe>';
        }
        return $code;
    }

    public function get_map_popup(){
        $map_data = $this->get_map_data();
        return  "<div class='row gx-3 gy-2'>" .
                    "<div class='col-auto'>" .
                         "<img src='" . $map_data["image"] . "' class='img-fluid rounded' style='max-width:50px;'/>" .
                    "</div>" .
                    "<div class='col'>" .
                        "<ul class='list-unstyled m-0'>" .
                            "<li class='fw-bold'>" . $map_data["title"] . "</li>" .
                            "<li class='text-muted' style='font-size:12px;'>" . $this->get_location() . "</li>" .
                        "</ul>" .
                    "</div>" .
                    "<div class='col-12 text-primary' style='font-size:12px;'>" .
                        $this->get_local_date("","",$GLOBALS["user"]->get_timezone()) . " GMT" . $this->get_gmt() . "</span>" .
                    "</div>" .
                "</div>";
    }

    public function get_blocks_array($exception = array(), $render = false) {
        $blocks_array = array();
        $post = get_post($this->ID);
        if (!$post) {
            return 'Belirtilen post bulunamadı.';
        }
        $blocks = parse_blocks($post->post_content);
        foreach ($blocks as $block) {
            if (
                in_array($block['blockName'], $exception) || 
                (isset($block['attrs']["metadata"]) && 
                in_array($block['attrs']["metadata"]["name"], $exception))
            ) {
                continue;
            }
            if ($render) {
                $blocks_array[] = render_block($block);
            } else {
                $blocks_array[] = $block['innerHTML'];
            }
        }
        return $blocks_array;
    }

    public function get_blocks_v1($args = []) {
        if (has_blocks($this)) {
            $content = $this->content;
        }else{
            if(in_array(get_query_var("qpt_settings"), [2, 3])){
                return "";
            }
            $content = $this->content;
        }
        return $content;
    }

    public function get_blocks($args = []) {

        if (has_blocks($this)) {
            $blocks = parse_blocks($this->post_content);

            $blocks = array_filter($blocks, function ($block) {
                return !isset($block['attrs']['data']['block_settings_hero']) || !$block['attrs']['data']['block_settings_hero'];
            });
            if(in_array(get_query_var("qpt_settings"), [2, 3])){
                $blocks = array_filter($blocks, function ($block) {
                    if (isset($block['attrs']["name"])) {
                        return (
                            ($block['attrs']["name"] == "acf/text" && has_shortcode($block['attrs']["data"]["text"], "search_field")) 
                            || $block['attrs']["name"] == "acf/search-results"
                        );
                    }
                    return false;
                });
            }

            $blocks = array_values($blocks);

            // Bloklara sıra (index) ekleme
            $index = 0;
            $blocks = array_map(function ($block) use (&$index) {
                // Zero-based index ekleme
                $block['attrs']['index'] = $index;
                $index++;
                return $block;
            }, $blocks);

            //print_r($blocks);


            $content = join('', array_map('render_block', $blocks));


            /*$content = join('', array_map(function ($block) {
                // Eğer blok embed ise manuel render
                if ($block['blockName'] === 'core/embed' && isset($block['attrs']['url'])) {
                    return $this->render_embed_block($block['attrs']['url']);
                }

                // Diğer bloklar için normal render
                return render_block($block);
            }, $blocks));*/

            if(isset($args["extract_js"])){
                $html = "";
                $js = "";
                $html = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($matches) use (&$js) {
                    $js .= $matches[0] . "\n";
                    return '';
                }, $content);
                $content = array(
                    "html" => $html,
                    "js" => $js
                ); 
            }
            
            if(!isset($_GET["fetch"]) && (SEPERATE_CSS || SEPERATE_JS)) { 
                $tags = "";
                if(SEPERATE_CSS){
                    $tags = "<style>";
                }
                if(SEPERATE_JS){
                    $tags .= "<script>";
                }
                if(!empty($tags)){
                    $content = $this->strip_tags($content, $tags);//strip_tags_opposite($content, '<script><style>');                    
                }
            }

        }else{
            if(in_array(get_query_var("qpt_settings"), [2, 3])){
                return "";
            }
            $content = $this->content;
        }
        return $content;
    }

    private function render_embed_block($url) {
        // wp_oembed_get ile tüm desteklenen platformlar için embed kodunu al
        $embed_code = wp_oembed_get($url);

        // Eğer embed bulunamadıysa, URL'yi düz metin olarak göster
        if (!$embed_code) {
            return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
        }

        // Embed kodunu döndür
        return $embed_code;
    }

    public function get_block($block_name = "", $render = false, $args = []) {
        if (!$block_name) {
            return 'Lütfen geçerli bir post ID ve blok adı belirtin.';
        }
        $post = get_post($this->ID);
        if (!$post) {
            return '';//Belirtilen post bulunamadı.';
        }
        $blocks = parse_blocks($post->post_content);
        foreach ($blocks as $block) {
            if ($block['blockName'] === $block_name || (isset($block['attrs']["metadata"]) && $block['attrs']["metadata"]["name"] === $block_name) )  {
                if($args){
                    foreach($args as $key => $arg){
                        $block['attrs'][$key] = $arg;
                    }
                }
                if ($render) {
                    return render_block($block);
                } else {
                    return $block['innerHTML'];
                }
            }
        }
        return '';//Belirtilen blok bulunamadı.';
    }

    public function get_deeper_link(){
        return get_page_deeper_link($this->ID);
    }

    public function get_average_color(){
        return $this->meta("average_color");
    }

    public function get_read_time(){
        return get_post_read_time($this->content);
    }


    public function get_breadcrumb($link=true){
        return post2Breadcrumb($this->ID, $link);
    }

    function strip_tags($content = "", $allowed_tags = "<script><style>") {
         // DOMDocument başlat
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true); // Hataları gizle

        // HTML'yi yükle (UTF-8 desteği için encoding belirtildi)
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // İzin verilen tagleri al
        $allowed_tags_array = explode('><', trim($allowed_tags, '<>'));

        // Taglere göre işlem yap
        foreach ($allowed_tags_array as $tag) {
            $elements = $dom->getElementsByTagName($tag);

            // Tüm elementleri sondan başa doğru kontrol et
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);

                // `data-inline="true"` kontrolü
                if (!$element->hasAttribute('data-inline') || $element->getAttribute('data-inline') !== 'true') {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // Sonuç HTML'yi döndür (daha güvenli string işlemi)
        $output = $dom->saveHTML();
        return html_entity_decode($output, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /*public function get_thumbnail($args=array()){
        $media = $this->meta("media");
        if(isset($media) && $media["media_type"] == "image"){
            if($media["use_responsive_image"]){
                $args["src"] = $media["image_responsive"];
                $image = new SaltHareket\Image($args);
                return $image->init();
            }else{
                $args["src"] = $this->thumbnail();
                $image = new SaltHareket\Image($args);
                return $image->init();
            }
        }else{
            $args["src"] = $this->thumbnail();
            $image = new SaltHareket\Image($args);
            return $image->init();
        }
    }*/

    /*public function get_thumbnail(array $args = []){
        $media = $this->meta('media');
        $src   = $this->thumbnail(); // en sağlam fallback

        // 1) JSON/serialize yakala (ACF bazen ham meta döndürebilir)
        if (is_string($media)) {
            // JSON?
            $decoded = json_decode($media, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $media = $decoded;
            } elseif (ctype_digit($media)) {
                // attachment ID string
                $maybe = wp_get_attachment_url((int)$media);
                if ($maybe) { $src = $maybe; }
            }
        }

        // 2) Dizi ise güvenli erişim
        if (is_array($media)) {
            $type = $media['media_type'] ?? null;

            if ($type === 'image') {
                // responsive varsa onu kullan
                if (!empty($media['use_responsive_image']) && !empty($media['image_responsive'])) {
                    $src = $media['image_responsive'];
                } elseif (!empty($media['image'])) {
                    // bazı alanlarda doğrudan image olabilir
                    $src = $media['image'];
                }
            } elseif (isset($media['id']) && is_numeric($media['id'])) {
                // generic attachment id alanı
                $maybe = wp_get_attachment_url((int)$media['id']);
                if ($maybe) { $src = $maybe; }
            }
        } elseif (is_int($media)) {
            // ham attachment id
            $maybe = wp_get_attachment_url($media);
            if ($maybe) { $src = $maybe; }
        }

        // 3) SaltHareket\Image çağrısı
        $args['src'] = $src;

        try {
            $image = new \SaltHareket\Image($args);
            return $image->init();
        } catch (\Throwable $e) {
            error_log('get_thumbnail failed: '.$e->getMessage());
            return '';
        }
    }*/

    public function get_thumbnail(array $args = []){
        $media = $this->meta('media');
        $src   = '';

        // 1) Media alanı varsa kullan
        if ($media) {
            // JSON string mi?
            if (is_string($media)) {
                $decoded = json_decode($media, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $media = $decoded;
                } elseif (ctype_digit($media)) {
                    $maybe = wp_get_attachment_url((int)$media);
                    if ($maybe) { $src = $maybe; }
                }
            }

            // Array ise
            if (is_array($media)) {
                $type = $media['media_type'] ?? null;

                if ($type === 'image') {
                    if (!empty($media['use_responsive_image']) && !empty($media['image_responsive'])) {
                        $src = $media['image_responsive'];
                    } elseif (!empty($media['image'])) {
                        $src = $media['image'];
                    }
                } elseif (!empty($media['id']) && is_numeric($media['id'])) {
                    $maybe = wp_get_attachment_url((int)$media['id']);
                    if ($maybe) { $src = $maybe; }
                }
            } elseif (is_int($media)) {
                $maybe = wp_get_attachment_url($media);
                if ($maybe) { $src = $maybe; }
            }
        }

        // 2) Fallback: featured image
        if (empty($src)) {
            $thumbnail_id = get_post_thumbnail_id($this->ID ?? 0);
            if ($thumbnail_id) {
                $src = wp_get_attachment_url($thumbnail_id) ?: '';
            }
        }

        // 3) Default fallback (hiçbir görsel yoksa boş string)
        if (empty($src)) {
            $src = '';
        }

        $args['src'] = $src;

        // 4) SaltHareket\Image güvenli çağrı
        try {
            $image = new \SaltHareket\Image($args);
            return $image->init();
        } catch (\Throwable $e) {
            error_log('get_thumbnail failed: ' . $e->getMessage());
            return '';
        }
    }




    /**
     * $post->slug_default erişimini mümkün kıl
     */
    public function __get($key) {
        if ($key === 'slug_default') {
            return $this->get_slug_default();
        }
        return parent::__get($key);
    }


    public function pll_get_post($lang){
        $fallback = $this->ID;
        $ml = trim((string) (ENABLE_MULTILANGUAGE ?? ''));
        if ($ml === '' || $ml !== "polylang" || $lang === '') {
            return $fallback;
        }
        $lang_id = $fallback;
        if (function_exists('pll_get_post')) {
            $lang_id = pll_get_post($this->ID, $lang);
        }
        return $lang_id;
    }

    public function pll_get_post_default(){
        $fallback = $this->ID;
        $ml = trim((string) (ENABLE_MULTILANGUAGE ?? ''));
        if ($ml === '' || $ml !== "polylang") {
            return $fallback;
        }
        $default_id = $fallback;
        if (function_exists('pll_get_post')) {
            $def = pll_default_language();
            if ($def) {
                $default_id = pll_get_post($this->ID, $def);
            }
        }
        return $default_id;
    }

    /**
     * Varsayılan dil slug’ını döndürür.
     * Polylang / WPML / qTranslate-XT (slug çeviri aktifse) destekler.
     * Hiçbiri yoksa normal slug döner.
     */
    public function get_slug_default(): string {
        $fallback = $this->post_name ?: ($this->slug ?? '');
        $ml = trim((string) (ENABLE_MULTILANGUAGE ?? ''));

        if ($ml === '') {
            return $fallback;
        }

        switch (strtolower($ml)) {
            case 'polylang':
                if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
                    $def = pll_default_language();
                    if ($def) {
                        $default_id = pll_get_post($this->ID, $def);
                        if ($default_id) {
                            $slug = get_post_field('post_name', $default_id);
                            if ($slug) return $slug;
                        }
                    }
                }
                return $fallback;

            case 'wpml':
                // WPML default language ve object id alma
                if (has_filter('wpml_default_language') && has_filter('wpml_object_id')) {
                    $def = apply_filters('wpml_default_language', null);
                    $default_id = apply_filters('wpml_object_id', $this->ID, $this->post_type, true, $def);
                    if ($default_id) {
                        $slug = get_post_field('post_name', $default_id);
                        if ($slug) return $slug;
                    }
                }
                return $fallback;

            case 'qtranslate-xt':
            case 'qtranslate_xt':
            case 'qtranslate':
                // qTranslate-XT tek post kullanır; slug çeviri eklentisi/metası varsa çek
                // 1) Varsayılan dili bul
                $def = function_exists('qtranxf_getLanguageDefault') ? qtranxf_getLanguageDefault() : null;

                // 2) Yaygın meta şemaları:
                //    a) _qts_slug_{lang}  (qTranslate Slug eklentisi)
                //    b) _qtranslate_slugs (JSON/serialized map)
                if ($def) {
                    // a) _qts_slug_tr gibi
                    $slug_a = get_post_meta($this->ID, '_qts_slug_' . $def, true);
                    if (!empty($slug_a)) return sanitize_title($slug_a);

                    // b) _qtranslate_slugs → array veya json
                    $slugs_meta = get_post_meta($this->ID, '_qtranslate_slugs', true);
                    if (!empty($slugs_meta)) {
                        if (is_string($slugs_meta)) {
                            $decoded = json_decode($slugs_meta, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $slugs_meta = $decoded;
                            } else {
                                // serialize edilmiş olabilir
                                $unser = @unserialize($slugs_meta);
                                if ($unser !== false && is_array($unser)) {
                                    $slugs_meta = $unser;
                                }
                            }
                        }
                        if (is_array($slugs_meta) && !empty($slugs_meta[$def])) {
                            return sanitize_title($slugs_meta[$def]);
                        }
                    }
                }
                // qTranslate’da özel slug yoksa normal post_name
                return $fallback;

            default:
                // Bilinmeyen/özel ML sistemi -> düş
                return $fallback;
        }
    }

    public function get_terms_default($taxonomy, $lang = null) {
        if (!$taxonomy) return [];

        $terms = wp_get_post_terms($this->ID, $taxonomy);

        if (empty($terms)) return [];

        $result = [];
        foreach ($terms as $term) {
            $term_id = null;

            if ($term instanceof Term) {
                $term_id = $term->id;
            } elseif ($term instanceof WP_Term) {
                $term_id = $term->term_id;
            } elseif (is_numeric($term)) {
                $term_id = intval($term);
            }

            if ($term_id && function_exists('pll_get_term')) {
                $target_lang = $lang ?: pll_default_language();
                $translated_id = pll_get_term($term_id, $target_lang);
                if ($translated_id) {
                    $term_id = $translated_id;
                }
            }

            $result[] = Timber::get_term($term_id);
        }

        return $result;
    }


}