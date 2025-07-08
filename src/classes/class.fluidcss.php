<?php
// $variables                 : Direkt root'a yazılır.
// $variables_mobile          : Max width 575'e yazılır (mobile).
// $variables_media_query     : Tüm media query^lere yazılır.
// $variables_media_query_set : Özeleştirilmiş olarak mobile dışındaki media query'lere

class FluidCss {
    public $css;

    private $variables;
    private $variables_mobile;
    private $variables_media_query;
    private $variables_media_query_set;

    private $variables_mobile_added;
    private $breakpoints;
    private $breakpoint_keys;

    public function __construct(array $variables = [], array $variables_mobile = [], array $variables_media_query = [], array $variables_media_query_set = []) {
        $this->variables = $variables;
        $this->variables_mobile = $variables_mobile;
        $this->variables_media_query = $variables_media_query;
        $this->variables_media_query_set = $variables_media_query_set;

        $this->variables_mobile_added = false;
        $this->breakpoints = $GLOBALS['breakpoints'];
        $this->breakpoint_keys = array_keys($GLOBALS['breakpoints']);
    }

    public function generate(){
        $this->root_variables();
        $this->root_media_query();
        $this->root_media_query_set();
        return $this->css;
    }

    private function root_variables(){
        $css = ":root {\n";
        foreach ($this->variables as $key => $value) {
            $key = str_replace("_", "-", $key);
            if (is_array($value)) {
                continue;
            }
            $css .= "    --{$key}: {$value};\n";
            if (is_hex_color($value)) {
                $rgb = hex2rgb($value);
                $css .= "    --{$key}-rgb: " . implode(', ', $rgb) . ";\n";
            }
        }
        $css .= "--hero-height-full: 100vh;\n";
        if(isset($this->variables["custom-colors-list"])){
            $css .= "--salt-colors: ".$this->variables["custom-colors-list"].";\n";
        }
        $css .= "}\n\n";
        $this->css .= $css;
    }
    private function root_media_query(){
        
        $css = "";
        $grouped_by_breakpoint = [];

        foreach ($this->variables_media_query as $var_name => $breakpoint_values) {
            foreach ($breakpoint_values as $breakpoint => $value) {
                if (!isset($this->breakpoints[$breakpoint])) continue;
                $grouped_by_breakpoint[$breakpoint][$var_name] = $value;
            }
        }

        foreach ($this->breakpoint_keys as $i => $key) {
            if (!isset($grouped_by_breakpoint[$key])) continue;

            $vars = $grouped_by_breakpoint[$key];
            $min = $this->breakpoints[$key];
            $max = null;

            if (isset($this->breakpoint_keys[$i + 1])) {
                $next_key = $this->breakpoint_keys[$i + 1];
                $max = $this->breakpoints[$next_key] - 1;
            }

            if ($key === 'xs') {
                $css .= "@media (max-width: {$min}px) {\n";
            } else {
                if ($max !== null) {
                    $css .= "@media (min-width: {$min}px) and (max-width: {$max}px) {\n";
                } else {
                    $css .= "@media (min-width: {$min}px) {\n";
                }
            }

            $css .= "    :root {\n";

            if (!empty($this->variables_mobile) && $key === 'xs') {
                $this->variables_mobile_added = true;
                foreach ($this->variables_mobile as $k => $v) {
                    $css .= "        --{$k}: {$v};\n";
                    if (is_hex_color($v)) {
                        $rgb = hex2rgb($v);
                        $css .= "        --{$k}-rgb: " . implode(', ', $rgb) . ";\n";
                    }
                }
            }

            foreach ($vars as $var_name => $value) {
                $var_name = str_replace("_", "-", $var_name);
                $css .= "        --{$var_name}: {$value};\n";
                if (is_hex_color($value)) {
                    $rgb = hex2rgb($value);
                    $css .= "        --{$var_name}-rgb: " . implode(', ', $rgb) . ";\n";
                }
            }

            $css .= "    }\n";
            $css .= "}\n\n";
        }

        if (!empty($this->variables_mobile) && !$this->variables_mobile_added) {
            $css .= "@media (max-width: {$this->breakpoints['xs']}px) {\n";
            $css .= "    :root {\n";
            foreach ($this->variables_mobile as $key => $value) {
                $key = str_replace("_", "-", $key);
                $css .= "        --{$key}: {$value};\n";
                if (is_hex_color($value)) {
                    $rgb = hex2rgb($value);
                    $css .= "        --{$key}-rgb: " . implode(', ', $rgb) . ";\n";
                }
            }
            $css .= "    }\n";
            $css .= "}\n\n";
        }

        $this->css .= $css;
    }
    private function root_media_query_set() {
 
        // min_vw: xs + 1
        $min_vw = isset($this->breakpoints['xs']) ? $this->breakpoints['xs'] + 1 : 0;
        // max_vw: xxxl - 1
        $max_vw = isset($this->breakpoints['xxxl']) ? $this->breakpoints['xxxl'] - 1 : 1920;

        $css = "";

        // 1️⃣ Clamp media query
        $css .= "@media (min-width: {$min_vw}px) and (max-width: {$max_vw}px) {\n";
        $css .= "  :root {\n";

        foreach ($this->variables_media_query_set as $type => $sizes) {
            foreach ($sizes as $size_key => $val_arr) {
                foreach ($val_arr as $val_name => $val) {
                    $xs_val = isset($sizes['xs'][$val_name]) ? ($sizes['xs'][$val_name]) : null;
                    $curr_val = isset($val) ? ($val) : null;
                    if (!$xs_val || !$curr_val) continue;
                    if(in_array($val_name, ["lh"])){
                        $css .= "    --{$type}-{$val_name}-{$size_key}: {$curr_val};\n";
                    }else{
                        $clamp = $this->fluid_type($min_vw, $max_vw, $xs_val, $curr_val);
                        $css .= "    --{$type}-{$val_name}-{$size_key}: {$clamp};\n";
                    }
                }
            }
        }

        $css .= "  }\n";
        $css .= "}\n\n";

        // 2️⃣ XXXL media query (direct values)
        if (isset($this->breakpoints['xxxl'])) {
            $xxxl_min = $this->breakpoints['xxxl'];

            $css .= "@media (min-width: {$xxxl_min}px) {\n";
            $css .= "  :root {\n";

            foreach ($this->variables_media_query_set as $type => $sizes) {
                foreach ($sizes as $size_key => $val_arr) {
                    foreach ($val_arr as $val_name => $val) {
                        $curr_val = isset($val) ? ($val) : null;
                        if (!$curr_val) continue;
                        $css .= "    --{$type}-{$val_name}-{$size_key}: {$curr_val};\n";
                    }
                }
            }

            $css .= "  }\n";
            $css .= "}\n";
        }

        $this->css .= $css;
    }

