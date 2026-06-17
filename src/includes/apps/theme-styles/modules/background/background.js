/**
 * Background/Body Module JavaScript
 * @version 2.0.0
 */

(function($) {
    'use strict';

    // Colors.js ile AYNI pattern - dependency olarak yüklendiği için hazır
    const { createElement, render, useState } = wp.element;
    const { GradientPicker } = wp.components;

    const _inited = {};

    function initGradientPicker(control) {
        if (!control || control.dataset.init) return;
        control.dataset.init = '1';

        const $wrapper = $(control).closest('.ts-bg-field-wrapper');
        const $input   = $wrapper.find('.ts-gradient-input');
        if (!$input.length) return;

        const defaultGradient = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';

        const Picker = () => {
            const [value, setValue] = useState($input.val() || defaultGradient);
            const update = (val) => {
                setValue(val);
                $input.val(val).trigger('change');
                ThemeStylesBackground.updatePreview();
            };
            return createElement(GradientPicker, { value, onChange: update });
        };

        if (wp.element.createRoot) {
            wp.element.createRoot(control).render(createElement(Picker));
        } else {
            render(createElement(Picker), control);
        }
    }

    const ThemeStylesBackground = {

        init() {
            if (!$('#module-background').length) return;
            this.bindEvents();
            this.initMediaUpload();
            this.updatePreview();
        },

        bindEvents() {
            // Type switcher - wrapper içinde çalışır
            $(document).on('click', '.ts-bg-type-btn', (e) => {
                const $btn    = $(e.currentTarget);
                const type    = $btn.data('type');
                const $wrapper = $btn.closest('.ts-bg-field-wrapper');

                $wrapper.find('.ts-bg-type-btn').removeClass('active');
                $btn.addClass('active');
                $wrapper.find('.ts-bg-tab').removeClass('active');
                $wrapper.find(`.ts-bg-tab[data-tab="${type}"]`).addClass('active');
                $wrapper.find('.ts-bg-type-value').val(type).trigger('change');

                if (type === 'gradient') {
                    const control = $wrapper.find('[class*="ts-bg-gradient-picker-"]')[0];
                    initGradientPicker(control);
                }

                this.updatePreview();
            });

            // Backdrop test
            $(document).on('click', '#ts-backdrop-test-btn', () => {
                const d = this.collectData();
                const bg = this.hexToRgba(d.backdrop_color || '#000000', parseFloat(d.backdrop_opacity ?? 0.5));
                $('#ts-backdrop-overlay').css('background', bg).removeClass('hidden');
            });

            $(document).on('click', '#ts-backdrop-close', () => {
                $('#ts-backdrop-overlay').addClass('hidden');
            });

            // Range input
            $(document).on('input', '#module-background input[type="range"]', function() {
                $(this).next('.ts-range-value').text($(this).val());
                ThemeStylesBackground.updatePreview();
            });

            // Field changes → preview
            $(document).on('change input', '#module-background [data-field]', () => {
                this.updatePreview();
            });

            // Button groups - tüm bg wrapper'larında çalışır
            $(document).on('click', '.ts-bg-field-wrapper .ts-button-group .ts-btn-group-item', function() {
                const $group   = $(this).closest('.ts-button-group');
                const $wrapper = $(this).closest('.ts-bg-field-wrapper');
                $group.find('.ts-btn-group-item').removeClass('active');
                $(this).addClass('active');
                const field = $group.data('field');
                const val   = $(this).data('value');
                $wrapper.find(`[data-field="${field}"]`).not('.ts-btn-group-item').val(val).trigger('change');
                if (field && field.includes('_size')) {
                    $wrapper.find('.ts-bg-custom-size').toggleClass('hidden', val !== 'custom');
                }
                ThemeStylesBackground.updatePreview();
            });

            // Position grid
            $(document).on('click', '.ts-bg-pos-btn', function() {
                const $wrapper = $(this).closest('.ts-bg-field-wrapper');
                $wrapper.find('.ts-bg-pos-btn').removeClass('active');
                $(this).addClass('active');
                $wrapper.find('.ts-bg-position-value').val($(this).data('value')).trigger('change');
                ThemeStylesBackground.updatePreview();
            });
        },

        collectData() {
            const data = {};
            $('#module-background [data-field]').each(function() {
                const $f    = $(this);
                const field = $f.data('field');
                let val;
                if ($f.hasClass('ts-button-group')) {
                    // Button group: active button'ın data-value'su
                    val = $f.find('.ts-btn-group-item.active').data('value') || '';
                } else if ($f.is('select')) {
                    val = $f.find('option:selected').val();
                } else {
                    val = $f.val();
                }
                data[field] = val;
            });
            return data;
        },

        updatePreview() {
            const d = this.collectData();
            const $preview = $('#ts-body-preview');
            const $inner   = $('#ts-body-preview-inner');
            const $link    = $preview.find('.ts-body-preview-link');
            const $visited = $preview.find('.ts-body-preview-link-visited');

            $preview.css({ backgroundImage: '', backgroundColor: '' });
            $preview.css('background-color', d.bg_color || '#ffffff');

            const grad     = d.bg_gradient || '';
            const hasImage = !!(d.bg_image_url);
            const hasGrad  = !!grad;
            const size     = d.bg_size === 'custom'
                ? `${d.bg_size_w || '100%'} ${d.bg_size_h || 'auto'}`
                : (d.bg_size || 'cover');

            if (hasImage && hasGrad) {
                $preview.css({
                    backgroundImage:    `url(${d.bg_image_url}), ${grad}`,
                    backgroundSize:     `${size}, cover`,
                    backgroundPosition: `${d.bg_position || 'center center'}, center center`,
                    backgroundRepeat:   `${d.bg_repeat || 'no-repeat'}, no-repeat`,
                    backgroundAttachment: `${d.bg_attachment || 'scroll'}, scroll`,
                });
            } else if (hasImage) {
                $preview.css({
                    backgroundImage:    `url(${d.bg_image_url})`,
                    backgroundSize:     size,
                    backgroundPosition: d.bg_position || 'center center',
                    backgroundRepeat:   d.bg_repeat || 'no-repeat',
                    backgroundAttachment: d.bg_attachment || 'scroll',
                });
            } else if (hasGrad) {
                $preview.css('backgroundImage', grad);
            }

            $inner.css({ color: d.text_color || '#212529', fontSize: d.font_size || '16px', fontWeight: d.font_weight || '400', lineHeight: d.line_height || '1.6', letterSpacing: d.letter_spacing || '0' });
            $link.css('color', d.link_color || '#007bff');
            $visited.css('color', d.link_color_visited || '#6f42c1');

            $('#ts-body-dynamic-style').remove();
            $('head').append(`<style id="ts-body-dynamic-style">
                #ts-body-preview .ts-body-preview-link:hover { color: ${d.link_color_hover || '#0056b3'} !important; }
                #ts-body-preview ::selection { background: ${d.selection_bg || '#007bff'}; color: ${d.selection_color || '#ffffff'}; }
                #ts-body-preview { scrollbar-width: ${d.scrollbar_width || 'auto'}; scrollbar-color: ${d.scrollbar_thumb || '#888'} ${d.scrollbar_track || '#f1f1f1'}; }
                #ts-body-preview::-webkit-scrollbar { width: ${d.scrollbar_width === 'thin' ? '6px' : d.scrollbar_width === 'none' ? '0' : '12px'}; }
                #ts-body-preview::-webkit-scrollbar-track { background: ${d.scrollbar_track || '#f1f1f1'}; border-radius: 4px; }
                #ts-body-preview::-webkit-scrollbar-thumb { background: ${d.scrollbar_thumb || '#888'}; border-radius: 4px; }
                #ts-body-preview::-webkit-scrollbar-thumb:hover { background: ${d.scrollbar_thumb_hover || '#555'}; }
            </style>`);
        },

        hexToRgba(hex, opacity) {
            if (!hex || hex.startsWith('rgb')) return hex || `rgba(0,0,0,${opacity})`;
            const r = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (!r) return `rgba(0,0,0,${opacity})`;
            return `rgba(${parseInt(r[1],16)},${parseInt(r[2],16)},${parseInt(r[3],16)},${opacity})`;
        },

        initMediaUpload() {
            const mediaFrames = {};

            $(document).on('click', '.ts-media-select', function(e) {
                e.preventDefault();
                const $btn     = $(this);
                const target   = $btn.data('target') || 'body_bg';
                const $wrapper = $btn.closest('.ts-bg-field-wrapper');

                if (mediaFrames[target]) { mediaFrames[target].open(); return; }

                mediaFrames[target] = wp.media({ title: 'Select Background Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });

                mediaFrames[target].on('select', function() {
                    const att = mediaFrames[target].state().get('selection').first().toJSON();
                    $wrapper.find('.ts-bg-image-id').val(att.id).trigger('change');
                    $wrapper.find('.ts-bg-image-url').val(att.url).trigger('change');
                    $wrapper.find(`#ts-bg-image-preview-${target}`).html(`<img src="${att.url}" alt="" />`);
                    $wrapper.find('.ts-media-remove').removeClass('hidden');
                    $wrapper.find('.ts-bg-image-options').removeClass('hidden');
                    ThemeStylesBackground.updatePreview();
                });

                mediaFrames[target].open();
            });

            $(document).on('click', '.ts-media-remove', function() {
                const $wrapper = $(this).closest('.ts-bg-field-wrapper');
                const target   = $wrapper.data('bg-id') || 'body_bg';
                $wrapper.find('.ts-bg-image-id, .ts-bg-image-url').val('').trigger('change');
                $wrapper.find(`#ts-bg-image-preview-${target}`).html(`<div class="ts-media-placeholder"><span class="dashicons dashicons-format-image"></span><span>No image selected</span></div>`);
                $(this).addClass('hidden');
                $wrapper.find('.ts-bg-image-options').addClass('hidden');
                ThemeStylesBackground.updatePreview();
            });
        }
    };

    $(document).ready(() => {
        ThemeStylesBackground.init();
    });

})(jQuery);
