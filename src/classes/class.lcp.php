<?php
class Lcp {
    private static $instance = null;
    private static $lcp_found = false;
    private $data = [];
    private $view_type = "code";
    private $lcp_ids = []; 
    private $lcp_urls = [];

    private function __construct($data = []) {
        // Data yükleme
        if ($data) {
            $this->data = $data;
        } elseif (defined("SITE_ASSETS") && is_array(SITE_ASSETS)) {
            $this->data = SITE_ASSETS["lcp"] ?? [];
        }

        $this->prepare_lookup_tables();

        // WP Rocket bypass kontrolü - Erken tetiklenmesi için filtreye ekle
        if (function_exists('rocket_cache_reject_uri')) {
            add_filter('rocket_cache_reject_uri', [$this, 'handle_rocket_cache_bypass'], 10, 1);
        }

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
            if (isset($this->data[$device]) && is_array($this->data[$device])) {
                $lcp_item = $this->data[$device];
                
                if (!empty($lcp_item["id"])) {
                    $this->lcp_ids[] = (int)$lcp_item["id"];
                }
                
                if (!empty($lcp_item["url"])) {
                    $this->lcp_urls[] = (string)$lcp_item["url"];
                }
            }
        }
        
        $this->lcp_ids = array_unique($this->lcp_ids);
        $this->lcp_urls = array_unique($this->lcp_urls);
    }

    public function handle_rocket_cache_bypass($urls) {
        $device = wp_is_mobile() ? "mobile" : "desktop";
        $lock = "lcp_lock_" . $device;

        // EĞER BU CİHAZIN VERİSİ YOKSA: Kilit olsa da olmasa da CACHE'İ KAPAT (Ölçüm sağlıklı bitsin)
        if (empty($this->data[$device]["type"])) {
            if (!get_transient($lock)) {
                set_transient($lock, true, 2 * MINUTE_IN_SECONDS);
            }
            $urls[] = $_SERVER['REQUEST_URI']; 
        }
        return $urls;
    }

    private function init_logic() {
        $device = wp_is_mobile() ? "mobile" : "desktop";

        // Bu cihazın verisi varsa preload bas (Bu aşamada cache serbest)
        if (!empty($this->data[$device]["type"])) {
            add_action('wp_head', [$this, "preloadCode"], 1);
        } 
        // Data yoksa ve BU CİHAZ için kilit vurulmadıysa (veya süresi bittiyse) ölçümü başlat
        elseif (!$this->is_measuring_locked($device)) {
            $this->no_cache(); 
        }
    }

    private function is_measuring_locked($device) {
        return get_transient("lcp_lock_" . $device) === true;
    }

    public function preloadCode() {
        $preload = "";
        $css = "";

        foreach ($this->data as $key => $lcp) {
            if (empty($lcp["url"]) && empty($lcp["code"])) continue;
            
            $is_mobile_key = ($key == "mobile");
            $media = $is_mobile_key ? "max-width: 768px" : "min-width: 769px";
            $url = $lcp["url"] ?? '';

            if (!empty($lcp["code"])) {
                $css .= "@media ({$media}) { {$lcp["code"]} }\n";
            }

            if (!empty($url) && $this->view_type == "code") {
                $preload .= sprintf(
                    '<link rel="preload" as="%s" href="%s" fetchpriority="high" media="(%s)">' . "\n",
                    $lcp["type"], $url, $media
                );
            }
        }

        if ($preload) echo $preload;
        if ($css) echo "<style type='text/css' id='lcp-style'>\n{$css}</style>\n";
    }

    private function no_cache() {
        // Zaten no-cache tanımlıysa veya script yüklüyse çık
        //if (defined('DONOTCACHEPAGE')) return;
        
        //define('DONOTCACHEPAGE', true);

        /* ACHTUNG: Blocked for some reason
        if(is_user_logged_in() && current_user_can('manage_options') && !is_admin()){
            add_action("wp_enqueue_scripts", function() {
                wp_enqueue_script('measure-lcp', SH_STATIC_URL . 'js/measure-lcp.js', [], '1.0.2', false);
            }, 20);

            add_action('wp_footer', [$this, 'inject_measurement_scripts']);
        }
        */


    }

    public function inject_measurement_scripts() {
    ?>
        <script id="lcp-measure-js" nowprocket>
          (function () {
            window.lcp_measurement_sent = false;

            var script = document.createElement('script');
            script.id = 'web-vitals'; // Silmek için ID verdik
            script.src = ajax_request_vars.theme_url + 'static/js/plugins/web-vitals.js';
            
            script.onload = function () {
                if (window.webVitals && typeof window.webVitals.onLCP === 'function') {
                    
                    window.webVitals.onLCP(function(metric) {
                        if (!window.lcp_measurement_sent) {
                            const platform = window.innerWidth <= 768 ? "mobile" : "desktop";
                            
                            if (typeof lcp_data_save === 'function') {
                                console.log("LCP Yakalandı:", metric.value);
                                lcp_data_save(metric, platform);
                                
                                // --- TEMİZLİK BAŞLIYOR ---
                                window.lcp_measurement_sent = true; 
                                
                                // 1. Onload eventini siktirip atıyoruz
                                script.onload = null; 
                                
                                // 2. Script etiketini DOM'dan söküyoruz
                                if(script.parentNode) {
                                    script.parentNode.removeChild(script);
                                    console.log("LCP Scripti DOM'dan temizlendi.");
                                }
                                
                                // 3. İstersen bu inline script'in kendisini de silebilirsin
                                var selfScript = document.getElementById('lcp-measure-js');
                                if(selfScript) selfScript.remove();
                                // -------------------------
                            }
                        }
                    }, { reportAllChanges: true });

                } else {
                    console.error("webVitals objesi var ama onLCP fonksiyonu yok!");
                    script.onload = null; // Hata olsa da temizle
                }
            };
            
            script.onerror = function() {
                script.onload = null;
                if(script.parentNode) script.parentNode.removeChild(script);
            };

            document.head.appendChild(script);
          })();
        </script>
        <?php
    }

    public function is_lcp($image) {
        // Kapı Kontrolü
        if (self::$lcp_found) return false;
        if (empty($this->lcp_ids) && empty($this->lcp_urls)) return false;

        $result = false;
        $target_id = null;
        $target_url = null;

        // 1. Veri Ayıklama (Mermi Hızı)
        if (is_array($image)) {
            $target_id = $image['id'] ?? null;
            $target_url = $image['url'] ?? null;
            
            if (!$target_id && !$target_url) {
                foreach ($image as $item) {
                    $check_id = $item['id'] ?? (is_numeric($item) ? $item : null);
                    if ($check_id && in_array((int)$check_id, $this->lcp_ids, true)) {
                        $result = true; break;
                    }
                }
            }
        } elseif (is_numeric($image)) {
            $target_id = $image;
        } elseif (is_string($image)) {
            $target_url = $image;
        } elseif (is_object($image)) {
            $target_id = $image->id ?? null;
        }

        // 2. Kontrol (Hiyerarşik)
        if (!$result) {
            if ($target_id && in_array((int)$target_id, $this->lcp_ids, true)) {
                $result = true;
            } elseif ($target_url && in_array((string)$target_url, $this->lcp_urls, true)) {
                $result = true;
            }
        }

        // 3. Bayrak Operasyonu
        if ($result) {
            self::$lcp_found = true;
        }

        return $result;
    }
}