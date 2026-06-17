<?php
/**
 * Header Module Template
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$data   = $data ?? [];
$h      = $data['header']   ?? [];
$navbar = $data['navbar']   ?? [];
$nav    = $data['nav']      ?? [];
$ni     = $data['nav_item'] ?? [];
$dd     = $data['dropdown'] ?? [];
$logo   = $data['logo']     ?? [];
$ht     = $data['header_tools'] ?? [];
$bps    = array_keys( THEME_STYLES_BREAKPOINTS );
$weights = ['100','200','300','400','500','600','700','800','900'];
?>

<div class="ts-module-header">

    <!-- Live Preview -->
    <div class="ts-section ts-sticky-preview" id="ts-header-preview-section">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>
        <div class="ts-header-preview-wrapper">
            <!-- State toggle -->
            <div class="ts-header-preview-controls">
                <button type="button" class="ts-btn-group-item active" id="ts-hp-default"><?php _e('Default', 'theme-styles'); ?></button>
                <button type="button" class="ts-btn-group-item" id="ts-hp-affix"><?php _e('Affix', 'theme-styles'); ?></button>
            </div>
            <!-- Fake header -->
            <div class="ts-header-preview" id="ts-header-preview">
                <div class="ts-hp-logo">
                    <img class="ts-hp-logo-img ts-hp-logo-default" src="" alt="Logo" style="max-height:40px;display:none;" />
                    <img class="ts-hp-logo-img ts-hp-logo-affix" src="" alt="Logo" style="max-height:40px;display:none;" />
                    <span class="ts-hp-logo-text">LOGO</span>
                </div>
                <nav class="ts-hp-nav">
                    <a href="#" class="ts-hp-nav-item" onclick="return false;">Home</a>
                    <a href="#" class="ts-hp-nav-item ts-hp-active" onclick="return false;">About</a>
                    <div class="ts-hp-nav-item ts-hp-has-dropdown">
                        Services
                        <div class="ts-hp-dropdown">
                            <a href="#" class="ts-hp-dropdown-item" onclick="return false;">Web Design</a>
                            <a href="#" class="ts-hp-dropdown-item" onclick="return false;">Development</a>
                            <a href="#" class="ts-hp-dropdown-item" onclick="return false;">Marketing</a>
                        </div>
                    </div>
                    <a href="#" class="ts-hp-nav-item" onclick="return false;">Contact</a>
                </nav>
            </div>
        </div>
    </div>

    <!-- Module Tabs -->
    <div class="ts-module-tabs">
        <button type="button" class="ts-module-tab-btn active" data-module-tab="general">General</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="navbar">Navbar</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="nav">Nav</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="nav_item">Nav Item</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="dropdown">Dropdown</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="logo">Logo</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="themes">Themes</button>
        <button type="button" class="ts-module-tab-btn" data-module-tab="header_tools">Header Tools</button>
    </div>
    
    <!-- ═══ GENERAL TAB ═══ -->
    <div class="ts-module-tab-content active" data-module-tab-content="general">
    <!-- Header Background -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('General', 'theme-styles'); ?></h3>
            <div class="ts-field-row ts-field-row-3" style="margin-bottom:24px;">
                <div>
                    <label class="ts-field-label"><?php _e('Dropshadow', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('header.dropshadow', !empty($h['dropshadow'])); ?>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Z-Index', 'theme-styles'); ?></label>
                    <input type="number" class="ts-field-input" data-field="header.z_index" value="<?php echo esc_attr($h['z_index'] ?? '5'); ?>" placeholder="5" />
                </div>
            </div>

            <!-- Default / Affix state boxes -->
            <div class="ts-states-grid ts-states-stacked">
                <!-- Default -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles'); ?></h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="header.bg_color" value="<?php echo esc_attr($h['bg_color'] ?? ''); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="header.height.<?php echo $bp; ?>" value="<?php echo esc_attr($h['height'][$bp] ?? ''); ?>" placeholder="80px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Affix -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Affix', 'theme-styles'); ?></h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="header.bg_color_affix" value="<?php echo esc_attr($h['bg_color_affix'] ?? '#ffffff'); ?>" />
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="header.height_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($h['height_affix'][$bp] ?? ''); ?>" placeholder="60px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /general tab -->
    
    <!-- ═══ NAVBAR TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="navbar">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Navigation Container', 'theme-styles'); ?></h3>
            <div class="ts-field-row ts-field-row-3" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label"><?php _e('Align Horizontal', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="navbar.align_hr">
                        <option value="start"  <?php selected($navbar['align_hr'] ?? 'center', 'start'); ?>>Left</option>
                        <option value="center" <?php selected($navbar['align_hr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($navbar['align_hr'] ?? 'center', 'end'); ?>>Right</option>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Align Vertical', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="navbar.align_vr">
                        <option value="start"  <?php selected($navbar['align_vr'] ?? 'center', 'start'); ?>>Top</option>
                        <option value="center" <?php selected($navbar['align_vr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($navbar['align_vr'] ?? 'center', 'end'); ?>>Bottom</option>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Height same as Header', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('navbar.height_header', !empty($navbar['height_header'])); ?>
                </div>
            </div>

            <!-- Default / Affix state boxes - her zaman görünür -->
            <div class="ts-states-grid ts-states-stacked">
                <!-- Default -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles'); ?></h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="navbar.bg_color" value="<?php echo esc_attr($navbar['bg_color'] ?? ''); ?>" />
                        </div>
                        <!-- Height - sadece switch kapalıyken görünür -->
                        <div class="ts-state-field ts-navbar-height-field" <?php echo !empty($navbar['height_header']) ? 'style="display:none;"' : ''; ?>>
                            <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="navbar.height.<?php echo $bp; ?>" value="<?php echo esc_attr($navbar['height'][$bp] ?? ''); ?>" placeholder="80px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Padding (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="navbar.padding.<?php echo $bp; ?>" value="<?php echo esc_attr($navbar['padding'][$bp] ?? ''); ?>" placeholder="0px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Affix -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Affix', 'theme-styles'); ?></h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Bg Color', 'theme-styles'); ?></label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="navbar.bg_color_affix" value="<?php echo esc_attr($navbar['bg_color_affix'] ?? ''); ?>" />
                        </div>
                        <!-- Height Affix - sadece switch kapalıyken görünür -->
                        <div class="ts-state-field ts-navbar-height-field" <?php echo !empty($navbar['height_header']) ? 'style="display:none;"' : ''; ?>>
                            <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="navbar.height_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($navbar['height_affix'][$bp] ?? ''); ?>" placeholder="60px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="ts-state-field">
                            <label class="ts-field-label"><?php _e('Padding (per breakpoint)', 'theme-styles'); ?></label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="navbar.padding_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($navbar['padding_affix'][$bp] ?? ''); ?>" placeholder="0px" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /navbar tab -->
    
    <!-- ═══ NAV TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="nav">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Navigation', 'theme-styles'); ?></h3>
            <?php $nav_height_same = !empty($nav['height_header']); ?>
            <div class="ts-field-row ts-field-row-3" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label"><?php _e('Width', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav.width" value="<?php echo esc_attr($nav['width'] ?? 'auto'); ?>" placeholder="auto" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Margin', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav.margin" value="<?php echo esc_attr($nav['margin'] ?? '0'); ?>" placeholder="0" />
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Height same as Header', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('nav.height_header', $nav_height_same); ?>
                </div>
            </div>
            <div class="ts-nav-height-field" <?php echo $nav_height_same ? 'style="display:none;"' : ''; ?>>
                <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                <div class="ts-responsive-grid" style="margin-top:8px;">
                    <?php foreach ($bps as $bp): ?>
                    <div class="ts-responsive-column">
                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                        <input type="text" class="ts-field-input" data-field="nav.height.<?php echo $bp; ?>" value="<?php echo esc_attr($nav['height'][$bp] ?? ''); ?>" placeholder="80px" />
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div><!-- /nav tab -->
    
    <!-- ═══ NAV ITEM TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="nav_item">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Nav Item', 'theme-styles'); ?></h3>

            <!-- Font row - 5 kolon -->
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
                <div><?php theme_styles_render_font_family_field('nav_item.font_family', $ni['font_family'] ?? 'inherit'); ?></div>
                <div>
                    <label class="ts-field-label"><?php _e('Weight', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="nav_item.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($ni['font_weight'] ?? '400', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Weight Active', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="nav_item.font_weight_active">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($ni['font_weight_active'] ?? '700', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Transform', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="nav_item.text_transform">
                        <option value="none"       <?php selected($ni['text_transform'] ?? 'none', 'none'); ?>>None</option>
                        <option value="uppercase"  <?php selected($ni['text_transform'] ?? 'none', 'uppercase'); ?>>Upper</option>
                        <option value="lowercase"  <?php selected($ni['text_transform'] ?? 'none', 'lowercase'); ?>>Lower</option>
                        <option value="capitalize" <?php selected($ni['text_transform'] ?? 'none', 'capitalize'); ?>>Cap</option>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Letter Spacing', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="nav_item.letter_spacing" value="<?php echo esc_attr($ni['letter_spacing'] ?? '0'); ?>" placeholder="0" />
                </div>
            </div>
            <!-- States -->
            <div class="ts-states-grid ts-states-stacked">
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.color" value="<?php echo esc_attr($ni['color'] ?? '#212529'); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.bg_color" value="<?php echo esc_attr($ni['bg_color'] ?? ''); ?>" /></div>
                    </div>
                </div>
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.color_hover" value="<?php echo esc_attr($ni['color_hover'] ?? '#007bff'); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.bg_color_hover" value="<?php echo esc_attr($ni['bg_color_hover'] ?? ''); ?>" /></div>
                    </div>
                </div>
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Active</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.color_active" value="<?php echo esc_attr($ni['color_active'] ?? '#007bff'); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="nav_item.bg_color_active" value="<?php echo esc_attr($ni['bg_color_active'] ?? ''); ?>" /></div>
                    </div>
                </div>
            </div>
            <!-- Font size responsive -->
            <div style="margin-top:24px;">
                <label class="ts-field-label"><?php _e('Font Size (per breakpoint)', 'theme-styles'); ?></label>
                <div class="ts-responsive-grid" style="margin-top:8px;">
                    <?php foreach ($bps as $bp): ?>
                    <div class="ts-responsive-column">
                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                        <input type="text" class="ts-field-input" data-field="nav_item.font_size.<?php echo $bp; ?>" value="<?php echo esc_attr($ni['font_size'][$bp] ?? ''); ?>" placeholder="16px" />
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Padding responsive -->
            <div style="margin-top:16px;">
                <label class="ts-field-label"><?php _e('Padding (per breakpoint)', 'theme-styles'); ?></label>
                <div class="ts-responsive-grid" style="margin-top:8px;">
                    <?php foreach ($bps as $bp): ?>
                    <div class="ts-responsive-column">
                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                        <input type="text" class="ts-field-input" data-field="nav_item.padding.<?php echo $bp; ?>" value="<?php echo esc_attr($ni['padding'][$bp] ?? ''); ?>" placeholder="0 15px" />
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div><!-- /nav_item tab -->

    <!-- ═══ DROPDOWN TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="dropdown">
        <?php $ddd = $dd['dropdown'] ?? []; $ddi = $dd['dropdown_item'] ?? []; $arrow = $dd['arrow'] ?? []; $arrow_active = !empty($arrow['arrow']); ?>

        <!-- Arrow -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Arrow', 'theme-styles'); ?></h3>
            <div class="ts-field-row ts-field-row-3">
                <div>
                    <label class="ts-field-label"><?php _e('Use Arrow', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('dropdown.arrow.arrow', $arrow_active); ?>
                </div>
                <div class="ts-dropdown-arrow-fields" <?php echo !$arrow_active ? 'style="display:none;"' : ''; ?>>
                    <label class="ts-field-label"><?php _e('Top', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="dropdown.arrow.top" value="<?php echo esc_attr($arrow['top'] ?? '100%'); ?>" placeholder="100%" />
                </div>
                <div class="ts-dropdown-arrow-fields" <?php echo !$arrow_active ? 'style="display:none;"' : ''; ?>>
                    <label class="ts-field-label"><?php _e('Left', 'theme-styles'); ?></label>
                    <input type="text" class="ts-field-input" data-field="dropdown.arrow.left" value="<?php echo esc_attr($arrow['left'] ?? ''); ?>" placeholder="" />
                </div>
            </div>
        </div>

        <!-- Dropdown -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Dropdown', 'theme-styles'); ?></h3>
            <div class="ts-field-row ts-field-row-4" style="margin-bottom:16px;">
                <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown.bg_color" value="<?php echo esc_attr($ddd['bg_color'] ?? '#ffffff'); ?>" /></div>
                <div><label class="ts-field-label">Width</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown.width" value="<?php echo esc_attr($ddd['width'] ?? '240px'); ?>" placeholder="240px" /></div>
                <div><label class="ts-field-label">Margin</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown.margin" value="<?php echo esc_attr($ddd['margin'] ?? '0'); ?>" placeholder="0" /></div>
                <div><label class="ts-field-label">Padding</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown.padding" value="<?php echo esc_attr($ddd['padding'] ?? '20px 15px'); ?>" placeholder="20px 15px" /></div>
            </div>
            <div class="ts-field-row ts-field-row-4">
                <div>
                    <label class="ts-field-label">Align Vertical</label>
                    <select class="ts-field-select" data-field="dropdown.dropdown.align_vr">
                        <option value="start"  <?php selected($ddd['align_vr'] ?? 'center', 'start'); ?>>Top</option>
                        <option value="center" <?php selected($ddd['align_vr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($ddd['align_vr'] ?? 'center', 'end'); ?>>Bottom</option>
                    </select>
                </div>
                <div><label class="ts-field-label">Top</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown.top" value="<?php echo esc_attr($ddd['top'] ?? 'calc(100% + 15px)'); ?>" placeholder="calc(100% + 15px)" /></div>
                <div>
                    <label class="ts-field-label">Border</label>
                    <div class="ts-border-builder">
                        <input type="text" class="ts-field-input" data-field="dropdown.dropdown.border_width" value="<?php echo esc_attr($ddd['border_width'] ?? '0px'); ?>" placeholder="0px" />
                        <select class="ts-field-select" data-field="dropdown.dropdown.border_style">
                            <option value="none"   <?php selected($ddd['border_style'] ?? 'none', 'none'); ?>>None</option>
                            <option value="solid"  <?php selected($ddd['border_style'] ?? 'none', 'solid'); ?>>Solid</option>
                            <option value="dashed" <?php selected($ddd['border_style'] ?? 'none', 'dashed'); ?>>Dashed</option>
                        </select>
                        <input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown.border_color" value="<?php echo esc_attr($ddd['border_color'] ?? ''); ?>" />
                    </div>
                </div>
                <div><label class="ts-field-label">Border Radius</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown.border_radius" value="<?php echo esc_attr($ddd['border_radius'] ?? '22px'); ?>" placeholder="22px" /></div>
            </div>
        </div>

        <!-- Dropdown Item -->
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Dropdown Item', 'theme-styles'); ?></h3>

            <!-- Font row -->
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
                <div><?php theme_styles_render_font_family_field('dropdown.dropdown_item.font_family', $ddi['font_family'] ?? 'inherit'); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown_item.font_size" value="<?php echo esc_attr($ddi['font_size'] ?? '14px'); ?>" placeholder="14px" /></div>
                <div>
                    <label class="ts-field-label">Transform</label>
                    <select class="ts-field-select" data-field="dropdown.dropdown_item.text_transform">
                        <option value="none"      <?php selected($ddi['text_transform'] ?? 'none', 'none'); ?>>None</option>
                        <option value="uppercase" <?php selected($ddi['text_transform'] ?? 'none', 'uppercase'); ?>>Upper</option>
                        <option value="lowercase" <?php selected($ddi['text_transform'] ?? 'none', 'lowercase'); ?>>Lower</option>
                        <option value="capitalize"<?php selected($ddi['text_transform'] ?? 'none', 'capitalize'); ?>>Cap</option>
                    </select>
                </div>
                <div><label class="ts-field-label">Padding</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown_item.padding" value="<?php echo esc_attr($ddi['padding'] ?? '8px 12px'); ?>" placeholder="8px 12px" /></div>
            </div>

            <!-- States: Default + Hover -->
            <div class="ts-states-grid">
                <!-- Default -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label">Font Weight</label>
                            <select class="ts-field-select" data-field="dropdown.dropdown_item.font_weight">
                                <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($ddi['font_weight'] ?? '400', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.color" value="<?php echo esc_attr($ddi['color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.bg_color" value="<?php echo esc_attr($ddi['bg_color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field">
                            <label class="ts-field-label">Border</label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input" data-field="dropdown.dropdown_item.border_width" value="<?php echo esc_attr($ddi['border_width'] ?? '0px'); ?>" placeholder="0px" />
                                <select class="ts-field-select" data-field="dropdown.dropdown_item.border_style">
                                    <option value="none"   <?php selected($ddi['border_style'] ?? 'none', 'none'); ?>>None</option>
                                    <option value="solid"  <?php selected($ddi['border_style'] ?? 'none', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($ddi['border_style'] ?? 'none', 'dashed'); ?>>Dashed</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.border_color" value="<?php echo esc_attr($ddi['border_color'] ?? ''); ?>" />
                            </div>
                        </div>
                        <div class="ts-state-field"><label class="ts-field-label">Border Radius</label><input type="text" class="ts-field-input" data-field="dropdown.dropdown_item.border_radius" value="<?php echo esc_attr($ddi['border_radius'] ?? '16px'); ?>" placeholder="16px" /></div>
                    </div>
                </div>

                <!-- Hover -->
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Hover</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field">
                            <label class="ts-field-label">Font Weight</label>
                            <select class="ts-field-select" data-field="dropdown.dropdown_item.font_weight_hover">
                                <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($ddi['font_weight_hover'] ?? '700', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.color_hover" value="<?php echo esc_attr($ddi['color_hover'] ?? ''); ?>" /></div>
                        <div class="ts-state-field"><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.bg_color_hover" value="<?php echo esc_attr($ddi['bg_color_hover'] ?? '#f8f9fa'); ?>" /></div>
                        <div class="ts-state-field">
                            <label class="ts-field-label">Border</label>
                            <div class="ts-border-builder">
                                <input type="text" class="ts-field-input" data-field="dropdown.dropdown_item.border_width_hover" value="<?php echo esc_attr($ddi['border_width_hover'] ?? '0px'); ?>" placeholder="0px" />
                                <select class="ts-field-select" data-field="dropdown.dropdown_item.border_style_hover">
                                    <option value="none"   <?php selected($ddi['border_style_hover'] ?? 'none', 'none'); ?>>None</option>
                                    <option value="solid"  <?php selected($ddi['border_style_hover'] ?? 'none', 'solid'); ?>>Solid</option>
                                    <option value="dashed" <?php selected($ddi['border_style_hover'] ?? 'none', 'dashed'); ?>>Dashed</option>
                                </select>
                                <input type="text" class="ts-field-input ts-color-input" data-field="dropdown.dropdown_item.border_color_hover" value="<?php echo esc_attr($ddi['border_color_hover'] ?? ''); ?>" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /dropdown tab -->

    <!-- ═══ LOGO TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="logo">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Logo', 'theme-styles'); ?></h3>
            <div class="ts-field-row ts-field-row-3" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label">Align Horizontal</label>
                    <select class="ts-field-select" data-field="logo.align_hr">
                        <option value="start"  <?php selected($logo['align_hr'] ?? 'center', 'start'); ?>>Left</option>
                        <option value="center" <?php selected($logo['align_hr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($logo['align_hr'] ?? 'center', 'end'); ?>>Right</option>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label">Align Vertical</label>
                    <select class="ts-field-select" data-field="logo.align_vr">
                        <option value="start"  <?php selected($logo['align_vr'] ?? 'center', 'start'); ?>>Top</option>
                        <option value="center" <?php selected($logo['align_vr'] ?? 'center', 'center'); ?>>Center</option>
                        <option value="end"    <?php selected($logo['align_vr'] ?? 'center', 'end'); ?>>Bottom</option>
                    </select>
                </div>
            </div>

            <!-- Default / Affix state boxes -->
            <div class="ts-states-grid ts-states-stacked">
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="logo.color" value="<?php echo esc_attr($logo['color'] ?? ''); ?>" /></div>
                        <div class="ts-state-field">
                            <label class="ts-field-label">Padding (per breakpoint)</label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="logo.padding.<?php echo $bp; ?>" value="<?php echo esc_attr($logo['padding'][$bp] ?? ''); ?>" placeholder="20px 0" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ts-state-box">
                    <div class="ts-state-box-header"><h5 class="ts-state-box-title">Affix</h5></div>
                    <div class="ts-state-box-body">
                        <div class="ts-state-field"><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="logo.color_affix" value="<?php echo esc_attr($logo['color_affix'] ?? ''); ?>" /></div>
                        <div class="ts-state-field">
                            <label class="ts-field-label">Padding (per breakpoint)</label>
                            <div class="ts-responsive-grid" style="margin-top:8px;">
                                <?php foreach ($bps as $bp): ?>
                                <div class="ts-responsive-column">
                                    <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                    <input type="text" class="ts-field-input" data-field="logo.padding_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($logo['padding_affix'][$bp] ?? ''); ?>" placeholder="20px 0" />
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /logo tab -->

    <!-- ═══ THEMES TAB ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="themes">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Header Themes', 'theme-styles'); ?></h3>
            <p class="ts-section-description"><?php _e('CSS class-based overrides. Each theme applies when the given class is on the body/header element (e.g. has-hero).', 'theme-styles'); ?></p>

            <div class="ts-repeater-container">
                <div class="ts-repeater-list" id="ts-header-themes-list">
                    <?php $themes = $data['themes'] ?? []; foreach ($themes as $ti => $theme): ?>
                    <div class="ts-repeater-item ts-header-theme-item" data-index="<?php echo $ti; ?>">
                        <div class="ts-repeater-handle"><span class="dashicons dashicons-menu"></span></div>
                        <div class="ts-header-theme-fields">

                            <!-- Top row: Class + Z-Index -->
                            <div class="ts-field-row" style="margin-bottom:16px;">
                                <div>
                                    <label class="ts-field-label"><?php _e('CSS Class', 'theme-styles'); ?> <span style="color:red">*</span></label>
                                    <input type="text" class="ts-field-input" data-field="themes.<?php echo $ti; ?>.class" value="<?php echo esc_attr($theme['class'] ?? ''); ?>" placeholder="has-hero" />
                                </div>
                                <div>
                                    <label class="ts-field-label"><?php _e('Z-Index', 'theme-styles'); ?></label>
                                    <input type="number" class="ts-field-input" data-field="themes.<?php echo $ti; ?>.z_index" value="<?php echo esc_attr($theme['z_index'] ?? '5'); ?>" placeholder="5" />
                                </div>
                            </div>

                            <!-- Default state -->
                            <div class="ts-theme-state-label"><?php _e('Default', 'theme-styles'); ?></div>
                            <div class="ts-field-row ts-field-row-4" style="margin-bottom:12px;">
                                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.default.color" value="<?php echo esc_attr($theme['default']['color'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.default.color_active" value="<?php echo esc_attr($theme['default']['color_active'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.default.bg_color" value="<?php echo esc_attr($theme['default']['bg_color'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Logo</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.default.logo" value="<?php echo esc_attr($theme['default']['logo'] ?? ''); ?>" /></div>
                            </div>

                            <!-- Affix state -->
                            <div class="ts-theme-state-label"><?php _e('Affix', 'theme-styles'); ?></div>
                            <div class="ts-field-row ts-field-row-4">
                                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.affix.color" value="<?php echo esc_attr($theme['affix']['color'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.affix.color_active" value="<?php echo esc_attr($theme['affix']['color_active'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.affix.bg_color" value="<?php echo esc_attr($theme['affix']['bg_color'] ?? ''); ?>" /></div>
                                <div><label class="ts-field-label">Logo</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.<?php echo $ti; ?>.affix.logo" value="<?php echo esc_attr($theme['affix']['logo'] ?? ''); ?>" /></div>
                                <div>
                                    <label class="ts-field-label">Reverse Button</label>
                                    <?php theme_styles_render_switch("themes.{$ti}.affix.btn_reverse", !empty($theme['affix']['btn_reverse'])); ?>
                                </div>
                            </div>

                        </div>
                        <button type="button" class="ts-repeater-remove"><span class="dashicons dashicons-no-alt"></span></button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="ts-repeater-empty <?php echo !empty($themes) ? 'hidden' : ''; ?>">
                    <div class="ts-repeater-empty-icon"><span class="dashicons dashicons-admin-appearance"></span></div>
                    <p class="ts-repeater-empty-text">No header themes yet</p>
                </div>

                <div class="ts-repeater-footer">
                    <button type="button" class="ts-repeater-add-btn" id="ts-add-header-theme">
                        <span class="dashicons dashicons-plus-alt"></span> Add New Theme
                    </button>
                </div>
            </div>
        </div>
    </div><!-- /themes tab -->

    <!-- ═══ HEADER TOOLS TABS ═══ -->
    <?php
    // Header Tools data
    $ht   = $data['header_tools'] ?? [];
    $htg  = $ht['header_tools'] ?? [];
    $hts  = $ht['social']       ?? [];
    $hti  = $ht['icons']        ?? [];
    $htl  = $ht['link']         ?? [];
    $htb  = $ht['button']       ?? [];
    $htla = $ht['language']     ?? [];
    $htt  = $ht['toggler']      ?? [];
    $htc  = $ht['counter']      ?? [];
    $ht_height_same = !empty($htg['height_header']);
    ?>

    <!-- ═══ HEADER TOOLS TAB (inner tabs) ═══ -->
    <div class="ts-module-tab-content" data-module-tab-content="header_tools">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Header Tools', 'theme-styles'); ?></h3>
        </div>

        <!-- Inner tabs -->
        <div class="ts-tabs" style="margin-bottom:0;">
            <div class="ts-tabs-nav">
                <button type="button" class="ts-tab-btn active" data-tab="ht_general">General</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_social">Social Icons</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_icons">Icons</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_link">Link</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_button">Button</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_language">Language</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_toggler">Nav Toggler</button>
                <button type="button" class="ts-tab-btn" data-tab="ht_counter">Counter</button>
            </div>

        <!-- ht_general -->
    <div class="ts-tab-content active" data-tab-content="ht_general">
        <div class="ts-section">
            <h3 class="ts-section-title"><?php _e('Header Tools - General', 'theme-styles'); ?></h3>
            <div class="ts-field-row" style="margin-bottom:20px;">
                <div>
                    <label class="ts-field-label"><?php _e('Height same as Header', 'theme-styles'); ?></label>
                    <?php theme_styles_render_switch('header_tools.header_tools.height_header', $ht_height_same); ?>
                </div>
            </div>
            <div id="ts-ht-height-fields" <?php echo $ht_height_same ? 'style="display:none;"' : ''; ?>>
                <div class="ts-states-grid ts-states-stacked">
                    <div class="ts-state-box">
                        <div class="ts-state-box-header"><h5 class="ts-state-box-title">Default</h5></div>
                        <div class="ts-state-box-body">
                            <div class="ts-state-field">
                                <label class="ts-field-label"><?php _e('Height (per breakpoint)', 'theme-styles'); ?></label>
                                <div class="ts-responsive-grid" style="margin-top:8px;">
                                    <?php foreach ($bps as $bp): ?>
                                    <div class="ts-responsive-column">
                                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                        <input type="text" class="ts-field-input" data-field="header_tools.header_tools.height.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['height'][$bp] ?? ''); ?>" placeholder="80px" />
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
                                <label class="ts-field-label"><?php _e('Height Affix (per breakpoint)', 'theme-styles'); ?></label>
                                <div class="ts-responsive-grid" style="margin-top:8px;">
                                    <?php foreach ($bps as $bp): ?>
                                    <div class="ts-responsive-column">
                                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                                        <input type="text" class="ts-field-input" data-field="header_tools.header_tools.height_affix.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['height_affix'][$bp] ?? ''); ?>" placeholder="60px" />
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <label class="ts-field-label"><?php _e('Gap (per breakpoint)', 'theme-styles'); ?></label>
                <div class="ts-responsive-grid" style="margin-top:8px;">
                    <?php foreach ($bps as $bp): ?>
                    <div class="ts-responsive-column">
                        <div class="ts-responsive-label"><span class="ts-bp-badge ts-bp-badge-<?php echo $bp; ?>"><?php echo $bp; ?></span></div>
                        <input type="text" class="ts-field-input" data-field="header_tools.header_tools.gap.<?php echo $bp; ?>" value="<?php echo esc_attr($htg['gap'][$bp] ?? ''); ?>" placeholder="20px" />
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ht_social -->
    <div class="ts-tab-content" data-tab-content="ht_social">
        <div class="ts-section">
            <h3 class="ts-section-title">Social Icons</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('header_tools.social.font_family', $hts['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="header_tools.social.font_size" value="<?php echo esc_attr($hts['font_size'] ?? '24px'); ?>" placeholder="24px" /></div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.social.color" value="<?php echo esc_attr($hts['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.social.color_hover" value="<?php echo esc_attr($hts['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Gap</label><input type="text" class="ts-field-input" data-field="header_tools.social.gap" value="<?php echo esc_attr($hts['gap'] ?? '10px'); ?>" placeholder="10px" /></div>
            </div>
        </div>
    </div>

    <!-- ht_icons -->
    <div class="ts-tab-content" data-tab-content="ht_icons">
        <div class="ts-section">
            <h3 class="ts-section-title">Icons</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('header_tools.icons.font_family', $hti['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="header_tools.icons.font_size" value="<?php echo esc_attr($hti['font_size'] ?? '20px'); ?>" placeholder="20px" /></div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.icons.color" value="<?php echo esc_attr($hti['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.icons.color_hover" value="<?php echo esc_attr($hti['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Dot Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.icons.dot_color" value="<?php echo esc_attr($hti['dot_color'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ht_link -->
    <div class="ts-tab-content" data-tab-content="ht_link">
        <div class="ts-section">
            <h3 class="ts-section-title">Link</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('header_tools.link.font_family', $htl['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="header_tools.link.font_size" value="<?php echo esc_attr($htl['font_size'] ?? '16px'); ?>" placeholder="16px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="header_tools.link.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htl['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.link.color" value="<?php echo esc_attr($htl['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.link.color_hover" value="<?php echo esc_attr($htl['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.link.color_active" value="<?php echo esc_attr($htl['color_active'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ht_button -->
    <div class="ts-tab-content" data-tab-content="ht_button">
        <div class="ts-section">
            <h3 class="ts-section-title">Button</h3>
            <div class="ts-field-row ts-field-row-3">
                <div><?php theme_styles_render_font_family_field('header_tools.button.font_family', $htb['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="header_tools.button.font_size" value="<?php echo esc_attr($htb['font_size'] ?? '14px'); ?>" placeholder="14px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="header_tools.button.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htb['font_weight'] ?? '500', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ht_language -->
    <div class="ts-tab-content" data-tab-content="ht_language">
        <div class="ts-section">
            <h3 class="ts-section-title">Language</h3>
            <div class="ts-field-row ts-field-row-4">
                <div><?php theme_styles_render_font_family_field('header_tools.language.font_family', $htla['font_family'] ?? 'inherit', ['label' => 'Font Family']); ?></div>
                <div><label class="ts-field-label">Font Size</label><input type="text" class="ts-field-input" data-field="header_tools.language.font_size" value="<?php echo esc_attr($htla['font_size'] ?? '16px'); ?>" placeholder="16px" /></div>
                <div><label class="ts-field-label">Font Weight</label>
                    <select class="ts-field-select" data-field="header_tools.language.font_weight">
                        <?php foreach ($weights as $w): ?><option value="<?php echo $w; ?>" <?php selected($htla['font_weight'] ?? '400', $w); ?>><?php echo $w; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.language.color" value="<?php echo esc_attr($htla['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.language.color_hover" value="<?php echo esc_attr($htla['color_hover'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.language.color_active" value="<?php echo esc_attr($htla['color_active'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ht_toggler -->
    <div class="ts-tab-content" data-tab-content="ht_toggler">
        <div class="ts-section">
            <h3 class="ts-section-title">Nav Toggler</h3>
            <div class="ts-field-row">
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.toggler.color" value="<?php echo esc_attr($htt['color'] ?? ''); ?>" /></div>
                <div><label class="ts-field-label">Color Hover</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.toggler.color_hover" value="<?php echo esc_attr($htt['color_hover'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

    <!-- ht_counter -->
    <div class="ts-tab-content" data-tab-content="ht_counter">
        <div class="ts-section">
            <h3 class="ts-section-title">Counter Badge</h3>
            <div class="ts-field-row">
                <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.counter.color" value="<?php echo esc_attr($htc['color'] ?? '#ffffff'); ?>" /></div>
                <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="header_tools.counter.bg_color" value="<?php echo esc_attr($htc['bg_color'] ?? ''); ?>" /></div>
            </div>
        </div>
    </div>

        </div><!-- /ts-tabs -->
    </div><!-- /header_tools tab -->

</div><!-- /ts-module-header -->


