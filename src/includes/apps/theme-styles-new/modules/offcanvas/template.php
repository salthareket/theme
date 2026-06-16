<?php
/**
 * Offcanvas Menu Module Template
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;
$data = $data ?? [];
$oc   = $data['offcanvas']  ?? [];
$mh   = $data['header']     ?? [];
$ni   = $data['nav_item']   ?? [];
$ns   = $data['nav_sub']    ?? [];
$nsi  = $data['nav_sub_item'] ?? [];
$weights = ['100','200','300','400','500','600','700','800','900'];
?>

<div class="ts-module-offcanvas">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview" id="ts-offcanvas-preview-section">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles-new'); ?></h3>
        <div class="ts-oc-preview-wrapper">
            <div class="ts-oc-toggle-area">
                <button type="button" class="ts-oc-toggle-btn" id="ts-oc-toggle">
                    <span class="dashicons dashicons-menu-alt"></span>
                    <?php _e('Toggle Offcanvas', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Offcanvas Preview Panel (fixed, body level) -->
    <div class="ts-oc-preview-panel" id="ts-oc-preview-panel">
        <div class="ts-oc-preview-header" id="ts-oc-preview-header">
            <span class="ts-oc-preview-title" id="ts-oc-preview-title">Menu</span>
            <button type="button" class="ts-oc-preview-close" id="ts-oc-preview-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <nav class="ts-oc-preview-nav" id="ts-oc-preview-nav">
            <a href="#" class="ts-oc-nav-item" onclick="return false;">Home</a>
            <a href="#" class="ts-oc-nav-item ts-oc-nav-active" onclick="return false;">About</a>
            <div class="ts-oc-nav-item ts-oc-has-sub">
                <span>Services</span>
                <span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px;width:14px;height:14px;"></span>
                <div class="ts-oc-sub-menu" id="ts-oc-sub-menu">
                    <a href="#" class="ts-oc-sub-item" onclick="return false;">Web Design</a>
                    <a href="#" class="ts-oc-sub-item" onclick="return false;">Development</a>
                </div>
            </div>
            <a href="#" class="ts-oc-nav-item" onclick="return false;">Contact</a>
        </nav>
    </div>
    <div class="ts-oc-preview-overlay" id="ts-oc-preview-overlay"></div>

    <!-- Module Tabs -->
    <div class="ts-module-tabs">
        <button type="button" class="ts-module-tab-btn active" data-module-tab="oc_general">General</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="oc_header">Menu Header</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="oc_item">Menu Item</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="oc_sub">Sub Menu</button>
    </div>

    <!-- ═══ GENERAL ═══ -->
    <div class="ts-module-tab-content active" data-module-tab-content="oc_general">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Background', 'theme-styles-new'); ?></h3>
            <?php theme_styles_render_background_field('bg', $oc, ['label' => '', 'show_image' => true, 'id_suffix' => 'oc_bg']); ?>
        </div>
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Layout', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-3">
                <div>
                    <label class="ts-field-label"><?php _e('Padding', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="offcanvas.padding" value="<?php echo esc_attr($oc['padding'] ?? '15px 0 40px 0'); ?>" placeholder="15px 0 40px 0" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Align Horizontal', 'theme-styles-new'); ?></label>
                    <select class="ts-field-select" data-field="offcanvas.align_hr">
                        <option value="start"  <?php selected($oc['align_hr'] ?? 'start', 'start'); ?>>Left</option>
                        <option value="center" <?php selected($oc['align_hr'] ?? 'start', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($oc['align_hr'] ?? 'start', 'end'); ?>>Right</option>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Align Vertical', 'theme-styles-new'); ?></label>
                    <select class="ts-field-select" data-field="offcanvas.align_vr">
                        <option value="start"  <?php selected($oc['align_vr'] ?? 'center', 'start'); ?>>Top</option>
                        <option value="center" <?php selected($oc['align_vr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($oc['align_vr'] ?? 'center', 'end'); ?>>Bottom</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MENU HEADER ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="oc_header">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Menu Header', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('header.font_family', $mh['font_family'] ?? 'inherit'); ?></div>
                <div>
                    <label class="ts-field-label">Font Size</label>
                    <input type="text" class="ts-field-input" data-field="header.font_size" value="<?php echo esc_attr($mh['font_size'] ?? '28px'); ?>" placeholder="28px" />
                </div>
                <div>
                    <label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="header.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($mh['font_weight'] ?? '600', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label">Padding</label>
                    <input type="text" class="ts-field-input" data-field="header.padding" value="<?php echo esc_attr($mh['padding'] ?? '10px 40px 10px 0'); ?>" placeholder="10px 40px 10px 0" />
                </div>
            </div>
            <div class="ts-field-row ts-field-row-4" style="margin-top:16px;">
                <div>
                    <label class="ts-field-label">Color</label>
                    <input type="text" class="ts-field-input ts-color-input" data-field="header.color" value="<?php echo esc_attr($mh['color'] ?? ''); ?>" />
                </div>
                <div>
                    <label class="ts-field-label">Icon Size</label>
                    <input type="text" class="ts-field-input" data-field="header.icon_font_size" value="<?php echo esc_attr($mh['icon_font_size'] ?? '22px'); ?>" placeholder="22px" />
                </div>
                <div>
                    <label class="ts-field-label">Icon Color</label>
                    <input type="text" class="ts-field-input ts-color-input" data-field="header.icon_color" value="<?php echo esc_attr($mh['icon_color'] ?? ''); ?>" />
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MENU ITEM ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="oc_item">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Menu Item', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-4" style="margin-bottom:20px;">
                <div><?php theme_styles_render_font_family_field('nav_item.font_family', $ni['font_family'] ?? 'inherit'); ?></div>
                <div>
                    <label class="ts-field-label">Font Size</label>
                    <input type="text" class="ts-field-input" data-field="nav_item.font_size" value="<?php echo esc_attr($ni['font_size'] ?? '36px'); ?>" placeholder="36px" />
                </div>
                <div>
                    <label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="nav_item.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($ni['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label">Padding</label>
                    <input type="text" class="ts-field-input" data-field="nav_item.padding" value="<?php echo esc_attr($ni['padding'] ?? '5px 25px 5px 0'); ?>" placeholder="5px 25px 5px 0" />
                </div>
            </div>
            <div class="ts-field-row ts-field-row-3" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label">Align</label>
                    <select class="ts-field-select" data-field="nav_item.align_hr">
                        <option value="start"  <?php selected($ni['align_hr'] ?? 'start', 'start'); ?>>Left</option>
                        <option value="center" <?php selected($ni['align_hr'] ?? 'start', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($ni['align_hr'] ?? 'start', 'end'); ?>>Right</option>
                    </select>
                </div>
            </div>
            <div class="ts-states-grid">
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.color" value="<?php echo esc_attr($ni['color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.bg_color" value="<?php echo esc_attr($ni['bg_color'] ?? ''); ?>" /></div>
                    </div>
                </div>
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.color_hover" value="<?php echo esc_attr($ni['color_hover'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.bg_color_hover" value="<?php echo esc_attr($ni['bg_color_hover'] ?? ''); ?>" /></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SUB MENU ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="oc_sub">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Sub Menu Container', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row">
                <div>
                    <label class="ts-field-label">Bg Color</label>
                    <input type="text" class="ts-field-input ts-color-input" data-field="nav_sub.bg_color" value="<?php echo esc_attr($ns['bg_color'] ?? '#f9f9f9'); ?>" />
                </div>
                <div>
                    <label class="ts-field-label">Padding</label>
                    <input type="text" class="ts-field-input" data-field="nav_sub.padding" value="<?php echo esc_attr($ns['padding'] ?? '15px 10px'); ?>" placeholder="15px 10px" />
                </div>
            </div>
        </div>
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Sub Menu Item', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-4" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label">Font Size</label>
                    <input type="text" class="ts-field-input" data-field="nav_sub_item.font_size" value="<?php echo esc_attr($nsi['font_size'] ?? '18px'); ?>" placeholder="18px" />
                </div>
                <div>
                    <label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="nav_sub_item.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($nsi['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label">Font Weight Hover</label>
                    <select class="ts-field-select" data-field="nav_sub_item.font_weight_hover">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($nsi['font_weight_hover'] ?? '700', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label">Padding</label>
                    <input type="text" class="ts-field-input" data-field="nav_sub_item.padding" value="<?php echo esc_attr($nsi['padding'] ?? '5px 18px'); ?>" placeholder="5px 18px" />
                </div>
            </div>
            <div class="ts-states-grid">
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_sub_item.color" value="<?php echo esc_attr($nsi['color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_sub_item.bg_color" value="<?php echo esc_attr($nsi['bg_color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Border</label><input type="text" class="ts-field-input" data-field="nav_sub_item.border" value="<?php echo esc_attr($nsi['border'] ?? 'none'); ?>" placeholder="none" /></div>
                    </div>
                </div>
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_sub_item.color_hover" value="<?php echo esc_attr($nsi['color_hover'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_sub_item.bg_color_hover" value="<?php echo esc_attr($nsi['bg_color_hover'] ?? '#f2f2f2'); ?>" /></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
