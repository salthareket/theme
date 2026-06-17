/**
 * Typography Module JavaScript
 * Edit/Preview tabs + live preview update
 * @version 2.0.0
 */

(function($) {
    'use strict';

    const ThemeStylesTypography = {

        init() {
            if (!$('#module-typography').length) return;
            this.bindEvents();
            this.updateClampPreview();
        },

        bindEvents() {
            // Module tab switching (Edit/Preview)
            $(document).on('click', '#module-typography .ts-module-tab-btn', function() {
                const tab = $(this).data('module-tab');
                $('#module-typography .ts-module-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('#module-typography .ts-module-tab-content').removeClass('active');
                $(`#module-typography [data-module-tab-content="${tab}"]`).addClass('active');

                if (tab === 'preview') {
                    ThemeStylesTypography.updatePreview();
                }
            });

            // Advanced toggle
            $(document).on('click', '#module-typography .ts-fluid-advanced-toggle', function() {
                const targetId = $(this).data('target');
                const $panel = $('#' + targetId);
                const isOpen = $panel.is(':visible');
                $panel.slideToggle(200);
                $(this).toggleClass('open', !isOpen);
            });

            // Mobile override switch
            $(document).on('change', '.ts-switch-value[data-field="mobile_override_active"]', function() {
                const val = $(this).val();
                if (val === '1') {
                    $('#ts-mobile-override-panel').slideDown(200);
                } else {
                    $('#ts-mobile-override-panel').slideUp(200);
                }
            });

            // Fluid min/max input → clamp preview güncelle
            $(document).on('input change', '#module-typography .ts-fluid-input', function() {
                ThemeStylesTypography.updateClampPreview();
            });

            // Field changes → update preview if visible
            $(document).on('change input', '#module-typography [data-field]', () => {
                if ($('#module-typography [data-module-tab-content="preview"]').hasClass('active')) {
                    ThemeStylesTypography.updatePreview();
                }
            });
        },

        updateClampPreview() {
            const titleMin = $('[data-field="title_min_size"]').val() || '24px';
            const titleMax = $('[data-field="title_max_size"]').val() || '64px';
            const textMin  = $('[data-field="text_min_size"]').val()  || '14px';
            const textMax  = $('[data-field="text_max_size"]').val()  || '18px';

            const titleClamp = this.buildClamp(titleMin, titleMax);
            const textClamp  = this.buildClamp(textMin, textMax);

            $('#ts-fluid-title-preview').text(titleClamp);
            $('#ts-fluid-text-preview').text(textClamp);
        },

        buildClamp(minVal, maxVal) {
            const minNum = parseFloat(minVal) || 0;
            const maxNum = parseFloat(maxVal) || 0;
            const unit   = (minVal.match(/[a-z%]+/) || ['px'])[0];
            // min_vw: 576, max_vw: 1600
            const minVw = 576, maxVw = 1600;
            if (minNum === maxNum) return `${minNum}${unit}`;
            const factor = (maxNum - minNum) / (maxVw - minVw);
            const calc   = minNum - (minVw * factor);
            return `clamp(${minNum}${unit}, calc(${calc.toFixed(2)}${unit} + ${factor.toFixed(4)} * 100vw), ${maxNum}${unit})`;
        },

        collectData() {
            const data = {};
            $('#module-typography [data-field]').each(function() {
                const $f = $(this);
                const key = $f.data('field');
                data[key] = $f.is('select') ? $f.find('option:selected').val() : $f.val();
            });
            return data;
        },

        updatePreview() {
            const d = this.collectData();

            // Inject preview styles
            $('#ts-typography-preview-style').remove();

            const primaryFont = d.font_primary || 'inherit';
            const headingFont = d.font_heading || primaryFont;
            const baseSize = d.base_font_size || '16px';
            const baseWeight = d.base_font_weight || '400';
            const baseLH = d.base_line_height || '1.6';
            const baseLS = d.base_letter_spacing || '0';

            // Heading styles
            const headings = ['h1','h2','h3','h4','h5','h6'];
            let headingCSS = '';
            headings.forEach(h => {
                const font = d[`headings.${h}.font_family`] || headingFont;
                const size = d[`headings.${h}.font_size`] || '';
                const weight = d[`headings.${h}.font_weight`] || '700';
                const lh = d[`headings.${h}.line_height`] || '1.2';
                const transform = d[`headings.${h}.text_transform`] || 'none';
                const index = headings.indexOf(h);
                const defaultSizes = ['48px','40px','32px','24px','20px','16px'];

                headingCSS += `
                    #ts-preview-${h} {
                        font-family: ${font};
                        font-size: ${size || defaultSizes[index]};
                        font-weight: ${weight};
                        line-height: ${lh};
                        text-transform: ${transform};
                    }
                `;
            });

            // Title scale - her breakpoint için
            const bpKeys = ['xxxl', 'xxl', 'xl', 'lg', 'md', 'sm', 'xs'];
            const defaultTitleSizes = { xxxl: '64px', xxl: '56px', xl: '48px', lg: '40px', md: '32px', sm: '28px', xs: '24px' };
            const defaultMobileSizes = { xxxl: '48px', xxl: '44px', xl: '40px', lg: '36px', md: '30px', sm: '26px', xs: '22px' };

            let titleScaleCSS = '';
            bpKeys.forEach(bp => {
                const desktopSize = d[`title.${bp}`] || defaultTitleSizes[bp];
                const mobileSize = d[`title_mobile.${bp}`] || defaultMobileSizes[bp];
                const headingFont = d.font_heading || d.font_primary || 'inherit';
                const headingWeight = d['headings.h1.font_weight'] || '700';

                titleScaleCSS += `
                    #ts-preview-title-${bp} {
                        font-family: ${headingFont};
                        font-size: ${desktopSize};
                        font-weight: ${headingWeight};
                    }
                    #ts-preview-title-mobile-${bp} {
                        font-family: ${headingFont};
                        font-size: ${mobileSize};
                        font-weight: ${headingWeight};
                    }
                `;
            });

            $('head').append(`<style id="ts-typography-preview-style">
                .ts-typography-preview {
                    font-family: ${primaryFont};
                    font-size: ${baseSize};
                    font-weight: ${baseWeight};
                    line-height: ${baseLH};
                    letter-spacing: ${baseLS};
                }
                ${headingCSS}
                ${titleScaleCSS}
            </style>`);
        }
    };

    $(document).ready(() => {
        ThemeStylesTypography.init();
    });

})(jQuery);
