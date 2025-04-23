<?php

class QueryCache {

    /** @var bool Tüm cache sistemini devre dışı bırakır */
    public static $disable_caching = true;

    /** @var array|null ACF option cache */
    protected static $bulk_options = null;

    public function __construct() {

        if (self::$disable_caching) return;

        add_action('save_post', [$this, 'handle_post_change'], 20);
        add_action('delete_post', [$this, 'handle_post_change'], 20);
        add_action('trashed_post', [$this, 'handle_post_change'], 20);
        add_action('untrashed_post', [$this, 'handle_post_change'], 20);
        add_action('acf/update_value', [$this, 'clear_cached_option'], 19, 3);
        add_action('acf/save_post', [$this, 'rebuild_salt_options_cache'], 99);
        add_filter('acf/load_value', [__CLASS__, 'load_value_to_cache'], 20, 3);/**/
        
    }

    /** 🔥 WP_Query sonuçlarını cache'li veya cache'siz getirir */
        /**
     * Universal cached query getter with flexible modes.
     * Modes:
     * - 'object': returns full WP_Query object (for have_posts(), etc.)
     * - 'posts': returns only post array (faster, for get_posts/timber)
     * - 'data': returns ['posts', 'found_posts', 'max_num_pages']
     */
    public static function get_cached_query($args = [], $mode = 'object') {

        return new WP_Query($args);

        if (self::$disable_caching) {
            return self::run_query($args, $mode);
        } 

        $post_type = isset($args['post_type']) ? (is_array($args['post_type']) ? implode('_', $args['post_type']) : $args['post_type']) : 'any';
        $cache_key = 'custom_query_' . $post_type . '_' . $mode . '_' . md5(serialize($args));
        $cached = get_transient($cache_key);

        if ($cached === false) {
            $cached = self::run_query($args, $mode);
            set_transient($cache_key, $cached, HOUR_IN_SECONDS);
        }

        return $cached;
    }

    /** 🔎 WP_Query çalıştırır ve istenen formata çevirir */
    protected static function run_query($args, $mode) {
        $query = new WP_Query($args);

        return match ($mode) {
            'posts' => $query->posts,
            'data' => [
                'posts' => $query->posts,
                'found_posts' => $query->found_posts,
                'max_num_pages' => $query->max_num_pages,
                'paged' => $query->paged,
                'posts_per_page' => $query->posts_per_page,
                'is_singular' => $query->is_singular,
                'post_count' => $query->post_count,
            ],
            default => $query,
        };
    }

    /** 🧹 Belirli post_type için query cache'i temizle */
    public static function clear_query_cache_by_post_type($post_type) {
        if (self::$disable_caching) return;
        global $wpdb;
        $escaped = esc_sql('custom_query_' . $post_type . '_%');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_{$escaped}'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_{$escaped}'");
    }

    /** ✅ Tek bir option değerini cache'li ya da doğrudan getirir */
    public static function get_cached_option($key, $method = 'auto') {

        return get_field($key, 'option');

        if (self::$disable_caching) {
            return ($method === 'acf' || ($method === 'auto' && function_exists('get_field')))
                ? get_field($key, 'option')
                : get_option("options_{$key}");
        }

        if (self::$bulk_options === null) {
            self::$bulk_options = get_option('salt_options', []);
        }

        if (isset(self::$bulk_options[$key])) {
            return self::$bulk_options[$key];
        }

        $value = ($method === 'acf' || ($method === 'auto' && function_exists('get_field')))
            ? get_field($key, 'option')
            : get_option("options_{$key}");

        self::$bulk_options[$key] = $value;
        update_option('salt_options', self::$bulk_options);

        return $value;
    }

    /** 🎯 ACF'den gelen field'ı RAM cache'e alır */
    public static function load_value_to_cache($value, $post_id, $field) {
        if (self::$disable_caching) return $value;

        $excluded_types = ['repeater', 'flexible_content', 'gallery', 'post_object', 'file', 'image'];
        if (!is_scalar($value) && !is_array($value)) return $value;
        if (isset($field['type']) && in_array($field['type'], $excluded_types)) return $value;

        $key = "acf_field_{$field['name']}_{$post_id}_1";
        wp_cache_set($key, $value, 'acf');

        return $value;
    }

    /** 🧠 get_field() yerine kullanılabilir cache destekli versiyon */
    public static function get_cached_field($selector, $post_id = false, $format_value = true) {
        if (is_object($post_id) && isset($post_id->ID)) {
            $post_id = $post_id->ID;
        }

        $post_id = $post_id ?: get_the_ID();
        $cache_key = "acf_field_{$selector}_{$post_id}_" . ($format_value ? '1' : '0');

        if (!self::$disable_caching) {
            $cached = wp_cache_get($cache_key, 'acf');
            if ($cached !== false) return $cached;
        }

        $value = get_field($selector, $post_id);

        if (!self::$disable_caching && (is_scalar($value) || (is_array($value) && count($value) < 20))) {
            wp_cache_set($cache_key, $value, 'acf');
        }

        return $value;
    }

    /** 🧽 Tüm cache'i temizler */
    public static function delete_cache() {
        if (self::$disable_caching) return;

        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_custom_query_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_custom_query_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_acf_option_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_acf_option_%'");

        delete_option('salt_options');

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /** 🧹 ACF option kaydında cache temizler */
    public function clear_cached_option($value, $post_id, $field) {
        if ($post_id !== 'options' || self::$disable_caching) return $value;

        $key = $field['name'];
        delete_transient('acf_option_' . $key);
        delete_option('salt_options');

        return $value;
    }

    /** ♻️ ACF option cache yeniden kurar */
    public function rebuild_salt_options_cache($post_id) {
        if ($post_id !== 'options' || self::$disable_caching) return;

        $bulk = [];
        foreach (get_fields('option') as $k => $v) {
            $bulk[$k] = $v;
        }
        update_option('salt_options', $bulk);
    }

    /** 🔔 post değiştiğinde cache siler */
    public function handle_post_change($post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type) {
            self::clear_query_cache_by_post_type($post_type);
        }
    }
}

new QueryCache();