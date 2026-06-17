<?php
/**
 * WooCommerce Custom Product Fields - Admin Panel
 * 
 * Ürün tiplerine göre özel alanlar ekler (Simple, Variable, Variation, vb.)
 * Modern UI/UX ile profesyonel admin paneli
 * 
 * @package SaltHareket
 * @version 2.0.0
 * @author Tolga Bey
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce_Custom_Product_Fields_Admin {
    
    private $field_types = [];
    private $product_types = [];
    
    public function __construct() {
        $this->init_field_types();
        $this->init_product_types();
        $this->init_hooks();
    }
    
    private function init_field_types() {
        $this->field_types = [
            'text' => [
                'label' => 'Text',
                'icon' => 'dashicons-editor-textcolor',
                'supports' => ['placeholder', 'description', 'desc_tip', 'value', 'custom_attributes']
            ],
            'textarea' => [
                'label' => 'Textarea',
                'icon' => 'dashicons-editor-alignleft',
                'supports' => ['placeholder', 'description', 'desc_tip', 'rows', 'cols', 'value']
            ],
            'number' => [
                'label' => 'Number',
                'icon' => 'dashicons-calculator',
                'supports' => ['placeholder', 'description', 'custom_attributes', 'value']
            ],
            'email' => [
                'label' => 'Email',
                'icon' => 'dashicons-email',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'url' => [
                'label' => 'URL',
                'icon' => 'dashicons-admin-links',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'tel' => [
                'label' => 'Telephone',
                'icon' => 'dashicons-phone',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'password' => [
                'label' => 'Password',
                'icon' => 'dashicons-lock',
                'supports' => ['placeholder', 'description', 'value']
            ],
            'select' => [
                'label' => 'Select',
                'icon' => 'dashicons-menu-alt',
                'supports' => ['description', 'options', 'value']
            ],
            'checkbox' => [
                'label' => 'Checkbox',
                'icon' => 'dashicons-yes-alt',
                'supports' => ['description', 'value']
            ],
            'date' => [
                'label' => 'Date Picker',
                'icon' => 'dashicons-calendar-alt',
                'supports' => ['description', 'value']
            ],
            'color' => [
                'label' => 'Color Picker',
                'icon' => 'dashicons-art',
                'supports' => ['description', 'value']
            ],
        ];
    }
    
    private function init_product_types() {
        $exclude = ['grouped', 'external']; // İstemediğin tipler
        $this->product_types = array_filter(
            array_keys(wc_get_product_types()),
            fn($type) => !in_array($type, $exclude)
        );
    }
    
    private function init_hooks() {
        // Admin section ekle
        add_filter('woocommerce_get_sections_products', [$this, 'add_section']);
        
        // Settings render
        add_action('woocommerce_settings_products', [$this, 'render_settings']);
        
        // Settings save
        add_action('woocommerce_update_options_products', [$this, 'save_settings']);
        
        // Admin styles & scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function add_section($sections) {
        $sections['custom_fields'] = __('Custom Product Fields', 'woocommerce');
        return $sections;
    }
    
    public function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        // Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        
        // Sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Custom styles
        wp_add_inline_style('woocommerce_admin_styles', $this->get_custom_styles());
        
        // Custom scripts
        wp_add_inline_script('jquery', $this->get_custom_scripts());
    }
    
    private function get_custom_styles() {
        return <<<CSS
        /* Custom Product Fields - Modern UI */
        .custom-fields-wrapper {
            max-width: 1200px;
            margin: 20px 0;
        }
        
        .custom-field-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .custom-field-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #2271b1;
        }
        
        .custom-field-card.collapsed .field-content {
            display: none;
        }
        
        .field-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: move;
        }
        
        .field-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .field-drag-handle {
            color: #999;
            cursor: move;
            font-size: 20px;
        }
        
        .field-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f0f6fc;
            border: 1px solid #d0e3f0;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #2271b1;
        }
        
        .field-type-badge .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        .field-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
            margin: 0;
        }
        
        .field-id {
            font-size: 12px;
            color: #757575;
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .field-header-actions {
            display: flex;
            gap: 8px;
        }
        
        .field-action-btn {
            background: none;
            border: none;
            padding: 6px;
            cursor: pointer;
            color: #757575;
            transition: color 0.2s;
            font-size: 18px;
        }
        
        .field-action-btn:hover {
            color: #2271b1;
        }
        
        .field-action-btn.delete:hover {
            color: #d63638;
        }
        
        .field-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .field-group.full-width {
            grid-column: 1 / -1;
        }
        
        .field-label {
            font-size: 13px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .field-label .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #757575;
        }
        
        .field-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
            width: 100%;
        }
        
        .field-input:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .field-input.error {
            border-color: #d63638;
        }
        
        textarea.field-input {
            resize: vertical;
            min-height: 80px;
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        .field-help-text {
            font-size: 12px;
            color: #757575;
            margin-top: 4px;
            font-style: italic;
        }
        
        .add-field-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #2271b1;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(34, 113, 177, 0.2);
        }
        
        .add-field-btn:hover {
            background: #135e96;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(34, 113, 177, 0.3);
        }
        
        .add-field-btn .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f9f9f9;
            border: 2px dashed #ddd;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .empty-state .dashicons {
            font-size: 64px;
            width: 64px;
            height: 64px;
            color: #ddd;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: #757575;
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            color: #999;
            margin: 0;
        }
        
        .variation-only-wrapper {
            grid-column: 1 / -1;
            padding: 12px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            display: none;
        }
        
        .variation-only-wrapper.show {
            display: block;
        }
        
        .variation-only-wrapper label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #856404;
            cursor: pointer;
        }
        
        .ui-sortable-helper {
            opacity: 0.8;
            transform: rotate(2deg);
        }
        
        .ui-sortable-placeholder {
            background: #f0f6fc;
            border: 2px dashed #2271b1;
            border-radius: 8px;
            visibility: visible !important;
            height: 100px !important;
        }
