<?php
/**
 * Typography Module Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$weights = ['100' => '100', '200' => '200', '300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900'];
$breakpoints = array_map(fn($w) => $w ? "≤{$w}" : '>1600px', THEME_STYLES_BREAKPOINTS);
?>

<div class="ts-module-typography">

    <!-- Edit / Preview Tabs -->
    <div class="ts-module-tabs">
        <button type="button" class="ts-module-tab-btn active" data-module-tab="edit">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('Edit', 'theme-styles-new'); ?>
        </button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="preview">
            <span class="dashicons dashicons-visibility"></span>
            <?php _e('Preview', 'theme-styles-new'); ?>
        </button>
    </div>

    <!-- ═══ EDIT TAB ═══ -->
    <div class="ts-module-tab-content active" data-module-tab-content="edit">

        <!-- Font Families -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Font Families', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-3">
                <div><?php theme_styles_render_font_family_field('font_primary', $data['font_primary'] ?? '', ['label' => __('Primary (Body)', 'theme-styles-new')]); ?></div>
                <div><?php theme_styles_render_font_family_field('font_heading', $data['font_heading'] ?? '', ['label' => __('Heading', 'theme-styles-new')]); ?></div>
                <div><?php theme_styles_render_font_family_field('font_secondary', $data['font_secondary'] ?? '', ['label' => __('Secondary', 'theme-styles-new')]); ?></div>
            </div>
            <div class="ts-field-row ts-field-row-2" style="margin-top: 20px;">
                <div><?php theme_styles_render_font_family_field('icon_font', $data['icon_font'] ?? '', ['label' => __('Icon Font', 'theme-styles-new')]); ?></div>
                <div><?php theme_styles_render_font_family_field('icon_font_brands', $data['icon_font_brands'] ?? '', ['label' => __('Icon Font (Brands)', 'theme-styles-new')]); ?></div>
            </div>
        </div>

        <!-- Base Settings -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Base Settings', 'theme-styles-new'); ?></h3>
            <div class="ts-field-row ts-field-row-4">
                <div>
                    <label class="ts-field-label"><?php _e('Font Size', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="base_font_size" value="<?php echo esc_attr($data['base_font_size'] ?? '16px'); ?>" placeholder="16px" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles-new'); ?></label>
                    <select class="ts-field-select" data-field="base_font_weight">
                        <?php foreach ($weights as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php selected($data['base_font_weight'] ?? '400', $v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Line Height', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="base_line_height" value="<?php echo esc_attr($data['base_line_height'] ?? '1.6'); ?>" placeholder="1.6" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Letter Spacing', 'theme-styles-new'); ?></label>
                    <input type="text" class="ts-field-input" data-field="base_letter_spacing" value="<?php echo esc_attr($data['base_letter_spacing'] ?? '0'); ?>" placeholder="0" />
                </div>
            </div>
        </div>

        <!-- Headings -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Headings', 'theme-styles-new'); ?></h3>
            <div class="ts-headings-grid">
                <?php foreach (['h1','h2','h3','h4','h5','h6'] as $h):
                    $hd = $data['headings'][$h] ?? [];
                ?>
                <div class="ts-heading-column">
                    <div class="ts-heading-label"><?php echo strtoupper($h); ?></div>
                    <div class="ts-heading-fields">
                        <div>
                            <?php theme_styles_render_font_family_field("headings.{$h}.font_family", $hd['font_family'] ?? '', ['label' => __('Font', 'theme-styles-new')]); ?>
                        </div>
                        <div>
                            <label class="ts-field-label-sm"><?php _e('Size', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="headings.<?php echo $h; ?>.font_size" value="<?php echo esc_attr($hd['font_size'] ?? ''); ?>" placeholder="48px" />
                        </div>
                        <div>
                            <label class="ts-field-label-sm"><?php _e('Weight', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="headings.<?php echo $h; ?>.font_weight">
                                <?php foreach ($weights as $v => $l): ?>
                                <option value="<?php echo $v; ?>" <?php selected($hd['font_weight'] ?? '700', $v); ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ts-field-label-sm"><?php _e('Line Height', 'theme-styles-new'); ?></label>
                            <input type="text" class="ts-field-input" data-field="headings.<?php echo $h; ?>.line_height" value="<?php echo esc_attr($hd['line_height'] ?? ''); ?>" placeholder="1.2" />
                        </div>
                        <div>
                            <label class="ts-field-label-sm"><?php _e('Transform', 'theme-styles-new'); ?></label>
                            <select class="ts-field-select" data-field="headings.<?php echo $h; ?>.text_transform">
                                <option value="none" <?php selected($hd['text_transform'] ?? 'none', 'none'); ?>>None</option>
                                <option value="uppercase" <?php selected($hd['text_transform'] ?? 'none', 'uppercase'); ?>>Upper</option>
                                <option value="lowercase" <?php selected($hd['text_transform'] ?? 'none', 'lowercase'); ?>>Lower</option>
                                <option value="capitalize" <?php selected($hd['text_transform'] ?? 'none', 'capitalize'); ?>>Cap</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Responsive Sizes -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Responsive Sizes', 'theme-styles-new'); ?></h3>
            <p class="ts-section-description" style="margin-bottom:24px;"><?php _e('Min (mobile) → Max (desktop) arasında otomatik fluid clamp üretilir.', 'theme-styles-new'); ?></p>

            <!-- Title + Text yan yana -->
            <div class="ts-fluid-sizes-row">

                <!-- Title Sizes -->
                <div class="ts-fluid-type-block">
                    <div class="ts-fluid-type-header">
                        <h4 class="ts-fluid-type-title"><?php _e('Title Sizes', 'theme-styles-new'); ?></h4>
                        <span class="ts-fluid-type-preview" id="ts-fluid-title-preview">clamp(…)</span>
                    </div>

                    <div class="ts-fluid-group-label"><?php _e('Font Size', 'theme-styles-new'); ?></div>
                    <div class="ts-fluid-pair">
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Min</label>
                            <input type="text" class="ts-field-input ts-fluid-input" data-field="title_min_size" data-fluid-target="title" data-fluid-role="min_size" value="<?php echo esc_attr($data['title_min_size'] ?? '24px'); ?>" placeholder="24px" />
                        </div>
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Max</label>
                            <input type="text" class="ts-field-input ts-fluid-input" data-field="title_max_size" data-fluid-target="title" data-fluid-role="max_size" value="<?php echo esc_attr($data['title_max_size'] ?? '64px'); ?>" placeholder="64px" />
                        </div>
                    </div>

                    <div class="ts-fluid-group-label" style="margin-top:16px;"><?php _e('Line Height', 'theme-styles-new'); ?></div>
                    <div class="ts-fluid-pair">
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Min</label>
                            <input type="text" class="ts-field-input" data-field="title_min_lh" value="<?php echo esc_attr($data['title_min_lh'] ?? '1.4'); ?>" placeholder="1.4" />
                        </div>
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Max</label>
                            <input type="text" class="ts-field-input" data-field="title_max_lh" value="<?php echo esc_attr($data['title_max_lh'] ?? '1.2'); ?>" placeholder="1.2" />
                        </div>
                    </div>

                    <button type="button" class="ts-fluid-advanced-toggle" data-target="ts-title-advanced">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Per-breakpoint override', 'theme-styles-new'); ?>
                    </button>
                    <div class="ts-fluid-advanced-panel" id="ts-title-advanced" style="display:none;">
                        <p class="ts-fluid-advanced-note"><?php _e('Doldurduğun breakpoint\'ler clamp yerine direkt değer olarak kullanılır.', 'theme-styles-new'); ?></p>
                        <div class="ts-responsive-grid">
                            <?php foreach ($breakpoints as $bp => $label): ?>
                            <div class="ts-responsive-column">
                                <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                <div class="ts-responsive-fields">
                                    <div><label class="ts-field-label-sm">Size</label><input type="text" class="ts-field-input" data-field="title.<?php echo $bp; ?>" value="<?php echo esc_attr($data['title'][$bp] ?? ''); ?>" placeholder="–" /></div>
                                    <div><label class="ts-field-label-sm">LH</label><input type="text" class="ts-field-input" data-field="title_line_height.<?php echo $bp; ?>" value="<?php echo esc_attr($data['title_line_height'][$bp] ?? ''); ?>" placeholder="–" /></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Text Sizes -->
                <div class="ts-fluid-type-block">
                    <div class="ts-fluid-type-header">
                        <h4 class="ts-fluid-type-title"><?php _e('Text Sizes', 'theme-styles-new'); ?></h4>
                        <span class="ts-fluid-type-preview" id="ts-fluid-text-preview">clamp(…)</span>
                    </div>

                    <div class="ts-fluid-group-label"><?php _e('Font Size', 'theme-styles-new'); ?></div>
                    <div class="ts-fluid-pair">
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Min</label>
                            <input type="text" class="ts-field-input ts-fluid-input" data-field="text_min_size" data-fluid-target="text" data-fluid-role="min_size" value="<?php echo esc_attr($data['text_min_size'] ?? '14px'); ?>" placeholder="14px" />
                        </div>
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Max</label>
                            <input type="text" class="ts-field-input ts-fluid-input" data-field="text_max_size" data-fluid-target="text" data-fluid-role="max_size" value="<?php echo esc_attr($data['text_max_size'] ?? '18px'); ?>" placeholder="18px" />
                        </div>
                    </div>

                    <div class="ts-fluid-group-label" style="margin-top:16px;"><?php _e('Line Height', 'theme-styles-new'); ?></div>
                    <div class="ts-fluid-pair">
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Min</label>
                            <input type="text" class="ts-field-input" data-field="text_min_lh" value="<?php echo esc_attr($data['text_min_lh'] ?? '1.5'); ?>" placeholder="1.5" />
                        </div>
                        <div class="ts-fluid-pair-row">
                            <label class="ts-fluid-pair-label">Max</label>
                            <input type="text" class="ts-field-input" data-field="text_max_lh" value="<?php echo esc_attr($data['text_max_lh'] ?? '1.6'); ?>" placeholder="1.6" />
                        </div>
                    </div>

                    <button type="button" class="ts-fluid-advanced-toggle" data-target="ts-text-advanced">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Per-breakpoint override', 'theme-styles-new'); ?>
                    </button>
                    <div class="ts-fluid-advanced-panel" id="ts-text-advanced" style="display:none;">
                        <p class="ts-fluid-advanced-note"><?php _e('Doldurduğun breakpoint\'ler clamp yerine direkt değer olarak kullanılır.', 'theme-styles-new'); ?></p>
                        <div class="ts-responsive-grid">
                            <?php foreach ($breakpoints as $bp => $label): ?>
                            <div class="ts-responsive-column">
                                <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                <div class="ts-responsive-fields">
                                    <div><label class="ts-field-label-sm">Size</label><input type="text" class="ts-field-input" data-field="text.<?php echo $bp; ?>" value="<?php echo esc_attr($data['text'][$bp] ?? ''); ?>" placeholder="–" /></div>
                                    <div><label class="ts-field-label-sm">LH</label><input type="text" class="ts-field-input" data-field="text_line_height.<?php echo $bp; ?>" value="<?php echo esc_attr($data['text_line_height'][$bp] ?? ''); ?>" placeholder="–" /></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /ts-fluid-sizes-row -->

            <!-- Mobile Override -->
            <div class="ts-fluid-type-block" style="margin-top:16px;">
                <div class="ts-fluid-type-header">
                    <h4 class="ts-fluid-type-title">
                        <span class="dashicons dashicons-smartphone" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
                        <?php _e('Mobile Overrides', 'theme-styles-new'); ?>
                    </h4>
                    <?php theme_styles_render_switch('mobile_override_active', !empty($data['mobile_override_active'])); ?>
                </div>
                <div id="ts-mobile-override-panel" <?php echo empty($data['mobile_override_active']) ? 'style="display:none;"' : ''; ?>>
                    <p class="ts-fluid-advanced-note" style="margin:12px 0;"><?php _e('xs breakpoint\'te fluid clamp yerine bu değerler kullanılır.', 'theme-styles-new'); ?></p>
                    <div class="ts-responsive-grid" style="margin-top:12px;">
                        <?php foreach ($breakpoints as $bp => $label): ?>
                        <div class="ts-responsive-column">
                            <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                            <div class="ts-responsive-fields">
                                <div><label class="ts-field-label-sm">Title</label><input type="text" class="ts-field-input" data-field="title_mobile.<?php echo $bp; ?>" value="<?php echo esc_attr($data['title_mobile'][$bp] ?? ''); ?>" placeholder="–" /></div>
                                <div><label class="ts-field-label-sm">Text</label><input type="text" class="ts-field-input" data-field="text_mobile.<?php echo $bp; ?>" value="<?php echo esc_attr($data['text_mobile'][$bp] ?? ''); ?>" placeholder="–" /></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div><!-- /edit tab -->

    <!-- ═══ PREVIEW TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="preview">
        <div class="ts-typography-preview" id="ts-typography-preview">

            <!-- Headings Preview -->
            <div class="ts-typo-preview-section">
                <div class="ts-typo-preview-label">Headings</div>
                <div id="ts-preview-h1" class="ts-preview-heading">Heading 1 — The quick brown fox</div>
                <div id="ts-preview-h2" class="ts-preview-heading">Heading 2 — The quick brown fox</div>
                <div id="ts-preview-h3" class="ts-preview-heading">Heading 3 — The quick brown fox</div>
                <div id="ts-preview-h4" class="ts-preview-heading">Heading 4 — The quick brown fox</div>
                <div id="ts-preview-h5" class="ts-preview-heading">Heading 5 — The quick brown fox</div>
                <div id="ts-preview-h6" class="ts-preview-heading">Heading 6 — The quick brown fox</div>
            </div>

            <!-- Body Text Preview -->
            <div class="ts-typo-preview-section">
                <div class="ts-typo-preview-label">Body Text</div>
                <div id="ts-preview-body">
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
                    <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.</p>
                </div>
            </div>

            <!-- Title Sizes Preview -->
            <div class="ts-typo-preview-section">
                <div class="ts-typo-preview-label">Title Scale</div>
                <div class="ts-title-scale-grid">
                    <!-- Desktop -->
                    <div class="ts-title-scale-group">
                        <div class="ts-title-scale-group-label">
                            <span class="dashicons dashicons-desktop"></span> Desktop
                        </div>
                        <?php
                        $bp_labels = ['xxxl' => '>1600px', 'xxl' => '≤1599px', 'xl' => '≤1399px', 'lg' => '≤1199px', 'md' => '≤991px', 'sm' => '≤767px', 'xs' => '≤575px'];
                        foreach ($bp_labels as $bp => $label):
                        ?>
                        <div class="ts-title-scale-row" data-bp="<?php echo $bp; ?>" data-type="title">
                            <div class="ts-title-scale-badge">
                                <span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span>
                                <span class="ts-bp-range"><?php echo $label; ?></span>
                            </div>
                            <div class="ts-title-scale-text" id="ts-preview-title-<?php echo $bp; ?>">
                                The quick brown fox
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Mobile -->
                    <div class="ts-title-scale-group">
                        <div class="ts-title-scale-group-label">
                            <span class="dashicons dashicons-smartphone"></span> Mobile
                        </div>
                        <?php foreach ($bp_labels as $bp => $label): ?>
                        <div class="ts-title-scale-row" data-bp="<?php echo $bp; ?>" data-type="title_mobile">
                            <div class="ts-title-scale-badge">
                                <span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span>
                                <span class="ts-bp-range"><?php echo $label; ?></span>
                            </div>
                            <div class="ts-title-scale-text" id="ts-preview-title-mobile-<?php echo $bp; ?>">
                                The quick brown fox
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /preview tab -->

</div>
