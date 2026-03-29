<?php

/**
 * Sticky Posts Column — Admin list table'da sticky toggle checkbox'ı.
 */

function get_sticky_supported_post_types() {
    $post_types   = get_post_types(['public' => true, '_builtin' => false], 'names');
    $post_types[] = 'post';
    return array_filter($post_types, fn($pt) => post_type_supports($pt, 'sticky'));
}

function add_sticky_column($columns) {
    $columns['sticky'] = 'Sticky';
    return $columns;
}

function render_sticky_column($column, $post_id) {
    if ($column !== 'sticky') return;
    $checked = is_sticky($post_id) ? ' checked' : '';
    echo '<input type="checkbox" class="sticky-checkbox" data-post-id="' . (int) $post_id . '"' . $checked . '>';
}

function add_sticky_column_to_supported_post_types() {
    foreach (get_sticky_supported_post_types() as $pt) {
        add_filter("manage_{$pt}_posts_columns", 'add_sticky_column');
        add_action("manage_{$pt}_posts_custom_column", 'render_sticky_column', 10, 2);
    }
}
add_action('admin_init', 'add_sticky_column_to_supported_post_types');

function enqueue_admin_sticky_script($hook) {
    global $typenow;
    if ($hook !== 'edit.php' || !in_array($typenow, get_sticky_supported_post_types())) return;

    wp_enqueue_script('admin-sticky-script', SH_INCLUDES_URL . 'admin/column-sticky-posts/ajax.js', ['jquery'], null, true);
    wp_localize_script('admin-sticky-script', 'stickyAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('sticky_toggle'),
    ]);
}
add_action('admin_enqueue_scripts', 'enqueue_admin_sticky_script');

function toggle_sticky_status() {
    check_ajax_referer('sticky_toggle', 'nonce');

    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permission denied.');
    }

    is_sticky($post_id) ? unstick_post($post_id) : stick_post($post_id);
    wp_send_json_success();
}
add_action('wp_ajax_toggle_sticky', 'toggle_sticky_status');

function update_meta_on_stick($post_id) {
    update_post_meta($post_id, '_is_sticky', is_sticky($post_id) ? 1 : 0);
}
add_action('stick_post', 'update_meta_on_stick');
add_action('unstick_post', 'update_meta_on_stick');

function update_sticky_meta_on_save($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

    $public_types = get_post_types(['public' => true, '_builtin' => false], 'names');
    if (in_array(get_post_type($post_id), array_keys($public_types))) {
        update_post_meta($post_id, '_is_sticky', is_sticky($post_id) ? 1 : 0);
    }
}
add_action('save_post', 'update_sticky_meta_on_save');
