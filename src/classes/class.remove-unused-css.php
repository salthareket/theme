<?php

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;

class RemoveUnusedCss {
    private $html;
    private $css;
    private $css_temp = "";
    private $output;
    private $root_variables_used = [];
    private $animations_used = [];
    private $css_structure = [
        "media_queries" => [],
        "root_variables" => [],
        "keyframes" => [],
        "fonts" => [],
        "styles" => []
    ];
    public $white_list = [
        ".fade",
        ".show",
        ".affix",
        ".header-hide",
        ".resizing",
        ".scroll-down",
        ".loading",
        ".loading-*",
        ".lenis",
        ".lenis-*",
        ".modal-open",
        ".menu-open",
        ".offcanvas-open",
        ".offcanvas-fullscreen-open",
        ".collapse",
        ".collapsing",
        ".in",
        ".open",
        ".active",
        ".close",
        ".loaded"
    ];

    private $acceptable_pseudo_classes = [
        ':hover', ':focus', ':active', ':visited', ':disabled', ':checked', ':required', ':empty'];
         //':first-child', ':last-child', ':nth-child', ':nth-of-type'];

    public function __construct($html, $css, $output = "", $additional_whitelist = []) {
        if (is_string($html)) {
            $this->html = is_file($html) ? file_get_contents($html) : $html;
            $this->html = HtmlDomParser::str_get_html($this->html);
        } elseif ($html instanceof HtmlDomParser) {
            // Eğer $html zaten HtmlDomParser nesnesiyse, direkt kullan
            $this->html = $html;
        } else {
            throw new InvalidArgumentException("Invalid HTML input. Must be a string or HtmlDomParser object.");
        }
        $this->css = is_file($css) ? file_get_contents($css) : $css;
        $this->output = $output;
        $this->white_list = array_merge($this->white_list, $additional_whitelist);
    }

