<?php
class SearchHistory {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'search_terms';
        $this->create_table();
    }

    private function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'search',
            rank int(11) NOT NULL DEFAULT 1,
            date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name_type (name, type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function set_term($term, $type = 'search') {
        global $wpdb;
        $term = trim(strtolower($term));
        $now = current_time('mysql');

        if (empty($term)) {
            return;
        }

        // Eğer type search veya any ise, tüm public post types için işlemi gerçekleştir
        if ($type === 'search' || $type === 'any') {
            // Get all public post types except 'attachment'
            $post_types = get_post_types(array('public' => true), 'names');
            $post_types = array_diff($post_types, array('attachment'));

            foreach ($post_types as $post_type) {
                // Check if term exists for this post type
                $query = new WP_Query(array(
                    'post_type' => $post_type,
                    's' => $term,
                    'posts_per_page' => 1,
                    'fields' => 'ids', // Only get post IDs
                    'suppress_filters' => true
                ));

                if (!$query->have_posts()) {
                    continue; // Skip if no posts are found
                }

                $existing_term = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM $this->table_name WHERE name = %s AND type = %s", $term, $post_type)
                );

                if ($existing_term) {
                    // Update existing term's rank
                    $wpdb->update(
                        $this->table_name,
                        array(
                            'rank' => $existing_term->rank + 1,
                            'date_modified' => $now
                        ),
                        array('id' => $existing_term->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                } else {
                    // Insert new row for this post type
                    $insert_result = $wpdb->insert(
                        $this->table_name,
                        array(
                            'name' => $term,
                            'type' => $post_type,
                            'rank' => 1,
                            'date' => $now,
                            'date_modified' => $now
                        ),
                        array('%s', '%s', '%s', '%d', '%s', '%s')
                    );
                    if ($insert_result) {
                        $inserted_id = $wpdb->insert_id;
                        $update_result = $wpdb->update(
                            $this->table_name,
                            array(
                                'date' => $now
                            ),
                            array('id' => $inserted_id),
                            array('%s', '%s'),
                            array('%d')
                        );
                    }
                }
            }
        } else {
            // Handle case for other types
            $existing_term = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $this->table_name WHERE name = %s AND type = %s", $term, $type)
            );

            if ($existing_term) {
                $wpdb->update(
                    $this->table_name,
                    array(
                        'rank' => $existing_term->rank + 1,
                        'date_modified' => $now
                    ),
                    array('id' => $existing_term->id),
                    array('%d', '%s'),
                    array('%d')
                );
            } else {
                $insert_result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'name' => $term,
                        'type' => $type,
                        'rank' => 1,
                        'date' => $now,
                        'date_modified' => $now
                    ),
                    array('%s', '%s', '%d', '%s', '%s')
                );
                if ($insert_result) {
                    $inserted_id = $wpdb->insert_id;
                    $update_result = $wpdb->update(
                        $this->table_name,
                        array(
                            'date' => $now
                        ),
                        array('id' => $inserted_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                }
            }
        }

        // Update user meta for logged-in users
        if (is_user_logged_in()) {
            $this->update_user_meta_terms($term);
        } else {
            // Cookie handling for non-logged-in users
            $this->update_cookie_terms($term);
        }
    }
    private function update_user_meta_terms($term) {
        $user_id = get_current_user_id();
        if ($user_id && $user_id != 0) {
            $terms = get_user_meta($user_id, 'search_terms', true);
            if (!$terms) {
                $terms = [];
            }

            if (!in_array($term, $terms)) {
                $terms[] = $term;
                update_user_meta($user_id, 'search_terms', $terms);
            }
        }
    }

    private function update_cookie_terms($term) {
        $cookie_name = 'wp_search_terms';
        $cookie_value = isset($_COOKIE[$cookie_name]) ? json_decode(stripslashes($_COOKIE[$cookie_name]), true) : array();

        if (!in_array($term, $cookie_value)) {
            $cookie_value[] = $term;
            setcookie($cookie_name, json_encode($cookie_value), time() + (86400 * 30), "/"); // 30 days expiry
        }
    }

    public function get_user_terms($user_id = null, $type = 'search', $count = 5) {
        if(!ENABLE_MEMBERSHIP){
            //return array();
        }

        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        if (empty($user_id) || $user_id == 0) {
            return array();
        }

        if (!is_user_logged_in() && isset($_COOKIE['wp_search_terms'])) {
            return array_slice(json_decode(stripslashes($_COOKIE['wp_search_terms']), true), 0, $count);
        }

        if (is_null(get_user_by('ID', $user_id))) {
            delete_user_meta($user_id, 'search_terms');
            return array();
        }

        $terms = get_user_meta($user_id, 'search_terms', true);

        if (!$terms) {
            return array();
        }

        return array_slice($terms, 0, $count);
    }

    public function get_popular_terms($type = 'search', $count = 5) {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT name FROM $this->table_name WHERE type = %s ORDER BY rank DESC LIMIT %d", $type, $count),
            ARRAY_A
        );
        return wp_list_pluck($results, 'name');
    }
}