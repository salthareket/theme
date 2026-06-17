<?php
/**
 * WooCommerce Custom Product Fields - Helper Functions
 * 
 * Helper functions to get and display custom field values in templates
 * 
 * @package SaltHareket\Theme\WooCommerce
 * @version 2.0.0
 * @author SaltHareket
 * @since 1.0.0
 * 
 * CHANGELOG:
 * 
 * 2.0.0 - 2026-04-23
 * - Added CustomTimberProduct class with woo_meta() method
 * - Added magic getter support with woo_meta_ prefix
 * - Added comprehensive @example documentation
 * - Added woo_meta_all(), woo_meta_formatted(), has_woo_meta() methods
 * - Improved conflict prevention with native properties
 * 
 * 1.5.0 - 2026-04-22
 * - Added wc_get_custom_field_formatted() for URL, email, color fields
 * - Added wc_display_all_custom_fields() for automatic display
 * - Added support for select field options parsing
 * 
 * 1.0.0 - 2026-04-20
 * - Initial release
 * - Basic helper functions: wc_get_custom_field(), wc_get_all_custom_fields()
 * - Display functions: wc_display_custom_field()
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get custom field value for a product
 * 
 * @param int|WC_Product $product Product ID or object
 * @param string $field_id Custom field ID
 * @param mixed $default Default value if field is empty
 * @return mixed Field value
 */
function wc_get_custom_field($product, $field_id, $default = '') {
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }
    
    if (!$product) {
        return $default;
    }
    
    $value = $product->get_meta('_custom_field_' . $field_id, true);
    
    return $value !== '' ? $value : $default;
}

/**
 * Get all custom fields for a product
 * 
 * @param int|WC_Product $product Product ID or object
 * @return array Array of field_id => value
 */
function wc_get_all_custom_fields($product) {
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }
    
    if (!$product) {
        return [];
    }
    
    $custom_fields = get_option('wc_custom_product_fields', []);
    $values = [];
    
    foreach ($custom_fields as $field) {
        $field_id = $field['id'] ?? '';
        if (empty($field_id)) {
            continue;
        }
        
        $value = $product->get_meta('_custom_field_' . $field_id, true);
        
        if ($value !== '') {
            $values[$field_id] = [
                'label' => $field['label'] ?? '',
                'value' => $value,
                'type' => $field['type'] ?? 'text',
                'field' => $field,
            ];
        }
    }
    
    return $values;
}

/**
 * Display custom field value with label
 * 
 * @param int|WC_Product $product Product ID or object
 * @param string $field_id Custom field ID
 * @param array $args Display arguments
 */
function wc_display_custom_field($product, $field_id, $args = []) {
    $defaults = [
        'before' => '<div class="custom-field custom-field-' . esc_attr($field_id) . '">',
        'after' => '</div>',
        'label_before' => '<span class="custom-field-label">',
        'label_after' => ':</span> ',
        'value_before' => '<span class="custom-field-value">',
        'value_after' => '</span>',
        'show_label' => true,
        'default' => '',
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $value = wc_get_custom_field($product, $field_id, $args['default']);
    
    if ($value === '' || $value === $args['default']) {
        return;
    }
    
    // Get field config
    $custom_fields = get_option('wc_custom_product_fields', []);
    $field_config = null;
    
    foreach ($custom_fields as $field) {
        if (($field['id'] ?? '') === $field_id) {
            $field_config = $field;
            break;
        }
    }
    
    if (!$field_config) {
        return;
    }
    
    echo $args['before'];
    
    if ($args['show_label']) {
        echo $args['label_before'] . esc_html($field_config['label'] ?? $field_id) . $args['label_after'];
    }
    
    echo $args['value_before'];
    
    // Format value based on field type
    switch ($field_config['type'] ?? 'text') {
        case 'checkbox':
            echo $value ? __('Yes', 'woocommerce') : __('No', 'woocommerce');
            break;
            
        case 'url':
            echo '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
            break;
            
        case 'email':
            echo '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            break;
            
        case 'tel':
            echo '<a href="tel:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            break;
            
        case 'color':
            echo '<span class="color-preview" style="display:inline-block;width:20px;height:20px;background:' . esc_attr($value) . ';border:1px solid #ddd;border-radius:3px;vertical-align:middle;margin-right:5px;"></span>';
            echo esc_html($value);
            break;
            
        case 'textarea':
            echo nl2br(esc_html($value));
            break;
            
        case 'select':
            // Get option label
            $options = [];
            if (!empty($field_config['options'])) {
                $lines = explode("\n", $field_config['options']);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, ':') !== false) {
                        list($key, $label) = explode(':', $line, 2);
                        $options[trim($key)] = trim($label);
                    } else {
                        $options[$line] = $line;
                    }
                }
            }
            echo esc_html($options[$value] ?? $value);
            break;
            
        default:
            echo esc_html($value);
            break;
    }
    
    echo $args['value_after'];
    echo $args['after'];
}

