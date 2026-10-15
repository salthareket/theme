<?php
/**
 * Admin Login Module — Template
 *
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.1.0
 *
 * ts-color-input       → hex only (text colors)
 * ts-color-input-alpha → rgba (background/border colors)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'theme_styles_admin_login_defaults' ) ) {
    require_once __DIR__ . '/processor.php';
}

$defaults = theme_styles_admin_login_defaults();
$data     = array_merge( $defaults, $data ?? [] );
$v        = fn( $key ) => esc_attr( $data[ $key ] ?? '' );
?>

<div class="ts-module-admin-login">

    <!-- ═══ LIVE PREVIEW ═══ -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e( 'Live Preview', 'theme-styles' ); ?></h3>
        <div class="ts-al-preview-wrap" id="ts-al-preview-wrap">
            <div class="ts-al-preview-page" id="ts-al-preview-page">
                <div class="ts-al-preview-box" id="ts-al-preview-box">
                    <div class="ts-al-preview-logo" id="ts-al-preview-logo">
                        <?php if ( ! empty( $data['logo_image_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $data['logo_image_url'] ); ?>" alt="" id="ts-al-logo-img" />
                        <?php else : ?>
                            <div id="ts-al-logo-placeholder">
                                <span class="dashicons dashicons-wordpress-alt" style="font-size:48px;color:#1d2327"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ts-al-preview-field">
                        <div class="ts-al-preview-label" id="ts-al-preview-label">Username or Email</div>
                        <div class="ts-al-preview-input" id="ts-al-preview-input"></div>
                    </div>
                    <div class="ts-al-preview-field">
                        <div class="ts-al-preview-label" id="ts-al-preview-label2">Password</div>
                        <div class="ts-al-preview-input" id="ts-al-preview-input2"></div>
                    </div>
                    <div class="ts-al-preview-btn" id="ts-al-preview-btn">Log In</div>
                    <div class="ts-al-preview-link" id="ts-al-preview-link">Lost your password?</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ PAGE BACKGROUND ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Page Background', 'theme-styles' ); ?></h3>
        <?php theme_styles_render_background_field( 'page_bg', $data, [ 'show_image' => true, 'id_suffix' => 'al_page_bg' ] ); ?>
    </div>

    <!-- ═══ LOGO ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Logo', 'theme-styles' ); ?></h3>
        <div class="ts-field-row">
            <div style="flex:1">
                <label class="ts-field-label"><?php _e( 'Logo Image', 'theme-styles' ); ?></label>
                <div class="ts-media-field">
                    <div class="ts-media-preview" id="ts-al-logo-preview">
                        <?php if ( ! empty( $data['logo_image_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $data['logo_image_url'] ); ?>" alt="" />
                        <?php else : ?>
                            <div class="ts-media-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                                <span><?php _e( 'No logo selected', 'theme-styles' ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ts-media-actions">
                        <button type="button" class="ts-repeater-add-btn ts-media-select" data-target="al_logo">
                            <span class="dashicons dashicons-upload"></span> <?php _e( 'Select Logo', 'theme-styles' ); ?>
                        </button>
                        <button type="button" class="ts-media-remove <?php echo empty( $data['logo_image_url'] ) ? 'hidden' : ''; ?>" data-target="al_logo">
                            <span class="dashicons dashicons-no-alt"></span> <?php _e( 'Remove', 'theme-styles' ); ?>
                        </button>
                    </div>
                    <input type="hidden" data-field="logo_image_id"  value="<?php echo $v('logo_image_id'); ?>"  class="ts-bg-image-id"  data-target="al_logo" />
                    <input type="hidden" data-field="logo_image_url" value="<?php echo $v('logo_image_url'); ?>" class="ts-bg-image-url" data-target="al_logo" />
                </div>
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Max Height (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="logo_max_height" value="<?php echo $v('logo_max_height'); ?>" min="20" max="300" style="width:90px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Max Width (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="logo_max_width" value="<?php echo $v('logo_max_width'); ?>" min="50" max="600" style="width:90px" />
            </div>
        </div>
    </div>

    <!-- ═══ LOGIN BOX ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Login Box', 'theme-styles' ); ?></h3>
        <p class="ts-section-description"><?php _e( 'Background supports alpha (rgba) — use the opacity slider in the color picker.', 'theme-styles' ); ?></p>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e( 'Background', 'theme-styles' ); ?></label>
                <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="box_bg_color" value="<?php echo $v('box_bg_color'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Border Radius (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="box_border_radius" value="<?php echo $v('box_border_radius'); ?>" min="0" max="50" style="width:90px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Width (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="box_width" value="<?php echo $v('box_width'); ?>" min="280" max="600" style="width:90px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Padding (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="box_padding" value="<?php echo $v('box_padding'); ?>" min="0" max="80" style="width:90px" />
            </div>
        </div>
        <div class="ts-field-row" style="margin-top:12px">
            <div>
                <label class="ts-field-label"><?php _e( 'Box Shadow', 'theme-styles' ); ?></label>
                <?php theme_styles_render_switch( 'box_shadow', ! empty( $data['box_shadow'] ) && $data['box_shadow'] !== '0', 'Enabled' ); ?>
            </div>
        </div>
    </div>

    <!-- ═══ FORM FIELDS ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Form Fields', 'theme-styles' ); ?></h3>
        <div class="ts-states-grid">

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Label', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Color', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="label_color" value="<?php echo $v('label_color'); ?>" />
                    </div>
                </div>
            </div>

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Input', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Background', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="input_bg" value="<?php echo $v('input_bg'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Text Color', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input_color" value="<?php echo $v('input_color'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Border', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="input_border" value="<?php echo $v('input_border'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Border (Focus)', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="input_border_focus" value="<?php echo $v('input_border_focus'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Border Radius (px)', 'theme-styles' ); ?></label>
                        <input type="number" class="ts-field-input" data-field="input_radius" value="<?php echo $v('input_radius'); ?>" min="0" max="30" style="width:80px" />
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ BUTTON ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Login Button', 'theme-styles' ); ?></h3>
        <div class="ts-states-grid">

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Default', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Text', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="btn_color" value="<?php echo $v('btn_color'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Background', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="btn_bg" value="<?php echo $v('btn_bg'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Border', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="btn_border" value="<?php echo $v('btn_border'); ?>" />
                    </div>
                </div>
            </div>

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Hover', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Text', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="btn_color_hover" value="<?php echo $v('btn_color_hover'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Background', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="btn_bg_hover" value="<?php echo $v('btn_bg_hover'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Border', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="btn_border_hover" value="<?php echo $v('btn_border_hover'); ?>" />
                    </div>
                </div>
            </div>

        </div>
        <div class="ts-field-row ts-field-row-4" style="margin-top:16px">
            <div>
                <label class="ts-field-label"><?php _e( 'Border Radius (px)', 'theme-styles' ); ?></label>
                <input type="number" class="ts-field-input" data-field="btn_radius" value="<?php echo $v('btn_radius'); ?>" min="0" max="50" style="width:80px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Text Transform', 'theme-styles' ); ?></label>
                <select class="ts-field-select" data-field="btn_text_transform" style="width:140px">
                    <?php foreach ( [ 'none' => 'None', 'uppercase' => 'Uppercase', 'lowercase' => 'Lowercase', 'capitalize' => 'Capitalize' ] as $v2 => $l ) : ?>
                    <option value="<?php echo $v2; ?>" <?php selected( $data['btn_text_transform'], $v2 ); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="ts-field-label"><?php _e( 'Font Weight', 'theme-styles' ); ?></label>
                <select class="ts-field-select" data-field="btn_font_weight" style="width:120px">
                    <?php foreach ( [ '400' => 'Normal', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold' ] as $v2 => $l ) : ?>
                    <option value="<?php echo $v2; ?>" <?php selected( $data['btn_font_weight'], $v2 ); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- ═══ LINKS & TEXT ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Links & Text', 'theme-styles' ); ?></h3>
        <div class="ts-states-grid">

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Text', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Default', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="text_color" value="<?php echo $v('text_color'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Description', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="description_color" value="<?php echo $v('description_color'); ?>" />
                    </div>
                </div>
            </div>

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Links', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Default', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="link_color" value="<?php echo $v('link_color'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Hover', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="link_color_hover" value="<?php echo $v('link_color_hover'); ?>" />
                    </div>
                </div>
            </div>

            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e( 'Messages', 'theme-styles' ); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Error BG', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="error_bg" value="<?php echo $v('error_bg'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Error Border', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input ts-color-input-alpha" data-field="error_border" value="<?php echo $v('error_border'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e( 'Error Text', 'theme-styles' ); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="error_color" value="<?php echo $v('error_color'); ?>" />
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ CUSTOM CSS ═══ -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e( 'Custom CSS', 'theme-styles' ); ?></h3>
        <p class="ts-section-description"><?php _e( 'Additional CSS applied only to the login page. Use <code>body.login</code> as selector.', 'theme-styles' ); ?></p>
        <textarea class="ts-field-input" data-field="custom_css" rows="6" style="font-family:monospace;font-size:12px;width:100%"><?php echo esc_textarea( $data['custom_css'] ?? '' ); ?></textarea>
    </div>

</div>

<style>
.ts-al-preview-wrap {
    background: #f0f0f1;
    border-radius: 8px;
    padding: 20px;
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ts-al-preview-page {
    width: 100%;
    min-height: 280px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: <?php echo esc_attr( $data['page_bg_color'] ?? '#1d2327' ); ?>;
}
.ts-al-preview-box {
    background: <?php echo ! empty( $data['box_bg_color'] ) ? esc_attr( $data['box_bg_color'] ) : 'transparent'; ?>;
    border-radius: <?php echo esc_attr( $data['box_border_radius'] ?? '8' ); ?>px;
    padding: <?php echo esc_attr( $data['box_padding'] ?? '24' ); ?>px;
    width: <?php echo min( 280, intval( $data['box_width'] ?? 320 ) ); ?>px;
    <?php if ( ! empty( $data['box_shadow'] ) && $data['box_shadow'] !== '0' ) : ?>box-shadow: 0 4px 24px rgba(0,0,0,0.18);<?php endif; ?>
}
.ts-al-preview-logo { text-align:center; margin-bottom:16px; }
.ts-al-preview-logo img { max-height:<?php echo esc_attr( $data['logo_max_height'] ?? '80' ); ?>px; max-width:100%; }
.ts-al-preview-field { margin-bottom:12px; }
.ts-al-preview-label { font-size:11px; font-weight:600; color:<?php echo esc_attr( $data['label_color'] ?? '#1d2327' ); ?>; margin-bottom:4px; }
.ts-al-preview-input { height:28px; background:<?php echo ! empty($data['input_bg']) ? esc_attr($data['input_bg']) : 'transparent'; ?>; border:1px solid <?php echo ! empty($data['input_border']) ? esc_attr($data['input_border']) : 'transparent'; ?>; border-radius:<?php echo esc_attr( $data['input_radius'] ?? '4' ); ?>px; }
.ts-al-preview-btn {
    background: <?php echo ! empty($data['btn_bg']) ? esc_attr($data['btn_bg']) : 'transparent'; ?>;
    color: <?php echo esc_attr( $data['btn_color'] ?? '#fff' ); ?>;
    border: 1px solid <?php echo ! empty($data['btn_border']) ? esc_attr($data['btn_border']) : 'transparent'; ?>;
    border-radius: <?php echo esc_attr( $data['btn_radius'] ?? '3' ); ?>px;
    padding: 7px 12px; font-size:13px;
    font-weight: <?php echo esc_attr( $data['btn_font_weight'] ?? '600' ); ?>;
    text-transform: <?php echo esc_attr( $data['btn_text_transform'] ?? 'none' ); ?>;
    text-align: center; margin-top:8px; cursor:pointer;
}
.ts-al-preview-link { font-size:11px; color:<?php echo esc_attr( $data['link_color'] ?? '#2271b1' ); ?>; text-align:center; margin-top:8px; }
</style>
