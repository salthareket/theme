<?php

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\OutputFormat;

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
       ':empty'
    ];

    public function __construct($html, $css, $output = "", $additional_whitelist = [], $critical_css = false) {
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

        //error_log(print_r($this->html, true));

        $this->css = is_file($css) ? file_get_contents($css) : $css;
        $this->output = $output;
        $this->white_list = array_merge($this->white_list, $additional_whitelist);
        //error_log(print_r($this->white_list, true));
        $this->critical_css = $critical_css;
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

        //error_log(print_r($this->css_structure["styles"], true));
        
        $this->filterUsedCss();

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

    /*public function extract_critical_css(HtmlDomParser $dom, string $css): array {
        $usedSelectors = [];

        // Sayfadaki tüm class ve ID'leri topla
        foreach ($dom->find('*') as $element) {
            if ($element->hasAttribute('class')) {
                foreach (explode(" ", $element->getAttribute('class')) as $class) {
                    $usedSelectors[] = "." . trim($class);
                }
            }
            if ($element->hasAttribute('id')) {
                $usedSelectors[] = "#" . trim($element->getAttribute('id'));
            }
        }

        // CSS'yi parse et
        $parser = new Parser($css);
        $cssDocument = $parser->parse();

        $criticalCSS = "";
        $remainingCSS = $css;

        foreach ($cssDocument->getAllRuleSets() as $ruleSet) {
            // Eğer RuleSet, DeclarationBlock değilse atla
            if (!($ruleSet instanceof Sabberworm\CSS\RuleSet\DeclarationBlock)) {
                continue;
            }

            foreach ($ruleSet->getSelectors() as $selector) {
                foreach ($usedSelectors as $usedSelector) {
                    if (strpos($selector, $usedSelector) !== false) {
                        $ruleCss = $ruleSet->render(OutputFormat::createCompact()); // ✅ BURASI DÜZELTİLDİ!

                        $criticalCSS .= $ruleCss;// . "\n";

                        // Orijinal CSS'den bu critical kodu çıkar
                        $remainingCSS = str_replace($ruleCss, '', $remainingCSS);
                        break;
                    }
                }
            }
        }

        // Minify yap
        $minifierCritical = new Minify\CSS($criticalCSS);
        $criticalCSS = $minifierCritical->minify();

        $minifierCSS = new Minify\CSS($remainingCSS);
        $remainingCSS = $minifierCSS->minify();

        return [
            'css' => $remainingCSS,
            'critical_css' => $criticalCSS
        ];
    }*/
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
    private function cleanSelector($selector) {
        // Tüm pseudo-class ve element’leri uçur
        return preg_replace('/:(hover|focus|active|visited|checked|disabled|enabled|invalid|valid|target|focus-visible|focus-within|empty|selection|is\([^)]+\)|where\([^)]+\)|has\([^)]+\)|not\([^)]+\)|before|after)/', '', $selector);
    }
    public function generate_critical_css(array $selectors) {
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
                    $criticalCSS .= implode(", ", $cleaned_selectors) . " { " . $style["code"] . " }\n";
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
                    $media_block .= "$cleaned { $rule }\n";
                }
            }
            if (!empty($media_block)) {
                $criticalCSS .= "$media {\n$media_block}\n";
            }
        }

        // Overlay/Modal gibi yapıları sakla
        $criticalCSS .= "body.offcanvas-open, .offcanvas, .modal, .bootbox, .backdrop, .overlay { display: none !important; }\n";

        // Görsel efekt override'ları
        $criticalCSS .= "img, picture, source {
            opacity: 1 !important;
            mix-blend-mode: normal !important;
            filter: none !important;
        }\n";

        // Minify
        $minifier = new Minify\CSS($criticalCSS);
        $criticalCSS = $minifier->minify();
        $criticalCSS = str_replace("../", "../../", $criticalCSS);

        if (!empty($this->output)) {
            file_put_contents($this->output, $criticalCSS);
        } else {
            return $criticalCSS;
        }
    }






    private function removeUnnecessaryLines() {
        $this->css = preg_replace('/@charset[^;]+;/', '', $this->css);
    }
    private function removeComments() {
        $this->css = preg_replace('/\/\*.*?\*\//s', '', $this->css);
    }
    private function extractRootVariables() {
        preg_match('/:root\s*{([^}]*)}/s', $this->css, $match);
        if ($match) {
            preg_match_all('/(--[^:]+):\s*([^;]+);/s', $match[1], $vars, PREG_SET_ORDER);
            foreach ($vars as $var) {
                $this->css_structure["root_variables"][ trim($var[1]) ] = trim($var[2]);
            }
            $this->css = str_replace($match[0], '', $this->css);
        }
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
        foreach ($this->css_structure["media_queries"] as $media_query => $selectors) {
            $media_css = "";
            
            foreach ($selectors as $selector => $rules) {
                if ($this->isWhitelisted($dom, $selector) || $selector === ":root") {
                    $media_css .= "$selector { $rules }\n";
                } else {
                    $individual_selectors = array_map('trim', explode(',', $selector));
                    foreach ($individual_selectors as $ind_selector) {
                        if ($this->selectorExists($dom, $ind_selector)) {
                            $media_css .= "$selector { $rules }\n";
                            break;
                        }
                    }
                }
            }
            
            if (!empty($media_css)) {
                $this->css_temp .= "$media_query {\n$media_css}\n";
                $this->trackUsedItems($media_css);
            }
        }
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

    /*private function processStyles($dom) {
        foreach ($this->css_structure["styles"] as $style) {
            $selectors = $style["selectors"];
            $found = false;
            
            foreach ($selectors as $selector) {
                if ($this->isWhitelisted($dom, $selector) || strpos($selector, '*') !== false || strpos($selector, '~') !== false) {
                    $found = true;
                    break;
                }
                if ($this->selectorExists($dom, $selector)) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $this->css_temp .= implode(", ", $selectors) . " { " . $style["code"] . " }\n";
                $this->trackUsedItems($style["code"]);
            }
        }
    }*/

    /*private function processStyles($dom) {
        foreach ($this->css_structure["styles"] as $style) {
            $selectors = $style["selectors"];
            $keptSelectors = [];

            foreach ($selectors as $selector) {
                $keep = false;

                // Acceptable pseudo-class varsa tut
                foreach ($this->acceptable_pseudo_classes as $pseudo) {
                    if (strpos($selector, $pseudo) !== false) {
                        error_log($selector." pseudo:".$pseudo);
                        $keep = true;
                        break;
                    }
                }

                // Acceptable değilse normal selectorExists testi
                if (!$keep) {
                    if ($this->isWhitelisted($dom, $selector) || 
                        strpos($selector, '*') !== false || 
                        strpos($selector, '~') !== false ||
                        $this->selectorExists($dom, $selector)) {
                        error_log($selector." is exist");
                        $keep = true;
                    }
                }

                if ($keep) {
                    $keptSelectors[] = $selector;
                }
            }

            if (!empty($keptSelectors)) {
                $this->css_temp .= implode(", ", $keptSelectors) . " { " . $style["code"] . " }\n";
                $this->trackUsedItems($style["code"]);
            }
        }
    }

    private function processStyles($dom) {
        foreach ($this->css_structure["styles"] as $style) {
            $selectors = $style["selectors"];
            $keptSelectors = [];

            foreach ($selectors as $selector) {

                if (empty($selector)) {
                    continue;
                }
                
                // Unclosed attribute selector
                if (preg_match('/\[\s*[^\]]*$/', $selector)) {
                    continue;
                }

                $keep = false;

                // 1) Pseudo-class varsa işaretle
                $hasPseudo = false;
                foreach ($this->acceptable_pseudo_classes as $pseudo) {
                    if (strpos($selector, $pseudo) !== false) {
                        $hasPseudo = true;
                        break;
                    }
                }

                if ($hasPseudo) {
                    // Kökteki selector'u ayıkla
                    preg_match(
                        '/^(
                            (?:\[[^\]]+\]         # attribute selector
                            | [.#][\w\-]+         # class veya id
                            | \w+ )               # tag
                            (?: [.#][\w\-]+       # ek class veya id
                            | \[[^\]]+\] )*       # ek attribute
                        )/x',
                        $selector,
                        $matches
                    );
                    $rootSelector = $matches[1] ?? null;

                    if ($rootSelector) {
                        if (
                            $this->isWhitelisted($dom, $rootSelector) ||
                            $this->selectorExists($dom, $rootSelector)
                        ) {
                            $keep = true;
                        } else {
                            $keep = false;
                        }
                    } else {
                        // kök selector yoksa riskli → sil
                        $keep = false;
                    }
                } else {
                    // normal selector testi
                    if (
                        $this->isWhitelisted($dom, $selector) ||
                        strpos($selector, '*') !== false ||
                        strpos($selector, '~') !== false ||
                        $this->selectorExists($dom, $selector)
                    ) {
                        $keep = true;
                    }
                }

                if ($keep) {
                    $keptSelectors[] = $selector;
                }
            }

            if (!empty($keptSelectors)) {
                $this->css_temp .= implode(", ", $keptSelectors) . " { " . $style["code"] . " }\n";
                $this->trackUsedItems($style["code"]);
            }
        }
    }*/

    private function processStyles($dom) {
        foreach ($this->css_structure["styles"] as $style) {
            $selectors = $style["selectors"];
            $keptSelectors = [];

            foreach ($selectors as $selector) {
                if (empty($selector)) {
                    continue;
                }

                // Skip unclosed attribute selector (ör: [class*=")
                if (preg_match('/\[\s*[^\]]*$/', $selector)) {
                    error_log("Skipping incomplete attribute selector: $selector");
                    continue;
                }

                $keep = false;

                // 1) Acceptable pseudo-class içeriyor mu?
                $hasPseudo = false;
                foreach ($this->acceptable_pseudo_classes as $pseudo) {
                    if (strpos($selector, $pseudo) !== false) {
                        $hasPseudo = true;
                        break;
                    }
                }

                if ($hasPseudo) {
                    // Kök selector'u bul
                    preg_match(
                        '/^(
                            (?:\[[^\]]+\]
                            | [.#][\w\-]+
                            | \w+ )
                            (?: [.#][\w\-]+
                            | \[[^\]]+\] )*
                        )/x',
                        $selector,
                        $matches
                    );
                    $rootSelector = $matches[1] ?? null;

                    if ($rootSelector) {
                        if (
                            $this->isWhitelisted($dom, $rootSelector) ||
                            $this->selectorExists($dom, $rootSelector)
                        ) {
                            $keep = true;
                        }
                    }
                } else {
                    // Normal selector testi
                    if (
                        $this->isWhitelisted($dom, $selector) ||
                        strpos($selector, '*') !== false ||
                        strpos($selector, '~') !== false ||
                        $this->selectorExists($dom, $selector)
                    ) {
                        $keep = true;
                    }
                }

                if ($keep) {
                    $keptSelectors[] = $selector;
                }
            }

            if (!empty($keptSelectors)) {
                $this->css_temp .= implode(", ", $keptSelectors) . " { " . $style["code"] . " }\n";
                $this->trackUsedItems($style["code"]);
            }
        }
    }

    private function processRootVariables() {
        $vars_temp = "";
        foreach ($this->css_structure["root_variables"] as $var_name => $value) {
            if (in_array($var_name, $this->root_variables_used)) {
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

    private function selectorExists($dom, $selector) {
        $selector = trim($selector);
        if (empty($selector)) return false;

        // CSS selector mı kontrol et (çok temel bir örnek, genişletilebilir)
        if (preg_match('/^[a-zA-Z.#:\[]/', trim($selector)) === 0) {
            error_log('[RemoveUnusedCss] Skipping invalid selector: ' . $selector);
            return false;
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
            if ($elements || count($elements) > 1) {
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
            if (!$elements || count($elements) === 0) {
            }else{
                $this->white_list[] = ".".$trigger;
                $this->white_list[] = ".".$trigger."-*";
            }
        }

        $elements = $dom->find(".plyr, .player");
        if ($elements || count($elements) > 1) {
            $this->white_list[] = ".plyr";
            $this->white_list[] = ".plyr-*";
            $this->white_list[] = ".plyr__*";
        }

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
}