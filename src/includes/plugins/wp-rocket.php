<?php

//namespace WP_Rocket\Helpers\static_files\exclude\optimized_css_cpt_taxonomy;

defined( 'ABSPATH' ) or die();

/*
// revert mobile cache
add_filter( 'pre_get_rocket_option_cache_mobile', '__return_zero' );
add_filter( 'pre_get_rocket_option_do_caching_mobile_files', '__return_zero' );

// Unless both mobile cache options are enabled, disable rocket_above_the_fold_optimization
add_filter( 'rocket_above_the_fold_optimization', function( $enabled ) {
    $options = get_option('wp_rocket_settings', []);
    return $enabled && isset($options['do_caching_mobile_files'], $options['cache_mobile']) && $options['do_caching_mobile_files'] == 1 && $options['cache_mobile'] == 1;
} );

// Unless both mobile cache options are enabled, disable rocket_lrc_optimization
add_filter( 'rocket_lrc_optimization', function( $enabled ) {
    $options = get_option('wp_rocket_settings', []);
    return $enabled && isset($options['do_caching_mobile_files'], $options['cache_mobile']) && $options['do_caching_mobile_files'] == 1 && $options['cache_mobile'] == 1;
} );

// Unless both mobile cache options are enabled, disable rocket_preconnect_external_domains_optimization
add_filter( 'rocket_preconnect_external_domains_optimization', function( $enabled ) {
    $options = get_option('wp_rocket_settings', []);
    return $enabled && isset($options['do_caching_mobile_files'], $options['cache_mobile']) && $options['do_caching_mobile_files'] == 1 && $options['cache_mobile'] == 1;
} );
*/

add_filter( 'rocket_defer_inline_exclusions', function( $inline_exclusions_list ) {
  if ( ! is_array( $inline_exclusions_list ) ) {
    $inline_exclusions_list = array();
  }
  $inline_exclusions_list[] = 'jquery';
  $inline_exclusions_list[] = 'jquery-js';
  $inline_exclusions_list[] = 'site_config_vars*$';
  $inline_exclusions_list[] = 'site_config_vars';
  $inline_exclusions_list[] = 'site_config_vars-js';
  $inline_exclusions_list[] = 'site_config_vars-js-extra';
  $inline_exclusions_list[] = 'site_config_vars-js-after';
  $inline_exclusions_list[] = 'preload_image';
  $inline_exclusions_list[] = 'map_data';
  return $inline_exclusions_list;
});

function wprocket_is_cached() {
    foreach (headers_list() as $header) {
        if (strpos($header, 'x-rocket-nginx-serving-static') !== false) {
            //error_log("wprocket_is_cached();");
            return true;
        }
    }        
    return false;
}
function is_wp_rocket_crawling() {
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'WP Rocket') !== false) {
        //error_log("is_wp_rocket_crawling();");
        return true; // WP Rocket bu sayfayı önbelleğe almak için ziyaret ediyor
    }
    return false;
}

/*add_filter('rocket_buffer', function ($buffer) {
    if (!is_admin()) {
        return str_replace('"cached":""', '"cached":"1"', $buffer);
    }
    return $buffer;
});*/

add_filter('rocket_buffer', function ($html) {
    if (strpos($html, 'id="site-config"') === false) {
        return $html;
    }
    if (!preg_match(
        '#<script type="application/json" id="site-config">(.*?)</script>#s',
        $html,
        $m
    )) {
        return $html;
    }
    $data = json_decode(trim($m[1]), true);
    if (!is_array($data)) {
        return $html;
    }
    $data['cached'] = true;
    $new_script = '<script type="application/json" id="site-config">'
        . wp_json_encode($data, JSON_UNESCAPED_SLASHES)
        . '</script>';
    return str_replace($m[0], $new_script, $html);
}, 9999);