    private function fluid_type($min_vw, $max_vw, $min_value, $max_value) {
        // Regex ile unit tespiti
        preg_match('/([\d\.]+)([a-z%]*)/', trim($min_value), $min_matches);
        preg_match('/([\d\.]+)([a-z%]*)/', trim($max_value), $max_matches);

        error_log(print_r($min_matches, true));
        error_log(print_r($max_matches, true));

        $min_num = isset($min_matches[1]) ? floatval($min_matches[1]) : 0;
        $min_unit = isset($min_matches[2]) && $min_matches[2] !== '' ? $min_matches[2] : 'px';

        $max_num = isset($max_matches[1]) ? floatval($max_matches[1]) : 0;
        $max_unit = isset($max_matches[2]) && $max_matches[2] !== '' ? $max_matches[2] : 'px';

        // Eğer unit'ler farklı ise, hata ver veya string olarak döndür
        if ($min_unit !== $max_unit) {
            return $max_value;
        }

        if ($min_num == $max_num) {
            return "{$max_num}{$max_unit}";
        }

        if ($min_num > $max_num) {
            $min_num = $max_num;
        }

        $factor = ($max_num - $min_num) / ($max_vw - $min_vw);
        $calc_value = $min_num - ($min_vw * $factor);

        $factor = round($factor, 4);
        $calc_value = round($calc_value, 4);

        return "clamp({$min_num}{$min_unit}, calc({$calc_value}{$min_unit} + {$factor} * 100vw), {$max_num}{$max_unit})";
    }

}
