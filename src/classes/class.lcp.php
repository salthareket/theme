<?php

/**
 * LCP (Largest Contentful Paint) Optimizer
 *
 * Detects and optimizes the LCP element per page, per device (desktop/mobile).
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   $lcp = Lcp::getInstance();
 *   // Hook'lar otomatik register edilir.
 *   // Sayfa yüklendiğinde SITE_ASSETS["lcp"] verisi kontrol edilir.
 *   // Veri varsa preload + critical CSS inject edilir.
 *   // Veri yoksa measurement script (web-vitals.js) inject edilir.
 *
 * Flow:
 * 1. Page loads -> check SITE_ASSETS["lcp"] for saved LCP data
 * 2. Data EXISTS -> inject <link rel="preload"> + inline critical CSS for LCP element
 * 3. Data MISSING for this device -> inject measurement script (web-vitals.js)
 * 4. JS detects LCP -> sends data via AJAX (save_lcp_results)
 * 5. Next visit -> preload + CSS active, page loads faster
 *
 * LCP is NOT always an image - can be text, video, background-image, any element.
 */
class Lcp {
    private static $instance = null;
    private static $lcp_found = false;
    private $data = [];
    private $lcp_ids = [];
    private $lcp_urls = [];
    private $device;

    private function __construct($data = []) {
        $this->device = wp_is_mobile() ? 'mobile' : 'desktop';

        if ($data) {
            $this->data = $data;
        } elseif (defined("SITE_ASSETS") && is_array(SITE_ASSETS)) {
            $this->data = SITE_ASSETS["lcp"] ?? [];
        }

        $this->prepare_lookup_tables();
        $this->init_logic();
    }

    public static function getInstance($data = []) {
        if (self::$instance === null) {
            self::$instance = new self($data);
        }
        return self::$instance;
    }

    private function prepare_lookup_tables() {
        if (empty($this->data)) return;

        foreach (['desktop', 'mobile'] as $device) {
            if (!isset($this->data[$device]) || !is_array($this->data[$device])) continue;
            $lcp = $this->data[$device];
            if (!empty($lcp["id"])) $this->lcp_ids[] = (int) $lcp["id"];
            if (!empty($lcp["url"])) $this->lcp_urls[] = (string) $lcp["url"];
            // font_url veya bg-image url'si de match için ekle
            if (!empty($lcp["font_url"])) $this->lcp_urls[] = (string) $lcp["font_url"];
        }

        $this->lcp_ids  = array_unique($this->lcp_ids);
        $this->lcp_urls = array_unique($this->lcp_urls);
    }

    // ─── INIT LOGIC ──────────────────────────────────────

    private function init_logic() {
        $device_data = $this->data[$this->device] ?? [];
        $has_lcp_data = !empty($device_data["type"]);

        // css_critical varsa da inject_preload çalışmalı
        $has_critical_css = defined('SITE_ASSETS') && is_array(SITE_ASSETS) && !empty(SITE_ASSETS["css_critical"]);

        if ($has_lcp_data || $has_critical_css) {
            // LCP verisi veya critical CSS var → preload + critical CSS inject et
            add_action('wp_head', [$this, 'inject_preload'], 1);
            
            // Debug
            add_action('wp_footer', function() use ($has_lcp_data, $has_critical_css) {
                echo "<!-- LCP: Data found for {$this->device} (lcp=" . ($has_lcp_data?'yes':'no') . ", critical=" . ($has_critical_css?'yes':'no') . "), preload injected -->\n";
            }, 999);
        }

        // Mevcut device için LCP verisi yoksa measurement başlat
        // (critical CSS olsa bile eksik device için ölçüm yapılmalı)
        if (!$has_lcp_data) {
            $this->disable_page_cache();
            $this->start_measurement();

            add_action('wp_footer', function() {
                echo "<!-- LCP: Measurement mode active for {$this->device} -->\n";
            }, 999);
        }
    }

    // ─── PRELOAD + CRITICAL CSS ──────────────────────────

