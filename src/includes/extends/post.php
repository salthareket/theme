<?php

use SaltHareket\Image;
use Carbon\Carbon;

/**
 * Post — Timber\Post extend'i.
 * Tum post type'lar icin kullanilir (page, post, custom post type).
 *
 * @version 1.2.0
 * @changelog
 *   1.2.0 - 2026-05-08
 *     - Add: reaction_count() — post'un reaction sayisi
 *     - Add: has_reaction() — mevcut kullanici reaction yapti mi
 *     - Add: reaction_counts() — tum tiplerin sayilari (array)
 *     - Add: reaction_button() — reaction button HTML'i render et
 *   1.1.0 - 2026-05-07
 *     - Add: get_thumbnail() — media field + fallback featured image
 *     - Add: pll_get_post(), pll_get_post_default() — Polylang entegrasyonu
 *   1.0.0 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Twig'de:
 * {{ post.reaction_count('like') }}
 * {% if post.has_reaction('favorite') %}...{% endif %}
 * {{ post.reaction_button('like', {'style': 'pill'})|raw }}
 * {% set counts = post.reaction_counts %}
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   {{ post.reaction_count('like') }}
 *
 * @example
 *   {% if post.has_reaction('favorite') %}<span>Favorilendi</span>{% endif %}
 *
 * @example
 *   {{ post.reaction_button('like', {'style': 'icon-count'})|raw }}
 *
 * @example
 *   {{ post.reaction_button('clap', {'style': 'icon-count', 'require_login': false})|raw }}
 *
 * @example
 *   {% set counts = post.reaction_counts %}{{ counts.like }} like, {{ counts.clap }} clap
 */

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
        $map_service = \SaltHareket\SaltConfig::get('options_map_service', 'leaflet');
        $map_key = "map_" . ($map_service ?: 'leaflet');
        $location_data = ( $map_service && is_array($this->contact) ) ? ( $this->contact[$map_key] ?? null ) : null;
        $map_url = is_array($this->contact) ? ( $this->contact["map_url"] ?? '' ) : '';

        if($location_data){
            $map_marker = $this->contact["map_marker"];
            if($map_marker){
                $marker = $map_marker;
            }else{
                $marker = \SaltHareket\SaltConfig::get('options_map_marker');
            }
            if(!$marker){
                $marker = \SaltHareket\SaltConfig::get('options_logo_marker');
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
        $image = $map_data["image"] ?? ($this->thumbnail ? $this->thumbnail->src('thumbnail') : '');
        return  "<div class='row gx-3 gy-2'>" .
                    "<div class='col-auto'>" .
                         "<img src='" . esc_url($image) . "' class='img-fluid rounded' style='max-width:50px;'/>" .
                    "</div>" .
                    "<div class='col'>" .
                        "<ul class='list-unstyled m-0'>" .
                            "<li class='fw-bold'>" . esc_html($map_data["title"] ?? '') . "</li>" .
                            "<li class='text-muted' style='font-size:12px;'>" . esc_html($this->get_location() ?? '') . "</li>" .
                        "</ul>" .
                    "</div>" .
                    "<div class='col-12 text-primary' style='font-size:12px;'>" .
                        ($user ? $this->get_local_date("", "", $user->get_timezone()) : '') . ($user ? " GMT" . $this->get_gmt() : '') .
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
                // ACF V3 / WP 7.0: attrs.data içinde veya direkt attrs içinde olabilir
                $in_data  = isset($block['attrs']['data']['block_settings_hero']) && $block['attrs']['data']['block_settings_hero'];
                $in_attrs = isset($block['attrs']['block_settings_hero']) && $block['attrs']['block_settings_hero'];
                return !$in_data && !$in_attrs;
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
                        // data-inline style'lari koru — strip_tags oncesi cikar, sonra geri ekle
                        $inline_styles = '';
                        $content["html"] = preg_replace_callback(
                            '/<style\b[^>]*\bdata-inline\b[^>]*>.*?<\/style>/is',
                            function( $m ) use ( &$inline_styles ) { $inline_styles .= $m[0]; return ''; },
                            $content["html"]
                        );
                        $content["html"] = $this->strip_tags($content["html"], $tags) . $inline_styles;
                    } else {
                        // data-inline style'lari koru
                        $inline_styles = '';
                        $content = preg_replace_callback(
                            '/<style\b[^>]*\bdata-inline\b[^>]*>.*?<\/style>/is',
                            function( $m ) use ( &$inline_styles ) { $inline_styles .= $m[0]; return ''; },
                            $content
                        );
                        $content = $this->strip_tags($content, $tags) . $inline_styles;
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
        // post.content çağrısında hero block'u filtrele
        // Hero block üstte ayrıca render ediliyor — content içinde duplicate olmasın
        if ($key === 'content') {
            return $this->content_without_hero();
        }
        return parent::__get($key);
    }

    /**
     * Hero block (block_settings_hero=1) olmadan content döndür.
     * {{ post.content }} → hero block'u strip edilmiş HTML
     * 
     * Hem Gutenberg block'ları hem klasik editör içeriğini handle eder.
     */
    public function content_without_hero(): string {
        // Boş içerik kontrolü — sonsuz döngü önleme
        $raw_content = $this->post_content ?? '';
        if ( empty( trim( $raw_content ) ) ) {
            return '';
        }

        // Klasik editör içeriği (block yok) — direkt döndür
        if ( ! has_blocks( $raw_content ) ) {
            // apply_filters + do_shortcode ile işle (WP'nin standart content filtresi)
            $content = apply_filters( 'the_content', $raw_content );
            return $content;
        }

        // Gutenberg içeriği — hero block'u filtrele
        $blocks = parse_blocks( $raw_content );
        
        // Parse edilen block'lar boş mu kontrolü
        $has_real_blocks = false;
        foreach ( $blocks as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $has_real_blocks = true;
                break;
            }
        }
        if ( ! $has_real_blocks ) {
            return '';
        }

        $filtered = array_filter( $blocks, function ( $block ) {
            // ACF V3: attrs.data içinde
            $in_data  = isset( $block['attrs']['data']['block_settings_hero'] ) && $block['attrs']['data']['block_settings_hero'];
            // Fallback: direkt attrs içinde (WP 7.0 bazı durumlarda)
            $in_attrs = isset( $block['attrs']['block_settings_hero'] ) && $block['attrs']['block_settings_hero'];
            return ! $in_data && ! $in_attrs;
        } );

        // Filtrelenmiş block'lar boş mu kontrolü
        if ( empty( $filtered ) ) {
            return '';
        }

        $html = '';
        foreach ( $filtered as $block ) {
            if ( empty( $block['blockName'] ) ) continue;
            try {
                $html .= render_block( $block );
            } catch ( \Throwable $e ) {
                // Block render hatası — sessizce atla, diğer block'ları render etmeye devam et
                if ( defined('ENABLE_LOGS') && ENABLE_LOGS ) {
                    error_log('[content_without_hero] Block render error: ' . $e->getMessage() . ' in ' . ($block['blockName'] ?? 'unknown'));
                }
            }
        }
        
        // Boş HTML kontrolü
        if ( empty( trim( $html ) ) ) {
            return '';
        }
        
        // render_block() paragraph içindeki shortcode'ları işlemiyor — do_shortcode ile tamamla
        return do_shortcode( $html );
    }

    /**
     * Timber content() override — hero block'u strip eder.
     * {{ post.content() }} ve {{ post.content }} ikisi de buraya gelir.
     */
    public function content( $page = 0, $len = -1, $remove_blocks = false ): string {
        return $this->content_without_hero();
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
                trans('Every %s'),
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
                trans('Every %2$s starting from %1$s'),
                $dt1->translatedFormat('j F Y l H:i'),
                implode(", ", $days)
            );
        }

        // sadece period + end_date varsa
        if ($dt2 && !$dt1 && !empty($days)) {
            return sprintf(
                /* translators: 1: bitiş tarihi, 2: gün adları */
                trans('Every %2$s until %1$s'),
                $dt2->translatedFormat('j F Y l H:i'),
                implode(", ", $days)
            );
        }

        // hem start hem end hem de period varsa
        if ($dt1 && $dt2 && !empty($days)) {
            return sprintf(
                /* translators: 1: başlangıç tarihi, 2: bitiş tarihi, 3: gün adları */
                trans('Every %3$s between %1$s - %2$s'),
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

    // ── REACTIONS ────────────────────────────────────────────────────────────

    /**
     * Bu post'un belirli bir reaction sayisi.
     * Twig: {{ post.reaction_count('like') }}
     */
    public function reaction_count( string $type = 'like' ): int {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return 0;
        return \SaltHareket\Reactions\Reactions::count( $type, $this->ID, 'post' );
    }

    /**
     * Mevcut kullanici bu post'a reaction yapti mi?
     * Twig: {% if post.has_reaction('like') %}
     */
    public function has_reaction( string $type = 'like' ): bool {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return false;
        return \SaltHareket\Reactions\Reactions::has( $type, $this->ID, 'post' );
    }

    /**
     * Bu post'a yapilan tum reaction sayilari (type => count).
     * Twig: {% set counts = post.reaction_counts %}
     */
    public function reaction_counts(): array {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return [];
        $types  = \SaltHareket\Reactions\ReactionsSettings::getTypes();
        $result = [];
        foreach ( array_keys( $types ) as $type ) {
            $result[ $type ] = \SaltHareket\Reactions\Reactions::count( $type, $this->ID, 'post' );
        }
        return $result;
    }

    /**
     * Reaction button HTML'i render et.
     * Twig: {{ post.reaction_button('like', {'style': 'pill'}) }}
     */
    public function reaction_button( string $type = 'like', array $options = [] ): string {
        if ( ! class_exists( \SaltHareket\Reactions\Admin\ReactionsAjax::class ) ) return '';
        return \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $this->ID, 'post', $type, $options );
    }

    /**
     * Post tipine göre kayıtlı tüm aktif reaction butonlarını render eder.
     *
     * Reactions Generator'da tanımlı button'ları okur:
     *  - object_type = 'post'
     *  - subtype = bu post'un post_type'ı VEYA '' (wildcard — tüm post türlerine)
     *  - active = true
     *
     * Twig kullanımı:
     *   {{ post.reaction_buttons()|raw }}
     *   {{ post.reaction_buttons({'style': 'pill'})|raw }}
     *   {{ post.reaction_buttons({}, 'div', 'gap-2 my-2')|raw }}
     *
     * @param  array  $options      Her butona aktarılacak opsiyonel override'lar (style, class vb.)
     * @param  string $wrapper_tag  Wrapper HTML elementi. '' = wrapper yok. Default: 'div'
     * @param  string $wrapper_class Wrapper class'ı. Default: 'd-flex gap-1 align-items-center'
     * @return string HTML
     */
    public function reaction_buttons( array $options = [], string $wrapper_tag = 'div', string $wrapper_class = 'd-flex gap-1 align-items-center' ): string {
        if ( ! class_exists( \SaltHareket\Reactions\Admin\ReactionsAjax::class ) ) return '';
        if ( ! defined('ENABLE_REACTIONS') || ! ENABLE_REACTIONS ) return '';

        $all_buttons = (array) get_option( 'sh_reactions_buttons', [] );

        $post_type  = $this->post_type ?? get_post_type( $this->ID );
        $match_type = ( $post_type === 'product_variation' ) ? 'product' : $post_type;
        $html       = '';

        foreach ( $all_buttons as $btn ) {
            if ( ( $btn['object_type'] ?? '' ) !== 'post' ) continue;
            if ( isset( $btn['active'] ) && ! $btn['active'] ) continue;
            $btn_subtype = $btn['subtype'] ?? '';
            if ( $btn_subtype !== '' && $btn_subtype !== $match_type ) continue;
            $type = $btn['type'] ?? '';
            if ( ! $type ) continue;
            $html .= \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $this->ID, 'post', $type, $options );
        }

        if ( $html === '' ) return new \Twig\Markup( '', 'UTF-8' );
        if ( $wrapper_tag === '' ) return new \Twig\Markup( $html, 'UTF-8' );
        return new \Twig\Markup( "<{$wrapper_tag} class=\"" . esc_attr( $wrapper_class ) . "\">{$html}</{$wrapper_tag}>", 'UTF-8' );
    }

    // ── END REACTIONS ─────────────────────────────────────────────────────────

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

    // ── LOCATION ──────────────────────────────────────────────────────────────

    /**
     * Post'un atandığı region term'leri.
     * Twig: {% for region in post.regions %}{{ region.name }}{% endfor %}
     */
    public function get_regions(): array {
        if ( ! taxonomy_exists( 'region' ) ) return [];
        $terms = get_the_terms( $this->ID, 'region' );
        return ( $terms && ! is_wp_error( $terms ) ) ? $terms : [];
    }

    /**
     * Post belirli bir region'da mı?
     * Twig: {% if post.in_region('TR') %}...{% endif %}
     */
    public function in_region( string $country_code = '' ): bool {
        if ( ! taxonomy_exists( 'region' ) || empty( $country_code ) ) return true;
        $region_ids = function_exists( 'get_region_by_country_code' )
            ? get_region_by_country_code( $country_code )
            : [];
        if ( empty( $region_ids ) ) return true;
        return has_term( $region_ids, 'region', $this->ID );
    }

    /**
     * Post'un şehir adı (meta'dan).
     * Twig: {{ post.city_name }}
     */
    public function get_city_name(): string {
        $city = $this->meta( 'city' );
        if ( empty( $city ) ) return '';
        return function_exists( 'get_city_name' ) ? get_city_name( 'id', $city ) : '';
    }

    /**
     * Post'un ülke adı (meta'dan).
     * Twig: {{ post.country_name }}
     */
    public function get_country_name(): string {
        $country = $this->meta( 'country' ) ?: $this->meta( 'billing_country' );
        if ( empty( $country ) ) return '';
        return function_exists( 'get_country_name' ) ? get_country_name( 'iso2', $country ) : $country;
    }

    // ── END LOCATION ──────────────────────────────────────────────────────────
}

