<?php
/**
 * Theme Styles New - CSS Generator
 *
 * Tüm modüllerin processor'larını çalıştırarak CSS custom property'leri
 * (variables) toplar ve theme/static/css/theme-styles.css dosyasına yazar.
 * Breakpoint bazlı media query'ler otomatik oluşturulur.
 *
 * @package SaltHareket\Theme\ThemeStyles
 * @version 1.3.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 1.3.0 - 2026-04-28
 * - $header_themes_css property eklendi
 * - set_header_themes_css() metodu eklendi - header themes CSS'ini root.css'e ekler
 *
 * 1.2.0 - 2026-04-27
 * - xxxl breakpoint root'a ekleniyor (media query olmadan)
 * - THEME_STYLES_BREAKPOINTS constant'ından breakpoint listesi alınıyor
 *
 * 1.1.0 - 2026-04-20
 * - add_variable(), add_mobile_variable(), add_media_query_variable() eklendi
 * - Processor'lar $generator instance'ı alıyor
 *
 * 1.0.0 - 2026-04-15
 * - Initial release
 *
 * HOW TO USE:
 * Her modülün processor.php dosyasında theme_styles_process_{module}($data, $generator)
 * fonksiyonu tanımlanır. Bu fonksiyon variables/mobile/media_queries array'i döner
 * veya $generator metodlarını kullanır.
 *
 * BASIC USAGE:
 * - CSS üret ve kaydet : (new Theme_Styles_CSS_Generator())->generate($data)
 * - Variable ekle      : $generator->add_variable('header-height', '80px')
 * - Media query ekle   : $generator->add_media_query_variable('lg', 'header-height', '70px')
 *
 * @example Processor fonksiyonu örneği:
 * function theme_styles_process_header($data, $generator) {
 *     $h = $data['header']['header'] ?? [];
 *     return [
 *         'variables' => [
 *             'header-bg'     => $h['bg_color'] ?? '#fff',
 *             'header-z-index'=> $h['z_index']  ?? '100',
 *         ],
 *         'media_queries' => [
 *             'lg' => ['header-height' => $h['height']['lg'] ?? '70px'],
 *             'md' => ['header-height' => $h['height']['md'] ?? '60px'],
 *         ],
 *     ];
 * }
 *
 * @example CSS üretme ve kaydetme:
 * $generator = new Theme_Styles_CSS_Generator();
 * $css = $generator->generate($data);
 *
 * @example Üretilen CSS örneği:
 * :root {
 *   --header-bg: #ffffff;
 *   --header-z-index: 100;
 * }
 * \@media (max-width: 1199px) {
 *   :root { --header-height: 70px; }
 * }
 */

if (!defined('ABSPATH')) exit;

class Theme_Styles_CSS_Generator {
    
    private $variables = [];
    private $mobile_variables = [];
    private $media_queries = [];
    private $media_query_set = [];   // clamp üretimi için (FluidCss)
    private $header_themes_css = '';
    
    /**
     * Generate CSS
     */
    public function generate($data) {
        $start_time = microtime(true);

        // Modüller initialize edilmemişse yükle
        if (!class_exists('Theme_Styles_Module_Manager')) {
            require_once THEME_STYLES_PATH . '/includes/class-module-manager.php';
        }
        Theme_Styles_Module_Manager::init();

        // Reset (header_themes_css korunur - set_header_themes_css ile set edilmişse)
        $this->variables = [];
        $this->mobile_variables = [];
        $this->media_queries = [];
        $this->media_query_set = [];
        
        // Process modules
        $modules = Theme_Styles_Module_Manager::get_enabled();
        foreach ($modules as $module_id => $module_config) {
            $this->process_module($module_id, $data);
        }
        
        // Build CSS
        $css = $this->build_css();
        
        // Save to file
        $this->save_css($css);

        $elapsed = round((microtime(true) - $start_time) * 1000, 2);
        error_log("[ThemeStyles] CSS generation: {$elapsed}ms | " . count($this->variables) . " variables");
        
        return $css;
    }
    
