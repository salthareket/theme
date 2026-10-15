<?php
/**
 * Popover & Tooltip Module Template
 * BS5 uyumlu variable adları
 * @version 2.0.0
 */
if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$p    = $data['popover']  ?? [];
$phd  = $p['header']      ?? [];
$pbd  = $p['body']        ?? [];
$tt   = $data['tooltip']  ?? [];
?>

<div class="ts-module-popover">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div style="display:flex;gap:40px;align-items:flex-start;flex-wrap:wrap;">
            <div>
                <p style="margin:0 0 8px;font-size:12px;color:#6c757d;text-transform:uppercase;font-weight:600;">Popover</p>
                <div id="ts-pv-preview-popover" style="border:1px solid;overflow:hidden;max-width:276px;">
                    <div id="ts-pv-preview-popover-header" style="font-weight:500;">Popover Title</div>
                    <div id="ts-pv-preview-popover-body">Popover content. Small description text inside the popover body.</div>
                </div>
            </div>
            <div>
                <p style="margin:0 0 8px;font-size:12px;color:#6c757d;text-transform:uppercase;font-weight:600;">Tooltip</p>
                <div id="ts-pv-preview-tooltip" style="display:inline-block;white-space:nowrap;">Tooltip text</div>
            </div>
        </div>
    </div>

    <!-- ═══ POPOVER ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Popover', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?> <small style="opacity:.5">(popover-bg)</small></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="popover.bg_color" value="<?php echo esc_attr($p['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="popover.border_color" value="<?php echo esc_attr($p['border_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.border_width" value="<?php echo esc_attr($p['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.border_radius" value="<?php echo esc_attr($p['border_radius'] ?? ''); ?>" placeholder="0.375rem" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Box Shadow', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.box_shadow" value="<?php echo esc_attr($p['box_shadow'] ?? ''); ?>" placeholder="0 .5rem 1rem rgba(0,0,0,.15)" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Max Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.max_width" value="<?php echo esc_attr($p['max_width'] ?? ''); ?>" placeholder="276px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.font_size" value="<?php echo esc_attr($p['font_size'] ?? ''); ?>" placeholder="0.875rem" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Arrow Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.arrow_width" value="<?php echo esc_attr($p['arrow_width'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Arrow Height', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.arrow_height" value="<?php echo esc_attr($p['arrow_height'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
        </div>
    </div>

    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Popover Header', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="popover.header.bg_color" value="<?php echo esc_attr($phd['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="popover.header.color" value="<?php echo esc_attr($phd['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.header.font_size" value="<?php echo esc_attr($phd['font_size'] ?? ''); ?>" placeholder="1rem" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.header.padding_x" value="<?php echo esc_attr($phd['padding_x'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.header.padding_y" value="<?php echo esc_attr($phd['padding_y'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
        </div>
    </div>

    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Popover Body', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="popover.body.color" value="<?php echo esc_attr($pbd['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.body.padding_x" value="<?php echo esc_attr($pbd['padding_x'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="popover.body.padding_y" value="<?php echo esc_attr($pbd['padding_y'] ?? ''); ?>" placeholder="1rem" />
            </div>
        </div>
    </div>

    <!-- ═══ TOOLTIP ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Tooltip', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?> <small style="opacity:.5">(tooltip-bg)</small></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="tooltip.bg_color" value="<?php echo esc_attr($tt['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="tooltip.color" value="<?php echo esc_attr($tt['color'] ?? '#fff'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.font_size" value="<?php echo esc_attr($tt['font_size'] ?? ''); ?>" placeholder="0.875rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Opacity', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.opacity" value="<?php echo esc_attr($tt['opacity'] ?? ''); ?>" placeholder="0.9" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.border_radius" value="<?php echo esc_attr($tt['border_radius'] ?? ''); ?>" placeholder="0.375rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.padding_x" value="<?php echo esc_attr($tt['padding_x'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.padding_y" value="<?php echo esc_attr($tt['padding_y'] ?? ''); ?>" placeholder="0.25rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Max Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.max_width" value="<?php echo esc_attr($tt['max_width'] ?? ''); ?>" placeholder="200px" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Arrow Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.arrow_width" value="<?php echo esc_attr($tt['arrow_width'] ?? ''); ?>" placeholder="0.8rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Arrow Height', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="tooltip.arrow_height" value="<?php echo esc_attr($tt['arrow_height'] ?? ''); ?>" placeholder="0.4rem" />
            </div>
        </div>
    </div>

</div>
