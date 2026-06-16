<?php
/**
 * Header Tools Module Template
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;
$data = $data ?? [];
$htg  = $data['header_tools'] ?? [];
$hts  = $data['social']       ?? [];
$hti  = $data['icons']        ?? [];
$htl  = $data['link']         ?? [];
$htb  = $data['button']       ?? [];
$htla = $data['language']     ?? [];
$htt  = $data['toggler']      ?? [];
$htc  = $data['counter']      ?? [];
$bps  = array_keys(THEME_STYLES_BREAKPOINTS);
$weights = ['100','200','300','400','500','600','700','800','900'];
$height_same = !empty($htg['height_header']);
?>

<div class="ts-module-header-tools">

    <!-- Module Tabs -->
    <div class="ts-module-tabs">
        <button type="button" class="ts-module-tab-btn active" data-module-tab="ht_general">General</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_social">Social Icons</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_icons">Icons</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_link">Link</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_button">Button</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_language">Language</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_toggler">Nav Toggler</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ht_counter">Counter</button>
    </div>

    <!-- ═══ GENERAL ═══ -->
    <div class="ts-module-tab-content active" data-module-tab-content="ht_general">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('General', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label"><?php _e('Height same as Header', 'theme-styles-new'); ?></label>
                    <?php theme_styles_render_switch('header_tools.height_header', $height_same); ?>
                </div>
            </div>
            <div id="ts-ht-height-fields" <?php echo $height_same ? 'style="display:none;"' : ''; ?>>
                <div class="ts-states-grid ts-states-stacked">
                    <div class="ts-state-box">
                        <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                        <div class="ts-state-box-body">
                            <div class="ts-state-field">
                                <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles-new'); ?></label>
                                <div class="ts-responsive-grid" style="margin-top:8px;">
                                    <?php foreach ($bps as $bp): ?>
                                    <div class="ts-responsive-column">
                                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                        <input type="text" class="ts-field-input" data-field="header_tools.height.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['height'][$bp] ?? ''); ?>" placeholder="80px" />
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ts-state-box">
                        <div class="ts-state-box-header"><h5 class="ts-state-box-title">Affix</h5></div>
                        <div class="ts-state-box-body">
                            <div class="ts-state-field">
                                <label class="ts-field-label"><?php _e('Height Affix (per breakpoint)', 'theme-styles-new'); ?></label>
                                <div class="ts-responsive-grid" style="margin-top:8px;">
                                    <?php foreach ($bps as $bp): ?>
                                    <div class="ts-responsive-column">
                                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                        <input type="text" class="ts-field-input" data-field="header_tools.height_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['height_affix'][$bp] ?? ''); ?>" placeholder="60px" />
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <label class="ts-field-label"><?php _e('Gap (per breakpoint)', 'theme-styles-new'); ?></label>
                <div class="ts-responsive-grid" style="margin-top:8px;">
                    <?php foreach ($bps as $bp): ?>
                    <div class="ts-responsive-column">
                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                        <input type="text" class="ts-field-input" data-field="header_tools.gap.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['gap'][$bp] ?? ''); ?>" placeholder="20px" />
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SOCIAL ICONS ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_social">
        <div class="ts-section">
            <h3 class="ts-section-title">Social Icons</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('social.font_family', $hts['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="social.font_size" value="<?php echo esc_attr($hts['font_size'] ?? '24px'); ?>" placeholder="24px" /></div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="social.color" value="<?php echo esc_attr($hts['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="social.color_hover" value="<?php echo esc_attr($hts['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Gap</label><input type="text" class="ts-field-input" data-field="social.gap" value="<?php echo esc_attr($hts['gap'] ?? '10px'); ?>" placeholder="10px" /></div>
            </div>
        </div>
    </div>

    <!-- ═══ ICONS ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_icons">
        <div class="ts-section">
            <h3 class="ts-section-title">Icons</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('icons.font_family', $hti['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="icons.font_size" value="<?php echo esc_attr($hti['font_size'] ?? '20px'); ?>" placeholder="20px" /></div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="icons.color" value="<?php echo esc_attr($hti['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="icons.color_hover" value="<?php echo esc_attr($hti['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Dot Color</label><input type="text" class="ts-field-input ts-color-input" data-field="icons.dot_color" value="<?php echo esc_attr($hti['dot_color'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ═══ LINK ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_link">
        <div class="ts-section">
            <h3 class="ts-section-title">Link</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('link.font_family', $htl['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="link.font_size" value="<?php echo esc_attr($htl['font_size'] ?? '16px'); ?>" placeholder="16px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="link.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htl['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="link.color" value="<?php echo esc_attr($htl['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="link.color_hover" value="<?php echo esc_attr($htl['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="link.color_active" value="<?php echo esc_attr($htl['color_active'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ═══ BUTTON ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_button">
        <div class="ts-section">
            <h3 class="ts-section-title">Button</h3>
            <div class="ts-field-row ts-field-row-3">
                <div><?php theme_styles_render_font_family_field('button.font_family', $htb['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="button.font_size" value="<?php echo esc_attr($htb['font_size'] ?? '14px'); ?>" placeholder="14px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="button.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htb['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ LANGUAGE ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_language">
        <div class="ts-section">
            <h3 class="ts-section-title">Language</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('language.font_family', $htla['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="language.font_size" value="<?php echo esc_attr($htla['font_size'] ?? '16px'); ?>" placeholder="16px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="language.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htla['font_weight'] ?? '400', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="language.color" value="<?php echo esc_attr($htla['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="language.color_hover" value="<?php echo esc_attr($htla['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="language.color_active" value="<?php echo esc_attr($htla['color_active'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ═══ NAV TOGGLER ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_toggler">
        <div class="ts-section">
            <h3 class="ts-section-title">Nav Toggler</h3>
            <div class="ts-field-row">
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="toggler.color" value="<?php echo esc_attr($htt['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="toggler.color_hover" value="<?php echo esc_attr($htt['color_hover'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ═══ COUNTER ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ht_counter">
        <div class="ts-section">
            <h3 class="ts-section-title">Counter Badge</h3>
            <div class="ts-field-row">
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="counter.color" value="<?php echo esc_attr($htc['color'] ?? '#ffffff'); ?>" /></div>
                <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="counter.bg_color" value="<?php echo esc_attr($htc['bg_color'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

</div>
