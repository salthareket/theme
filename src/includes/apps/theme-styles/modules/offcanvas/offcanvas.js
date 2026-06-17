/**
 * Offcanvas Module JavaScript
 * @version 2.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesOffcanvas = {

        init() {
            if (!$('#module-offcanvas').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        updatePreview() {
            const $panel   = $('#ts-oc-preview-panel');
            const $header  = $('#ts-oc-preview-header');
            const $title   = $('#ts-oc-preview-title');
            const $nav     = $('#ts-oc-preview-nav');
            const $sub     = $('#ts-oc-sub-menu');
            if (!$panel.length) return;

            const getField = (field) => {
                const $el = $(`#module-offcanvas [data-field="${field}"]`);
                return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
            };

            // Renk field'ı - boşsa transparent
            const getColor = (field) => {
                const v = getField(field);
                return v !== '' ? v : 'transparent';
            };

            // --- Background ---
            const bgType   = getField('bg_type') || 'color';
            const bgColor  = getColor('bg_color') || '#1a1a2e';
            const bgGrad   = getField('bg_gradient') || '';
            const bgImgUrl = getField('bg_image_url') || '';

            let bg = bgColor;
            if (bgType === 'gradient' && bgGrad) bg = bgGrad;
            else if (bgType === 'image' && bgImgUrl) bg = `url(${bgImgUrl}) center/cover no-repeat`;
            $panel.css('background', bg);

            // Padding & align
            const padding  = getField('offcanvas.padding') || '15px 0 40px 0';
            const alignHr  = getField('offcanvas.align_hr') || 'start';
            const alignVr  = getField('offcanvas.align_vr') || 'center';
            $panel.css('padding', padding);

            const flexMap = { start: 'flex-start', center: 'center', end: 'flex-end' };
            $nav.css({
                'align-items':     flexMap[alignHr] || 'flex-start',
                'justify-content': flexMap[alignVr] || 'center',
            });

            // --- Menu Header ---
            const mhColor    = getColor('header.color')          || '#ffffff';
            const mhFontSize = getField('header.font_size')       || '28px';
            const mhWeight   = getField('header.font_weight')     || '600';
            const mhPadding  = getField('header.padding')         || '10px 40px 10px 0';
            const mhIconSize = getField('header.icon_font_size')  || '22px';
            const mhIconColor= getColor('header.icon_color')      || mhColor;
            const mhFont     = getField('header.font_family')     || 'inherit';

            $header.css('padding', mhPadding);
            $title.css({ color: mhColor, fontSize: mhFontSize, fontWeight: mhWeight, fontFamily: mhFont });
            $('#ts-oc-preview-close').css({ color: mhIconColor, fontSize: mhIconSize });

            // --- Nav Item ---
            const niColor      = getColor('nav_item.color')          || '#ffffff';
            const niColorHover = getColor('nav_item.color_hover')     || '#cccccc';
            const niBg         = getColor('nav_item.bg_color');
            const niBgHover    = getColor('nav_item.bg_color_hover');
            const niFontSize   = getField('nav_item.font_size')       || '36px';
            const niWeight     = getField('nav_item.font_weight')     || '500';
            const niPadding    = getField('nav_item.padding')         || '5px 25px 5px 0';
            const niAlign      = getField('nav_item.align_hr')        || 'start';
            const niFont       = getField('nav_item.font_family')     || 'inherit';
            const niAlignMap   = { start: 'flex-start', center: 'center', end: 'flex-end' };

            $nav.find('.ts-oc-nav-item, .ts-oc-has-sub').css({
                color:          niColor,
                background:     niBg,
                fontSize:       niFontSize,
                fontWeight:     niWeight,
                fontFamily:     niFont,
                padding:        niPadding,
                justifyContent: niAlignMap[niAlign] || 'flex-start',
            });

            // --- Sub Menu ---
            const subBg      = getColor('nav_sub.bg_color');
            const subPadding = getField('nav_sub.padding') || '15px 10px';
            $sub.css({ background: subBg, padding: subPadding });

            const nsiColor      = getColor('nav_sub_item.color')          || '#333333';
            const nsiColorHover = getColor('nav_sub_item.color_hover')    || '#000000';
            const nsiBg         = getColor('nav_sub_item.bg_color');
            const nsiBgHover    = getColor('nav_sub_item.bg_color_hover');
            const nsiFontSize   = getField('nav_sub_item.font_size')      || '18px';
            const nsiWeight     = getField('nav_sub_item.font_weight')    || '500';
            const nsiPadding    = getField('nav_sub_item.padding')        || '5px 18px';

            $sub.find('.ts-oc-sub-item').css({
                color:      nsiColor,
                background: nsiBg,
                fontSize:   nsiFontSize,
                fontWeight: nsiWeight,
                padding:    nsiPadding,
            });

            // Inject hover styles
            $('#ts-oc-preview-style').remove();
            $('head').append(`<style id="ts-oc-preview-style">
                #ts-oc-preview-nav .ts-oc-nav-item:hover,
                #ts-oc-preview-nav .ts-oc-has-sub:hover { color: ${niColorHover} !important; background: ${niBgHover} !important; }
                #ts-oc-sub-menu .ts-oc-sub-item:hover { color: ${nsiColorHover} !important; background: ${nsiBgHover} !important; }
            </style>`);
        },

        bindEvents() {
            // Module tab switching
            $(document).on('click', '#module-offcanvas .ts-module-tab-btn', function() {
                const tab = $(this).data('module-tab');
                $('#module-offcanvas .ts-module-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('#module-offcanvas .ts-module-tab-content').removeClass('active');
                $(`#module-offcanvas [data-module-tab-content="${tab}"]`).addClass('active');
            });

            // Toggle preview panel
            $(document).on('click', '#ts-oc-toggle', function() {
                $('#ts-oc-preview-panel, #ts-oc-preview-overlay').addClass('open');
            });

            $(document).on('click', '#ts-oc-preview-close, #ts-oc-preview-overlay', function() {
                $('#ts-oc-preview-panel, #ts-oc-preview-overlay').removeClass('open');
            });

            // Sub menu toggle
            $(document).on('click', '#module-offcanvas .ts-oc-has-sub', function(e) {
                e.stopPropagation();
                $(this).find('.ts-oc-sub-menu').slideToggle(200);
            });

            // Field changes → update preview
            $(document).on('change input', '#module-offcanvas [data-field]', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesOffcanvas.init());

})(jQuery);
