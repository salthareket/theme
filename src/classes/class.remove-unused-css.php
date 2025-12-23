<?php

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\RuleSet\DeclarationBlock;

class RemoveUnusedCss {
    private $html;
    private $css;
    private $css_temp = "";
    private $critical_css = false;
    private $output;
    private $root_variables_used = [];
    private $animations_used = [];
    private $css_structure = [
        "media_queries" => [],
        "root_variables" => [],
        "keyframes" => [],
        "supports" => [],
        "fonts" => [],
        "styles" => []
    ];
    public $white_list = [
        ".w-100",
        ".h-100",
        ".show",
        ".affix",
        ".header-hide",
        ".fixed-*",
        ".resizing",
        ".scroll-down",
        ".loading",
        ".loading-*",

        ".menu-dropdown-open",
        ".menu-open",
        ".menu-show-header",

        ".offcanvas-open",
        ".offcanvas-fullscreen-open",

        ".collapse",
        ".collapsing",
        ".collapsed",

        ".search-open",

        ".filter-open",

        ".in",
        ".open",
        ".active",
        ".close",
        ".showing",
        ".hiding",
        ".closing",

        ".loaded",
        ".error",
        ".initial",
        ".nav-equal",
        ".nav-equalized",
        
        // plugin classes
        ".lenis",
        ".lenis-*",
        ".dgwt-wcas-open",

        ".screen-reader-response",
        ".wpcf7-not-valid-tip"
    ];
    public $white_list_map = [];
    public $black_list = [
        '.dropdown-notifications',
    ];
    private $root_variable_whitelist = [
        '--bs-breakpoint-xs', 
        '--bs-breakpoint-sm', 
        '--bs-breakpoint-md', 
        '--bs-breakpoint-lg', 
        '--bs-breakpoint-xl', 
        '--bs-breakpoint-xxl', 
        '--bs-breakpoint-xxxl',
    ];

    private $acceptable_pseudo_classes = [
        ':not',
        ':checked', 
        ':link', 
        ':disabled', 
        ':enabled', 
        ':selected', 
        ':invalid', 
        ':hover', 
        ':visited', 
        ':root', 
        ':scope', 
        ':first-child', 
        ':last-child', 
        ':nth-child', 
        ':nth-last-child', 
        //':first-of-type', 
        //':last-of-type', 
        ':only-child', 
        ':only-of-type', 
        ':empty',
        ':before', 
        ':after',
        ':placeholder',
        '::placeholder'
    ];

    private string $twig_attr = 'data-template';
    private array  $twig_template_paths = [];   // Timber template paths (override edilebilir)
    private bool   $twig_scan_includes = true;   // {% include %} yakala, rekürsif tara
    private array  $twig_seen_templates = []; // 'magazalar/single-modal.twig' => true
    private array  $twig_locate_cache   = []; // 'magazalar/single-modal.twig' => '/abs/path/...'
    private array  $twig_approx_cache   = []; // '/abs/path/...' => '<div>...</div>'


    // lazy init için:
    private bool   $twig_paths_initialized = false;
    private array  $twig_options = []; // dışarıdan gelen opsiyonlar saklansın

    public $opts = [];

    /*
    opts:
        scope: css style'ları verilen tag, class, id vs içine alır. zB: #wrapper .d-flex{...}
        black_list: verilen tag, class, id vs yi css e dahil etmez.
        ignore_whitelist: ön tanımlı olan white list leri kaldırır.
    */

    public function __construct($html, $css, $output = "", $additional_whitelist = [], $critical_css = false, array $opts = []) {
        if (is_string($html)) {
            $this->html = is_file($html) ? file_get_contents($html) : $html;
            $this->html = HtmlDomParser::str_get_html($this->html);
        } elseif ($html instanceof HtmlDomParser) {
            // Eğer $html zaten HtmlDomParser nesnesiyse, direkt kullan
            $this->html = $html;
        } else {
            if(!$critical_css){
                throw new InvalidArgumentException("Invalid HTML input. Must be a string or HtmlDomParser object.");
            }
        }
        
        $this->css = is_file($css) ? file_get_contents($css) : $css;
        //$this->css = $this->check_and_flatten_css($css);
        $this->output = $output;

        $this->critical_css = $critical_css;

        $default_options = [
            'ignore_whitelist'      => false,
            'black_list'            => [],
            'ignore_root_variables' => false,
            'scope'                 => ''
        ];
        $opts = array_merge($default_options, $opts);
        $this->twig_options = $opts;

        if(isset($opts['ignore_whitelist']) && $opts['ignore_whitelist']){
            $this->white_list = [];
        }
        $this->white_list = array_merge($this->white_list, $additional_whitelist);
        
        // opsiyonlardan basit atamalar (path hariç)
        if (isset($opts['twig_attr']) && is_string($opts['twig_attr']) && $opts['twig_attr'] !== '') {
            $this->twig_attr = $opts['twig_attr'];
        }
        if (isset($opts['twig_scan_includes'])) {
            $this->twig_scan_includes = (bool)$opts['twig_scan_includes'];
        }

        if(isset($opts['black_list']) && $opts['black_list']){
            $this->black_list = array_merge($this->white_list, $opts['black_list']);
        }

        $this->opts = $opts;

        error_log('[RemoveUnusedCss] init | twig_attr=' . $this->twig_attr);
    }

    private function cleanDom($dom) {
        $removeTags = ['script', 'style', 'noscript', 'template', 'svg', 'canvas'];
        foreach ($removeTags as $tag) {
            foreach ($dom->find($tag) as $node) {
                $node->outertext = '';
            }
        }
        return $dom;
    }

    public function process() {
        $this->removeUnnecessaryLines();
        $this->removeComments();
        //$this->extractSupportsQueries();
        $this->extractMediaQueries();
        $this->extractRootVariables();
        $this->extractKeyframes();
        $this->extractFonts();
        $this->extractStyles();

        //error_log(print_r($this->css_structure["root_variables"], true));

        if(isset($this->opts["ignore_root_variables"]) && $this->opts["ignore_root_variables"]){
            if(isset($this->css_structure["root_variables"])){
                $this->css_structure["root_variables"] = [];
            }
        }
        
        $this->filterUsedCss();

        if(isset($this->opts["scope"]) && !empty($this->opts["scope"])){
            $this->css_temp = $this->scope_css($this->css_temp, $this->opts["scope"]);
        }

        $minify = new Minify\CSS($this->css_temp);
        $this->css_temp = $minify->minify();
        
        if(!empty($this->output)){
            file_put_contents($this->output, $this->css_temp);
        }else{
            if($this->critical_css){ // returns array
                return $this->extract_critical_css($this->html, $this->css_temp);
            }
            return $this->css_temp;
        }
    }

