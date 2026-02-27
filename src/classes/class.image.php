<?php

namespace SaltHareket;

class Image {

    // Sınıf versiyonu (Tasarım/CSS değişirse burayı arttır, tüm cache patlasın)
    private const VERSION = "1.0.6"; 
    
    // Aynı sayfa içinde tekrar tekrar işlem yapmamak için statik depolama
    private static $static_cache = [];

    private $defaults = array(
        'src' => '',
        'id' => null,
        'class' => '',
        'style' => '',
        'lazy' => true,
        'lazy_native' => false,
        'width' => null,
        'height' => null,
        'alt' => '',
        'post' => null,
        'type' => "img", 
        'resize' => false,
        'lcp' => false,
        'placeholder' => false,
        'placeholder_class' => '',
        'preview' => false,
        'attrs' => [],
        'wrapper' => false
    );

    private $args = array();
    private $prefix = "";
    private $has_breakpoints = false;
    private $is_single = false;
    private $breakpoints = array();
    private $id = 0;
    private $url = "";
    private $queries = [];
    private $invalid = false;
    private $lcp_checked = false;

    public function __construct($args = array()) {

        //$this->breakpoints = array_reverse($GLOBALS["breakpoints"] ?? []);
        $this->breakpoints = array_reverse(Data::get("breakpoints") ?? []);
        $this->args = array_merge($this->defaults, $args);
        $this->lcp_checked = isset($this->args["lcp"]);

        if(!isset($this->args["attrs"])) $this->args["attrs"] = [];
        
        if (empty($this->args['src'])) {
            $this->invalid = true;
            return;
        }

        // LCP ve Lazy Logic (Senin dokunulmaz mantığın)
        if($this->args["preview"]){
            $this->args["lcp"] = false;
            $this->args["lazy"] = false;
        } else {
            $lcp_status = $this->lcp_checked ? $this->args["lcp"] : (function_exists('image_is_lcp') ? image_is_lcp($this->args['src']) : false);
            if($lcp_status){
                $this->args["lcp"] = true;
                $this->args["lazy"] = false;
                $this->args["attrs"]["fetchpriority"] = "high";
                $this->args["attrs"]["decoding"] = "sync";
            } else {
                $this->args["lcp"] = false;
                $this->args["lazy"] = true;
            }
        }

        $this->prefix = $this->args["lazy"] ? "data-" : "";

        // Breakpoint Ayıklama Logic
        if(is_array($this->args["src"]) && !empty($this->breakpoints) && in_array(array_keys($this->args["src"])[0], array_keys($this->breakpoints))){
            $values = remove_empty_items(array_values($this->args["src"]));
            $values = array_unique($values);
            
            if(empty($values)) { $this->invalid = true; return; }

            $this->id  = $this->args["src"]["id"] ?? 0;
            $this->url = $this->args["src"]["url"] ?? "";
            unset($this->args["src"]["id"], $this->args["src"]["url"]);
            
            if(count($values) == 2){
                $this->args["src"] = $values[0];
                $this->is_single = true;
            } elseif(count($values) > 1){
                $this->args["src"] = array_filter($this->args["src"]);
                $this->queries = array_keys($this->args["src"]);
                $this->has_breakpoints = true;
            } else {
                $this->invalid = true;
            }
        }
    }

    public function init() {
        if ($this->invalid) return $this->not_found();

        // --- CACHE BAŞLANGIÇ ---
        $cache_key = 'sh_img_' . md5(serialize($this->args) . self::VERSION);

        // 1. Statik Cache (Aynı request içinde)
        if (isset(self::$static_cache[$cache_key])) return self::$static_cache[$cache_key];

        // 2. Transient Cache (DB / Redis)
        $cached_html = get_transient($cache_key);
        if ($cached_html !== false) {
            self::$static_cache[$cache_key] = $cached_html;
            return $cached_html;
        }
        // --- CACHE SONU ---

        // Asıl İşlem Yükü (generate_actual_html metoduna taşıdım ki temiz olsun)
        $html = $this->generate_actual_html();

        // --- CACHE KAYIT VE TAGGING ---
        if ($html && !$this->invalid) {
            set_transient($cache_key, $html, DAY_IN_SECONDS);
            self::$static_cache[$cache_key] = $html;
            $this->track_dependencies($cache_key);
        }

        return $html;
    }

