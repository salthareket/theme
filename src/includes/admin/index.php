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
/**
* Admin Pages Table — Template column.
*/
add_filter('manage_pages_columns', function($columns) {
$columns['col_template'] = 'Template';
return $columns;
}, 10, 1);
add_action('manage_pages_custom_column', function($column, $post_id) {
if ($column === 'col_template') {
echo esc_html(basename(get_page_template()));
}
}, 10, 2);
/**
* Admin Post/Page Table — Modified Date column (sortable).
*/
add_filter('manage_posts_columns', 'admin_add_modified_date_column');
add_filter('manage_pages_columns', 'admin_add_modified_date_column');
function admin_add_modified_date_column($columns) {
$columns['modified_date'] = __('Modified', 'default');
return $columns;
}
add_action('manage_posts_custom_column', 'admin_show_modified_date_column', 10, 2);
add_action('manage_pages_custom_column', 'admin_show_modified_date_column', 10, 2);
function admin_show_modified_date_column($column_name, $post_id) {
if ($column_name !== 'modified_date') return;
echo __('Modified', 'default') . '<br>' . get_post_modified_time('d.m.Y H:i', false, $post_id);
}
add_filter('manage_edit-post_sortable_columns', 'admin_make_modified_date_sortable');
add_filter('manage_edit-page_sortable_columns', 'admin_make_modified_date_sortable');
function admin_make_modified_date_sortable($columns) {
$columns['modified_date'] = 'modified';
return $columns;
}
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
/**
* Admin Users Table — Register Type, Activation Status, Password Set columns.
*/
add_filter('manage_users_columns', function($columns) {
$columns['register_type'] = 'Register Type';
$columns['user_status']   = 'Activated';
$columns['password_set']  = 'Password Set';
return $columns;
});
add_filter('manage_users_custom_column', function($val, $column_name, $user_id) {
switch ($column_name) {
case 'register_type':
return esc_html(get_user_meta($user_id, 'register_type', true));
case 'user_status':
return get_user_meta($user_id, 'user_status', true)
? '<span style="color:green">Yes</span>'
: '<span style="color:red">No</span>';
case 'password_set':
if (!metadata_exists('user', $user_id, 'password_set')) {
return '<span style="color:red">No - not exist</span>';
}
return get_user_meta($user_id, 'password_set', true)
? '<span style="color:green">Yes</span>'
: '<span style="color:red">No</span>';
}
return $val;
}, 10, 3);
/**
* TinyMCE Editor — Bootstrap CSS + admin addon stylesheet.
*/
add_action('admin_init', function() {
add_editor_style('https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.1/css/bootstrap.min.css');
add_editor_style(get_stylesheet_directory_uri() . '/static/css/admin-addon.css');
});
/**
* TinyMCE Editor — Bootstrap style select, font weights, line heights, margins.
*/
add_filter('mce_buttons_2', function($buttons) {
array_unshift($buttons, 'styleselect');
return $buttons;
});
add_filter('tiny_mce_before_init', function($init_array) {
$new_styles = [];
// ─── Button Styles ──────────────────────────────────────
$buttons = [];
if (Data::has('mce_text_colors')) {
foreach (Data::get('mce_text_colors') as $value) {
$slug      = strtolower($value);
$buttons[] = ['title' => 'btn-' . $slug, 'selector' => 'a', 'classes' => 'btn btn-' . $slug . ' btn-extended'];
}
}
$new_styles[] = ['title' => 'Button', 'items' => $buttons];
// ─── Base Styles ────────────────────────────────────────
$base_styles = [
['title' => 'List Unstyled',   'selector' => 'ul, ol', 'classes' => 'list-unstyled ms-4'],
['title' => 'Table Bordered',  'selector' => 'table',  'classes' => 'table-bordered'],
['title' => 'Table Striped',   'selector' => 'table',  'classes' => 'table-striped'],
['title' => 'Text - Slab',     'selector' => '*',      'classes' => 'slab-text-container'],
['title' => 'Small',           'inline'   => 'small'],
];
$new_styles[] = ['title' => 'Styles', 'items' => $base_styles];
// ─── Breakpoint Typography ──────────────────────────────
$breakpoints = Data::get('breakpoints');
if ($breakpoints) {
$typography    = [];
$theme_styles  = function_exists('acf_get_theme_styles') ? acf_get_theme_styles() : [];
if (isset($theme_styles['typography'])) {
$typography = $theme_styles['typography'];
}
$title_classes = [];
$text_classes  = [];
foreach ($breakpoints as $key => $bp) {
$title_size = (isset($typography['title'][$key]['value']) && $typography['title'][$key]['value'] !== '')
? ' - ' . $typography['title'][$key]['value'] . $typography['title'][$key]['unit'] : '';
$title_classes[] = ['title' => 'Title - ' . $key . $title_size, 'selector' => 'h1,h2,h3,h4,h5,h6', 'classes' => 'title-' . $key];
$text_size = (isset($typography['text'][$key]['value']) && $typography['text'][$key]['value'] !== '')
? ' - ' . $typography['text'][$key]['value'] . $typography['text'][$key]['unit'] : '';
$text_classes[] = ['title' => 'Text - ' . $key . $text_size, 'selector' => 'p', 'classes' => 'text-' . $key];
}
$new_styles[] = ['title' => 'Title', 'items' => $title_classes];
$new_styles[] = ['title' => 'Text',  'items' => $text_classes];
}
// ─── Font Weight ────────────────────────────────────────
$fw_items = [];
foreach (['normal', 100, 200, 300, 400, 500, 600, 700, 800, 900] as $fw) {
$fw_items[] = ['title' => 'Font Weight - ' . $fw, 'selector' => '*', 'classes' => 'fw-' . $fw];
}
$new_styles[] = ['title' => 'Font Weight', 'items' => $fw_items];
// ─── Line Height ────────────────────────────────────────
$lh_items = [];
foreach (['1', 'base', 'sm', 'md', 'lg'] as $lh) {
$lh_items[] = ['title' => 'Line Height - ' . $lh, 'selector' => '*', 'classes' => 'lh-' . $lh];
}
$new_styles[] = ['title' => 'Line Height', 'items' => $lh_items];
// ─── Margin ─────────────────────────────────────────────
$margin_items = [];
foreach (['mt-5', 'mt-4', 'mt-3', 'mt-2', 'mt-1', 'm-0', 'mb-5', 'mb-4', 'mb-3', 'mb-2', 'mb-1'] as $m) {
$margin_items[] = ['title' => 'Margin - ' . $m, 'selector' => 'h1,h2,h3,h4,h5,h6,p', 'classes' => $m];
}
$new_styles[] = ['title' => 'Margin', 'items' => $margin_items];
// ─── Extra MCE Styles ───────────────────────────────────
$mce_styles = Data::get('mce_styles');
if (is_array($mce_styles) && !empty($mce_styles)) {
$new_styles[] = ['title' => 'Extras', 'items' => $mce_styles];
}
// ─── Text Colors ────────────────────────────────────────
$mce_text_colors = Data::get('mce_text_colors');
if ($mce_text_colors) {
$pairs = [];
foreach ($mce_text_colors as $hex => $name) {
$pairs[] = '"' . str_replace('#', '', $hex) . '"';
$pairs[] = '"' . $name . '"';
}
$init_array['textcolor_map']  = '[' . implode(', ', $pairs) . ']';
$init_array['textcolor_rows'] = 1;
}
$init_array['style_formats_merge'] = true;
$init_array['style_formats']       = json_encode($new_styles);
return $init_array;
});
add_filter('mce_buttons', function($buttons) {
$buttons[] = 'letter_spacing_button';
return $buttons;
});
/**
* TinyMCE Dark Mode Toggle — Toolbar button.
*/
add_filter('mce_external_plugins', function($plugins) {
$plugins['darkmode_toggle'] = SH_INCLUDES_URL . 'admin/editor-dark-mode/darkmode-toggle.js';
return $plugins;
});
add_filter('mce_buttons', function($buttons) {
$buttons[] = 'darkmode_toggle';
return $buttons;
});
/**
* ACF Language Field — qTranslate dil seçeneklerini populate eder.
*/
if (function_exists('qtranxf_getSortedLanguages')) {
add_filter('acf/load_field/name=language', function($field) {
$field['choices'] = [];
foreach (qtranxf_getSortedLanguages() as $lang) {
$field['choices'][$lang] = qtranxf_getLanguageName($lang);
}
return $field;
});
}