    public function inject_preload(): void {
        $preload = "";
        $css = "";

        // ─── Critical CSS dosyasını inline ekle ──────────────────────────────
        $critical_css_path = '';
        if (defined('SITE_ASSETS') && is_array(SITE_ASSETS)) {
            $critical_css_path = SITE_ASSETS["css_critical"] ?? '';
        }
        if (!empty($critical_css_path)) {
            $full_path = STATIC_PATH . $critical_css_path;
            if (file_exists($full_path)) {
                $critical_content = file_get_contents($full_path);
                if (!empty($critical_content)) {
                    $css .= $critical_content . "\n";
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        foreach ($this->data as $device => $lcp) {
            if (!is_array($lcp)) continue;

            $url      = $lcp["url"]      ?? '';
            $code     = $lcp["code"]     ?? '';
            $font_url = $lcp["font_url"] ?? '';
            $type     = $lcp["type"]     ?? 'image';

            if (empty($url) && empty($code) && empty($font_url)) continue;

            $is_mobile = ($device === 'mobile');
            $media     = $is_mobile ? 'max-width: 768px' : 'min-width: 769px';

            switch ($type) {

                case 'image':
                    // <img> → preload as="image"
                    if (!empty($url)) {
                        $preload .= sprintf(
                            '<link rel="preload" as="image" href="%s" fetchpriority="high" media="(%s)">' . "\n",
                            esc_url($url), esc_attr($media)
                        );
                    }
                    // Critical CSS: width/height/aspect-ratio (layout shift önleme)
                    if (!empty($code)) {
                        $css .= "@media ({$media}) { {$code} }\n";
                    }
                    break;

                case 'video':
                    // <video> → poster'ı image olarak preload et
                    if (!empty($url)) {
                        $preload .= sprintf(
                            '<link rel="preload" as="image" href="%s" fetchpriority="high" media="(%s)">' . "\n",
                            esc_url($url), esc_attr($media)
                        );
                    }
                    // Video için CSS gerekmez
                    break;

                case 'iframe':
                    // <iframe> → preload as="document"
                    if (!empty($url)) {
                        $preload .= sprintf(
                            '<link rel="preload" as="document" href="%s" media="(%s)">' . "\n",
                            esc_url($url), esc_attr($media)
                        );
                    }
                    break;

                case 'bg-image':
                    // background-image → preload as="image" + critical CSS
                    if (!empty($url)) {
                        $preload .= sprintf(
                            '<link rel="preload" as="image" href="%s" fetchpriority="high" media="(%s)">' . "\n",
                            esc_url($url), esc_attr($media)
                        );
                    }
                    if (!empty($code)) {
                        $css .= "@media ({$media}) { {$code} }\n";
                    }
                    break;

                case 'text':
                    // Text element → font preload (custom font varsa) + critical CSS
                    if (!empty($font_url)) {
                        // woff2 mi woff mu?
                        $font_ext = pathinfo(parse_url($font_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                        $font_type = ($font_ext === 'woff2') ? 'font/woff2' : 'font/woff';
                        $preload .= sprintf(
                            '<link rel="preload" as="font" type="%s" href="%s" crossorigin="anonymous" media="(%s)">' . "\n",
                            esc_attr($font_type), esc_url($font_url), esc_attr($media)
                        );
                    }
                    // Critical CSS: font-size, color, font-family, line-height
                    if (!empty($code)) {
                        $css .= "@media ({$media}) { {$code} }\n";
                    }
                    break;

                default:
                    // Bilinmeyen tip → varsa URL'yi image olarak preload et
                    if (!empty($url)) {
                        $preload .= sprintf(
                            '<link rel="preload" as="image" href="%s" fetchpriority="high" media="(%s)">' . "\n",
                            esc_url($url), esc_attr($media)
                        );
                    }
                    if (!empty($code)) {
                        $css .= "@media ({$media}) { {$code} }\n";
                    }
                    break;
            }
        }

        if ($preload) echo $preload;
        if ($css) echo "<style id='lcp-critical'>\n{$css}</style>\n";
    }

    // ─── CACHE CONTROL ──────────────────────────────────

    /**
     * Disable all page caching during LCP measurement
     * Prevents WP Rocket / other cache plugins from caching the page with measurement scripts
     */
    private function disable_page_cache() {
        // Universal: works with WP Rocket, WP Super Cache, W3 Total Cache, LiteSpeed, etc.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        // WP Rocket specific: reject this URI from cache
        if (function_exists('rocket_cache_reject_uri')) {
            add_filter('rocket_cache_reject_uri', function($urls) {
                $urls[] = $_SERVER['REQUEST_URI'];
                return $urls;
            });
        }

        // Send no-cache headers
        add_action('send_headers', function() {
            if (!headers_sent()) {
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
        }, 1);
    }

    // ─── MEASUREMENT ─────────────────────────────────────

    private function start_measurement() {
        // Ölçüm script'ini footer'a inject et — tüm kullanıcılar için (ilk ziyaretçi saptasın)
        add_action('wp_footer', [$this, 'inject_measurement_script'], 99);
    }

    public function inject_measurement_script() {
        // web-vitals.js ve measure-lcp.js'i inline olarak yükle
        $web_vitals_url = defined('STATIC_URL') ? STATIC_URL . 'js/plugins/web-vitals.js' : '';
        $measure_lcp_url = defined('SH_STATIC_URL') ? SH_STATIC_URL . 'js/measure-lcp.js' : '';

        if (empty($web_vitals_url) || empty($measure_lcp_url)) return;
        ?>
        <script id="lcp-measure" data-nowprocket>
        (function() {
            if (window.__lcp_measured) return;
            window.__lcp_measured = true;

            // Sunucu tarafı device tespiti + JS tarafı genişlik kontrolü
            var serverDevice = '<?php echo $this->device; ?>';
            var clientDevice = window.innerWidth <= 768 ? "mobile" : "desktop";
            // İkisi de mobile diyorsa mobile, aksi halde client'a güven
            var platform = (serverDevice === 'mobile' || clientDevice === 'mobile') ? 'mobile' : 'desktop';
            var lastLcpMetric = null;

            // measure-lcp.js'i yükle (lcp_data + lcp_data_save fonksiyonları)
            var ms = document.createElement('script');
            ms.src = '<?php echo esc_url($measure_lcp_url); ?>';
            ms.onload = function() {
                // Sonra web-vitals.js'i yükle
                var wv = document.createElement('script');
                wv.src = '<?php echo esc_url($web_vitals_url); ?>';
                wv.onload = function() {
                    if (window.webVitals && typeof window.webVitals.onLCP === 'function') {
                        // reportAllChanges: true → her LCP güncellemesini al, son olanı sakla
                        window.webVitals.onLCP(function(metric) {
                            lastLcpMetric = metric; // Her güncellemede son değeri sakla
                            log('[LCP] Güncelleme: ' + metric.value.toFixed(0) + 'ms', 'info');
                        }, { reportAllChanges: true });

                        // Sayfa kapanırken / arka plana geçerken son LCP'yi kaydet
                        function saveFinalLcp() {
                            if (!lastLcpMetric) return;
                            if (window.__lcp_save_sent) return;
                            window.__lcp_save_sent = true;

                            if (typeof lcp_data_save === 'function') {
                                lcp_data_save(lastLcpMetric, platform);
                            }

                            // Temizlik
                            if (wv && wv.parentNode) wv.parentNode.removeChild(wv);
                            if (ms && ms.parentNode) ms.parentNode.removeChild(ms);
                            var self = document.getElementById('lcp-measure');
                            if (self) self.remove();
                        }

                        // visibilitychange: sekme arka plana geçince (PSI bunu kullanır)
                        document.addEventListener('visibilitychange', function() {
                            if (document.visibilityState === 'hidden') {
                                saveFinalLcp();
                            }
                        });

                        // pagehide: sayfa kapanınca
                        window.addEventListener('pagehide', saveFinalLcp);

                        // beforeunload: ek güvence
                        window.addEventListener('beforeunload', saveFinalLcp);

                        // Fallback: 3 saniye sonra ne varsa kaydet
                        // (PSI gibi botlar visibilitychange tetiklemeyebilir)
                        setTimeout(function() {
                            saveFinalLcp();
                        }, 3000);

                        // Agresif fallback: load event sonrası hemen kaydet
                        // PSI sayfayı tam yükledikten sonra LCP'yi raporlar
                        window.addEventListener('load', function() {
                            setTimeout(function() {
                                saveFinalLcp();
                            }, 1000);
                        });
                    }
                };
                document.head.appendChild(wv);
            };
            document.head.appendChild(ms);
        })();
        </script>
        <?php
    }

    // ─── LCP DETECTION (Image class integration) ─────────

    /**
     * Check if a given image/element is the LCP element
     * Called by image_is_lcp() helper
     *
     * @param mixed $image ID (int), URL (string), array with id/url, or object with id
     * @return bool
     */
    public function is_lcp($image): bool {
        // Sayfa başına 1 LCP — ilk match'ten sonra diğerleri false
        if (self::$lcp_found) return false;
        if (empty($this->lcp_ids) && empty($this->lcp_urls)) return false;

        $target_id = null;
        $target_url = null;

        // Veri ayıklama
        if (is_array($image)) {
            $target_id = $image['id'] ?? null;
            $target_url = $image['url'] ?? null;

            // Breakpoint array: her value'yu kontrol et
            if (!$target_id && !$target_url) {
                foreach ($image as $item) {
                    $check_id = is_array($item) ? ($item['id'] ?? null) : (is_numeric($item) ? $item : null);
                    if ($check_id && in_array((int) $check_id, $this->lcp_ids, true)) {
                        self::$lcp_found = true;
                        return true;
                    }
                }
            }
        } elseif (is_numeric($image)) {
            $target_id = $image;
        } elseif (is_string($image)) {
            $target_url = $image;
        } elseif (is_object($image)) {
            $target_id = $image->id ?? ($image->ID ?? null);
        }

        // ID match
        if ($target_id && in_array((int) $target_id, $this->lcp_ids, true)) {
            self::$lcp_found = true;
            return true;
        }

        // URL match
        if ($target_url && in_array((string) $target_url, $this->lcp_urls, true)) {
            self::$lcp_found = true;
            return true;
        }

        return false;
    }

    /**
     * Reset LCP found flag (useful for testing or multi-render scenarios)
     */
    public static function resetFound(): void {
        self::$lcp_found = false;
    }

    /**
     * Get current device type
     */
    public function getDevice(): string {
        return $this->device;
    }

    /**
     * Check if LCP data exists for current device
     */
    public function hasData(): bool {
        return !empty($this->data[$this->device]["type"]);
    }

    // ─── AJAX: save_lcp_results ───────────────────────────────────────────────
    // Eski konum: actions.php. Buraya taşındı — LCP class'ına ait.
    // Frontend JS (measure-lcp.js) LCP verisini tespit edince bu endpoint'e POST eder.

    public static function registerAjaxHooks(): void {
        add_action( 'wp_ajax_save_lcp_results',        [ self::class, 'ajaxSaveLcpResults' ] );
        add_action( 'wp_ajax_nopriv_save_lcp_results', [ self::class, 'ajaxSaveLcpResults' ] );
        // sh_save_csp_violations → SecurityHooks::saveCspViolations() (security app'e taşındı)
    }

    public static function ajaxSaveLcpResults(): void {
        $id_raw = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
        $id     = is_numeric( $id_raw ) ? intval( $id_raw ) : $id_raw;
        $type   = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $url    = isset( $_POST['url'] )  ? esc_url_raw( $_POST['url'] )           : '';

        if ( empty( $id ) || ! $type || ! isset( $_POST['lcp_data'] ) ) {
            wp_send_json_error( [ 'message' => 'Eksik veya geçersiz parametre!' ] );
        }

        $lcp_raw  = stripslashes( $_POST['lcp_data'] );
        $lcp_data = json_decode( $lcp_raw, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => 'JSON formatı bozuk!' ] );
        }

        if ( empty( $id ) || ! $type || ! $lcp_data ) {
            wp_send_json_error( [ 'message' => 'Geçersiz veri!' ] );
        }

        // Selector'ları topla
        $selectors = [];
        foreach ( $lcp_data as $device => &$data ) {
            if ( ! empty( $data['selectors'] ) && is_array( $data['selectors'] ) ) {
                $selectors = array_merge( $selectors, $data['selectors'] );
                unset( $data['selectors'] );
            }
        }
        unset( $data );

        $critical_css  = '';
        $structure_fp  = '';
        $existing_meta = [];

        // Mevcut meta'yı çek
        if ( $type === 'archive' || $type === 'dynamic' ) {
            $existing_meta = get_option( $id . '_assets' );
        } elseif ( in_array( $type, [ 'post', 'term', 'user', 'comment' ], true ) ) {
            $existing_meta = call_user_func( "get_{$type}_meta", $id, 'assets', true );
        } else {
            wp_send_json_error( [ 'message' => 'Geçersiz type: ' . $type ] );
        }

        $structure_fp = $existing_meta['structure_fp'] ?? '';

        if ( empty( $structure_fp ) ) {
            wp_send_json_error( [ 'message' => 'Critical CSS oluşturulamadı: structure_fp bulunamadı.' ] );
        }

        // Critical CSS üret
        $selectors = array_unique( $selectors );
        if ( $selectors ) {
            $cache_dir = STATIC_PATH . 'css/cache/';
            $output    = $cache_dir . $structure_fp . '-critical.css';

            $input = '';
            if ( defined( 'SITE_ASSETS' ) && is_array( SITE_ASSETS ) ) {
                if ( ! empty( SITE_ASSETS['plugin_css'] ) && file_exists( STATIC_PATH . SITE_ASSETS['plugin_css'] ) ) {
                    $input .= file_get_contents( STATIC_PATH . SITE_ASSETS['plugin_css'] );
                }
                if ( ! empty( SITE_ASSETS['css_page'] ) && file_exists( STATIC_PATH . SITE_ASSETS['css_page'] ) ) {
                    $input .= file_get_contents( STATIC_PATH . SITE_ASSETS['css_page'] );
                }
            }
            if ( empty( $input ) && file_exists( STATIC_PATH . 'css/main-combined.css' ) ) {
                $input = file_get_contents( STATIC_PATH . 'css/main-combined.css' );
            }

            if ( ! empty( $input ) && class_exists( 'SaltHareket\AssetManager\RemoveUnusedCss' ) ) {
                try {
                    $remover = new \SaltHareket\AssetManager\RemoveUnusedCss( $url, $input, $output, [], true );
                    $remover->generate_critical_css( $selectors );
                    if ( file_exists( $output ) ) {
                        $critical_css = str_replace( STATIC_PATH, '', $output );
                    }
                } catch ( \Exception $e ) {
                    if ( defined( 'ENABLE_LOGS' ) && ENABLE_LOGS ) {
                        error_log( '[LCP] Critical CSS hatası: ' . $e->getMessage() );
                    }
                }
            }
        }

        // LCP URL'lerini attachment ID'ye çevir
        foreach ( $lcp_data as $key => $lcp ) {
            if ( ! empty( $lcp['url'] ) && function_exists( 'is_local' ) && is_local( $lcp['url'] ) ) {
                $lcp_data[ $key ]['id'] = function_exists( 'get_attachment_id_by_url' )
                    ? get_attachment_id_by_url( $lcp['url'] )
                    : 0;
            }
        }

        // Meta'ya yaz
        $return = false;
        if ( $existing_meta ) {
            $existing_meta['lcp'] = array_merge( (array) ( $existing_meta['lcp'] ?? [] ), $lcp_data );
            if ( $critical_css ) {
                $existing_meta['css_critical'] = $critical_css;
            }
            if ( $type === 'archive' || $type === 'dynamic' ) {
                $return = update_option( $id . '_assets', $existing_meta );
            } else {
                $return = call_user_func( "update_{$type}_meta", $id, 'assets', $existing_meta );
            }
        }

        wp_send_json_success( [
            'message' => 'LCP verileri kaydedildi!',
            'data'    => $lcp_data,
            'status'  => $return,
        ] );
    }
}
