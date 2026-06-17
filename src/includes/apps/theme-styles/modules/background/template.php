<?php
/**
 * Body Module Template
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$bg_type = $data['bg_type'] ?? 'color';
?>

<div class="ts-module-background">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-body-preview" id="ts-body-preview">
            <div class="ts-body-preview-inner" id="ts-body-preview-inner">
                <p class="ts-body-preview-text">
                    The quick brown fox jumps over the lazy dog. 
                    <a href="#" onclick="return false;" class="ts-body-preview-link">This is a link</a> and 
                    <a href="#" onclick="return false;" class="ts-body-preview-link-visited">visited link</a>.
                </p>
                <p class="ts-body-preview-text">Hover over the link above to see hover color.</p>
                <p class="ts-body-preview-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                <p class="ts-body-preview-text">Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                <p class="ts-body-preview-text">Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
                <p class="ts-body-preview-text">Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                <p class="ts-body-preview-select">← Select this text to see selection styles</p>
                <!-- Backdrop test button -->
                <div style="margin-top: 8px;">
                    <button type="button" class="ts-repeater-add-btn" id="ts-backdrop-test-btn" style="font-size:12px; padding: 6px 14px;">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Test Backdrop', 'theme-styles'); ?>
                    </button>
                </div>
            </div>
        </div>
        <!-- Backdrop overlay - preview dışında, wrapper içinde -->
        <div class="ts-body-preview-backdrop-overlay hidden" id="ts-backdrop-overlay">
            <div class="ts-body-preview-modal">
                <p><?php _e('This is a modal on backdrop', 'theme-styles'); ?></p>
                <button type="button" class="ts-backdrop-close" id="ts-backdrop-close">
                    <span class="dashicons dashicons-no-alt"></span> Close
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         BACKGROUND
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Background', 'theme-styles'); ?></h3>
        <?php theme_styles_render_background_field('bg', $data, ['show_image' => true, 'id_suffix' => 'body_bg']); ?>
    </div>

    <!-- ═══════════════════════════════════════
         COLORS
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Colors', 'theme-styles'); ?></h3>
        <div class="ts-states-grid">

            <!-- Text -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Text', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Default', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="text_color" value="<?php echo esc_attr($data['text_color'] ?? '#212529'); ?>" />
                    </div>
                </div>
            </div>

            <!-- Link -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Link', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Default', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="link_color" value="<?php echo esc_attr($data['link_color'] ?? '#007bff'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Hover', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="link_color_hover" value="<?php echo esc_attr($data['link_color_hover'] ?? '#135e96'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Visited', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="link_color_visited" value="<?php echo esc_attr($data['link_color_visited'] ?? '#6f42c1'); ?>" />
                    </div>
                </div>
            </div>

            <!-- Backdrop -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Backdrop', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="backdrop_color" value="<?php echo esc_attr($data['backdrop_color'] ?? '#000000'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Opacity', 'theme-styles'); ?></label>
                        <div class="ts-range-field">
                            <input type="range" class="ts-field-range" data-field="backdrop_opacity" min="0" max="1" step="0.05" value="<?php echo esc_attr($data['backdrop_opacity'] ?? '0.5'); ?>" />
                            <span class="ts-range-value"><?php echo esc_attr($data['backdrop_opacity'] ?? '0.5'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SELECTION STYLES
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Selection Styles', 'theme-styles'); ?></h3>
        <p class="ts-section-description"><?php _e('Styles applied when user selects text (::selection)', 'theme-styles'); ?></p>
        <div class="ts-field-row">
            <div>
                <label class="ts-field-label"><?php _e('Selection Background', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="selection_bg" value="<?php echo esc_attr($data['selection_bg'] ?? '#007bff'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Selection Text Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="selection_color" value="<?php echo esc_attr($data['selection_color'] ?? '#ffffff'); ?>" />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SCROLLBAR
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Scrollbar', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Width', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="scrollbar_width">
                    <option value="auto" <?php selected($data['scrollbar_width'] ?? 'auto', 'auto'); ?>>Auto</option>
                    <option value="thin" <?php selected($data['scrollbar_width'] ?? 'auto', 'thin'); ?>>Thin</option>
                    <option value="none" <?php selected($data['scrollbar_width'] ?? 'auto', 'none'); ?>>None</option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Track Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="scrollbar_track" value="<?php echo esc_attr($data['scrollbar_track'] ?? '#f1f1f1'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Thumb Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="scrollbar_thumb" value="<?php echo esc_attr($data['scrollbar_thumb'] ?? '#888888'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Thumb Hover', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="scrollbar_thumb_hover" value="<?php echo esc_attr($data['scrollbar_thumb_hover'] ?? '#555555'); ?>" />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         FOCUS STYLES
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Focus Styles', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Outline Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="focus_color" value="<?php echo esc_attr($data['focus_color'] ?? '#007bff'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Outline Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="focus_width" value="<?php echo esc_attr($data['focus_width'] ?? '2px'); ?>" placeholder="2px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Outline Style', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="focus_style">
                    <option value="solid" <?php selected($data['focus_style'] ?? 'solid', 'solid'); ?>>Solid</option>
                    <option value="dashed" <?php selected($data['focus_style'] ?? 'solid', 'dashed'); ?>>Dashed</option>
                    <option value="dotted" <?php selected($data['focus_style'] ?? 'solid', 'dotted'); ?>>Dotted</option>
                    <option value="none" <?php selected($data['focus_style'] ?? 'solid', 'none'); ?>>None</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         RENDERING & BEHAVIOR
    ═══════════════════════════════════════ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Rendering & Behavior', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Smooth Scroll', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="smooth_scroll">
                    <option value="smooth" <?php selected($data['smooth_scroll'] ?? 'smooth', 'smooth'); ?>>Enabled</option>
                    <option value="auto" <?php selected($data['smooth_scroll'] ?? 'smooth', 'auto'); ?>>Disabled</option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Smoothing', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="font_smoothing">
                    <option value="antialiased" <?php selected($data['font_smoothing'] ?? 'antialiased', 'antialiased'); ?>>Antialiased</option>
                    <option value="auto" <?php selected($data['font_smoothing'] ?? 'antialiased', 'auto'); ?>>Auto</option>
                    <option value="none" <?php selected($data['font_smoothing'] ?? 'antialiased', 'none'); ?>>None</option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Text Rendering', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="text_rendering">
                    <option value="optimizeLegibility" <?php selected($data['text_rendering'] ?? 'optimizeLegibility', 'optimizeLegibility'); ?>>Optimize Legibility</option>
                    <option value="optimizeSpeed" <?php selected($data['text_rendering'] ?? 'optimizeLegibility', 'optimizeSpeed'); ?>>Optimize Speed</option>
                    <option value="auto" <?php selected($data['text_rendering'] ?? 'optimizeLegibility', 'auto'); ?>>Auto</option>
                </select>
            </div>
        </div>
    </div>

</div>
