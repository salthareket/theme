/**
 * Pagination Module JavaScript
 * Live preview functionality
 * 
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    const ThemeStylesPagination = {
        
        $preview: null,
        
        /**
         * Initialize
         */
        init() {
            this.$preview = $('#ts-pagination-demo');
            if (!this.$preview.length) return;
            
            this.bindEvents();
            this.updatePreview();
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Listen to all pagination field changes
            $('#module-pagination').on('change input', '[data-field]', () => {
                this.updatePreview();
            });
        },
        
        /**
         * Update preview
         */
        updatePreview() {
            const data = this.collectData();
            
            // General
            const align = data.align || 'center';
            const gap = data.gap || '4px';
            
            // Apply alignment to nav (parent)
            this.$preview.attr('data-align', align);
            
            // Item
            const itemFontFamily = data.item_font_family || 'inherit';
            const itemFontSize = data.item_font_size || '14px';
            const itemFontWeight = data.item_font_weight || '400';
            const itemFontWeightHover = data.item_font_weight_hover || '400';
            const itemFontWeightActive = data.item_font_weight_active || '600';
            const itemColor = data.item_color || '#007bff';
            const itemColorHover = data.item_color_hover || '#0056b3';
            const itemColorActive = data.item_color_active || '#ffffff';
            const itemBg = data.item_bg || 'transparent';
            const itemBgHover = data.item_bg_hover || '#e9ecef';
            const itemBgActive = data.item_bg_active || '#007bff';
            
            // Build border strings from border builder
            const itemBorder = `${data.item_border_width || '1px'} ${data.item_border_style || 'solid'} ${data.item_border_color || '#dee2e6'}`;
            const itemBorderHover = `${data.item_border_width_hover || '1px'} ${data.item_border_style_hover || 'solid'} ${data.item_border_color_hover || '#dee2e6'}`;
            const itemBorderActive = `${data.item_border_width_active || '1px'} ${data.item_border_style_active || 'solid'} ${data.item_border_color_active || '#007bff'}`;
            
            const itemBorderRadius = data.item_border_radius || '4px';
            const itemPadding = data.item_padding || '8px 12px';
            
            // Nav
            const navFontFamily = data.nav_font_family || 'inherit';
            const navFontSize = data.nav_font_size || '14px';
            const navFontWeight = data.nav_font_weight || '400';
            const navColor = data.nav_color || '#007bff';
            const navColorHover = data.nav_color_hover || '#0056b3';
            const navColorDisabled = data.nav_color_disabled || '#6c757d';
            const navBg = data.nav_bg || 'transparent';
            const navBgHover = data.nav_bg_hover || '#e9ecef';
            const navBgDisabled = data.nav_bg_disabled || 'transparent';
            
            // Build nav border strings from border builder
            const navBorder = `${data.nav_border_width || '0px'} ${data.nav_border_style || 'none'} ${data.nav_border_color || 'transparent'}`;
            const navBorderHover = `${data.nav_border_width_hover || '0px'} ${data.nav_border_style_hover || 'none'} ${data.nav_border_color_hover || 'transparent'}`;
            const navBorderDisabled = `${data.nav_border_width_disabled || '0px'} ${data.nav_border_style_disabled || 'none'} ${data.nav_border_color_disabled || 'transparent'}`;
            
            const navBorderRadius = data.nav_border_radius || '4px';
            
            // Icon preview — AJAX SVG fetch (breadcrumb ile aynı yöntem)
            const navPrevIconRaw = data.prev_icon || 'regular:angle-left';
            const navNextIconRaw = data.next_icon || 'regular:angle-right';

            const self = this;
            const navColor_ref = navColor;

            // Her iki icon için SVG fetch yap, sonra preview'ı uygula
            const applyPreview = (prevSvgUrl, nextSvgUrl) => {
                const iconCss = (svgUrl, selector, margin) => svgUrl ? `
                    ${selector} {
                        display: inline-block;
                        width: 0.85em;
                        height: 0.85em;
                        flex-shrink: 0;
                        ${margin}
                        background-color: currentColor;
                        -webkit-mask-image: ${svgUrl};
                        mask-image: ${svgUrl};
                        -webkit-mask-repeat: no-repeat;
                        mask-repeat: no-repeat;
                        -webkit-mask-position: center;
                        mask-position: center;
                        -webkit-mask-size: contain;
                        mask-size: contain;
                        vertical-align: middle;
                    }` : '';

                const css = `
                    <style id="ts-pagination-preview-style">
                        #ts-pagination-demo .pagination { gap: ${gap}; }
                        #ts-pagination-demo .page-item:not(.page-item-prev):not(.page-item-next) .page-link {
                            font-family: ${itemFontFamily}; font-size: ${itemFontSize}; font-weight: ${itemFontWeight};
                            color: ${itemColor}; background: ${itemBg}; border: ${itemBorder};
                            border-radius: ${itemBorderRadius}; padding: ${itemPadding};
                        }
                        #ts-pagination-demo .page-item:not(.page-item-prev):not(.page-item-next):hover .page-link {
                            font-weight: ${itemFontWeightHover}; color: ${itemColorHover};
                            background: ${itemBgHover}; border: ${itemBorderHover};
                        }
                        #ts-pagination-demo .page-item.active .page-link {
                            font-weight: ${itemFontWeightActive} !important; color: ${itemColorActive} !important;
                            background: ${itemBgActive} !important; border: ${itemBorderActive} !important;
                        }
                        #ts-pagination-demo .page-item-prev .page-link,
                        #ts-pagination-demo .page-item-next .page-link {
                            font-family: ${navFontFamily}; font-size: ${navFontSize}; font-weight: ${navFontWeight};
                            color: ${navColor}; background: ${navBg}; border: ${navBorder};
                            border-radius: ${navBorderRadius}; padding: ${itemPadding};
                        }
                        #ts-pagination-demo .page-item-prev:hover .page-link,
                        #ts-pagination-demo .page-item-next:hover .page-link {
                            color: ${navColorHover}; background: ${navBgHover}; border: ${navBorderHover};
                        }
                        #ts-pagination-demo .page-item.disabled .page-link { color: ${navColorDisabled}; }
                        ${iconCss(prevSvgUrl, '#ts-pagination-demo .pagination-icon-prev', 'margin-right: 6px;')}
                        ${iconCss(nextSvgUrl, '#ts-pagination-demo .pagination-icon-next', 'margin-left: 6px;')}
                    </style>
                `;
                $('#ts-pagination-preview-style').remove();
                $('head').append(css);
            };

            // Her iki ikon için AJAX SVG çek
            const fetchSvg = (raw, cb) => {
                if (!raw || raw.indexOf(':') === -1) { cb(''); return; }
                const [style, name] = raw.split(':');
                const cfg = window.shIconPickerConfig || {};
                $.post(cfg.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php', {
                    action: 'sh_fa_icon_svg', style, name, nonce: cfg.nonce || ''
                }, function(res) {
                    if (res && res.success && res.data) {
                        let svg = res.data;
                        if (svg.indexOf('xmlns=') === -1) {
                            svg = svg.replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ');
                        }
                        cb('url("data:image/svg+xml,' + encodeURIComponent(svg) + '")');
                    } else { cb(''); }
                }).fail(() => cb(''));
            };

            // Paralel fetch — ikisi de hazır olunca preview'ı uygula
            let prevSvgUrl = '', nextSvgUrl = '', fetched = 0;
            const tryApply = () => { if (++fetched === 2) applyPreview(prevSvgUrl, nextSvgUrl); };
            fetchSvg(navPrevIconRaw, url => { prevSvgUrl = url; tryApply(); });
            fetchSvg(navNextIconRaw, url => { nextSvgUrl = url; tryApply(); });
        },
        
        /**
         * Collect data from fields
         */
        collectData() {
            const data = {};
            
            $('#module-pagination [data-field]').each(function() {
                const $field = $(this);
                const fieldName = $field.data('field');
                let value = $field.val();
                
                if ($field.is('select')) {
                    value = $field.find('option:selected').val();
                }
                
                data[fieldName] = value;
            });
            
            return data;
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        ThemeStylesPagination.init();
    });
    
})(jQuery);
