<?php
/**
 * WooCommerce Custom Product Fields - MUHTEŞEM Admin Panel
 * 
 * Modern, professional UI/UX with drag-drop, conditional fields, and all field types
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

class CustomProductFields {
    
    private $field_types = [];
    private $product_types = [];
    
    public function __construct() {
        $this->init_field_types();
        $this->init_product_types();
        $this->add_hooks();
    }
    
    private function init_field_types() {
        $this->field_types = [
            'text' => [
                'label' => __('Text', 'woocommerce'),
                'icon' => 'dashicons-editor-textcolor',
                'supports' => ['placeholder', 'description', 'desc_tip', 'value', 'custom_attributes']
            ],
            'textarea' => [
                'label' => __('Textarea', 'woocommerce'),
                'icon' => 'dashicons-editor-alignleft',
                'supports' => ['placeholder', 'description', 'desc_tip', 'rows', 'cols', 'value']
            ],
            'number' => [
                'label' => __('Number', 'woocommerce'),
                'icon' => 'dashicons-calculator',
                'supports' => ['placeholder', 'description', 'custom_attributes', 'value']
            ],
            'email' => [
                'label' => __('Email', 'woocommerce'),
                'icon' => 'dashicons-email',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'url' => [
                'label' => __('URL', 'woocommerce'),
                'icon' => 'dashicons-admin-links',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'tel' => [
                'label' => __('Telephone', 'woocommerce'),
                'icon' => 'dashicons-phone',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'password' => [
                'label' => __('Password', 'woocommerce'),
                'icon' => 'dashicons-lock',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'checkbox' => [
                'label' => __('Checkbox', 'woocommerce'),
                'icon' => 'dashicons-yes-alt',
                'supports' => ['description', 'value']
            ],
            'select' => [
                'label' => __('Select', 'woocommerce'),
                'icon' => 'dashicons-menu-alt',
                'supports' => ['description', 'options', 'value']
            ],
            'date' => [
                'label' => __('Date Picker', 'woocommerce'),
                'icon' => 'dashicons-calendar-alt',
                'supports' => ['description', 'value', 'custom_attributes']
            ],
            'color' => [
                'label' => __('Color Picker', 'woocommerce'),
                'icon' => 'dashicons-art',
                'supports' => ['description', 'value']
            ],
        ];
    }
    
    private function init_product_types() {
        if (function_exists('wc_get_product_types')) {
            $this->product_types = wc_get_product_types();
        } else {
            $this->product_types = [
                'simple' => __('Simple product', 'woocommerce'),
                'variable' => __('Variable product', 'woocommerce'),
            ];
        }
    }
    
    private function add_hooks() {
        // Add section to WooCommerce Products settings
        add_filter('woocommerce_get_sections_products', [$this, 'add_settings_section']);
        
        // Render custom fields admin page
        add_action('woocommerce_settings_products', [$this, 'render_admin_page']);
        
        // Save custom fields
        add_action('woocommerce_update_options_products', [$this, 'save_fields']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function add_settings_section($sections) {
        $sections['custom_fields'] = __('Custom Product Fields', 'woocommerce');
        return $sections;
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on WooCommerce settings page
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        // jQuery UI for sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Select2 for multi-select
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    }
    
    public function render_admin_page() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        $custom_fields = get_option('wc_custom_product_fields', []);
        
        ?>
        <div class="wrap wc-custom-fields-admin">
            <h1><?php _e('Custom Product Fields', 'woocommerce'); ?></h1>
            <p class="description">
                <?php _e('Create custom fields that will appear on product pages. Drag and drop to reorder fields.', 'woocommerce'); ?>
            </p>
            
            <?php $this->render_styles(); ?>
            
            <div class="wc-custom-fields-container">
                <div class="wc-custom-fields-sidebar">
                    <h3><?php _e('Add New Field', 'woocommerce'); ?></h3>
                    <div class="field-type-buttons">
                        <?php foreach ($this->field_types as $type => $config): ?>
                            <button type="button" class="field-type-btn" data-type="<?php echo esc_attr($type); ?>">
                                <span class="dashicons <?php echo esc_attr($config['icon']); ?>"></span>
                                <span class="label"><?php echo esc_html($config['label']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="wc-custom-fields-main">
                    <div id="custom-fields-list" class="custom-fields-sortable">
                        <?php if (empty($custom_fields)): ?>
                            <div class="no-fields-message">
                                <span class="dashicons dashicons-info"></span>
                                <p><?php _e('No custom fields yet. Click a field type on the left to add your first field.', 'woocommerce'); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($custom_fields as $index => $field): ?>
                                <?php $this->render_field_card($index, $field); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="button button-primary button-large save-fields-btn">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save All Fields', 'woocommerce'); ?>
                    </button>
                </div>
            </div>
            
            <?php $this->render_scripts(); ?>
        </div>
        <?php
    }

    
    private function render_field_card($index, $field) {
        $type = $field['type'] ?? 'text';
        $type_config = $this->field_types[$type] ?? $this->field_types['text'];
        ?>
        <div class="field-card" data-index="<?php echo esc_attr($index); ?>">
            <div class="field-card-header">
                <span class="drag-handle dashicons dashicons-menu"></span>
                <span class="field-icon dashicons <?php echo esc_attr($type_config['icon']); ?>"></span>
                <span class="field-title"><?php echo esc_html($field['label'] ?? __('Untitled Field', 'woocommerce')); ?></span>
                <span class="field-type-badge"><?php echo esc_html($type_config['label']); ?></span>
                <div class="field-actions">
                    <button type="button" class="toggle-field" title="<?php _e('Expand/Collapse', 'woocommerce'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <button type="button" class="duplicate-field" title="<?php _e('Duplicate', 'woocommerce'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button type="button" class="delete-field" title="<?php _e('Delete', 'woocommerce'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            
            <div class="field-card-body">
                <?php $this->render_field_inputs($index, $field, $type_config); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_field_inputs($index, $field, $type_config) {
        ?>
        <div class="field-row">
            <div class="field-col field-col-4">
                <label class="field-label">
                    <?php _e('Field ID', 'woocommerce'); ?> <span class="required">*</span>
                </label>
                <input type="text" 
                       name="wc_custom_fields[<?php echo $index; ?>][id]" 
                       value="<?php echo esc_attr($field['id'] ?? ''); ?>" 
                       placeholder="unique_field_id" 
                       class="field-input field-id" 
                       required>
                <p class="field-description"><?php _e('Unique identifier (lowercase, no spaces)', 'woocommerce'); ?></p>
            </div>
            
            <div class="field-col field-col-4">
                <label class="field-label">
                    <?php _e('Label', 'woocommerce'); ?> <span class="required">*</span>
                </label>
                <input type="text" 
                       name="wc_custom_fields[<?php echo $index; ?>][label]" 
                       value="<?php echo esc_attr($field['label'] ?? ''); ?>" 
                       placeholder="<?php _e('Field Label', 'woocommerce'); ?>" 
                       class="field-input field-label-input" 
                       required>
            </div>
            
            <div class="field-col field-col-4">
                <label class="field-label"><?php _e('Field Type', 'woocommerce'); ?></label>
                <select name="wc_custom_fields[<?php echo $index; ?>][type]" class="field-input field-type-select">
                    <?php foreach ($this->field_types as $type => $config): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($field['type'] ?? '', $type); ?>>
                            <?php echo esc_html($config['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if (in_array('description', $type_config['supports'])): ?>
        <div class="field-row field-supports-description">
            <div class="field-col field-col-12">
                <label class="field-label"><?php _e('Description', 'woocommerce'); ?></label>
                <textarea name="wc_custom_fields[<?php echo $index; ?>][description]" 
                          class="field-input" 
                          rows="2" 
                          placeholder="<?php _e('Help text for this field', 'woocommerce'); ?>"><?php echo esc_textarea($field['description'] ?? ''); ?></textarea>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (in_array('placeholder', $type_config['supports'])): ?>
        <div class="field-row field-supports-placeholder">
            <div class="field-col field-col-6">
                <label class="field-label"><?php _e('Placeholder', 'woocommerce'); ?></label>
                <input type="text" 
                       name="wc_custom_fields[<?php echo $index; ?>][placeholder]" 
                       value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" 
                       class="field-input" 
                       placeholder="<?php _e('Placeholder text', 'woocommerce'); ?>">
            </div>
            
            <div class="field-col field-col-6">
                <label class="field-label"><?php _e('Default Value', 'woocommerce'); ?></label>
                <input type="text" 
                       name="wc_custom_fields[<?php echo $index; ?>][value]" 
                       value="<?php echo esc_attr($field['value'] ?? ''); ?>" 
                       class="field-input">
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (in_array('rows', $type_config['supports']) || in_array('cols', $type_config['supports'])): ?>
        <div class="field-row field-supports-rows-cols">
            <div class="field-col field-col-6">
                <label class="field-label"><?php _e('Rows', 'woocommerce'); ?></label>
                <input type="number" 
                       name="wc_custom_fields[<?php echo $index; ?>][rows]" 
                       value="<?php echo esc_attr($field['rows'] ?? '3'); ?>" 
                       class="field-input" 
                       min="1">
            </div>
            
            <div class="field-col field-col-6">
                <label class="field-label"><?php _e('Columns', 'woocommerce'); ?></label>
                <input type="number" 
                       name="wc_custom_fields[<?php echo $index; ?>][cols]" 
                       value="<?php echo esc_attr($field['cols'] ?? '50'); ?>" 
                       class="field-input" 
                       min="1">
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (in_array('options', $type_config['supports'])): ?>
        <div class="field-row field-supports-options">
            <div class="field-col field-col-12">
                <label class="field-label"><?php _e('Options', 'woocommerce'); ?></label>
                <textarea name="wc_custom_fields[<?php echo $index; ?>][options]" 
                          class="field-input" 
                          rows="3" 
                          placeholder="<?php _e('key1:Label 1\nkey2:Label 2\nkey3:Label 3', 'woocommerce'); ?>"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea>
                <p class="field-description"><?php _e('One option per line. Format: key:Label', 'woocommerce'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (in_array('custom_attributes', $type_config['supports'])): ?>
        <div class="field-row field-supports-custom-attributes">
            <div class="field-col field-col-12">
                <label class="field-label"><?php _e('Custom Attributes', 'woocommerce'); ?></label>
                <input type="text" 
                       name="wc_custom_fields[<?php echo $index; ?>][custom_attributes]" 
                       value="<?php echo esc_attr($field['custom_attributes'] ?? ''); ?>" 
                       class="field-input" 
                       placeholder='min="0" max="100" step="1"'>
                <p class="field-description"><?php _e('HTML attributes for the input field', 'woocommerce'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="field-row">
            <div class="field-col field-col-12">
                <label class="field-label"><?php _e('Show on Product Types', 'woocommerce'); ?></label>
                <select name="wc_custom_fields[<?php echo $index; ?>][product_types][]" 
                        class="field-input field-product-types" 
                        multiple>
                    <?php foreach ($this->product_types as $type => $label): ?>
                        <option value="<?php echo esc_attr($type); ?>" 
                                <?php echo in_array($type, $field['product_types'] ?? []) ? 'selected' : ''; ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-description"><?php _e('Leave empty to show on all product types', 'woocommerce'); ?></p>
            </div>
        </div>
        
        <div class="field-row field-variation-only" style="<?php echo in_array('variable', $field['product_types'] ?? []) ? '' : 'display:none;'; ?>">
            <div class="field-col field-col-12">
                <label class="field-checkbox">
                    <input type="checkbox" 
                           name="wc_custom_fields[<?php echo $index; ?>][variation_only]" 
                           value="1" 
                           <?php checked($field['variation_only'] ?? '', '1'); ?>>
                    <?php _e('Show only on variations (not on parent variable product)', 'woocommerce'); ?>
                </label>
            </div>
        </div>
        
        <div class="field-row field-advanced-toggle">
            <button type="button" class="toggle-advanced-btn">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Advanced Options', 'woocommerce'); ?>
            </button>
        </div>
        
        <div class="field-advanced-options" style="display:none;">
            <div class="field-row">
                <div class="field-col field-col-4">
                    <label class="field-label"><?php _e('Wrapper Class', 'woocommerce'); ?></label>
                    <input type="text" 
                           name="wc_custom_fields[<?php echo $index; ?>][wrapper_class]" 
                           value="<?php echo esc_attr($field['wrapper_class'] ?? ''); ?>" 
                           class="field-input">
                </div>
                
                <div class="field-col field-col-4">
                    <label class="field-label"><?php _e('Input Class', 'woocommerce'); ?></label>
                    <input type="text" 
                           name="wc_custom_fields[<?php echo $index; ?>][class]" 
                           value="<?php echo esc_attr($field['class'] ?? ''); ?>" 
                           class="field-input">
                </div>
                
                <div class="field-col field-col-4">
                    <label class="field-label"><?php _e('Label Class', 'woocommerce'); ?></label>
                    <input type="text" 
                           name="wc_custom_fields[<?php echo $index; ?>][label_class]" 
                           value="<?php echo esc_attr($field['label_class'] ?? ''); ?>" 
                           class="field-input">
                </div>
            </div>
            
            <div class="field-row">
                <div class="field-col field-col-12">
                    <label class="field-checkbox">
                        <input type="checkbox" 
                               name="wc_custom_fields[<?php echo $index; ?>][required]" 
                               value="1" 
                               <?php checked($field['required'] ?? '', '1'); ?>>
                        <?php _e('Required field', 'woocommerce'); ?>
                    </label>
                </div>
            </div>
        </div>
        
        <input type="hidden" name="wc_custom_fields[<?php echo $index; ?>][order]" value="<?php echo esc_attr($index); ?>" class="field-order">
        <?php
    }

    
    private function render_styles() {
        ?>
        <style>
            .wc-custom-fields-admin {
                background: #f0f0f1;
                margin: -10px -20px 0;
                padding: 20px;
            }
            
            .wc-custom-fields-container {
                display: flex;
                gap: 20px;
                margin-top: 20px;
            }
            
            .wc-custom-fields-sidebar {
                flex: 0 0 250px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                height: fit-content;
                position: sticky;
                top: 32px;
            }
            
            .wc-custom-fields-sidebar h3 {
                margin: 0 0 15px;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .field-type-buttons {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .field-type-btn {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 13px;
                color: #2c3338;
            }
            
            .field-type-btn:hover {
                background: #fff;
                border-color: #2271b1;
                color: #2271b1;
                transform: translateX(4px);
            }
            
            .field-type-btn .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            
            .wc-custom-fields-main {
                flex: 1;
            }
            
            .custom-fields-sortable {
                display: flex;
                flex-direction: column;
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .no-fields-message {
                background: #fff;
                border: 2px dashed #c3c4c7;
                border-radius: 4px;
                padding: 40px;
                text-align: center;
                color: #646970;
            }
            
            .no-fields-message .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                opacity: 0.5;
            }
            
            .no-fields-message p {
                margin: 10px 0 0;
                font-size: 14px;
            }
            
            .field-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                transition: all 0.2s;
            }
            
            .field-card:hover {
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .field-card.ui-sortable-helper {
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: rotate(2deg);
            }
            
            .field-card-header {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 15px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #c3c4c7;
                cursor: move;
            }
            
            .drag-handle {
                color: #8c8f94;
                cursor: grab;
            }
            
            .drag-handle:active {
                cursor: grabbing;
            }
            
            .field-icon {
                color: #2271b1;
                font-size: 18px;
            }
            
            .field-title {
                flex: 1;
                font-weight: 600;
                font-size: 14px;
                color: #1d2327;
            }
            
            .field-type-badge {
                padding: 4px 10px;
                background: #2271b1;
                color: #fff;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            
            .field-actions {
                display: flex;
                gap: 5px;
            }
            
            .field-actions button {
                padding: 4px;
                background: transparent;
                border: none;
                cursor: pointer;
                color: #646970;
                transition: color 0.2s;
            }
            
            .field-actions button:hover {
                color: #2271b1;
            }
            
            .field-actions .delete-field:hover {
                color: #d63638;
            }
            
            .field-card-body {
                padding: 20px;
            }
            
            .field-card-body.collapsed {
                display: none;
            }
            
            .field-row {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .field-row:last-child {
                margin-bottom: 0;
            }
            
            .field-col {
                flex: 1;
            }
            
            .field-col-4 {
                flex: 0 0 calc(33.333% - 10px);
            }
            
            .field-col-6 {
                flex: 0 0 calc(50% - 7.5px);
            }
            
            .field-col-12 {
                flex: 0 0 100%;
            }
            
            .field-label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                font-size: 13px;
                color: #1d2327;
            }
            
            .field-label .required {
                color: #d63638;
            }
            
            .field-input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 13px;
            }
            
            .field-input:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }
            
            .field-description {
                margin: 5px 0 0;
                font-size: 12px;
                color: #646970;
                font-style: italic;
            }
            
            .field-checkbox {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                cursor: pointer;
            }
            
            .field-checkbox input[type="checkbox"] {
                margin: 0;
            }
            
            .toggle-advanced-btn {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 8px 12px;
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                color: #2c3338;
                transition: all 0.2s;
            }
            
            .toggle-advanced-btn:hover {
                background: #fff;
                border-color: #2271b1;
                color: #2271b1;
            }
            
            .field-advanced-options {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #c3c4c7;
            }
            
            .save-fields-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px !important;
                font-size: 14px !important;
                height: auto !important;
            }
            
            .save-fields-btn .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            
            /* Select2 customization */
            .select2-container--default .select2-selection--multiple {
                border-color: #8c8f94;
                border-radius: 4px;
            }
            
            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }
        </style>
        <?php
    }

    
    private function render_scripts() {
        $field_types_json = json_encode($this->field_types);
        $product_types_json = json_encode($this->product_types);
        ?>
        <script>
        jQuery(document).ready(function($) {
            const fieldTypes = <?php echo $field_types_json; ?>;
            const productTypes = <?php echo $product_types_json; ?>;
            let fieldIndex = $('.field-card').length;
            
            // Initialize Select2
            $('.field-product-types').select2({
                placeholder: '<?php _e('All product types', 'woocommerce'); ?>',
                allowClear: true
            });
            
            // Initialize Sortable
            $('#custom-fields-list').sortable({
                handle: '.drag-handle',
                placeholder: 'field-card-placeholder',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                update: function(e, ui) {
                    updateFieldOrder();
                }
            });
            
            // Add new field
            $('.field-type-btn').on('click', function() {
                const type = $(this).data('type');
                addNewField(type);
            });
            
            // Toggle field collapse
            $(document).on('click', '.toggle-field', function() {
                const $card = $(this).closest('.field-card');
                const $body = $card.find('.field-card-body');
                const $icon = $(this).find('.dashicons');
                
                $body.slideToggle(200);
                $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            });
            
            // Delete field
            $(document).on('click', '.delete-field', function() {
                if (confirm('<?php _e('Are you sure you want to delete this field?', 'woocommerce'); ?>')) {
                    $(this).closest('.field-card').fadeOut(300, function() {
                        $(this).remove();
                        updateFieldOrder();
                        checkEmptyState();
                    });
                }
            });
            
            // Duplicate field
            $(document).on('click', '.duplicate-field', function() {
                const $card = $(this).closest('.field-card');
                const $clone = $card.clone();
                
                // Update index
                const newIndex = fieldIndex++;
                $clone.attr('data-index', newIndex);
                
                // Update all name attributes
                $clone.find('[name]').each(function() {
                    const name = $(this).attr('name');
                    const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                    $(this).attr('name', newName);
                });
                
                // Update field ID to make it unique
                const $idInput = $clone.find('.field-id');
                const currentId = $idInput.val();
                $idInput.val(currentId + '_copy');
                
                // Update label
                const $labelInput = $clone.find('.field-label-input');
                $labelInput.val($labelInput.val() + ' (Copy)');
                
                // Insert after current card
                $card.after($clone);
                
                // Reinitialize Select2 for the cloned field
                $clone.find('.field-product-types').select2({
                    placeholder: '<?php _e('All product types', 'woocommerce'); ?>',
                    allowClear: true
                });
                
                updateFieldOrder();
            });
            
            // Toggle advanced options
            $(document).on('click', '.toggle-advanced-btn', function() {
                const $advanced = $(this).closest('.field-card-body').find('.field-advanced-options');
                $advanced.slideToggle(200);
            });
            
            // Update field title on label change
            $(document).on('input', '.field-label-input', function() {
                const $card = $(this).closest('.field-card');
                const label = $(this).val() || '<?php _e('Untitled Field', 'woocommerce'); ?>';
                $card.find('.field-title').text(label);
            });
            
            // Handle field type change
            $(document).on('change', '.field-type-select', function() {
                const $card = $(this).closest('.field-card');
                const type = $(this).val();
                const typeConfig = fieldTypes[type];
                
                // Update type badge
                $card.find('.field-type-badge').text(typeConfig.label);
                $card.find('.field-icon').attr('class', 'field-icon dashicons ' + typeConfig.icon);
                
                // Show/hide fields based on supports
                toggleFieldSupports($card, typeConfig.supports);
            });
            
            // Handle product types change
            $(document).on('change', '.field-product-types', function() {
                const $card = $(this).closest('.field-card');
                const selectedTypes = $(this).val() || [];
                const $variationOnly = $card.find('.field-variation-only');
                
                if (selectedTypes.includes('variable')) {
                    $variationOnly.slideDown(200);
                } else {
                    $variationOnly.slideUp(200);
                    $variationOnly.find('input[type="checkbox"]').prop('checked', false);
                }
            });
            
            function addNewField(type) {
                const typeConfig = fieldTypes[type];
                const index = fieldIndex++;
                
                // Remove no fields message
                $('.no-fields-message').remove();
                
                const fieldHTML = createFieldHTML(index, type, typeConfig);
                $('#custom-fields-list').append(fieldHTML);
                
                // Initialize Select2 for new field
                $(`[data-index="${index}"] .field-product-types`).select2({
                    placeholder: '<?php _e('All product types', 'woocommerce'); ?>',
                    allowClear: true
                });
                
                // Scroll to new field
                $('html, body').animate({
                    scrollTop: $(`[data-index="${index}"]`).offset().top - 100
                }, 500);
            }
            
            function createFieldHTML(index, type, typeConfig) {
                const supports = typeConfig.supports || [];
                
                let html = `
                <div class="field-card" data-index="${index}">
                    <div class="field-card-header">
                        <span class="drag-handle dashicons dashicons-menu"></span>
                        <span class="field-icon dashicons ${typeConfig.icon}"></span>
                        <span class="field-title"><?php _e('New Field', 'woocommerce'); ?></span>
                        <span class="field-type-badge">${typeConfig.label}</span>
                        <div class="field-actions">
                            <button type="button" class="toggle-field" title="<?php _e('Expand/Collapse', 'woocommerce'); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <button type="button" class="duplicate-field" title="<?php _e('Duplicate', 'woocommerce'); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="delete-field" title="<?php _e('Delete', 'woocommerce'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="field-card-body">
                        <div class="field-row">
                            <div class="field-col field-col-4">
                                <label class="field-label"><?php _e('Field ID', 'woocommerce'); ?> <span class="required">*</span></label>
                                <input type="text" name="wc_custom_fields[${index}][id]" placeholder="unique_field_id" class="field-input field-id" required>
                                <p class="field-description"><?php _e('Unique identifier (lowercase, no spaces)', 'woocommerce'); ?></p>
                            </div>
                            <div class="field-col field-col-4">
                                <label class="field-label"><?php _e('Label', 'woocommerce'); ?> <span class="required">*</span></label>
                                <input type="text" name="wc_custom_fields[${index}][label]" placeholder="<?php _e('Field Label', 'woocommerce'); ?>" class="field-input field-label-input" required>
                            </div>
                            <div class="field-col field-col-4">
                                <label class="field-label"><?php _e('Field Type', 'woocommerce'); ?></label>
                                <select name="wc_custom_fields[${index}][type]" class="field-input field-type-select">`;
                
                $.each(fieldTypes, function(key, config) {
                    html += `<option value="${key}" ${key === type ? 'selected' : ''}>${config.label}</option>`;
                });
                
                html += `</select>
                            </div>
                        </div>`;
                
                // Description
                if (supports.includes('description')) {
                    html += `
                        <div class="field-row field-supports-description">
                            <div class="field-col field-col-12">
                                <label class="field-label"><?php _e('Description', 'woocommerce'); ?></label>
                                <textarea name="wc_custom_fields[${index}][description]" class="field-input" rows="2" placeholder="<?php _e('Help text for this field', 'woocommerce'); ?>"></textarea>
                            </div>
                        </div>`;
                }
                
                // Placeholder & Value
                if (supports.includes('placeholder')) {
                    html += `
                        <div class="field-row field-supports-placeholder">
                            <div class="field-col field-col-6">
                                <label class="field-label"><?php _e('Placeholder', 'woocommerce'); ?></label>
                                <input type="text" name="wc_custom_fields[${index}][placeholder]" class="field-input" placeholder="<?php _e('Placeholder text', 'woocommerce'); ?>">
                            </div>
                            <div class="field-col field-col-6">
                                <label class="field-label"><?php _e('Default Value', 'woocommerce'); ?></label>
                                <input type="text" name="wc_custom_fields[${index}][value]" class="field-input">
                            </div>
                        </div>`;
                }
                
                // Rows & Cols
                if (supports.includes('rows') || supports.includes('cols')) {
                    html += `
                        <div class="field-row field-supports-rows-cols">
                            <div class="field-col field-col-6">
                                <label class="field-label"><?php _e('Rows', 'woocommerce'); ?></label>
                                <input type="number" name="wc_custom_fields[${index}][rows]" value="3" class="field-input" min="1">
                            </div>
                            <div class="field-col field-col-6">
                                <label class="field-label"><?php _e('Columns', 'woocommerce'); ?></label>
                                <input type="number" name="wc_custom_fields[${index}][cols]" value="50" class="field-input" min="1">
                            </div>
                        </div>`;
                }
                
                // Options
                if (supports.includes('options')) {
                    html += `
                        <div class="field-row field-supports-options">
                            <div class="field-col field-col-12">
                                <label class="field-label"><?php _e('Options', 'woocommerce'); ?></label>
                                <textarea name="wc_custom_fields[${index}][options]" class="field-input" rows="3" placeholder="<?php _e('key1:Label 1\nkey2:Label 2\nkey3:Label 3', 'woocommerce'); ?>"></textarea>
                                <p class="field-description"><?php _e('One option per line. Format: key:Label', 'woocommerce'); ?></p>
                            </div>
                        </div>`;
                }
                
                // Custom Attributes
                if (supports.includes('custom_attributes')) {
                    html += `
                        <div class="field-row field-supports-custom-attributes">
                            <div class="field-col field-col-12">
                                <label class="field-label"><?php _e('Custom Attributes', 'woocommerce'); ?></label>
                                <input type="text" name="wc_custom_fields[${index}][custom_attributes]" class="field-input" placeholder='min="0" max="100" step="1"'>
                                <p class="field-description"><?php _e('HTML attributes for the input field', 'woocommerce'); ?></p>
                            </div>
                        </div>`;
                }
                
                // Product Types
                html += `
                        <div class="field-row">
                            <div class="field-col field-col-12">
                                <label class="field-label"><?php _e('Show on Product Types', 'woocommerce'); ?></label>
                                <select name="wc_custom_fields[${index}][product_types][]" class="field-input field-product-types" multiple>`;
                
                $.each(productTypes, function(key, label) {
                    html += `<option value="${key}">${label}</option>`;
                });
                
                html += `</select>
                                <p class="field-description"><?php _e('Leave empty to show on all product types', 'woocommerce'); ?></p>
                            </div>
                        </div>
                        
                        <div class="field-row field-variation-only" style="display:none;">
                            <div class="field-col field-col-12">
                                <label class="field-checkbox">
                                    <input type="checkbox" name="wc_custom_fields[${index}][variation_only]" value="1">
                                    <?php _e('Show only on variations (not on parent variable product)', 'woocommerce'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="field-row field-advanced-toggle">
                            <button type="button" class="toggle-advanced-btn">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Advanced Options', 'woocommerce'); ?>
                            </button>
                        </div>
                        
                        <div class="field-advanced-options" style="display:none;">
                            <div class="field-row">
                                <div class="field-col field-col-4">
                                    <label class="field-label"><?php _e('Wrapper Class', 'woocommerce'); ?></label>
                                    <input type="text" name="wc_custom_fields[${index}][wrapper_class]" class="field-input">
                                </div>
                                <div class="field-col field-col-4">
                                    <label class="field-label"><?php _e('Input Class', 'woocommerce'); ?></label>
                                    <input type="text" name="wc_custom_fields[${index}][class]" class="field-input">
                                </div>
                                <div class="field-col field-col-4">
                                    <label class="field-label"><?php _e('Label Class', 'woocommerce'); ?></label>
                                    <input type="text" name="wc_custom_fields[${index}][label_class]" class="field-input">
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field-col field-col-12">
                                    <label class="field-checkbox">
                                        <input type="checkbox" name="wc_custom_fields[${index}][required]" value="1">
                                        <?php _e('Required field', 'woocommerce'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="wc_custom_fields[${index}][order]" value="${index}" class="field-order">
                    </div>
                </div>`;
                
                return html;
            }
            
            function toggleFieldSupports($card, supports) {
                // Hide all optional fields first
                $card.find('.field-supports-description').hide();
                $card.find('.field-supports-placeholder').hide();
                $card.find('.field-supports-rows-cols').hide();
                $card.find('.field-supports-options').hide();
                $card.find('.field-supports-custom-attributes').hide();
                
                // Show supported fields
                if (supports.includes('description')) {
                    $card.find('.field-supports-description').show();
                }
                if (supports.includes('placeholder')) {
                    $card.find('.field-supports-placeholder').show();
                }
                if (supports.includes('rows') || supports.includes('cols')) {
                    $card.find('.field-supports-rows-cols').show();
                }
                if (supports.includes('options')) {
                    $card.find('.field-supports-options').show();
                }
                if (supports.includes('custom_attributes')) {
                    $card.find('.field-supports-custom-attributes').show();
                }
            }
            
            function updateFieldOrder() {
                $('.field-card').each(function(index) {
                    $(this).find('.field-order').val(index);
                });
            }
            
            function checkEmptyState() {
                if ($('.field-card').length === 0) {
                    $('#custom-fields-list').html(`
                        <div class="no-fields-message">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('No custom fields yet. Click a field type on the left to add your first field.', 'woocommerce'); ?></p>
                        </div>
                    `);
                }
            }
        });
        </script>
        <?php
    }
    
    public function save_fields() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        if (!isset($_POST['wc_custom_fields']) || !is_array($_POST['wc_custom_fields'])) {
            update_option('wc_custom_product_fields', []);
            return;
        }
        
        $fields = $_POST['wc_custom_fields'];
        $cleaned = [];
        
        foreach ($fields as $field) {
            $cleaned[] = [
                'id' => isset($field['id']) ? sanitize_key($field['id']) : '',
                'label' => isset($field['label']) ? sanitize_text_field($field['label']) : '',
                'type' => isset($field['type']) ? sanitize_text_field($field['type']) : 'text',
                'description' => isset($field['description']) ? sanitize_textarea_field($field['description']) : '',
                'placeholder' => isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '',
                'value' => isset($field['value']) ? sanitize_text_field($field['value']) : '',
                'rows' => isset($field['rows']) ? absint($field['rows']) : 3,
                'cols' => isset($field['cols']) ? absint($field['cols']) : 50,
                'options' => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                'custom_attributes' => isset($field['custom_attributes']) ? sanitize_text_field($field['custom_attributes']) : '',
                'product_types' => isset($field['product_types']) && is_array($field['product_types']) ? array_map('sanitize_text_field', $field['product_types']) : [],
                'variation_only' => isset($field['variation_only']) ? '1' : '',
                'wrapper_class' => isset($field['wrapper_class']) ? sanitize_text_field($field['wrapper_class']) : '',
                'class' => isset($field['class']) ? sanitize_text_field($field['class']) : '',
                'label_class' => isset($field['label_class']) ? sanitize_text_field($field['label_class']) : '',
                'required' => isset($field['required']) ? '1' : '',
                'order' => isset($field['order']) ? absint($field['order']) : 0,
            ];
        }
        
        // Sort by order
        usort($cleaned, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        update_option('wc_custom_product_fields', $cleaned);
        
        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Custom product fields saved successfully!', 'woocommerce') . '</p></div>';
        });
    }
}

// Initialize - init hook'unda başlat (WooCommerce textdomain yüklendikten sonra)
add_action('init', function() {
    new CustomProductFields();
}, 5);
