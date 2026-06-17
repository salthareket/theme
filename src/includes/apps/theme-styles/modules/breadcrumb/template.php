<?php
/**
 * Breadcrumb Module Template
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
?>

<div class="ts-module-breadcrumb">
    
    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-breadcrumb-preview">
            <nav aria-label="Breadcrumb" id="ts-breadcrumb-demo">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#" onclick="return false;">Home</a></li>
                    <li class="breadcrumb-item"><a href="#" onclick="return false;">Products & Services</a></li>
                    <li class="breadcrumb-item"><a href="#" onclick="return false;">Very Long Category Name Example</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Current Page with Long Title</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- General Settings -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('General Settings', 'theme-styles'); ?></h3>
        
        <!-- Font Settings -->
        <div class="ts-field-row ts-field-row-4" style="margin-bottom: 30px;">
            <div>
                <?php theme_styles_render_font_family_field('font_family', $data['font_family'] ?? 'inherit'); ?>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="font_size" value="<?php echo esc_attr($data['font_size'] ?? '14px'); ?>" placeholder="14px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Text Transform', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="text_transform">
                    <option value="none" <?php selected($data['text_transform'] ?? 'none', 'none'); ?>>None</option>
                    <option value="uppercase" <?php selected($data['text_transform'] ?? 'none', 'uppercase'); ?>>Uppercase</option>
                    <option value="lowercase" <?php selected($data['text_transform'] ?? 'none', 'lowercase'); ?>>Lowercase</option>
                    <option value="capitalize" <?php selected($data['text_transform'] ?? 'none', 'capitalize'); ?>>Capitalize</option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Letter Spacing', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="letter_spacing" value="<?php echo esc_attr($data['letter_spacing'] ?? '0'); ?>" placeholder="0" />
            </div>
        </div>
        
        <!-- States (Default, Hover, Active) -->
        <div class="ts-states-grid">
            
            <!-- Default State -->
            <div class="ts-state-box">
                <div class="ts-state-box-header">
                    <h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles'); ?></h5>
                </div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="font_weight">
                            <?php for ($i = 100; $i <= 900; $i += 100): ?>
                            <option value="<?php echo $i; ?>" <?php selected($data['font_weight'] ?? '400', $i); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="color" value="<?php echo esc_attr($data['color'] ?? '#007bff'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Decoration', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="text_decoration">
                            <option value="none" <?php selected($data['text_decoration'] ?? 'none', 'none'); ?>>None</option>
                            <option value="underline" <?php selected($data['text_decoration'] ?? 'none', 'underline'); ?>>Underline</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Hover State -->
            <div class="ts-state-box">
                <div class="ts-state-box-header">
                    <h5 class="ts-state-box-title"><?php _e('Hover', 'theme-styles'); ?></h5>
                </div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="font_weight_hover">
                            <?php for ($i = 100; $i <= 900; $i += 100): ?>
                            <option value="<?php echo $i; ?>" <?php selected($data['font_weight_hover'] ?? '400', $i); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="color_hover" value="<?php echo esc_attr($data['color_hover'] ?? '#2271b1'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Decoration', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="text_decoration_hover">
                            <option value="none" <?php selected($data['text_decoration_hover'] ?? 'underline', 'none'); ?>>None</option>
                            <option value="underline" <?php selected($data['text_decoration_hover'] ?? 'underline', 'underline'); ?>>Underline</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Active State (Current Page) -->
            <div class="ts-state-box">
                <div class="ts-state-box-header">
                    <h5 class="ts-state-box-title"><?php _e('Active', 'theme-styles'); ?></h5>
                </div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="font_weight_active">
                            <?php for ($i = 100; $i <= 900; $i += 100): ?>
                            <option value="<?php echo $i; ?>" <?php selected($data['font_weight_active'] ?? '600', $i); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="color_active" value="<?php echo esc_attr($data['color_active'] ?? '#6c757d'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Decoration', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="text_decoration_active">
                            <option value="none" <?php selected($data['text_decoration_active'] ?? 'none', 'none'); ?>>None</option>
                            <option value="underline" <?php selected($data['text_decoration_active'] ?? 'none', 'underline'); ?>>Underline</option>
                        </select>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Separator & Spacing -->
        <div style="margin-top: 30px;">
            <div class="ts-field-row ts-field-row-4">
                <div>
                    <label class="ts-field-label"><?php _e('Separator Icon', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="separator_icon" value="<?php echo esc_attr($data['separator_icon'] ?? '\f054'); ?>" placeholder="\f054" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Separator Color', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input ts-color-input" data-field="separator_color" value="<?php echo esc_attr($data['separator_color'] ?? '#6c757d'); ?>" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Separator Size', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="separator_size" value="<?php echo esc_attr($data['separator_size'] ?? '12px'); ?>" placeholder="12px" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Gap Between Items', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="gap" value="<?php echo esc_attr($data['gap'] ?? '8px'); ?>" placeholder="8px" />
                </div>
            </div>
        </div>
        
        <!-- Truncation -->
        <div style="margin-top: 20px;">
            <div class="ts-field-row">
                <div>
                    <label class="ts-field-label"><?php _e('Max Width (Truncation)', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="max_width" value="<?php echo esc_attr($data['max_width'] ?? '200px'); ?>" placeholder="200px" />
                    <small class="ts-field-description"><?php _e('Long items will be truncated with ellipsis (...)', 'theme-styles'); ?></small>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Enable Truncation', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="enable_truncation">
                        <option value="yes" <?php selected($data['enable_truncation'] ?? 'yes', 'yes'); ?>>Yes</option>
                        <option value="no" <?php selected($data['enable_truncation'] ?? 'yes', 'no'); ?>>No</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
</div>