    private function isCriticalSelector($selector, array $usedSelectors): bool {
        foreach ($usedSelectors as $used) {
            $used = trim($used);
            // Direkt tam eşleşme
            if ($selector === $used) {
                return true;
            }

            // Nokta ile başlayan class eşleşmesi
            if (preg_match('/(^|\s|,)' . preg_quote($used, '/') . '($|\s|,|:)/', $selector)) {
                return true;
            }
        }
        return false;
    }
    /*private function cleanSelector($selector) {
        // Tüm pseudo-class ve element’leri uçur
        return preg_replace('/:(hover|focus|active|visited|checked|disabled|enabled|invalid|valid|target|focus-visible|focus-within|empty|selection|is\([^)]+\)|where\([^)]+\)|has\([^)]+\)|not\([^)]+\)|before|after)/', '', $selector);
    }*/
    /*private function cleanSelector($selector) {
        // Dinamik olarak kabul edilen pseudo’ları regex için hazırla
        $acceptable = array_map(function($p) {
            return preg_quote($p, '/');
        }, $this->acceptable_pseudo_classes);

        // Regex: : ile başlayanları yakala, eğer acceptable listesinde yoksa sil
        $selector = preg_replace_callback(
            '/:(?!' . implode('|', $acceptable) . ')([a-zA-Z0-9\(\)\-]+)/i',
            function($matches) {
                return ''; // acceptable değilse sil
            },
            $selector
        );

        return $selector;
    }*/
    private function cleanSelector($selector) {
        $parts = preg_split('/\s*,\s*/', $selector);
        $parts = array_map(function($sel) {
            $acceptable = array_map(function($p) {
                return preg_quote($p, '/');
            }, $this->acceptable_pseudo_classes);

            return preg_replace_callback(
                '/:(?!' . implode('|', $acceptable) . ')([a-zA-Z0-9\(\)\-]+)/i',
                function($matches) {
                    return '';
                },
                trim($sel)
            );
        }, $parts);

        return implode(', ', $parts);
    }

    /*public function generate_critical_css(array $selectors) {
        error_log(print_r($selectors, true));
        $this->removeUnnecessaryLines();
        $this->removeComments();
        $this->extractMediaQueries();
        $this->extractRootVariables();
        $this->extractKeyframes();
        $this->extractFonts();
        $this->extractStyles();

        $criticalCSS = "";

        foreach ($this->css_structure["styles"] as $style) {
            $cleaned_selectors = array_map([$this, 'cleanSelector'], $style["selectors"]);
            foreach ($cleaned_selectors as $selector) {
                if ($this->isCriticalSelector($selector, $selectors)) {
                    $criticalCSS .= implode(", ", $cleaned_selectors) . " { " . $style["code"] . " }";
                    $this->trackUsedItems($style["code"]);
                    break;
                }
            }
        }

        foreach ($this->css_structure["media_queries"] as $media => $rules) {
            $media_block = "";
            foreach ($rules as $selector => $rule) {
                $cleaned = $this->cleanSelector($selector);
                if ($this->isCriticalSelector($cleaned, $selectors)) {
                    $media_block .= "$cleaned { $rule }";
                }
            }
            if (!empty($media_block)) {
                $criticalCSS .= "$media {\n{$media_block}\n}\n";
            }
        }

        // Overlay/Modal gibi yapıları sakla
        $criticalCSS .= "body.offcanvas-open, .offcanvas, .modal, .bootbox, .backdrop, .overlay { display: none !important; }";

        // Görsel efekt override'ları
        $criticalCSS .= 'img, picture, source {
            opacity: 1 !important;
            mix-blend-mode: normal !important;
            filter: none !important;
        }
        img[fetchpriority="high"] {
          contain: paint;
          content-visibility: auto;
          will-change: transform;
        }';

        $common_css = STATIC_PATH . "css/common.css";
        if(file_exists($common_css)){
            $common_css = file_get_contents($common_css);
            $criticalCSS = $common_css . $criticalCSS;
        }

        $root_css = STATIC_PATH . "css/root.css";
        if(file_exists($root_css)){
            $root_css = file_get_contents($root_css);
            $criticalCSS = $root_css . $criticalCSS;
        }

        //$this->processRootVariables();
        //$criticalCSS = $this->css_temp . $criticalCSS;

        // Minify
        $minifier = new Minify\CSS($criticalCSS);
        $criticalCSS = $minifier->minify();
        $criticalCSS = str_replace("../", "../../", $criticalCSS);

        if (substr($criticalCSS, 0, 1) === ',') {
            $criticalCSS = substr($criticalCSS, 1);
        }

        if (!empty($this->output)) {
            file_put_contents($this->output, $criticalCSS);
        } else {
            return $criticalCSS;
        }
    }*/

    // class RemoveUnusedCss'in içinde
    public function generate_critical_css(array $selectors) {
        
        // 1. Gelen Seçiciler listesine White List'i EKLE (Header koruması için)
        $all_used_selectors = array_merge($selectors, $this->white_list);
        
        // Tüm seçicileri temizle ve benzersiz hale getir
        $cleaned_used_selectors = [];
        foreach ($all_used_selectors as $sel) {
            if (str_contains($sel, '*')) {
                $cleaned_used_selectors[] = $sel; // Wildcard'ı olduğu gibi tut
            } else {
                // cleanSelector() metodu var sayılıyor
                $cleaned_used_selectors[] = $this->cleanSelector($sel);
            }
        }
        $all_used_selectors = array_unique(array_filter($cleaned_used_selectors));

        // 2. TÜM GEREKLİ CSS'İ TEK BİR DİZEDE TOPLA
        // Bu, common.css ve root.css'in de ayrıştırma işlemine dahil edilmesini sağlar.
        $full_css_input = $this->css; // Başlangıçta gelen main-combined.css içeriği

        $common_css_path = STATIC_PATH . "css/common.css";
        if(file_exists($common_css_path)){
            $full_css_input .= "\n" . file_get_contents($common_css_path);
        }

        $root_css_path = STATIC_PATH . "css/root.css";
        if(file_exists($root_css_path)){
            $full_css_input .= "\n" . file_get_contents($root_css_path);
        }
        
        // 3. CSS Ayrıştırma için $this->css özelliğini güncelle
        $this->css = $full_css_input;

        // 4. Ayrıştırma ve Yapılandırma
        $this->removeUnnecessaryLines();
        $this->removeComments();
        $this->extractMediaQueries();
        $this->extractRootVariables();
        $this->extractKeyframes();
        $this->extractFonts();
        $this->extractStyles(); // Tüm CSS içeriği işlenir

        $criticalCSS = "";

        // 5. STİL AYIKLAMA (Normal CSS Kuralları)
        foreach ($this->css_structure["styles"] as $style) {
            $selectors_to_check = $style["selectors"];
            
            // SADECE TAM EŞLEŞME VEYA WILDCARD EŞLEŞMESİ KULLANILIYOR (Boyut küçültme)
            if ($this->shouldKeepStyleBlock($selectors_to_check, $all_used_selectors)) {
                $criticalCSS .= implode(", ", $selectors_to_check) . " { " . $style["code"] . " }";
                $this->trackUsedItems($style["code"]);
            }
        }

        // 6. MEDYA SORGUSU AYIKLAMA
        foreach ($this->css_structure["media_queries"] as $media => $rules) {
            $media_block = "";
            foreach ($rules as $selector_full => $rule) {
                
                // split_selectors_respecting_brackets() metodu var sayılıyor
                $selectors_array = $this->split_selectors_respecting_brackets($selector_full);
                
                if ($this->shouldKeepStyleBlock($selectors_array, $all_used_selectors)) {
                    $media_block .= "$selector_full { $rule }";
                }
            }
            
            if (!empty($media_block)) {
                $criticalCSS .= "$media {\n{$media_block}\n}\n";
            }
        }
        
        // 7. SABİT KURALLARI EKLE (Bu kısım en sonda kalmalı ve filtresizdir)
        
        // Overlay/Modal gibi yapıları sakla
        $criticalCSS .= "body.offcanvas-open, .offcanvas, .modal, .bootbox, .backdrop, .overlay { display: none !important; }";

        // Görsel efekt override'ları
        $criticalCSS .= 'img, picture, source {
            opacity: 1 !important;
            mix-blend-mode: normal !important;
            filter: none !important;
        }
        img[fetchpriority="high"] {
            contain: paint;
            content-visibility: auto;
            will-change: transform;
        }';

