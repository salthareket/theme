<?php
class Term extends Timber\Term{
    protected $post_count;

    public function thumbnail(){
        return Timber::get_image($this->meta("image"));
    }

    public function get_image($field){
        return Timber::get_image($this->meta($field));
    }

    public function get_field_lang($field="", $lang=""){
        if(empty($field)){
            return;
        }
        if(empty($lang)){
            $lang = $GLOBALS["language"];
        }
        $value = get_term_meta( $this->ID, $field, true);
        return qtranxf_use($lang, $value, false, true );
    }
    public function lang($lang=""){
        return $this->i18n_config["name"]["ts"][$lang];
        //return qtrans_translate($this->name, $lang);
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
                    'terms' => $GLOBALS["site_config"]["user_region"],
                    'operator' => 'IN'
                )
              )
            ));
            $count = count($my_posts);            
        }
        return $count;
    }
    public function get_posts_2($post_type="post"){
        echo "aaa";
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