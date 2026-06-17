<?php
/**
 * Utilities Module Template - Scroll to Top + Hero
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;
$data = $data ?? [];
$stt  = $data['scroll_to_top'] ?? [];
$hero = $data['hero']          ?? [];
$bps  = array_keys(THEME_STYLES_BREAKPOINTS);
$active = !empty($stt['active']);
?>

<div class="ts-module-utilities">

    <!-- Module Tabs -->
    <div class="ts-module-tabs">
        <button type="button" class="ts-module-tab-btn active" data-module-tab="ut_scroll">Scroll to Top</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="ut_hero">Hero</button>
    </div>

    <!-- ═══ SCROLL TO TOP ═══ -->
    <div class="ts-module-tab-content active" data-module-tab-content="ut_scroll">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Scroll to Top', 'theme-styles'); ?></h3>

            <!-- Active toggle -->
            <div class="ts-field-row" style="margin-bottom:24px;">
                <div>
                    <label class="ts-field-label"><?php _e('Active', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('scroll_to_top.active', $active); ?>
                </div>
            </div>

            <!-- Settings - shown only when active -->
            <div id="ts-stt-settings" <?php echo !$active ? 'style="display:none;"' : ''; ?>>

                <!-- Position & Behavior -->
                <div class="ts-field-row ts-field-row-4" style="margin-bottom:20px;">
                    <div>
                        <label class="ts-field-label"><?php _e('Horizontal Position', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="scroll_to_top.position_hr">
                            <option value="left"   <?php selected($stt['position_hr'] ?? 'right', 'left'); ?>>Left</option>
                            <option value="center" <?php selected($stt['position_hr'] ?? 'right', 'center'); ?>>Center</option>
                            <option value="right"  <?php selected($stt['position_hr'] ?? 'right', 'right'); ?>>Right</option>
                        </select>
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Vertical Position', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="scroll_to_top.position_vr">
                            <option value="top"    <?php selected($stt['position_vr'] ?? 'bottom', 'top'); ?>>Top</option>
                            <option value="center" <?php selected($stt['position_vr'] ?? 'bottom', 'center'); ?>>Center</option>
                            <option value="bottom" <?php selected($stt['position_vr'] ?? 'bottom', 'bottom'); ?>>Bottom</option>
                        </select>
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Gap from Edge', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.gap" value="<?php echo esc_attr($stt['gap'] ?? '35px'); ?>" placeholder="35px" />
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Show', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="scroll_to_top.show">
                            <option value="no"          <?php selected($stt['show'] ?? 'scroll_more', 'no'); ?>>Never</option>
                            <option value="always"      <?php selected($stt['show'] ?? 'scroll_more', 'always'); ?>>Always</option>
                            <option value="scroll"      <?php selected($stt['show'] ?? 'scroll_more', 'scroll'); ?>>On first scroll</option>
                            <option value="scroll_more" <?php selected($stt['show'] ?? 'scroll_more', 'scroll_more'); ?>>When scrolled enough</option>
                        </select>
                    </div>
                </div>

                <!-- Size & Style -->
                <div class="ts-field-row ts-field-row-4" style="margin-bottom:20px;">
                    <div>
                        <label class="ts-field-label"><?php _e('Width', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.width" value="<?php echo esc_attr($stt['width'] ?? '40px'); ?>" placeholder="40px" />
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Height', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.height" value="<?php echo esc_attr($stt['height'] ?? '40px'); ?>" placeholder="40px" />
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.radius" value="<?php echo esc_attr($stt['radius'] ?? '50%'); ?>" placeholder="50%" />
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.font_size" value="<?php echo esc_attr($stt['font_size'] ?? '22px'); ?>" placeholder="22px" />
                    </div>
                </div>

                <!-- Icon & Animation -->
                <div class="ts-field-row ts-field-row-3" style="margin-bottom:20px;">
                    <div>
                        <label class="ts-field-label"><?php _e('Icon (FA HTML)', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.icon" value="<?php echo esc_attr($stt['icon'] ?? '<i class="fa-solid fa-chevron-up"></i>'); ?>" placeholder='<i class="fa-solid fa-chevron-up"></i>' />
                    </div>
                    <div>
                        <label class="ts-field-label"><?php _e('Animation Duration (ms)', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="scroll_to_top.duration" value="<?php echo esc_attr($stt['duration'] ?? '600'); ?>" placeholder="600" />
                    </div>
                </div>

                <!-- Colors -->
                <div class="ts-states-grid">
                    <div class="ts-state-box">
                        <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                        <div class="ts-state-box-body">
                            <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="scroll_to_top.color" value="<?php echo esc_attr($stt['color'] ?? '#ffffff'); ?>" /></div>
                            <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="scroll_to_top.bg_color" value="<?php echo esc_attr($stt['bg_color'] ?? '#000000'); ?>" /></div>
                        </div>
                    </div>
                    <div class="ts-state-box">
                        <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                        <div class="ts-state-box-body">
                            <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="scroll_to_top.color_hover" value="<?php echo esc_attr($stt['color_hover'] ?? '#ffffff'); ?>" /></div>
                            <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="scroll_to_top.bg_color_hover" value="<?php echo esc_attr($stt['bg_color_hover'] ?? '#333333'); ?>" /></div>
                        </div>
                    </div>
                </div>

            </div><!-- /stt-settings -->
        </div>
    </div><!-- /scroll tab -->

    <!-- ═══ HERO ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="ut_hero">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Hero Height', 'theme-styles'); ?></h3>
            <p class="ts-section-description"><?php _e('Set hero section height per breakpoint. Use units like vh, px, etc.', 'theme-styles'); ?></p>
            <div class="ts-responsive-grid" style="margin-top:16px;">
                <?php foreach ($bps as $bp): ?>
                <div class="ts-responsive-column">
                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                    <input type="text" class="ts-field-input" data-field="hero.height.<?php echo $bp; ?>" value="<?php echo esc_attr($hero['height'][$bp] ?? ''); ?>" placeholder="100vh" />
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div><!-- /hero tab -->

</div>

<script>
jQuery(document).ready(function($) {
    // Scroll to top active toggle - switch ile
    $(document).on('change', '[data-field="scroll_to_top.active"]', function() {
        const val = $(this).closest('.ts-switch-wrapper').find('.ts-switch-value').val();
        if (val === '1') {
            $('#ts-stt-settings').slideDown(200);
        } else {
            $('#ts-stt-settings').slideUp(200);
        }
    });

    // Module tab switching for utilities
    $(document).on('click', '#module-utilities .ts-module-tab-btn', function() {
        const tab = $(this).data('module-tab');
        $('#module-utilities .ts-module-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('#module-utilities .ts-module-tab-content').removeClass('active');
        $(`#module-utilities [data-module-tab-content="${tab}"]`).addClass('active');
    });
});
</script>
