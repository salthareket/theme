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

add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id){
    $file = get_attached_file($attachment_id);
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') return $metadata;
    return $metadata; // diğer formatlar için normal conversion
}, 20, 2);



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
        $font = '"'.str_replace("|", "", $font).'"';
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





if(ENABLE_ECOMMERCE){
    // add order received page select
    add_filter('woocommerce_get_settings_advanced', 'add_order_received_page_setting', 10, 2);
    function add_order_received_page_setting($settings, $current_section) {
        if ($current_section !== '') return $settings;
        $new_settings = [];
        foreach ($settings as $setting) {
            $new_settings[] = $setting;
            if (isset($setting['id']) && $setting['id'] === 'woocommerce_checkout_page_id') {
                $new_settings[] = [
                    'title'    => __('Order Received Page', 'your-textdomain'),
                    'desc'     => __('This page will be used as the custom "Order Received" page.', TEXT_DOMAIN),
                    'id'       => 'woocommerce_order_received_page_id',
                    'type'     => 'single_select_page_with_search',
                    'default'  => '',
                    'class'    => 'wc-page-search',
                    'css'      => 'min-width:300px;',
                    'desc_tip' => true,
                    'autoload' => false,
                ];
            }
        }
        return $new_settings;
    }


    // Admin sayfasında alanları gösterme
    add_filter('woocommerce_get_sections_products', function ($sections) {
        $sections['extra_fields'] = 'Extra Fields';
        return $sections;
    });
    function render_custom_product_fields() {
        if (isset($_GET['section']) && $_GET['section'] === 'extra_fields') {
        $custom_fields = get_option('custom_product_fields', array());

        $exclude = []; // hariç tutmak istediklerin
        $views = array_filter(
            array_keys( wc_get_product_types() ),
            fn($type) => !in_array($type, $exclude)
        );

        // Alan tipleri ve parametrelerin hangi tiplerde görüneceği
        $types = [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'number' => 'Number',
            'checkbox' => 'Checkbox',
            'select' => 'Select',
            'date' => 'Date Picker',
            'color' => 'Color Picker',
            'password' => 'Password',
            'email' => 'Email',
            'url' => 'URL',
            'tel' => 'Telephone',
        ];

        ?>
        <style>
            #custom-product-fields-wrapper{
                max-width:500px;
            }
            .inline-group {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            .inline-group > div {
        
            }
            .field-label {
                font-weight: bold;
                margin-bottom: 3px;
                display: block;
            }
            .custom-product-field-item {
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
                background: #f9f9f9;
            }
            .remove-field {
                margin-top: 10px;
                background: #e74c3c;
                border: none;
                color: white;
                padding: 5px 10px;
                cursor: pointer;
                border-radius:8px;
            }
        </style>

        <div id="custom-product-fields-wrapper">
        <?php foreach ($custom_fields as $index => $field): ?>
            <div class="custom-product-field-item" data-index="<?php echo $index; ?>">
                <div class="inline-group">
                    <div class="w-100">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_type">Type</label>
                        <select class="custom-type-select w-50" id="custom_product_fields_<?php echo $index; ?>_type" name="custom_product_fields[<?php echo $index; ?>][type]" required>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php selected($field['type'] ?? '', $key); ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-100">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_id">ID</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_id" name="custom_product_fields[<?php echo $index; ?>][id]" value="<?php echo esc_attr($field['id'] ?? ''); ?>" placeholder="unique_field_id" class="w-100" required>
                    </div>
                    <div class="w-100">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_label">Label</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_label" name="custom_product_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($field['label'] ?? ''); ?>" class="w-100" required>
                    </div>
                </div>

                <div class="inline-group">
                    <div class="desc-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_description">Description</label>
                        <textarea id="custom_product_fields_<?php echo $index; ?>_description" name="custom_product_fields[<?php echo $index; ?>][description]" rows="2"><?php echo esc_textarea($field['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="placeholder-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_placeholder">Placeholder</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_placeholder" name="custom_product_fields[<?php echo $index; ?>][placeholder]" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
                    </div>
                </div>

                <div class="inline-group">
                    <div class="rows-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_rows">Rows (textarea)</label>
                        <input type="number" min="1" id="custom_product_fields_<?php echo $index; ?>_rows" name="custom_product_fields[<?php echo $index; ?>][rows]" value="<?php echo esc_attr($field['rows'] ?? ''); ?>">
                    </div>
                    <div class="cols-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_cols">Cols (textarea)</label>
                        <input type="number" min="1" id="custom_product_fields_<?php echo $index; ?>_cols" name="custom_product_fields[<?php echo $index; ?>][cols]" value="<?php echo esc_attr($field['cols'] ?? ''); ?>">
                    </div>
                    <div class="desc_tip-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_desc_tip">Desc Tip</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_desc_tip" name="custom_product_fields[<?php echo $index; ?>][desc_tip]" value="<?php echo esc_attr($field['desc_tip'] ?? ''); ?>">
                    </div>
                </div>

                <div class="inline-group">
                    <div class="custom_attributes-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_custom_attributes">Custom Attributes</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_custom_attributes" name="custom_product_fields[<?php echo $index; ?>][custom_attributes]" value="<?php echo esc_attr($field['custom_attributes'] ?? ''); ?>" placeholder='Örnek: min="0" max="100" step="1"'>
                    </div>
                    <div class="value-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_value">Value</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_value" name="custom_product_fields[<?php echo $index; ?>][value]" value="<?php echo esc_attr($field['value'] ?? ''); ?>">
                    </div>
                </div>

                <div class="inline-group">
                    <div class="wrapper_class-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_wrapper_class">Wrapper Class</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_wrapper_class" name="custom_product_fields[<?php echo $index; ?>][wrapper_class]" value="<?php echo esc_attr($field['wrapper_class'] ?? ''); ?>">
                    </div>
                    <div class="class-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_class">Class</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_class" name="custom_product_fields[<?php echo $index; ?>][class]" value="<?php echo esc_attr($field['class'] ?? ''); ?>">
                    </div>
                    <div class="label_class-wrapper">
                        <label class="field-label" for="custom_product_fields_<?php echo $index; ?>_label_class">Label Class</label>
                        <input type="text" id="custom_product_fields_<?php echo $index; ?>_label_class" name="custom_product_fields[<?php echo $index; ?>][label_class]" value="<?php echo esc_attr($field['label_class'] ?? ''); ?>">
                    </div>
                </div>

                <!-- View (Multi Select) -->
                    <div class="w-100">
                        <label class="field-label" for="field_<?php echo $index; ?>_view">View (Product Types)</label>
                        <select multiple name="custom_product_fields[<?php echo $index; ?>][view][]" id="field_<?php echo $index; ?>_view" class="custom-view-select w-100 select2" data-show=".only-variable-checkbox[data-index='.<?php echo $index; ?>.']">
                            <?php foreach ($views as $view): ?>
                                <option value="<?php echo $view; ?>" <?php if (!empty($field['view']) && in_array($view, $field['view'])) echo 'selected'; ?>><?php echo ucfirst($view); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Only for Variations -->
                    <div class="w-100 only-variable-checkbox" data-index="<?php echo $index; ?>" style="<?php echo (!empty($field['view']) && in_array('variable', $field['view'])) ? '' : 'display:none;'; ?>">
                        <label>
                            <input type="checkbox" name="custom_product_fields[<?php echo $index; ?>][variation_field]" value="1" <?php checked($field['variation_field'] ?? '', '1'); ?> />
                            Only show inside variations
                        </label>
                    </div>

                <button type="button" class="remove-field">Remove Field</button>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Select2 ve Toggle için JS -->
        <script>
            jQuery(document).ready(function ($) {
                $('.select2').select2();

                function handleVariableCheckboxVisibility($select) {
                    var views = $select.val();
                    var index = $select.attr('id').match(/field_(\d+)_view/)[1];
                    var variableDiv = $('.only-variable-checkbox[data-index="' + index + '"]');

                    if (Array.isArray(views) && views.includes('variable')) {
                        variableDiv.show();
                    } else {
                        variableDiv.hide();
                        variableDiv.find('input[type="checkbox"]').prop('checked', false);
                    }
                }


                $('.custom-view-select').each(function () {
                    handleVariableCheckboxVisibility($(this)); // sayfa yüklendiğinde kontrol et
                });

                // Select2 değişikliklerini dinle
                $('.custom-view-select').on('change select2:select select2:unselect', function () {
                    handleVariableCheckboxVisibility($(this));
                });
            });
        </script>

        <button type="button" id="add-custom-field" class="button button-primary">Add New Field</button>

        <script>
            jQuery(document).ready(function($){

                function toggleFieldVisibility($container) {
                    const type = $container.find('.custom-type-select').val();

                    // Show/hide description: text, textarea, number, email, url, tel, password
                    if(['text', 'textarea', 'number', 'email', 'url', 'tel', 'password'].includes(type)) {
                        $container.find('.desc-wrapper').show();
                    } else {
                        $container.find('.desc-wrapper').hide();
                    }

                    // Show placeholder for all except checkbox/select/date/color
                    if(['checkbox', 'select', 'date', 'color'].includes(type)) {
                        $container.find('.placeholder-wrapper').hide();
                    } else {
                        $container.find('.placeholder-wrapper').show();
                    }

                    // Show rows & cols only for textarea
                    if(type === 'textarea') {
                        $container.find('.rows-wrapper, .cols-wrapper').show();
                    } else {
                        $container.find('.rows-wrapper, .cols-wrapper').hide();
                    }

                    // Show desc_tip only for text, textarea
                    if(['text', 'textarea'].includes(type)) {
                        $container.find('.desc_tip-wrapper').show();
                    } else {
                        $container.find('.desc_tip-wrapper').hide();
                    }

                    // Custom attributes only for number type
                    if(type === 'number') {
                        $container.find('.custom_attributes-wrapper').show();
                    } else {
                        $container.find('.custom_attributes-wrapper').hide();
                    }

                    // Value is shown for all types
                    $container.find('.value-wrapper').show();

                    // Wrapper_class, class, label_class always visible
                    $container.find('.wrapper_class-wrapper, .class-wrapper, .label_class-wrapper').show();
                }

                // İlk yüklemede toggle yap
                $('#custom-product-fields-wrapper .custom-product-field-item').each(function(){
                    toggleFieldVisibility($(this));
                });

                // Type değişince toggle
                $(document).on('change', '.custom-type-select', function(){
                    toggleFieldVisibility($(this).closest('.custom-product-field-item'));
                });

                // Remove field
                $(document).on('click', '.remove-field', function(){
                    $(this).closest('.custom-product-field-item').remove();
                });

                // Add new field
                $('#add-custom-field').on('click', function(){
                    const wrapper = $('#custom-product-fields-wrapper');
                    const index = wrapper.children().length;
                    const typesOptions = `<?php
                        ob_start();
                        foreach ($types as $key => $label) {
                            echo "<option value=\"$key\">$label</option>";
                        }
                        $options = ob_get_clean();
                        echo addslashes($options);
                    ?>`;

                    const newFieldHTML = `
                    <div class="custom-product-field-item" data-index="${index}">
                        <div class="inline-group">
                            <div class="w-100">
                                <label class="field-label" for="custom_product_fields_${index}_type">Type</label>
                                <select class="custom-type-select w-50" id="custom_product_fields_${index}_type" name="custom_product_fields[${index}][type]" required>
                                    ${typesOptions}
                                </select>
                            </div>
                            <div class="w-100">
                                <label class="field-label" for="custom_product_fields_${index}_id">ID</label>
                                <input type="text" id="custom_product_fields_${index}_id" name="custom_product_fields[${index}][id]" placeholder="unique_field_id" class="w-100" required>
                            </div>
                            <div class="w-100">
                                <label class="field-label" for="custom_product_fields_${index}_label">Label</label>
                                <input type="text" id="custom_product_fields_${index}_label" name="custom_product_fields[${index}][label]" class="w-100" required>
                            </div>
                        </div>

                        <div class="inline-group">
                            <div class="desc-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_description">Description</label>
                                <textarea id="custom_product_fields_${index}_description" name="custom_product_fields[${index}][description]" rows="2"></textarea>
                            </div>

                            <div class="placeholder-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_placeholder">Placeholder</label>
                                <input type="text" id="custom_product_fields_${index}_placeholder" name="custom_product_fields[${index}][placeholder]">
                            </div>
                        </div>

                        <div class="inline-group">
                            <div class="rows-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_rows">Rows (textarea)</label>
                                <input type="number" min="1" id="custom_product_fields_${index}_rows" name="custom_product_fields[${index}][rows]">
                            </div>
                            <div class="cols-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_cols">Cols (textarea)</label>
                                <input type="number" min="1" id="custom_product_fields_${index}_cols" name="custom_product_fields[${index}][cols]">
                            </div>
                            <div class="desc_tip-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_desc_tip">Desc Tip</label>
                                <input type="text" id="custom_product_fields_${index}_desc_tip" name="custom_product_fields[${index}][desc_tip]">
                            </div>
                        </div>

                        <div class="inline-group">
                            <div class="custom_attributes-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_custom_attributes">Custom Attributes</label>
                                <input type="text" id="custom_product_fields_${index}_custom_attributes" name="custom_product_fields[${index}][custom_attributes]" placeholder='Örnek: min="0" max="100" step="1"'>
                            </div>
                            <div class="value-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_value">Value</label>
                                <input type="text" id="custom_product_fields_${index}_value" name="custom_product_fields[${index}][value]">
                            </div>
                        </div>

                        <div class="inline-group">
                            <div class="wrapper_class-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_wrapper_class">Wrapper Class</label>
                                <input type="text" id="custom_product_fields_${index}_wrapper_class" name="custom_product_fields[${index}][wrapper_class]">
                            </div>
                            <div class="class-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_class">Class</label>
                                <input type="text" id="custom_product_fields_${index}_class" name="custom_product_fields[${index}][class]">
                            </div>
                            <div class="label_class-wrapper">
                                <label class="field-label" for="custom_product_fields_${index}_label_class">Label Class</label>
                                <input type="text" id="custom_product_fields_${index}_label_class" name="custom_product_fields[${index}][label_class]">
                            </div>
                        </div>

                            <!-- View (Multi Select) -->
                            <div class="w-100">
                                <label class="field-label" for="custom_product_fields_${index}_view">View (Product Types)</label>
                                <select multiple name="custom_product_fields[${index}][view][]" id="custom_product_fields_${index}_view" class="custom-view-select w-100 select2" data-index="${index}">
                                    <?php foreach ($views as $view): ?>
                                        <option value="<?php echo $view; ?>" <?php if (!empty($field['view']) && in_array($view, $field['view'])) echo 'selected'; ?>><?php echo ucfirst($view); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Only for Variations -->
                            <div class="w-100 only-variable-checkbox" data-index="${index}" style="<?php echo (!empty($field['view']) && in_array('variable', $field['view'])) ? '' : 'display:none;'; ?>">
                                <label>
                                    <input type="checkbox" name="custom_product_fields[${index}][variation_field]" value="1" <?php checked($field['variation_field'] ?? '', '1'); ?> />
                                    Only show inside variations
                                </label>
                            </div>

                            <button type="button" class="remove-field">Remove Field</button>
                        </div>`;

                    wrapper.append(newFieldHTML);

                    $('.select2').select2();

                    function handleVariableCheckboxVisibility($select) {
                        var views = $select.val();
                        console.log(views)
                        var index = $select.data('index');
                        console.log(index)
                        var variableDiv = $('.only-variable-checkbox[data-index="' + index + '"]');

                        if (Array.isArray(views) && views.includes('variable')) {
                            variableDiv.show();
                        } else {
                            variableDiv.hide();
                            variableDiv.find('input[type="checkbox"]').prop('checked', false);
                        }
                    }


                    $('.custom-view-select').each(function () {
                        handleVariableCheckboxVisibility($(this)); // sayfa yüklendiğinde kontrol et
                    });

                    // Select2 değişikliklerini dinle
                    $('.custom-view-select').on('change select2:select select2:unselect', function () {
                        handleVariableCheckboxVisibility($(this));
                    });
                    toggleFieldVisibility(wrapper.find(`.custom-product-field-item[data-index="${index}"]`));
                });
            });
        </script>
    <?php
    }
    }
    function save_custom_product_fields() {
        if (isset($_GET['section']) && $_GET['section'] !== 'extra_fields') {
            return;
        }

        if (isset($_POST['custom_product_fields']) && is_array($_POST['custom_product_fields'])) {
            $fields = $_POST['custom_product_fields'];
            $cleaned = array();
            foreach ($fields as $field) {
                $cleaned[] = array(
                    'id'              => isset($field['id']) ? sanitize_key($field['id']) : '',
                    'title'           => isset($field['title']) ? sanitize_text_field($field['title']) : '',
                    'desc'            => isset($field['desc']) ? sanitize_textarea_field($field['desc']) : '',
                    'type'            => isset($field['type']) ? sanitize_text_field($field['type']) : '',
                    'placeholder'     => isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '',
                    'rows'            => isset($field['rows']) ? intval($field['rows']) : 0,
                    'cols'            => isset($field['cols']) ? intval($field['cols']) : 0,
                    'desc_tip'        => isset($field['desc_tip']) ? sanitize_text_field($field['desc_tip']) : '',
                    'custom_attributes' => isset($field['custom_attributes']) ? sanitize_text_field($field['custom_attributes']) : '',
                    'value'           => isset($field['value']) ? sanitize_text_field($field['value']) : '',
                    'wrapper_class'   => isset($field['wrapper_class']) ? sanitize_text_field($field['wrapper_class']) : '',
                    'class'           => isset($field['class']) ? sanitize_text_field($field['class']) : '',
                    'label_class'     => isset($field['label_class']) ? sanitize_text_field($field['label_class']) : '',
                    'options'         => isset($field['options']) ? sanitize_text_field($field['options']) : '',
                    'view'            => isset($field['view']) && is_array($field['view']) ? array_map('sanitize_text_field', $field['view']) : array(),
                );
            }
            update_option('custom_product_fields', $cleaned);
        }
    }
    add_action('woocommerce_settings_products', 'render_custom_product_fields');
    add_action('woocommerce_update_options_products', 'save_custom_product_fields');/**/
}







add_action('wp_login', function($user_login, $user) {
    error_log(print_r("wp_login", true));
    error_log(print_r($user, true));

    if ( user_can($user, 'manage_options') ) {
        $content = "User-agent: *\nDisallow:\n";

        if ( function_exists('wpseo_sitemap_url') ) {
            $content .= 'Sitemap: ' . wpseo_sitemap_url() . "\n";
        } else {
            $content .= 'Sitemap: ' . home_url('/sitemap.xml') . "\n";
        }

        $extra_sitemaps = ['llms.txt', 'ssms.txt'];
        foreach ($extra_sitemaps as $file) {
            if ( file_exists(ABSPATH . $file) ) {
                $content .= 'Sitemap: ' . home_url('/' . $file) . "\n";
            }
        }

        // Dosya yazılıyor
        file_put_contents(ABSPATH . 'robots.txt', $content);
        error_log("Debug - robots.txt dosyası oluşturuldu.");
    }
}, 11, 2);