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
if(in_array($post_type, $post_types)){
$is_sticky = is_sticky($post_id) ? 1 : 0;
update_post_meta($post_id, '_is_sticky', $is_sticky);
}
}
add_action('save_post', 'update_sticky_meta_on_save');
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
require SH_CLASSES_PATH . "class.methods.php";
$methods = new MethodClass();
$methods->createFiles(false);
$methods->createFiles(false, "admin");
if(function_exists("redirect_notice")){
redirect_notice("Frontend/Backend methods compiled!", "success");
}
}
return $value;
}
add_filter('acf/update_value/name=enable_membership', 'check_my_account_page', 10, 4);
function scss_variables_padding($padding=""){
$padding = trim($padding);
$padding = str_replace("px", " ", $padding);
$padding = str_replace("  ", " ", $padding);
$padding = explode(" ", $padding);
$padding = trim(implode("px ", $padding))."px";
$padding = str_replace("pxpx", "px", $padding);
return $padding;
}
function scss_variables_color($value=""){
if(empty($value)){
$value = "transparent";
}
return $value;
}
function scss_variables_boolean($value=""){
if(empty($value)){
$value = "false";
}else{
$value = "true";
}
return $value;
}
function scss_variables_image($balue=""){
if(empty($value)){
$value = "none";
}
return $value;
}
function scss_variables_array($array=array()){
$temp = array();
foreach($array as $key => $item){
$temp[] = $key."---".$item;
}
$temp = implode("___", $temp);
$temp = preg_replace('/\s+/', '', $temp);
return $temp;
}
function wp_scss_set_variables(){
$host_url = get_stylesheet_directory_uri();
if(ENABLE_PUBLISH){
if(function_exists("WPH_activated")){
$wph_settings = get_option("wph_settings");
$new_theme_path = "";
if(isset($wph_settings["module_settings"]["new_theme_path"])){
$new_theme_path = $wph_settings["module_settings"]["new_theme_path"];
}
if(!empty($new_theme_path)){
$host_url = PUBLISH_URL."/".$new_theme_path;
}
}else{
$host_url = str_replace(get_host_url(), PUBLISH_URL, $host_url);
}
}
$variables = [
"host_url" => "'" . $host_url . "'",
"woocommerce" => class_exists("WooCommerce") ? "true" : "false",
"yobro" => class_exists("Redq_YoBro") ? "true" : "false",
"mapplic" => class_exists("Mapplic") ? "true" : "false",
"newsletter" => class_exists("Newsletter") ? "true" : "false",
"yasr" => function_exists("yasr_fs") ? "true" : "false",
"apss" => class_exists("APSS_Class") ? "true" : "false",
"cf7" => class_exists("WPCF7") ? "true" : "false",
"enable_multilanguage" => boolval(ENABLE_MULTILANGUAGE) ? "true" : "false",
"enable_favorites" => boolval(ENABLE_FAVORITES) ? "true" : "false",
"enable_follow" => boolval(ENABLE_FOLLOW) ? "true" : "false",
"enable_cart" => boolval(ENABLE_CART) ? "true" : "false",
"enable_filters" => boolval(ENABLE_FILTERS) ? "true" : "false",
"enable_membership" => boolval(ENABLE_MEMBERSHIP) ? "true" : "false",
"enable_chat" => boolval(ENABLE_CHAT) ? "true" : "false",
"enable_notifications" => boolval(ENABLE_NOTIFICATIONS) ? "true" : "false",
"enable_sms_notifications" => boolval(ENABLE_NOTIFICATIONS) && boolval(ENABLE_SMS_NOTIFICATIONS) ? "true" : "false",
"search_history" => boolval(ENABLE_SEARCH_HISTORY) ? "true" : "false",
"logo" => "'" . get_field("logo", "option") . "'",
"dropdown_notification" => boolval(header_has_dropdown()) ? "true" : "false",
"node_modules_path" =>  '"' . str_replace('\\', '/', NODE_MODULES_PATH) . '"',
"theme_static_path" =>  '"' . str_replace('\\', '/', THEME_STATIC_PATH) . '"'
];
if(file_exists(get_stylesheet_directory() ."/static/js/js_files_all.json")){
$plugins = file_get_contents(get_stylesheet_directory() ."/static/js/js_files_all.json");
if($plugins){
$variables["plugins"] = str_replace(array("[", "]"), "", $plugins);
}
}
$variables = get_theme_styles($variables);
return $variables;
}
add_filter("wp_scss_variables", "wp_scss_set_variables");
function variable_font($font = ""){
if(!empty($font)){
$font = str_replace("|", "", $font);
}
return $font;
}
function get_theme_styles($variables = array()){
$theme_styles = acf_get_theme_styles();
if($theme_styles){
$path = THEME_STATIC_PATH . 'data/theme-styles';
if(!is_dir($path)){
mkdir($path, 0755, true);
}
// Typography
$headings_font = variable_font($theme_styles["typography"]["font_family"]);
$variables["header_font"] = $headings_font;
$headings = $theme_styles["typography"]["headings"];
foreach($headings as $key => $heading){
$variables["typography_".$key."_font"] = $headings_font;
$variables["typography_".$key."_size"] = acf_units_field_value($heading["font_size"]);
$variables["typography_".$key."_weight"] = $heading["font_weight"];
}
$title_sizes = [];
$title_mobile_sizes = [];
$title_line_heights = [];
$title_mobile_line_heights = [];
foreach ($theme_styles["typography"]["title"] as $key => $breakpoint) {
$title_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
}
foreach ($theme_styles["typography"]["title_mobile"] as $key => $breakpoint) {
$title_mobile_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
}
foreach ($theme_styles["typography"]["title_line_height"] as $key => $breakpoint) {
$line_height = acf_units_field_value($breakpoint);
$title_line_heights[] = "size: $key, line-height: $line_height";
$fs = $theme_styles["typography"]["title"][$key]["value"];
$lh = $breakpoint["value"];
$mobile_fs = $theme_styles["typography"]["title_mobile"][$key]["value"];
if (!empty($fs) && !empty($mobile_fs) && !empty($lh)) {
$mobile_lh = ($mobile_fs * $lh) / $fs;
$title_mobile_line_heights[] = "size: $key, line-height: ".($mobile_lh)."px";
}
}
$variables["title_sizes"] = "(".implode("), (", $title_sizes).")";
$variables["title_mobile_sizes"] = "(".implode("), (", $title_mobile_sizes).")";
$variables["title_line_heights"] = "(".implode("), (", $title_line_heights).")";
$variables["title_mobile_line_heights"] = "(".implode("), (", $title_mobile_line_heights).")";
// Body
$body = $theme_styles["body"];
$variables["font-primary"] = variable_font($body["primary_font"]);
$variables["font-secondary"] = variable_font($body["secondary_font"]);
$variables["base-font-size"] = acf_units_field_value($body["font_size"]);
$variables["base-font-weight"] = $body["font_weight"];
$variables["base-letter-spacing"] = acf_units_field_value($body["letter_spacing"]);
$variables["base-font-color"] = scss_variables_color($body["color"]);
$variables["body-bg-color"] = scss_variables_color($body["bg_color"]);
$variables["body-bg-backdrop"] = scss_variables_color($body["backdrop_color"]);
// Button Sizes
$buttons = $theme_styles["buttons"];
if ($buttons["custom"]) {
$button_sizes = [];
foreach ($buttons["custom"] as $key => $size) {
$button_sizes[] = "size: ".$size['size'].
", padding_x: ".acf_units_field_value($size['padding_x']).
", padding_y: ".acf_units_field_value($size['padding_y']).
", font-size: ".acf_units_field_value($size['font_size']).
", border-radius: ".acf_units_field_value($size['border_radius']);
}
$variables["button-sizes"] = "(".implode("), (", $button_sizes).")";
}
// Header
$header = $theme_styles["header"];
$header_general = $header["header"];
//$variables["header-fixed"] = scss_variables_boolean($header_general["fixed"]);
$variables["header-dropshadow"] = scss_variables_boolean($header_general["dropshadow"]);
//$variables["header-hide-on-scroll-down"] = scss_variables_boolean($header_general["hide_on_scroll_down"]);
$variables["header-z-index"] = $header_general["z_index"];
$variables["header-bg"] = scss_variables_color($header_general["bg_color"]);
$variables["header-bg-affix"] = scss_variables_color($header_general["bg_color_affix"]);
$variables["header-height"] = acf_units_field_value($header_general["height"][array_keys($header_general["height"])[0]]);
foreach($header_general["height"] as $key => $breakpoint){
$variables["header-height-".$key] = acf_units_field_value($breakpoint);
}
$variables["header-height-affix"] = acf_units_field_value($header_general["height_affix"][array_keys($header_general["height_affix"])[0]]);
foreach($header_general["height_affix"] as $key => $breakpoint){
$variables["header-height-".$key."-affix"] = acf_units_field_value($breakpoint);;
}
// Nav Bar
$header_navbar = $header["navbar"];
$variables["header-navbar-bg"] = scss_variables_color($header_navbar["bg_color"]);
$variables["header-navbar-bg-affix"] = scss_variables_color($header_navbar["bg_color_affix"]);
$variables["header-navbar-align-hr"] = $header_navbar["align_hr"];
$variables["header-navbar-align-vr"] = $header_navbar["align_vr"];
$height_header = $header_navbar["height_header"]; // is same with header
$variables["header-navbar-height"] = acf_units_field_value($header_navbar["height"][array_keys($header_navbar["height"])[0]]);
foreach($header_navbar["height"] as $key => $breakpoint){
$variables["header-navbar-height-".$key] = acf_units_field_value($breakpoint);
}
$variables["header-navbar-height-affix"] = acf_units_field_value($header_navbar["height_affix"][array_keys($header_navbar["height_affix"])[0]]);
foreach($header_navbar["height_affix"] as $key => $breakpoint){
$variables["header-navbar-height-".$key."-affix"] = acf_units_field_value($breakpoint);
}
$variables["header-navbar-padding"] = $header_navbar["padding"][array_keys($header_navbar["padding"])[0]];
foreach($header_navbar["padding"] as $key => $breakpoint){
$variables["header-navbar-padding-".$key] = scss_variables_padding($breakpoint);
}
$variables["header-navbar-padding-affix"] = $header_navbar["padding_affix"][array_keys($header_navbar["padding_affix"])[0]];
foreach($header_navbar["padding_affix"] as $key => $breakpoint){
$variables["header-navbar-padding-".$key."-affix"] = scss_variables_padding($breakpoint);
}
// Nav
$header_nav = $header["nav"];
$variables["header-navbar-nav-width"] = $header_nav["width"];
$variables["header-navbar-nav-margin"] = $header_nav["margin"];
$variables["header-navbar-nav-align-hr"] = $header_nav["align_hr"][array_keys($header_nav["align_hr"])[0]];
foreach($header_nav["align_hr"] as $key => $breakpoint){
$variables["header-navbar-nav-align-hr-".$key] = $breakpoint;
}
$variables["header-navbar-nav-align-vr"] = $header_nav["align_vr"][array_keys($header_nav["align_vr"])[0]];
foreach($header_nav["align_vr"] as $key => $breakpoint){
$variables["header-navbar-nav-align-vr-".$key] = $breakpoint;
}
$height_header = $header_nav["height_header"]; // is same with header
$variables["header-navbar-nav-height"] = acf_units_field_value($header_nav["height"][array_keys($header_nav["height"])[0]]);
foreach($header_nav["height"] as $key => $breakpoint){
$variables["header-navbar-nav-height-".$key] = acf_units_field_value($breakpoint);
}
$variables["header-navbar-nav-height-affix"] = acf_units_field_value($header_nav["height_affix"][array_keys($header_nav["height_affix"])[0]]);
foreach($header_nav["height_affix"] as $key => $breakpoint){
$variables["header-navbar-nav-height-".$key."-affix"] = acf_units_field_value($breakpoint);
}
// Nav Item
$header_nav_item = $header["nav_item"];
$variables["header-navbar-nav-font"] = variable_font($header_nav_item["font_family"]);
$variables["nav_font"] = variable_font($header_nav_item["font_family"]);
$variables["header-navbar-nav-font-weight"] = $header_nav_item["font_weight"];
$variables["header-navbar-nav-font-weight-active"] = $header_nav_item["font_weight_active"];
$variables["header-navbar-nav-font-text-transform"] = $header_nav_item["text_transform"];
$variables["header-navbar-nav-font-letter-spacing"] = acf_units_field_value($header_nav_item["letter_spacing"]);
$variables["header-navbar-nav-font-color"] = scss_variables_color($header_nav_item["color"]);
$variables["header-navbar-nav-font-color-hover"] = scss_variables_color($header_nav_item["color_hover"]);
$variables["header-navbar-nav-font-color-active"] = scss_variables_color($header_nav_item["color_active"]);
$variables["header-navbar-nav-bg-color"] = scss_variables_color($header_nav_item["bg_color"]);
$variables["header-navbar-nav-bg-color-hover"] = scss_variables_color($header_nav_item["bg_color_hover"]);
$variables["header-navbar-nav-item-padding"] = $header_nav_item["padding"][array_keys($header_nav_item["padding"])[0]];
foreach($header_nav_item["padding"] as $key => $breakpoint){
$variables["header-navbar-nav-item-padding-".$key] = scss_variables_padding($breakpoint);
}
$variables["header-navbar-nav-font-size"] = acf_units_field_value($header_nav_item["font_size"][array_keys($header_nav_item["font_size"])[0]]);
foreach($header_nav_item["font_size"] as $key => $breakpoint){
$variables["header-navbar-nav-font-size-".$key] = acf_units_field_value($breakpoint);
}
// Dropdown
$header_dropdown = $header["dropdown"];
$header_dropdown_arrow = $header_dropdown["arrow"];
$variables["header-navbar-nav-dropdown-root-arrow"] = scss_variables_boolean($header_dropdown_arrow["arrow"]);
$variables["header-navbar-nav-dropdown-root-arrow-top"] = $header_dropdown_arrow["top"];
$variables["header-navbar-nav-dropdown-root-arrow-left"] = $header_dropdown_arrow["left"];
$header_dropdown_general = $header_dropdown["dropdown"];
$variables["header-navbar-nav-dropdown-align"] = $header_dropdown_general["align_vr"];
$variables["header-navbar-nav-dropdown-bg"] = scss_variables_color($header_dropdown_general["bg_color"]);
$variables["header-navbar-nav-dropdown-width"] = $header_dropdown_general["width"];
$variables["header-navbar-nav-dropdown-margin"] = $header_dropdown_general["margin"];
$variables["header-navbar-nav-dropdown-top"] = $header_dropdown_general["top"];
$variables["header-navbar-nav-dropdown-padding"] = $header_dropdown_general["padding"];
$variables["header-navbar-nav-dropdown-border"] = $header_dropdown_general["border"];
$variables["header-navbar-nav-dropdown-border-radius"] = $header_dropdown_general["border_radius"];
$header_dropdown_item = $header_dropdown["dropdown_item"];
$variables["header-navbar-nav-dropdown-font-size"] = acf_units_field_value($header_dropdown_item["font_size"]);
$variables["header-navbar-nav-dropdown-font-color"] = scss_variables_color($header_dropdown_item["color"]);
$variables["header-navbar-nav-dropdown-font-color-hover"] = scss_variables_color($header_dropdown_item["color_hover"]);
$variables["header-navbar-nav-dropdown-font-weight"] = $header_dropdown_item["font_weight"];
$variables["header-navbar-nav-dropdown-font-weight-hover"] = $header_dropdown_item["font_weight_hover"];
$variables["header-navbar-nav-dropdown-font-text-transform"] = $header_dropdown_item["text_transform"];
$variables["header-navbar-nav-dropdown-item-padding"] = $header_dropdown_item["padding"];
$variables["header-navbar-nav-dropdown-item-bg"] = scss_variables_color($header_dropdown_item["bg_color"]);
$variables["header-navbar-nav-dropdown-item-bg-hover"] = scss_variables_color($header_dropdown_item["bg_color_hover"]);
$variables["header-navbar-nav-dropdown-item-border"] = $header_dropdown_item["border"];
$variables["header-navbar-nav-dropdown-item-border-radius"] = $header_dropdown_item["border_radius"];
// Logo
$header_logo = $header["logo"];
$variables["header-navbar-logo-color"] = scss_variables_color($header_logo["color"]);
$variables["header-navbar-logo-color-affix"] = scss_variables_color($header_logo["color_affix"]);
$variables["header-navbar-logo-align-hr"] = $header_logo["align_hr"];
$variables["header-navbar-logo-align-vr"] = $header_logo["align_vr"];
$variables["header-navbar-logo-padding"] = $header_logo["padding"][array_keys($header_logo["padding"])[0]];
foreach($header_logo["padding"] as $key => $breakpoint){
$variables["header-navbar-logo-padding-".$key] = $breakpoint;
}
$variables["header-navbar-logo-padding-affix"] = $header_logo["padding_affix"][array_keys($header_logo["padding_affix"])[0]];
foreach($header_logo["padding_affix"] as $key => $breakpoint){
$variables["header-navbar-logo-padding-".$key."-affix"] = $breakpoint;
}
// Footer
$footer = $theme_styles["footer"];
$variables["footer-height"] = acf_units_field_value($footer["height"]);
$variables["footer-padding"] = $footer["padding"];
$variables["footer-color"] = scss_variables_color($footer["color"]);
$variables["footer-color-link"] = scss_variables_color($footer["link_color"]);
$variables["footer-color-link-hover"] = scss_variables_color($footer["link_color_hover"]);
$variables["footer-bg-color"] = scss_variables_color($footer["bg_color"]);
$variables["footer-bg-image"] = scss_variables_image($footer["bg_image"]);
// Breadcrumb
$breadcrumb = $theme_styles["breadcrumb"];
$variables["breadcrumb-item-font-family"] = variable_font($breadcrumb["font_family"]);
$variables["breadcrumb-item-font-size"] = acf_units_field_value($breadcrumb["font_size"]);
$variables["breadcrumb-item-font-weight"] = $breadcrumb["font_weight"];
$variables["breadcrumb-item-line-height"] = $breadcrumb["line_height"];
$variables["breadcrumb-item-letter-spacing"] = acf_units_field_value($breadcrumb["letter_spacing"]);
$variables["breadcrumb-item-text-transform"] = $breadcrumb["text_transform"];
$variables["breadcrumb-item-color"] = scss_variables_color($breadcrumb["color"]);
$variables["breadcrumb-item-color-hover"] = scss_variables_color($breadcrumb["color_hover"]);
$variables["breadcrumb-sep-color"] = scss_variables_color($breadcrumb["seperator_color"]);
// Pagination
$pagination = $theme_styles["pagination"];
$pagination_general = $pagination["pagination"];
$variables["pagination-align"] = $pagination_general["align_vr"];
$pagination_item = $pagination["item"];
$variables["pagination-font-family"] = variable_font($pagination_item["font_family"]);
$variables["pagination-font-size"] = acf_units_field_value($pagination_item["font_size"]);
$variables["pagination-font-weight"] = $pagination_item["font_weight"];
$variables["pagination-font-weight-active"] = $pagination_item["font_weight_active"];
$variables["pagination-item-color"] = scss_variables_color($pagination_item["color"]);
$variables["pagination-item-color-hover"] = scss_variables_color($pagination_item["color_hover"]);
$variables["pagination-item-color-active"] = scss_variables_color($pagination_item["color_active"]);
$variables["pagination-item-bg-color"] = scss_variables_color($pagination_item["bg_color"]);
$variables["pagination-item-bg-color-hover"] = scss_variables_color($pagination_item["bg_color_hover"]);
$variables["pagination-item-bg-color-active"] = scss_variables_color($pagination_item["bg_color_active"]);
$variables["pagination-item-border"] = $pagination_item["border"];
$variables["pagination-item-border-hover"] = $pagination_item["border_hover"];
$variables["pagination-item-border-active"] = $pagination_item["border_active"];
$variables["pagination-item-border-radius"] = $pagination_item["border_radius"];
$pagination_nav= $pagination["nav"];
$variables["pagination-nav-font-family"] = variable_font($pagination_nav["font_family"]);
$variables["pagination-nav-font-size"] = acf_units_field_value($pagination_nav["font_size"]);
$variables["pagination-nav-color"] = scss_variables_color($pagination_nav["color"]);
$variables["pagination-nav-color-hover"] = scss_variables_color($pagination_nav["color_hover"]);
$variables["pagination-nav-color-disabled"] = scss_variables_color($pagination_nav["color_disabled"]);
$variables["pagination-nav-bg-color"] = scss_variables_color($pagination_nav["bg_color"]);
$variables["pagination-nav-bg-color-hover"] = scss_variables_color($pagination_nav["bg_color_hover"]);
$variables["pagination-nav-border"] = $pagination_nav["border"];
$variables["pagination-nav-border-hover"] = $pagination_nav["border_hover"];
$variables["pagination-nav-border-active"] = $pagination_nav["border_active"];
$variables["pagination-nav-border-radius"] = acf_units_field_value($pagination_nav["border_radius"]);
$variables["pagination-nav-prev-text"] = $pagination_nav["prev_text"];
$variables["pagination-nav-next-text"] = $pagination_nav["next_text"];
$variables["pagination-item-gap"] = acf_units_field_value($pagination_nav["gap"]);
// Hero
$hero = $theme_styles["hero"];
$variables["hero-height"] = acf_units_field_value($hero["height"][array_keys($hero["height"])[0]]);
foreach($hero["height"] as $key => $breakpoint){
$variables["hero-height-".$key] = acf_units_field_value($breakpoint);
}
// Offcanvas
$offcanvas = $theme_styles["offcanvas"];
$offcanvas_general = $offcanvas["offcanvas"];
$variables["offcanvas-bg"] = scss_variables_color($offcanvas_general["bg_color"]);
$variables["offcanvas-padding"] = $offcanvas_general["padding"];
$variables["offcanvas-align-hr"] = $offcanvas_general["align_hr"];
$variables["offcanvas-align-vr"] = $offcanvas_general["align_vr"];
$offcanvas_header = $offcanvas["header"];
$variables["offcanvas-header-font"] = variable_font($offcanvas_header["font_family"]);
$variables["offcanvas-header-font-size"] = acf_units_field_value($offcanvas_header["font_size"]);
$variables["offcanvas-header-font-weight"] = $offcanvas_header["font_weight"];
$variables["offcanvas-header-color"] = scss_variables_color($offcanvas_header["color"]);
$variables["offcanvas-header-padding"] = $offcanvas_header["padding"];
$variables["offcanvas-header-icon-font-size"] = acf_units_field_value($offcanvas_header["icon_font_size"]);
$variables["offcanvas-header-icon-color"] = scss_variables_color($offcanvas_header["icon_color"]);
$offcanvas_nav_item = $offcanvas["nav_item"];
$variables["offcanvas-item-font"] = variable_font($offcanvas_nav_item["font_family"]);
$variables["offcanvas-item-font-size"] = acf_units_field_value($offcanvas_nav_item["font_size"]);
$variables["offcanvas-item-font-weight"] = $offcanvas_nav_item["font_weight"];
$variables["offcanvas-item-color"] = scss_variables_color($offcanvas_nav_item["color"]);
$variables["offcanvas-item-color-hover"] = scss_variables_color($offcanvas_nav_item["color_hover"]);
$variables["offcanvas-item-bg"] = scss_variables_color($offcanvas_nav_item["bg_color"]);
$variables["offcanvas-item-bg-hover"] = scss_variables_color($offcanvas_nav_item["bg_color_hover"]);
$variables["offcanvas-item-padding"] = $offcanvas_nav_item["padding"];
$variables["offcanvas-item-align-hr"] = $offcanvas_nav_item["align_hr"];
$offcanvas_nav_sub = $offcanvas["nav_sub"];
$variables["offcanvas-dropdown-bg"] = scss_variables_color($offcanvas_nav_sub["bg_color"]);
$variables["offcanvas-dropdown-padding"] = $offcanvas_nav_sub["padding"];
$offcanvas_nav_sub_item = $offcanvas["nav_sub_item"];
$variables["offcanvas-dropdown-item-font-size"] = acf_units_field_value($offcanvas_nav_sub_item["font_size"]);
$variables["offcanvas-dropdown-item-font-color"] = scss_variables_color($offcanvas_nav_sub_item["color"]);
$variables["offcanvas-dropdown-item-font-color-hover"] = scss_variables_color($offcanvas_nav_sub_item["color_hover"]);
$variables["offcanvas-dropdown-item-font-weight"] = $offcanvas_nav_sub_item["font_weight"];
$variables["offcanvas-dropdown-item-font-weight-hover"] = $offcanvas_nav_sub_item["font_weight_hover"];
$variables["offcanvas-dropdown-item-padding"] = $offcanvas_nav_sub_item["padding"];
$variables["offcanvas-dropdown-item-bg"] = scss_variables_color($offcanvas_nav_sub_item["bg_color"]);
$variables["offcanvas-dropdown-item-bg-hover"] = scss_variables_color($offcanvas_nav_sub_item["bg_color_hover"]);
$variables["offcanvas-dropdown-item-border"] = $offcanvas_nav_sub_item["border"];
// Header Tools
$header_tools = $theme_styles["header_tools"];
$header_tools_general = $header_tools["header_tools"];
$height_header = $header_tools_general["height_header"]; // is same with header
$variables["header-tools-height"] = acf_units_field_value($header_tools_general["height"][array_keys($header_tools_general["height"])[0]]);
foreach($header_tools_general["height"] as $key => $breakpoint){
$variables["header-tools-height-".$key] = acf_units_field_value($breakpoint);
}
$variables["header-tools-height-affix"] = acf_units_field_value($header_tools_general["height_affix"][array_keys($header_tools_general["height_affix"])[0]]);
foreach($header_tools_general["height_affix"] as $key => $breakpoint){
$variables["header-tools-height-".$key."-affix"] = acf_units_field_value($breakpoint);
}
$variables["header-tools-item-gap"] = acf_units_field_value($header_tools_general["gap"][array_keys($header_tools_general["gap"])[0]]);
foreach($header_tools_general["gap"] as $key => $breakpoint){
$variables["header-tools-item-gap-".$key] = acf_units_field_value($breakpoint);
}
$header_tools_social = $header_tools["social"];
$variables["header-social-font"] = variable_font($header_tools_social["font_family"]);
$variables["header-social-font-size"] = acf_units_field_value($header_tools_social["font_size"]);
$variables["header-social-color"] = scss_variables_color($header_tools_social["color"]);
$variables["header-social-color-hover"] = scss_variables_color($header_tools_social["color_hover"]);
$variables["header-social-gap"] = acf_units_field_value($header_tools_social["gap"]);
$header_tools_icons = $header_tools["icons"];
$variables["header-icon-font"] = variable_font($header_tools_icons["font_family"]);
$variables["header-icon-font-size"] = acf_units_field_value($header_tools_icons["font_size"]);
$variables["header-icon-color"] = scss_variables_color($header_tools_icons["color"]);
$variables["header-icon-color-hover"] = scss_variables_color($header_tools_icons["color_hover"]);
$variables["header-icon-dot-color"] = scss_variables_color($header_tools_icons["dot_color"]);
$header_tools_link = $header_tools["link"];
$variables["header-link-font"] = variable_font($header_tools_link["font_family"]);
$variables["header-link-font-size"] = acf_units_field_value($header_tools_link["font_size"]);
$variables["header-link-font-weight"] = $header_tools_link["font_weight"];
$variables["header-link-color"] = scss_variables_color($header_tools_link["color"]);
$variables["header-link-color-hover"] = scss_variables_color($header_tools_link["color_hover"]);
$variables["header-link-color-active"] = scss_variables_color($header_tools_link["color_active"]);
$header_tools_button = $header_tools["button"];
$variables["header-btn-font"] = variable_font($header_tools_button["font_family"]);
$variables["header-btn-font-size"] = acf_units_field_value($header_tools_button["font_size"]);
$variables["header-btn-font-weight"] = $header_tools_button["font_weight"];
$header_tools_language = $header_tools["language"];
$variables["header-language-font"] = variable_font($header_tools_language["font_family"]);
$variables["header-language-font-size"] = acf_units_field_value($header_tools_language["font_size"]);
$variables["header-language-font-weight"] = $header_tools_language["font_weight"];
$variables["header-language-color"] = scss_variables_color($header_tools_language["color"]);
$variables["header-language-color-hover"] = scss_variables_color($header_tools_language["color_hover"]);
$variables["header-language-color-active"] = scss_variables_color($header_tools_language["color_active"]);
$header_tools_toggler = $header_tools["toggler"];
$variables["header-navbar-toggler-color"] = scss_variables_color($header_tools_toggler["color"]);
$variables["header-navbar-toggler-color-hover"] = scss_variables_color($header_tools_toggler["color_hover"]);
$header_tools_counter = $header_tools["counter"];
$variables["notification-count-color"] = scss_variables_color($header_tools_counter["color"]);
$variables["notification-count-bg-color"] = scss_variables_color($header_tools_counter["bg_color"]);
$variables["breakpoints"] = "'" . implode(",", array_keys($GLOBALS["breakpoints"])) . "'";
//Utilities
$scroll_to_top = $theme_styles["utilities"]["scroll_to_top"];
$variables["scroll-to-top-active"] = $scroll_to_top["active"];
if($scroll_to_top["active"]){
$variables["scroll-to-top-show"] = $scroll_to_top["show"];
$variables["scroll-to-top-hr"] = $scroll_to_top["position_hr"];
$variables["scroll-to-top-vr"] = $scroll_to_top["position_vr"];
$variables["scroll-to-top-bg-color"] = $scroll_to_top["bg_color"];
$variables["scroll-to-top-bg-color-hover"] = $scroll_to_top["bg_color_hover"];
$variables["scroll-to-top-color"] = $scroll_to_top["color"];
$variables["scroll-to-top-color-hover"] = $scroll_to_top["color_hover"];
$variables["scroll-to-top-width"] = $scroll_to_top["width"];
$variables["scroll-to-top-height"] = $scroll_to_top["height"];
$variables["scroll-to-top-radius"] = acf_units_field_value($scroll_to_top["radius"]);
$variables["scroll-to-top-gap"] = acf_units_field_value($scroll_to_top["gap"]);
$variables["scroll-to-top-font-size"] = acf_units_field_value($scroll_to_top["font_size"]);
$variables["scroll-to-top-duration"] = $scroll_to_top["duration"];
}
$pattern = '/class="([^"]*)"/';
$classes = [];
if (preg_match($pattern, $scroll_to_top["icon"], $matches)) {
if (!empty($matches[1])) {
$classes = explode(' ', $matches[1]);
}
}
update_dynamic_css_whitelist($classes);
}
return $variables;
}
function get_pages_need_updates($updated_plugins){
global $wpdb;
$pages = [];
$like_statements = [];
foreach ($updated_plugins as $term) {
$like_statements[] = $wpdb->prepare("meta_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
}
$like_conditions = implode(" OR ", $like_statements);
$query = "
(SELECT post_id as id, 'post' as type FROM $wpdb->postmeta WHERE meta_key = 'assets' AND ($like_conditions))
UNION
(SELECT term_id as id, 'term' as type FROM $wpdb->termmeta WHERE meta_key = 'assets' AND ($like_conditions))
UNION
(SELECT comment_id as id, 'comment' as type FROM $wpdb->commentmeta WHERE meta_key = 'assets' AND ($like_conditions))
UNION
(SELECT user_id as id, 'user' as type FROM $wpdb->usermeta WHERE meta_key = 'assets' AND ($like_conditions))
";
$results = $wpdb->get_results($query);
foreach ($results as $result) {
$pages[] = ["id" => intval($result->id), "type" => $result->type];
}
// Archive Control
$like_clauses = [];
foreach ($updated_plugins as $term) {
$like_clauses[] = $wpdb->prepare("option_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
}
$results = [];
if(ENABLE_MULTILANGUAGE){
if(ENABLE_MULTILANGUAGE == "polylang"){
$languages = pll_the_languages(['raw' => 1]);
foreach ($languages as $lang) {
$post_types = get_post_types(['public' => true], 'objects');
foreach ($post_types as $post_type) {
if ($post_type->has_archive) {
$option_name = "{$post_type->name}_{$lang['slug']}_assets";
$query = $wpdb->prepare(
"SELECT option_value FROM `{$wpdb->options}`
WHERE option_name = %s AND (" . implode(' OR ', $like_clauses) . ")",
$option_name
);
$option_value = $wpdb->get_var($query);
if ($option_value) {
foreach ($search_terms as $term) {
if (stripos($option_value, $term) !== false) {
$pages[] = [
'id' => $lang['slug'],
'type' => $post_type->name
];
}
}
}
}
}
}
}
}
$pages = array_unique($pages, SORT_REGULAR); // Tekrarları kaldır ve sonuçları döndür
$urls = [];
foreach($pages as $page){
if(is_string($page["id"])){
$url = pll_get_post_type_archive_link($page["type"], $page["id"]);
$urls[$page["type"]."_".$page["id"]] = [
"type" => "archive",
"url"  => $url
];
}else{
switch($page["type"]){
case "post" :
$url = get_permalink($page["id"]);
break;
case "term":
$url = get_term_link($page["id"]); // Term linkini al
break;
case "comment":
// Yorumların kendilerine özgü bir bağlantısı yoktur; eğer gerekli bir URL varsa, bunu belirlemelisin
$url = ''; // Yorumlar için spesifik bir bağlantı yoksa boş bırak
break;
case "user":
$url = get_author_posts_url($page["id"]); // Kullanıcı arşiv sayfasının URL'sini al
break;
}
$urls[$page["id"]] = [
"type" => $page["type"],
"url" => $url
];
}
}
$extractor = new PageAssetsExtractor();
return $extractor->fetch_urls($urls);
}
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