/**
 * Display all custom fields for a product
 * 
 * @param int|WC_Product $product Product ID or object
 * @param array $args Display arguments
 */
function wc_display_all_custom_fields($product, $args = []) {
    $defaults = [
        'before' => '<div class="custom-fields-list">',
        'after' => '</div>',
        'title' => __('Additional Information', 'woocommerce'),
        'title_before' => '<h3 class="custom-fields-title">',
        'title_after' => '</h3>',
        'show_title' => true,
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $fields = wc_get_all_custom_fields($product);
    
    if (empty($fields)) {
        return;
    }
    
    echo $args['before'];
    
    if ($args['show_title'] && !empty($args['title'])) {
        echo $args['title_before'] . esc_html($args['title']) . $args['title_after'];
    }
    
    foreach ($fields as $field_id => $field_data) {
        wc_display_custom_field($product, $field_id);
    }
    
    echo $args['after'];
}

/**
 * Get custom field config
 * 
 * @param string $field_id Field ID
 * @return array|null Field config or null if not found
 */
function wc_get_custom_field_config($field_id) {
    $custom_fields = get_option('wc_custom_product_fields', []);
    
    foreach ($custom_fields as $field) {
        if (($field['id'] ?? '') === $field_id) {
            return $field;
        }
    }
    
    return null;
}

/**
 * Check if product has custom field
 * 
 * @param int|WC_Product $product Product ID or object
 * @param string $field_id Custom field ID
 * @return bool
 */
function wc_has_custom_field($product, $field_id) {
    $value = wc_get_custom_field($product, $field_id);
    return $value !== '';
}

/**
 * Get formatted custom field value
 * 
 * @param int|WC_Product $product Product ID or object
 * @param string $field_id Custom field ID
 * @param array $args Format arguments
 * @return string Formatted value
 */
function wc_get_custom_field_formatted($product, $field_id, $args = []) {
    $value = wc_get_custom_field($product, $field_id);
    
    if ($value === '') {
        return '';
    }
    
    $field_config = wc_get_custom_field_config($field_id);
    
    if (!$field_config) {
        return esc_html($value);
    }
    
    $type = $field_config['type'] ?? 'text';
    
    switch ($type) {
        case 'checkbox':
            return $value ? __('Yes', 'woocommerce') : __('No', 'woocommerce');
            
        case 'url':
            return '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
            
        case 'email':
            return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            
        case 'tel':
            return '<a href="tel:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            
        case 'color':
            return '<span class="color-preview" style="display:inline-block;width:20px;height:20px;background:' . esc_attr($value) . ';border:1px solid #ddd;border-radius:3px;vertical-align:middle;margin-right:5px;"></span>' . esc_html($value);
            
        case 'textarea':
            return nl2br(esc_html($value));
            
        case 'select':
            $options = [];
            if (!empty($field_config['options'])) {
                $lines = explode("\n", $field_config['options']);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, ':') !== false) {
                        list($key, $label) = explode(':', $line, 2);
                        $options[trim($key)] = trim($label);
                    } else {
                        $options[$line] = $line;
                    }
                }
            }
            return esc_html($options[$value] ?? $value);
            
        default:
            return esc_html($value);
    }
}


/**
 * Add custom fields to Timber context
 * Priority 5 to load early
 */
add_filter('timber/context', function($context) {
    // Add helper functions to context
    $context['wc_custom_field'] = function($product_id, $field_id, $default = '') {
        return wc_get_custom_field($product_id, $field_id, $default);
    };
    
    $context['wc_custom_fields'] = function($product_id) {
        return wc_get_all_custom_fields($product_id);
    };
    
    // Add woo_meta as Twig function
    $context['woo_meta'] = function($product_id, $field_id, $default = '') {
        return wc_get_custom_field($product_id, $field_id, $default);
    };
    
    return $context;
}, 5);

