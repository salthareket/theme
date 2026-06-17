<?php

namespace SaltHareket;

/**
 * Image — Responsive image set, srcset, lazy loading, breakpoint-based image generation.
 *
 * @version 1.0.8
 *
 * @changelog
 *   1.0.8 - 2026-06-18
 *     - Fix: _sh_img_invalidate_cache() artık function_exists() check ile korunuyor
 *     - Fix: Namespace dışında tanımlanıyor, WP hook sistemi ile uyumlu
 *   1.0.7 - 2026-05-18
 *     - Add: ExternalImage wrapper class — Timber\Image interface'ini taklit eder
 *     - Add: is_external_url() — site host ile URL host karşılaştırır
 *     - Fix: get_image_set_post() — external URL'lerde attachment_url_to_postid() DB sorgusu atlanıyor
 *     - Fix: get_image_set_post() — array src'de id=0 ise url key'ine bakıyor
 *     - Fix: generate_actual_html() — width=0 / height=0 attribute'ları artık yazılmıyor
 *     - Fix: render() ve init() cache key'leri birleştirildi, preview flag'i key'e dahil edildi
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   // Numeric ID
 *   get_image_set(['src' => 123, 'class' => 'img-fluid']);
 *
 *   // Local URL
 *   get_image_set(['src' => 'https://site.com/wp-content/uploads/foto.jpg']);
 *
 *   // External URL (picsum, CDN vs.) — ExternalImage wrapper kullanılır
 *   get_image_set(['src' => 'https://picsum.photos/800/600']);
 *
 *   // ACF array (id + url)
 *   get_image_set(['src' => ['id' => 0, 'url' => 'https://picsum.photos/800/600']]);
 *
 * Examples:
 *   // Twig'de:
 *   {{ img({'src': post.thumbnail.id, 'class': 'img-fluid'}) }}
 *   {{ img({'src': 'https://picsum.photos/800/600', 'class': 'img-fluid'}) }}
 *   {{ img({'src': fields.image, 'class': 'img-fluid'}) }}
 *
 * @package SaltHareket
 */
class Image {

