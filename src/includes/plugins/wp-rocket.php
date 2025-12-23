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
            error_log("wprocket_is_cached();");
            return true;
        }
    }        
    return false;
}
function is_wp_rocket_crawling() {
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'WP Rocket') !== false) {
        error_log("is_wp_rocket_crawling();");
        return true; // WP Rocket bu sayfayı önbelleğe almak için ziyaret ediyor
    }
    return false;
}

add_filter('rocket_buffer', function ($buffer) {
    if (!is_admin()) {
        return str_replace('"cached":""', '"cached":"1"', $buffer);
    }
    return $buffer;
});

if(!class_exists("WP_Rocket_Dynamic_Excludes")){
    class WP_Rocket_Dynamic_Excludes{

        private $home_url;
        private $subfolder;
        private $transient_key = 'rocket_reject_urls';
        private $cache;

        public function __construct($cache = true) {
            $this->home_url = rtrim(home_url('/'), '/');
            $this->cache = (bool)$cache;
            $this->subfolder = $this->detect_subfolder();
            add_filter('pre_get_rocket_option_cache_reject_uri', [$this, 'modify_cache_reject_urls'], 10, 2);
            add_action('update_option_options_exclude_post_types_from_cache', [$this, 'clear_transient_cache'], 10, 3);
            add_action('update_option_options_exclude_taxonomies_from_cache', [$this, 'clear_transient_cache'], 10, 3);
        }

        // Subfolder varsa tespit et, yoksa boş
        private function detect_subfolder() {
            $path = parse_url($this->home_url, PHP_URL_PATH);
            $folder = trim($path, '/');
            return $folder ?: '';
        }

        public function clear_transient_cache($option, $old_value, $value) {
            delete_transient($this->transient_key);
        }

        private function build_regex($slug, $lang_prefix = '') {
            $slug = trim($slug, '/');
            $parts = [];

            // Burada **runtime** subfolder eklenmeyecek, sadece regex parçaları
            if ($lang_prefix) $parts[] = $lang_prefix;
            if ($slug) $parts[] = $slug;

            return '/' . implode('/', $parts) . '/(.*)';
        }

        private function post_type_slugs() {
            $slugs = [];
            $excluded_post_types = (array) get_option('options_exclude_post_types_from_cache', []);
            foreach ($excluded_post_types as $pt) {
                $obj = get_post_type_object($pt);
                if ($obj && !empty($obj->rewrite['slug'])) {
                    $slugs[] = $obj->rewrite['slug'];
                }
            }
            return $slugs;
        }

        private function taxonomy_slugs() {
            $slugs = [];
            $excluded_taxonomies = (array) get_option('options_exclude_taxonomies_from_cache', []);
            foreach ($excluded_taxonomies as $tax) {
                $obj = get_taxonomy($tax);
                if ($obj && !empty($obj->rewrite['slug'])) {
                    $slugs[] = $obj->rewrite['slug'];
                }
            }
            return $slugs;
        }

        public function get_dynamic_urls() {
            // Cache aktifse ve transient varsa
            if ($this->cache) {
                $cached = get_transient($this->transient_key);
                if ($cached !== false) return $cached;
            }

            $slugs = array_merge($this->post_type_slugs(), $this->taxonomy_slugs());
            $urls = [];
            $langs = $GLOBALS['languages'] ?? [];
            $default_lang = $GLOBALS['language_default'] ?? '';

            foreach ($slugs as $slug) {
                $urls[] = $this->build_regex($slug);
                foreach ($langs as $lang_data) {
                    $lang = $lang_data['name'] ?? '';
                    if ($lang && $lang !== $default_lang) {
                        $urls[] = $this->build_regex($slug, $lang);
                    }
                }
            }

            $urls = array_filter(array_unique($urls));

            if ($this->cache) {
                set_transient($this->transient_key, $urls, YEAR_IN_SECONDS);
            }

            return $urls;
        }

        public function modify_cache_reject_urls($urls, $option) {
            $urls = is_array($urls) ? $urls : [];
            $dynamic_urls = $this->get_dynamic_urls();
            if (!empty($this->subfolder)) {
                foreach ($dynamic_urls as &$url) {
                    $url = '/' . $this->subfolder . $url;
                }
                unset($url);
            }
            $merged = array_unique(array_merge($urls, $dynamic_urls));
            //error_log(print_r($merged, true));
            return $merged;
        }
    }
    new WP_Rocket_Dynamic_Excludes(true);
};

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
                    
                    console.log('WP Rocket: JS yüklemesi zaman aşımı nedeniyle tetiklendi.');
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
