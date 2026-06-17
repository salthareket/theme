<?php
/**
 * WooCommerce Custom Product Fields - Product Edit Page Display
 * 
 * Displays custom fields on product edit page with beautiful UI/UX
 * 
 * @package SaltHareket\Theme\WooCommerce\Admin
 * @version 2.0.0
 * @author SaltHareket
 * @since 2.0.0
 */

namespace SaltHareket\Theme\WooCommerce\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class CustomProductFieldsDisplay {
    
    public function __construct() {
        // Add custom fields to product data tabs
        add_filter('woocommerce_product_data_tabs', [$this, 'add_custom_fields_tab']);
        
        // Add custom fields panel content
        add_action('woocommerce_product_data_panels', [$this, 'add_custom_fields_panel']);
        
        // Save custom fields
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_fields']);
        
        // Add custom fields to variations
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_custom_fields'], 10, 3);
        
        // Save variation custom fields
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_custom_fields'], 10, 2);
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        if ($post_type !== 'product') {
            return;
        }
        
        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Date picker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
    }
    
    public function add_custom_fields_tab($tabs) {
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        if (empty($custom_fields)) {
            return $tabs;
        }
        
        $tabs['custom_fields'] = [
            'label' => __('Custom Fields', 'woocommerce'),
            'target' => 'custom_product_fields_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external'],
            'priority' => 25,
        ];
        
