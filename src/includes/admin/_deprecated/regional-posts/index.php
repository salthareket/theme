<?php
$required_setting = ENABLE_REGIONAL_POSTS;

/**
 * Regional Posts — Admin AJAX handler.
 * Post type seçildiğinde o post type'ın public taxonomy'lerini döndürür.
 */

add_action('wp_ajax_get_regional_posts_type_taxonomies', 'get_regional_posts_type_taxonomies');

function get_regional_posts_type_taxonomies() {
    $response = ['error' => false, 'message' => '', 'html' => '', 'data' => ''];

    $post_type = sanitize_text_field($_POST['value'] ?? '');

    if (empty($post_type)) {
        $response['error']   = true;
        $response['message'] = 'Post type is required.';
        echo json_encode($response);
        wp_die();
    }

    $taxonomies = get_object_taxonomies(['post_type' => $post_type], 'objects');
    $taxonomies = array_filter($taxonomies, fn($t) => $t->public);

    $options = '';
    foreach ($taxonomies as $taxonomy) {
        $options .= '<option value="' . esc_attr($taxonomy->name) . '">' . esc_html($taxonomy->label) . '</option>';
    }

    $response['html'] = $options;
    echo json_encode($response);
    wp_die();
}
