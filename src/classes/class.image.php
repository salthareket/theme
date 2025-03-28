<?php

namespace SaltHareket; 

Class Image{

	private $defaults = array(
        'src' => '',
        'id' => null,
        'class' => '',
        'style' => '',
        'lazy' => false,
        'lazy_native' => false,
        'width' => null,
        'height' => null,
        'alt' => '',
        'post' => null,
        'type' => "img", // img, picture
        'resize' => false,
        'lcp' => false,
        'placeholder' => false,
        'placeholder_class' => '',
        'preview' => false,
        'attrs' => []
    );

    private $args = array();
    private $attrs = array();
    private $prefix = "";
    private $has_breakpoints = false;
	private $is_single = false;
	private $breakpoints = array();
    private $id = 0;
    private $url = "";
    private $queries = [];
    private $invalid = false;

    public function __construct($args = array()) {

        $this->breakpoints = array_reverse($GLOBALS["breakpoints"]);

        $this->args = array_merge($this->defaults, $args);
        
        if (empty($this->args['src'])) {
            $this->not_found();
        }

        if($this->args["preview"]){
            $this->args["lcp"] = false;
            $this->args["lazy"] = false;
            $this->args["lazy_native"] = false;
        }else{
            if(image_is_lcp($this->args['src'])){
                $this->args["lcp"] = false;
                $this->args["lazy"] = false;
                $this->args["lazy_native"] = false;
            }
            if($this->args["lcp"]){
               $this->args["lazy"] = false;
               $this->args["lazy_native"] = false;
               $this->args["attrs"]["fetchpriority"] = "high";
               $this->args["attrs"]["loading"] = "eager";
            }            
        }

        $this->prefix = $this->args["lazy"]?"data-":"";

        if(is_array($this->args["src"]) && in_array(array_keys($this->args["src"])[0], array_keys($this->breakpoints))){

            $values = remove_empty_items(array_values($this->args["src"]));
            $values = array_unique($values);
            if(empty($values)){
                $this->invalid = true;
                return;
            }
            $this->id  = $this->args["src"]["id"];
            $this->url = $this->args["src"]["url"];
            unset($this->args["src"]["id"]);
            unset($this->args["src"]["url"]);
			
			if(count($values) == 2){
                if(!post_is_exist($values[0])){
                    $this->not_found();
                }else{
                    $this->args["src"] = $values[0];
                    $this->is_single = true;                    
                }
			}elseif(count($values) > 1){
                foreach($this->args["src"] as $key => $item){
                    if(empty($item)){
                        unset($this->args["src"][$key]);
                    }
                }
                $this->queries = array_keys($this->args["src"]);
                $this->has_breakpoints = true;

            }else{
                $this->not_found();
            }
		}
    }

    public function init(){
        
        if (empty($this->args['src']) || $this->invalid) {
            return $this->not_found();
        }

        if($this->has_breakpoints){
            $args_responsive = array();
            foreach($this->args["src"] as $key => $item){
                $args_temp = $this->args;
                $args_temp["src"] = $item;
                $args_temp = $this->get_image_set_post($args_temp);
                if($args_temp["post"]){
                    $args_responsive[] = array(
                        "post"   => $args_temp["post"],
                        "meta"   => wp_get_attachment_metadata($args_temp["id"]),
                        "srcset" => $args_temp["post"]->srcset(),
                        "prefix" => $this->prefix,
                        "breakpoint" => $key
                    );
                }
            }

            $this->args["src"]    = $args_responsive[0]["post"];
            $this->args["post"]    = $args_responsive[0]["post"];
            $this->args = $this->get_image_set_post($this->args);
            $this->args["srcset"] = $this->get_image_set_multiple($args_responsive, $this->has_breakpoints);
            $this->args["type"]   = $this->is_single?"img":"picture";


        }elseif (is_string($this->args["src"]) || is_numeric($this->args["src"]) || is_object($this->args["src"]) || is_array($this->args["src"])){

            $this->args = $this->get_image_set_post($this->args);

        }else{

            return $this->not_found();

        }

        if(!$this->args["post"]){
            return $this->not_found();
        }

        $attrs["width"] = $this->args["width"];
        $attrs["height"] = $this->args["height"];
        $attrs["alt"] = $this->args["alt"];

        $html = "";

        if(!$this->args["lazy"] && $this->args["lazy_native"]){
            $attrs["loading"] = "lazy";
        }

        $this->args["class"] .= $this->args["post"]->get_focal_point_class();

        if($this->args["type"] == "img"){

            if($this->is_single){

                $attrs[$this->prefix."src"] = $this->args["post"]->src();

            }else{

                $srcset = $this->args["post"]->srcset();
                if(!empty($srcset)){
                    $srcset = $this->reorder_srcset($srcset);
                    $attrs[$this->prefix."srcset"] = $srcset;
                    $attrs[$this->prefix."sizes"] = "auto";//create_sizes_attribute($srcset);//$args["post"]->img_sizes();
                    $attrs[$this->prefix."src"] = $this->args["post"]->src("thumbnail");
                }else{
                    $attrs[$this->prefix."src"] = $this->args["post"]->src();
                }        
                
            }
            
            if($this->args["post"]->post_mime_type == "image/svg+xml"){
                $this->args["class"] = str_replace("-cover", "-contain", $this->args["class"]);
            }
            
            $attrs["class"] = "img-fluid".($this->args["lazy"]?" lazy":"") . (!empty($this->args["class"])?" ".$this->args["class"]:"");

            if(!$this->args["lazy"] && $this->args["lcp"] && (ENABLE_MEMBERSHIP && is_user_logged_in())){
                add_action('wp_head', function() use ($attrs) {
                    $this->add_preload_image($attrs);
                });             
            }
            
            $attrs = array_merge($attrs, $this->args["attrs"]);
            $attrs = array2Attrs($attrs);
            $html .= "<img $attrs />";

        }elseif($this->args["type"] == "picture"){

            if($this->is_single){
                $attrs[$this->prefix."src"] = $this->args["post"]->src();
            }else{
                $attrs[$this->prefix."src"] = $this->args["post"]->src("thumbnail");
            }
            
            $attrs["class"] = "img-fluid".($this->args["lazy"]?" lazy":"") . (!empty($this->args["class"])?" ".$this->args["class"]:"");
            
            if(!$this->args["lazy"] && $this->args["lcp"] && (ENABLE_MEMBERSHIP && is_user_logged_in())){
                add_action('wp_head', function() use ($attrs) {
                    $this->add_preload_image($attrs);
                });
            }
            $attrs = array_merge($attrs, $this->args["attrs"]);
            $attrs = array2Attrs($attrs);
            $html .= "<picture ".(!empty($this->args["class"])?"class='".$this->args["class"]."'":"").">".$this->args["srcset"]."<img $attrs /></picture>";

        }

        if($this->args["placeholder"]){
            $html = '<div class="img-placeholder '.$this->args["placeholder_class"].' '. ($this->args["lazy"] && !$this->args["preview"]?"loading":"").'"  style="background-color:'.$this->args["post"]->meta("average_color").';aspect-ratio:'.$this->args["post"]->get_aspect_ratio().';">' . $html . '</div>';
        }

        return $html;
    }

    public function get_image_set_post($args=array()){

        if (is_numeric($args["src"])) {
            //echo $args["src"]." numeric";

            $args["id"] = intval($args["src"]);
            $args["post"] = \Timber::get_image($args["id"]);

        } elseif (is_string($args["src"])) {

            //echo $args["src"]." string";
            
            $args["id"] = get_attachment_id_by_url($args["src"]);
            $args["post"] = \Timber::get_image($args["id"]);

        } elseif (is_object($args["src"])) {
            //echo $args["src"]." object";

            if($args["src"]->post_type == "attachment"){
               $args["id"] = $args["src"]->ID;
               $args["post"] = $args["src"];
            }else{
                if($args["src"]->thumbnail){
                   $args["id"] = $args["src"]->id;
                   $args["post"] = $args["src"]->thumbnail; 
                }else{
                    return;
                }
            }

        } elseif (is_array($args["src"])) {

            $args["id"] = $args["src"]["id"];
            $args["post"] = \Timber::get_image($args["src"]["id"]);
        }

        if(empty($args["width"]) && isset($args["post"])){
            $args["width"] = $args["post"]->width();
        }
        if(empty($args["height"]) && isset($args["post"])){
            $args["height"] = $args["post"]->height();
        }

        if(empty($args["alt"])){
            if (isset($args["post"]) && !empty($args["post"]->alt())){
                $args["alt"] = $args["post"]->alt();
            }else{
                global $post;
                $args["alt"] = $post->post_title;
            }
        }

        return $args;
    }

    public function generateMediaQueries($selected) {
        $first = reset($selected);
        $last = end($selected);
        $queries = [];
        foreach ($selected as $index => $key) {
            if($key == $first){
                $next = $selected[$index+1];
                $query = "(min-width: " . ($this->breakpoints[$next]) . "px)";
            }elseif($key == $last){
                $query = "(max-width: " . ($this->breakpoints[$key] - 1) . "px)";
            }else{
                $next = $selected[$index+1];
                $query = "(max-width: " . ($this->breakpoints[$key] - 1) . "px) and (min-width: " . ($this->breakpoints[$next]) . "px)";
            }
            $queries[$key] = $query;
        }
        return $queries;
    }

    public function get_image_set_multiple($args=array(), $has_breakpoints = false){
        if(!$has_breakpoints){

            $src = array();
            if(isset($args[1]["meta"]["width"])){
                $mobile_start = $args[1]["meta"]["width"];
                foreach($args as $key => $set){
                    $set =  explode(",", $set["srcset"]);
                    foreach($set as $item){
                        $a = explode(" ", trim($item));
                        $width = str_replace('w', '', $a[1]);
                        if($width < 576){
                           continue;
                        }
                        if($key == 0 && $width > $mobile_start){
                            $src[$width] = $a[0]; 
                        }elseif ($key == 1 && $width <= $mobile_start){
                            $src[$width] = $a[0]; 
                        }
                    }
                }           
            }

            $srcset = "";
            if($src){
                uksort($src, function($a, $b) {
                    return $b - $a;
                });
                $counter = 0;
                $keys = array_keys($src);
                $last_key = end($keys);
                foreach ($src as $width => $item) {
                    $query_w = "max";
                    $w = $width;
                    if($width != $last_key){
                        $query_w = "min";
                        $w = $keys[$counter+1] + 1;
                    }
                    $srcset .= '<source media="('.$query_w.'-width: '.$w.'px)" '.$args[0]["prefix"].'srcset="'.$item.'"/>';
                    $counter++;
                }
            }

        }else{

            $html = "";
            $last_image = [];

            $values = array_values($this->breakpoints);
            $queries = $this->generateMediaQueries($this->queries);

            foreach ($args as $index => $item) {
                $value = $item;
                $query  = $queries[$item["breakpoint"]];
                $breakpoint_index = array_search($item["breakpoint"], $values);
                $breakpoint = $item["breakpoint"] == "xs"?"sm":$item["breakpoint"];
                $best_image = $this->find_best_image_for_breakpoint($args, $breakpoint, array_keys($this->breakpoints));

                if ($best_image) {
                    $html .= '<source media="'.$query.'" '.$args[0]["prefix"].'srcset="'.$best_image["image"].'"/>' . "\n";
                    $last_image = $best_image;
                }else{
                    if($last_image){
                        $html .= '<source media="'.$query.'" '.$args[0]["prefix"].'srcset="'.$last_image["src"]->src($breakpoint).'"/>' . "\n";
                    }
                }
            }
            $srcset = $html;
        }

        return $srcset;
    }

    public function find_best_image_for_breakpoint($images, $breakpoint, $breakpoints) {
        $current_index = array_search($breakpoint, $breakpoints);
        $index = array_search2d_by_field($breakpoint, $images, "breakpoint");

        //echo $breakpoint." indexi : ".$current_index."\n";
        //echo $index." = image ın bu breakpoint\n";

        if($index>-1){

            $item = $images[$index]["post"];

            return array(
                "src" => $item,
                "image" => $item->src(),//$breakpoint == "xxxl" ? $item->src() : $item->src($breakpoints[$index-1]),
                "sizes" => $item->img_sizes()
            );

        }else{

            for ($i = $current_index; $i >= 0; $i--) {
                $current_breakpoint = $breakpoints[$i];
                $index = array_search2d_by_field($current_breakpoint, $images, "breakpoint");
                if($index){
                    $item = $images[$index]["post"];
                    return array(
                        "src" => $item,
                        "image" => $item->src(),//$breakpoint == "xxxl" ? $item->src() : $item->src($breakpoints[$i-1]),
                        "sizes" => $item->img_sizes()
                    );
                }
            }

        }
        return [];
    }

    public function reorder_srcset($srcset) {// img için
        // Her bir kaynağı virgülle ayır
        $sources = explode(', ', $srcset);
        
        // Her bir kaynağı genişlik değeri ile birlikte bir diziye dönüştür
        $sources_array = [];
        foreach ($sources as $source) {
            // Kaynağı boşlukla ayır ve genişlik değerini al
            $parts = explode(' ', $source);
            $url = $parts[0];
            $width = intval($parts[1]);
            
            // Diziyi genişlik değeri ile birleştir
            $sources_array[] = ['url' => $url, 'width' => $width];
        }
        
        // Genişlik değerine göre sıralama yap
        usort($sources_array, function($a, $b) {
            return $a['width'] - $b['width'];
        });
        
        // Sıralanmış kaynakları yeniden stringe dönüştür
        $sorted_srcset = '';
        foreach ($sources_array as $source) {
            $sorted_srcset .= $source['url'] . ' ' . $source['width'] . 'w, ';
        }
        
        // Son virgülü kaldır
        return rtrim($sorted_srcset, ', ');
    }

    public static function add_preload_image($attrs=[], $echo = true){
        $code = "";
        error_log("add_preload_image ".json_encode($attrs));
        if(empty($attrs)){
            return;
        }
        if(is_array($attrs)){
            if(isset($attrs["srcset"])){
                $code = '<link rel="preload" as="image" href="'.$attrs["src"].'" imagesrcset="'.$attrs["srcset"].'" imagesizes="'.$attrs["sizes"].'" fetchpriority="high">'."\n";
            }else{
                error_log(json_encode($attrs["src"]));
                $code = '<link rel="preload" href="'.$attrs["src"].'" as="image" fetchpriority="high">'."\n";
            }       
        }else{
            $code = '<link rel="preload" href="'.$attrs.'" as="image" fetchpriority="high">'."\n";
        }
        if($echo){
            echo $code;
        }else{
            return $code;
        }
    }

    public function not_found(){
        if($this->args["placeholder"]){
            return '<div class="img-placeholder '.$this->args["placeholder_class"].' img-not-found"></div>';
        }else{
            return;
        }
    }

}