if(!class_exists("WP_Rocket_Dynamic_Excludes")){
    class WP_Rocket_Dynamic_Excludes {

        private $subfolder;
        private $transient_key = 'rocket_reject_urls';
        private $cache;
        private static $instance = null;

        public function __construct($cache = true) {
            $this->cache = (bool)$cache;
            $this->subfolder = $this->detect_subfolder();
            
            // 1. Runtime Injection
            add_filter('pre_get_rocket_option_cache_reject_uri', [$this, 'modify_cache_reject_urls'], 10, 2);
            add_filter('pre_get_rocket_option_preload_excluded_uri', [$this, 'modify_cache_reject_urls'], 10, 2);
            
            // 2. Save Hooks (Kalıcı Güncelleme)
            add_action('update_option_options_exclude_post_types_from_cache', [$this, 'sync_rocket_settings']); // Arşivler
            add_action('update_option_options_exclude_posts_from_cache', [$this, 'sync_rocket_settings']);      // Single Postlar
            add_action('update_option_options_exclude_taxonomies_from_cache', [$this, 'sync_rocket_settings']);
        }

        public static function get_instance($cache = true) {
            if (null === self::$instance) {
                self::$instance = new self($cache);
            }
            return self::$instance;
        }

        private function detect_subfolder() {
            $path = parse_url(home_url('/'), PHP_URL_PATH);
            return trim((string)$path, '/');
        }

        public function sync_rocket_settings() {
            delete_transient($this->transient_key);
            $rocket_options = get_option('wp_rocket_settings');
            
            if (is_array($rocket_options)) {
                $dynamic_urls = $this->get_dynamic_urls();
                
                // WP Rocket'in kendi ayarlarıyla bizimkileri birleştir ve güncelle
                $rocket_options['cache_reject_uri'] = array_unique(array_merge(
                    (array)($rocket_options['cache_reject_uri'] ?? []), 
                    $dynamic_urls
                ));

                $rocket_options['preload_excluded_uri'] = array_unique(array_merge(
                    (array)($rocket_options['preload_excluded_uri'] ?? []), 
                    $dynamic_urls
                ));
                
                update_option('wp_rocket_settings', $rocket_options);
                
                if (function_exists('rocket_clean_domain')) {
                    rocket_clean_domain();
                }
            }
        }

        private function get_translated_slug($slug, $lang_code) {
            if (!defined('ENABLE_MULTILANGUAGE') || !ENABLE_MULTILANGUAGE) return $slug;
            switch (ENABLE_MULTILANGUAGE) {
                case 'polylang':
                    if (function_exists('pll_translate_string')) return pll_translate_string($slug, $lang_code);
                    break;
                case 'wpml':
                    if (function_exists('icl_t')) {
                        $translated = icl_t('WordPress', 'URL slug: ' . $slug, $slug);
                        return ($translated === $slug) ? icl_t('WordPress', $slug, $slug) : $translated;
                    }
                    break;
            }
            return $slug;
        }

        /**
         * Akıllı Regex Oluşturucu
         * $type: 'archive' (sadece ana sayfa), 'single' (sadece alt sayfalar), 'tax' (hepsi)
         */
        private function build_regex($slug, $lang_prefix = '', $type = 'tax') {
            $slug = trim($slug, '/');
            if (!$slug) return '';
            
            $parts = array_filter([$lang_prefix, $slug]);
            $base_url = '/' . implode('/', $parts) . '/';

            switch ($type) {
                case 'archive':
                    return $base_url . '$'; // Sadece tam eşleşme (Arşiv ana sayfası)
                case 'single':
                    return $base_url . '(.+)'; // Sadece altındaki sayfalar (Detay sayfaları)
                case 'tax':
                default:
                    return $base_url . '(.*)'; // Her şey
            }
        }

        public function get_dynamic_urls() {
            if ($this->cache) {
                $cached = get_transient($this->transient_key);
                if ($cached !== false) return $cached;
            }

            $urls = [];
            $is_ml = (defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE);
            $langs = $is_ml ? (Data::get('languages') ?? []) : [['name' => '']];
            $default_lang = Data::get('language_default') ?? '';
            $sub = $this->subfolder;
            $sub_prefix = !empty($sub) ? '/' . $sub : '';

            // 1. ARCHIVE EXCLUDES (Sadece Arşiv Sayfaları)
            $excluded_archives = (array) get_option('options_exclude_post_types_from_cache', []);
            foreach ($excluded_archives as $pt) {
                $obj = get_post_type_object($pt);
                if (!$obj) continue;
                $base = $obj->rewrite['slug'] ?? $pt;
                foreach ($langs as $l) {
                    $code = $l['name'] ?? '';
                    $lang_prefix = ($code === $default_lang ? '' : $code);
                    $regex = $this->build_regex($this->get_translated_slug($base, $code), $lang_prefix, 'archive');
                    $urls[] = $sub_prefix . $regex;
                }
            }

            // 2. SINGLE POST EXCLUDES (Sadece Detay Sayfaları)
            $excluded_singles = (array) get_option('options_exclude_posts_from_cache', []);
            foreach ($excluded_singles as $pt) {
                $obj = get_post_type_object($pt);
                if (!$obj) continue;
                $base = $obj->rewrite['slug'] ?? $pt;
                foreach ($langs as $l) {
                    $code = $l['name'] ?? '';
                    $lang_prefix = ($code === $default_lang ? '' : $code);
                    $regex = $this->build_regex($this->get_translated_slug($base, $code), $lang_prefix, 'single');
                    $urls[] = $sub_prefix . $regex;
                }
            }

            // 3. TAXONOMY EXCLUDES (Hepsi)
            $excluded_taxs = (array) get_option('options_exclude_taxonomies_from_cache', []);
            foreach ($excluded_taxs as $tax) {
                $obj = get_taxonomy($tax);
                if (!$obj) continue;
                $base = $obj->rewrite['slug'] ?? $tax;
                foreach ($langs as $l) {
                    $code = $l['name'] ?? '';
                    $lang_prefix = ($code === $default_lang ? '' : $code);
                    $regex = $this->build_regex($this->get_translated_slug($base, $code), $lang_prefix, 'tax');
                    $urls[] = $sub_prefix . $regex;
                }
            }

            $urls = array_filter(array_unique($urls));
            if ($this->cache) {
                set_transient($this->transient_key, $urls, DAY_IN_SECONDS);
            }
            return $urls;
        }

        public function modify_cache_reject_urls($urls, $option) {
            return array_unique(array_merge((array)$urls, $this->get_dynamic_urls()));
        }
    }
    // Sınıfı bir kez başlat
    WP_Rocket_Dynamic_Excludes::get_instance(false);
}

