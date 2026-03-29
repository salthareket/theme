<?php

/**
 * Favorites System
 *
 * Supports logged-in users (DB storage) and guests (cookie storage).
 * On login/register, cookie favorites are merged into user account.
 * Supports post, term, and user object types.
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Get current user's favorites (auto-detects logged in vs guest)
 * $favs = new Favorites();
 * $favs->favorites;                    // [12, 45, 78]
 * $favs->count();                      // 3
 *
 * // Add / Remove / Check
 * $favs->add(123);                     // adds post ID 123
 * $favs->remove(123);                  // removes it
 * $favs->exists(123);                  // true/false
 *
 * // For specific user
 * $favs = new Favorites(5);            // user ID 5's favorites
 *
 * // Set type for user favorites (affects count meta)
 * $favs = new Favorites();
 * $favs->type = 'user';               // 'post' (default) or 'user'
 *
 * // Cookie→DB merge happens automatically on wp_login
 * // Guest adds favorites → logs in → cookie merged → cookie cleared
 *
 * // Type filtering (respects FAVORITE_TYPES from admin settings)
 * // If admin set Favorite Types → Post Types: [product, product_variation]
 * // then only those post types can be favorited:
 * $favs->add(123);                     // only works if post 123 is a product
 * $favs->add(456, 'user');             // only works if user roles match allowed roles
 * $favs->add(789, 'term');             // only works if term taxonomy is in allowed taxonomies
 *
 * // Get favorites filtered by type
 * $favs->get_posts('product');          // only product favorites
 * $favs->get_posts('user');             // only user favorites
 *
 * ──────────────────────────────────────────────────────────
 */
class Favorites {

    const COOKIE_NAME = 'wpcf_favorites';
    const META_KEY = '_favorites';
    const META_JSON_KEY = 'wpcf_favorites';
    const COUNT_META_KEY = 'wpcf_favorites_count';
    const COOKIE_LIFETIME = 30 * DAY_IN_SECONDS;

    /** @var int[] */
    public $favorites = [];

    /** @var int */
    public $user_id = 0;

    /** @var string 'post' or 'user' */
    public $type = 'post';

    /** @var int Items per page for get_posts pagination (0 = no pagination) */
    public $per_page = 0;

    public function __construct($user_id = 0) {
        $this->user_id = (int) $user_id;
        $this->load();
    }

    // ─── CORE: LOAD ──────────────────────────────────────

    /**
     * Load favorites from DB (logged in) or cookie (guest)
     */
    private function load() {
        if (is_user_logged_in() || $this->user_id > 0) {
            if (!$this->user_id) {
                $this->user_id = get_current_user_id();
            }
            $this->favorites = $this->load_from_db();

            // If DB empty but cookie has data, merge cookie into DB
            if (empty($this->favorites)) {
                $cookie_favs = $this->load_from_cookie();
                if (!empty($cookie_favs)) {
                    $this->favorites = $cookie_favs;
                    $this->save_to_db();
                }
            }
            $this->clear_cookie();
        } else {
            $this->favorites = $this->load_from_cookie();
        }
    }

