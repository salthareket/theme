<?php
/**
 * Modal Module Template
 * BS5 uyumlu variable adları
 * @version 2.1.0
 */
if (!defined('ABSPATH')) exit;

$data  = $data  ?? [];
$m     = $data;  // render_fields zaten modal section verisini $data olarak geçiyor
$hd    = $m['header']      ?? [];
$bd    = $m['body']        ?? [];
$ft    = $m['footer']      ?? [];
$bk    = $m['backdrop']    ?? [];
$anim  = $m['animation']   ?? [];
$close = $m['close_btn']   ?? [];
$weights = ['100','200','300','400','500','600','700','800','900'];
?>

<div class="ts-module-modal">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview" id="ts-modal-preview-section">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-oc-preview-wrapper">
            <div class="ts-oc-toggle-area">
                <button type="button" class="ts-oc-toggle-btn" id="ts-modal-toggle">
                    <span class="dashicons dashicons-layout"></span>
                    <?php _e('Open Modal', 'theme-styles'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Overlay + Dialog -->
    <div id="ts-modal-preview-overlay" style="position:fixed;inset:0;z-index:999998;pointer-events:none;background:rgba(0,0,0,0.5);opacity:0;visibility:hidden;"></div>
    <div id="ts-modal-preview-dialog" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:999999;width:500px;max-width:90vw;flex-direction:column;overflow:hidden;opacity:0;visibility:hidden;display:none;">
        <div id="ts-modal-preview-header" style="display:flex;align-items:center;justify-content:space-between;padding:1rem;">
            <span id="ts-modal-preview-title" style="font-weight:500;font-size:1.25rem;">Modal Title</span>
            <button type="button" id="ts-modal-preview-close" style="background:none;border:none;cursor:pointer;line-height:1;">✕</button>
        </div>
        <div id="ts-modal-preview-body" style="padding:1rem;">
            <p style="margin:0 0 8px;">Modal body content.</p>
            <p style="margin:0;opacity:.6;font-size:.875rem;">Additional content can go here.</p>
        </div>
        <div id="ts-modal-preview-footer" style="display:flex;gap:8px;justify-content:flex-end;padding:0.75rem 1rem;">
            <button style="padding:6px 14px;border-radius:4px;border:1px solid #ccc;background:#f8f9fa;cursor:pointer;">Close</button>
            <button style="padding:6px 14px;border-radius:4px;border:none;background:#0d6efd;color:#fff;cursor:pointer;">Save</button>
        </div>
    </div>

    <style>
        #ts-modal-preview-overlay.open { pointer-events:auto !important; }
    </style>

    <!-- ═══ BACKGROUND ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Background', 'theme-styles'); ?></h3>
        <?php theme_styles_render_background_field('bg', $m, ['label' => '', 'show_image' => true, 'id_suffix' => 'modal_bg']); ?>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="color" value="<?php echo esc_attr($m['color'] ?? ''); ?>" placeholder="inherit" />
            </div>
        </div>
    </div>

    <!-- ═══ BORDER & SHADOW ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Border & Shadow', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="border_color" value="<?php echo esc_attr($m['border_color'] ?? ''); ?>" placeholder="rgba(0,0,0,.2)" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="border_width" value="<?php echo esc_attr($m['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="border_radius" value="<?php echo esc_attr($m['border_radius'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Box Shadow', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="box_shadow" value="<?php echo esc_attr($m['box_shadow'] ?? ''); ?>" placeholder="0 .5rem 1rem rgba(0,0,0,.5)" />
            </div>
        </div>
    </div>

    <!-- ═══ ANIMATION ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Animation', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Type', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="animation.type">
                    <option value="fade"       <?php selected($anim['type'] ?? 'fade', 'fade'); ?>>Fade</option>
                    <option value="slide-up"   <?php selected($anim['type'] ?? '', 'slide-up'); ?>>Slide Up</option>
                    <option value="slide-down" <?php selected($anim['type'] ?? '', 'slide-down'); ?>>Slide Down</option>
                    <option value="zoom"       <?php selected($anim['type'] ?? '', 'zoom'); ?>>Zoom</option>
                    <option value="zoom-in"    <?php selected($anim['type'] ?? '', 'zoom-in'); ?>>Zoom In</option>
                    <option value="flip"       <?php selected($anim['type'] ?? '', 'flip'); ?>>Flip</option>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Duration', 'theme-styles'); ?></label>
                <div class="ts-range-field">
                    <input type="range" class="ts-field-range" data-field="animation.duration_raw"
                           min="0.1" max="1.5" step="0.05"
                           value="<?php echo esc_attr($anim['duration_raw'] ?? '0.3'); ?>" />
                    <span class="ts-range-value"><?php echo esc_attr($anim['duration_raw'] ?? '0.3'); ?>s</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ BACKDROP ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Backdrop', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="backdrop.color" value="<?php echo esc_attr($bk['color'] ?? '#000000'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Opacity', 'theme-styles'); ?></label>
                <div class="ts-range-field">
                    <input type="range" class="ts-field-range" data-field="backdrop.opacity"
                           min="0" max="1" step="0.05"
                           value="<?php echo esc_attr($bk['opacity'] ?? '0.5'); ?>" />
                    <span class="ts-range-value"><?php echo esc_attr($bk['opacity'] ?? '0.5'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ CLOSE BUTTON ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Close Button', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="close_btn.color" value="<?php echo esc_attr($close['color'] ?? ''); ?>" placeholder="inherit" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Hover Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="close_btn.color_hover" value="<?php echo esc_attr($close['color_hover'] ?? ''); ?>" placeholder="inherit" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="close_btn.size" value="<?php echo esc_attr($close['size'] ?? ''); ?>" placeholder="1.5rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Opacity', 'theme-styles'); ?></label>
                <div class="ts-range-field">
                    <input type="range" class="ts-field-range" data-field="close_btn.opacity"
                           min="0.1" max="1" step="0.05"
                           value="<?php echo esc_attr($close['opacity'] ?? '0.5'); ?>" />
                    <span class="ts-range-value"><?php echo esc_attr($close['opacity'] ?? '0.5'); ?></span>
                </div>
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
                <input type="text" class="ts-field-input" data-field="header.font_size" value="<?php echo esc_attr($hd['font_size'] ?? ''); ?>" placeholder="1.25rem" />
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
            <div>
                <label class="ts-field-label"><?php _e('Title Line Height', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="header.title_line_height" value="<?php echo esc_attr($hd['title_line_height'] ?? ''); ?>" placeholder="1.5" />
            </div>
        </div>
    </div>

    <!-- ═══ BODY ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Body', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="body.bg_color" value="<?php echo esc_attr($bd['bg_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="body.color" value="<?php echo esc_attr($bd['color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding', 'theme-styles'); ?> <small style="opacity:.5">(modal-padding)</small></label>
                <input type="text" class="ts-field-input" data-field="body.padding" value="<?php echo esc_attr($bd['padding'] ?? ''); ?>" placeholder="1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="body.font_size" value="<?php echo esc_attr($bd['font_size'] ?? ''); ?>" placeholder="1rem" />
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
                <input type="text" class="ts-field-input" data-field="footer.padding" value="<?php echo esc_attr($ft['padding'] ?? ''); ?>" placeholder="0.75rem 1rem" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Gap', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="footer.gap" value="<?php echo esc_attr($ft['gap'] ?? ''); ?>" placeholder="0.5rem" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-3" style="margin-top:12px;">
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="footer.border_color" value="<?php echo esc_attr($ft['border_color'] ?? ''); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Width', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="footer.border_width" value="<?php echo esc_attr($ft['border_width'] ?? ''); ?>" placeholder="1px" />
            </div>
        </div>
    </div>

</div>