    // Sınıf versiyonu (Tasarım/CSS değişirse burayı arttır, tüm cache patlasın)
    private const VERSION = "1.0.7"; 
    
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
        'wrapper' => false,
        'inline' => true
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
        } else {
            // src array ama breakpoint key'i değil — get_image_set_post'ta handle edilecek
        }
    }

    public function init() {
        if ($this->invalid) return $this->not_found();

        // render() ile aynı cache key — tüm args hash'leniyor
        $cache_key = 'sh_img_' . md5(json_encode([
            $this->args['src'] ?? '',
            $this->args['class'] ?? '',
            $this->args['type'] ?? '',
            $this->args['lazy'] ?? true,
            $this->args['lcp'] ?? false,
            $this->args['placeholder'] ?? false,
            $this->args['width'] ?? '',
            $this->args['height'] ?? '',
            $this->args['inline'] ?? true,
            $this->args['wrapper'] ?? false,
            $this->args['preview'] ?? false,
            self::VERSION
        ], JSON_UNESCAPED_UNICODE));

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

        if(!isset($this->args["post"]) || !$this->args["post"]) {
            return $this->not_found();
        }

        // Attrs ve Focal Point Logic
        $attrs = [
            "alt" => $this->args["alt"]
        ];
        // width/height sadece pozitif değerlerde ekle (0 veya null ise yazma)
        if (!empty($this->args["width"]))  $attrs["width"]  = $this->args["width"];
        if (!empty($this->args["height"])) $attrs["height"] = $this->args["height"];

        if (!empty($this->args["style"])) $attrs["style"] = $this->args["style"];
        if (!$this->args["lazy"] && $this->args["lazy_native"]) $attrs["loading"] = "lazy";

        if (method_exists($this->args['post'], 'get_focal_point_class')) {
            $class = $this->args['class'] ?? '';
            if (strpos($class, 'object-position-') === false) {
                $class .= ' ' . $this->args['post']->get_focal_point_class();
            }
            $this->args['class'] = trim($class);
        }

        $is_svg = (pathinfo($this->args['post']->src(), PATHINFO_EXTENSION) === 'svg');
        
        if ($is_svg && $this->args['inline']) {
            $svg_content = $this->get_inline_svg($this->args['post']->src());
            if (!empty($svg_content)) {
                return $svg_content; // Direkt SVG kodunu dön ve çık
            }
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
        if(!$this->args["lazy"] && $this->args["lcp"]){
            $preload_attrs = $attrs;
            add_action('wp_head', function() use ($preload_attrs) {
                self::add_preload_image($preload_attrs);
            }, 2);
        }
    }

    private function track_dependencies($cache_key) {
        $ids = [];
        if (isset($this->args['post']) && is_object($this->args['post']) && isset($this->args['post']->ID)) {
            $ids[] = (int) $this->args['post']->ID;
        }
        if ($this->id) $ids[] = (int) $this->id;

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) return;

        // Batch: tek option'da tüm mapping'leri tut
        $all_rels = get_option('sh_img_rels', []);
        $changed = false;

        foreach ($ids as $id) {
            if (!isset($all_rels[$id])) $all_rels[$id] = [];
            if (!in_array($cache_key, $all_rels[$id])) {
                $all_rels[$id][] = $cache_key;
                $changed = true;
            }
        }

        if ($changed) {
            update_option('sh_img_rels', $all_rels, false);
        }
    }

    /**
     * Bir URL'nin external (başka domain) olup olmadığını kontrol eder.
     */
    private function is_external_url($url): bool {
        if (!is_string($url) || empty($url)) return false;
        if (strpos($url, '//') === 0) $url = 'https:' . $url;
        if (strpos($url, 'http') !== 0) return false; // relative path

        $site_host = parse_url(site_url(), PHP_URL_HOST);
        $url_host  = parse_url($url, PHP_URL_HOST);

        return $url_host && $url_host !== $site_host;
    }

    public function get_image_set_post($args=array()){
        if (is_numeric($args["src"])) {
            $args["id"] = intval($args["src"]);
            $args["post"] = \Timber::get_image($args["id"]);

        } elseif (is_string($args["src"])) {
            // External URL ise Timber'a hiç sorma, direkt ExternalImage wrapper yap
            if ($this->is_external_url($args["src"])) {
                $args["id"]   = 0;
                $args["post"] = new ExternalImage($args["src"]);
            } else {
                $args["id"]   = attachment_url_to_postid($args["src"]);
                $args["post"] = $args["id"] ? \Timber::get_image($args["id"]) : new \Timber\Image($args["src"]);
            }

        } elseif (is_object($args["src"])) {
            if(isset($args["src"]->post_type) && $args["src"]->post_type == "attachment"){
               $args["id"] = $args["src"]->ID;
               $args["post"] = $args["src"];
            } else if(isset($args["src"]->thumbnail)) {
               $args["id"] = $args["src"]->thumbnail->id;
               $args["post"] = $args["src"]->thumbnail;
            }
        } elseif (is_array($args["src"]) && isset($args["src"]["id"])) {
            $args["id"] = (int) $args["src"]["id"];
            if ($args["id"] > 0) {
                // Normal WP attachment
                $args["post"] = \Timber::get_image($args["id"]);
            } elseif (!empty($args["src"]["url"])) {
                // id=0 ama url var (dummy data veya external array)
                $url = $args["src"]["url"];
                if ($this->is_external_url($url)) {
                    $args["post"] = new ExternalImage($url);
                } else {
                    $args["id"]   = attachment_url_to_postid($url);
                    $args["post"] = $args["id"] ? \Timber::get_image($args["id"]) : new \Timber\Image($url);
                }
            }
        }

        if(isset($args["post"])) {
            if(empty($args["width"]))  $args["width"]  = $args["post"]->width();
            if(empty($args["height"])) $args["height"] = $args["post"]->height();
            if(empty($args["alt"]))    $args["alt"]    = !empty($args["post"]->alt()) ? $args["post"]->alt() : (get_post($args["id"])->post_title ?? "");
        }
        return $args;
    }

    // --- SVG INLINE LOGIC ---
    private function get_inline_svg($url) {
        $svg = $this->file_get_contents_curl($url);
        if (!$svg) return '';

        $svg = preg_replace('/<!--.*?-->/s', '', $svg); // HTML yorumları sil
        $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg); // xml deklarasyonunu sil

        $suffix = '_' . uniqid();
        
        // ID ve Referansları benzersiz yap (Çakışma önleyici)
        $svg = preg_replace_callback('/id="([^"]+)"/', fn($m) => 'id="'.$m[1].$suffix.'"', $svg);
        $svg = preg_replace_callback('/url\(#([^)]+)\)/', fn($m) => 'url(#'.$m[1].$suffix.')', $svg);
        $svg = preg_replace_callback('/xlink:href="#([^"]+)"/', fn($m) => 'xlink:href="#'.$m[1].$suffix.'"', $svg);

        // Class ve Attr yönetimi
        $extra_attrs = "";
        if(!empty($this->args["class"])) $svg = str_replace("<svg ", "<svg class='".$this->args["class"]."' ", $svg);
        if(!empty($this->args["width"])) $extra_attrs .= ' width="'.$this->args["width"].'"';
        if(!empty($this->args["height"])) $extra_attrs .= ' height="'.$this->args["height"].'"';

        if(!empty($extra_attrs)){
            $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg);
            $svg = preg_replace('/<svg\b(.*?)>/i', '<svg$1'.$extra_attrs.'>', $svg, 1);
        }

        // viewBox kontrolü
        if(stripos($svg, "viewBox") === false){
            if(preg_match('/<svg[^>]*\bwidth=["\']?(\d+)[^"\']*["\']?/i', $svg, $wMatch) &&
               preg_match('/<svg[^>]*\bheight=["\']?(\d+)[^"\']*["\']?/i', $svg, $hMatch)){
                $vb = ' viewBox="0 0 '.$wMatch[1].' '.$hMatch[1].'"';
                $svg = preg_replace('/<svg\b(.*?)>/i', '<svg$1'.$vb.'>', $svg, 1);
            }
        }
        return $svg;
    }

    private function file_get_contents_curl($url) {
        // 1. URL'yi local path'e çevir (en hızlı)
        $local_path = $this->url_to_local_path($url);
        if ($local_path && file_exists($local_path)) {
            return file_get_contents($local_path);
        }

        // 2. allow_url_fopen açıksa direkt oku
        if (ini_get('allow_url_fopen')) {
            $content = @file_get_contents($url);
            if ($content !== false) return $content;
        }

        // 3. Curl fallback
        if (function_exists('curl_version')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        }

        return false;
    }

    /**
     * Convert URL to local filesystem path
     */
    private function url_to_local_path($url) {
        if (empty($url) || !is_string($url)) return false;

        $site_url = site_url('/');
        $abspath = rtrim(ABSPATH, '/');

        // Relative URL (starts with /)
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $site_path = rtrim(parse_url($site_url, PHP_URL_PATH) ?: '', '/');
            if ($site_path && strpos($url, $site_path) === 0) {
                $url = substr($url, strlen($site_path));
            }
            return $abspath . $url;
        }

        // Absolute URL (same host)
        if (strpos($url, $site_url) === 0) {
            $relative = str_replace($site_url, '', $url);
            return $abspath . '/' . ltrim($relative, '/');
        }

        return false;
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
                ? '<link rel="preload" as="image" href="'.esc_url($attrs["src"]).'" imagesrcset="'.esc_attr($attrs["srcset"]).'" imagesizes="'.esc_attr($attrs["sizes"]).'" fetchpriority="high">'."\n"
                : '<link rel="preload" href="'.esc_url($attrs["src"]).'" as="image" fetchpriority="high">'."\n";
        } else {
            $code = '<link rel="preload" href="'.esc_url($attrs).'" as="image" fetchpriority="high">'."\n";
        }
        if($echo) echo $code; else return $code;
    }

    public function not_found(): string {
        return $this->args["placeholder"] ? '<div class="img-placeholder ' . esc_attr($this->args["placeholder_class"]) . ' img-not-found"></div>' : '';
    }

    public static function clearStaticCache() {
        self::$static_cache = [];
    }

    public static function render($args) {
        $version = self::VERSION;
        $cache_key = 'sh_img_' . md5(json_encode([
            $args['src'] ?? '',
            $args['class'] ?? '',
            $args['type'] ?? '',
            $args['lazy'] ?? true,
            $args['lcp'] ?? false,
            $args['placeholder'] ?? false,
            $args['width'] ?? '',
            $args['height'] ?? '',
            $args['inline'] ?? true,
            $args['wrapper'] ?? false,
            $args['preview'] ?? false,
            $version
        ], JSON_UNESCAPED_UNICODE));

        if (isset(self::$static_cache[$cache_key])) {
            return self::$static_cache[$cache_key];
        }

        $transient = get_transient($cache_key);
        if ($transient !== false) {
            self::$static_cache[$cache_key] = $transient;
            return $transient;
        }

        $instance = new self($args);
        return $instance->init();
    }
}