        return $tabs;
    }
    
    public function add_custom_fields_panel() {
        global $post;
        
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        if (empty($custom_fields)) {
            return;
        }
        
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }
        
        $product_type = $product->get_type();
        
        ?>
        <div id="custom_product_fields_data" class="panel woocommerce_options_panel">
            <div class="options_group custom-fields-group">
                <?php
                foreach ($custom_fields as $field) {
                    // Check if field should be shown for this product type
                    if (!empty($field['product_types']) && !in_array($product_type, $field['product_types'])) {
                        continue;
                    }
                    
                    // Skip variation-only fields on parent product
                    if ($product_type === 'variable' && !empty($field['variation_only'])) {
                        continue;
                    }
                    
                    $this->render_field($field, $post->ID);
                }
                ?>
            </div>
            
            <?php $this->render_field_styles(); ?>
            <?php $this->render_field_scripts(); ?>
        </div>
        <?php
    }
    
    private function render_field($field, $product_id) {
        $field_id = $field['id'] ?? '';
        $field_type = $field['type'] ?? 'text';
        $field_label = $field['label'] ?? '';
        $field_description = $field['description'] ?? '';
        $field_placeholder = $field['placeholder'] ?? '';
        $field_value = get_post_meta($product_id, '_custom_field_' . $field_id, true);
        $field_default = $field['value'] ?? '';
        $field_required = !empty($field['required']);
        
        // Use default value if no value is set
        if ($field_value === '' && $field_default !== '') {
            $field_value = $field_default;
        }
        
        $wrapper_class = 'form-field _custom_field_' . $field_id . '_field';
        if (!empty($field['wrapper_class'])) {
            $wrapper_class .= ' ' . $field['wrapper_class'];
        }
        
        $input_class = !empty($field['class']) ? $field['class'] : '';
        $label_class = !empty($field['label_class']) ? $field['label_class'] : '';
        
        switch ($field_type) {
            case 'textarea':
                woocommerce_wp_textarea_input([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                    'rows' => $field['rows'] ?? 3,
                    'cols' => $field['cols'] ?? 50,
                    'custom_attributes' => $this->parse_custom_attributes($field['custom_attributes'] ?? ''),
                ]);
                break;
                
            case 'checkbox':
                woocommerce_wp_checkbox([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'cbvalue' => $field_default ?: '1',
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                ]);
                break;
                
            case 'select':
                $options = $this->parse_options($field['options'] ?? '');
                woocommerce_wp_select([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'options' => $options,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                ]);
                break;
                
            case 'date':
                woocommerce_wp_text_input([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class . ' wc-custom-datepicker',
                    'type' => 'text',
                    'custom_attributes' => array_merge(
                        ['data-field-type' => 'date'],
                        $this->parse_custom_attributes($field['custom_attributes'] ?? '')
                    ),
                ]);
                break;
                
            case 'color':
                woocommerce_wp_text_input([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class . ' wc-custom-colorpicker',
                    'type' => 'text',
                    'custom_attributes' => ['data-field-type' => 'color'],
                ]);
                break;
                
            case 'number':
                woocommerce_wp_text_input([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                    'type' => 'number',
                    'custom_attributes' => $this->parse_custom_attributes($field['custom_attributes'] ?? ''),
                ]);
                break;
                
            default: // text, email, url, tel, password
                woocommerce_wp_text_input([
                    'id' => '_custom_field_' . $field_id,
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'desc_tip' => !empty($field['desc_tip']),
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                    'type' => $field_type,
                    'custom_attributes' => $this->parse_custom_attributes($field['custom_attributes'] ?? ''),
                ]);
                break;
        }
    }
    
    public function add_variation_custom_fields($loop, $variation_data, $variation) {
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        if (empty($custom_fields)) {
            return;
        }
        
        echo '<div class="custom-variation-fields">';
        echo '<h4 style="margin: 15px 12px; padding-top: 15px; border-top: 1px solid #eee;">' . __('Custom Fields', 'woocommerce') . '</h4>';
        
        foreach ($custom_fields as $field) {
            // Only show fields that are for variable products
            if (empty($field['product_types']) || !in_array('variable', $field['product_types'])) {
                continue;
            }
            
            // Only show variation-only fields
            if (empty($field['variation_only'])) {
                continue;
            }
            
            $this->render_variation_field($field, $loop, $variation->ID);
        }
        
        echo '</div>';
    }
    
    private function render_variation_field($field, $loop, $variation_id) {
        $field_id = $field['id'] ?? '';
        $field_type = $field['type'] ?? 'text';
        $field_label = $field['label'] ?? '';
        $field_description = $field['description'] ?? '';
        $field_placeholder = $field['placeholder'] ?? '';
        $field_value = get_post_meta($variation_id, '_custom_field_' . $field_id, true);
        $field_default = $field['value'] ?? '';
        
        if ($field_value === '' && $field_default !== '') {
            $field_value = $field_default;
        }
        
        $wrapper_class = 'form-row form-row-full';
        if (!empty($field['wrapper_class'])) {
            $wrapper_class .= ' ' . $field['wrapper_class'];
        }
        
        $input_class = !empty($field['class']) ? $field['class'] : '';
        
        switch ($field_type) {
            case 'textarea':
                woocommerce_wp_textarea_input([
                    'id' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'name' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                    'rows' => $field['rows'] ?? 3,
                ]);
                break;
                
            case 'checkbox':
                woocommerce_wp_checkbox([
                    'id' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'name' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'label' => $field_label,
                    'description' => $field_description,
                    'value' => $field_value,
                    'cbvalue' => $field_default ?: '1',
                    'wrapper_class' => $wrapper_class,
                ]);
                break;
                
            case 'select':
                $options = $this->parse_options($field['options'] ?? '');
                woocommerce_wp_select([
                    'id' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'name' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'label' => $field_label,
                    'description' => $field_description,
                    'value' => $field_value,
                    'options' => $options,
                    'wrapper_class' => $wrapper_class,
                ]);
                break;
                
            default:
                woocommerce_wp_text_input([
                    'id' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'name' => '_custom_field_' . $field_id . '[' . $loop . ']',
                    'label' => $field_label,
                    'placeholder' => $field_placeholder,
                    'description' => $field_description,
                    'value' => $field_value,
                    'wrapper_class' => $wrapper_class,
                    'class' => $input_class,
                    'type' => $field_type,
                ]);
                break;
        }
    }
    
    public function save_custom_fields($post_id) {
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        if (empty($custom_fields)) {
            return;
        }
        
        foreach ($custom_fields as $field) {
            $field_id = $field['id'] ?? '';
            $meta_key = '_custom_field_' . $field_id;
            
            if (isset($_POST[$meta_key])) {
                $value = $_POST[$meta_key];
                
                // Sanitize based on field type
                switch ($field['type'] ?? 'text') {
                    case 'textarea':
                        $value = sanitize_textarea_field($value);
                        break;
                    case 'email':
                        $value = sanitize_email($value);
                        break;
                    case 'url':
                        $value = esc_url_raw($value);
                        break;
                    case 'number':
                        $value = floatval($value);
                        break;
                    case 'checkbox':
                        $value = $value ? '1' : '';
                        break;
                    default:
                        $value = sanitize_text_field($value);
                        break;
                }
                
                update_post_meta($post_id, $meta_key, $value);
            } else {
                // If checkbox is not set, save empty value
                if (($field['type'] ?? '') === 'checkbox') {
                    update_post_meta($post_id, $meta_key, '');
                }
            }
        }
    }
    
    public function save_variation_custom_fields($variation_id, $loop) {
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        if (empty($custom_fields)) {
            return;
        }
        
        foreach ($custom_fields as $field) {
            // Only save variation-only fields
            if (empty($field['variation_only'])) {
                continue;
            }
            
            $field_id = $field['id'] ?? '';
            $meta_key = '_custom_field_' . $field_id;
            
            if (isset($_POST[$meta_key][$loop])) {
                $value = $_POST[$meta_key][$loop];
                
                // Sanitize based on field type
                switch ($field['type'] ?? 'text') {
                    case 'textarea':
                        $value = sanitize_textarea_field($value);
                        break;
                    case 'email':
                        $value = sanitize_email($value);
                        break;
                    case 'url':
                        $value = esc_url_raw($value);
                        break;
                    case 'number':
                        $value = floatval($value);
                        break;
                    case 'checkbox':
                        $value = $value ? '1' : '';
                        break;
                    default:
                        $value = sanitize_text_field($value);
                        break;
                }
                
                update_post_meta($variation_id, $meta_key, $value);
            } else {
                if (($field['type'] ?? '') === 'checkbox') {
                    update_post_meta($variation_id, $meta_key, '');
                }
            }
        }
    }
    
    private function parse_custom_attributes($attributes_string) {
        if (empty($attributes_string)) {
            return [];
        }
        
        $attributes = [];
        
        // Parse attributes like: min="0" max="100" step="1"
        preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $attributes_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }
        
        return $attributes;
    }
    
    private function parse_options($options_string) {
        if (empty($options_string)) {
            return ['' => __('Select an option', 'woocommerce')];
        }
        
        $options = ['' => __('Select an option', 'woocommerce')];
        $lines = explode("\n", $options_string);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Format: key:Label or just Label
            if (strpos($line, ':') !== false) {
                list($key, $label) = explode(':', $line, 2);
                $options[trim($key)] = trim($label);
            } else {
                $options[$line] = $line;
            }
        }
        
        return $options;
    }
    
    private function render_field_styles() {
        ?>
        <style>
            .custom-fields-group {
                padding: 12px;
            }
            
            .custom-fields-group .form-field {
                padding: 8px 12px;
            }
            
            .custom-variation-fields {
                background: #f9f9f9;
                margin: 12px;
                padding: 0 12px 12px;
                border-radius: 4px;
            }
            
            .custom-variation-fields h4 {
                color: #555;
                font-size: 13px;
                font-weight: 600;
            }
        </style>
        <?php
    }
    
    private function render_field_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.wc-custom-colorpicker').wpColorPicker();
            
            // Initialize date pickers
            $('.wc-custom-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        });
        </script>
        <?php
    }
}

// Initialize
new CustomProductFieldsDisplay();