        // Minify
        $minifier = new Minify\CSS($criticalCSS);
        $criticalCSS = $minifier->minify();
        // Minify kütüphanesinin dizin düzeltmesi yapılıyor
        $criticalCSS = str_replace("../", "../../", $criticalCSS); 

        if (substr($criticalCSS, 0, 1) === ',') {
            $criticalCSS = substr($criticalCSS, 1);
        }

        if (!empty($this->output)) {
            file_put_contents($this->output, $criticalCSS);
        } else {
            return $criticalCSS;
        }
    }
    protected function shouldKeepStyleBlock(array $selectors_to_check, array $all_used_selectors): bool {
        
        foreach ($selectors_to_check as $css_selector) {
            $cleaned_css_selector = $this->cleanSelector($css_selector);

            // 1. TAM EŞLEŞME
            if (in_array($cleaned_css_selector, $all_used_selectors)) {
                return true;
            }

            // 2. Seçicinin Temel Bileşenlerini Çıkar (ID, Class, Tag)
            // Yalnızca [.#][a-zA-Z0-9_-]+ (Class/ID) ve [a-zA-Z0-9_-]+ (Tag) yakalanır.
            preg_match_all('/([.#][a-zA-Z0-9_-]+|[a-zA-Z0-9_-]+)/', $css_selector, $component_matches);
            $css_components = array_unique(array_filter($component_matches[1]));

            // --- A. Template/Ana Sınıf Reddi Kontrolü (.innovation-hub sorunu) ---
            $first_selector = null;
            foreach ($css_components as $comp) {
                // İlk ana Class veya ID'yi bul
                if (str_starts_with($comp, '.') || str_starts_with($comp, '#')) {
                    $first_selector = $comp;
                    break;
                }
            }
            
            // Kural, sayfada KULLANILMAYAN bir ana sınıf/ID ile başlıyorsa, REDDET
            if ($first_selector && !in_array($this->cleanSelector($first_selector), $all_used_selectors)) {
                continue; 
            }
            
            // --- B. Bileşen Eşleşmesi Kontrolü (.row, .align-items-center sorunu) ---
            // Kuralın herhangi bir bileşeni, AJAX listesindeki bir seçiciyle eşleşiyor mu?
            foreach ($css_components as $component) {
                $cleaned_component = $this->cleanSelector($component);

                // a) Tam Eşleşme (e.g., .row)
                if (in_array($cleaned_component, $all_used_selectors)) {
                    return true; 
                }
                
                // b) Wildcard Eşleşmesi (e.g., .col-* )
                foreach ($all_used_selectors as $used_sel) {
                    if (str_contains($used_sel, '*')) {
                        $pattern = '/^' . str_replace('*', '.*?', preg_quote($used_sel, '/')) . '$/i';
                        if (preg_match($pattern, $cleaned_component)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }





    private function detectTimberTemplatePaths(): array {
        $paths = [];
        $checked = [];

        // 1) Child/Parent theme default "templates"
        if (function_exists('get_stylesheet_directory')) {
            $p = trailingslashit(get_stylesheet_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }
        if (
            function_exists('get_template_directory') &&
            function_exists('get_stylesheet_directory') &&
            get_template_directory() !== get_stylesheet_directory()
        ) {
            $p = trailingslashit(get_template_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }

        // 2) Timber::$locations (Timber 2)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$locations)) {
            foreach (\Timber\Timber::$locations as $loc) {
                $abs = rtrim($loc, '/\\');
                $checked[] = $abs;
                if (is_dir($abs)) { $paths[] = $abs; }
            }
        }

        // 3) Timber::$dirname (legacy / hala yaygın)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$dirname)) {
            $dirnames = (array) \Timber\Timber::$dirname;
            $resolved = $this->resolveTimberDirnamesToAbsolute($dirnames, $checked);
            $paths = array_merge($paths, $resolved);
        }

        // Temizle + logla
        $paths = array_values(array_unique(array_filter($paths, 'is_dir')));
        error_log('[RemoveUnusedCss] detectTimberTemplatePaths checked=' . json_encode($checked, JSON_UNESCAPED_SLASHES));
        error_log('[RemoveUnusedCss] detectTimberTemplatePaths final='   . json_encode($paths,   JSON_UNESCAPED_SLASHES));

        return $paths;
    }
    private function ensureTwigPaths(): void {
        if ($this->twig_paths_initialized) return;

        // 1) dışarıdan geldiyse önce onu kullan
        if (!empty($this->twig_options['twig_paths']) && is_array($this->twig_options['twig_paths'])) {
            $paths = array_values(array_filter($this->twig_options['twig_paths'], 'is_string'));
            $paths = array_values(array_filter($paths, 'is_dir'));
            $this->twig_template_paths = $paths;
            $this->twig_paths_initialized = true;
        } else {
            // 2) otomatik tespit (bir kez)
            $this->twig_template_paths = $this->detectTimberTemplatePaths();
            $this->twig_paths_initialized = true;
        }

        error_log('[RemoveUnusedCss] twig_paths (lazy)=' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
    }
    /**
     * Timber::$dirname içine verilen relative (veya absolute) yolları
     * çeşitli köklere göre mutlak hale getirir.
     */
    private function resolveTimberDirnamesToAbsolute(array $dirnames, array &$checked): array {
        $roots = [];

        // Child & parent theme kökleri
        if (function_exists('get_stylesheet_directory')) {
            $roots[] = trailingslashit(get_stylesheet_directory());
        }
        if (function_exists('get_template_directory')) {
            $roots[] = trailingslashit(get_template_directory());
        }

        // wp-content ve ABSPATH de dene (vendor/... gibi)
        if (defined('WP_CONTENT_DIR')) {
            $roots[] = trailingslashit(WP_CONTENT_DIR);
        }
        if (defined('ABSPATH')) {
            $roots[] = trailingslashit(ABSPATH);
        }

        // Bu sınıfın bulunduğu plugin/theme kökü (vendor senaryosu için iş görür)
        if (defined('__DIR__')) {
            $roots[] = trailingslashit(dirname(__DIR__)); // sınıfa göre 1 seviye yukarı
            $roots[] = trailingslashit(__DIR__);          // bulunduğu klasör
        }

        $out = [];
        foreach ($dirnames as $d) {
            if (!$d) { continue; }
            // Absolute geldiyse direkt dene
            if ($d[0] === '/' || preg_match('#^[A-Z]:[\\\\/]#i', $d)) {
                $candidate = rtrim($d, '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                    continue;
                }
            }

            // Relative ise her root’a ekle
            foreach ($roots as $root) {
                $candidate = rtrim($root . ltrim($d, '/\\'), '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                }
            }
        }

        // uniq
        $out = array_values(array_unique($out));
        return $out;
    }
    private function locateTwig(string $template): ?string {
        $this->ensureTwigPaths(); // path’lar yoksa şimdi tespit et

        $tpl = ltrim($template, '/\\');
        if (!str_ends_with($tpl, '.twig')) {
            $tpl .= '.twig';
        }
        if (array_key_exists($tpl, $this->twig_locate_cache)) {
            return $this->twig_locate_cache[$tpl] ?: null;
        }
        foreach ($this->twig_template_paths as $base) {
            $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $tpl;
            if (is_file($path)) {
                $this->twig_locate_cache[$tpl] = $path;
                return $path;
            }
        }
        $this->twig_locate_cache[$tpl] = null;
        return null;
    }
    private function collectIncludedTwigRaw(string $raw, string $current_dir, array &$visited = []): string {
        $out = '';
        if (preg_match_all('/\{\%\s*include\s*[\'"]([^\'"]+)[\'"]\s*(?:with\s+[^%]+)?\%\}/', $raw, $m)) {
            foreach ($m[1] as $inc) {
                $candidates = [];
                if (strpos($inc, '/') === 0) {
                    $candidates[] = $this->locateTwig(ltrim($inc, '/'));
                } else {
                    $rel = $current_dir . DIRECTORY_SEPARATOR . $inc;
                    $candidates[] = is_file($rel) ? $rel : null;
                    $candidates[] = $this->locateTwig($inc);
                }
                $found = false;
                foreach (array_filter($candidates) as $file) {
                    if (!isset($visited[$file]) && is_readable($file)) {
                        $visited[$file] = true;
                        $sub = file_get_contents($file);
                        if ($sub !== false) {
                            $out .= "\n" . $sub;
                            $found = true;
                            error_log('[RemoveUnusedCss] include resolved: ' . $inc . ' -> ' . $file);
                            // rekürsif
                            $out .= "\n" . $this->collectIncludedTwigRaw($sub, dirname($file), $visited);
                        } else {
                            error_log('[RemoveUnusedCss] include read fail: ' . $file);
                        }
                    }
                }
                if (!$found) {
                    error_log('[RemoveUnusedCss] include NOT found: ' . $inc);
                }
            }
        }
        return $out;
    }
    private function logApproxHtmlSelectors(string $html, string $label = ''): void {
        $classes = [];
        $ids     = [];

        $frag = HtmlDomParser::str_get_html($html);
        if (!$frag) {
            error_log('[RemoveUnusedCss] logApproxHtmlSelectors: failed to parse approx html for ' . $label);
            return;
        }

        foreach ($frag->find('*') as $el) {
            // class
            $cls = $el->getAttribute('class');
            if ($cls) {
                foreach (preg_split('/\s+/', trim($cls)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
            // id
            $id = $el->getAttribute('id');
            if ($id) {
                $ids[$id] = true;
            }
        }

        $classes = array_keys($classes);
        $ids     = array_keys($ids);

        // Çok uzun olmasın diye ilk 30 tanesini gösterelim
        $sampleClasses = array_slice($classes, 0, 30);
        $sampleIds     = array_slice($ids, 0, 30);

        error_log(sprintf('[RemoveUnusedCss] selectors from %s | classes=%d ids=%d', $label, count($classes), count($ids)));
        error_log('[RemoveUnusedCss] classes sample: ' . implode(', ', $sampleClasses));
        error_log('[RemoveUnusedCss] ids sample: ' . implode(', ', $sampleIds));
    }
    private function twigToApproxHtml(string $twig): string {
        $s = $twig;

        // 1) Twig yorumları {# ... #}
        $s = preg_replace('/\{\#.*?\#\}/s', '', $s);

        // 2) Twig control blokları {% ... %} → tamamen sil
        $s = preg_replace('/\{\%.*?\%\}/s', '', $s);

        // 3) Twig değişkenleri {{ ... }} → boşalt (bazı yerlerde class attribute içinde olabilir)
        //   class="{{ something }}" → class="" kalsın
        $s = preg_replace('/\{\{.*?\}\}/s', '', $s);

        // 4) Bozuk kalan attribute/etiket kapanışlarını biraz toparla
        //   (Bu approx; HtmlDomParser çoğu durumda yine parse edebiliyor.)
        //   Fazla boşlukları azalt
        $s = preg_replace('/\s+/', ' ', $s);

        // 5) Twig include kalıntıları vs yok
        $s = trim($s);

        // Artık bu string, DOM’a gömülüp selector taramasında kullanılabilir
        return $s;
    }
    private function collectTwigLoadedHtml(\voku\helper\HtmlDomParser $dom): string {
        $nodes = $dom->find("*[{$this->twig_attr}]");
        if (!$nodes || count($nodes) === 0) {
            error_log('[RemoveUnusedCss] data-template: node bulunamadı');
            return '';
        }

        $this->ensureTwigPaths();

        // 1) DOM’daki TÜM data değerlerini topla ve normalize et
        $uniqueTemplates = [];
        foreach ($nodes as $node) {
            $raw = trim((string) $node->getAttribute($this->twig_attr));
            if ($raw === '') { continue; }
            $parts = preg_split('/[,;]+/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (!str_ends_with($p, '.twig')) $p .= '.twig';
                $uniqueTemplates[$p] = true;
            }
        }

        $all = array_keys($uniqueTemplates);
        if (empty($all)) {
            error_log('[RemoveUnusedCss] data-template: template değeri yok (boş)');
            return '';
        }

        // 2) Daha önce işlenmişleri at
        $toProcess = [];
        foreach ($all as $tpl) {
            if (!isset($this->twig_seen_templates[$tpl])) {
                $this->twig_seen_templates[$tpl] = true;
                $toProcess[] = $tpl;
            }
        }

        if (empty($toProcess)) {
            error_log('[RemoveUnusedCss] data-template: tüm template değerleri önceden işlenmiş, atlandı. uniq=' . count($all));
            return '';
        }

        error_log('[RemoveUnusedCss] data-template: uniq=' . count($all) . ', yeni_islenecek=' . count($toProcess));
        $html_chunks = [];
        $foundFiles  = [];
        $missed      = [];

        // 3) Her bir uniq template için bir kez çalış
        foreach ($toProcess as $tpl) {
            $file = $this->locateTwig($tpl);
            if (!$file || !is_readable($file)) {
                $missed[] = $tpl;
                continue;
            }

            $foundFiles[] = $file;

            // 4) Dosya approx-HTML cache’i
            if (isset($this->twig_approx_cache[$file])) {
                $html_chunks[] = $this->twig_approx_cache[$file];
                continue;
            }

            $raw = file_get_contents($file);
            if ($raw === false) { continue; }

            // include’ları çöz (opsiyonel)
            if ($this->twig_scan_includes) {
                $raw .= "\n" . $this->collectIncludedTwigRaw($raw, dirname($file));
            }

            $approx_html = $this->twigToApproxHtml($raw);
            $this->twig_approx_cache[$file] = $approx_html ?: '';

            if ($approx_html) {
                $html_chunks[] = $approx_html;
            }
        }

        // 5) Log
        if ($foundFiles) {
            error_log('[RemoveUnusedCss] Twig bulundu: ' . count($foundFiles));
            foreach ($foundFiles as $f) {
                error_log('[RemoveUnusedCss]  - ' . $f);
            }
        }
        if ($missed) {
            error_log('[RemoveUnusedCss] Twig bulunamadı: ' . json_encode($missed, JSON_UNESCAPED_SLASHES));
            error_log('[RemoveUnusedCss]  aranan_yollar: ' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
        }

        // Örnek: basit sınıf/ID istatistiği (yaklaşık)
        $summary = strip_tags(implode(' ', $html_chunks));
        preg_match_all('/class="([^"]+)"/', $summary, $m1);
        preg_match_all('/id="([^"]+)"/', $summary, $m2);
        $classes = [];
        if (!empty($m1[1])) {
            foreach ($m1[1] as $cstr) {
                foreach (preg_split('/\s+/', trim($cstr)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
        }
        $ids = array_unique($m2[1] ?? []);
        error_log('[RemoveUnusedCss] approx selectors: classes=' . count($classes) . ' ids=' . count($ids));

        return implode("\n", $html_chunks);
    }




    private function removeUnnecessaryLines() {
        $this->css = preg_replace('/@charset[^;]+;/', '', $this->css);
    }
    private function removeComments() {
        $this->css = preg_replace('/\/\*.*?\*\//s', '', $this->css);
    }


    private function extractRootVariables() {
        $pattern = '/([^{]+){([^}]*)}/s';
        preg_match_all($pattern, $this->css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', $match[1]));
            $body = $match[2];
            $process_block = false;
            foreach ($selectors as $sel) {
                if (preg_match('/^(:root|:host)\b/i', $sel)) {
                    $process_block = true;
                    break;
                }
            }

            if (!$process_block) continue;

            preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/s', $body, $vars, PREG_SET_ORDER);
            foreach ($vars as $var) {
                $this->css_structure["root_variables"][ trim($var[1]) ] = trim($var[2]);
            }
            $this->css = str_replace($match[0], '', $this->css);
        }
        //error_log("[RemoveUnusedCss] extractRootVariables();");
        //error_log(print_r($this->css_structure["root_variables"], true));
    }
    private function extractMediaQueries() {
        preg_match_all('/(@media[^{]+){((?:[^{}]+|{(?:[^{}]+|{[^{}]*})*})*)}/s', $this->css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $media_query = trim($match[1]);
            $styles = $match[2];
            preg_match_all('/([^{}]+){([^}]+)}/s', $styles, $style_matches, PREG_SET_ORDER);
            foreach ($style_matches as $style_match) {
                $selector = trim($style_match[1]);
                $rules = trim($style_match[2]);
                
                if (!isset($this->css_structure["media_queries"][$media_query])) {
                    $this->css_structure["media_queries"][$media_query] = [];
                }
                
                if (!isset($this->css_structure["media_queries"][$media_query][$selector])) {
                    $this->css_structure["media_queries"][$media_query][$selector] = $rules;
                } else {
                    $this->css_structure["media_queries"][$media_query][$selector] .= "; " . $rules;
                }
                
            }
            $this->css = str_replace($match[0], '', $this->css);
        }
    }
    private function extractKeyframes() {
        preg_match_all('/(@(?:-webkit-|-moz-|-o-|-ms-)?keyframes)\s+([\w-]+)\s*{((?:[^{}]+|{[^{}]*})*)}/s', $this->css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $keyframe_type = trim($match[1]);
            $keyframe_name = trim($match[2]);
            $content = trim($match[3]);
            
            if (!isset($this->css_structure["keyframes"][$keyframe_type])) {
                $this->css_structure["keyframes"][$keyframe_type] = [];
            }
            
            $this->css_structure["keyframes"][$keyframe_type][$keyframe_name] = $content;
            $this->css = str_replace($match[0], '', $this->css);
        }
    }
    private function extractFonts() {
        preg_match_all('/@font-face\s*{([^}]+)}/s', $this->css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $font_content = trim($match[1]);
            $this->css_structure["fonts"][] = $font_content;
            $this->css = str_replace($match[0], '', $this->css);
        }
    }
    private function extractStyles() {
        preg_match_all('/([^{}]+){([^}]+)}/s', $this->css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', $match[1]));
            $rules = trim($match[2]);
            
            $this->css_structure["styles"][] = [
                "selectors" => $selectors,
                "code" => $rules
            ];
            $this->css = str_replace($match[0], '', $this->css);
        }
    }

    private function filterUsedCss() {
        $dom = $this->html;
        if (!$dom) {
            error_log("Error parsing HTML");
            return;
        }

        // data-template taraması
        $extra_html = $this->collectTwigLoadedHtml($dom);
        if ($extra_html) {
            // Eklenen fragmenti logla
            error_log('[RemoveUnusedCss] collected extra html length=' . strlen($extra_html));

            // NOT: innertext string kabul eder; doğrudan $extra_html kullanıyoruz
            $bodyNode = $dom->find('body', 0);
            if ($bodyNode) {
                $bodyNode->innertext .= '<div id="__twig_extra__">' . $extra_html . '</div>';
                error_log('[RemoveUnusedCss] extra html appended into <body>');
            } else {
                $dom->innertext .= '<div id="__twig_extra__">' . $extra_html . '</div>';
                error_log('[RemoveUnusedCss] extra html appended into root (no body)');
            }
        } else {
            error_log('[RemoveUnusedCss] no extra twig html collected');
        }
        
        $this->addHtmlWhitelist($dom);

        //$dom = $this->cleanDom($dom);
        $this->updateWhiteList($dom);
        //error_log(print_r($this->white_list, true));
        $this->processFonts();
        $this->processStyles($dom);
        $this->checkMediaQueries($dom);
        //$this->processSupports();
        $this->processKeyframes();
        $this->processRootVariables();
    }

    private function processFonts() {
        foreach ($this->css_structure["fonts"] as $font_content) {
            $this->css_temp .= "@font-face {\n" . trim($font_content) . "\n}\n";
            $this->trackUsedItems($font_content);
        }
    }

    private function checkMediaQueries($dom) {
        // 1) Sırala (küçükten büyüğe)
        $mqItems = [];
        foreach ($this->css_structure["media_queries"] as $mq => $rules) {
            $mqItems[] = ['meta' => $this->parseMediaForSort($mq), 'rules' => $rules];
        }
        usort($mqItems, function($a, $b) {
            if ($a['meta']['val'] < $b['meta']['val']) return -1;
            if ($a['meta']['val'] > $b['meta']['val']) return 1;
            $ra = $this->mediaTypeRank($a['meta']['type']);
            $rb = $this->mediaTypeRank($b['meta']['type']);
            if ($ra !== $rb) return $ra <=> $rb;
            return strcmp($a['meta']['q'], $b['meta']['q']);
        });

        // 2) SIRALI listedeki her media'yı işle
        foreach ($mqItems as $item) {
            $media_query    = $item['meta']['q'];
            $rules_in_media = $item['rules'];

            $final_media_css_block = '';

            // Media içindeki her selector grubunu filtrele
            foreach ($rules_in_media as $selector_group => $style_code) {
                $all_selectors_in_group = array_map('trim', explode(',', $selector_group));
                $keptSelectors = [];

                foreach ($all_selectors_in_group as $individual_selector) {
                    if ($individual_selector === '') continue;

                    $root_selector = $this->getRootSelector($individual_selector);

                    if ($root_selector === null) {
                        if ($this->selectorExists($dom, $individual_selector)) {
                            $keptSelectors[] = $individual_selector;
                        }
                        continue;
                    }

                    if ($this->selectorExists($dom, $root_selector) || $this->isWhitelisted($dom, $root_selector)) {
                        $keptSelectors[] = $individual_selector;
                    }
                }

                if (!empty($keptSelectors)) {
                    // blacklist temizliği
                    $finalKeptSelectors = [];
                    foreach ($keptSelectors as $s) {
                        if (!$this->isBlacklisted($s)) {
                            $finalKeptSelectors[] = $s;
                        }
                    }
                    if (!empty($finalKeptSelectors)) {
                        $final_media_css_block .= implode(", ", $finalKeptSelectors) . " { " . $style_code . " }\n";
                    }
                }
            }

            if ($final_media_css_block !== '') {
                $this->css_temp .= "$media_query {\n" . $final_media_css_block . "}\n";
                $this->trackUsedItems($final_media_css_block);
            }
        }
    }
    private function normalizeMediaQuery(string $q): string {
        $q = preg_replace('/\s+/', ' ', trim($q));
        $q = preg_replace('/\(\s*/', '(', $q);
        $q = preg_replace('/\s*\)/', ')', $q);
        $q = preg_replace('/\s*:\s*/', ':', $q);
        $q = preg_replace('/\s*and\s*/i', ' and ', $q);
        return $q;
    }
    private function parseMediaForSort(string $q): array {
        $qNorm = $this->normalizeMediaQuery($q);
        $hasMin = preg_match('/min-width\s*:\s*([0-9.]+)\s*px/i', $qNorm, $mMin);
        $hasMax = preg_match('/max-width\s*:\s*([0-9.]+)\s*px/i', $qNorm, $mMax);

        if ($hasMin && $hasMax) {
            return ['type' => 'range', 'val' => (float)$mMin[1], 'q' => $q];
        }
        if ($hasMin) {
            return ['type' => 'min', 'val' => (float)$mMin[1], 'q' => $q];
        }
        if ($hasMax) {
            return ['type' => 'max', 'val' => (float)$mMax[1], 'q' => $q];
        }
        // unknown → en sona
        return ['type' => 'other', 'val' => INF, 'q' => $q];
    }
    private function mediaTypeRank(string $t): int {
        // aynı değerde öncelik: max → range → min → other
        return match ($t) {
            'max' => 0,
            'range' => 1,
            'min' => 2,
            default => 3,
        };
    }

    private function processKeyframes() {
        foreach ($this->css_structure["keyframes"] as $keyframe_type => $keyframes) {
            foreach ($keyframes as $keyframe_name => $keyframe_content) {
                // Sadece kullanılan animasyonlar eklenecek
                if (in_array($keyframe_name, $this->animations_used)) {
                    $this->css_temp .= "$keyframe_type $keyframe_name {\n$keyframe_content\n}\n";
                }
            }
        }
    }

    private function getRootSelector($selector) {
        // Selectörün başındaki boşlukları temizle
        $selector = ltrim($selector);

        // Pseudo-elements için mevcut özel durum:
        if (preg_match('/^::?(before|after)\b/i', $selector)) {
            return '*';
        }

        // ✅ Universal selector'u kök olarak kabul et
        if ($selector === '*' || strpos($selector, '*>') === 0 || strpos($selector, '* ') === 0 || strpos($selector, '*:') === 0 || strpos($selector, '*.') === 0 || strpos($selector, '*#') === 0) {
            return '*';
        }
        
        // Kök selectörü yakalamak için regex.
        // Bir etiket adı, id, class veya attribute selector ile başlayabilir.
        preg_match('/^([\w\-]+|[\.#][\w\-]+|\[[^\]]+\])/', $selector, $matches);
        
        return $matches[1] ?? null;
    }

    /**
     * CSS stillerini, "kök selectör" mantığına göre işler.
     */
    private function processStyles($dom) {
        foreach ($this->css_structure["styles"] as $style) {
            $all_selectors_in_group = $style["selectors"];
            $keptSelectors = [];

            // Bir kural grubundaki her bir selectörü (virgülle ayrılmış) tek tek kontrol et
            foreach ($all_selectors_in_group as $individual_selector) {
                
                if (empty(trim($individual_selector))) {
                    continue;
                }
                
                if (preg_match('/^::?(before|after)\b/i', $individual_selector)) {
                    $keptSelectors[] = $individual_selector;
                    continue;
                }

                $root_selector = $this->getRootSelector($individual_selector);

                // Eğer bir kök selectör bulunamadıysa (örn: *>p), risk alma, koru.
                if ($root_selector === null) {
                    if ($this->selectorExists($dom, $individual_selector)) {
                         $keptSelectors[] = $individual_selector;
                    }
                    continue;
                }

                // KURAL: Kök selectör HTML'de varsa veya whitelist'te ise,
                // o zaman bu selectör parçasını koru.
                if ($this->selectorExists($dom, $root_selector) || $this->isWhitelisted($dom, $root_selector)) {
                    $keptSelectors[] = $individual_selector;
                }
            }

            // Eğer korunan selectörlerden en az biri varsa, kuralı yeni selectör listesiyle yaz.
            if (!empty($keptSelectors)) {
                // Blacklist kontrolü son aşamada yapılır.
                $finalKeptSelectors = [];
                foreach($keptSelectors as $s) {
                    if (!$this->isBlacklisted($s)) {
                        $finalKeptSelectors[] = $s;
                    }
                }
                
                if(!empty($finalKeptSelectors)) {
                    $this->css_temp .= implode(", ", $finalKeptSelectors) . " { " . $style["code"] . " }\n";
                    $this->trackUsedItems($style["code"]);
                }
            }
        }
    }

    private function processRootVariables() {

        // GÜVENLİK KONTROLÜ: Dizi olduğundan emin ol.
        if (!is_array($this->css_structure["root_variables"])) {
            $this->css_structure["root_variables"] = [];
        }
        if (!is_array($this->root_variables_used)) {
            $this->root_variables_used = [];
        }

        $vars_temp = "";

        $used_and_whitelisted = array_unique(array_merge(
            $this->root_variables_used, 
            $this->root_variable_whitelist
        ));

        foreach ($this->css_structure["root_variables"] as $var_name => $value) {
            if (in_array($var_name, $used_and_whitelisted)) {
               $vars_temp .= "$var_name: $value;\n";
            }
        }
        if(!empty($vars_temp)){
            $vars_temp = ":root{\n" . $vars_temp . "}\n";
            $this->css_temp = $vars_temp . $this->css_temp;
        }
    }

    private function trackUsedItems($content) {
        // Root variables'ları takip et
        preg_match_all('/var\((--[^),]+)\)/', $content, $var_matches);
        foreach ($var_matches[1] as $var_name) {
            $this->root_variables_used[] = trim($var_name);
        }

        // Animation name'leri takip et
        preg_match_all('/animation:\s*([\w-]+)/', $content, $anim_matches);
        foreach ($anim_matches[1] as $anim_name) {
            $this->animations_used[] = trim($anim_name);
        }

        // Tekrarları önle
        $this->root_variables_used = array_values(array_unique($this->root_variables_used));
        $this->animations_used = array_values(array_unique($this->animations_used));
    }



    private function selectorExists($dom, $selector) {
        $selector = trim($selector);
        if (empty($selector)) return false;

        // ✅ '*' da geçerli başlangıç karakteri olsun
        if (preg_match('/^[a-zA-Z.#:\[\*]/', $selector) === 0) {
            error_log('[RemoveUnusedCss] Skipping invalid selector: ' . $selector);
            return false;
        }

        // ✅ Universal selector'u her zaman mevcut say
        if ($selector === '*' || preg_match('/(^|,\s*)\*(\s*[,>+~:\.\[#]|$)/', $selector)) {
            return true;
        }

        if (preg_match('/^::?(before|after)\b/i', $selector)) {
            return true;
        }

        // Eğer selector ':' ile başlıyorsa doğrudan kabul et
        if (strpos($selector, ':') === 0) {
            //error_log("found: ".$selector);
            return true;
        }

        if(preg_match('/^\s*@supports\s+/i', ltrim($selector))){
           //error_log("found: ".$selector);
            return true;
        }
        
        foreach ($this->white_list as $whitelist_class) {
            if (strpos($whitelist_class, '*') !== false) {
                $whitelist_pattern = str_replace('*', '.*', preg_quote($whitelist_class, '/'));
                if (preg_match('/' . $whitelist_pattern . '/', $selector)) {
                    //error_log(" wildcard: ".$selector);//
                    //error_log("found: ".$selector);
                    return true;
                }
            }
            $pattern = '/(^|\s|\+|>|\:)' . preg_quote($whitelist_class, '/') . '(\s|\+|>|\:|$)/';
            if (preg_match($pattern, $selector)) {
                //error_log("found: ".$selector);
                return true;
            }
        }
        
        $selector = $this->cleanWhitelistClasses($selector);

        //error_log("found: ".$selector);

        $found = true;
        $elements = $dom->find($selector);
        if (!$elements || count($elements) === 0) {
            $found = false;
            //error_log("not found: ".$selector);
        }else{
            //error_log("found: ".$selector);
        }
        return $found;
    }

    private function updateWhiteList($dom) {
        if (!$dom) {
            error_log("Error parsing HTML for whitelist update");
            return;
        }

        // Ajax method check
        $modal_triggers = ["map_modal", "page_modal", "form_modal", "template_modal", "iframe_modal"];
        $bootstrap_modal_classes = [
            ".modal", ".modal-*", ".btn-close"
        ];

        foreach ($modal_triggers as $trigger) {
            $selector = "[data-ajax-method='$trigger']";
            $elements = $dom->find($selector);
            if (!empty($elements) && $elements->count() > 0) {
                $this->white_list = array_merge($this->white_list, $bootstrap_modal_classes);
                $this->white_list[] = ".bootbox";
                break;
            }
        }

        //Bootstrap Check
        $triggers = ["collapse", "modal", "dropdown", "tooltip", "popover", "button", "tab", "pill", "offcanvas"];
        foreach ($triggers as $trigger) {
            $selector = "[data-bs-toggle='$trigger']";
            $elements = $dom->find($selector);
            if (!empty($elements) && $elements->count() > 0) {
                $this->white_list[] = ".".$trigger;
                $this->white_list[] = ".".$trigger."-*";
            }
        }

        $elements = $dom->find(".plyr, .player");
         if (!empty($elements) && $elements->count() > 0) {
            $this->white_list[] = ".plyr";
            $this->white_list[] = ".plyr-*";
            $this->white_list[] = ".plyr__*";
        }

        if((isset($this->opts["ignore_whitelist"]) && !$this->opts["ignore_whitelist"]) || !isset($this->opts["ignore_whitelist"])){
            $file_path = THEME_STATIC_PATH . 'data/css_safelist.json';
            if(file_exists($file_path)){
                $data = file_get_contents($file_path);
                $data = json_decode($data, true);
                $data = $data["dynamicSafelist"];
                $data = array_map(fn($item) => '.' . $item, $data);
                $data = array_merge($data, $this->white_list);
                $data = remove_duplicated_items($data);
                $this->white_list = $data;
                /*$white_list = array_map(function($item) {
                    $name = ltrim($item, '.');
                    if (str_ends_with($name, '-*')) {
                        $prefix = substr($name, 0, -2);
                        return '/^' . preg_quote($prefix, '/') . '-/';
                    }
                    return $name;
                }, $this->white_list);
                error_log(print_r($white_list, true));
                update_dynamic_css_whitelist($white_list);*/
            }
        }
        //error_log(print_r($this->white_list, true));
    }
    private function addHtmlWhitelist($dom) {
        $nodes = $dom->find('[data-html-whitelist]');
        foreach ($nodes as $node) {
            $attrVal = trim($node->getAttribute('data-html-whitelist'));
            if ($attrVal !== '') {
                foreach (explode(',', $attrVal) as $sel) {
                    $sel = trim($sel);
                    if ($sel !== '' && !isset($this->white_list_map[$sel])) {
                        $this->white_list[] = $sel;
                        $this->white_list_map[$sel] = true; // hızlı lookup
                    }
                }
            }
        }
    }
    private function cleanWhitelistClasses($selector) {

        // İlk olarak pseudo-elementlerin işlenmesi
        $selector = str_replace("::", ":", $selector);

        // Geçerli olmayan pseudo-class'ları temizliyoruz
        $selector = preg_replace_callback('/:[a-zA-Z0-9\-_]+/', function ($matches) {
            return in_array($matches[0], $this->acceptable_pseudo_classes) ? $matches[0] : '';
        }, $selector);

        $selector = str_replace(":not()", "", $selector);

        preg_match_all('/:not\(([^)]*)\)/', $selector, $not_matches);
        $protected_parts = [];
        if (!empty($not_matches[1])) {
            foreach ($not_matches[1] as $index => $content) {
                $placeholder = "PLACEHOLDER_$index";
                $selector = str_replace(":not($content)", $placeholder, $selector);
                $protected_parts[$placeholder] = ":not($content)";
            }
        }

        foreach ($this->white_list as $key => $value) {
            $selector = str_replace($value." ", " ", $selector);
            $selector = str_replace($value.":", ":", $selector);
            $selector = str_replace($value.".", ".", $selector);
            $selector = str_replace($value.">", ">", $selector);
            $selector = str_replace($value." >", ">", $selector);
            $selector = str_replace($value." +", "+", $selector);
            $selector = str_replace($value." ~", "~", $selector);
            $selector = str_replace($value."PLACEHOLDER_", "PLACEHOLDER_", $selector);
            //$selector = rtrim($selector, $value);
            $selector = rtrim($selector, ":");
        }

        // **Whitelist öğeleri için tam eşleşme yapan regex oluştur**
        $whitelist_pattern = implode('|', array_map(function ($class) {
            return '(?<=\s|\.)' . preg_quote(ltrim($class, '.'), '/') . '(?=\s|\.|>|$)';
        }, $this->white_list));

        // **Tam eşleşen class'ları kaldır (Ama :not() içindeki class'ları dokunmadan bırak!)**
        $selector = preg_replace('/\b' . $whitelist_pattern . '\b(?![^\(]*\))/', '', $selector);

        // **Ekstra temizlik**
        $selector = preg_replace('/\s+/', ' ', $selector);  // Fazla boşlukları temizle
        $selector = preg_replace('/\.(\s|$)/', '$1', $selector); // Gereksiz noktaları kaldır
        $selector = trim($selector);

        // :not(...) içindeki ifadeleri geri ekleyelim
        foreach ($protected_parts as $placeholder => $original) {
            $selector = str_replace($placeholder, $original, $selector);
        }

        // Eğer :not() boşsa, onu silelim
        $selector = str_replace(":not()", "", $selector);

        // Gereksiz boşlukları ve noktaları temizliyoruz
        $selector = preg_replace('/\s+/', ' ', $selector);
        $selector = preg_replace('/\.+/', '.', $selector);
        $selector = preg_replace('/\.\s/', ' ', $selector);
        $selector = str_replace(">:", ">*:", $selector);
        $selector = str_replace(".>", ">", $selector);
        $selector = str_replace(":>", ">", $selector);
        $selector = str_replace("+>", ">", $selector);
        $selector = str_replace("~>", ">", $selector);

        $selector = rtrim($selector, '.');
        $selector = ltrim($selector, "+");
        $selector = rtrim($selector, "+");
        $selector = rtrim($selector, "~");
        $selector = ltrim($selector, "~");
        $selector = trim($selector, ',');
        $selector = rtrim($selector, ">");
        $selector = ltrim($selector, ">");
        $selector = rtrim($selector, '.');

        return $selector;
    }
    private function isWhitelisted($dom, $selector) {
        foreach ($this->white_list as $whitelisted_class) {
            // Eğer selector tamamen whitelist'teki class ise direkt true döndür
            if ($selector === $whitelisted_class) {
                return true;
            }

            // Eğer whitelist class'ı parantez içindeyse, dokunma
            if (preg_match('/\(' . preg_quote($whitelisted_class, '/') . '\)/', $selector)) {
                continue;
            }

            // Sadece tam sınıf eşleşmelerini kaldır (diğer kelimeleri bozmaz)
            $pattern = '/(?<=\s|^)' . preg_quote($whitelisted_class, '/') . '(?=\s|$)/';

            if (preg_match($pattern, $selector)) {
                $parent_selector = trim(preg_replace($pattern, '', $selector));

                if (empty($parent_selector) || $this->selectorExists($dom, $parent_selector)) {
                    return true;
                }
            }
        }
        return false;
    }
    private function isBlacklisted($selector) {
        foreach ($this->black_list as $black_class) {
            $className = ltrim($black_class, '.');

            // 1️⃣ Class kontrolü
            preg_match_all('/\.([a-zA-Z0-9_-]+)/', $selector, $matches);
            if (in_array($className, $matches[1])) {
                return true;
            }

            // 2️⃣ Tag kontrolü
            // Eğer black list . ile başlamıyorsa, tag olarak da kontrol et
            if (strpos($black_class, '.') !== 0) {
                preg_match('/^([a-zA-Z0-9_-]+)/', $selector, $tag_match);
                if (isset($tag_match[1]) && strtolower($tag_match[1]) === strtolower($black_class)) {
                    return true;
                }
            }
        }

        return false;
    }





    //latest
    /**
     * Main entry. Scope tüm CSS içine scope_class'ı uygular (sadece selector başına).
     */
    private function scope_css(string $css, string $scope_class): string {
        $scope_class = trim($scope_class);
        if ($scope_class === '') return $css;
        if ($scope_class[0] !== '.') $scope_class = '.' . $scope_class;

        $len = strlen($css);
        $i = 0;
        $out = '';

        while ($i < $len) {
            // append whitespace/comments between rules
            if (preg_match('/\s/sA', $css[$i])) {
                $out .= $css[$i++];
                continue;
            }

            // If starts with @ (at-rule)
            if ($css[$i] === '@') {
                // read header until next '{' or ';'
                $start = $i;
                while ($i < $len && $css[$i] !== '{' && $css[$i] !== ';') $i++;
                // if it's a simple at-rule ending with ; (e.g. @import ...;)
                if ($i < $len && $css[$i] === ';') {
                    $out .= substr($css, $start, $i - $start + 1);
                    $i++;
                    continue;
                }
                // else it's a block at-rule: capture header and the balanced block
                $header = substr($css, $start, $i - $start);
                if ($i >= $len || $css[$i] !== '{') {
                    // malformed, append rest and break
                    $out .= substr($css, $start);
                    break;
                }
                $i++; // consume '{'
                $brace = 1;
                $bodyStart = $i;
                while ($i < $len && $brace > 0) {
                    if ($css[$i] === '{') $brace++;
                    elseif ($css[$i] === '}') $brace--;
                    $i++;
                }
                $body = substr($css, $bodyStart, $i - $bodyStart - 1); // without final closing brace
                // For at-rules with inner blocks (e.g. @media, @supports) we need to process inner body:
                $processed_body = $this->process_rules_in_block($body, $scope_class);
                $out .= $header . '{' . $processed_body . '}';
                continue;
            }

            // Otherwise parse a normal rule: selectors { body }
            // find next '{'
            $nextBrace = strpos($css, '{', $i);
            if ($nextBrace === false) {
                // append rest and break
                $out .= substr($css, $i);
                break;
            }
            $selectors = trim(substr($css, $i, $nextBrace - $i));
            $i = $nextBrace + 1;
            // capture balanced body
            $brace = 1;
            $bodyStart = $i;
            while ($i < $len && $brace > 0) {
                if ($css[$i] === '{') $brace++;
                elseif ($css[$i] === '}') $brace--;
                $i++;
            }
            $body = substr($css, $bodyStart, $i - $bodyStart - 1);

            // scope selectors and append if valid
            $scoped_selectors = $this->scope_selector_list($selectors, $scope_class);
            if ($scoped_selectors !== '') {
                $out .= $scoped_selectors . ' {' . trim($body) . '}';
            }
            // else skip invalid/empty rule
        }

        return $out;
    }
    /**
     * Process inner text of a block (like the body of @media).
     * Finds selector{body} pairs and scopes them. Keeps comments/whitespace between.
     */
    private function process_rules_in_block(string $blockBody, string $scope_class): string {
        $len = strlen($blockBody);
        $i = 0;
        $out = '';

        while ($i < $len) {
            // copy whitespace/comments until next real token
            if (preg_match('/\s/sA', $blockBody[$i])) {
                $out .= $blockBody[$i++];
                continue;
            }

            // find next '{'
            $nextBrace = strpos($blockBody, '{', $i);
            if ($nextBrace === false) {
                // append rest and break
                $out .= substr($blockBody, $i);
                break;
            }

            // selectors text is from current pos to nextBrace
            $selectorsText = trim(substr($blockBody, $i, $nextBrace - $i));
            $i = $nextBrace + 1;

            // capture balanced inner body
            $brace = 1;
            $bodyStart = $i;
            while ($i < $len && $brace > 0) {
                if ($blockBody[$i] === '{') $brace++;
                elseif ($blockBody[$i] === '}') $brace--;
                $i++;
            }
            $innerBody = substr($blockBody, $bodyStart, $i - $bodyStart - 1);

            // scope selectors
            $scoped_selectors = $this->scope_selector_list($selectorsText, $scope_class);
            if ($scoped_selectors !== '') {
                $out .= $scoped_selectors . ' {' . trim($innerBody) . '}';
            }
            // else skip invalid/empty rule
        }

        return $out;
    }
    /**
     * Scope a comma-separated selector list (handles commas inside [], (), quotes).
     * Returns empty string if no valid selectors remain.
     */
    private function scope_selector_list(string $selectors_raw, string $scope_class): string {
        $parts = $this->split_selectors_respecting_brackets($selectors_raw);
        $scoped = [];
        foreach ($parts as $part) {
            $s = trim($part);
            if ($s === '') continue;
            // keep global selectors intact
            if (preg_match('/^(:root|html|body|:host)\b/i', $s)) {
                $scoped[] = $s;
                continue;
            }
            // ignore at-rules accidentally passed here
            if ($s[0] === '@') continue;

            // if selector already begins with scope_class (exact start) keep it
            if (strpos($s, $scope_class) === 0) {
                $scoped[] = $s;
                continue;
            }

            // Otherwise prepend scope_class + space
            $scoped[] = $scope_class . ' ' . $s;
        }

        return empty($scoped) ? '' : implode(', ', $scoped);
    }
    /**
     * Split selector list by commas but ignore commas inside quotes, brackets, parens.
     */
    private function split_selectors_respecting_brackets(string $s): array {
        $len = strlen($s);
        $parts = [];
        $buf = '';
        $depthSquare = 0;
        $depthParen = 0;
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            // handle quote toggles
            if ($c === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $c;
                continue;
            }
            if ($c === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $c;
                continue;
            }
            if ($inSingle || $inDouble) {
                $buf .= $c;
                continue;
            }
            // track bracket/paren depth
            if ($c === '[') { $depthSquare++; $buf .= $c; continue; }
            if ($c === ']') { if ($depthSquare>0) $depthSquare--; $buf .= $c; continue; }
            if ($c === '(') { $depthParen++; $buf .= $c; continue; }
            if ($c === ')') { if ($depthParen>0) $depthParen--; $buf .= $c; continue; }

            // split on comma only if not inside brackets/paren/quotes
            if ($c === ',' && $depthSquare === 0 && $depthParen === 0) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $c;
        }
        if (trim($buf) !== '') $parts[] = $buf;
        // trim each part
        return array_map('trim', $parts);
    }
}