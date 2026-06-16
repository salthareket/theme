<?php
/**
 * Pagination Module Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
?>

<div class="ts-module-pagination">
    
    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles-new'); ?></h3>
        <div class="ts-pagination-preview">
            <nav aria-label="Page navigation" id="ts-pagination-demo">
                <ul class="pagination">
                    <li class="page-item page-item-prev">
                        <a class="page-link" href="#" onclick="return false;">
                            <i class="pagination-icon-prev"></i>
                            <span class="pagination-text-prev">Previous</span>
                        </a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#" onclick="return false;">1</a></li>
                    <li class="page-item"><a class="page-link" href="#" onclick="return false;">2</a></li>
                    <li class="page-item"><a class="page-link" href="#" onclick="return false;">3</a></li>
                    <li class="page-item"><a class="page-link" href="#" onclick="return false;">4</a></li>
                    <li class="page-item"><a class="page-link" href="#" onclick="return false;">5</a></li>
                    <li class="page-item page-item-next">
                        <a class="page-link" href="#" onclick="return false;">
                            <span class="pagination-text-next">Next</span>
                            <i class="pagination-icon-next"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    
    <!-- General Settings -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('General Settings', 'theme-styles-new'); ?></h3>
        <div class="ts-field-row-compact">
            <div>
                <label class="ts-field-label"><?php _e('Alignment', 'theme-styles-new'); ?></label>
                <select class="ts-field-select ts-field-narrow" data-field="align">
                    <option value="left" <?php selected($data['align'] ?? 'center', 'left'); ?>><?php _e('Left', 'theme-styles-new'); ?></option>
                    <option value="center" <?php selected($data['align'] ?? 'center', 'center'); ?>><?php _e('Center', 'theme-styles-new'); ?></option>
                    <option value="right" <?php selected($data['align'] ?? 'center', 'right'); ?>><?php _e('Right', 'theme-styles-new'); ?></option>
                    <option value="justify" <?php selected($data['align'] ?? 'center', 'justify'); ?>><?php _e('Justify', 'theme-styles-new'); ?></option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Gap Between Items', 'theme-styles-new'); ?></label>
                <input type="text" class="ts-field-input ts-field-narrow" data-field="gap" value="<?php echo esc_attr($data['gap'] ?? '4px'); ?>" placeholder="4px" />
            </div>
        </div>
    </div>
    
    <!-- Pagination Item (Page Numbers) -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Page Numbers', 'theme-styles-new'); ?></h3>
        
        <!-- Font Settings -->
        <div class="ts-field-row" style="margin-bottom: 30px;">
            <div>
                <?php theme_styles_render_font_family_field('item_font_family', $data['item_font_family'] ?? 'inherit'); ?>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles-new'); ?></label>
                <input type="text" class="ts-field-input" data-field="item_font_size" value="<?php echo esc_attr($data['item_font_size'] ?? '14px'); ?>" placeholder="14px" />
            </div>
        </div>
        
        <!-- States (Default, Hover, Active) -->
        <div class="ts-states-grid">
                
                <!-- Default State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="item_font_weight">
                                <?php for ($i = 100; $i <= 900; $i += 100): ?>
                                <option value="<?php echo $i; ?>" <?php selected($data['item_font_weight'] ?? '400', $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_color" value="<?php echo esc_attr($data['item_color'] ?? '#007bff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_bg" value="<?php echo esc_attr($data['item_bg'] ?? 'transparent'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input ts-field-narrow" data-field="item_border_width" value="<?php echo esc_attr($data['item_border_width'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select ts-field-narrow" data-field="item_border_style">
                                    <option value="solid" <?php selected($data['item_border_style'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['item_border_style'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['item_border_style'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['item_border_style'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="item_border_color" value="<?php echo esc_attr($data['item_border_color'] ?? '#dee2e6'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hover State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Hover', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="item_font_weight_hover">
                                <?php for ($i = 100; $i <= 900; $i += 100): ?>
                                <option value="<?php echo $i; ?>" <?php selected($data['item_font_weight_hover'] ?? '400', $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_color_hover" value="<?php echo esc_attr($data['item_color_hover'] ?? '#ffffff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_bg_hover" value="<?php echo esc_attr($data['item_bg_hover'] ?? '#2271b1'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input ts-field-narrow" data-field="item_border_width_hover" value="<?php echo esc_attr($data['item_border_width_hover'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select ts-field-narrow" data-field="item_border_style_hover">
                                    <option value="solid" <?php selected($data['item_border_style_hover'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['item_border_style_hover'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['item_border_style_hover'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['item_border_style_hover'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="item_border_color_hover" value="<?php echo esc_attr($data['item_border_color_hover'] ?? '#dee2e6'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Active', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="item_font_weight_active">
                                <?php for ($i = 100; $i <= 900; $i += 100): ?>
                                <option value="<?php echo $i; ?>" <?php selected($data['item_font_weight_active'] ?? '600', $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_color_active" value="<?php echo esc_attr($data['item_color_active'] ?? '#ffffff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="item_bg_active" value="<?php echo esc_attr($data['item_bg_active'] ?? '#007bff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input ts-field-narrow" data-field="item_border_width_active" value="<?php echo esc_attr($data['item_border_width_active'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select ts-field-narrow" data-field="item_border_style_active">
                                    <option value="solid" <?php selected($data['item_border_style_active'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['item_border_style_active'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['item_border_style_active'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['item_border_style_active'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="item_border_color_active" value="<?php echo esc_attr($data['item_border_color_active'] ?? '#007bff'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        
        <!-- Size & Spacing -->
        <div style="margin-top: 30px;">
            <div class="ts-field-row">
                <div>
                    <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="item_border_radius" value="<?php echo esc_attr($data['item_border_radius'] ?? '4px'); ?>" placeholder="4px" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Padding', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="item_padding" value="<?php echo esc_attr($data['item_padding'] ?? '8px 12px'); ?>" placeholder="8px 12px" />
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pagination Nav (Prev/Next) -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Prev/Next Buttons', 'theme-styles-new'); ?></h3>
        
        <!-- Font Settings -->
        <div class="ts-field-row ts-field-row-3" style="margin-bottom: 30px;">
            <div>
                <?php theme_styles_render_font_family_field('nav_font_family', $data['nav_font_family'] ?? 'inherit'); ?>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles-new'); ?></label>
                <input type="text" class="ts-field-input" data-field="nav_font_size" value="<?php echo esc_attr($data['nav_font_size'] ?? '14px'); ?>" placeholder="14px" />
            </div>
        </div>
        
        <!-- States (Default, Hover, Disabled) -->
        <div class="ts-states-grid">
                
                <!-- Default State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="nav_font_weight">
                                <?php for ($i = 100; $i <= 900; $i += 100): ?>
                                <option value="<?php echo $i; ?>" <?php selected($data['nav_font_weight'] ?? '400', $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_color" value="<?php echo esc_attr($data['nav_color'] ?? '#007bff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_bg" value="<?php echo esc_attr($data['nav_bg'] ?? 'transparent'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input" data-field="nav_border_width" value="<?php echo esc_attr($data['nav_border_width'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select" data-field="nav_border_style">
                                    <option value="solid" <?php selected($data['nav_border_style'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['nav_border_style'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['nav_border_style'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['nav_border_style'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="nav_border_color" value="<?php echo esc_attr($data['nav_border_color'] ?? '#dee2e6'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hover State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Hover', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_color_hover" value="<?php echo esc_attr($data['nav_color_hover'] ?? '#ffffff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_bg_hover" value="<?php echo esc_attr($data['nav_bg_hover'] ?? '#2271b1'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input" data-field="nav_border_width_hover" value="<?php echo esc_attr($data['nav_border_width_hover'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select" data-field="nav_border_style_hover">
                                    <option value="solid" <?php selected($data['nav_border_style_hover'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['nav_border_style_hover'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['nav_border_style_hover'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['nav_border_style_hover'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="nav_border_color_hover" value="<?php echo esc_attr($data['nav_border_color_hover'] ?? '#dee2e6'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Disabled State -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header">
                        <h5 class="ts-state-box-title"><?php _e('Disabled', 'theme-styles-new'); ?></h5>
                    </div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Text', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_color_disabled" value="<?php echo esc_attr($data['nav_color_disabled'] ?? '#6c757d'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Background', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="nav_bg_disabled" value="<?php echo esc_attr($data['nav_bg_disabled'] ?? 'transparent'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Border', 'theme-styles-new'); ?></label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input" data-field="nav_border_width_disabled" value="<?php echo esc_attr($data['nav_border_width_disabled'] ?? '1px'); ?>" placeholder="1px" />
                                <select class="ts-field-select" data-field="nav_border_style_disabled">
                                    <option value="solid" <?php selected($data['nav_border_style_disabled'] ?? 'solid', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($data['nav_border_style_disabled'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                                    <option value="dotted" <?php selected($data['nav_border_style_disabled'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                                    <option value="none" <?php selected($data['nav_border_style_disabled'] ?? 'solid', 'none'); ?>>None</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="nav_border_color_disabled" value="<?php echo esc_attr($data['nav_border_color_disabled'] ?? '#dee2e6'); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        
        <!-- Icons & Size -->
        <div style="margin-top: 30px;">
            <div class="ts-field-row ts-field-row-3">
                <div>
                    <label class="ts-field-label"><?php _e('Prev Icon', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav_prev_icon" value="<?php echo esc_attr($data['nav_prev_icon'] ?? '\f053'); ?>" placeholder="\f053" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Next Icon', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav_next_icon" value="<?php echo esc_attr($data['nav_next_icon'] ?? '\f054'); ?>" placeholder="\f054" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav_border_radius" value="<?php echo esc_attr($data['nav_border_radius'] ?? '4px'); ?>" placeholder="4px" />
                </div>
            </div>
        </div>
    </div>
    
</div>