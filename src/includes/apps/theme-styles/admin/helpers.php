<?php
/**
 * Theme Styles New - Admin Helper Functions
 *
 * Admin arayüzünde kullanılan reusable field render fonksiyonları.
 * Font family select, background (color/gradient/image), toggle switch.
 *
 * @package SaltHareket\Theme\ThemeStyles
 * @version 2.1.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 2.1.0 - 2026-04-27
 * - theme_styles_get_font_choices() System UI (Modern) eklendi
 *
 * 2.0.0 - 2026-04-23
 * - theme_styles_render_background_field() reusable hale getirildi
 *   (Body, Footer, Offcanvas, Header vb. her yerde aynı)
 * - ts-gradient-picker-control class'ı KALDIRILDI (colors.js çakışması)
 *   Sadece ts-bg-gradient-picker-{id} kullanılıyor
 * - Image tab: seçim yapılmadan repeat/position/size gizleniyor
 *
 * 1.0.0 - 2026-04-15
 * - Initial release
 *
 * HOW TO USE:
 *
 * theme_styles_render_font_family_field():
 *   Font family select field render eder. Yabe Webfont, Icon Fonts ve
 *   System Fonts gruplarını içerir.
 *
 * theme_styles_render_background_field():
 *   Color / Gradient / Image sekmeli background field render eder.
 *   ÖNEMLI: prefix parametresi field adlarının önekidir.
 *   data array'i prefix_type, prefix_color, prefix_gradient vb. key'ler içermeli.
 *   id_suffix her sayfada unique olmalı (gradient picker çakışmasını önler).
 *
 * theme_styles_render_switch():
 *   Toggle switch render eder. Hidden input ile değer her zaman submit edilir.
 *
 * @example Font family field:
 * theme_styles_render_font_family_field('nav_item.font_family', $data['font_family'] ?? 'inherit');
 *
 * @example Font family field (custom label):
 * theme_styles_render_font_family_field('header.font', $val, ['label' => 'Header Font']);
 *
 * @example Background field (body modülü):
 * theme_styles_render_background_field('bg', $data, ['id_suffix' => 'body_bg']);
 *
 * @example Background field (footer modülü):
 * theme_styles_render_background_field('bg', $data, ['id_suffix' => 'footer_bg', 'show_image' => true]);
 *
 * @example Background field (offcanvas - image yok):
 * theme_styles_render_background_field('bg', $oc, ['id_suffix' => 'oc_bg', 'show_image' => false]);
 *
 * @example Switch field:
 * theme_styles_render_switch('header.dropshadow', !empty($h['dropshadow']));
 *
 * @example Switch field (label ile):
 * theme_styles_render_switch('navbar.height_header', $checked, 'Same as Header');
 */

if (!defined('ABSPATH')) exit;

/**
 * Render font family select field
 * 
 * @param string $field_name Field name for data-field attribute
 * @param string $current_value Current selected value
 * @param array $args Additional arguments (label, description, class)
 * @return void
 */
