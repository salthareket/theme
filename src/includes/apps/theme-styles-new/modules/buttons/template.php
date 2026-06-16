<?php
/**
 * Buttons Module Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$sizes = $data['custom'] ?? [];
?>

<div class="ts-module-buttons">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles-new'); ?></h3>
        <div class="ts-buttons-preview" id="ts-buttons-preview">
            <p class="ts-preview-empty-text"><?php _e('Add button sizes below to see preview', 'theme-styles-new'); ?></p>
        </div>
    </div>

    <!-- Button Sizes Repeater -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Button Sizes', 'theme-styles-new'); ?></h3>

        <div class="ts-repeater-container">
            <div class="ts-repeater-list" id="ts-buttons-list">
                <?php foreach ($sizes as $index => $size): ?>
                <div class="ts-repeater-item" data-index="<?php echo $index; ?>">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields ts-button-fields">
                        <div>
                            <label class="ts-field-label"><?php _e('Size', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="custom.<?php echo $index; ?>.size">
                                <option value="default" <?php selected($size['size'] ?? '', 'default'); ?>>default</option>
                                <?php foreach (array_keys(THEME_STYLES_BREAKPOINTS) as $bp): ?>
                                <option value="<?php echo $bp; ?>" <?php selected($size['size'] ?? '', $bp); ?>><?php echo $bp; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ts-field-label"><?php _e('Padding X', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom.<?php echo $index; ?>.padding_x" value="<?php echo esc_attr($size['padding_x'] ?? '16px'); ?>" placeholder="16px" />
                        </div>
                        <div>
                            <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom.<?php echo $index; ?>.padding_y" value="<?php echo esc_attr($size['padding_y'] ?? '8px'); ?>" placeholder="8px" />
                        </div>
                        <div>
                            <label class="ts-field-label"><?php _e('Font Size', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom.<?php echo $index; ?>.font_size" value="<?php echo esc_attr($size['font_size'] ?? '14px'); ?>" placeholder="14px" />
                        </div>
                        <div>
                            <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="custom.<?php echo $index; ?>.border_radius" value="<?php echo esc_attr($size['border_radius'] ?? '4px'); ?>" placeholder="4px" />
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="<?php esc_attr_e('Remove', 'theme-styles-new'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="ts-repeater-empty <?php echo !empty($sizes) ? 'hidden' : ''; ?>">
                <div class="ts-repeater-empty-icon">
                    <span class="dashicons dashicons-button"></span>
                </div>
                <p class="ts-repeater-empty-text"><?php _e('No button sizes yet. Add your first one!', 'theme-styles-new'); ?></p>
            </div>

            <div class="ts-repeater-footer">
                <button type="button" class="ts-repeater-add-btn" id="ts-add-button-size">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Button Size', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>

</div>