    private function load_from_db() {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $this->user_id,
            self::META_KEY
        ));
        return array_values(array_filter(array_map('intval', $results)));
    }

    private function load_from_cookie() {
        if (!isset($_COOKIE[self::COOKIE_NAME]) || $_COOKIE[self::COOKIE_NAME] === '') return [];
        $raw = urldecode($_COOKIE[self::COOKIE_NAME]);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        return array_values(array_filter(array_map('intval', $decoded)));
    }

    // ─── CORE: SAVE ──────────────────────────────────────

    private function save() {
        if ($this->user_id) {
            $this->save_to_db();
        }
        $this->save_to_cookie();
    }

    private function save_to_db() {
        delete_user_meta($this->user_id, self::META_KEY);
        foreach ($this->favorites as $fav_id) {
            add_user_meta($this->user_id, self::META_KEY, $fav_id);
        }
        update_user_meta($this->user_id, self::META_JSON_KEY, json_encode($this->favorites, JSON_NUMERIC_CHECK));
    }

    private function save_to_cookie() {
        $json = json_encode(array_values($this->favorites), JSON_NUMERIC_CHECK);
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $json, time() + self::COOKIE_LIFETIME, COOKIEPATH, COOKIE_DOMAIN);
        }
        $_COOKIE[self::COOKIE_NAME] = $json;
    }

    private function clear_cookie() {
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    // ─── PUBLIC API ──────────────────────────────────────

    /**
     * Add an item to favorites
     * @param int $id Post ID, Term ID, or User ID
     * @param string $object_type 'post', 'term', or 'user' — auto-detected if not provided
     */
    public function add($id = 0, $object_type = '') {
        $id = (int) $id;
        if ($id <= 0 || $this->exists($id)) return;

        // Validate against allowed favorite types
        if (!$this->is_allowed_type($id, $object_type)) return;

        $this->favorites[] = $id;
        $this->save();
        $this->increment_count($id, 1);
    }

    /**
     * Remove an item from favorites
     */
    public function remove($id = 0) {
        $id = (int) $id;
        if (!$this->exists($id)) return;

        $this->favorites = array_values(array_diff($this->favorites, [$id]));
        $this->save();
        $this->increment_count($id, -1);

        if ($this->user_id) {
            $this->clear_cookie();
        }
    }

    /**
     * Check if an item is in favorites
     */
    public function exists($id = 0) {
        return in_array((int) $id, $this->favorites, true);
    }

    /** @deprecated Use exists() */
    public function exist($id = 0) {
        return $this->exists($id);
    }

    /**
     * Get favorites count
     */
    public function count() {
        return count($this->favorites);
    }

    // ─── MERGE & SYNC ────────────────────────────────────

    /**
     * Merge cookie favorites into user account (called on login)
     */
    public function merge() {
        $cookie_favs = $this->load_from_cookie();
        if (empty($cookie_favs)) return;

        $merged = array_values(array_unique(array_merge($this->favorites, $cookie_favs)));
        if ($merged !== $this->favorites) {
            $this->favorites = $merged;
            $this->save_to_db();
        }
        $this->clear_cookie();
    }

    /**
     * Validate that favorited items still exist
     * Removes deleted posts/users from favorites
     */
    public function check() {
        if (empty($this->favorites)) return;

        if ($this->type === 'user') {
            $args = ['fields' => 'ids', 'include' => $this->favorites];
            $query = new WP_User_Query($args);
            $valid_ids = $query->get_results();
        } else {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($this->favorites), '%d'));
            $valid_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_status = 'publish'",
                ...$this->favorites
            ));
        }

        $valid_ids = array_map('intval', $valid_ids);
        if (count($valid_ids) !== count($this->favorites)) {
            $this->favorites = array_values($valid_ids);
            $this->save();
        }
    }

    /**
     * Replace all favorites (used by calculate/sync operations)
     */
    public function calculate($favorites = []) {
        $this->favorites = array_values(array_filter(array_map('intval', $favorites)));
        if ($this->user_id) {
            $this->save_to_db();
        } else {
            $this->save_to_cookie();
        }
    }

    /**
     * Force update (re-save current state)
     */
    public function update() {
        $this->save();
    }

    // ─── HELPERS ─────────────────────────────────────────

    /**
     * Increment/decrement the favorites count on the target object
     */
    private function increment_count($id, $delta) {
        if ($this->type === 'user') {
            $current = (int) get_user_meta($id, self::COUNT_META_KEY, true);
            update_user_meta($id, self::COUNT_META_KEY, max(0, $current + $delta));
        } else {
            $current = (int) get_post_meta($id, self::COUNT_META_KEY, true);
            update_post_meta($id, self::COUNT_META_KEY, max(0, $current + $delta));
        }
    }

    /**
     * Check if an item's type is allowed in FAVORITE_TYPES config
     * If FAVORITE_TYPES is not defined or empty, allow everything (no restriction)
     */
    private function is_allowed_type($id, $object_type = '') {
        if (!defined('FAVORITE_TYPES')) return true;
        $types = FAVORITE_TYPES;
        if (empty($types['post_types']) && empty($types['taxonomies']) && empty($types['roles'])) return true;

        // Auto-detect object type if not provided
        if (empty($object_type)) {
            $post = get_post($id);
            if ($post) $object_type = 'post';
            elseif (get_userdata($id)) $object_type = 'user';
            else $object_type = 'post'; // fallback
        }

        if ($object_type === 'post') {
            $post_type = get_post_type($id);
            return !empty($types['post_types']) && in_array($post_type, (array) $types['post_types'], true);
        }
        if ($object_type === 'term') {
            $term = get_term($id);
            return $term && !is_wp_error($term) && !empty($types['taxonomies']) && in_array($term->taxonomy, (array) $types['taxonomies'], true);
        }
        if ($object_type === 'user') {
            $user = get_userdata($id);
            if (!$user || empty($types['roles'])) return false;
            return !empty(array_intersect($user->roles, (array) $types['roles']));
        }

        return true;
    }

    /**
     * Get favorited items as objects
     * @param string $filter_type Optional: 'user', or specific post_type like 'product'
     * @param int $page Page number (0 = use class default, 1+ = specific page)
     * @param int|null $per_page Override per_page (null = use $this->per_page)
     * @return array ['items' => WP_Post[]|WP_User[], 'total' => int, 'pages' => int]
     */
    public function get_posts($filter_type = '', $page = 0, $per_page = null) {
        if (empty($this->favorites)) return ['items' => [], 'total' => 0, 'pages' => 0];

        $limit = $per_page ?? $this->per_page;

        if ($this->type === 'user' || $filter_type === 'user') {
            $ids = $this->favorites;
            $total = count($ids);
            if ($page > 0 && $limit > 0) {
                $ids = array_slice($ids, ($page - 1) * $limit, $limit);
            }
            return [
                'items' => !empty($ids) ? get_users(['include' => $ids]) : [],
                'total' => $total,
                'pages' => ($page > 0 && $limit > 0) ? (int) ceil($total / $limit) : 1,
            ];
        }

        $args = [
            'post__in' => $this->favorites,
            'post_type' => 'any',
            'post_status' => 'publish',
            'orderby' => 'post__in',
            'no_found_rows' => true,
        ];

        if (!empty($filter_type) && $filter_type !== 'user') {
            $args['post_type'] = $filter_type;
        }

        if ($page > 0 && $limit > 0) {
            $args['posts_per_page'] = $limit;
            $args['paged'] = $page;
            $args['no_found_rows'] = false; // need total count for pagination
            $query = new WP_Query($args);
            return [
                'items' => $query->posts,
                'total' => $query->found_posts,
                'pages' => (int) $query->max_num_pages,
            ];
        }

        $args['posts_per_page'] = -1;
        return [
            'items' => get_posts($args),
            'total' => count($this->favorites),
            'pages' => 1,
        ];
    }

    // ─── COOKIE HELPERS (legacy compat) ──────────────────

    public function setCookie() { $this->save_to_cookie(); }
    public function unsetCookie() { $this->clear_cookie(); }
}

/**
 * On login: merge cookie favorites into user account
 */
function favorites_from_cookie($user_login = '', $user = null) {
    if (!$user && is_string($user_login)) {
        $user = get_user_by('login', $user_login);
    }
    if (!$user) return;

    $favorites_obj = new Favorites($user->ID);
    $favorites_obj->check();
    $favorites_obj->merge();
}
add_action('wp_login', 'favorites_from_cookie', 10, 2);
