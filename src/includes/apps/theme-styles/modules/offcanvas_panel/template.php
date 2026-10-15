<?php
/**
 * Offcanvas Panel Module Template
 * BS5 uyumlu variable adları
 * @version 2.0.0
 */
if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$op   = $data;  // render_fields zaten offcanvas_panel section verisini $data olarak geçiyor
$hd   = $data['header']   ?? [];
$ft   = $data['footer']   ?? [];
$bk   = $data['backdrop'] ?? [];
$anim = $data['animation']?? [];
$weights = ['100','200','300','400','500','600','700','800','900'];
?>

<div class="ts-module-offcanvas-panel">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview" id="ts-op-preview-section">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-oc-preview-wrapper">
            <div class="ts-oc-toggle-area">
                <button type="button" class="ts-oc-toggle-btn" id="ts-op-toggle">
                    <span class="dashicons dashicons-align-pull-right"></span>
                    <?php _e('Open Panel', 'theme-styles'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="ts-oc-preview-panel" id="ts-op-preview-panel" style="display:flex;flex-direction:column;">
        <div id="ts-op-preview-header" style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:500;">Panel Title</span>
            <button type="button" id="ts-op-preview-close" style="background:none;border:none;cursor:pointer;font-size:1.2rem;">✕</button>
        </div>
        <div id="ts-op-preview-body" style="flex:1;overflow-y:auto;">
            <p style="margin:0 0 8px;">Panel body content goes here.</p>
            <p style="margin:0;opacity:.6;font-size:.875rem;">Scroll content can appear here.</p>
        </div>
        <div id="ts-op-preview-footer" style="display:flex;gap:8px;justify-content:flex-end;">
            <button style="padding:6px 14px;border-radius:4px;border:1px solid #ccc;background:#f8f9fa;cursor:pointer;">Cancel</button>
            <button style="padding:6px 14px;border-radius:4px;border:none;background:#0d6efd;color:#fff;cursor:pointer;">Save</button>
        </div>
    </div>
    <div class="ts-oc-preview-overlay" id="ts-op-preview-overlay"></div>

    <!-- ═══ PANEL ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Background', 'theme-styles'); ?></h3>
        <?php theme_styles_render_background_field('bg', $op, ['label' => '', 'show_image' => true, 'id_suffix' => 'op_bg']); ?>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-color)</small></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="color" value="<?php echo esc_attr($op['color'] ?? ''); ?>" placeholder="inherit" />
            </div>
        </div>
    </div>

    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Layout', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Width', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-width)</small></label>
                <input type="text" class="ts-field-input" data-field="width" value="<?php echo esc_attr($op['width'] ?? ''); ?>" placeholder="400px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-padding-x)</small></label>
                <input type="text" class="ts-field-input" data-field="padding_x" value="<?php echo esc_attr($op['padding_x'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-padding-y)</small></label>
                <input type="text" class="ts-field-input" data-field="padding_y" value="<?php echo esc_attr($op['padding_y'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Box Shadow', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-box-shadow)</small></label>
                <input type="text" class="ts-field-input" data-field="box_shadow" value="<?php echo esc_attr($op['box_shadow'] ?? ''); ?>" placeholder="none" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="border_color" value="<?php echo esc_attr($op['border_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="border_width" value="<?php echo esc_attr($op['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Title Line Height', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="title_line_height" value="<?php echo esc_attr($op['title_line_height'] ?? ''); ?>" placeholder="1.5" />
            </div>
        </div>
    </div>

    <!-- ═══ ANIMATION ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Animation', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Duration', 'theme-styles'); ?> <small style="opacity:.5">(offcanvas-transition)</small></label>
                <input type="text" class="ts-field-input" data-field="animation.duration" value="<?php echo esc_attr($anim['duration'] ?? ''); ?>" placeholder="0.3s" />
            </div>
        </div>
    </div>

    <!-- ═══ BACKDROP ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Backdrop', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?> <small style="opacity:.5">(backdrop-bg)</small></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="backdrop.color" value="<?php echo esc_attr($bk['color'] ?? '#000000'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Opacity', 'theme-styles'); ?> <small style="opacity:.5">(backdrop-opacity)</small></label>
                <input type="text" class="ts-field-input" data-field="backdrop.opacity" value="<?php echo esc_attr($bk['opacity'] ?? '0.5'); ?>" placeholder="0.5" />
            </div>
        </div>
    </div>

    <!-- ═══ HEADER ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Header', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="header.bg_color" value="<?php echo esc_attr($hd['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="header.color" value="<?php echo esc_attr($hd['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.font_size" value="<?php echo esc_attr($hd['font_size'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="header.font_weight">
                    <option value="">—</option>
                    <?php foreach ($weights as $w): ?>
                        <option value="<?php echo $w; ?>" <?php selected($hd['font_weight'] ?? '', $w); ?>><?php echo $w; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Padding', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.padding" value="<?php echo esc_attr($hd['padding'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="header.border_color" value="<?php echo esc_attr($hd['border_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.border_width" value="<?php echo esc_attr($hd['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
        </div>
    </div>

    <!-- ═══ FOOTER ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Footer', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="footer.bg_color" value="<?php echo esc_attr($ft['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="footer.color" value="<?php echo esc_attr($ft['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="footer.padding" value="<?php echo esc_attr($ft['padding'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="footer.border_color" value="<?php echo esc_attr($ft['border_color'] ?? ''); ?>" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="footer.border_width" value="<?php echo esc_attr($ft['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
        </div>
    </div>

</div>