/**
 * WooProduct
 *
 * WooCommerce product ve product_variation için Timber Post extend'i.
 * ThemeProduct bu class'ı extend eder — core WC metodları burada,
 * proje özel metodlar theme/includes/extends/post.php'deki ThemeProduct'ta.
 *
 * Twig class mapping (twig-extends.php):
 *   product           → ThemeProduct (extends WooProduct)
 *   product_variation → ThemeProduct (extends WooProduct)
 */

/**
 * ThemeProduct - WooCommerce + Timber product extend.
 *
 * @version 1.2.0
 *
 * @changelog
 *   1.2.0 - 2026-04-07
 *     - Add: Variation images - variation_images(), variation_thumbnails(), default_variation_images()
 *     - Add: Free shipping helpers - free_shipping_amount(), has_free_shipping()
 *     - Add: Price helpers - variable_price_html(), price_with_currency(), currency_symbol()
 *     - Add: Product cover bridge - product_cover(), woo_gallery()
 *     - Add: Variation loop data - variations_loop(), variations_unique()
 *     - Add: Order/payment helpers - order_ids(), payments(), payment_is_complete(), deposit_payment_is_complete()
 *     - Add: Role based pricing - role_based_price()
 *     - Add: Static methods - best_sellers(), available_categories()
 *     - Add: URL helpers - url_pa_parse()
 *   1.1.0 - 2026-04-03
 *     - Add: Full product extend - pricing, stock, badges, gallery, variations,
 *            attributes, categories, Schema.org, cart helpers, reviews
 *     - Fix: get_title infinite recursion + qtranxf_use safety
 *     - Fix: is_in_grouped performance (single SQL)
 *     - Fix: category() and get_product_attribute() null safety
 *   1.0.0 - Onceki stabil versiyon
 *
 * How to use (Twig):
 *
 *   Twig Kullanim                                              Aciklama                              Donus Ornegi
 *   ─────────────────────────────────────────────────────────── ───────────────────────────────────── ─────────────────────────────────────────────
 *
 *   -- PRICING --
 *   {{ post.price_html }}                                      Fiyat HTML (indirimli/normal)         '<del>200,00 TL</del> <ins>149,90 TL</ins>'
 *   {{ post.price }}                                           Aktif fiyat (sayi)                    149.9
 *   {{ post.regular_price }}                                   Normal fiyat                          200.0
 *   {{ post.sale_price }}                                      Indirimli fiyat                       149.9
 *   {{ post.sale_percentage }}                                 Indirim yuzdesi                       25
 *   {{ post.is_on_sale }}                                      Indirimde mi                          true
 *   {{ post.formatted_price }}                                 Formatli fiyat                        '149,90 TL'
 *   {{ post.variable_price_html }}                             Variable fiyat araligi                '<del>200 TL</del> <ins>149 TL</ins>'
 *   {{ post.price_with_currency }}                             Fiyat + para birimi                   '149,90 <span class="currency">TL</span>'
 *   {{ post.currency_symbol }}                                 Para birimi sembolu                   'TL'
 *   {{ post.role_based_price }}                                Kullaniciya ozel fiyat                ['regular_price' => '180', ...]
 *
 *   -- STOCK --
 *   {{ post.is_in_stock }}                                     Stokta mi                             true
 *   {{ post.stock_quantity }}                                  Stok miktari                          42
 *   {{ post.stock_status_label }}                              Stok durumu etiketi                   'Stokta'
 *   {{ post.is_low_stock }}                                    Dusuk stok mu                         true
 *   {{ post.low_stock_amount }}                                Low stock threshold                   5
 *   {{ post.backorders_allowed }}                              Backorder kabul ediyor mu             false
 *
 *   -- PRODUCT META --
 *   {{ post.sku }}                                             SKU                                   'PRD-001'
 *   {{ post.weight }}                                          Agirlik                               '1.5 kg'
 *   {{ post.dimensions }}                                      Boyutlar                              '30 x 20 x 10 cm'
 *   {{ post.product_type }}                                    Urun tipi                             'variable'
 *   {{ post.is_featured }}                                     One cikarilmis mi                     true
 *   {{ post.is_purchasable }}                                  Satin alinabilir mi                   true
 *   {{ post.is_virtual }}                                      Virtual urun mu                       false
 *   {{ post.is_downloadable }}                                 Downloadable mi                       false
 *   {{ post.short_description }}                               Kisa aciklama                         'Urun ozeti metni...'
 *
 *   -- IMAGES & GALLERY --
 *   {{ post.cover_url('large') }}                              Ana gorsel URL                        'https://site.com/image.jpg'
 *   {{ post.gallery_ids }}                                     Galeri attachment ID'leri              [45, 46, 47]
 *   {{ post.gallery_urls('medium') }}                          Galeri URL'leri                       ['https://...jpg', 'https://...jpg']
 *   {{ post.all_image_urls('large') }}                         Cover + galeri hepsi                  ['https://cover.jpg', 'https://g1.jpg', ...]
 *   {{ post.product_cover(true) }}                             Kapak gorselleri (grouped dahil)      ['https://...jpg', 'https://...jpg']
 *   {{ post.woo_gallery }}                                     WooCommerce galeri URL'leri            ['https://...jpg', 'https://...jpg']
 *
 *   -- VARIATION IMAGES --
 *   {{ post.variation_images('color','kirmizi','large') }}     Varyasyon gorselleri                  ['https://var1.jpg', 'https://var2.jpg']
 *   {{ post.variation_thumbnails('color','thumbnail') }}       Renk secici thumbnails                [{slug:'kirmizi', name:'Kirmizi', color:'#f00', image:'url'}, ...]
 *   {{ post.default_variation_images('large') }}               Default varyasyon gorselleri          ['https://default1.jpg', 'https://default2.jpg']
 *
 *   -- TAXONOMY --
 *   {{ post.categories }}                                      Kategoriler (Timber\Term[])           [Term{name:'Elbise'}, Term{name:'Kadin'}]
 *   {{ post.primary_category }}                                Birincil kategori                     Term{name:'Elbise', link:'/elbise/'}
 *   {{ post.tags }}                                            Etiketler (Timber\Term[])             [Term{name:'Yeni Sezon'}, ...]
 *
 *   -- ATTRIBUTES --
 *   {{ post.get_product_attribute('color') }}                  Tek attribute (Timber\Term[])         [Term{name:'Kirmizi'}, Term{name:'Mavi'}]
 *   {{ post.all_attributes }}                                  Tum attribute'lar                     {color: {label:'Renk', terms:[{id:1, name:'Kirmizi', slug:'kirmizi', color:'#f00'}], ...}}
 *
 *   -- VARIATIONS --
 *   {{ post.is_variable }}                                     Variable urun mu                      true
 *   {{ post.variations }}                                      Tum varyasyonlar (WC format)          [{variation_id:55, attributes:{...}, ...}, ...]
 *   {{ post.variation_attributes }}                            Varyasyon attribute'lari              {pa_color: ['kirmizi','mavi'], pa_size: ['s','m','l']}
 *   {{ post.default_variation_id }}                            Default varyasyon ID                  55
 *   {{ post.default_attributes }}                              Default attribute'lar                 {pa_color:'kirmizi', pa_size:'m'}
 *   {{ post.variation_url }}                                   SEO-friendly varyasyon URL            '/urun/elbise/color-kirmizi/'
 *   {{ post.variations_loop }}                                 Attribute loop data                   [{id:1, name:'Renk', slug:'pa_color', terms:[...]}, ...]
 *
 *   -- CART --
 *   {{ post.add_to_cart_url }}                                 Sepete ekle URL                       '/?add-to-cart=123'
 *   {{ post.add_to_cart_attrs }}                               Sepete ekle data attrs (HTML)         'data-product_id="123" data-quantity="1" ...'
 *   {{ post.add_to_cart_text }}                                Sepete ekle buton metni               'Sepete Ekle'
 *
 *   -- BADGES --
 *   {{ post.badges(200, 'discount,stock,shipping') }}          Urun badge'leri (HTML)                '<div class="product-badges">...</div>'
 *   {{ post.free_shipping_amount }}                            Ucretsiz kargo min tutar              200.0
 *   {{ post.has_free_shipping }}                               Ucretsiz kargoya uygun mu             true
 *
 *   -- RELATED / UPSELL / CROSS-SELL --
 *   {{ post.related_ids(4) }}                                  Iliskili urun ID'leri                 [45, 67, 89, 12]
 *   {{ post.upsell_ids }}                                      Upsell urun ID'leri                   [33, 44]
 *   {{ post.cross_sell_ids }}                                  Cross-sell urun ID'leri               [55, 66]
 *
 *   -- GROUPED --
 *   {{ post.is_in_grouped }}                                   Grouped urun parcasi mi               [101, 102]  (parent grouped ID'leri)
 *   {{ post.grouped_children }}                                Grouped child ID'leri                 [10, 11, 12]
 *
 *   -- REVIEWS / RATING --
 *   {{ post.average_rating }}                                  Ortalama puan                         4.5
 *   {{ post.review_count }}                                    Yorum sayisi                          28
 *   {{ post.rating_counts }}                                   Puan dagilimi                         {5:15, 4:8, 3:3, 2:1, 1:1}
 *
 *   -- SCHEMA.ORG --
 *   {{ post.schema }}                                          Schema.org Product data               {type:'Product', name:'...', offers:{...}, ...}
 *
 *   -- ORDER / PAYMENT --
 *   {{ post.order_ids }}                                       Siparis ID'leri                       [1001, 1002, 1003]
 *   {{ post.payments }}                                        Odeme bilgileri                       [{title:'Payment', id:1001, status:'completed', ...}]
 *   {{ post.payment_is_complete }}                             Odeme tamamlandi mi                   true
 *   {{ post.deposit_payment_is_complete }}                     Deposit odemesi tamamlandi mi         false
 *
 *   -- STATIC --
 *   ThemeProduct::best_sellers(10)                             En cok satanlar                       [{id:5, count:120}, {id:8, count:95}, ...]
 *   ThemeProduct::available_categories()                       Mevcut kategoriler                    [Timber\Term, Timber\Term, ...]
 *
 * Examples:
 *   {# Indirim badge #}
 *   {% if post.is_on_sale %}
 *       <span class="badge">-{{ post.sale_percentage }}%</span>
 *   {% endif %}
 *   <div class="price">{{ post.price_html }}</div>
 *
 *   {# Gallery slider #}
 *   {% for url in post.gallery_urls('large') %}
 *       <img src="{{ url }}" alt="{{ post.title }}">
 *   {% endfor %}
 */