CSS;
    }
    
    private function get_custom_scripts() {
        return <<<JS
        jQuery(document).ready(function($) {
            // Select2 init
            $('.custom-field-select2').select2({
                placeholder: 'Select options...',
                allowClear: true
            });
            
            // Sortable
            $('.custom-fields-wrapper').sortable({
                handle: '.field-drag-handle',
                placeholder: 'ui-sortable-placeholder',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                }
            });
            
            // Toggle collapse
            $(document).on('click', '.field-toggle-btn', function() {
                $(this).closest('.custom-field-card').toggleClass('collapsed');
                const icon = $(this).find('.dashicons');
                icon.toggleClass('dashicons-arrow-down dashicons-arrow-up');
            });
            
            // Delete field
            $(document).on('click', '.field-delete-btn', function() {
                if (confirm('Are you sure you want to delete this field?')) {
                    $(this).closest('.custom-field-card').fadeOut(300, function() {
                        $(this).remove();
                        checkEmptyState();
                    });
                }
            });
            
            // Field type change - show/hide relevant fields
            $(document).on('change', '.field-type-select', function() {
                updateFieldVisibility($(this).closest('.custom-field-card'));
            });
            
            // Product type change - show/hide variation checkbox
            $(document).on('change', '.field-product-types', function() {
                const card = $(this).closest('.custom-field-card');
                const values = $(this).val() || [];
                const variationWrapper = card.find('.variation-only-wrapper');
                
                if (values.includes('variable')) {
                    variationWrapper.addClass('show');
                } else {
                    variationWrapper.removeClass('show');
                    variationWrapper.find('input[type="checkbox"]').prop('checked', false);
                }
            });
            
            // Add new field
            $('#add-custom-field-btn').on('click', function() {
                const index = $('.custom-field-card').length;
                const newField = createFieldHTML(index);
                
                if ($('.empty-state').length) {
                    $('.empty-state').remove();
                }
                
                $('.custom-fields-wrapper').append(newField);
                
                // Init Select2 for new field
                $('.custom-field-card').last().find('.custom-field-select2').select2({
                    placeholder: 'Select options...',
                    allowClear: true
                });
                
                // Scroll to new field
                $('html, body').animate({
                    scrollTop: $('.custom-field-card').last().offset().top - 100
                }, 500);
            });
            
            function updateFieldVisibility(card) {
                const type = card.find('.field-type-select').val();
                const supports = fieldTypeSupports[type] || [];
                
                // Hide all optional fields first
                card.find('[data-field-option]').closest('.field-group').hide();
                
                // Show supported fields
                supports.forEach(function(field) {
                    card.find('[data-field-option="' + field + '"]').closest('.field-group').show();
                });
            }
            
            function checkEmptyState() {
                if ($('.custom-field-card').length === 0) {
                    $('.custom-fields-wrapper').html(getEmptyStateHTML());
                }
            }
            
            function createFieldHTML(index) {
                // Will be generated via PHP
                return '';
            }
            
            function getEmptyStateHTML() {
                return `
                    <div class="empty-state">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <h3>No custom fields yet</h3>
                        <p>Click "Add New Field" to create your first custom product field</p>
                    </div>
                `;
            }
            
            // Field type supports mapping
            const fieldTypeSupports = <?php echo json_encode(array_map(fn($type) => $type['supports'], $this->field_types)); ?>;
            
            // Initial visibility update
            $('.custom-field-card').each(function() {
                updateFieldVisibility($(this));
            });
        });
