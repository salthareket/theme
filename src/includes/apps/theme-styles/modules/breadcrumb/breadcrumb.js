/**
 * Breadcrumb Module JavaScript
 * Live preview functionality
 * 
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    const ThemeStylesBreadcrumb = {
        
        $preview: null,
        
        /**
         * Initialize
         */
        init() {
            this.$preview = $('#ts-breadcrumb-demo');
            if (!this.$preview.length) return;
            
            this.bindEvents();
            this.updatePreview();
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Listen to all breadcrumb field changes
            $('#module-breadcrumb').on('change input', '[data-field]', () => {
                this.updatePreview();
            });
        },
        
        /**
         * Update preview
         */
        updatePreview() {
            const data = this.collectData();
            
            // General
            const fontFamily = data.font_family || 'inherit';
            const fontSize = data.font_size || '14px';
            const textTransform = data.text_transform || 'none';
            const letterSpacing = data.letter_spacing || '0';
            const gap = data.gap || '8px';
            
            // States
            const fontWeight = data.font_weight || '400';
            const fontWeightHover = data.font_weight_hover || '400';
            const fontWeightActive = data.font_weight_active || '600';
            const color = data.color || '#007bff';
            const colorHover = data.color_hover || '#0056b3';
            const colorActive = data.color_active || '#6c757d';
            const textDecoration = data.text_decoration || 'none';
            const textDecorationHover = data.text_decoration_hover || 'underline';
            const textDecorationActive = data.text_decoration_active || 'none';
            
            // Separator
            const separatorIcon = data.separator_icon || '\\f054';
            const separatorColor = data.separator_color || '#6c757d';
            const separatorSize = data.separator_size || '12px';
            
            // Truncation
            const enableTruncation = data.enable_truncation || 'yes';
            const maxWidth = data.max_width || '200px';
            
            // Apply styles
            const css = `
                <style id="ts-breadcrumb-preview-style">
                    #ts-breadcrumb-demo .breadcrumb {
                        gap: ${gap};
                        font-family: ${fontFamily};
                        font-size: ${fontSize};
                        text-transform: ${textTransform};
                        letter-spacing: ${letterSpacing};
                    }
                    
                    /* Truncation */
                    ${enableTruncation === 'yes' ? `
                    #ts-breadcrumb-demo .breadcrumb-item a,
                    #ts-breadcrumb-demo .breadcrumb-item.active {
                        display: inline-block;
                        max-width: ${maxWidth};
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        vertical-align: bottom;
                    }
                    ` : ''}
                    
                    /* Default Links */
                    #ts-breadcrumb-demo .breadcrumb-item a {
                        font-weight: ${fontWeight};
                        color: ${color};
                        text-decoration: ${textDecoration};
                    }
                    
                    /* Hover Links */
                    #ts-breadcrumb-demo .breadcrumb-item a:hover {
                        font-weight: ${fontWeightHover};
                        color: ${colorHover};
                        text-decoration: ${textDecorationHover};
                    }
                    
                    /* Active (Current Page) */
                    #ts-breadcrumb-demo .breadcrumb-item.active {
                        font-weight: ${fontWeightActive};
                        color: ${colorActive};
                        text-decoration: ${textDecorationActive};
                    }
                    
                    /* Separator */
                    #ts-breadcrumb-demo .breadcrumb-item + .breadcrumb-item::before {
                        content: "${separatorIcon}";
                        color: ${separatorColor};
                        font-size: ${separatorSize};
                        margin-right: ${gap};
                    }
                </style>
            `;
            
            // Remove old style
            $('#ts-breadcrumb-preview-style').remove();
            
            // Add new style
            $('head').append(css);
        },
        
        /**
         * Collect data from fields
         */
        collectData() {
            const data = {};
            
            $('#module-breadcrumb [data-field]').each(function() {
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
        ThemeStylesBreadcrumb.init();
    });
    
})(jQuery);
