<?php

use SaltHareket\Image;
use Carbon\Carbon;

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
        $map_service = QueryCache::get_field("map_service", "options");//get_option("options_map_service");
        $location_data = $this->contact["map_".$map_service];
        $map_url = $this->contact["map_url"];
        if($location_data){
            $map_marker = $this->contact["map_marker"];
            if($map_marker){
                $marker = $map_marker;
            }else{
                $marker = QueryCache::get_field("map_marker", "options");
            }
            if(!$marker){
                 $marker = QueryCache::get_field("logo_marker", "options");
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
                $data["map_url"] = $location_data["map_url"];
            }
            if($popup){
               $data["popup"] = esc_html($this->get_map_popup());
            }            
        }elseif(!empty($map_url)){
            $data["map_url"] = $map_url;
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
            $code ='<iframe
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
        $user = Data::get("user");
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
                        $this->get_local_date("", "", $user->get_timezone()) . " GMT" . $this->get_gmt() . "</span>" .
                    "</div>" .
                "</div>";
    }


    public function get_video_embed($url="", $image_size=0, $attrs=[]){
        $embed = new OembedVideo($url, $image_size, $attrs);
        return $embed->get($attrs);
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
    public function get_blocks($args = []) {
        $content = '';
        $all_required_js = [];
        $lang = Data::get("language") ?? 'tr'; // Dili alıyoruz

        if (has_blocks($this)) {
            $blocks = parse_blocks($this->post_content);

            // 1. HERO ve Filtreleme İşlemleri (Mevcut mantığın)
            $blocks = array_filter($blocks, function ($block) {
                return !isset($block['attrs']['data']['block_settings_hero']) || !$block['attrs']['data']['block_settings_hero'];
            });

            if (in_array(get_query_var("qpt_settings"), [2, 3])) {
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

            // 2. BLOKLARI İŞLEME VE RENDER (İşte sihir burada başlıyor)
            $final_html = '';
            $index = 0;

            foreach ($blocks as $block) {
                if (empty($block['blockName'])) continue;

                $block_type = WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']);
                
                // Required JS toplama
                if (isset($block_type->required_js) && !empty($block_type->required_js)) {
                    $all_required_js = array_merge($all_required_js, $block_type->required_js);
                }

                // Index ekle (Zero-based)
                $block['attrs']['index'] = $index;
                $index++;

                // --- CACHE KONTROLÜ ---
                $block_html = '';
                $custom_id = $block['attrs']['data']['block_settings_custom_id'] ?? null;

                /*if ($custom_id && isset($block_type->cache_html) && $block_type->cache_html) {
                    // Option Name: block_id . _html_ . language
                    $option_name = $custom_id . '_html_' . $lang;
                    $block_html = "cache abi".get_option($option_name)."ablam"; // Veritabanından direkt oku
                }*/

                // Eğer cache yoksa veya cache özelliği kapalıysa normal render et
                if (empty($block_html)) {
                    $block_html = render_block($block);
                }

                $final_html .= $block_html;
            }

            $content = $final_html;

            // 3. JS ve CSS AYRIŞTIRMA (Mevcut mantığın)
            if (isset($args["extract_js"])) {
                $html_only = "";
                $js_only = "";
                $html_only = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($matches) use (&$js_only) {
                    $js_only .= $matches[0] . "\n";
                    return '';
                }, $content);
                $content = array(
                    "html" => $html_only,
                    "js" => $js_only
                ); 
            }

            $seperate_css = isset($args["seperate_css"]) ? (bool)$args["seperate_css"] : SEPERATE_CSS;
            $seperate_js = isset($args["seperate_js"]) ? (bool)$args["seperate_js"] : SEPERATE_JS;
        
            if (!isset($_GET["fetch"]) && ($seperate_css || $seperate_js)) { 
                $tags = "";
                if ($seperate_css) $tags = "<style>";
                if ($seperate_js) $tags .= "<script>";
                
                if (!empty($tags)) {
                    // Eğer content array ise (extract_js yapılmışsa) sadece html kısmını işle
                    if (is_array($content)) {
                        $content["html"] = $this->strip_tags($content["html"], $tags);
                    } else {
                        $content = $this->strip_tags($content, $tags);
                    }
                }
            }

        } else {
            if (in_array(get_query_var("qpt_settings"), [2, 3])) return "";
            $content = $this->content;
        }

        return array(
            "html" => $content,
            "required_js" => array_values(array_unique($all_required_js))
        ); 
    }
    private function get_block_dependencies($block_name) {
        $slug = str_replace('acf/', '', $block_name);
        
        // Twig dosyasının yolunu belirle (Kendi klasör yapına göre güncelle)
        $file_path = get_template_directory() . '/views/blocks/' . $slug . '.twig';

        if (!file_exists($file_path)) return [];

        $content = file_get_contents($file_path);
        
        // Yorum bloğu içindeki "Required:" satırını yakala
        preg_match('/Required:\s*(.*)/i', $content, $matches);

        if (isset($matches[1])) {
            // Virgülle ayrılmış kütüphaneleri temizleyip array yap
            return array_map('trim', explode(',', $matches[1]));
        }

        return [];
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
    public function post2Root(){
        return post2Root($this->ID);
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

    public function strip_tags($content = "", $allowed_tags = "<script><style>") {
        if (empty($content)) return "";

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // UTF-8 desteği için başlangıcı ekle
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $allowed_tags_array = explode('><', trim($allowed_tags, '<>'));

        foreach ($allowed_tags_array as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                if (!$element->hasAttribute('data-inline') || $element->getAttribute('data-inline') !== 'true') {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // Normal HTML çıktısını al
        $output = $dom->saveHTML();

        // 1. Gereksiz XML başlangıcını temizle
        $output = str_replace('<?xml encoding="utf-8" ?>', '', $output);

        // 2. 🔥 EN TEMİZ ÇÖZÜM: Hatalı </source> kapatmalarını Regex ile temizle
        // Bu sayede hiyerarşi bozulmadan sadece o "kırmızı" yapan tagleri sileriz.
        $output = preg_replace('/<\/source>/i', '', $output);

        return html_entity_decode($output, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

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
            //error_log('get_thumbnail failed: ' . $e->getMessage());
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
    public function pll_get_terms_default($taxonomy, $lang = null) {
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



    public function merge_dates() {
        Carbon::setLocale(Data::get("language") ?? 'tr');

        $dt1_raw = trim((string) $this->meta("start_date"));
        $dt2_raw = trim((string) $this->meta("end_date"));
        $period  = $this->meta("period") ?? false;

        // Günleri locale'e göre çevir
        $daysMap = [
            0 => Carbon::create()->startOfWeek()->addDays(0)->translatedFormat('l'), // Pazartesi
            1 => Carbon::create()->startOfWeek()->addDays(1)->translatedFormat('l'),
            2 => Carbon::create()->startOfWeek()->addDays(2)->translatedFormat('l'),
            3 => Carbon::create()->startOfWeek()->addDays(3)->translatedFormat('l'),
            4 => Carbon::create()->startOfWeek()->addDays(4)->translatedFormat('l'),
            5 => Carbon::create()->startOfWeek()->addDays(5)->translatedFormat('l'),
            6 => Carbon::create()->startOfWeek()->addDays(6)->translatedFormat('l'), // Pazar
        ];

        $days = [];
        if (is_array($period) && !empty($period)) {
            foreach ($period as $d) {
                if (isset($daysMap[$d])) {
                    $days[] = $daysMap[$d];
                }
            }
        }

        // start_date yok ve sadece period varsa
        if (empty($dt1_raw) && empty($dt2_raw) && !empty($days)) {
            return sprintf(
                /* translators: %s gün adlarını içerir */
                translate('Her %s'),
                implode(", ", $days)
            );
        }

        // start_date parse et
        $dt1 = null;
        if (!empty($dt1_raw)) {
            try {
                $dt1 = Carbon::createFromFormat('Y-m-d H:i', $dt1_raw);
            } catch (\Exception $e) {
                $dt1 = null;
            }
        }

        // end_date parse et
        $dt2 = null;
        if (!empty($dt2_raw)) {
            try {
                $dt2 = Carbon::createFromFormat('Y-m-d H:i', $dt2_raw);
            } catch (\Exception $e) {
                $dt2 = null;
            }
        }

        // sadece period + start_date varsa
        if ($dt1 && !$dt2 && !empty($days)) {
            return sprintf(
                /* translators: 1: başlangıç tarihi, 2: gün adları */
                translate('%1$s tarihinden itibaren her %2$s'),
                $dt1->translatedFormat('j F Y l H:i'),
                implode(", ", $days)
            );
        }

        // sadece period + end_date varsa
        if ($dt2 && !$dt1 && !empty($days)) {
            return sprintf(
                /* translators: 1: bitiş tarihi, 2: gün adları */
                translate('%1$s tarihine kadar her %2$s'),
                $dt2->translatedFormat('j F Y l H:i'),
                implode(", ", $days)
            );
        }

        // hem start hem end hem de period varsa
        if ($dt1 && $dt2 && !empty($days)) {
            return sprintf(
                /* translators: 1: başlangıç tarihi, 2: bitiş tarihi, 3: gün adları */
                translate('%1$s - %2$s arası her %3$s'),
                $dt1->translatedFormat('j F Y l H:i'),
                $dt2->translatedFormat('j F Y l H:i'),
                implode(", ", $days)
            );
        }

        // === period yok, default eski mantık ===
        if ($dt1 && !$dt2) {
            return $dt1->translatedFormat('j F Y l H:i');
        }

        if ($dt1 && $dt2) {
            if ($dt1->isSameDay($dt2)) {
                if ($dt1->hour === $dt2->hour) {
                    return $dt1->translatedFormat('j F Y l H:i');
                } else {
                    return $dt1->translatedFormat('j F Y l H:i') . ' - ' . $dt2->translatedFormat('H:i');
                }
            }

            if ($dt1->isSameYear($dt2)) {
                if ($dt1->isSameMonth($dt2)) {
                    return $dt1->translatedFormat('j') . ' - ' . $dt2->translatedFormat('j') . ' ' .
                           $dt1->translatedFormat('F Y H:i') . ' - ' . $dt2->translatedFormat('H:i');
                } else {
                    return $dt1->translatedFormat('j F') . ' - ' . $dt2->translatedFormat('j F') . ' ' .
                           $dt1->translatedFormat('Y H:i') . ' - ' . $dt2->translatedFormat('H:i');
                }
            }

            return $dt1->translatedFormat('j F Y l H:i') . " - " . $dt2->translatedFormat('j F Y l H:i');
        }

        return null;
    }

    public function get_custom_template_data($type = "modal") {
        if (!in_array($type, ['offcanvas', 'modal'])) {
            return false;
        }

        $attrs = $this->meta($type);
        $template = $this->meta("template");

        if (empty($attrs)) return false;

        $data_strings = [];

        $data_strings[] = sprintf('data-id="%s"', esc_attr($this->ID));
        $data_strings[] = sprintf('data-class="%s"', esc_attr($this->slug));
        
        // Template varsa data-template olarak ekle
        if ($template) {
            $data_strings[] = sprintf('data-template="%s"', esc_attr("_custom/".$template));
        }

        foreach ($attrs as $key => $value) {
            if (empty($key)) continue;

            // --- FULLSCREEN MANTIĞI ---
            if ($key === 'fullscreen') {
                // "none" ise veya boşsa HTML'e hiç eklemiyoruz
                if ($value === 'none' || empty($value)) {
                    continue;
                }
                // "none" değilse (örn: modal-fullscreen-md-down), değeri olduğu gibi basıyoruz
                $data_strings[] = sprintf('data-fullscreen="%s"', esc_attr($value));
                continue;
            }

            // --- DİĞER DEĞERLER ---
            $val = $value;
            // Boolean dönüşümleri (JS tarafında dataset.centered === 'true' kontrolü için)
            if ($val === "1" || $val === 1) $val = "true";
            if ($val === "0" || $val === 0) $val = "false";

            $data_strings[] = sprintf('data-%s="%s"', esc_attr($key), esc_attr($val));
        }

        return implode(' ', $data_strings);
    }
}