class WooProduct extends Post {

    // Core WC product metodları burada.
    // ThemeProduct bu class'ı extend ederek miras alır.
    // Proje özel eklemeler ThemeProduct'a yapılır.

    protected $product = null;

    // ── CORE ─────────────────────────────────────────────

    public function product() {
        if (!$this->product) {
            $this->product = wc_get_product($this->ID);
        }
        return $this->product;
    }

    public function get_title() {
        if (function_exists('qtranxf_use')) {
            return html_entity_decode(qtranxf_use(Data::get("language"), parent::title(), false, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return html_entity_decode(parent::title(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function setup($loop_index = 0) {
        global $wp_query;
        $wp_query->in_the_loop = true;
        $wp_query->setup_postdata($this->ID);
        return $this;
    }

    public function teardown() {
        global $wp_query;
        $wp_query->in_the_loop = false;
        return $this;
    }

    // ── PRICING ──────────────────────────────────────────

    public function price_html() {
        $p = $this->product();
        if (!$p) return '';
        // variation için: direkt bu variation'ın fiyatını göster
        if ($p->is_type('variation')) {
            $regular = (float) $p->get_regular_price();
            if ($regular > 0) {
                return $p->get_price_html();
            }
            // Variation'da fiyat yoksa parent'ın min fiyatını göster
            $parent = wc_get_product($p->get_parent_id());
            if (!$parent) return '';
            $min_price = (float) $parent->get_variation_price('min');
            $max_price = (float) $parent->get_variation_price('max');
            if ($min_price <= 0) return $parent->get_price_html();
            if ($min_price === $max_price) return wc_price($min_price);
            // Fiyat aralığı varsa min'i göster (bu variation için en yakın değer)
            return wc_price($min_price);
        }
        return $p->get_price_html();
    }

    public function price() {
        $p = $this->product();
        return $p ? (float) $p->get_price() : 0;
    }

    public function regular_price() {
        $p = $this->product();
        return $p ? (float) $p->get_regular_price() : 0;
    }

    public function sale_price() {
        $p = $this->product();
        if (!$p) return 0;
        $sale = $p->get_sale_price();
        return $sale !== '' ? (float) $sale : 0;
    }

    public function sale_percentage() {
        $p = $this->product();
        if (!$p || !$p->is_on_sale()) return 0;

        if ($p->is_type('variable')) {
            $percentages = [];
            foreach ($p->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child && $child->is_on_sale()) {
                    $regular = (float) $child->get_regular_price();
                    $sale = (float) $child->get_sale_price();
                    if ($regular > 0) {
                        $percentages[] = round((($regular - $sale) / $regular) * 100);
                    }
                }
            }
            return $percentages ? max($percentages) : 0;
        }

        $regular = (float) $p->get_regular_price();
        $sale = (float) $p->get_sale_price();
        return ($regular > 0 && $sale > 0) ? round((($regular - $sale) / $regular) * 100) : 0;
    }

    public function is_on_sale() {
        $p = $this->product();
        return $p ? $p->is_on_sale() : false;
    }

    public function formatted_price() {
        return wc_price($this->price());
    }

    // ── STOCK ────────────────────────────────────────────

    public function is_in_stock() {
        $p = $this->product();
        if (!$p) return false;
        // variation için: manage_stock kapalıysa parent'ın stock_status'ına bak
        if ($p->is_type('variation') && !$p->managing_stock()) {
            $parent = wc_get_product($p->get_parent_id());
            return $parent ? $parent->is_in_stock() : $p->is_in_stock();
        }
        return $p->is_in_stock();
    }

    public function stock_quantity() {
        $p = $this->product();
        return $p ? $p->get_stock_quantity() : null;
    }

    public function stock_status_label() {
        $p = $this->product();
        if (!$p) return '';
        $map = [
            'instock'     => __('Stokta', 'woocommerce'),
            'outofstock'  => __('Tükendi', 'woocommerce'),
            'onbackorder' => __('Sipariş Üzerine', 'woocommerce'),
        ];
        return $map[$p->get_stock_status()] ?? $p->get_stock_status();
    }

    public function is_low_stock() {
        $p = $this->product();
        if (!$p) return false;
        $qty = $p->get_stock_quantity();
        if ($qty === null) return false;
        return $qty > 0 && $qty <= wc_get_low_stock_amount($p);
    }

    public function backorders_allowed() {
        $p = $this->product();
        return $p ? $p->backorders_allowed() : false;
    }

    // ── PRODUCT META ─────────────────────────────────────

    public function sku() {
        $p = $this->product();
        return $p ? $p->get_sku() : '';
    }

    public function weight() {
        $p = $this->product();
        if (!$p || !$p->has_weight()) return '';
        return $p->get_weight() . ' ' . get_option('woocommerce_weight_unit');
    }

    public function dimensions() {
        $p = $this->product();
        if (!$p || !$p->has_dimensions()) return '';
        return wc_format_dimensions($p->get_dimensions(false));
    }

    public function product_type() {
        $p = $this->product();
        return $p ? $p->get_type() : '';
    }

    public function get_product_type() {
        return $this->product_type();
    }

    public function is_featured() {
        $p = $this->product();
        return $p ? $p->is_featured() : false;
    }

    public function is_purchasable() {
        $p = $this->product();
        return $p ? $p->is_purchasable() : false;
    }

    public function is_virtual() {
        $p = $this->product();
        return $p ? $p->is_virtual() : false;
    }

    public function is_downloadable() {
        $p = $this->product();
        return $p ? $p->is_downloadable() : false;
    }

    public function short_description() {
        $p = $this->product();
        return $p ? $p->get_short_description() : '';
    }

    // ── IMAGES & GALLERY ─────────────────────────────────

    public function gallery_ids() {
        $p = $this->product();
        return $p ? $p->get_gallery_image_ids() : [];
    }

    public function gallery_urls($size = 'full') {
        $urls = [];
        foreach ($this->gallery_ids() as $id) {
            $url = wp_get_attachment_image_url($id, $size);
            if ($url) $urls[] = $url;
        }
        return $urls;
    }

    public function cover_url($size = 'full') {
        $p = $this->product();
        if (!$p) return '';
        $image_id = $p->get_image_id();
        // variation'ın kendi resmi yoksa parent'ın resmini kullan
        if (!$image_id && $p->is_type('variation')) {
            $parent = wc_get_product($p->get_parent_id());
            if ($parent) $image_id = $parent->get_image_id();
        }
        return $image_id ? wp_get_attachment_image_url($image_id, $size) : '';
    }

    /**
     * product_variation için ana resim + ek görseller (WooCommerce Ek Varyasyon Görselleri plugin).
     * Twig: post.variation_all_images('woocommerce_thumbnail')
     */
    public function variation_all_images(string $size = 'woocommerce_thumbnail'): array {
        $p = $this->product();
        if (!$p || !$p->is_type('variation')) return [];

        $images   = [];
        $seen     = [];

        // 1. Variation'ın ana resmi
        $image_id = $p->get_image_id();
        if ($image_id) {
            $url = wp_get_attachment_image_url($image_id, $size);
            if ($url) { $images[] = $url; $seen[$image_id] = true; }
        }

        // 2. Ek görseller (_wc_additional_variation_images plugin)
        $additional = get_post_meta($this->ID, '_wc_additional_variation_images', true);
        if ($additional) {
            foreach (array_filter(explode(',', $additional)) as $add_id) {
                $add_id = (int) trim($add_id);
                if ($add_id && !isset($seen[$add_id])) {
                    $url = wp_get_attachment_image_url($add_id, $size);
                    if ($url) { $images[] = $url; $seen[$add_id] = true; }
                }
            }
        }

        // 3. Hiç görsel yoksa parent'ın ana resmini kullan
        if (empty($images)) {
            $parent = wc_get_product($p->get_parent_id());
            if ($parent) {
                $pid = $parent->get_image_id();
                if ($pid) {
                    $url = wp_get_attachment_image_url($pid, $size);
                    if ($url) $images[] = $url;
                }
            }
        }

        return $images;
    }

    public function all_image_urls($size = 'full') {
        $images = [];
        $cover = $this->cover_url($size);
        if ($cover) $images[] = $cover;
        return array_merge($images, $this->gallery_urls($size));
    }

    // ── TAXONOMY ─────────────────────────────────────────

    public function categories() {
        $p = $this->product();
        if (!$p) return [];
        $ids = $p->get_category_ids();
        if (!$ids) return [];
        return array_map(function ($id) { return Timber::get_term($id); }, $ids);
    }

    public function primary_category() {
        // product_variation için parent product'ın kategorisini döndür
        if ($this->post_type === 'product_variation') {
            $p = $this->product();
            if ($p && $p->is_type('variation')) {
                $parent_id = $p->get_parent_id();
                if ($parent_id) {
                    $terms = get_the_terms($parent_id, 'product_cat');
                    if ($terms && !is_wp_error($terms)) {
                        // Yoast primary category varsa onu kullan
                        $primary_id = get_post_meta($parent_id, '_yoast_wpseo_primary_product_cat', true);
                        if ($primary_id) {
                            foreach ($terms as $term) {
                                if ((int) $term->term_id === (int) $primary_id) {
                                    return \Timber\Timber::get_term($term->term_id);
                                }
                            }
                        }
                        return \Timber\Timber::get_term(reset($terms)->term_id);
                    }
                }
            }
            return false;
        }
        $cats = $this->categories();
        return $cats ? reset($cats) : false;
    }

    public function category() {
        return $this->primary_category();
    }

    public function tags() {
        $p = $this->product();
        if (!$p) return [];
        $ids = $p->get_tag_ids();
        if (!$ids) return [];
        return array_map(function ($id) { return Timber::get_term($id); }, $ids);
    }

    // ── ATTRIBUTES ───────────────────────────────────────

    public function get_product_attribute($slug, $convert_terms = true) {
        $product = $this->product();
        if (!$product) return false;
        $attributes = $product->get_attributes();
        if (!$attributes) return false;

        $attribute = $attributes["pa_{$slug}"] ?? false;
        if (!$attribute) return false;

        if ($attribute->is_taxonomy()) {
            $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
            if ($convert_terms) {
                $terms = array_map(function ($term) { return Timber::get_term($term); }, $terms);
            }
            return $terms;
        }
        return $attribute->get_options();
    }

    public function all_attributes() {
        $product = $this->product();
        if (!$product) return [];
        $result = [];
        $attributes = $product->get_attributes();
        if (!$attributes) return [];

        foreach ($attributes as $key => $attribute) {
            $slug = str_replace('pa_', '', $key);
            if ($attribute->is_taxonomy()) {
                // Attribute type'ı al (color, button, image, radio, select)
                $attr_type = 'select';
                $taxonomy_obj = wc_get_attribute(wc_attribute_taxonomy_id_by_name($attribute->get_name()));
                if ($taxonomy_obj) {
                    $attr_type = $taxonomy_obj->type ?? 'select';
                }

                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                $result[$slug] = [
                    'label'     => wc_attribute_label($attribute->get_name()),
                    'type'      => $attr_type, // 'color', 'button', 'image', 'radio', 'select'
                    'terms'     => array_map(function ($term) use ($attr_type) {
                        $color = '';
                        if ($attr_type === 'color') {
                            // Variation Swatches plugin meta key
                            $color = get_term_meta($term->term_id, 'product_attribute_color', true);
                            // Fallback: eski 'color' meta key
                            if (!$color) {
                                $color = get_term_meta($term->term_id, 'color', true);
                            }
                        }
                        $image = '';
                        if ($attr_type === 'image') {
                            $image_id = get_term_meta($term->term_id, 'product_attribute_image', true);
                            if ($image_id) {
                                $image = wp_get_attachment_image_url($image_id, 'thumbnail');
                            }
                        }
                        return [
                            'id'    => $term->term_id,
                            'name'  => $term->name,
                            'slug'  => $term->slug,
                            'color' => $color,
                            'image' => $image,
                        ];
                    }, $terms),
                    'visible'   => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                ];
            } else {
                $result[$slug] = [
                    'label'     => $attribute->get_name(),
                    'type'      => 'select',
                    'terms'     => $attribute->get_options(),
                    'visible'   => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                ];
            }
        }
        return $result;
    }

    // ── VARIATIONS ───────────────────────────────────────

    public function is_variable() {
        return $this->product_type() === 'variable';
    }

    public function variations() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_available_variations();
    }

    public function variation_attributes() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_variation_attributes();
    }

    public function default_variation_id() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return 0;
        $attributes = $p->get_default_attributes();
        if (!$attributes) return 0;
        $lookup = [];
        foreach ($attributes as $key => $value) {
            $lookup[strpos($key, 'attribute_') === 0 ? $key : "attribute_{$key}"] = $value;
        }
        if (class_exists('WC_Data_Store')) {
            return WC_Data_Store::load('product')->find_matching_product_variation($p, $lookup);
        }
        return $p->get_matching_variation($lookup);
    }

    public function default_attributes() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_default_attributes();
    }

    public function variation_url() {
        $p = $this->product();
        // product_variation için parent URL + attribute query string
        if ($p && $p->is_type('variation')) {
            $url = $p->get_permalink();
            return function_exists('variation_url_rewrite') ? variation_url_rewrite($url) : $url;
        }
        return function_exists('variation_url_rewrite') ? variation_url_rewrite($this->link) : $this->link;
    }

    public function get_variation_url() {
        return $this->variation_url();
    }

    /**
     * product_variation için seçili attribute değerlerini döndürür.
     * Twig: post.variation_selected_attrs
     * Döner: [['label'=>'Renk','slug'=>'color','name'=>'Kırmızı','type'=>'color','color'=>'#f00'], ...]
     */
    public function variation_selected_attrs(): array {
        $p = $this->product();
        if (!$p || !$p->is_type('variation')) return [];

        $result = [];
        foreach ($p->get_variation_attributes() as $key => $value) {
            if (empty($value)) continue;
            $slug     = str_replace('attribute_pa_', '', $key);
            $taxonomy = 'pa_' . $slug;
            $term     = get_term_by('slug', $value, $taxonomy);
            $tax_id   = wc_attribute_taxonomy_id_by_name($taxonomy);
            $tax_obj  = $tax_id ? wc_get_attribute($tax_id) : null;
            $type     = $tax_obj ? ($tax_obj->type ?? 'select') : 'select';

            $result[] = [
                'label' => wc_attribute_label($taxonomy),
                'slug'  => $slug,
                'value' => $value,
                'name'  => $term ? $term->name : $value,
                'type'  => $type,
                'color' => $term ? (string) get_term_meta($term->term_id, 'product_attribute_color', true) : '',
                'image' => $term ? (string) get_term_meta($term->term_id, 'product_attribute_image', true) : '',
            ];
        }
        return $result;
    }

    /**
     * product_variation için kardeş variation sayısı (kendisi hariç).
     * Twig: post.variation_siblings_count
     * Döner: int (örn. 4 → "+4 renk")
     */
    public function variation_siblings_count(): int {
        $p = $this->product();
        if (!$p || !$p->is_type('variation')) return 0;
        $parent = wc_get_product($p->get_parent_id());
        if (!$parent) return 0;
        $children = $parent->get_children();
        return max(0, count($children) - 1);
    }

    /**
     * product_variation için parent product URL'i.
     * Twig: post.parent_url
     */
    public function parent_url(): string {
        $p = $this->product();
        if (!$p || !$p->is_type('variation')) return $this->link;
        return (string) get_permalink($p->get_parent_id());
    }

    // ── CART ─────────────────────────────────────────────

    public function add_to_cart_url() {
        $p = $this->product();
        return $p ? $p->add_to_cart_url() : '';
    }

    public function add_to_cart_attrs() {
        return function_exists('get_add_to_cart_attrs') ? get_add_to_cart_attrs($this->ID) : '';
    }

    public function add_to_cart_text() {
        $p = $this->product();
        return $p ? $p->add_to_cart_text() : '';
    }

    // ── BADGES ───────────────────────────────────────────

    public function badges($free_shipping_min = 0, $types = 'discount,stock,shipping') {
        $p = $this->product();
        if (!$p) return '';
        return function_exists('woo_product_badges') ? woo_product_badges($p, $free_shipping_min, $types) : '';
    }

    // ── RELATED / UPSELL / CROSS-SELL ────────────────────

    public function related_ids($limit = 4) {
        return wc_get_related_products($this->ID, $limit);
    }

    public function upsell_ids() {
        $p = $this->product();
        return $p ? $p->get_upsell_ids() : [];
    }

    public function cross_sell_ids() {
        $p = $this->product();
        return $p ? $p->get_cross_sell_ids() : [];
    }

    // ── GROUPED ──────────────────────────────────────────

    public function is_in_grouped() {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' AND p.post_status = 'publish'
            AND pm.meta_key = '_children' AND pm.meta_value LIKE %s
        ", '%"' . (int) $this->ID . '"%'));
    }

    public function grouped_children() {
        $p = $this->product();
        if (!$p || !$p->is_type('grouped')) return [];
        return $p->get_children();
    }

    // ── REVIEWS / RATING ─────────────────────────────────

    public function average_rating() {
        $p = $this->product();
        return $p ? (float) $p->get_average_rating() : 0;
    }

    public function review_count() {
        $p = $this->product();
        return $p ? (int) $p->get_review_count() : 0;
    }

    public function rating_counts() {
        $p = $this->product();
        return $p ? $p->get_rating_counts() : [];
    }

    // ── SCHEMA.ORG ───────────────────────────────────────

    public function schema() {
        $p = $this->product();
        if (!$p) return [];

        $schema = [
            '@type'       => 'Product',
            'name'        => $this->get_title(),
            'description' => wp_strip_all_tags($p->get_short_description() ?: $p->get_description()),
            'sku'         => $p->get_sku(),
            'image'       => $this->cover_url('large'),
            'url'         => $this->link,
        ];

        $brands = $this->terms('wpc-brand');
        if (!$brands) $brands = $this->terms('product_brand');
        if ($brands) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brands[0]->name];
        }