    private function generate_actual_html() {
        // Senin orijinal init içindeki HTML üretim mantığın
        if($this->has_breakpoints){
            $args_responsive = array();
            foreach($this->args["src"] as $key => $item){
                $args_temp = $this->args;
                $args_temp["src"] = $item;
                $args_temp = $this->get_image_set_post($args_temp);
                if(isset($args_temp["post"]) && $args_temp["post"]){
                    $args_responsive[] = array(
                        "post"   => $args_temp["post"],
                        "meta"   => wp_get_attachment_metadata($args_temp["id"]),
                        "srcset" => $args_temp["post"]->srcset(),
                        "prefix" => $this->prefix,
                        "breakpoint" => $key
                    );
                }
            }

            if(empty($args_responsive)) return $this->not_found();

            $this->args["src"]    = $args_responsive[0]["post"];
            $this->args["post"]   = $args_responsive[0]["post"];
            $this->args = $this->get_image_set_post($this->args);
            $this->args["srcset"] = $this->get_image_set_multiple($args_responsive, $this->has_breakpoints);
            $this->args["type"]   = $this->is_single ? "img" : "picture";

        } else {
            $this->args = $this->get_image_set_post($this->args);
        }

        if(!isset($this->args["post"]) || !$this->args["post"]) return $this->not_found();

        // Attrs ve Focal Point Logic
        $attrs = [
            "width" => $this->args["width"],
            "height" => $this->args["height"],
            "alt" => $this->args["alt"]
        ];

        if (!empty($this->args["style"])) $attrs["style"] = $this->args["style"];
        if (!$this->args["lazy"] && $this->args["lazy_native"]) $attrs["loading"] = "lazy";

        if (method_exists($this->args['post'], 'get_focal_point_class')) {
            $class = $this->args['class'] ?? '';
            if (strpos($class, 'object-position-') === false) {
                $class .= ' ' . $this->args['post']->get_focal_point_class();
            }
            $this->args['class'] = trim($class);
        }

        $html = "";
        $base_class = "img-fluid" . ($this->args["lazy"] ? " lazy" : "") . (!empty($this->args["class"]) ? " " . $this->args["class"] : "");

        if($this->args["type"] == "img") {
            if($this->is_single){
                $attrs[$this->prefix."src"] = $this->args["post"]->src();
            } else {
                $srcset = $this->args["post"]->srcset();
                if(!empty($srcset)){
                    $attrs[$this->prefix."srcset"] = $this->reorder_srcset($srcset);
                    $attrs[$this->prefix."sizes"] = "auto";
                    $attrs[$this->prefix."src"] = $this->args["post"]->src("medium");
                } else {
                    $attrs[$this->prefix."src"] = $this->args["post"]->src();
                }
            }
            $attrs["class"] = $base_class;
            $this->handle_lcp_preload($attrs);
            $merged_attrs = array2Attrs(array_merge($attrs, $this->args["attrs"]));
            $html = "<img $merged_attrs />";

        } elseif($this->args["type"] == "picture") {
            $attrs[$this->prefix."src"] = $this->is_single ? $this->args["post"]->src() : $this->args["post"]->src("medium");
            $attrs["class"] = $base_class;
            $this->handle_lcp_preload($attrs);
            $merged_attrs = array2Attrs(array_merge($attrs, $this->args["attrs"]));
            $html = "<picture ".(!empty($this->args["class"])?"class='".$this->args["class"]."'":"").">".$this->args["srcset"]."<img $merged_attrs /></picture>";
        }

        if($this->args["placeholder"]){
            $html = '<div class="img-placeholder '.$this->args["placeholder_class"].' '. ($this->args["lazy"] && !$this->args["preview"]?"loading":"").'" style="background-color:'.$this->args["post"]->meta("average_color").';aspect-ratio:'.$this->args["post"]->get_aspect_ratio().';">' . $html . '</div>';
        }

        return $html;
    }

