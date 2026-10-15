/**
 * Modal Module JavaScript
 * @version 2.1.0
 */
(function($) {
    'use strict';

    const ThemeStylesModal = {

        init() {
            if (!$('#module-modal').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        getField(field) {
            const $el = $(`#module-modal [data-field="${field}"]`);
            if ($el.is('select'))            return $el.find('option:selected').val();
            if ($el.attr('type') === 'range') return $el.val();
            return $el.val() || '';
        },

        updatePreview() {
            const $dialog  = $('#ts-modal-preview-dialog');
            const $header  = $('#ts-modal-preview-header');
            const $title   = $('#ts-modal-preview-title');
            const $closeBtn= $('#ts-modal-preview-close');
            const $body    = $('#ts-modal-preview-body');
            const $footer  = $('#ts-modal-preview-footer');
            const $overlay = $('#ts-modal-preview-overlay');
            if (!$dialog.length) return;

            const g  = (f) => this.getField(f);
            const c  = (f) => g(f) || 'transparent';
            const px = (v, fallback) => v || fallback;

            // ── Background ───────────────────────────────────────
            const bgType  = g('bg_type') || 'color';
            const bgColor = g('bg_color') || '#ffffff';
            const bgGrad  = g('bg_gradient') || '';
            const bgImg   = g('bg_image_url') || '';
            if (bgType === 'gradient' && bgGrad) {
                $dialog.css({ background: bgGrad });
            } else if (bgType === 'image' && bgImg) {
                $dialog.css({ background: `url(${bgImg}) center/cover no-repeat` });
            } else {
                $dialog.css({ background: bgColor || '#fff' });
            }

            // ── Border & Shadow ──────────────────────────────────
            $dialog.css({
                borderColor:  g('border_color')  || 'rgba(0,0,0,.2)',
                borderWidth:  g('border_width')  || '1px',
                borderStyle:  'solid',
                borderRadius: g('border_radius') || '0.5rem',
                boxShadow:    g('box_shadow')    || '0 .5rem 1rem rgba(0,0,0,.5)',
            });

            // ── Animation ────────────────────────────────────────
            const animType = g('animation.type')         || 'fade';
            const animDur  = g('animation.duration_raw') || '0.3';
            const duration = parseFloat(animDur) + 's';

            // Remove previous animation class
            $dialog[0].style.transition = `opacity ${duration}, transform ${duration}`;

            // ── Backdrop ─────────────────────────────────────────
            const bdColor   = g('backdrop.color')   || '#000000';
            const bdOpacity = g('backdrop.opacity') || '0.5';
            $overlay.css({ background: bdColor, opacity: bdOpacity });

            // ── Close Button ─────────────────────────────────────
            const closeFontSize  = g('close_btn.size')        || '1.25rem';
            const closeColor     = g('close_btn.color')       || 'inherit';
            const closeColorHov  = g('close_btn.color_hover') || closeColor;
            const closeOpacity   = g('close_btn.opacity')     || '0.5';
            $closeBtn.css({ fontSize: closeFontSize, color: closeColor, opacity: closeOpacity });

            // Hover → dynamic style tag
            $('#ts-modal-close-style').remove();
            $('head').append(`<style id="ts-modal-close-style">
                #ts-modal-preview-close:hover { color: ${closeColorHov} !important; opacity: 1 !important; }
            </style>`);

            // ── Header ───────────────────────────────────────────
            const hdBg     = c('header.bg_color');
            const hdColor  = g('header.color')       || '#212529';
            const hdPad    = px(g('header.padding'), '1rem');
            const hdBordC  = g('header.border_color') || 'transparent';
            const hdBordW  = g('header.border_width') || '0';
            const hdFs     = px(g('header.font_size'),   '1.25rem');
            const hdFw     = px(g('header.font_weight'), '500');
            $header.css({
                background:   hdBg,
                color:        hdColor,
                padding:      hdPad,
                borderBottom: `${hdBordW} solid ${hdBordC}`,
                fontSize:     hdFs,
                fontWeight:   hdFw,
            });
            if ($title.length) {
                $title.css({ fontSize: hdFs, fontWeight: hdFw });
            }

            // ── Body ─────────────────────────────────────────────
            const bdBg   = c('body.bg_color');
            const bdClr  = g('body.color')    || '#212529';
            const bdPad  = px(g('body.padding'),  '1rem');
            const bdFs   = px(g('body.font_size'), '1rem');
            $body.css({ background: bdBg, color: bdClr, padding: bdPad, fontSize: bdFs });

            // ── Footer ───────────────────────────────────────────
            const ftBg    = c('footer.bg_color');
            const ftClr   = g('footer.color')        || '#212529';
            const ftPad   = px(g('footer.padding'),  '0.75rem 1rem');
            const ftGap   = px(g('footer.gap'),      '0.5rem');
            const ftBordC = g('footer.border_color') || 'transparent';
            const ftBordW = g('footer.border_width') || '0';
            $footer.css({
                background: ftBg,
                color:      ftClr,
                padding:    ftPad,
                gap:        ftGap,
                borderTop:  `${ftBordW} solid ${ftBordC}`,
            });
        },

        playAnimation() {
            const $dialog  = $('#ts-modal-preview-dialog');
            const $overlay = $('#ts-modal-preview-overlay');
            const animType = this.getField('animation.type') || 'fade';
            const animDur  = parseFloat(this.getField('animation.duration_raw') || '0.3');
            const duration = animDur + 's';
            const bdOpacity= this.getField('backdrop.opacity') || '0.5';
            const bdColor  = this.getField('backdrop.color') || '#000000';

            // Başlangıç transformları — hepsi translate(-50%,-50%) base'i koruyor
            const startTransforms = {
                'fade':       'translate(-50%, -50%)',
                'slide-up':   'translate(-50%, calc(-50% + 50px))',
                'slide-down': 'translate(-50%, calc(-50% - 50px))',
                'zoom':       'translate(-50%, -50%) scale(0.85)',
                'zoom-in':    'translate(-50%, -50%) scale(1.15)',
                'flip':       'translate(-50%, -50%) rotateX(-15deg)',
            };
            const endTransform = 'translate(-50%, -50%)';

            // Dialog'u göster, başlangıç state'e getir
            $dialog.css({
                display:    'flex',
                visibility: 'visible',
                opacity:    0,
                transition: 'none',
                transform:  startTransforms[animType] || startTransforms['fade'],
            });
            $overlay.css({
                visibility: 'visible',
                opacity:    0,
                background: bdColor,
                transition: 'none',
            });

            // Frame sonra animasyonu başlat
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    $dialog.css({
                        transition: `opacity ${duration} ease, transform ${duration} ease`,
                        opacity:    1,
                        transform:  endTransform,
                    });
                    $overlay.css({
                        transition: `opacity ${duration} ease`,
                        opacity:    bdOpacity,
                    });
                    $dialog.addClass('open');
                    $overlay.addClass('open');
                });
            });
        },

        bindEvents() {
            // Toggle open
            $(document).on('click', '#ts-modal-toggle', () => {
                this.updatePreview();
                this.playAnimation();
            });

            // Close
            $(document).on('click', '#ts-modal-preview-close, #ts-modal-preview-overlay', (e) => {
                if (!$(e.target).is('#ts-modal-preview-overlay') && !$(e.target).is('#ts-modal-preview-close')) return;
                const dur = parseFloat(this.getField('animation.duration_raw') || '0.3');
                const $d  = $('#ts-modal-preview-dialog');
                const $o  = $('#ts-modal-preview-overlay');
                $d.css({ transition: `opacity ${dur}s ease, transform ${dur}s ease`, opacity: 0 });
                $o.css({ transition: `opacity ${dur}s ease`, opacity: 0 });
                setTimeout(() => {
                    $d.removeClass('open').css({ display: 'none', visibility: 'hidden', opacity: '', transform: 'translate(-50%,-50%)', transition: '' });
                    $o.removeClass('open').css({ visibility: 'hidden', opacity: '', transition: '', 'pointer-events': '' });
                }, dur * 1000 + 50);
            });

            // Range input → span güncelle
            $(document).on('input', '#module-modal input[type="range"]', function() {
                const field = $(this).data('field');
                const val   = $(this).val();
                const suffix = field === 'animation.duration_raw' ? 's' : '';
                $(this).next('.ts-range-value').text(val + suffix);
                ThemeStylesModal.updatePreview();
            });

            // Diğer field değişiklikleri
            $(document).on('change input', '#module-modal [data-field]:not([type="range"])', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesModal.init());

})(jQuery);
