<?php
/**
 * Dropdown Module Template
 * BS5 uyumlu variable adları
 * @version 2.0.0
 */
if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$d    = $data;  // render_fields zaten dropdown section verisini $data olarak geçiyor
$it   = $d['item']    ?? [];
$hd   = $d['header']  ?? [];
$dv   = $d['divider'] ?? [];
$weights = ['100','200','300','400','500','600','700','800','900'];
?>

<div class="ts-module-dropdown">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div id="ts-dd-preview-wrapper" style="position:relative;display:inline-block;">
            <button type="button" class="ts-oc-toggle-btn" id="ts-dd-toggle">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
                <?php _e('Toggle Dropdown', 'theme-styles'); ?>
            </button>
            <div id="ts-dd-preview-menu" style="position:absolute;top:100%;left:0;z-index:1000;min-width:200px;display:none;">
                <div id="ts-dd-preview-header" style="font-weight:600;letter-spacing:.05em;text-transform:uppercase;">Menu Header</div>
                <a href="#" class="ts-dd-item" onclick="return false;" style="display:block;text-decoration:none;white-space:nowrap;">Menu Item 1</a>
                <a href="#" class="ts-dd-item" onclick="return false;" style="display:block;text-decoration:none;white-space:nowrap;">Menu Item 2</a>
                <hr id="ts-dd-preview-divider" style="border:0;border-top:1px solid;margin:0.5rem 0;" />
                <a href="#" class="ts-dd-item active" onclick="return false;" style="display:block;text-decoration:none;white-space:nowrap;">Active Item</a>
                <a href="#" class="ts-dd-item" onclick="return false;" style="display:block;text-decoration:none;white-space:nowrap;">Menu Item 3</a>
            </div>
        </div>
    </div>
    <style>#ts-dd-preview-menu.open { display:block !important; }</style>

    <!-- ═══ MENU ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Background', 'theme-styles'); ?></h3>
        <?php theme_styles_render_background_field('bg', $d, ['label' => '', 'show_image' => false, 'id_suffix' => 'dd_bg']); ?>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="color" value="<?php echo esc_attr($d['color'] ?? ''); ?>" placeholder="inherit" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="font_size" value="<?php echo esc_attr($d['font_size'] ?? ''); ?>" placeholder="1rem" />
            </div>
        </div>
    </div>

    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Layout', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Min Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="min_width" value="<?php echo esc_attr($d['min_width'] ?? ''); ?>" placeholder="10rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="padding_x" value="<?php echo esc_attr($d['padding_x'] ?? ''); ?>" placeholder="0" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="padding_y" value="<?php echo esc_attr($d['padding_y'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Spacer', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="spacer" value="<?php echo esc_attr($d['spacer'] ?? ''); ?>" placeholder="0.125rem" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="border_color" value="<?php echo esc_attr($d['border_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="border_width" value="<?php echo esc_attr($d['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="border_radius" value="<?php echo esc_attr($d['border_radius'] ?? ''); ?>" placeholder="0.375rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Box Shadow', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="box_shadow" value="<?php echo esc_attr($d['box_shadow'] ?? ''); ?>" placeholder="0 .5rem 1rem rgba(0,0,0,.15)" />
            </div>
        </div>
    </div>

    <!-- ═══ HEADER ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Header', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="header.color" value="<?php echo esc_attr($hd['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.font_size" value="<?php echo esc_attr($hd['font_size'] ?? ''); ?>" placeholder="0.75rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.padding_x" value="<?php echo esc_attr($hd['padding_x'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.padding_y" value="<?php echo esc_attr($hd['padding_y'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
        </div>
    </div>

    <!-- ═══ ITEM ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Item', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4" style="margin-bottom:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="item.font_weight">
                    <option value="">—</option>
                    <?php foreach ($weights as $w): ?>
                        <option value="<?php echo $w; ?>" <?php selected($it['font_weight'] ?? '', $w); ?>><?php echo $w; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="item.padding_x" value="<?php echo esc_attr($it['padding_x'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="item.padding_y" value="<?php echo esc_attr($it['padding_y'] ?? ''); ?>" placeholder="0.25rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="item.border_radius" value="<?php echo esc_attr($it['border_radius'] ?? ''); ?>" placeholder="0" />
            </div>
        </div>
        <div class="ts-states-grid">
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label">Color</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.color" value="<?php echo esc_attr($it['color'] ?? ''); ?>" />
                    </div>
                </div>
            </div>
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label">Color</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.color_hover" value="<?php echo esc_attr($it['color_hover'] ?? ''); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label">Bg</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.bg_hover" value="<?php echo esc_attr($it['bg_hover'] ?? ''); ?>" />
                    </div>
                </div>
            </div>
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title">Active</h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label">Color</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.color_active" value="<?php echo esc_attr($it['color_active'] ?? ''); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label">Bg</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.bg_active" value="<?php echo esc_attr($it['bg_active'] ?? ''); ?>" />
                    </div>
                </div>
            </div>
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title">Disabled</h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label">Color</label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="item.color_disabled" value="<?php echo esc_attr($it['color_disabled'] ?? ''); ?>" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ DIVIDER ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Divider', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="divider.color" value="<?php echo esc_attr($dv['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Margin Y', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="divider.margin_y" value="<?php echo esc_attr($dv['margin_y'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
        </div>
    </div>

</div>