/**
 * WP Rocket Delay JS özelliğini kullanıcı etkileşimi olmadan 
 * belirli bir süre sonra tetiklemek için kullanılan fonksiyon.
 */
add_action('wp_head', function() {
    // 1. WP Rocket yüklü mü ve Delay JS özelliği aktif mi kontrol et
    if ( function_exists('get_rocket_option') && get_rocket_option('js_defer') && get_rocket_option('js_defer') == "yes" ) {
        ?>
        <script id="wp-rocket-auto-trigger">
            (function() {
                // Ayarlanan süre (milisaniye cinsinden). 
                // Örn: 3000 = 3 saniye. Çok düşük yaparsanız PageSpeed puanınız düşebilir.
                var autoTriggerDelay = 3000; 

                var triggerEvents = function() {
                    // WP Rocket'ın dinlediği standart olayları taklit et
                    // Çoğu sürüm için 'touchstart' veya 'mousemove' yeterlidir
                    var eventNames = ['touchstart', 'mousemove', 'keydown', 'scroll'];
                    
                    eventNames.forEach(function(name) {
                        window.dispatchEvent(new Event(name));
                    });
                    
                    debugJS('WP Rocket: JS yüklemesi zaman aşımı nedeniyle tetiklendi.');
                };

                // Sayfa yüklendikten sonra zamanlayıcıyı başlat
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(triggerEvents, autoTriggerDelay);
                    });
                } else {
                    setTimeout(triggerEvents, autoTriggerDelay);
                }
            })();
        </script>
        <?php
    }
}, 1); // 1 önceliği ile head'in en üstüne eklemeye çalışır




/**
 * WP Rocket Nulled Veritabanı Kilidi Engelleyici (Mermi Modu)
 */
//if(is_admin()){
    add_action('init', function() {
        // WordPress'in tüm init aksiyonlarını tarayalım
        global $wp_filter;
        if (isset($wp_filter['init'])) {
            foreach ($wp_filter['init']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $callback) {
                    // Sınıfın namespace ve metod adını kontrol ediyoruz
                    // Namespace: WP_Rocket\Engine\License\API\UserClient
                    // Metod: force_license_activation
                    if (
                        is_array($callback['function']) && 
                        is_object($callback['function'][0]) &&
                        get_class($callback['function'][0]) === 'WP_Rocket\Engine\License\API\UserClient' &&
                        $callback['function'][1] === 'force_license_activation'
                    ) {
                        // Bulduğumuz an o aksiyonu siliyoruz
                        remove_action('init', $callback['function'], $priority);
                    }
                }
            }
        }
    }, 0); // WP Rocket'ten daha önce (0 önceliğiyle) çalışıp onu listeden siliyoruz.    
