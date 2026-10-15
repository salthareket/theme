/**
 * Dropdown Module JavaScript
 * @version 1.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesDropdown = {

        init() {
            if (!$('#module-dropdown').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        getField(field) {
            const $el = $(`#module-dropdown [data-field="${field}"]`);
            return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
        },

        updatePreview() {
            const $menu    = $('#ts-dd-preview-menu');
            const $divider = $('#ts-dd-preview-divider');
            const $header  = $('#ts-dd-preview-header');
            if (!$menu.length) return;

            const g = (f) => this.getField(f);
            const c = (f) => g(f) || 'transparent';

            // Menu background
            const bgType = g('bg_type') || 'color';
            const bgColor= g('bg_color') || '#ffffff';
            const bgGrad = g('bg_gradient') || '';
            let bg = bgColor;
            if (bgType === 'gradient' && bgGrad) bg = bgGrad;
            $menu.css('background', bg);

            // Layout
            const minW    = g('min_width')     || '10rem';
            const pad     = g('padding')       || '0.5rem 0';
            const bRadius = g('border_radius') || '0.375rem';
            const border  = g('border')        || '1px solid rgba(0,0,0,.15)';
            const shadow  = g('box_shadow')    || '0 .5rem 1rem rgba(0,0,0,.15)';
            $menu.css({ minWidth: minW, padding: pad, borderRadius: bRadius, border: border, boxShadow: shadow });

            // Header
            const hdColor = g('header.color')    || '#6c757d';
            const hdFs    = g('header.font_size')|| '0.75rem';
            const hdPad   = g('header.padding')  || '0.5rem 1rem';
            $header.css({ color: hdColor, fontSize: hdFs, padding: hdPad });

            // Items
            const itemColor  = g('item.color')   || '#212529';
            const itemPad    = g('item.padding')  || '0.25rem 1rem';
            const itemFs     = g('item.font_size')|| '1rem';
            const itemFw     = g('item.font_weight') || '400';
            const itemBr     = g('item.border_radius') || '0';
            const itemHoverC = g('item.color_hover')  || '#000000';
            const itemHoverBg= g('item.bg_hover')     || 'rgba(0,0,0,.04)';
            const itemActiveC= g('item.color_active') || '#ffffff';
            const itemActiveBg= g('item.bg_active')   || '#0d6efd';

            $menu.find('.ts-dd-item').css({ color: itemColor, padding: itemPad, fontSize: itemFs, fontWeight: itemFw, borderRadius: itemBr });
            $menu.find('.ts-dd-item.active').css({ color: itemActiveC, background: itemActiveBg });

            // Divider
            const divColor = g('divider.color')  || 'rgba(0,0,0,.15)';
            const divMargin= g('divider.margin') || '0.5rem 0';
            $divider.css({ borderColor: divColor, margin: divMargin });

            // Hover via style tag
            $('#ts-dd-preview-style').remove();
            $('head').append(`<style id="ts-dd-preview-style">
                #ts-dd-preview-menu .ts-dd-item:not(.active):hover {
                    color: ${itemHoverC} !important;
                    background: ${itemHoverBg} !important;
                }
            </style>`);
        },

        bindEvents() {
            $(document).on('click', '#ts-dd-toggle', function() {
                $('#ts-dd-preview-menu').toggleClass('open');
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#ts-dd-preview-wrapper').length) {
                    $('#ts-dd-preview-menu').removeClass('open');
                }
            });
            $(document).on('change input', '#module-dropdown [data-field]', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesDropdown.init());

})(jQuery);