        $schema['offers'] = [
            '@type'         => 'Offer',
            'url'           => $this->link,
            'priceCurrency' => get_woocommerce_currency(),
            'price'         => $p->get_price(),
            'availability'  => $p->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        if ($p->get_review_count() > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $p->get_average_rating(),
                'reviewCount' => $p->get_review_count(),
            ];
        }
        return $schema;
    }

    // ── VARIATION IMAGES ─────────────────────────────────

    /**
     * Varyasyon gorselleri (attribute + value bazli).
     * Ornek: post.variation_images('color', 'kirmizi', 'large')
     * @param string $attr Attribute slug (color, size vs.)
     * @param string $attr_value Attribute value slug (kirmizi, mavi vs.)
     * @param string $size WP image size
     * @return array URL listesi
     */
    public function variation_images($attr, $attr_value = '', $size = 'full') {
        if (function_exists('woo_get_product_variation_thumbnails')) {
            return woo_get_product_variation_thumbnails($this->ID, $attr, $attr_value, $size);
        }
        return [];
    }

    /**
     * Her varyasyon degeri icin tek thumbnail (renk secici icin).
     * Ornek: post.variation_thumbnails('color', 'thumbnail')
     * Doner: [['slug' => 'kirmizi', 'image' => 'url'], ...]
     * @param string $attr Attribute slug
     * @param string $size WP image size
     */
    public function variation_thumbnails($attr, $size = 'thumbnail') {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];

        $seen = [];
        $result = [];
        $variations = $p->get_available_variations();

        foreach ($variations as $variation) {
            $key = "attribute_pa_{$attr}";
            if (!isset($variation['attributes'][$key])) continue;
            $value = $variation['attributes'][$key];
            if (isset($seen[$value])) continue;
            $seen[$value] = true;

            $image_url = '';
            if (!empty($variation['image_id'])) {
                $image_url = wp_get_attachment_image_url($variation['image_id'], $size);
            } elseif (!empty($variation['image'][$size . '_src'])) {
                $image_url = $variation['image'][$size . '_src'];
            }

            $term = get_term_by('slug', $value, "pa_{$attr}");
            $result[] = [
                'slug'  => $value,
                'name'  => $term ? $term->name : $value,
                'color' => $term ? get_term_meta($term->term_id, 'color', true) : '',
                'image' => $image_url,
            ];
        }
        return $result;
    }

    /**
     * Tüm varyasyonların unique ana görsellerini döndürür (hover zone için).
     * Her varyasyondan sadece 1 görsel — ana görsel veya ilk ek görsel.
     * Ornek: post.default_variation_images('woocommerce_thumbnail')
     */
    public function default_variation_images($size = 'full') {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return $this->all_image_urls($size);

        $images     = [];
        $seen       = [];
        $variations = $p->get_available_variations();

        foreach ($variations as $variation) {
            $var_id   = $variation['variation_id'];
            $image_id = $variation['image_id'] ?? 0;

            // Varyasyonun ana görseli varsa onu al
            if ($image_id && !isset($seen[$image_id])) {
                $url = wp_get_attachment_image_url($image_id, $size);
                if ($url) {
                    $images[]        = $url;
                    $seen[$image_id] = true;
                    continue; // Bu varyasyon için bitti, sonrakine geç
                }
            }

            // Ana görsel yoksa ek görsellerden ilkini al
            $additional = get_post_meta($var_id, '_wc_additional_variation_images', true);
            if ($additional) {
                $add_ids = array_filter(explode(',', $additional));
                foreach ($add_ids as $add_id) {
                    $add_id = (int) trim($add_id);
                    if ($add_id && !isset($seen[$add_id])) {
                        $url = wp_get_attachment_image_url($add_id, $size);
                        if ($url) {
                            $images[]      = $url;
                            $seen[$add_id] = true;
                            break; // Sadece ilkini al
                        }
                    }
                }
            }
        }

        // Hiç görsel yoksa ana ürün görsellerine fallback
        return $images ?: $this->all_image_urls($size);
    }

    // ── FREE SHIPPING ────────────────────────────────────

    /**
     * Ucretsiz kargo minimum tutari.
     */
    public function free_shipping_amount($multiply_by = 1) {
        return function_exists('get_free_shipping_amount') ? get_free_shipping_amount($multiply_by) : 0;
    }

    /**
     * Ucretsiz kargoya uygun mu?
     */
    public function has_free_shipping() {
        $min = $this->free_shipping_amount();
        return $min > 0 && $this->price() >= $min;
    }

    // ── PRICE HELPERS ────────────────────────────────────

    /**
     * Variable urun fiyat araligi HTML.
     */
    public function variable_price_html() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return $this->price_html();
        return function_exists('variable_product_price') ? variable_product_price($p) : $p->get_price_html();
    }

    /**
     * Fiyat + para birimi formatli.
     * Ornek: "149,90 ₺"
     */
    public function price_with_currency($price = null) {
        if ($price === null) $price = $this->price();
        return function_exists('woo_get_currency_with_price') ? woo_get_currency_with_price($price) : wc_price($price);
    }

    /**
     * Para birimi sembolu.
     */
    public function currency_symbol() {
        return get_woocommerce_currency_symbol();
    }

    // ── PRODUCT COVER (functions.php bridge) ─────────────

    /**
     * Urun kapak gorselleri (simple, variable, grouped destekli).
     * functions.php'deki get_product_cover() fonksiyonunu kullanir.
     * @param bool $multiple Coklu gorsel dondur
     */
    public function product_cover($multiple = false) {
        return function_exists('get_product_cover') ? get_product_cover($this->product(), $multiple) : ($multiple ? [] : '');
    }

    // ── GALLERY (functions.php bridge) ───────────────────

    /**
     * WooCommerce galeri URL'leri (functions.php bridge).
     */
    public function woo_gallery() {
        return function_exists('woo_get_product_gallery') ? woo_get_product_gallery($this->ID) : [];
    }

    // ── VARIATION LOOP DATA ──────────────────────────────

    /**
     * Urun attribute'larini loop icin hazir data olarak doner.
     * functions.php'deki woo_get_product_variations_loop() bridge.
     */
    public function variations_loop() {
        $p = $this->product();
        if (!$p) return [];
        return function_exists('woo_get_product_variations_loop') ? woo_get_product_variations_loop($p) : [];
    }

    /**
     * Unique varyasyon degerleri.
     */
    public function variations_unique($arr = []) {
        return function_exists('woo_get_product_variations_unique') ? woo_get_product_variations_unique($arr) : [];
    }

    // ── LOW STOCK ────────────────────────────────────────

    /**
     * Low stock threshold degeri.
     */
    public function low_stock_amount() {
        $p = $this->product();
        if (!$p) return 0;
        return function_exists('woo_get_product_low_stock_amount') ? woo_get_product_low_stock_amount($p) : 0;
    }

    // ── URL HELPERS ──────────────────────────────────────

    /**
     * Attribute bazli URL parse (SEO-friendly variation URL).
     */
    public function url_pa_parse($variation = '') {
        $p = $this->product();
        if (!$p) return [];
        return function_exists('woo_url_pa_parse') ? woo_url_pa_parse($p, $variation) : [];
    }

    /**
     * Swatch linki için her attribute term slug'ını variation permalink'ine map'le.
     *
     * XT WooCommerce Variations as Singles aktifse get_permalink(variation_id)
     * doğru SEO URL'yi döndürür. Plugin yoksa variation_url_rewrite fallback.
     *
     * Twig kullanımı:
     *   {% set _var_urls = post.variation_urls_by_attr(_swatch_slug) %}
     *   {# _var_urls = {'kirmizi': 'https://site.com/urun/kirmizi/', ...} #}
     *   {% set _url = _var_urls[_term.slug] ?? post.link %}
     *
     * @param string $attr_slug  Attribute slug (örn: 'color', 'shade')
     * @return array<string, string>  [term_slug => permalink]
     */
    public function variation_urls_by_attr( string $attr_slug ): array {
        $p = $this->product();
        if ( ! $p || ! $p->is_type( 'variable' ) ) return [];

        $map        = [];
        $attr_key   = 'attribute_pa_' . $attr_slug;
        $variations = $p->get_available_variations();

        foreach ( $variations as $variation ) {
            if ( ! isset( $variation['attributes'][ $attr_key ] ) ) continue;
            $term_slug    = $variation['attributes'][ $attr_key ];
            $variation_id = $variation['variation_id'] ?? 0;

            if ( ! $term_slug || ! $variation_id || isset( $map[ $term_slug ] ) ) continue;

            // XT Variations as Singles aktifse variation'ın kendi permalink'i doğrudur
            $url = get_permalink( $variation_id );

            // Fallback: permalink ana ürünle aynıysa SEO-friendly URL elle oluştur
            $parent_url = trailingslashit( get_permalink( $p->get_id() ) );
            if ( ! $url || trailingslashit( $url ) === $parent_url ) {
                // variation-url.php formatı: {parent}/{attr}-{value}/
                $url = $parent_url . $attr_slug . '-' . $term_slug . '/';
            }

            $map[ $term_slug ] = $url;
        }

        return $map;
    }

    // ── ROLE BASED PRICING ───────────────────────────────

    /**
     * Kullaniciya ozel fiyat (role based pricing).
     */
    public function role_based_price($user = null) {
        if (!$user) $user = wp_get_current_user();
        return function_exists('role_based_price') ? role_based_price($user, $this) : [];
    }

    // ── ORDER / PAYMENT HELPERS ──────────────────────────

    /**
     * Bu urune ait siparis ID'leri.
     */
    public function order_ids() {
        return function_exists('get_orders_ids_by_product_id') ? get_orders_ids_by_product_id($this->ID) : [];
    }

    /**
     * Bu urunun odeme bilgileri.
     */
    public function payments() {
        return function_exists('get_product_payments') ? get_product_payments($this->ID) : [];
    }

    /**
     * Odeme tamamlandi mi (deposit dahil)?
     */
    public function payment_is_complete($forced = false) {
        return function_exists('product_payment_is_complete') ? product_payment_is_complete($this->ID, $forced) : false;
    }

    /**
     * Deposit odemesi tamamlandi mi?
     */
    public function deposit_payment_is_complete() {
        return function_exists('product_deposit_payment_is_complete') ? product_deposit_payment_is_complete($this->ID) : false;
    }

    // ── AVAILABLE CATEGORIES ─────────────────────────────

    /**
     * Mevcut urun kategorileri (shop/archive icin).
     */
    public static function available_categories() {
        return function_exists('woo_get_available_categories') ? woo_get_available_categories() : [];
    }

    // ── CUSTOM FIELDS (WooCommerce Custom Product Fields) ───────────────────────────

    /**
     * Get WooCommerce custom field value
     * 
     * @version 1.0.0
     * @since 2026-04-23
     * 
     * CHANGELOG:
     * 1.0.0 - 2026-04-23
     *   - Initial release
     *   - Added woo_meta() method to ThemeProduct class
     *   - Support for all custom field types (text, textarea, number, email, url, tel, password, checkbox, select, date, color)
     *   - Default value support
     * 
     * HOW TO USE:
     * This method retrieves custom field values that were added via WooCommerce → Settings → Products → Custom Fields.
     * Use it in Twig templates to display custom product information like warranty periods, badges, special notes, etc.
     * The method accepts a field ID (defined in admin) and an optional default value.
     * 
     * @param string $field_id Field ID (defined in WooCommerce custom fields settings)
     * @param mixed $default Default value if field is empty or doesn't exist
     * @return mixed Field value (string, number, boolean depending on field type)
     * 
     * @example Basic usage - Get warranty period:
     * {{ post.woo_meta('warranty_period') }}
     * 
     * @example With default value:
     * {{ post.woo_meta('badge_text', 'Yeni Ürün') }}
     * 
     * @example Conditional display:
     * {% if post.woo_meta('special_note') %}
     *     <div class="alert">{{ post.woo_meta('special_note') }}</div>
     * {% endif %}
     * 
     * @example Loop through products in archive:
     * {% for product in products %}
     *     <h3>{{ product.title }}</h3>
     *     {% if product.woo_meta('badge') %}
     *         <span class="badge">{{ product.woo_meta('badge') }}</span>
     *     {% endif %}
     *     <p>Garanti: {{ product.woo_meta('warranty', 'Yok') }}</p>
     * {% endfor %}
     * 
     * @example Color field usage:
     * <div style="background: {{ post.woo_meta('product_color') }}">
     *     Renk: {{ post.woo_meta('product_color') }}
     * </div>
     * 
     * @example Checkbox field:
     * {% if post.woo_meta('is_featured') %}
     *     <span class="featured-badge">Öne Çıkan</span>
     * {% endif %}
     * 
     * @example Number field (stock alert threshold):
     * {% if post.stock_quantity < post.woo_meta('low_stock_threshold', 5) %}
     *     <span class="low-stock">Stok Azalıyor!</span>
     * {% endif %}
     */
    public function woo_meta($field_id, $default = '') {
        return function_exists('wc_get_custom_field') 
            ? wc_get_custom_field($this->ID, $field_id, $default) 
            : $default;
    }

    /**
     * Get all WooCommerce custom fields
     * 
     * @version 1.0.0
     * @since 2026-04-23
     * 
     * CHANGELOG:
     * 1.0.0 - 2026-04-23
     *   - Initial release
     *   - Returns all custom fields with labels, values, types, and field configs
     * 
     * HOW TO USE:
     * This method retrieves ALL custom fields for a product at once.
     * Returns an associative array where keys are field IDs and values contain label, value, type, and field config.
     * Useful for displaying all custom information in a structured way.
     * 
     * @return array Array of field_id => ['label' => string, 'value' => mixed, 'type' => string, 'field' => array]
     * 
     * @example Display all custom fields:
     * {% set fields = post.woo_meta_all() %}
     * {% if fields %}
     *     <div class="custom-fields">
     *         <h4>Ek Bilgiler</h4>
     *         {% for field_id, data in fields %}
     *             <div class="field">
     *                 <strong>{{ data.label }}:</strong> {{ data.value }}
     *             </div>
     *         {% endfor %}
     *     </div>
     * {% endif %}
     * 
     * @example Styled table display:
     * {% set fields = post.woo_meta_all() %}
     * {% if fields %}
     *     <table class="product-specs">
     *         {% for field_id, data in fields %}
     *             <tr>
     *                 <th>{{ data.label }}</th>
     *                 <td>{{ data.value }}</td>
     *             </tr>
     *         {% endfor %}
     *     </table>
     * {% endif %}
     * 
     * @example Filter by field type:
     * {% set fields = post.woo_meta_all() %}
     * {% for field_id, data in fields %}
     *     {% if data.type == 'url' %}
     *         <a href="{{ data.value }}" target="_blank">{{ data.label }}</a>
     *     {% endif %}
     * {% endfor %}
     * 
     * @example Count custom fields:
     * {% set field_count = post.woo_meta_all()|length %}
     * <p>Bu üründe {{ field_count }} ek bilgi var</p>
     * 
     * @example Check if any custom fields exist:
     * {% if post.woo_meta_all() %}
     *     <button class="show-more-info">Daha Fazla Bilgi</button>
     * {% endif %}
     */
    public function woo_meta_all() {
        return function_exists('wc_get_all_custom_fields') 
            ? wc_get_all_custom_fields($this->ID) 
            : [];
    }

    /**
     * Get formatted WooCommerce custom field (for URL, email, color, etc.)
     * 
     * @version 1.0.0
     * @since 2026-04-23
     * 
     * CHANGELOG:
     * 1.0.0 - 2026-04-23
     *   - Initial release
     *   - Auto-formats URL fields as clickable links
     *   - Auto-formats email fields as mailto links
     *   - Auto-formats tel fields as tel links
     *   - Auto-formats color fields with color preview box
     *   - Auto-formats textarea with line breaks
     *   - Auto-formats select fields with option labels
     * 
     * HOW TO USE:
     * This method returns HTML-formatted field values based on field type.
     * Use it when you want automatic formatting for special field types like URLs, emails, colors.
     * IMPORTANT: Use |raw filter in Twig to render HTML properly.
     * 
     * @param string $field_id Field ID
     * @return string Formatted HTML string
     * 
     * @example URL field - Auto-creates clickable link:
     * {{ post.woo_meta_formatted('website')|raw }}
     * {# Output: <a href="https://example.com" target="_blank" rel="noopener">https://example.com</a> #}
     * 
     * @example Email field - Auto-creates mailto link:
     * {{ post.woo_meta_formatted('support_email')|raw }}
     * {# Output: <a href="mailto:support@example.com">support@example.com</a> #}
     * 
     * @example Color field - Shows color preview box:
     * {{ post.woo_meta_formatted('product_color')|raw }}
     * {# Output: <span class="color-preview" style="..."></span>#dd9933 #}
     * 
     * @example Phone field - Auto-creates tel link:
     * {{ post.woo_meta_formatted('contact_phone')|raw }}
     * {# Output: <a href="tel:+905551234567">+90 555 123 45 67</a> #}
     * 
     * @example Textarea field - Preserves line breaks:
     * {{ post.woo_meta_formatted('long_description')|raw }}
     * {# Output: Line 1<br>Line 2<br>Line 3 #}
     * 
     * @example Multiple formatted fields:
     * <div class="contact-info">
     *     <p>Web: {{ post.woo_meta_formatted('website')|raw }}</p>
     *     <p>Email: {{ post.woo_meta_formatted('email')|raw }}</p>
     *     <p>Tel: {{ post.woo_meta_formatted('phone')|raw }}</p>
     * </div>
     * 
     * @example Conditional formatted display:
     * {% if post.woo_meta('website') %}
     *     <div class="website">
     *         {{ post.woo_meta_formatted('website')|raw }}
     *     </div>
     * {% endif %}
     */
    public function woo_meta_formatted($field_id) {
        return function_exists('wc_get_custom_field_formatted') 
            ? wc_get_custom_field_formatted($this->ID, $field_id) 
            : '';
    }

    /**
     * Check if has WooCommerce custom field
     * 
     * @version 1.0.0
     * @since 2026-04-23
     * 
     * CHANGELOG:
     * 1.0.0 - 2026-04-23
     *   - Initial release
     *   - Checks if custom field exists and has a non-empty value
     * 
     * HOW TO USE:
     * This method checks if a custom field exists and has a value.
     * Use it in conditional statements to avoid displaying empty fields.
     * Returns true if field has a value, false if empty or doesn't exist.
     * 
     * @param string $field_id Field ID
     * @return bool True if field exists and has value, false otherwise
     * 
     * @example Basic conditional:
     * {% if post.has_woo_meta('special_note') %}
     *     <div class="alert">{{ post.woo_meta('special_note') }}</div>
     * {% endif %}
     * 
     * @example Multiple field check:
     * {% if post.has_woo_meta('warranty') or post.has_woo_meta('guarantee') %}
     *     <div class="warranty-info">
     *         {% if post.has_woo_meta('warranty') %}
     *             <p>Garanti: {{ post.woo_meta('warranty') }}</p>
     *         {% endif %}
     *         {% if post.has_woo_meta('guarantee') %}
     *             <p>Garanti Süresi: {{ post.woo_meta('guarantee') }}</p>
     *         {% endif %}
     *     </div>
     * {% endif %}
     * 
     * @example Show section only if any field exists:
     * {% if post.has_woo_meta('badge') or post.has_woo_meta('label') %}
     *     <div class="badges">
     *         {% if post.has_woo_meta('badge') %}
     *             <span class="badge">{{ post.woo_meta('badge') }}</span>
     *         {% endif %}
     *         {% if post.has_woo_meta('label') %}
     *             <span class="label">{{ post.woo_meta('label') }}</span>
     *         {% endif %}
     *     </div>
     * {% endif %}
     * 
     * @example Avoid empty divs:
     * {% if post.has_woo_meta('shipping_note') %}
     *     <div class="shipping-note">
     *         <i class="icon-truck"></i>
     *         {{ post.woo_meta('shipping_note') }}
     *     </div>
     * {% endif %}
     * 
     * @example Loop with field check:
     * {% for product in products %}
     *     <div class="product">
     *         <h3>{{ product.title }}</h3>
     *         {% if product.has_woo_meta('badge') %}
     *             <span class="badge">{{ product.woo_meta('badge') }}</span>
     *         {% endif %}
     *     </div>
     * {% endfor %}
     */
    public function has_woo_meta($field_id) {
        return function_exists('wc_has_custom_field') 
            ? wc_has_custom_field($this->ID, $field_id) 
            : false;
    }

    // ── MISC / BACKWARD COMPAT ───────────────────────────

    // ── PRODUCT GALLERY ──────────────────────────────────

    /**
     * Ürün galerisi HTML'ini render eder.
     * Ayarlar General Settings > WooCommerce > Product Gallery'den okunur.
     * Template override ile inline seçenekler global ayarları ezer.
     *
     * Twig:
     *   {{ post.product_gallery() }}
     *   {{ post.product_gallery({'type': 'single'}) }}
     *   {{ post.product_gallery({'type': 'swiper', 'thumbs': true, 'lightbox': true}) }}
     *
     * @param  array $options   Inline override — global settings'i ezer
     * @param  string $size     WP image size (default: woocommerce_single)
     * @return \Twig\Markup
     */
    public function product_gallery( array $options = [], string $size = 'woocommerce_single' ): \Twig\Markup {
        if ( ! class_exists('\Timber\Timber') ) return new \Twig\Markup( '', 'UTF-8' );

        // Global settings — constants'tan oku
        $defaults = [
            'type'             => defined('GALLERY_TYPE')             ? GALLERY_TYPE             : 'swiper',
            'ratio'            => defined('GALLERY_RATIO')            ? GALLERY_RATIO            : '4x3',
            'object_fit'       => defined('GALLERY_OBJECT_FIT')       ? GALLERY_OBJECT_FIT       : 'cover',
            'hover'            => defined('GALLERY_HOVER')            ? GALLERY_HOVER            : 'lightbox',
            'lightbox'         => defined('GALLERY_LIGHTBOX')         ? GALLERY_LIGHTBOX         : true,
            'navigation'       => defined('GALLERY_NAVIGATION')       ? GALLERY_NAVIGATION       : true,
            'loop'             => defined('GALLERY_LOOP')             ? GALLERY_LOOP             : false,
            'thumbs'           => defined('GALLERY_THUMBS')           ? GALLERY_THUMBS           : true,
            'thumb_nav'        => defined('GALLERY_THUMB_NAV')        ? GALLERY_THUMB_NAV        : true,
            'variation_update' => defined('GALLERY_VARIATION_UPDATE') ? GALLERY_VARIATION_UPDATE : true,
            'pagination'       => defined('GALLERY_PAGINATION')       ? GALLERY_PAGINATION       : 'bullets',
            'slides_per_view'  => defined('GALLERY_SLIDES_PER_VIEW')  ? GALLERY_SLIDES_PER_VIEW  : 1,
            'thumb_count'      => defined('GALLERY_THUMB_COUNT')      ? GALLERY_THUMB_COUNT      : 4,
            'stack_gap'        => defined('GALLERY_STACK_GAP')         ? GALLERY_STACK_GAP        : 8,
        ];

        // Inline options global settings'i ezer
        $cfg = array_merge( $defaults, array_filter( $options, fn($v) => $v !== null ) );

        // Görselleri topla — variation görselleri dahil
        $p = $this->product();
        if ( ! $p ) return new \Twig\Markup( '', 'UTF-8' );

        $images = [];

        if ( $p->is_type('variation') ) {
            // Variation: kendi görselleri + ek görseller
            $images = $this->variation_all_images( $size );
        } elseif ( $p->is_type('variable') ) {
            // URL'den gelen attribute'larla eşleşen variation var mı? (color-xx/ slug'ı)
            // wp hook'unda $_REQUEST['attribute_pa_color'] set edilmiş olabilir
            $url_variation_id = 0;
            $variation_attrs  = $p->get_variation_attributes(); // ['pa_color' => [...], ...]
            if ( ! empty( $variation_attrs ) ) {
                $lookup = [];
                foreach ( array_keys( $variation_attrs ) as $taxonomy ) {
                    $req_key = 'attribute_' . $taxonomy; // 'attribute_pa_color'
                    if ( ! empty( $_REQUEST[ $req_key ] ) ) {
                        $lookup[ $req_key ] = sanitize_title( wp_unslash( $_REQUEST[ $req_key ] ) );
                    }
                }
                if ( ! empty( $lookup ) ) {
                    if ( class_exists( 'WC_Data_Store' ) ) {
                        $url_variation_id = (int) \WC_Data_Store::load('product')->find_matching_product_variation( $p, $lookup );
                    }
                }
            }

            if ( $url_variation_id > 0 ) {
                // URL'den gelen variation'ın görsellerini kullan
                $var_product = wc_get_product( $url_variation_id );
                if ( $var_product ) {
                    $var_main    = $var_product->get_image_id();
                    $var_gallery = $var_product->get_gallery_image_ids();
                    $var_ids     = $var_main ? array_merge( [ $var_main ], $var_gallery ) : $var_gallery;
                    foreach ( $var_ids as $att_id ) {
                        $url = wp_get_attachment_image_url( $att_id, $size );
                        if ( $url ) $images[] = $url;
                    }
                }
            }

            if ( empty( $images ) ) {
                // Variation seçili değil — ana ürünün kendi görsellerini kullan
                $parent_main    = $p->get_image_id();
                $parent_gallery = $p->get_gallery_image_ids();
                $parent_ids     = $parent_main ? array_merge( [ $parent_main ], $parent_gallery ) : $parent_gallery;
                foreach ( $parent_ids as $att_id ) {
                    $url = wp_get_attachment_image_url( $att_id, $size );
                    if ( $url ) $images[] = $url;
                }
            }

            if ( empty( $images ) ) {
                // Fallback: default variation görselleri
                $images = $this->default_variation_images( $size );
            }
            if ( empty( $images ) ) {
                $images = $this->all_image_urls( $size );
            }
        } else {
            // Simple / grouped / bundle
            $images = $this->all_image_urls( $size );
        }

        if ( empty( $images ) ) {
            // Fallback: placeholder
            $images = [ wc_placeholder_img_src( $size ) ];
        }

        // Full URL listesi — JS variation update için data attr'a da eklenir
        $context = \Timber\Timber::context();
        $context['gallery_cfg']    = $cfg;
        $context['gallery_images'] = $images;
        $context['gallery_post_id']= $this->ID;
        $context['gallery_base_url'] = trailingslashit( get_permalink( $this->ID ) );
        $context['gallery_is_variable'] = $p->is_type('variable') || $p->is_type('variation');
        $context['gallery_size']   = $size;

        // Thumbnail görselleri — küçük boyutta ayrı liste
        $thumb_images = [];
        $zoom_images  = [];
        foreach ( $images as $img_url ) {
            // URL'den attachment ID bulmaya çalış, bulamazsa aynı URL'yi kullan
            $att_id = attachment_url_to_postid( $img_url );
            if ( $att_id ) {
                $thumb_url = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' );
                $thumb_images[] = $thumb_url ?: $img_url;
                // Zoom için full size (ya da woocommerce_single'dan büyük boyut)
                $zoom_url = wp_get_attachment_image_url( $att_id, 'full' );
                $zoom_images[] = $zoom_url ?: $img_url;
            } else {
                $thumb_images[] = $img_url;
                $zoom_images[]  = $img_url;
            }
        }
        $context['gallery_thumb_images'] = $thumb_images;
        $context['gallery_zoom_images']  = $zoom_images;

        // ── Tüm variation gallery verilerini inline JS olarak yaz ───────────────
        // Her variation için: window.sh_gallery_{variation_id} = [{src, zoom}]
        // LightGallery ve gallery swiper bu veriyi kullanır — stale cache yok.
        $gallery_js_data = [];

        if ( $p->is_type('variable') ) {
            $variations = $p->get_available_variations();
            foreach ( $variations as $var_data ) {
                $var_id = (int) ( $var_data['variation_id'] ?? 0 );
                if ( ! $var_id ) continue;

                $var_product = wc_get_product( $var_id );
                if ( ! $var_product ) continue;

                // variation görselleri — variation_all_images() benzeri mantık
                $var_images = [];
                $var_zoom   = [];
                $var_thumb  = [];

                // Ana görsel
                $main_id = $var_product->get_image_id();
                $gal_ids = $var_product->get_gallery_image_ids();
                $all_ids = $main_id ? array_merge( [$main_id], $gal_ids ) : $gal_ids;

                // Ana ürün galeri de ekle (variation özel görseli yoksa)
                if ( empty( $all_ids ) ) {
                    $parent_id  = $p->get_image_id();
                    $parent_gal = $p->get_gallery_image_ids();
                    $all_ids    = $parent_id ? array_merge( [$parent_id], $parent_gal ) : $parent_gal;
                }

                foreach ( $all_ids as $att_id ) {
                    $src_url   = wp_get_attachment_image_url( $att_id, $size ) ?: '';
                    $zoom_url  = wp_get_attachment_image_url( $att_id, 'full' ) ?: $src_url;
                    $thumb_url = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' ) ?: $src_url;
                    if ( $src_url ) {
                        $var_images[] = $src_url;
                        $var_zoom[]   = $zoom_url;
                        $var_thumb[]  = $thumb_url;
                    }
                }

                if ( ! empty( $var_images ) ) {
                    $items = [];
                    foreach ( $var_images as $i => $src ) {
                        $items[] = [ 'src' => $src, 'zoom' => $var_zoom[$i] ?? $src, 'thumb' => $var_thumb[$i] ?? $src ];
                    }
                    $gallery_js_data[ $var_id ] = $items;
                }
            }
        }

        // Ana ürün görselleri de ekle (parent ID ile)
        $parent_items = [];
        foreach ( $images as $i => $src ) {
            $parent_items[] = [ 'src' => $src, 'zoom' => $zoom_images[$i] ?? $src, 'thumb' => $thumb_images[$i] ?? $src ];
        }
        $gallery_js_data[ $this->ID ] = $parent_items;

        $context['gallery_js_data']    = $gallery_js_data;
        $context['gallery_js_data_json'] = wp_json_encode( $gallery_js_data );

        $html = \Timber\Timber::compile( 'woo/product-gallery.twig', $context );

        return new \Twig\Markup( $html ?: '', 'UTF-8' );
    }

}