    private function handle_lcp_preload($attrs) {
        if(!$this->args["lazy"] && $this->args["lcp"] && (!function_exists('is_user_logged_in') || is_user_logged_in())){
            add_action('wp_head', function() use ($attrs) {
                self::add_preload_image($attrs);
            });
        }
    }

    private function track_dependencies($cache_key) {
        $ids = [];
        // src array ise içindeki tüm ID'leri topla
        if (is_array($this->args['src'])) {
            foreach($this->args['src'] as $val) {
                if (is_numeric($val)) $ids[] = (int)$val;
            }
        }
        // Mevcut post ID'sini ekle
        if (isset($this->args['post']->ID)) $ids[] = (int)$this->args['post']->ID;
        
        $ids = array_unique(array_filter($ids));

        foreach ($ids as $id) {
            $rel_key = "sh_img_rel_" . $id;
            $rels = get_option($rel_key, []);
            if (!in_array($cache_key, $rels)) {
                $rels[] = $cache_key;
                update_option($rel_key, $rels, false);
            }
        }
    }

    public function get_image_set_post($args=array()){
        if (is_numeric($args["src"])) {
            $args["id"] = intval($args["src"]);
            $args["post"] = \Timber::get_image($args["id"]);
        } elseif (is_string($args["src"])) {
            $args["id"] = attachment_url_to_postid($args["src"]);
            $args["post"] = $args["id"] ? \Timber::get_image($args["id"]) : new \Timber\Image($args["src"]);
        } elseif (is_object($args["src"])) {
            if(isset($args["src"]->post_type) && $args["src"]->post_type == "attachment"){
               $args["id"] = $args["src"]->ID;
               $args["post"] = $args["src"];
            } else if(isset($args["src"]->thumbnail)) {
               $args["id"] = $args["src"]->thumbnail->id;
               $args["post"] = $args["src"]->thumbnail;
            }
        } elseif (is_array($args["src"]) && isset($args["src"]["id"])) {
            $args["id"] = $args["src"]["id"];
            $args["post"] = \Timber::get_image($args["src"]["id"]);
        }

        if(isset($args["post"])) {
            if(empty($args["width"])) $args["width"] = $args["post"]->width();
            if(empty($args["height"])) $args["height"] = $args["post"]->height();
            if(empty($args["alt"])) $args["alt"] = !empty($args["post"]->alt()) ? $args["post"]->alt() : (get_post($args["id"])->post_title ?? "");
        }
        return $args;
    }

    // --- DİĞER YARDIMCI METODLAR (Sıralama, MediaQuery vs.) ---
    // (Buralar senin orijinal mantığınla aynı kaldı, sadece temizlendi)

    public function generateMediaQueries($selected) {
        $selected = array_reverse($selected);
        $first = reset($selected); $last = end($selected);
        $queries = []; $breakpoints = array_reverse($this->breakpoints);
        foreach ($selected as $index => $key) {
            if($key == $first){
                $next = $selected[$index+1];
                $query = "(max-width: " . ($breakpoints[$next]) . "px)";
            } elseif($key == $last){
                $query = "(min-width: " . ($breakpoints[$key] - 1) . "px)";
            } else {
                $next = $selected[$index+1];
                $query = "(max-width: " . ($breakpoints[$next] - 1) . "px) and (min-width: " . ($breakpoints[$key]) . "px)";
            }
            $queries[$key] = $query;
        }
        return $queries;
    }

