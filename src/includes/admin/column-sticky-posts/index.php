<?php

// Sticky desteği olan post type'ları getir
function get_sticky_supported_post_types() {
    $post_types = get_post_types(array('public' => true, '_builtin' => false), 'names');
    $post_types[] = 'post';
    return array_filter($post_types, function($post_type) {
        return post_type_supports($post_type, 'sticky');
    });
}

// Admin columns'a checkbox sütunu ekle
function add_sticky_column($columns) {
    $columns['sticky'] = 'Sticky';
    return $columns;
}

// Sticky sütunu render et
function render_sticky_column($column, $post_id) {
    if ($column === 'sticky') {
        $checked = is_sticky($post_id) ? 'checked' : '';
        echo '<input type="checkbox" class="sticky-checkbox" data-post-id="' . $post_id . '" ' . $checked . '>';
    }
}

// Sticky sütununu tüm destekleyen post type'lara ekle
function add_sticky_column_to_supported_post_types() {
    $post_types = get_sticky_supported_post_types();
    foreach ($post_types as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", 'add_sticky_column');
        add_action("manage_{$post_type}_posts_custom_column", 'render_sticky_column', 10, 2);
    }
}
add_action('admin_init', 'add_sticky_column_to_supported_post_types');

// Admin paneline AJAX kodunu ekle
function enqueue_admin_sticky_script($hook) {
    global $typenow;
    $post_types = get_sticky_supported_post_types();
    if ('edit.php' === $hook && in_array($typenow, $post_types)) {
        wp_enqueue_script('admin-sticky-script', SH_INCLUDES_URL . 'admin/column-sticky-posts/ajax.js', array('jquery'), null, true);
        wp_localize_script('admin-sticky-script', 'stickyAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
}
add_action('admin_enqueue_scripts', 'enqueue_admin_sticky_script');

// AJAX işleme
function toggle_sticky_status() {
    $post_id = intval($_POST['post_id']);
    if (current_user_can('edit_post', $post_id)) {
        if (is_sticky($post_id)) {
            unstick_post($post_id);
        } else {
            stick_post($post_id);
        }
        wp_send_json_success();
    } else {
        wp_send_json_error('You do not have permission to edit this post.');
    }
}
add_action('wp_ajax_toggle_sticky', 'toggle_sticky_status');

function update_meta_on_stick($post_id) {
    if (is_sticky($post_id)) {
        update_post_meta($post_id, '_is_sticky', 1); // Sticky olarak işaretlenmişse meta güncelle
    } else {
        update_post_meta($post_id, '_is_sticky', 0); // Unstick yapılmışsa meta değerini sıfırla
    }
}
add_action('stick_post', 'update_meta_on_stick');
add_action('unstick_post', 'update_meta_on_stick');

function update_sticky_meta_on_save($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    $post_types = get_post_types(array('public' => true, '_builtin' => false), 'names');
    $post_type = get_post_type($post_id);
    if(in_array($post_type, array_keys($post_types))){
        $is_sticky = is_sticky($post_id) ? 1 : 0;
        update_post_meta($post_id, '_is_sticky', $is_sticky);        
    }
}
add_action('save_post', 'update_sticky_meta_on_save');
