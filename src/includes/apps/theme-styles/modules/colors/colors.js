/**
 * Colors Module JavaScript
 * Handles gradient picker, repeater, and PRO features
 * 
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    const { createElement, render, useState } = wp.element;
    const { GradientPicker } = wp.components;
    
    const defaultGradient = 'linear-gradient(90deg, #000000 0%, #ffffff 100%)';
    const defaultGradients = [
        { name: 'Sunset', gradient: 'linear-gradient(135deg, #ff7e5f 0%, #feb47b 100%)' },
        { name: 'Ocean', gradient: 'linear-gradient(135deg, #2E3192 0%, #1BFFFF 100%)' },
        { name: 'Midnight', gradient: 'linear-gradient(135deg, #000000 0%, #434343 100%)' },
        { name: 'Purple', gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
        { name: 'Fire', gradient: 'linear-gradient(135deg, #f12711 0%, #f5af19 100%)' }
    ];
    
    const ThemeStylesColors = {
        
        /**
         * Initialize
         */
        init() {
            this.initGradientPickers();
            this.bindEvents();
            this.initSortable();
            this.initAccessibilityChecker();
        },
        
        /**
         * Initialize sortable
         */
        initSortable() {
            $('.ts-repeater-list').sortable({
                handle: '.ts-repeater-handle',
                placeholder: 'ts-repeater-placeholder',
                axis: 'y',
                opacity: 0.8,
                cursor: 'move',
                tolerance: 'pointer',
                update: (event, ui) => {
                    // Reindex after sort
                    this.reindexRepeater($(event.target));
                }
            });
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Add custom color
            $(document).on('click', '#ts-add-custom-color', () => this.addCustomColor());
            
            // Remove item
            $(document).on('click', '.ts-repeater-item .ts-repeater-remove', function() {
                const $item = $(this).closest('.ts-repeater-item');
                const $list = $item.closest('.ts-repeater-list');
                const $container = $list.closest('.ts-repeater-container');
                
                $item.fadeOut(200, function() {
                    $(this).remove();
                    
                    // Show empty state if no items
                    if ($list.find('.ts-repeater-item').length === 0) {
                        $container.find('.ts-repeater-empty').removeClass('hidden');
                    }
                    
                    // Reindex items
                    ThemeStylesColors.reindexRepeater($list);
                    
                    // Refresh color palettes
                    ThemeStylesAdmin.refreshColorPalettes();
                });
            });
            
            // Add custom gradient
            $(document).on('click', '#ts-add-custom-gradient', () => this.addCustomGradient());
            
            // Generate shades
            $(document).on('click', '.ts-generate-shades', function() {
                const colorKey = $(this).data('color-key');
                const $input = $(`.ts-color-input[data-field="${colorKey}"]`);
                const color = $input.val();
                ThemeStylesColors.generateShades(colorKey, color);
            });
            
            // Copy CSS variable
            $(document).on('click', '.ts-copy-var', function() {
                const varName = $(this).data('var');
                ThemeStylesColors.copyToClipboard(varName);
            });
            
            // Gradient presets
            $(document).on('click', '.ts-preset-btn', function() {
                const gradient = $(this).data('gradient');
                const name = $(this).find('.ts-preset-name').text();
                ThemeStylesColors.addGradientFromPreset(name, gradient);
            });
            
            // Color change - update accessibility
            $(document).on('change', '.ts-color-input', function() {
                const color = $(this).val();
                const $field = $(this).closest('.ts-color-field-pro');
                if ($field.length) {
                    ThemeStylesColors.updateAccessibility($field, color);
                }
                // Refresh palettes when primary colors change
                if ($(this).closest('#module-colors').length) {
                    setTimeout(() => ThemeStylesAdmin.refreshColorPalettes(), 100);
                }
            });
        },
        
        /**
         * Add custom color
         */
        addCustomColor() {
            const $list = $('#ts-custom-colors');
            const $container = $list.closest('.ts-repeater-container');
            const index = $list.find('.ts-repeater-item').length;
            
            // Hide empty state
            $container.find('.ts-repeater-empty').addClass('hidden');
            
            const html = `
                <div class="ts-repeater-item" data-index="${index}" style="display: none;">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields">
                        <div class="ts-color-field">
                            <label class="ts-field-label">Name</label>
                            <input type="text" class="ts-field-input" data-field="custom.${index}.title" placeholder="accent" />
                        </div>
                        <div class="ts-color-field">
                            <label class="ts-field-label">Color</label>
                            <input type="text" class="ts-field-input ts-color-input" data-field="custom.${index}.color" value="#000000" />
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            
            $list.append(html);
            
            // Fade in
            $list.find('.ts-repeater-item:last').fadeIn(300);
            
            // Init color picker for new field
            $list.find('.ts-repeater-item:last .ts-color-input').wpColorPicker({
                change: (event, ui) => {
                    const color = ui.color.toString();
                    $(event.target).val(color).trigger('change');
                }
            });
            
            // Refresh sortable
            if ($list.hasClass('ui-sortable')) {
                $list.sortable('refresh');
            }
        },
        
        /**
         * Add custom gradient
         */
        addCustomGradient() {
            const $list = $('#ts-custom-gradients');
            const $container = $list.closest('.ts-repeater-container');
            const index = $list.find('.ts-repeater-item').length;
            
            // Hide empty state
            $container.find('.ts-repeater-empty').addClass('hidden');
            
            const html = `
                <div class="ts-repeater-item" data-index="${index}" style="display: none;">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields">
                        <div class="ts-gradient-field">
                            <label class="ts-field-label">Name</label>
                            <input type="text" class="ts-field-input" data-field="custom_gradients.${index}.title" placeholder="sunset" />
                        </div>
                        <div class="ts-gradient-field ts-gradient-picker-wrapper">
                            <label class="ts-field-label">Gradient</label>
                            <input type="hidden" class="ts-gradient-input" data-field="custom_gradients.${index}.color" value="" />
                            <div class="ts-gradient-picker-control" data-index="${index}"></div>
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            
            $list.append(html);
            
            // Fade in
            $list.find('.ts-repeater-item:last').fadeIn(300);
            
            // Init gradient picker for new field
            this.initGradientPicker($list.find('.ts-repeater-item:last .ts-gradient-picker-control')[0]);
            
            // Refresh sortable
            if ($list.hasClass('ui-sortable')) {
                $list.sortable('refresh');
            }
        },
        
        /**
         * Reindex repeater items
         */
        reindexRepeater($list) {
            $list.find('.ts-repeater-item').each(function(index) {
                $(this).attr('data-index', index);
                
                // Update field names
                $(this).find('[data-field]').each(function() {
                    const field = $(this).data('field');
                    const parts = field.split('.');
                    parts[1] = index;
                    $(this).attr('data-field', parts.join('.'));
                });
            });
        },
        
        /**
         * Initialize gradient pickers
         */
        initGradientPickers() {
            document.querySelectorAll('.ts-gradient-picker-control').forEach(control => {
                this.initGradientPicker(control);
            });
        },
        
        /**
         * Initialize single gradient picker
         */
        initGradientPicker(control) {
            if (!control || control.dataset.init) return;
            
            control.dataset.init = '1';
            
            const $wrapper = $(control).closest('.ts-gradient-picker-wrapper');
            const $input = $wrapper.find('.ts-gradient-input');
            
            if (!$input.length) return;
            
            const Picker = () => {
                const [value, setValue] = useState($input.val() || defaultGradient);
                
                const update = (val) => {
                    setValue(val);
                    $input.val(val).trigger('change');
                };
                
                return createElement('div', { className: 'ts-gradient-picker-inner' },
                    createElement(GradientPicker, {
                        value: value,
                        onChange: update,
                        gradients: defaultGradients
                    })
                );
            };
            
            render(createElement(Picker), control);
        },
        
        /**
         * Generate color shades (Tailwind style)
         */
        generateShades(colorKey, baseColor) {
            const shades = this.calculateShades(baseColor);
            const $container = $('#ts-color-shades-container');
            const $section = $('#ts-color-shades-section');
            
            // Show section
            $section.show();
            
            // Define color order
            const colorOrder = ['primary', 'secondary', 'tertiary', 'quaternary'];
            
            // Create shades HTML
            const html = `
                <div class="ts-shades-group" data-color="${colorKey}" data-order="${colorOrder.indexOf(colorKey)}">
                    <div class="ts-shades-header">
                        <h4 class="ts-shades-title">${colorKey.charAt(0).toUpperCase() + colorKey.slice(1)} Shades</h4>
                        <button type="button" class="ts-shades-remove" data-color="${colorKey}" title="Remove Shades">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="ts-shades-grid">
                        ${Object.entries(shades).map(([shade, color]) => `
                            <div class="ts-shade-item">
                                <div class="ts-shade-preview" style="background: ${color}"></div>
                                <div class="ts-shade-info">
                                    <span class="ts-shade-label">${shade}</span>
                                    <span class="ts-shade-value">${color}</span>
                                    <button type="button" class="ts-shade-copy" data-color="${color}" title="Copy">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            // Remove existing shades for this color
            $container.find(`[data-color="${colorKey}"]`).remove();
            
            // Add new shades in correct order
            const $newGroup = $(html);
            const targetOrder = colorOrder.indexOf(colorKey);
            let inserted = false;
            
            $container.find('.ts-shades-group').each(function() {
                const order = parseInt($(this).data('order'));
                if (order > targetOrder) {
                    $(this).before($newGroup);
                    inserted = true;
                    return false;
                }
            });
            
            if (!inserted) {
                $container.append($newGroup);
            }
            
            // Bind events
            $('.ts-shade-copy').off('click').on('click', function() {
                const color = $(this).data('color');
                ThemeStylesColors.copyToClipboard(color);
            });
            
            $('.ts-shades-remove').off('click').on('click', function() {
                const color = $(this).data('color');
                $container.find(`[data-color="${color}"]`).fadeOut(300, function() {
                    $(this).remove();
                    // Hide section if no shades
                    if ($container.find('.ts-shades-group').length === 0) {
                        $section.hide();
                    }
                });
            });
        },
        
        /**
         * Calculate color shades
         */
        calculateShades(hex) {
            const rgb = this.hexToRgb(hex);
            const hsl = this.rgbToHsl(rgb.r, rgb.g, rgb.b);
            
            const shades = {};
            const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
            
            steps.forEach(step => {
                let lightness;
                if (step === 500) {
                    lightness = hsl.l;
                } else if (step < 500) {
                    // Lighter shades
                    const factor = (500 - step) / 500;
                    lightness = hsl.l + (100 - hsl.l) * factor;
                } else {
                    // Darker shades
                    const factor = (step - 500) / 500;
                    lightness = hsl.l * (1 - factor);
                }
                
                shades[step] = this.hslToHex(hsl.h, hsl.s, lightness);
            });
            
            return shades;
        },
        
        /**
         * Copy to clipboard
         */
        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show toast notification
                const $toast = $('<div class="ts-toast">Copied: ' + text + '</div>');
                $('body').append($toast);
                setTimeout(() => $toast.addClass('show'), 10);
                setTimeout(() => {
                    $toast.removeClass('show');
                    setTimeout(() => $toast.remove(), 300);
                }, 2000);
            });
        },
        
        /**
         * Add gradient from preset
         */
        addGradientFromPreset(name, gradient) {
            const $list = $('#ts-custom-gradients');
            const $container = $list.closest('.ts-repeater-container');
            const index = $list.find('.ts-repeater-item').length;
            
            // Hide empty state
            $container.find('.ts-repeater-empty').addClass('hidden');
            
            const html = `
                <div class="ts-repeater-item" data-index="${index}" style="display: none;">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields">
                        <div class="ts-gradient-field">
                            <label class="ts-field-label">Name</label>
                            <input type="text" class="ts-field-input" data-field="custom_gradients.${index}.title" value="${name}" />
                        </div>
                        <div class="ts-gradient-field ts-gradient-picker-wrapper">
                            <label class="ts-field-label">Gradient</label>
                            <input type="hidden" class="ts-gradient-input" data-field="custom_gradients.${index}.color" value="${gradient}" />
                            <div class="ts-gradient-picker-control" data-index="${index}"></div>
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            
            $list.append(html);
            $list.find('.ts-repeater-item:last').fadeIn(300);
            
            // Init gradient picker
            this.initGradientPicker($list.find('.ts-repeater-item:last .ts-gradient-picker-control')[0]);
            
            // Refresh sortable
            if ($list.hasClass('ui-sortable')) {
                $list.sortable('refresh');
            }
        },
        
        /**
         * Initialize accessibility checker
         */
        initAccessibilityChecker() {
            $('.ts-color-input').each(function() {
                const color = $(this).val();
                const $field = $(this).closest('.ts-color-field-pro');
                if ($field.length) {
                    ThemeStylesColors.updateAccessibility($field, color);
                }
            });
        },
        
        /**
         * Update accessibility info
         */
        updateAccessibility($field, color) {
            const $contrast = $field.find('.ts-color-contrast');
            const whiteContrast = this.getContrastRatio(color, '#ffffff');
            const blackContrast = this.getContrastRatio(color, '#000000');
            
            let badge = '';
            let title = '';
            let bestText = whiteContrast > blackContrast ? 'white' : 'black';
            let bestRatio = Math.max(whiteContrast, blackContrast);
            
            if (bestRatio >= 7) {
                badge = '<span class="ts-badge ts-badge-success">AAA</span>';
                title = `✓ AAA Standard (${bestRatio.toFixed(2)}:1)\n` +
                       `Perfect for all text sizes\n` +
                       `Best with ${bestText} text`;
            } else if (bestRatio >= 4.5) {
                badge = '<span class="ts-badge ts-badge-success">AA</span>';
                title = `✓ AA Standard (${bestRatio.toFixed(2)}:1)\n` +
                       `Good for normal text (< 18pt)\n` +
                       `Best with ${bestText} text`;
            } else if (bestRatio >= 3) {
                badge = '<span class="ts-badge ts-badge-warning">AA Large</span>';
                title = `⚠ AA Large Only (${bestRatio.toFixed(2)}:1)\n` +
                       `Only for large text (≥ 18pt or bold 14pt)\n` +
                       `Best with ${bestText} text`;
            } else {
                badge = '<span class="ts-badge ts-badge-error">Fail</span>';
                title = `✗ Insufficient Contrast (${bestRatio.toFixed(2)}:1)\n` +
                       `Does not meet WCAG standards\n` +
                       `White: ${whiteContrast.toFixed(2)}:1, Black: ${blackContrast.toFixed(2)}:1`;
            }
            
            $contrast.html(badge).attr('title', title);
        },
        
        /**
         * Get contrast ratio
         */
        getContrastRatio(color1, color2) {
            const lum1 = this.getLuminance(color1);
            const lum2 = this.getLuminance(color2);
            const brightest = Math.max(lum1, lum2);
            const darkest = Math.min(lum1, lum2);
            return (brightest + 0.05) / (darkest + 0.05);
        },
        
        /**
         * Get luminance
         */
        getLuminance(hex) {
            const rgb = this.hexToRgb(hex);
            const [r, g, b] = [rgb.r, rgb.g, rgb.b].map(val => {
                val = val / 255;
                return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        },
        
        /**
         * Color conversion utilities
         */
        hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : { r: 0, g: 0, b: 0 };
        },
        
        rgbToHsl(r, g, b) {
            r /= 255; g /= 255; b /= 255;
            const max = Math.max(r, g, b), min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;
            
            if (max === min) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                    case g: h = ((b - r) / d + 2) / 6; break;
                    case b: h = ((r - g) / d + 4) / 6; break;
                }
            }
            
            return { h: h * 360, s: s * 100, l: l * 100 };
        },
        
        hslToHex(h, s, l) {
            h /= 360; s /= 100; l /= 100;
            let r, g, b;
            
            if (s === 0) {
                r = g = b = l;
            } else {
                const hue2rgb = (p, q, t) => {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1/6) return p + (q - p) * 6 * t;
                    if (t < 1/2) return q;
                    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                    return p;
                };
                
                const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                const p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
            }
            
            const toHex = x => {
                const hex = Math.round(x * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            };
            
            return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        ThemeStylesColors.init();
        
        // Check and hide/show empty states on load
        $('.ts-repeater-list').each(function() {
            const $list = $(this);
            const $container = $list.closest('.ts-repeater-container');
            const $empty = $container.find('.ts-repeater-empty');
            
            if ($list.find('.ts-repeater-item').length > 0) {
                $empty.addClass('hidden');
            } else {
                $empty.removeClass('hidden');
            }
        });
    });
    
})(jQuery);
