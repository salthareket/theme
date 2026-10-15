<?php

add_action('wp_ajax_get_post_type_taxonomies', 'get_post_type_taxonomies');
add_action('wp_ajax_nopriv_get_post_type_taxonomies', 'get_post_type_taxonomies');

function get_post_type_taxonomies() {
    $response = ['error' => false, 'message' => '', 'html' => '', 'data' => ''];

    $selected   = sanitize_text_field($_POST['selected'] ?? '');
    $post_type  = sanitize_text_field($_POST['value'] ?? '');

    $taxonomies = empty($post_type)
        ? get_taxonomies([], 'objects')
        : get_object_taxonomies(['post_type' => $post_type], 'objects');

    $taxonomies = array_filter($taxonomies ?? [], fn($t) => $t->public);

    $options = '<option value=""' . (empty($selected) ? ' selected' : '') . '>'
        . ($taxonomies ? "Don't add Taxonomies" : 'Not found any taxonomy')
        . '</option>';

    $ids = [];
    foreach ($taxonomies as $taxonomy) {
        $ids[]    = $taxonomy;
        $sel      = ($selected === $taxonomy->name) ? ' selected' : '';
        $options .= '<option value="' . esc_attr($taxonomy->name) . '"' . $sel . '>' . esc_html($taxonomy->label) . '</option>';
    }

    $response['html'] = $options;
    $response['data']  = ['selected' => $selected, 'ids' => $ids, 'count' => 0];
    echo json_encode($response);
    wp_die();
}

function post_type_ui_render_field($field) {
    if (empty($field['value'])) return;

    $js = 'if(typeof acf!=="undefined"&&typeof acf.add_action!=="undefined"){'
        . 'acf.addAction("new_field/key=' . esc_js($field['key']) . '",function(e){'
        . 'if(e.$el.closest(".acf-clone").length==0){e.$el.attr("data-val","%s")}'
        . '});}';

    printf('<script>' . $js . '</script>', esc_js($field['value']));
}
add_action('acf/render_field/name=menu_item_taxonomy', 'post_type_ui_render_field');