JS;
    }
    
    public function render_settings() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        $fields = get_option('custom_product_fields', []);
        
        ?>
        <div class="wrap woocommerce">
            <h2><?php _e('Custom Product Fields', 'woocommerce'); ?></h2>
            <p class="description">
                <?php _e('Add custom fields to your products based on product type. These fields will appear on the product edit page.', 'woocommerce'); ?>
            </p>
            
            <div class="custom-fields-wrapper">
                <?php if (empty($fields)): ?>
                    <div class="empty-state">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <h3><?php _e('No custom fields yet', 'woocommerce'); ?></h3>
                        <p><?php _e('Click "Add New Field" to create your first custom product field', 'woocommerce'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($fields as $index => $field): ?>
                        <?php echo $this->render_field_card($index, $field); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-custom-field-btn" class="add-field-btn">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add New Field', 'woocommerce'); ?>
            </button>
        </div>
        <?php
    }
    
    private function render_field_card($index, $field) {
        $type = $field['type'] ?? 'text';
        $type_info = $this->field_types[$type] ?? $this->field_types['text'];
        
        ob_start();
        ?>
        <div class="custom-field-card" data-index="<?php echo $index; ?>">
            <div class="field-header">
                <div class="field-header-left">
                    <span class="dashicons dashicons-menu field-drag-handle"></span>
                    <span class="field-type-badge">
                        <span class="dashicons <?php echo $type_info['icon']; ?>"></span>
                        <?php echo $type_info['label']; ?>
                    </span>
                    <h4 class="field-title"><?php echo esc_html($field['label'] ?? 'Untitled Field'); ?></h4>
                    <span class="field-id"><?php echo esc_html($field['id'] ?? ''); ?></span>
                </div>
                <div class="field-header-actions">
                    <button type="button" class="field-action-btn field-toggle-btn" title="Toggle">
                        <span class="dashicons dashicons-arrow-up"></span>
                    </button>
                    <button type="button" class="field-action-btn field-delete-btn delete" title="Delete">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            
            <div class="field-content">
                <!-- Type -->
                <div class="field-group">
                    <label class="field-label">
                        <span class="dashicons dashicons-admin-generic"></span>
                        Field Type
                    </label>
                    <select name="custom_product_fields[<?php echo $index; ?>][type]" class="field-input field-type-select" required>
                        <?php foreach ($this->field_types as $key => $info): ?>
                            <option value="<?php echo $key; ?>" <?php selected($type, $key); ?>>
                                <?php echo $info['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- ID -->
                <div class="field-group">
                    <label class="field-label">
                        <span class="dashicons dashicons-admin-network"></span>
                        Field ID
                    </label>
                    <input type="text" 
                           name="custom_product_fields[<?php echo $index; ?>][id]" 
                           class="field-input" 
                           value="<?php echo esc_attr($field['id'] ?? ''); ?>" 
                           placeholder="unique_field_id" 
                           required>
                    <span class="field-help-text">Unique identifier (lowercase, underscores only)</span>
                </div>
                
                <!-- Label -->
                <div class="field-group">
                    <label class="field-label">
                        <span class="dashicons dashicons-tag"></span>
                        Field Label
                    </label>
                    <input type="text" 
                           name="custom_product_fields[<?php echo $index; ?>][label]" 
                           class="field-input" 
                           value="<?php echo esc_attr($field['label'] ?? ''); ?>" 
                           placeholder="Field Label" 
                           required>
                </div>
                
                <!-- Description -->
                <div class="field-group full-width" data-field-option="description">
                    <label class="field-label">
                        <span class="dashicons dashicons-info"></span>
                        Description
                    </label>
                    <textarea name="custom_product_fields[<?php echo $index; ?>][description]" 
                              class="field-input" 
                              rows="2" 
                              placeholder="Help text for this field"><?php echo esc_textarea($field['description'] ?? ''); ?></textarea>
                </div>
                
                <!-- Placeholder -->
                <div class="field-group" data-field-option="placeholder">
                    <label class="field-label">
                        <span class="dashicons dashicons-editor-code"></span>
                        Placeholder
                    </label>
                    <input type="text" 
                           name="custom_product_fields[<?php echo $index; ?>][placeholder]" 
                           class="field-input" 
                           value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" 
                           placeholder="Enter placeholder...">
                </div>
                
                <!-- Default Value -->
                <div class="field-group" data-field-option="value">
                    <label class="field-label">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Default Value
                    </label>
                    <input type="text" 
                           name="custom_product_fields[<?php echo $index; ?>][value]" 
                           class="field-input" 
                           value="<?php echo esc_attr($field['value'] ?? ''); ?>" 
                           placeholder="Default value">
                </div>
                
                <!-- Product Types -->
                <div class="field-group full-width">
                    <label class="field-label">
                        <span class="dashicons dashicons-products"></span>
                        Show for Product Types
                    </label>
                    <select name="custom_product_fields[<?php echo $index; ?>][view][]" 
                            class="field-input custom-field-select2 field-product-types" 
                            multiple>
                        <?php foreach ($this->product_types as $product_type): ?>
                            <option value="<?php echo $product_type; ?>" 
                                    <?php echo in_array($product_type, $field['view'] ?? []) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($product_type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-help-text">Select which product types should show this field</span>
                </div>
                
                <!-- Variation Only -->
                <div class="variation-only-wrapper <?php echo in_array('variable', $field['view'] ?? []) ? 'show' : ''; ?>">
                    <label>
                        <input type="checkbox" 
                               name="custom_product_fields[<?php echo $index; ?>][variation_field]" 
                               value="1" 
                               <?php checked($field['variation_field'] ?? '', '1'); ?>>
                        <span class="dashicons dashicons-warning"></span>
                        Only show this field inside product variations (not on main product)
                    </label>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save_settings() {
        if (!isset($_GET['section']) || $_GET['section'] !== 'custom_fields') {
            return;
        }
        
        if (!isset($_POST['custom_product_fields']) || !is_array($_POST['custom_product_fields'])) {
            update_option('custom_product_fields', []);
            return;
        }
        
        $fields = [];
        foreach ($_POST['custom_product_fields'] as $field) {
            $fields[] = [
                'id' => sanitize_key($field['id'] ?? ''),
                'label' => sanitize_text_field($field['label'] ?? ''),
                'description' => sanitize_textarea_field($field['description'] ?? ''),
                'type' => sanitize_text_field($field['type'] ?? 'text'),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'value' => sanitize_text_field($field['value'] ?? ''),
                'view' => array_map('sanitize_text_field', $field['view'] ?? []),
                'variation_field' => isset($field['variation_field']) ? '1' : '',
            ];
        }
        
        update_option('custom_product_fields', $fields);
    }
}

// Initialize
new WooCommerce_Custom_Product_Fields_Admin();
