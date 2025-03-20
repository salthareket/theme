<?php

add_action('wp_update_nav_menu', function($menu_id, $menu_data = []) {
    delete_transient('timber_menus'); // Menü cache'ini temizle
}, 10, 2);


add_filter('wp_editor_set_quality', function ($quality, $mime_type) {
    if ($mime_type === 'image/avif') {
        return get_google_optimized_avif_quality();
    }
    return $quality;
}, 10, 2);


function set_default_image_alt_text($attachment_id) {
    $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (empty($alt_text)) {
        $image_url = wp_get_attachment_url($attachment_id);
        $path_parts = pathinfo($image_url);
        $alt_text = ucwords(str_replace(['-', '_'], ' ', $path_parts['filename']));
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
    }
}
add_action('add_attachment', 'set_default_image_alt_text');



// görsel kayudederken gorselin ortalama renk degerini ve bu rengin kontrastını kaydet
function extract_and_save_average_color($post_ID = 0) {
    if(!$post_ID){
        return;
    }
    $mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    $image_path = get_attached_file($post_ID);
    $mime_type = get_post_mime_type($post_ID);
    if (in_array($mime_type, $mime_types)) {
        $colors = get_image_average_color($image_path);
        if($colors){
            update_post_meta($post_ID, 'average_color', $colors["average_color"]);
            update_post_meta($post_ID, 'contrast_color', $colors["contrast_color"]);            
        }
    }
}
add_action('add_attachment', 'extract_and_save_average_color');


// Admin profil sayfasına Title alanını "Name" başlıklı alana ekleyelim
add_action('show_user_profile', 'add_title_field', 0);
add_action('edit_user_profile', 'add_title_field', 0);

function add_title_field($user) {
    ?>
    <div class="postbox">
        <div class="postbox-header"><h2 id="user-title">User Title</h2></div>
        <table class="form-table m-0">
            <tr>
                <th><label for="title"><?php _e('Title'); ?></label></th>
                <td>
                    <input type="text" name="title" id="title" value="<?php echo esc_attr(get_user_meta($user->ID, 'title', true)); ?>" class="regular-text" /><br />
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// Title alanının kaydedilmesi
add_action('personal_options_update', 'save_title_field');
add_action('edit_user_profile_update', 'save_title_field');

function save_title_field($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'title', $_POST['title']);
    }
}






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
function scss_variables_image($value=""){
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
function scss_variables_font($font = ""){
    if(!empty($font)){
        $font = str_replace("|", "", $font);
    }
    return $font;
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
        "host_url" => "'" . $host_url . "'",
        "node_modules_path" =>  '"' . str_replace('\\', '/', NODE_MODULES_PATH) . '"',
        "theme_static_path" =>  '"' . str_replace('\\', '/', THEME_STATIC_PATH) . '"',
        "sh_static_path" =>  '"' . str_replace('\\', '/', SH_STATIC_PATH) . '"'
    ];

    //error_log(print_r($variables['theme_static_path'], true));

    
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