    /**
     * Process module
     */
    private function process_module($module_name, $data) {
        $processor_file = THEME_STYLES_PATH . "/modules/{$module_name}/processor.php";
        
        if (file_exists($processor_file)) {
            include_once $processor_file;
            
            $processor_function = "theme_styles_process_{$module_name}";
            if (function_exists($processor_function)) {
                $result = $processor_function($data, $this);
                
                if (!empty($result['variables']) && is_array($result['variables'])) {
                    foreach ($result['variables'] as $k => $v) {
                        if (!is_array($v)) {
                            $this->variables[$k] = self::normalize_value($k, $v);
                        }
                    }
                }
                if (!empty($result['mobile']) && is_array($result['mobile'])) {
                    foreach ($result['mobile'] as $k => $v) {
                        if (!is_array($v)) {
                            $this->mobile_variables[$k] = self::normalize_value($k, $v);
                        }
                    }
                }
                // media_queries: ['bp' => ['var' => 'val']]
                if (!empty($result['media_queries']) && is_array($result['media_queries'])) {
                    foreach ($result['media_queries'] as $bp => $vars) {
                        if (!is_array($vars)) continue;
                        if (!isset($this->media_queries[$bp])) {
                            $this->media_queries[$bp] = [];
                        }
                        foreach ($vars as $k => $v) {
                            if (!is_array($v)) {
                                $this->media_queries[$bp][$k] = self::normalize_value($k, $v);
                            }
                        }
                    }
                }
                // media_query_set: ['type' => ['bp' => ['key' => 'val']]] - clamp için
                if (!empty($result['media_query_set']) && is_array($result['media_query_set'])) {
                    foreach ($result['media_query_set'] as $type => $sizes) {
                        if (!isset($this->media_query_set[$type])) {
                            $this->media_query_set[$type] = [];
                        }
                        foreach ($sizes as $bp => $vals) {
                            if (!isset($this->media_query_set[$type][$bp])) {
                                $this->media_query_set[$type][$bp] = [];
                            }
                            foreach ($vals as $k => $v) {
                                $this->media_query_set[$type][$bp][$k] = $v;
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Build CSS - FluidCss kullanarak root.css formatında üretir
     */
    private function build_css() {
        // FluidCss varsa kullan (eski sistemle aynı çıktı)
        if (class_exists('FluidCss')) {
            $fluid = new FluidCss(
                $this->variables,
                $this->mobile_variables,
                $this->media_queries,
                $this->media_query_set
            );
            return $fluid->generate();
        }

        // Fallback: basit CSS üretimi
        $css = "/**\n * Theme Styles New - Generated CSS\n * Generated: " . date('Y-m-d H:i:s') . "\n */\n\n";
        $css .= ":root {\n";
        foreach ($this->variables as $key => $value) {
            if (!is_array($value)) {
                $css .= "  --{$key}: {$value};\n";
            }
        }
        $css .= "}\n\n";

        $breakpoints = array_filter(THEME_STYLES_BREAKPOINTS);
        foreach ($breakpoints as $bp => $width) {
            if (!empty($this->media_queries[$bp])) {
                $css .= "@media (max-width: {$width}) {\n  :root {\n";
                foreach ($this->media_queries[$bp] as $key => $value) {
                    if (!is_array($value)) {
                        $css .= "    --{$key}: {$value};\n";
                    }
                }
                $css .= "  }\n}\n\n";
            }
        }

        return $css;
    }

    /**
     * Save CSS - STATIC_PATH/css/root.css (eski sistemle aynı path)
     * Header themes CSS de varsa eklenir.
     */
    private function save_css($css) {
        // Eski sistemle aynı path: STATIC_PATH/css/root.css
        if (defined('STATIC_PATH')) {
            $css_dir  = STATIC_PATH . 'css';
            $css_file = $css_dir . '/root.css';
        } else {
            $css_dir  = get_template_directory() . '/static/css';
            $css_file = $css_dir . '/root.css';
        }

        if (!is_dir($css_dir)) {
            mkdir($css_dir, 0755, true);
        }

        // Header themes CSS (eski sistemden - SCSS mixin ile üretilir)
        if (!empty($this->header_themes_css)) {
            $css .= "\n" . $this->header_themes_css;
        }

        file_put_contents($css_file, $css);
    }

    /**
     * Set header themes CSS (save_theme_styles_header_themes() çıktısı)
     */
    public function set_header_themes_css($css) {
        $this->header_themes_css = $css;
    }
    
    /**
     * Get SCSS variables — save_data() sonrasında scss-variables.json'a yazılır.
     * wp_scss_set_variables() bu JSON'ı okur, get_theme_styles() çağrısına gerek kalmaz.
     */
    public function get_scss_variables(): array {
        return $this->variables;
    }

    /**
     * Add variable
     */
    public function add_variable($key, $value) {
        $this->variables[$key] = $value;
    }
    
    /**
     * Add mobile variable
     */
    public function add_mobile_variable($key, $value) {
        $this->mobile_variables[$key] = $value;
    }
    
    /**
     * Add media query variable
     */
    public function add_media_query_variable($breakpoint, $key, $value) {
        if (!isset($this->media_queries[$breakpoint])) {
            $this->media_queries[$breakpoint] = [];
        }
        $this->media_queries[$breakpoint][$key] = $value;
    }

    /**
     * Normalize variable value.
     * Renk variable'ları (color, bg, gradient vb.) boş string ise transparent döner.
     * Font variable'ları boşluk içeriyorsa tırnak içine alınır.
     * Non-color variable'lar (image, size, position vb.) boş kalır.
     */
    private static function normalize_value(string $key, $value): string {
        $value = (string) $value;

        // Font variable'ı mı? Boşluk içeriyorsa ve tırnak yoksa tırnak ekle
        $font_keywords = ['font', 'font-family', 'font-primary', 'font-secondary', 'header-font', 'icon-font', 'nav-font'];
        $is_font = false;
        foreach ($font_keywords as $kw) {
            if (str_contains($key, $kw)) {
                $is_font = true;
                break;
            }
        }

        if ($is_font && $value !== '' && str_contains($value, ' ')) {
            // Virgülle ayrılmış font stack - her parçayı kontrol et
            $parts = explode(',', $value);
            $parts = array_map(function($part) {
                $part = trim($part);
                // Zaten tırnak içindeyse dokunma
                if (str_starts_with($part, '"') || str_starts_with($part, "'")) {
                    return $part;
                }
                // Boşluk içeriyorsa tırnak ekle
                if (str_contains($part, ' ')) {
                    return '"' . $part . '"';
                }
                return $part;
            }, $parts);
            $value = implode(', ', $parts);
            return $value;
        }

        // Boş değilse dokunma (font değilse)
        if ($value !== '') return $value;

        // Renk variable'ı mı? key içinde bu kelimeler varsa transparent
        // NOT: bg-image, bg-size, bg-position, bg-repeat, bg-attachment renk değil
        $non_color_keywords = ['image', 'size', 'position', 'repeat', 'attachment', 'font', 'weight', 'transform', 'spacing', 'radius', 'width', 'height', 'padding', 'margin', 'gap', 'duration', 'rendering', 'smoothing', 'scroll', 'opacity', 'border-radius'];
        foreach ($non_color_keywords as $kw) {
            if (str_contains($key, $kw)) {
                return $value; // boş kalır
            }
        }

        $color_keywords = ['color', '-bg', 'background', 'gradient', 'fill', 'stroke', 'shadow', 'border-color', 'outline', 'accent', 'track', 'thumb'];
        foreach ($color_keywords as $kw) {
            if (str_contains($key, $kw)) {
                return 'transparent';
            }
        }

        return $value;
    }
}