    public function process() {
        $this->removeUnnecessaryLines();
        $this->removeComments();
        $this->extractMediaQueries();
        $this->extractRootVariables();
        $this->extractKeyframes();
        $this->extractFonts();
        $this->extractStyles();

        //error_log(print_r($this->css_structure["styles"], true));
        
        $this->filterUsedCss();

        $minify = new Minify\CSS($this->css_temp);
        $this->css_temp = $minify->minify();
        
        if(!empty($output)){
            file_put_contents($this->output, $this->css_temp);
        }else{
            return $this->css_temp;
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
        preg_match_all('/(@(?:-webkit-|-moz-|-o-)?keyframes)\s+([\w-]+)\s*{((?:[^{}]+|{[^{}]*})*)}/s', $this->css, $matches, PREG_SET_ORDER);
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
        $this->updateWhiteList($dom);
       // error_log(print_r($this->white_list, true));
        $this->processFonts();
        $this->processStyles($dom);
        $this->checkMediaQueries($dom);
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

    private function processStyles($dom) {
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

    private function selectorExists($dom, $selector) {
        $selector = trim($selector);
        if (empty($selector)) return false;

        error_log("input:" . $selector);

        // Eğer selector ':' ile başlıyorsa doğrudan kabul et
        if (strpos($selector, ':') === 0) {
            return true;
        }

        // CSS combinator operatörleri ile başlayanları temizle
        $selector = preg_replace('/^[>+~\s]+/', '', $selector);

        foreach ($this->white_list as $whitelist_class) {
            if (strpos($whitelist_class, '*') !== false) {
                // Wildcard içeren whitelisted class'lar için
                $whitelist_pattern = str_replace('*', '.*', preg_quote($whitelist_class, '/'));
                if (preg_match('/' . $whitelist_pattern . '/', $selector)) {
                    return true;
                }
            } elseif (strpos($selector, $whitelist_class) !== 0) {
                $selector = preg_replace('/(?<![-\w])' . preg_quote($whitelist_class, '/') . '(?![-\w])/', '', $selector);
            }
        }
        

        error_log("output 1:" . $selector);

        // Whitelist içindeki elemanları selector'dan çıkar
        foreach ($this->white_list as $whitelist_class) {
            // Eğer selector'un başında whitelist class varsa dokunma
            if (strpos($selector, $whitelist_class) === 0) {
                continue;
            }
            // Whitelist class'ı sadece bağımsız bir kelime olarak kaldır
            $selector = preg_replace('/(?<=\W|^)' . preg_quote($whitelist_class, '/') . '(?=\W|$)/', '', $selector);
        }

        // Boşlukları düzelt
        $selector = trim(preg_replace('/\s+/', ' ', $selector));

        error_log("output 2:" . $selector);

        $selector = trim(preg_replace('/\s+/', ' ', $selector));

        error_log("output 3:" . $selector);
        
        // Pseudo-elements temizle, sadece belirlenen pseudo-class'ları tut
        $selector = preg_replace_callback('/:(?!' . implode('|', array_map('preg_quote', $this->acceptable_pseudo_classes)) . ')([a-zA-Z-]+(?:\(.*?\))?)/', function ($matches) {
            return '';
        }, $selector);

        error_log("output 4:" . $selector);

        // Eğer selector pseudo içeriyorsa ve kabul edilebilir değilse, ana selector'u koru
        if (strpos($selector, ':') !== false) {
            preg_match('/([^:]+)(:[^:]*)/', $selector, $matches);
            if (!empty($matches[1]) && !empty($matches[2]) && in_array($matches[2], $this->acceptable_pseudo_classes)) {
                $selector = $matches[1] . $matches[2];
            } else {
                $selector = $matches[1] ?? $selector;
            }
        }

        $selector = rtrim($selector, ">");
        $selector = ltrim($selector, ">");

        error_log("output 5:" . $selector);

        // Eğer combinator içeren selector ise, ana selector'u kontrol et
        if (preg_match('/(.+)([>+~])(.+)/', $selector, $matches)) {
        	error_log(print_r($matches, true));
            $base_selector = trim($matches[1]);
            $child_selector = trim($matches[3]);
            
            if ($this->selectorExists($dom, $base_selector)) {
                error_log("output base:" . $base_selector);
                return true;
            } else {
                return false;
            }
        }

        // Hatalı selectorleri direkt ekleyerek hata almayı engelle
        /*if (preg_match('/[^a-zA-Z0-9.#\[\]\-_\s>+~]/', $selector)) {
            error_log("Skipping invalid selector: " . $selector);
            return true;
        }*/

        $selector = preg_replace_callback('/:(?!' . implode('|', array_map('preg_quote', $this->acceptable_pseudo_classes)) . ')([a-zA-Z-]+(?:\(.*?\))?)/', function ($matches) {
            return '';
        }, $selector);

        $selector = preg_replace_callback('/:(?!' . implode('|', array_map('preg_quote', $this->acceptable_pseudo_classes)) . ')([a-zA-Z-]+(?:\(.*?\))?)/', function ($matches) {
            return '';
        }, $selector);

        // `:not()` içinde içerik yoksa, onu kaldır
        $selector = preg_replace('/:not\(\s*\)/', '', $selector);

        // Çift virgülleri tek hale getir
        $selector = preg_replace('/,+/', ',', $selector);

        // Baştaki ve sondaki virgülleri temizle
        $selector = trim($selector, ',');

        error_log("output final:" . $selector);

        // Elementi HTML içinde ara
        $elements = $dom->find($selector);
        if (!$elements || count($elements) === 0) {
            //error_log("Selector not found in DOM: " . $selector);
            return false;
        }
        
        
        return true;
    }

    private function updateWhiteList($dom) {
        if (!$dom) {
            error_log("Error parsing HTML for whitelist update");
            return;
        }

        $modal_triggers = ["map_modal", "page_modal", "form_modal", "template_modal", "iframe_modal"];
        $bootstrap_modal_classes = [
            ".modal", ".modal-*", ".btn-close"
        ];

        foreach ($modal_triggers as $trigger) {
            $elements = $dom->find("[data-ajax-method='$trigger']");
            if (!empty($elements)) {
                $this->white_list = array_merge($this->white_list, $bootstrap_modal_classes);
                $this->white_list[] = ".bootbox";
                break;
            }
        }
    }

    private function isWhitelisted($dom, $selector) {
        foreach ($this->white_list as $whitelisted_class) {
            if ($selector === $whitelisted_class) {
                return true;
            }
            if (strpos($selector, $whitelisted_class) !== false) {
                $parent_selector = trim(str_replace($whitelisted_class, '', $selector));
                if (empty($parent_selector) || $this->selectorExists($dom, $parent_selector)) {
                    return true;
                }
            }
        }
        return false;
    }

}








