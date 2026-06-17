/**
 * Footer Module JavaScript
 * @version 2.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesFooter = {
        init() {
            if (!$('#module-footer').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        updatePreview() {
            const $preview = $('#ts-footer-preview');
            if (!$preview.length) return;

            const getField = (field) => {
                const $el = $(`#module-footer [data-field="${field}"]`);
                return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
            };

            // Background
            const bgType  = getField('bg_type') || 'color';
            const bgColor = getField('bg_color') || '#212529';
            const bgGrad  = getField('bg_gradient') || '';
            const bgImgUrl = getField('bg_image_url') || '';

            let bg = bgColor;
            if (bgType === 'gradient' && bgGrad) bg = bgGrad;
            else if (bgType === 'image' && bgImgUrl) bg = `url(${bgImgUrl}) center/cover no-repeat`;

            $preview.css('background', bg);

            // Colors
            const textColor      = getField('color') || '#ffffff';
            const linkColor      = getField('link_color') || '#adb5bd';
            const linkColorHover = getField('link_color_hover') || '#ffffff';

            $preview.css('color', textColor);
            $preview.find('.ts-fp-text, .ts-fp-heading').css('color', textColor);
            $preview.find('.ts-fp-link').css('color', linkColor);
            $preview.find('.ts-fp-link-hover').css('color', linkColorHover);

            // Size
            const height  = getField('height') || 'auto';
            const padding = getField('padding') || '60px 0';
            $preview.css({ minHeight: height !== 'auto' ? height : '', padding });

            // Inject hover style
            $('#ts-footer-preview-style').remove();
            $('head').append(`<style id="ts-footer-preview-style">
                #ts-footer-preview .ts-fp-link:hover { color: ${linkColorHover} !important; }
            </style>`);
        },

        bindEvents() {
            $(document).on('change input', '#module-footer [data-field]', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesFooter.init());

})(jQuery);
