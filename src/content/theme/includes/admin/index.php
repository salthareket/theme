<?php
add_action('wp_ajax_get_post_type_taxonomies', 'get_post_type_taxonomies');
add_action('wp_ajax_nopriv_get_post_type_taxonomies', 'get_post_type_taxonomies');
function get_post_type_taxonomies(){
$response = array(
"error" => false,
"message" => "",
"html" => "",
"data" => ""
);
$count = 0;
$options = "";
$ids = array();
$selected = $_POST["selected"];
//switch ($_POST["name"]) {
//case 'menu_item_post_type':
if( empty($_POST["value"])){
$taxonomies = get_taxonomies(array(), 'objects' );
}else{
$taxonomies = get_object_taxonomies( array( 'post_type' => $_POST["value"] ), 'objects' );
}
if($taxonomies){
$taxonomies = array_filter($taxonomies, function($taxonomy) {
return $taxonomy->public;
});
$options .= "<option value='' ".(empty($selected)?"":"selected").">".($taxonomies?"Don't add Taxonomies":"Not found any taxonomy")."</option>";
foreach( $taxonomies as $taxonomy ){
$ids[] = $taxonomy;
$options .= "<option value='".$taxonomy->name."' ".($selected?"selected":"").">".$taxonomy->label."</option>";
}
}
//break;
//}
$response["html"] = $options;
$values = array();
$values["selected"] = $selected;
$values["ids"] = $ids;
$values["count"] = $count;
$response["data"] = $values;
echo json_encode($response);
die;
}
function post_type_ui_render_field($field) {
$js_code = 'if (typeof acf !== "undefined" && typeof acf.add_action !== "undefined") {';
$js_code .= 'acf.addAction("new_field/key='.$field["key"].'", function(e){';
$js_code .= 'if(e.$el.closest(".acf-clone").length == 0){';
$js_code .= 'debugJS(e);';
$js_code .= 'e.$el.attr("data-val", "%s");';
$js_code .= '}';
$js_code .= '});';
$js_code .= '}';
if(!empty($field["value"])){
printf('<script>' . $js_code . '</script>', esc_js($field["value"]));
}
}
add_action('acf/render_field/name=menu_item_taxonomy', 'post_type_ui_render_field');
add_filter( 'manage_pages_columns', 'table_template_columns', 10, 1 );
add_action( 'manage_pages_custom_column', 'table_template_column', 10, 2 );
function table_template_columns( $columns ) {
$custom_columns = array(
'col_template' => 'Template'
);
$columns = array_merge( $columns, $custom_columns );
return $columns;
}
function table_template_column( $column, $post_id ) {
if ( $column == 'col_template' ) {
echo basename( get_page_template() );
}
}
// Admin panelde liste sayfasına yeni bir sütun ekleme
function add_modified_date_column($columns) {
$columns['modified_date'] = __('Modified', 'default'); // "Modified" terimi default WP stilinde
return $columns;
}
add_filter('manage_posts_columns', 'add_modified_date_column');
add_filter('manage_pages_columns', 'add_modified_date_column');
// Değiştirilme tarihi sütununa verileri ekleme
function show_modified_date_column_content($column_name, $post_id) {
if ($column_name === 'modified_date') {
// Değiştirilme tarihini dd.mm.yyyy formatında göster
$modified_date = get_post_modified_time('d.m.Y H:i', false, $post_id);
echo __('Modified', 'default')."<br>".$modified_date;
}
}
add_action('manage_posts_custom_column', 'show_modified_date_column_content', 10, 2);
add_action('manage_pages_custom_column', 'show_modified_date_column_content', 10, 2);
// Sütunları sıralanabilir hale getirme
function make_modified_date_sortable($columns) {
$columns['modified_date'] = 'modified';
return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'make_modified_date_sortable');
add_filter('manage_edit-page_sortable_columns', 'make_modified_date_sortable');
// Sticky desteği olan post type'ları getir
function get_sticky_supported_post_types() {
$post_types = get_post_types(array('public' => true), 'names');
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
$post_types = get_post_types(array('public' => true), 'names');
$post_type = get_post_type($post_id);
if(in_array($post_type, array_keys($post_types))){
$is_sticky = is_sticky($post_id) ? 1 : 0;
update_post_meta($post_id, '_is_sticky', $is_sticky);
}
}
add_action('save_post', 'update_sticky_meta_on_save');
// Thumbnail sütunu term listesine checkbox'tan sonra ekle
add_filter('manage_edit-category_columns', function($columns) {
$new_columns = [];
foreach ($columns as $key => $value) {
$new_columns[$key] = $value;
if ($key === 'cb') { // Checkbox sütununu kontrol et
$new_columns['thumbnail'] = __('Thumbnail'); // Thumbnail sütununu checkbox'tan sonra ekle
}
}
return $new_columns;
});
// Term listesi için thumbnail içeriği
add_filter('manage_category_custom_column', function($content, $column_name, $term_id) {
if ($column_name === 'thumbnail') {
$thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
if ($thumbnail_id) {
$image_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
$content = '<img src="' . esc_url($image_url) . '" style="width:75px;height:auto;border-radius:6px;">';
} else {
$content = __('No Thumbnail');
}
}
return $content;
}, 10, 3);
// Thumbnail sütununu post listesine checkbox'tan sonra ekle
add_filter('manage_post_posts_columns', 'add_thumbnail_column_to_posts');
add_filter('manage_page_posts_columns', 'add_thumbnail_column_to_posts');
function add_thumbnail_column_to_posts($columns) {
$new_columns = [];
foreach ($columns as $key => $value) {
$new_columns[$key] = $value;
if ($key === 'cb') { // Checkbox sütununu kontrol et
$new_columns['thumbnail'] = __('Thumbnail'); // Thumbnail sütununu checkbox'tan sonra ekle
}
}
return $new_columns;
}
// Post listesi için thumbnail içeriği
add_action('manage_posts_custom_column', 'add_thumbnail_to_post_column', 10, 2);
add_action('manage_pages_custom_column', 'add_thumbnail_to_post_column', 10, 2);
function add_thumbnail_to_post_column($column, $post_id) {
if ($column === 'thumbnail') {
if (has_post_thumbnail($post_id)) {
$image_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
echo '<img src="' . esc_url($image_url) . '" style="width:75px;height:auto;border-radius:6px;">';
} else {
echo __('No Thumbnail');
}
}
}
function new_modify_user_table( $column ) {
$column['register_type'] = 'Register Type';
$column['user_status'] = 'Activated';
$column['password_set'] = 'Password Set';
return $column;
}
add_filter( 'manage_users_columns', 'new_modify_user_table' );
function new_modify_user_table_row( $val, $column_name, $user_id ) {
switch ($column_name) {
case 'register_type' :
$value = get_user_meta( $user_id, 'register_type', true);
return $value;
case 'user_status' :
$value = get_user_meta( $user_id, 'user_status', true);
if($value){
return "<span style='color:green;'>Yes</span>";
}else{
return "<span style='color:red;'>No</span>";
}
case 'password_set' :
$value = get_user_meta( $user_id, 'password_set', true);
if(!metadata_exists( 'user', $user_id, 'password_set')){
return "<span style='color:red;'>No - not exist</span>";
}else{
if(!$value){
return "<span style='color:red;'>No".$value."</span>";
}else{
return "<span style='color:green;'>Yes</span>";
}
}
default:
}
return $val;
}
add_filter( 'manage_users_custom_column', 'new_modify_user_table_row', 10, 3 );
/**
* Registers an editor stylesheet for the theme.
*/
function wpdocs_theme_add_editor_styles() {
add_editor_style( 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.1/css/bootstrap.min.css' );
add_editor_style( get_stylesheet_directory_uri() . '/static/css/admin-addon.css');
}
add_action( 'admin_init', 'wpdocs_theme_add_editor_styles' );
// Callback function to insert 'styleselect' into the $buttons array
function my_mce_buttons_2( $buttons ) {
array_unshift( $buttons, 'styleselect' );
return $buttons;
}
// Register our callback to the appropriate filter
add_filter('mce_buttons_2', 'my_mce_buttons_2');
// Callback function to filter the MCE settings
function my_mce_before_init_insert_formats( $init_array ) {
$new_styles = [];
// Buttons from costom colors
$buttons = array();
if(isset($GLOBALS["mce_text_colors"])){
foreach ($GLOBALS["mce_text_colors"] as $value) {
$slug = strtolower($value);
$buttons[] = array(
'title' => 'btn-'.$slug,
'selector' => 'a',
'classes' => 'btn btn-'.$slug.' btn-extended'
);
}
}
$new_styles[] = [
"title" => "Button",
"items" => $buttons
];
$style_formats = array(
array(
'title' => 'List Unstyled 22',
'selector' => 'ul, ol',
'classes' => 'list-unstyled ms-4'
),
array(
'title' => 'Table Bordered',
'selector' => 'table',
'classes' => 'table-bordered'
),
array(
'title' => 'Table Striped',
'selector' => 'table',
'classes' => 'table-striped'
),
array(
'title' => 'Text - Slab',
'selector' => '*',
'classes' => 'slab-text-container'
),
array(
'title' => 'Small',
'inline' => 'small'
),
);
$new_styles[] = [
"title" => "Styles",
"items" => $style_formats
];
if($GLOBALS["breakpoints"]){
$typography = [];
$theme_styles = acf_get_theme_styles();
if($theme_styles){
if(isset($theme_styles["typography"])){
$typography = $theme_styles["typography"];
}
}
foreach($GLOBALS["breakpoints"] as $key => $breakpoint){
$size = "";
if(isset($typography["title"][$key]) && !empty($typography["title"][$key]["value"])){
$size = " - ".$typography["title"][$key]["value"].$typography["title"][$key]["unit"];
}
$title_classes[] = array(
'title' => 'Title - '.$key.$size,
'selector' => 'h1,h2,h3,h4,h5,h6',
'classes' => 'title-'.$key
);
$size = "";
if(isset($typography["text"][$key]) && !empty($typography["text"][$key]["value"])){
$size = " - ".$typography["text"][$key]["value"].$typography["text"][$key]["unit"];
}
$text_classes[] = array(
'title' => 'Text - '.$key.$size,
'selector' => 'p',
'classes' => 'text-'.$key
);
}
$new_styles[] = [
"title" => "Title",
"items" => $title_classes
];
$new_styles[] = [
"title" => "Text",
"items" => $text_classes
];
//$style_formats = array_merge($title_classes, $style_formats);
//$style_formats = array_merge($text_classes, $style_formats);
}
$font_weights = [];
foreach(["normal", 100, 200, 300, 400, 500, 600, 700, 800, 900] as $fw){
$font_weights[] = array(
'title' => 'Font Weight - '.$fw,
'selector' => '*',
'classes' => 'fw-'.$fw
);
}
$new_styles[] = [
"title" => "Font Weight",
"items" => $font_weights
];
//$style_formats = array_merge($font_weights, $style_formats);
$line_heights = [];
foreach(["1", "base", "sm", "lg"] as $lh){
$line_heights[] = array(
'title' => 'Line Height - '.$lh,
'selector' => '*',
'classes' => 'lh-'.$lh
);
}
$new_styles[] = [
"title" => "Line Height",
"items" => $line_heights
];
//$style_formats = array_merge($line_heights, $style_formats);
if(isset($GLOBALS["mce_styles"]) && is_array($GLOBALS["mce_styles"])){
$style_formats = array_merge($GLOBALS["mce_styles"], $style_formats);
$new_styles[] = [
"title" => "Extras",
"items" => $GLOBALS["mce_styles"]
];
}
// Insert the array, JSON ENCODED, into 'style_formats'
//$init_array['style_formats'] = json_encode( $style_formats );
//colors
if(isset($GLOBALS["mce_text_colors"])){
$mce_colors = '';
foreach ($GLOBALS["mce_text_colors"] as $key => $value) {
$mce_colors .= '"' . str_replace("#", "", $key) . '", "' . $value . '", ';
}
$mce_colors = rtrim($mce_colors, ', ');
$init_array['textcolor_map'] = '[' . $mce_colors . ']';
$init_array['textcolor_rows'] = 1;
}
// Yeni stilleri JSON formatına çevir
$new_styles_json = json_encode($new_styles);
// Mevcut style_formats'ı korumak için style_formats_merge ayarını aktif et
$init_array['style_formats_merge'] = true;
// Yeni stilleri init_array'ye ekle
$init_array['style_formats'] = $new_styles_json;
return $init_array;
}
// Attach callback to 'tiny_mce_before_init'
add_filter( 'tiny_mce_before_init', 'my_mce_before_init_insert_formats' );
// add letter spacing button
function custom_tinymce_buttons($buttons) {
array_push($buttons, 'letter_spacing_button');
return $buttons;
}
add_filter('mce_buttons', 'custom_tinymce_buttons');
use Timber\Timber;
use Timber\Loader;
use SaltHareket\Theme;
if(class_exists("underConstruction")){
add_filter( 'option_underConstructionActivationStatus', function( $status ){
if($status == "1"){
if ( function_exists( 'rocket_clean_domain' ) ) {
rocket_clean_domain();
}
}
return $status;
});
}
// on global settings changed
function acf_general_settings_rewrite( $value, $post_id, $field, $original ) {
$old = get_field($field["name"], "option");
if( $value != $old) {
flush_rewrite_rules();
}
return $value;
}
add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_membership_activation', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_chat', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_notifications', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_favorites', 'acf_general_settings_rewrite', 10, 4);
function acf_general_settings_enable_membership( $value, $post_id, $field, $original ) {
$old = get_field($field["name"], "option");
if( $value ) {
create_my_account_page();
}else{
$my_account_page = get_page_by_path('my-account');
if ($my_account_page) {
wp_delete_post($my_account_page->ID, true);
}
}
return $value;
}
add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_enable_membership', 10, 4);
function acf_general_settings_enable_location_db( $value, $post_id, $field, $original ) {
$ip2country = get_field("enable_ip2country", "option");
$settings = get_field("ip2country_settings", "option");
if( $ip2country && $settings == "db") {
$value = 1;
}
return $value;
}
add_filter('acf/update_value/name=enable_location_db', 'acf_general_settings_enable_location_db', 10, 4);
function acf_general_settings_registration( $value, $post_id, $field, $original ) {
update_option("users_can_register", $value);
update_option("woocommerce_enable_myaccount_registration", $value?"yes":"no");
return $value;
}
add_filter('acf/update_value/name=enable_registration', 'acf_general_settings_registration', 10, 4);
function plugins_activated($plugin, $network_activation) {
if($plugin == "woocommerce/woocommerce.php"){
$page_on_front = get_option( 'page_on_front' );
set_my_account_page(true);
$woo_pages = array(
array(
"endpoint" => "shop",
"title"    => "Mağaza",
"content"  => "",
"template" => "template-shop.php"
),
array(
"endpoint" => "cart",
"title"    => "Sepet",
"content"  => "[woocommerce_cart]",
"template" => "template-cart.php"
),
array(
"endpoint" => "checkout",
"title"    => "Ödeme",
"content"  => "[woocommerce_checkout]",
"template" => "template-checkout.php"
),
array(
"endpoint" => "refund_returns",
"title"    => "Geri Ödeme ve İade Politikası",
"content"  => "",
"template" => ""
),
array(
"endpoint" => "order_received",
"title"    => "Sipariş Tamamlandı",
"content"  => "",
"template" => ""
)
);
foreach($woo_pages as $page){
$page_id = get_option( "woocommerce_".$page["endpoint"]."_page_id");
if ( FALSE === get_post_status( $page_id ) ){
$args = array(
'post_title'    => $page["title"],
'post_content'  => $page["content"],
'post_status'   => 'publish',
'post_type'     => 'page',
);
if(!empty($page["template"])){
$args["page_template"] = $page["template"];
}
$page_id = wp_insert_post($args);
update_option("woocommerce_".$page["endpoint"]."_page_id", $page_id);
if(empty($page_on_front) && $page["endpoint"] == "shop"){
update_option( 'page_on_front', $page_id );
update_option( 'show_on_front', 'page' );
}
}
}
acf_development_methods_settings(1);
}
if($plugin == "underconstruction/underConstruction.php"){
$args = array(
'post_title'    => 'Under Construction',
'post_content'  => '',
'post_status'   => 'publish',
'post_type'     => 'page',
'page_template' => 'under-construction.php'
);
$page_id = wp_insert_post($args);
if (get_option('under-construction-page') === false) {
add_option('under-construction-page', $page_id);
} else {
update_option('under-construction-page', $page_id);
}
}
}
function plugins_deactivated($plugin, $network_activation) {
if($plugin == "woocommerce/woocommerce.php"){
set_my_account_page(false);
foreach(['shop', 'cart', 'checkout', 'refund_returns', 'order_received'] as $page){
wp_delete_post(wc_get_page_id( $page ), true);
}
acf_development_methods_settings(1);
}
if($plugin == "underconstruction/underConstruction.php"){
if (get_option('under-construction-page') != false) {
$page = intval(get_option('under-construction-page'));
if($page){
wp_delete_post($page, true);
delete_option('under-construction-page');
}
}
}
}
add_filter('activated_plugin', 'plugins_activated', 10, 2);
add_filter('deactivated_plugin', 'plugins_deactivated', 10, 2);
function create_my_account_page(){
$my_account_page = get_page_by_path('my-account');
if (!$my_account_page) {
$args = array(
'post_title'    => 'My Account',
'post_content'  => '[salt_my_account]',
'post_status'   => 'publish',
'post_type'     => 'page',
'page_template' => 'template-my-account.php'
//'page_template' => 'template-my-account-native.php'
);
if(class_exists("WooCommerce") && $enabled_ecommerce){
$args["post_content"] = "[woocommerce_my_account]";
//$args["page_template"] = 'template-my-account.php';
}
return wp_insert_post($args);
}else{
return $my_account_page->ID;
}
}
function set_my_account_page($enabled_ecommerce=true){
// Create My Account Page if membership is enabled but woocommerce is not exist
$my_account_page = get_page_by_path('my-account');
if (!$my_account_page) {
$my_account_page_id = create_my_account_page();
}else{
$args = array(
'ID'            => $my_account_page->ID,
//'page_template' => 'template-my-account-native.php',
'post_content'  => '[salt_my_account]'
);
if(class_exists("WooCommerce") && $enabled_ecommerce){
$args["post_content"] = "[woocommerce_my_account]";
//$args["page_template"] = 'template-my-account.php';
$woo_my_account_page_id = get_option("woocommerce_myaccount_page_id");
wp_delete_post($woo_my_account_page_id, true);
update_option("woocommerce_myaccount_page_id", $my_account_page->ID);
}
wp_update_post($args);
}
}
function check_my_account_page( $value, $post_id, $field, $original ){
if($field["name"] == "enable_membership" && $value == 1){
if($value){
set_my_account_page();
}
if(!class_exists("SaltHareket\MethodClass")){
require_once SH_CLASSES_PATH . "class.methods.php";
}
$methods = new SaltHareket\MethodClass();
$methods->createFiles(false);
$methods->createFiles(false, "admin");
if(function_exists("redirect_notice")){
redirect_notice("Frontend/Backend methods compiled!", "success");
}
}
return $value;
}
add_filter('acf/update_value/name=enable_membership', 'check_my_account_page', 10, 4);
function acf_load_language_choices( $field ) {
$field['choices'] = array();
foreach(qtranxf_getSortedLanguages() as $language) {
$field['choices'][$language] = qtranxf_getLanguageName($language);
}
return $field;
}
if(function_exists("qtranxf_getSortedLanguages")){
add_filter('acf/load_field/name=language', 'acf_load_language_choices');
}
// Clear cache.
// Also preload the cache if the Preload is enabled.
if ( function_exists( 'rocket_clean_domain' ) ) {
//rocket_clean_domain();
}
// Clear minified CSS and JavaScript files.
if ( function_exists( 'rocket_clean_minify' ) ) {
//rocket_clean_minify();
}