    public function get_image_set_multiple($args=array(), $has_breakpoints = false){
        $srcset = "";
        if(!$has_breakpoints && isset($args[1]["meta"]["width"])){
            $src = array();
            $mobile_start = $args[1]["meta"]["width"];
            foreach($args as $key => $set){
                $sources = explode(",", $set["srcset"]);
                foreach($sources as $item){
                    $a = explode(" ", trim($item));
                    if(count($a) < 2) continue;
                    $width = (int)str_replace('w', '', $a[1]);
                    if($width < 576) continue;
                    if(($key == 0 && $width > $mobile_start) || ($key == 1 && $width <= $mobile_start)) $src[$width] = $a[0];
                }
            }
            uksort($src, fn($a, $b) => $b - $a);
            $keys = array_keys($src); $last_key = end($keys); $counter = 0;
            foreach ($src as $width => $item) {
                $query_w = ($width != $last_key) ? "min" : "max";
                $w = ($width != $last_key) ? $keys[$counter+1] + 1 : $width;
                $srcset .= '<source media="('.$query_w.'-width: '.$w.'px)" '.$args[0]["prefix"].'srcset="'.$item.'" />';
                $counter++;
            }
        } else {
            $html = ""; $queries = $this->generateMediaQueries($this->queries);
            $breakpoints = $this->breakpoints;
            foreach ($args as $item) {
                $query = $queries[$item["breakpoint"]];
                $bp = $item["breakpoint"] == "xs" ? "sm" : $item["breakpoint"];
                $best = $this->find_best_image_for_breakpoint($args, $bp, array_keys($breakpoints));
                if ($best) $html .= '<source media="'.$query.'" '.$args[0]["prefix"].'srcset="'.$best["image"].'" />' . "\n";
            }
            $srcset = $html;
        }
        return $srcset;
    }

    public function find_best_image_for_breakpoint($images, $breakpoint, $breakpoints) {
        $idx = array_search2d_by_field($breakpoint, $images, "breakpoint");
        if($idx > -1) return ["image" => $images[$idx]["post"]->src()];
        
        $curr = array_search($breakpoint, $breakpoints);
        for ($i = $curr; $i >= 0; $i--) {
            $idx = array_search2d_by_field($breakpoints[$i], $images, "breakpoint");
            if($idx > -1) return ["image" => $images[$idx]["post"]->src()];
        }
        return [];
    }

    public function reorder_srcset($srcset) {
        $sources = explode(', ', $srcset); $arr = [];
        foreach ($sources as $s) {
            $p = explode(' ', $s);
            if(count($p) == 2) $arr[] = ['url' => $p[0], 'width' => (int)$p[1]];
        }
        usort($arr, fn($a, $b) => $a['width'] - $b['width']);
        return implode(', ', array_map(fn($s) => $s['url'].' '.$s['width'].'w', $arr));
    }

    public static function add_preload_image($attrs=[], $echo = true){
        if(empty($attrs)) return;
        if(is_array($attrs)){
            $code = isset($attrs["srcset"]) 
                ? '<link rel="preload" as="image" href="'.$attrs["src"].'" imagesrcset="'.$attrs["srcset"].'" imagesizes="'.$attrs["sizes"].'" fetchpriority="high">'."\n"
                : '<link rel="preload" href="'.$attrs["src"].'" as="image" fetchpriority="high">'."\n";
        } else {
            $code = '<link rel="preload" href="'.$attrs.'" as="image" fetchpriority="high">'."\n";
        }
        if($echo) echo $code; else return $code;
    }

    public function not_found(){
        return $this->args["placeholder"] ? '<div class="img-placeholder '.$this->args["placeholder_class"].' img-not-found"></div>' : '';
    }

    public static function render($args) {
        // Önce bi hash'e bak, nesneyi hiç doğurmadan sonucu dönebilir miyiz?
        $version = self::VERSION;
        $cache_key = 'sh_img_' . md5(serialize($args) . $version);

        if (isset(self::$static_cache[$cache_key])) {
            return self::$static_cache[$cache_key];
        }

        // Yoksa mecbur nesneyi oluşturup işi yaptıralım
        $instance = new self($args);
        return $instance->init();
    }
}

/** * AUTO-INVALIDATION: Görsel silindiğinde cache'i patlatır.
 * Bu kısmı functions.php'ye veya sınıftan bağımsız bir yere koymalısın.
 */
add_action('delete_attachment', function($post_id) {
    $rel_key = "sh_img_rel_" . $post_id;
    $related_hashes = get_option($rel_key);
    if ($related_hashes && is_array($related_hashes)) {
        foreach ($related_hashes as $hash) {
            delete_transient($hash);
        }
        delete_option($rel_key);
    }
}, 10, 1);