/**
 * ExternalImage — Timber\Image arayüzünü taklit eden hafif wrapper.
 *
 * @version 1.0.0
 * @since   1.0.7 (class.image.php)
 *
 * @changelog
 *   1.0.0 - 2026-05-18
 *     - Add: Initial release — external URL'ler için Timber\Image interface'i
 *
 * Picsum, CDN, vs. gibi external URL'ler için kullanılır.
 * srcset, width, height gibi metodlar boş/0 döner — sadece src() çalışır.
 *
 * How to use:
 *   // Otomatik — get_image_set() external URL algılarsa kullanır
 *   get_image_set(['src' => 'https://picsum.photos/800/600']);
 *
 *   // Manuel
 *   $img = new ExternalImage('https://picsum.photos/800/600');
 *   echo $img->src(); // https://picsum.photos/800/600
 */
class ExternalImage {
    private string $url;

    public function __construct(string $url) {
        $this->url = $url;
    }

    public function src($size = 'full'): string { return $this->url; }
    public function width(): int                { return 0; }
    public function height(): int               { return 0; }
    public function alt(): string               { return ''; }
    public function srcset($size = null): string { return ''; }
    public function get_aspect_ratio(): string  { return ''; }
    public function meta($key = ''): string     { return ''; }

    // Timber\Image compat — focal point
    public function get_focal_point_class(): string { return ''; }
}

} // namespace SaltHareket

/**
 * AUTO-INVALIDATION
 * - Görsel silindiğinde → cache patlar
 * - Görsel güncellendiğinde (replace, crop, metadata) → cache patlar
 */
if (!function_exists('_sh_img_invalidate_cache')) {
    function _sh_img_invalidate_cache($post_id) {
        if (get_post_type($post_id) !== 'attachment') return;
        $all_rels = get_option('sh_img_rels', []);
        if (isset($all_rels[$post_id]) && is_array($all_rels[$post_id])) {
            foreach ($all_rels[$post_id] as $hash) {
                delete_transient($hash);
            }
            // Silme işleminde mapping'i de kaldır, güncelleme'de koru (yeniden oluşturulacak)
            if (current_action() === 'delete_attachment') {
                unset($all_rels[$post_id]);
                update_option('sh_img_rels', $all_rels, false);
            }
        }
        \SaltHareket\Image::clearStaticCache();
    }
}
add_action('delete_attachment', '_sh_img_invalidate_cache');
add_action('edit_attachment', '_sh_img_invalidate_cache');
add_action('wp_update_attachment_metadata', function($data, $post_id) {
    _sh_img_invalidate_cache($post_id);
    return $data;
}, 10, 2);