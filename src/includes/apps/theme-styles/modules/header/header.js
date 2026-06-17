/**
 * Header Module JavaScript
 * @version 2.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesHeader = {
        init() {
            if (!$('#module-header').length) return;
            this.bindEvents();
            this.updatePreview();
        },

        updatePreview() {
            const $preview = $('#ts-header-preview');
            if (!$preview.length) return;

            const isAffix = $('#ts-hp-affix').hasClass('active');

            const getField = (field) => {
                const $el = $(`#module-header [data-field="${field}"]`);
                return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
            };

            const bp = this.getCurrentBreakpoint();

            // Background + Height
            const bgColor = isAffix ? (getField('header.bg_color_affix') || '#ffffff') : (getField('header.bg_color') || '#ffffff');
            const heightVal = isAffix ? getField(`header.height_affix.${bp}`) : getField(`header.height.${bp}`);
            const height = heightVal || '80px';
            $preview.css({ background: bgColor || '#ffffff', height });

            // Logo
            const logos = (typeof themeStyles !== 'undefined' && themeStyles.logos) ? themeStyles.logos : {};
            const logoUrl = isAffix
                ? (logos.acf_logo_affix || logos.acf_logo || logos.wp_logo || '')
                : (logos.acf_logo || logos.wp_logo || '');
            const $logoDefault = $preview.find('.ts-hp-logo-default');
            const $logoAffix   = $preview.find('.ts-hp-logo-affix');
            const $logoText    = $preview.find('.ts-hp-logo-text');

            if (logoUrl) {
                $preview.find('.ts-hp-logo-img').hide();
                (isAffix ? $logoAffix : $logoDefault).attr('src', logoUrl).show();
                $logoText.hide();
            } else {
                $preview.find('.ts-hp-logo-img').hide();
                $logoText.show();
            }

            // Nav
            const navColor        = getField('nav_item.color') || '#212529';
            const navColorHover   = getField('nav_item.color_hover') || navColor;
            const navColorActive  = getField('nav_item.color_active') || '#007bff';
            const navBg           = getField('nav_item.bg_color') || 'transparent';
            const navBgHover      = getField('nav_item.bg_color_hover') || 'transparent';
            const navWeight       = getField('nav_item.font_weight') || '400';
            const navWeightActive = getField('nav_item.font_weight_active') || '700';
            const transform       = getField('nav_item.text_transform') || 'none';
            const spacing         = getField('nav_item.letter_spacing') || '0';
            const fontFamily      = getField('nav_item.font_family') || 'inherit';
            const fontSize        = getField(`nav_item.font_size.${bp}`) || '16px';
            const padding         = getField(`nav_item.padding.${bp}`) || '8px 14px';

            $preview.find('.ts-hp-nav-item:not(.ts-hp-active), .ts-hp-has-dropdown').css({
                color: navColor, background: navBg, fontWeight: navWeight,
                textTransform: transform, letterSpacing: spacing, fontFamily, fontSize, padding
            });
            $preview.find('.ts-hp-nav-item.ts-hp-active').css({
                color: navColorActive, background: navBg, fontWeight: navWeightActive,
                textTransform: transform, letterSpacing: spacing, fontFamily, fontSize, padding
            });

            // Dropdown
            const ddBg     = getField('dropdown.dropdown.bg_color') || '#ffffff';
            const ddRadius = getField('dropdown.dropdown.border_radius') || '8px';
            const ddPad    = getField('dropdown.dropdown.padding') || '8px';
            const ddTop    = getField('dropdown.dropdown.top') || 'calc(100% + 8px)';
            $preview.find('.ts-hp-dropdown').css({ background: ddBg, borderRadius: ddRadius, padding: ddPad, top: ddTop });

            // Dropdown items
            const ddiColor    = getField('dropdown.dropdown_item.color') || '#212529';
            const ddiColorHov = getField('dropdown.dropdown_item.color_hover') || '#007bff';
            const ddiBg       = getField('dropdown.dropdown_item.bg_color') || 'transparent';
            const ddiBgHov    = getField('dropdown.dropdown_item.bg_color_hover') || '#f8f9fa';
            const ddiRadius   = getField('dropdown.dropdown_item.border_radius') || '4px';
            const ddiFont     = getField('dropdown.dropdown_item.font_family') || 'inherit';
            const ddiFontSize = getField('dropdown.dropdown_item.font_size') || '14px';
            const ddiPad      = getField('dropdown.dropdown_item.padding') || '8px 12px';
            const ddiWeight   = getField('dropdown.dropdown_item.font_weight') || '400';

            $preview.find('.ts-hp-dropdown-item').css({
                color: ddiColor, background: ddiBg, borderRadius: ddiRadius,
                fontFamily: ddiFont, fontSize: ddiFontSize, padding: ddiPad, fontWeight: ddiWeight
            });

            // Hover styles via injected CSS
            $('#ts-header-preview-style').remove();
            $('head').append(`<style id="ts-header-preview-style">
                #ts-header-preview .ts-hp-nav-item:hover,
                #ts-header-preview .ts-hp-has-dropdown:hover { color: ${navColorHover} !important; background: ${navBgHover} !important; }
                #ts-header-preview .ts-hp-dropdown-item:hover { color: ${ddiColorHov} !important; background: ${ddiBgHov} !important; }
            </style>`);
        },

        getCurrentBreakpoint() {
            const w = window.innerWidth;
            if (w <= 575) return 'xs';
            if (w <= 767) return 'sm';
            if (w <= 991) return 'md';
            if (w <= 1199) return 'lg';
            if (w <= 1399) return 'xl';
            if (w <= 1599) return 'xxl';
            return 'xxxl';
        },

        bindEvents() {
            // Module tab switching
            $(document).on('click', '#module-header .ts-module-tab-btn', function() {
                const tab = $(this).data('module-tab');
                $('#module-header .ts-module-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('#module-header .ts-module-tab-content').removeClass('active');
                $(`#module-header [data-module-tab-content="${tab}"]`).addClass('active');
            });

            // Inner tab switching (Header Tools sub-tabs)
            $(document).on('click', '#module-header .ts-tab-btn', function() {
                const tab = $(this).data('tab');
                const $container = $(this).closest('.ts-tabs');
                $container.find('.ts-tab-btn').removeClass('active');
                $(this).addClass('active');
                $container.find('.ts-tab-content').removeClass('active');
                $container.find(`[data-tab-content="${tab}"]`).addClass('active');
            });

            // Preview Default/Affix toggle
            $(document).on('click', '#ts-hp-default, #ts-hp-affix', function() {
                $('#ts-hp-default, #ts-hp-affix').removeClass('active');
                $(this).addClass('active');
                ThemeStylesHeader.updatePreview();
            });

            // Field changes → update preview
            $(document).on('change input', '#module-header [data-field]', () => {
                this.updatePreview();
            });

            // Navbar height same as header toggle
            $(document).on('change', '[data-field="navbar.height_header"]', function() {
                const val = $(this).closest('.ts-switch-wrapper').find('.ts-switch-value').val();
                if (val === '1') {
                    $('.ts-navbar-height-field').slideUp(200);
                } else {
                    $('.ts-navbar-height-field').slideDown(200);
                }
            });

            // Nav height same as header toggle
            $(document).on('change', '[data-field="nav.height_header"]', function() {
                const val = $(this).closest('.ts-switch-wrapper').find('.ts-switch-value').val();
                if (val === '1') {
                    $('.ts-nav-height-field').slideUp(200);
                } else {
                    $('.ts-nav-height-field').slideDown(200);
                }
            });

            // Header Tools height same as header toggle
            $(document).on('change', '[data-field="header_tools.header_tools.height_header"]', function() {
                const val = $(this).closest('.ts-switch-wrapper').find('.ts-switch-value').val();
                if (val === '1') {
                    $('#ts-ht-height-fields').slideUp(200);
                } else {
                    $('#ts-ht-height-fields').slideDown(200);
                }
            });
            $(document).on('change', '[data-field="dropdown.arrow.arrow"]', function() {
                const val = $(this).closest('.ts-switch-wrapper').find('.ts-switch-value').val();
                if (val === '1') {
                    $('.ts-dropdown-arrow-fields').slideDown(200);
                } else {
                    $('.ts-dropdown-arrow-fields').slideUp(200);
                }
            });

            // Add header theme
            $(document).on('click', '#ts-add-header-theme', () => this.addTheme());

            // Remove header theme
            $(document).on('click', '#ts-header-themes-list .ts-repeater-remove', function() {
                const $item = $(this).closest('.ts-repeater-item');
                $item.fadeOut(200, function() {
                    $(this).remove();
                    ThemeStylesHeader.reindex();
                    ThemeStylesHeader.checkEmpty();
                });
            });
        },

        addTheme() {
            const $list = $('#ts-header-themes-list');
            const index = $list.find('.ts-repeater-item').length;
            $('#module-header .ts-repeater-empty').addClass('hidden');

            const html = `
                <div class="ts-repeater-item ts-header-theme-item" data-index="${index}" style="display:none;">
                    <div class="ts-repeater-handle"><span class="dashicons dashicons-menu"></span></div>
                    <div class="ts-header-theme-fields">
                        <div class="ts-field-row" style="margin-bottom:16px;">
                            <div>
                                <label class="ts-field-label">CSS Class <span style="color:red">*</span></label>
                                <input type="text" class="ts-field-input" data-field="themes.${index}.class" value="" placeholder="has-hero" />
                            </div>
                            <div>
                                <label class="ts-field-label">Z-Index</label>
                                <input type="number" class="ts-field-input" data-field="themes.${index}.z_index" value="5" placeholder="5" />
                            </div>
                        </div>
                        <div class="ts-theme-state-label">Default</div>
                        <div class="ts-field-row ts-field-row-4" style="margin-bottom:12px;">
                            <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.default.color" value="" /></div>
                            <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.default.color_active" value="" /></div>
                            <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.default.bg_color" value="" /></div>
                            <div><label class="ts-field-label">Logo</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.default.logo" value="" /></div>
                        </div>
                        <div class="ts-theme-state-label">Affix</div>
                        <div class="ts-field-row ts-field-row-4">
                            <div><label class="ts-field-label">Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.affix.color" value="" /></div>
                            <div><label class="ts-field-label">Color Active</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.affix.color_active" value="" /></div>
                            <div><label class="ts-field-label">Bg Color</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.affix.bg_color" value="" /></div>
                            <div><label class="ts-field-label">Logo</label><input type="text" class="ts-field-input ts-color-input" data-field="themes.${index}.affix.logo" value="" /></div>
                            <div>
                                <label class="ts-field-label">Reverse Button</label>
                                <div class="ts-switch-wrapper">
                                    <label class="ts-switch">
                                        <input type="checkbox" class="ts-switch-input" data-field="themes.${index}.affix.btn_reverse" data-on="1" data-off="0" />
                                        <span class="ts-switch-slider"></span>
                                    </label>
                                    <input type="hidden" class="ts-switch-value" data-field="themes.${index}.affix.btn_reverse" value="0" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove"><span class="dashicons dashicons-no-alt"></span></button>
                </div>
            `;

            $list.append(html);
            const $new = $list.find('.ts-repeater-item:last');
            $new.fadeIn(300);

            // Init color pickers
            $new.find('.ts-color-input').wpColorPicker({
                change: (event, ui) => {
                    $(event.target).val(ui.color.toString()).trigger('change');
                }
            });
        },

        reindex() {
            $('#ts-header-themes-list .ts-repeater-item').each(function(i) {
                $(this).attr('data-index', i);
                $(this).find('[data-field]').each(function() {
                    const f = $(this).attr('data-field');
                    $(this).attr('data-field', f.replace(/themes\.\d+\./, `themes.${i}.`));
                });
            });
        },

        checkEmpty() {
            if ($('#ts-header-themes-list .ts-repeater-item').length === 0) {
                $('#module-header .ts-repeater-empty').removeClass('hidden');
            }
        }
    };

    $(document).ready(() => ThemeStylesHeader.init());

})(jQuery);
