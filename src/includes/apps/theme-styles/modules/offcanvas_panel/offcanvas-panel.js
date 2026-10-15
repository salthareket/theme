/**
 * Offcanvas Panel Module JavaScript
 * @version 1.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesOffcanvasPanel = {

        init() {
            if (!$('#module-offcanvas_panel').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        getField(field) {
            const $el = $(`#module-offcanvas_panel [data-field="${field}"]`);
            return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
        },

        updatePreview() {
            const $panel  = $('#ts-op-preview-panel');
            const $header = $('#ts-op-preview-header');
            const $body   = $('#ts-op-preview-body');
            const $footer = $('#ts-op-preview-footer');
            if (!$panel.length) return;

            const g = (f) => this.getField(f);
            const c = (f) => g(f) || 'transparent';

            // Panel background
            const bgType    = g('bg_type')      || 'color';
            const bgColor   = g('bg_color')     || '#ffffff';
            const bgGrad    = g('bg_gradient')  || '';
            const bgImgUrl  = g('bg_image_url') || '';
            const bgSize    = g('bg_size')      || 'cover';
            const bgPos     = g('bg_position')  || 'center center';
            const bgRepeat  = g('bg_repeat')    || 'no-repeat';
            const bgAttach  = g('bg_attachment')|| 'scroll';

            // Reset önce
            $panel.css({ background: '', backgroundImage: '', backgroundSize: '', backgroundPosition: '', backgroundRepeat: '', backgroundAttachment: '' });

            if (bgType === 'gradient' && bgGrad) {
                $panel.css({ backgroundImage: bgGrad, backgroundColor: 'transparent' });
            } else if (bgType === 'image' && bgImgUrl) {
                // Processor ile aynı pattern: backgroundImage'a url() yaz
                $panel.css({
                    backgroundImage:    `url(${bgImgUrl})`,
                    backgroundSize:     bgSize,
                    backgroundPosition: bgPos,
                    backgroundRepeat:   bgRepeat,
                    backgroundAttachment: bgAttach,
                    backgroundColor:    'transparent',
                });
            } else {
                $panel.css({ backgroundColor: bgColor, backgroundImage: 'none' });
            }

            // Layout
            const width    = g('width')        || '380px';
            const bdPad    = g('body_padding') || '1rem';
            const bRadius  = g('border_radius') || '0';
            const shadow   = g('box_shadow')   || 'none';
            $panel.css({ width, borderRadius: bRadius, boxShadow: shadow });
            $body.css('padding', bdPad);

            // Header
            const hdBg     = c('header.bg_color');
            const hdColor  = g('header.color')      || '#212529';
            const hdPad    = g('header.padding')    || '1rem';
            const hdBorder = g('header.border')     || 'none';
            const hdFs     = g('header.font_size')  || '1rem';
            const hdFw     = g('header.font_weight')|| '500';
            $header.css({ background: hdBg, color: hdColor, padding: hdPad, borderBottom: hdBorder, fontSize: hdFs, fontWeight: hdFw });

            // Footer
            const ftBg     = c('footer.bg_color');
            const ftColor  = g('footer.color')   || '#212529';
            const ftPad    = g('footer.padding') || '1rem';
            const ftBorder = g('footer.border')  || 'none';
            $footer.css({ background: ftBg, color: ftColor, padding: ftPad, borderTop: ftBorder });
        },

        bindEvents() {
            $(document).on('click', '#ts-op-toggle', () => {
                $('#ts-op-preview-panel, #ts-op-preview-overlay').addClass('open');
            });
            $(document).on('click', '#ts-op-preview-close, #ts-op-preview-overlay', () => {
                $('#ts-op-preview-panel, #ts-op-preview-overlay').removeClass('open');
            });
            $(document).on('change input', '#module-offcanvas_panel [data-field]', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesOffcanvasPanel.init());

})(jQuery);
