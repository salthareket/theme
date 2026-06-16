<?php
/**
 * Colors Module Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
?>

<div class="ts-module-colors">
    
    <!-- Primary Colors -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Primary Colors', 'theme-styles-new'); ?></h3>
        <div class="ts-colors-grid">
            <?php 
            $primary_colors = [
                'primary' => __('Primary', 'theme-styles-new'),
                'secondary' => __('Secondary', 'theme-styles-new'),
                'tertiary' => __('Tertiary', 'theme-styles-new'),
                'quaternary' => __('Quaternary', 'theme-styles-new')
            ];
            foreach ($primary_colors as $key => $label): 
                $value = $data[$key] ?? '#007bff';
            ?>
            <div class="ts-color-field-pro">
                <div class="ts-color-field-header">
                    <label class="ts-field-label"><?php echo esc_html($label); ?></label>
                    <div class="ts-color-actions">
                        <button type="button" class="ts-color-action-btn ts-generate-shades" data-color-key="<?php echo $key; ?>" title="<?php esc_attr_e('Generate Shades', 'theme-styles-new'); ?>">
                            <span class="dashicons dashicons-admin-appearance"></span>
                        </button>
                        <button type="button" class="ts-color-action-btn ts-copy-var" data-var="--color-<?php echo $key; ?>" title="<?php esc_attr_e('Copy CSS Variable', 'theme-styles-new'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </div>
                </div>
                <input type="text" class="ts-field-input ts-color-input" data-field="<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" />
                <div class="ts-color-info">
                    <span class="ts-color-var">--color-<?php echo $key; ?></span>
                    <span class="ts-color-contrast" data-color="<?php echo esc_attr($value); ?>"></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Color Shades (Generated) -->
    <div class="ts-section" id="ts-color-shades-section" style="display: none;">
        <h3 class="ts-section-title"><?php _e('Color Shades', 'theme-styles-new'); ?></h3>
        <p class="ts-section-description"><?php _e('Generated shades are for reference only and not saved. Click the palette icon next to any primary color to generate shades.', 'theme-styles-new'); ?></p>
        <div id="ts-color-shades-container"></div>
    </div>
    
    <!-- Custom Colors -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Custom Colors', 'theme-styles-new'); ?></h3>
        <div class="ts-repeater-container">
            <div class="ts-repeater-empty" id="ts-custom-colors-empty">
                <div class="ts-repeater-empty-icon">
                    <span class="dashicons dashicons-art"></span>
                </div>
                <p class="ts-repeater-empty-text"><?php _e('No custom colors yet', 'theme-styles-new'); ?></p>
            </div>
            <div id="ts-custom-colors" class="ts-repeater-list">
                <?php 
                $custom_colors = $data['custom'] ?? [];
                if (!empty($custom_colors)):
                    foreach ($custom_colors as $index => $color): 
                ?>
                <div class="ts-repeater-item" data-index="<?php echo $index; ?>">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields">
                        <div class="ts-color-field">
                            <label class="ts-field-label"><?php _e('Name', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom.<?php echo $index; ?>.title" value="<?php echo esc_attr($color['title'] ?? ''); ?>" placeholder="accent" />
                        </div>
                        <div class="ts-color-field">
                            <label class="ts-field-label"><?php _e('Color', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="custom.<?php echo $index; ?>.color" value="<?php echo esc_attr($color['color'] ?? '#000000'); ?>" />
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="<?php esc_attr_e('Remove', 'theme-styles-new'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
            <div class="ts-repeater-footer">
                <button type="button" class="ts-repeater-add-btn" id="ts-add-custom-color">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Color', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Custom Gradients -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Custom Gradients', 'theme-styles-new'); ?></h3>
        <div class="ts-gradient-presets">
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)"></span>
                <span class="ts-preset-name">Purple Dream</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"></span>
                <span class="ts-preset-name">Pink Passion</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"></span>
                <span class="ts-preset-name">Ocean Blue</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)"></span>
                <span class="ts-preset-name">Fresh Mint</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #fa709a 0%, #fee140 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%)"></span>
                <span class="ts-preset-name">Sunset</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #30cfd0 0%, #330867 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%)"></span>
                <span class="ts-preset-name">Deep Ocean</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)"></span>
                <span class="ts-preset-name">Soft Pastel</span>
            </button>
            <button type="button" class="ts-preset-btn" data-gradient="linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)">
                <span class="ts-preset-preview" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)"></span>
                <span class="ts-preset-name">Cotton Candy</span>
            </button>
        </div>
        <div class="ts-repeater-container">
            <div class="ts-repeater-empty" id="ts-custom-gradients-empty">
                <div class="ts-repeater-empty-icon">
                    <span class="dashicons dashicons-admin-customizer"></span>
                </div>
                <p class="ts-repeater-empty-text"><?php _e('No custom gradients yet', 'theme-styles-new'); ?></p>
            </div>
            <div id="ts-custom-gradients" class="ts-repeater-list">
                <?php 
                $custom_gradients = $data['custom_gradients'] ?? [];
                if (!empty($custom_gradients)):
                    foreach ($custom_gradients as $index => $gradient): 
                ?>
                <div class="ts-repeater-item" data-index="<?php echo $index; ?>">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields">
                        <div class="ts-gradient-field">
                            <label class="ts-field-label"><?php _e('Name', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom_gradients.<?php echo $index; ?>.title" value="<?php echo esc_attr($gradient['title'] ?? ''); ?>" placeholder="sunset" />
                        </div>
                        <div class="ts-gradient-field ts-gradient-picker-wrapper">
                            <label class="ts-field-label"><?php _e('Gradient', 'theme-styles-new'); ?></label>
                            <input type="hidden" class="ts-gradient-input" data-field="custom_gradients.<?php echo $index; ?>.color" value="<?php echo esc_attr($gradient['color'] ?? ''); ?>" />
                            <div class="ts-gradient-picker-control" data-index="<?php echo $index; ?>"></div>
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="<?php esc_attr_e('Remove', 'theme-styles-new'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
            <div class="ts-repeater-footer">
                <button type="button" class="ts-repeater-add-btn" id="ts-add-custom-gradient">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Gradient', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>
    
</div>