function theme_styles_render_font_family_field($field_name, $current_value = '', $args = []) {
    $defaults = [
        'label' => __('Font Family', 'theme-styles'),
        'description' => '',
        'class' => 'ts-field-input ts-font-family-select',
        'show_label' => true,
        'show_description' => true
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Get font choices
    $font_choices = theme_styles_get_font_choices();
    
    ?>
    <?php if ($args['show_label']): ?>
    <label class="ts-field-label"><?php echo esc_html($args['label']); ?></label>
    <?php endif; ?>
    
    <select class="<?php echo esc_attr($args['class']); ?>" data-field="<?php echo esc_attr($field_name); ?>">
        <?php foreach ($font_choices as $group_label => $fonts): ?>
        <optgroup label="<?php echo esc_attr($group_label); ?>">
            <?php foreach ($fonts as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
            <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
    
    <?php if ($args['show_description'] && !empty($args['description'])): ?>
    <span class="ts-field-description"><?php echo esc_html($args['description']); ?></span>
    <?php endif; ?>
    <?php
}

/**
 * Get font choices for select field
 * 
 * @return array Grouped font choices
 */
function theme_styles_get_font_choices() {
    static $font_choices = null;
    
    if ($font_choices !== null) {
        return $font_choices;
    }
    
    $font_choices = [];
    
    // Defaults
    $font_choices[__('Defaults', 'theme-styles')] = [
        'initial' => 'initial',
        'inherit' => 'inherit'
    ];
    
    // Custom Fonts from Yabe Webfont
    if (class_exists('YABE_WEBFONT') && function_exists('yabe_get_fonts')) {
        $yabe_fonts = yabe_get_fonts();
        if (!empty($yabe_fonts)) {
            $custom_fonts = [];
            foreach ($yabe_fonts as $font) {
                $family = isset($font['family']) ? trim($font['family']) : '';
                if ($family === '') continue;
                
                $selector = isset($font['selector']) ? trim($font['selector']) : '';
                $family_safe = "'" . str_replace("'", "", $family) . "'";
                $name = ($selector !== '') ? $family_safe . ', ' . $selector : $family_safe;
                $custom_fonts[$name] = isset($font['title']) ? $font['title'] : $family;
            }
            
            if (!empty($custom_fonts)) {
                $font_choices[__('Custom Fonts', 'theme-styles')] = $custom_fonts;
            }
        }
    }
    
    // Icon Fonts
    $font_choices[__('Icon Fonts', 'theme-styles')] = [
        'Font Awesome 6 Pro' => 'Font Awesome 6 Pro',
        'Font Awesome 6 Brands' => 'Font Awesome 6 Brands'
    ];
    
    // System Fonts
    $font_choices[__('System Fonts', 'theme-styles')] = [
        '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif' => 'System UI (Modern)',
        'Arial, Helvetica, sans-serif' => 'Arial, Helvetica, sans-serif',
        '"Arial Black", Gadget, sans-serif' => 'Arial Black',
        '"Bookman Old Style", serif' => 'Bookman Old Style',
        '"Comic Sans MS", cursive' => 'Comic Sans MS',
        'Courier, monospace' => 'Courier',
        'Garamond, serif' => 'Garamond',
        'Georgia, serif' => 'Georgia',
        'Impact, Charcoal, sans-serif' => 'Impact',
        '"Lucida Console", Monaco, monospace' => 'Lucida Console',
        '"Lucida Sans Unicode", "Lucida Grande", sans-serif' => 'Lucida Sans Unicode',
        '"MS Sans Serif", Geneva, sans-serif' => 'MS Sans Serif',
        '"MS Serif", "New York", sans-serif' => 'MS Serif',
        '"Palatino Linotype", "Book Antiqua", Palatino, serif' => 'Palatino Linotype',
        'Tahoma, Geneva, sans-serif' => 'Tahoma',
        '"Times New Roman", Times, serif' => 'Times New Roman',
        '"Trebuchet MS", Helvetica, sans-serif' => 'Trebuchet MS',
        'Verdana, Geneva, sans-serif' => 'Verdana'
    ];
    
    return $font_choices;
}


/**
 * Render background field (Color + Gradient + Image tabs)
 * Reusable - Body, Footer, Offcanvas, Header vb. her yerde aynı görünüm
 *
 * @param string $prefix     Field name prefix e.g. 'bg', 'footer_bg'
 * @param array  $data       Current module data (flat, prefix'li key'ler içermeli)
 * @param array  $options    show_image (bool), label (string), id_suffix (string)
 */
function theme_styles_render_background_field( string $prefix, array $data = [], array $options = [] ): void {
    $opts = wp_parse_args( $options, [
        'show_image' => true,
        'label'      => '',
        'id_suffix'  => $prefix,
    ] );

    $id          = esc_attr( $opts['id_suffix'] );
    $bg_type     = $data[ $prefix . '_type' ]       ?? 'color';
    $bg_color    = $data[ $prefix . '_color' ]      ?? '#ffffff';
    $bg_gradient = $data[ $prefix . '_gradient' ]   ?? '';
    $bg_image_id  = $data[ $prefix . '_image_id' ]  ?? '';
    $bg_image_url = $data[ $prefix . '_image_url' ] ?? '';
    $bg_size      = $data[ $prefix . '_size' ]      ?? 'cover';
    $bg_position  = $data[ $prefix . '_position' ]  ?? 'center center';
    $bg_repeat    = $data[ $prefix . '_repeat' ]    ?? 'no-repeat';
    $bg_attachment = $data[ $prefix . '_attachment' ] ?? 'scroll';
    $bg_size_w   = $data[ $prefix . '_size_w' ]     ?? '100%';
    $bg_size_h   = $data[ $prefix . '_size_h' ]     ?? 'auto';

    $positions = [
        'top left'     => '↖', 'top center'    => '↑', 'top right'    => '↗',
        'center left'  => '←', 'center center' => '⊙', 'center right' => '→',
        'bottom left'  => '↙', 'bottom center' => '↓', 'bottom right' => '↘',
    ];
    $sizes = ['cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto', 'custom' => 'Custom'];
    ?>
    <div class="ts-bg-field-wrapper" data-bg-id="<?php echo $id; ?>">

        <!-- Type Switcher -->
        <div class="ts-bg-type-switcher">
            <button type="button" class="ts-bg-type-btn <?php echo $bg_type === 'color' ? 'active' : ''; ?>" data-type="color" data-target="<?php echo $id; ?>">
                <span class="dashicons dashicons-art"></span> <?php _e('Color', 'theme-styles'); ?>
            </button>
            <button type="button" class="ts-bg-type-btn <?php echo $bg_type === 'gradient' ? 'active' : ''; ?>" data-type="gradient" data-target="<?php echo $id; ?>">
                <span class="dashicons dashicons-admin-customizer"></span> <?php _e('Gradient', 'theme-styles'); ?>
            </button>
            <?php if ($opts['show_image']): ?>
            <button type="button" class="ts-bg-type-btn <?php echo $bg_type === 'image' ? 'active' : ''; ?>" data-type="image" data-target="<?php echo $id; ?>">
                <span class="dashicons dashicons-format-image"></span> <?php _e('Image', 'theme-styles'); ?>
            </button>
            <?php endif; ?>
        </div>
        <input type="hidden" data-field="<?php echo esc_attr($prefix); ?>_type" value="<?php echo esc_attr($bg_type); ?>" class="ts-bg-type-value" data-target="<?php echo $id; ?>" />

        <!-- COLOR TAB -->
        <div class="ts-bg-tab <?php echo $bg_type === 'color' ? 'active' : ''; ?>" data-tab="color" data-target="<?php echo $id; ?>" style="margin-top:16px;">
            <input type="text" class="ts-field-input ts-color-input" data-field="<?php echo esc_attr($prefix); ?>_color" value="<?php echo esc_attr($bg_color); ?>" />
        </div>

        <!-- GRADIENT TAB -->
        <div class="ts-bg-tab <?php echo $bg_type === 'gradient' ? 'active' : ''; ?>" data-tab="gradient" data-target="<?php echo $id; ?>" style="margin-top:16px;">
            <input type="hidden" class="ts-gradient-input" data-field="<?php echo esc_attr($prefix); ?>_gradient" value="<?php echo esc_attr($bg_gradient); ?>" />
            <div class="ts-bg-gradient-picker-<?php echo $id; ?>"></div>
        </div>

        <?php if ($opts['show_image']): ?>
        <!-- IMAGE TAB -->
        <div class="ts-bg-tab <?php echo $bg_type === 'image' ? 'active' : ''; ?>" data-tab="image" data-target="<?php echo $id; ?>" style="margin-top:16px;">

            <div class="ts-bg-image-layout">
                <!-- Left: Upload -->
                <div class="ts-bg-image-upload">
                    <label class="ts-field-label"><?php _e('Background Image', 'theme-styles'); ?></label>
                    <div class="ts-media-field">
                        <div class="ts-media-preview" id="ts-bg-image-preview-<?php echo $id; ?>">
                            <?php if ($bg_image_url): ?>
                                <img src="<?php echo esc_url($bg_image_url); ?>" alt="" />
                            <?php else: ?>
                                <div class="ts-media-placeholder">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span><?php _e('No image selected', 'theme-styles'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ts-media-actions">
                            <button type="button" class="ts-repeater-add-btn ts-media-select" data-target="<?php echo $id; ?>">
                                <span class="dashicons dashicons-upload"></span> <?php _e('Select Image', 'theme-styles'); ?>
                            </button>
                            <button type="button" class="ts-media-remove <?php echo $bg_image_url ? '' : 'hidden'; ?>" data-target="<?php echo $id; ?>">
                                <span class="dashicons dashicons-no-alt"></span> <?php _e('Remove', 'theme-styles'); ?>
                            </button>
                        </div>
                        <input type="hidden" data-field="<?php echo esc_attr($prefix); ?>_image_id"  value="<?php echo esc_attr($bg_image_id); ?>"  class="ts-bg-image-id"  data-target="<?php echo $id; ?>" />
                        <input type="hidden" data-field="<?php echo esc_attr($prefix); ?>_image_url" value="<?php echo esc_attr($bg_image_url); ?>" class="ts-bg-image-url" data-target="<?php echo $id; ?>" />
                    </div>
                </div>

                <!-- Right: Options - only visible when image selected -->
                <div class="ts-bg-image-options <?php echo $bg_image_url ? '' : 'hidden'; ?>" data-target="<?php echo $id; ?>">

                    <!-- Size -->
                    <div style="margin-bottom:16px;">
                        <label class="ts-field-label"><?php _e('Size', 'theme-styles'); ?></label>
                        <div class="ts-button-group" data-field="<?php echo esc_attr($prefix); ?>_size" data-target="<?php echo $id; ?>">
                            <?php foreach ($sizes as $val => $lbl): ?>
                            <button type="button" class="ts-btn-group-item <?php echo $bg_size === $val ? 'active' : ''; ?>" data-value="<?php echo $val; ?>">
                                <?php echo $lbl; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="ts-bg-custom-size <?php echo $bg_size === 'custom' ? '' : 'hidden'; ?>" style="margin-top:10px;">
                            <div class="ts-field-row">
                                <div>
                                    <label class="ts-field-label"><?php _e('Width', 'theme-styles'); ?></label>
                                    <input type="text" class="ts-field-input" data-field="<?php echo esc_attr($prefix); ?>_size_w" value="<?php echo esc_attr($bg_size_w); ?>" placeholder="100%" />
                                </div>
                                <div>
                                    <label class="ts-field-label"><?php _e('Height', 'theme-styles'); ?></label>
                                    <input type="text" class="ts-field-input" data-field="<?php echo esc_attr($prefix); ?>_size_h" value="<?php echo esc_attr($bg_size_h); ?>" placeholder="auto" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Position -->
                    <div style="margin-bottom:16px;">
                        <label class="ts-field-label"><?php _e('Position', 'theme-styles'); ?></label>
                        <div class="ts-bg-position-grid">
                            <?php foreach ($positions as $val => $icon): ?>
                            <button type="button" class="ts-bg-pos-btn <?php echo $bg_position === $val ? 'active' : ''; ?>"
                                    data-value="<?php echo $val; ?>" data-target="<?php echo $id; ?>" title="<?php echo $val; ?>">
                                <?php echo $icon; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" data-field="<?php echo esc_attr($prefix); ?>_position" value="<?php echo esc_attr($bg_position); ?>" class="ts-bg-position-value" data-target="<?php echo $id; ?>" />
                    </div>

                    <!-- Repeat & Attachment -->
                    <div>
                        <label class="ts-field-label"><?php _e('Repeat', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="<?php echo esc_attr($prefix); ?>_repeat" style="margin-bottom:10px;">
                            <?php foreach (['no-repeat' => 'No Repeat', 'repeat' => 'Repeat', 'repeat-x' => 'Repeat X', 'repeat-y' => 'Repeat Y', 'space' => 'Space', 'round' => 'Round'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php selected($bg_repeat, $v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="ts-field-label"><?php _e('Attachment', 'theme-styles'); ?></label>
                        <div class="ts-button-group" data-field="<?php echo esc_attr($prefix); ?>_attachment">
                            <button type="button" class="ts-btn-group-item <?php echo $bg_attachment === 'scroll' ? 'active' : ''; ?>" data-value="scroll">
                                <span class="dashicons dashicons-arrow-down-alt2"></span> Scroll
                            </button>
                            <button type="button" class="ts-btn-group-item <?php echo $bg_attachment === 'fixed' ? 'active' : ''; ?>" data-value="fixed">
                                <span class="dashicons dashicons-lock"></span> Fixed
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php
}


/**
 * Render toggle switch field
 *
 * @param string $field_name  data-field attribute value
 * @param bool   $checked     Current value
 * @param string $label       Label text
 * @param string $on_value    Value when on  (default '1')
 * @param string $off_value   Value when off (default '0')
 */
function theme_styles_render_switch( string $field_name, bool $checked = false, string $label = '', string $on_value = '1', string $off_value = '0' ): void {
    $uid = 'ts-switch-' . sanitize_key( $field_name );
    ?>
    <div class="ts-switch-wrapper">
        <label class="ts-switch" for="<?php echo esc_attr($uid); ?>">
            <input
                type="checkbox"
                id="<?php echo esc_attr($uid); ?>"
                class="ts-switch-input"
                data-field="<?php echo esc_attr($field_name); ?>"
                data-on="<?php echo esc_attr($on_value); ?>"
                data-off="<?php echo esc_attr($off_value); ?>"
                <?php checked($checked, true); ?>
            />
            <span class="ts-switch-slider"></span>
        </label>
        <?php if ($label): ?>
        <span class="ts-switch-label" onclick="document.getElementById('<?php echo esc_attr($uid); ?>').click()"><?php echo esc_html($label); ?></span>
        <?php endif; ?>
        <!-- Hidden input to always submit a value -->
        <input type="hidden" class="ts-switch-value" data-field="<?php echo esc_attr($field_name); ?>" value="<?php echo $checked ? esc_attr($on_value) : esc_attr($off_value); ?>" />
    </div>
    <?php
}
