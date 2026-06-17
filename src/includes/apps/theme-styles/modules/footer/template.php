<?php
/**
 * Footer Module Template
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$bps  = array_keys( THEME_STYLES_BREAKPOINTS );
?>

<div class="ts-module-footer">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview" id="ts-footer-preview-section">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-footer-preview-wrapper">
            <div class="ts-footer-preview" id="ts-footer-preview">
                <div class="ts-fp-inner">
                    <div class="ts-fp-col">
                        <div class="ts-fp-logo">LOGO</div>
                        <p class="ts-fp-text">© 2024 Company Name. All rights reserved.</p>
                    </div>
                    <div class="ts-fp-col">
                        <p class="ts-fp-heading">Quick Links</p>
                        <a href="#" class="ts-fp-link" onclick="return false;">About Us</a>
                        <a href="#" class="ts-fp-link" onclick="return false;">Services</a>
                        <a href="#" class="ts-fp-link ts-fp-link-hover" onclick="return false;">Contact</a>
                    </div>
                    <div class="ts-fp-col">
                        <p class="ts-fp-heading">Contact</p>
                        <a href="#" class="ts-fp-link" onclick="return false;">info@example.com</a>
                        <a href="#" class="ts-fp-link" onclick="return false;">+90 555 000 00 00</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Background -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Background', 'theme-styles'); ?></h3>
        <?php theme_styles_render_background_field('bg', $data, ['label' => '', 'show_image' => true, 'id_suffix' => 'footer_bg']); ?>
    </div>

    <!-- Colors & Typography -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Colors & Typography', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-4">
            <div>
                <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="color" value="<?php echo esc_attr($data['color'] ?? '#ffffff'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Link Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="link_color" value="<?php echo esc_attr($data['link_color'] ?? '#adb5bd'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Link Hover', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="link_color_hover" value="<?php echo esc_attr($data['link_color_hover'] ?? '#ffffff'); ?>" />
            </div>
        </div>
    </div>

    <!-- Size -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Size', 'theme-styles'); ?></h3>
        <div class="ts-field-row">
            <div>
                <label class="ts-field-label"><?php _e('Height', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="height" value="<?php echo esc_attr($data['height'] ?? 'auto'); ?>" placeholder="auto" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Padding', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="padding" value="<?php echo esc_attr($data['padding'] ?? 'auto'); ?>" placeholder="60px 0" />
            </div>
        </div>
    </div>

</div>
