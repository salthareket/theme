<?php

use SaltHareket\Image;

class Term extends Timber\Term{
    protected $post_count;

    public function thumbnail(){
        return Timber::get_image(get_term_meta($this->term_id, '_thumbnail_id', true));
    }
    /*public function get_thumbnail($args=array()){
        $media = $this->meta("media");
        if($media->media_type == "image"){
            if($media->use_responsive_image){
                $args["src"] = $media->image_responsive;
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

    public function get_thumbnail(array $args = []){
        $media = $this->meta('media');
        $src   = '';

        if ($media && is_array($media)) {
            $type = $media["media_type"] ?? null;

            if ($type == 'image') {
                if (!empty($media["use_responsive_image"]) && !empty($media["image_responsive"])) {
                    $src = $media["image_responsive"];
                } elseif (!empty($media["image"])) {
                    $src = $media["image"];
                }
            }

            if ($type == 'gallery') {

                if (!empty($media["video_gallery"]) && count($media["video_gallery"]) > 0) {
                    $video = $media["video_gallery"][0];
                    $video_type = $video["type"];
                    $video_poster = $video["image"] ?? "";
                    $video_poster = $video_poster?$video_poster["url"]:"";
                    $video_file = $video["file"] ?? "";
                    $video_url = $video["url"] ?? "";
                    $videoArgs = [
                        "video_type" => $video_type,
                        "video_settings" => [
                            "videoBg" => isset($args["videoBg"]) ? $args["videoBg"] : 1, 
                            "autoplay" => isset($args["autoplay"]) ? $args["autoplay"] : 0, 
                            "loop" => isset($args["loop"]) ? $args["loop"] : 0,
                            "muted" => isset($args["muted"]) ? $args["muted"] : 0,
                            "videoReact" => isset($args["videoReact"]) ? $args["videoReact"] : 0, 
                            "controls" => isset($args["controls"]) ? $args["controls"] : 0, 
                            "controls_options" => [], 
                            "controls_options_settings" => [],
                            "controls_hide" => isset($args["controls"]) ? 1 : 0,
                            "ratio" => "", 
                            "custom_video_image" => "",
                            "video_image" => $video_poster ?? null, 
                            "vtt" => ""
                        ],
                    ];
                    $videoDataKey = ($video_type == "file") ? "video_file" : "video_url";
                    $videoArgs[$videoDataKey] = ($video_type == "file") 
                        ? ["desktop" => $video_file ?? null] 
                        : $video_url ?? null;
                    return get_video([
                        "src" => $videoArgs, "class" => $args["class"]." ", "init" => true, "lazy" => true, "attrs" => []
                    ]);                    
                }
            }
        }

        // 2) Fallback: Timber Term Thumbnail
        if (empty($src)) {
            $src = method_exists($this, 'thumbnail') ? $this->thumbnail() : '';
        }

        $args['src'] = $src;

        // 3) SaltHareket\Image güvenli çağrı
        try {
            $image = new \SaltHareket\Image($args);
            return $image->init();
        } catch (\Throwable $e) {
            //error_log('Term get_thumbnail failed: ' . $e->getMessage());
            return '';
        }
    }


    public function get_field_lang($field="", $lang=""){
        if(empty($field)){
            return;
        }
        if(empty($lang)){
            $lang = Data::get("language");
        }
        $value = get_term_meta( $this->ID, $field, true);
        if(ENABLE_MULTILANGUAGE == "qtranslate"){
            return qtranxf_use($lang, $value, false, true );
        }
    }
    public function lang($lang=""){
        if(ENABLE_MULTILANGUAGE == "qtranslate"){
            return $this->i18n_config["name"]["ts"][$lang];
            //return qtrans_translate($this->name, $lang);
        }
    }
    public function content(){
        $desc = $this->description;
        if(ENABLE_MULTILANGUAGE){
            global $q_config;
            if(empty($desc) && !$q_config["hide_untranslated"] && $q_config["show_alternative_content"]){
                $default_language = $q_config["default_language"];
                $desc = nl2br($this->i18n_config["description"]["ts"][$default_language]);
            }
        }
        return $desc;
    }
    public function get_country_post_count(){
        $count = true;
        if(ENABLE_REGIONAL_PRODUCTS){
            $my_posts = get_posts(array(
              'post_type' => 'urun', //post type
              'numberposts' => -1,
              'tax_query' => array(
                array(
                  'taxonomy' => 'urun-kategorisi', //taxonomy name
                  'field' => 'id', //field to get
                  'terms' => $this->ID, //term id
                  'include_children' => true,
                ),
                array(
                    'taxonomy' => 'region',
                    'field' => 'term_id',
                    'terms' => Data::get("site_config.user_region"),
                    'operator' => 'IN'
                )
              )
            ));
            $count = count($my_posts);            
        }
        return $count;
    }
    public function get_posts_2($post_type="post"){
        return Timber::get_posts(
            array(
                'post_type' => $post_type,
                'post_status' => 'inherit',
                'tax_query' => array(
                    array(
                        'taxonomy' => $this->taxonomy,
                        'field' => 'id',
                        'terms' => $this->term_id
                    )
                )
            )
        );
    }
    public function get_post_count(){
        if(empty($this->post_count)){
            $this->post_count = get_category_total_post_count($this->taxonomy, $this->term_id);
        }
        return $this->post_count;
    }

    public function get_breadcrumb() {
        $term_id = $this->term_id;
        $breadcrumb = [];
        $code = "";
        while ($term_id) {
            $term = get_term($term_id, $this->taxonomy);
            if (is_wp_error($term) || !$term) {
                break;
            }
            $breadcrumb[] = [
                'post_title' => $term->name,
                'link' => get_term_link($term),
                'ID' => $term->term_id
            ];
            $term_id = $term->parent;
        }
        $nodes = array_reverse($breadcrumb);
        return generate_breadcrumb($nodes);
        /*if($breadcrumb){
            foreach ($breadcrumb as $crumb) {
                $code .= '<a href="' . esc_url($crumb['link']) . '">' . esc_html($crumb['name']) . '</a> &raquo; ';
            }            
        }
        return $code;*/
    }

    public function get_term_root(){
        return get_term_root($this->term_id, $this->taxonomy);
    }

}