//}


/*
        // clean the default domain
        rocket_clean_domain();

        // clean the French domain only
        rocket_clean_domain( 'fr' );

        // clean the home
        rocket_clean_home();

        // clean the French home page
        rocket_clean_home( 'fr' );

        //clean post with ID 5
          rocket_clean_post( 5 );

        // clean http://your-site.com/contact/
        rocket_clean_files( 'http://your-site.com/contact/' );

        // clean http://your-site.com/contact and http://your-site.com/legal/
        $clear_urls = array(
            'http://your-site.com/contact/',
            'http://your-site.com/legal/'
        );
        rocket_clean_files( $clear_urls );

        // remove minified filed in min folder
        rocket_clean_minify();
    */

class WP_Rocket_Manifest_Manager {

    private static $instance = null;
    private $manifest = [];
    private static $processed_items = [];
    private static $object_id_to_process = null;
    private static $is_taxonomy_process = false;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_manifest();
        $this->init_hooks();
    }

    private function define_manifest() {
        // Varsayılan boş yapı
        $this->manifest = [
            'post'     => [],
            'taxonomy' => []
        ];

        // ACF'den repeater verisini çek (Field adın: cache_manifest)
        $acf_data = QueryCache::get_field('cache_manifest', 'options');

        if (empty($acf_data) || !is_array($acf_data)) {
            return;
        }

        foreach ($acf_data as $row) {
            $type_group = $row['type']; // 'post_type' veya 'taxonomy' geliyor

            // Gruplandırma anahtarını belirle (ACF'den post_type gelirse biz 'post' yapıyoruz)
            $main_key = ($type_group === 'post_type') ? 'post' : 'taxonomy';
            
            // Slug'ı belirle (post_type ise 'magazalar', taxonomy ise 'magaza-tipi' gibi)
            $slug = ($type_group === 'post_type') ? $row['post_type'] : $row['taxonomy'];

            if (empty($slug)) continue;

            // Manifest formatına dönüştür
            $this->manifest[$main_key][$slug] = [
                'self'     => (bool) $row['self'],
                'home'     => (bool) $row['home'],
                'archives' => (bool) ($row['archive'] ?? false), // Archive sadece post'ta var
                'pages'    => is_array($row['pages']) ? $row['pages'] : [],
                'terms'    => is_array($row['terms']) ? $row['terms'] : []
            ];
        }
    }

    private function init_hooks() {

        // WP Rocket Varsayılan Temizlik Hooklarını Kapat
        /*$hooks_to_remove = [
            'wp_update_nav_menu', 
            'update_option_sidebars_widgets', 
            'update_option_category_base', 
            'update_option_tag_base', 
            'permalink_structure_changed', 
            'customize_save', 
            'switch_theme'
        ];
        foreach ($hooks_to_remove as $hook) {
            remove_action($hook, 'rocket_clean_domain');
        }*/
        
        add_filter( 'rocket_preload_exclude', array( $this, 'exclude_based_on_rocket_settings' ), 10, 2 );

        remove_action('post_updated', 'rocket_clean_post_cache_on_slug_change', 10);

        // WP Rocket Otomatik Purge'ü Filtrele
        add_filter('rocket_pre_purge_post', function($purge, $post_id) {
            if (isset($_GET['meta-box-loader']) || isset($_GET['meta-box-loader-nonce'])) return false;
            return $purge;
        }, 10, 2);

        // Ayarları Zorla
        add_filter('wp_rocket_cache_lifespan', '__return_false', 999);
        add_filter('pre_get_rocket_option_purge_cron_interval', function() { return 0; });
        add_filter('rocket_cache_purge_all', '__return_false', 999);
        add_filter('rocket_purge_term_on_update', '__return_false', 999);
        add_filter('do_rocket_generate_caching_files', '__return_true', 999);
        add_filter('do_run_rocket_sitemap_preload', '__return_false', 999);
        add_filter('do_run_rocket_bot', '__return_false', 999);
        
        // Mobile Kapat
        update_rocket_option('cache_mobile', 0);
        update_rocket_option( 'do_caching_mobile_files', 0);
        add_filter('get_rocket_option_wp_rocket_cache_mobile', '__return_false', 999);

        // Yakalayıcı Hooklar
        add_action('save_post', [$this, 'catch_post_for_shutdown'], 999, 1);
        add_action('before_delete_post', [$this, 'catch_delete_post_for_shutdown'], 10, 1);
        add_action('pre_delete_term', [$this, 'catch_delete_term_for_shutdown'], 10, 2);

        add_action('transition_post_status', [$this, 'catch_status_change'], 10, 3);

        add_action('edited_term', [$this, 'catch_term_for_shutdown'], 999, 3);
        add_action('create_term', [$this, 'catch_term_for_shutdown'], 999, 3);
        
        add_action('shutdown', [$this, 'execute_shutdown_purge']);
    }

    /**
     * WP Rocket ayarlarındaki "Never Cache URL(s)" listesini çekip
     * Preload botunu bu URL'lerden uzak tutar.
    */
    public function exclude_based_on_rocket_settings( $is_excluded, $url ) {
        // Eğer başka bir sebeple zaten dışlanmışsa devam et
        if ( $is_excluded ) {
            return $is_excluded;
        }

        // WP Rocket'ın "Asla Cache'leme" (cache_reject_uri) listesini alıyoruz
        $never_cache_list = get_rocket_option( 'cache_reject_uri', [] );

        if ( ! empty( $never_cache_list ) && is_array( $never_cache_list ) ) {
            foreach ( $never_cache_list as $rejected_uri ) {
                // Boş satırları atla
                if ( empty( $rejected_uri ) ) continue;

                // WP Rocket bu listeyi Regex formatında tutar (örn: /magazalar/(.*))
                // Biz bunu güvenli bir şekilde URL içinde arıyoruz
                $pattern = str_replace( '(.*)', '', $rejected_uri );
                $pattern = trim( $pattern, '/' );

                if ( ! empty( $pattern ) && strpos( $url, $pattern ) !== false ) {
                    return true; // Eşleşme varsa botu durdur
                }
            }
        }

        return $is_excluded;
    }

    public function catch_delete_post_for_shutdown($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        
        // Nesne silinmeden önce tipini almamız şart
        self::$object_id_to_process = $post_id;
        self::$is_taxonomy_process = false;
        
        $this->log("🗑️ [Delete Detect] Post ID: $post_id siliniyor, manifest tetiklenecek.");
    }

    /**
     * Term silinmeden hemen önce taxonomy bilgisini yakalar
     */
    public function catch_delete_term_for_shutdown($term_id, $taxonomy) {
        self::$object_id_to_process = $term_id;
        self::$is_taxonomy_process = true;
        
        $this->log("🗑️ [Delete Detect] Term ID: $term_id ($taxonomy) siliniyor, manifest tetiklenecek.");
    }

    /**
     * Yazı durumu değiştiğinde tetiklenir.
     * Örn: Yayından kaldırılınca arşivlerin önbelleği temizlenmeli.
     */
    public function catch_status_change($new_status, $old_status, $post) {
        // Eğer ikisi de aynıysa veya revizyonsa siktir et
        if ($new_status === $old_status || wp_is_post_revision($post->ID)) {
            return;
        }

        // Durumlardan biri mutlaka 'publish' olmalı ki cache'i ilgilendirsin
        // (Ya yayına girdi, ya yayından çıktı)
        if ($new_status === 'publish' || $old_status === 'publish') {
            self::$object_id_to_process = $post->ID;
            self::$is_taxonomy_process = false;
            
            $this->log("🔄 [Status Change] {$post->post_type} (ID: {$post->ID}) durumu {$old_status} -> {$new_status} oldu.");
        }
    }

    public function catch_post_for_shutdown($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (get_post_status($post_id) !== 'publish') return;
        if (isset($_GET['meta-box-loader'])) return;
        
        self::$object_id_to_process = $post_id;
        self::$is_taxonomy_process = false;
    }

    public function catch_term_for_shutdown($term_id, $tt_id, $taxonomy) {
        self::$object_id_to_process = $term_id;
        self::$is_taxonomy_process = true;
    }

    public function execute_shutdown_purge() {
        if (null === self::$object_id_to_process) return;
        
        $obj_id = self::$object_id_to_process;
        $is_tax = self::$is_taxonomy_process;
        $key = ($is_tax ? 'tax_' : 'post_') . $obj_id;

        if (in_array($key, self::$processed_items)) return;
        self::$processed_items[] = $key;

        $this->handle_purge($obj_id, $is_tax);
    }

    public function handle_purge($object_id, $is_taxonomy = false) {
        if (!$object_id) return;

        if (class_exists('SaltHareket\Theme')) {
            \SaltHareket\Theme::getInstance()->language_settings();
        }

        $type_key = $is_taxonomy ? 'taxonomy' : 'post';
        $slug_or_type = $is_taxonomy ? get_term($object_id)->taxonomy : get_post_type($object_id);
        
        $excluded_post_types = (array) QueryCache::get_field("exclude_post_types_from_cache", "options");
        $excluded_taxonomies = (array) QueryCache::get_field("exclude_taxonomies_from_cache", "options");
        
        $rules = $this->manifest[$type_key][$slug_or_type] ?? null;
        if (!$rules) return;

        // --- MANIFEST MANIPULASYONU ---
        $is_excluded = $is_taxonomy ? in_array($slug_or_type, $excluded_taxonomies) : in_array($slug_or_type, $excluded_post_types);
        
        if ($is_excluded) {
            $rules['self'] = false;
            if (isset($rules['archives'])) $rules['archives'] = false;
            $this->log("🚫 [Exclude Active] $slug_or_type kısıtlandı.");
        } else {
            $rules['self'] = $rules['self'] ?? true;
        }

        $this->log("🚀 [Purge Start] $type_key: $slug_or_type | ID: $object_id");
        $purge_data = [];

        // 1. Self
        if ($rules['self'] === true) {
            $purge_data = array_merge($purge_data, $is_taxonomy ? $this->get_term_all_data($object_id, $slug_or_type) : $this->get_post_all_data($object_id));
        }

        // 2. Pages
        $purge_data = array_merge($purge_data, $this->process_pages($rules['pages'] ?? []));

        // 3. Terms
        $purge_data = array_merge($purge_data, $this->process_terms($rules['terms'] ?? [], $excluded_taxonomies));

        // 4. Archives (Sadece Postlar için)
        if (!$is_taxonomy && ($rules['archives'] ?? false) === true) {
            $purge_data = array_merge($purge_data, $this->get_archive_all_data($slug_or_type));
        }

        // 5. Home
        if (($rules['home'] ?? false) === true) {
            $purge_data = array_merge($purge_data, $this->get_home_all_data());
        }

        $this->execute_purge_process($purge_data);
    }

    private function execute_purge_process($purge_items) {
        if (empty($purge_items)) return;

        $temp_urls = [];
        $final_items = [];
        foreach ($purge_items as $item) {
            if (!in_array($item['url'], $temp_urls)) {
                $temp_urls[] = $item['url'];
                $final_items[] = $item;
            }
        }

        if (function_exists('rocket_clean_files')) {
            rocket_clean_files($temp_urls);
            error_log(print_r($temp_urls, true));
        }

        foreach ($final_items as $item) {
            $url = $item['url'];
            $lang = $item['lang'];

            wp_remote_get($url, [
                'timeout'   => 25,
                'blocking'  => true,
                'headers'   => [
                    'User-Agent'          => 'WP Rocket/Preload',
                    'X-WP-Rocket-Preload' => 'yes',
                    'Accept-Language'     => $lang . ',* node;q=0.9'
                ],
                'cookies'   => ['pll_language' => $lang]
            ]);
            $this->log("✅ Preload [$lang]: $url");
            usleep(500000);
        }
    }

    private function get_post_all_data($post_id) {
        $data = [];
        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($post_id);
            foreach ($translations as $lang => $t_post_id) {
                $data[] = ['url' => get_permalink($t_post_id), 'lang' => $lang];
            }
        } else {
            $data[] = ['url' => get_permalink($post_id), 'lang' => 'tr'];
        }
        return $data;
    }

    private function get_term_all_data($term_id, $taxonomy) {
        $data = [];
        if (function_exists('pll_get_term_translations')) {
            $translations = pll_get_term_translations($term_id);
            foreach ($translations as $lang => $t_id) {
                $data[] = ['url' => get_term_link($t_id, $taxonomy), 'lang' => $lang];
            }
        } else {
            $data[] = ['url' => get_term_link($term_id, $taxonomy), 'lang' => 'tr'];
        }
        return $data;
    }

    private function process_pages($pages) {
        $data = [];
        foreach ($pages as $id_or_slug) {
            $page = is_numeric($id_or_slug) ? get_post($id_or_slug) : get_page_by_path($id_or_slug);
            if ($page) {
                $data = array_merge($data, $this->get_post_all_data($page->ID));
            }
        }
        return $data;
    }

    private function process_terms($term_identifiers, $excluded_taxonomies = []) {
        $data = [];
        
        // Eğer manifestten boş veya geçersiz veri geldse çık
        if (empty($term_identifiers) || !is_array($term_identifiers)) return $data;

        foreach ($term_identifiers as $id_or_slug) {
            // 1. Terimi bul (Slug mı ID mi kontrolüyle)
            // Not: Terimin hangi taksonomide olduğunu bilmediğimiz için 
            // burada en sağlam yol 'get_term_by' veya ID ise direkt 'get_term' kullanmaktır.
            
            $term = null;
            if (is_numeric($id_or_slug)) {
                $term = get_term($id_or_slug);
            } else {
                // Slug üzerinden ararken taksonomi belirtmek gerektiği için 
                // tüm taksonomileri tarayan bir mantık gerekebilir veya 
                // global bir arama yaptırabiliriz.
                // Ama WP'de en hızlısı term_id üzerinden gitmektir.
                $term = $this->find_term_by_slug_globally($id_or_slug);
            }

            if ($term && !is_wp_error($term)) {
                $taxonomy = $term->taxonomy;

                // 2. Excluded kontrolünü terimin kendi taksonomisi üzerinden yap
                if (in_array($taxonomy, $excluded_taxonomies)) {
                    $this->log("⚠️ [Term Skip] {$term->slug} ({$taxonomy}) excluded listesinde.");
                    continue;
                }

                // 3. Veriyi topla
                $data = array_merge($data, $this->get_term_all_data($term->term_id, $taxonomy));
            }
        }
        return $data;
    }

    /**
     * Yardımcı fonksiyon: Slug'ı verilen terimi hangi taksonomide olursa olsun bulur.
     */
    private function find_term_by_slug_globally($slug) {
        // WP'de slug'lar genelde benzersizdir (farklı taksonomilerde aynı slug olsa da ilkini döner)
        // Eğer sistemde çakışma riski varsa bu kısım özelleştirilebilir.
        $taxonomies = get_taxonomies(['public' => true]);
        foreach ($taxonomies as $tax) {
            $term = get_term_by('slug', $slug, $tax);
            if ($term) return $term;
        }
        return null;
    }

    private function get_archive_all_data($post_type) {
        $data = [];
        if (function_exists('pll_languages_list')) {
            foreach (pll_languages_list() as $lang) {
                $data[] = [
                    'url'  => trailingslashit(pll_home_url($lang)) . $post_type . '/',
                    'lang' => $lang
                ];
            }
        }
        return $data;
    }

    private function get_home_all_data() {
        $data = [];
        if (function_exists('pll_languages_list')) {
            foreach (pll_languages_list() as $lang) {
                $data[] = ['url' => pll_home_url($lang), 'lang' => $lang];
            }
        }
        return $data;
    }

    private function log($message) {
        error_log("[RocketManifest] " . $message);
    }
}


/**
 * WP Rocket'in Veritabanını Taciz Etmesini Engelleyen Balyoz
 */
// 1. Müşteri verisi transient güncellemesini engelle
add_filter('pre_update_option__transient_wp_rocket_customer_data', function($value, $old_value) {
    return $old_value; // Yeni değeri reddet, eskisi neyse o kalsın
}, 10, 2);

add_filter('pre_update_option__transient_timeout_wp_rocket_customer_data', function($value, $old_value) {
    return $old_value; // Timeout süresini de sabit tut, sürekli UPDATE atmasın
}, 10, 2);

// 2. Lisans kontrolü seçeneğini '0' (sorun yok) olarak dondur
add_filter('pre_update_option_wp_rocket_no_licence', function($value, $old_value) {
    return '0'; // Ne gelirse gelsin veritabanına '0' yaz (veya yazmaya çalışma)
}, 10, 2);

// 3. get_option çağrıldığında da direkt '0' döndür ki DB'ye gitmesin
add_filter('pre_option_wp_rocket_no_licence', function() {
    return '0';
});