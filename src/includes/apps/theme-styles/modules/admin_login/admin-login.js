/**
 * Admin Login Module — Live Preview JS
 * Field değişince preview panelini anlık günceller.
 */
(function ($) {
    'use strict';

    // Preview element cache
    const sel = {
        page:   '#ts-al-preview-page',
        box:    '#ts-al-preview-box',
        label:  '#ts-al-preview-label, #ts-al-preview-label2',
        input:  '#ts-al-preview-input, #ts-al-preview-input2',
        btn:    '#ts-al-preview-btn',
        link:   '#ts-al-preview-link',
        logo:   '#ts-al-preview-logo',
        logoImg:'#ts-al-preview-logo img',
        logoPlaceholder: '#ts-al-logo-placeholder',
    };

    // Hex+opacity → rgba, boş hex → transparent
    function hexOpacity(hex, opacity) {
        if (!hex || hex === '') return 'transparent';
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        const r = parseInt(hex.substr(0,2),16);
        const g = parseInt(hex.substr(2,2),16);
        const b = parseInt(hex.substr(4,2),16);
        const a = parseFloat(opacity || 1);
        return a >= 1 ? '#' + hex : 'rgba('+r+','+g+','+b+','+a+')';
    }

    function getField(name) {
        const $el = $('#module-admin_login [data-field="'+name+'"]').not('.ts-switch-value').first();
        if ($el.is(':checkbox')) return $el.is(':checked') ? '1' : '0';
        return $el.val() || '';
    }

    function updatePreview() {
        const $panel = $('#module-admin_login');
        if (!$panel.length) return;

        // Page background
        const bgType  = getField('page_bg_type');
        const bgColor = getField('page_bg_color') || '#1d2327';
        const bgGrad  = getField('page_bg_gradient');
        const bgImgUrl= $panel.find('[data-field="page_bg_image_url"]').val() || '';
        const bgSize  = getField('page_bg_size') || 'cover';
        const bgPos   = getField('page_bg_position') || 'center center';
        const bgRep   = getField('page_bg_repeat') || 'no-repeat';

        const $page = $(sel.page);
        $page.css('background-color', bgColor);
        if (bgType === 'image' && bgImgUrl) {
            $page.css({ 'background-image': 'url('+bgImgUrl+')', 'background-size': bgSize, 'background-position': bgPos, 'background-repeat': bgRep });
        } else if (bgType === 'gradient' && bgGrad) {
            $page.css({ 'background-image': bgGrad, 'background-size': '', 'background-position': '', 'background-repeat': '' });
        } else {
            $page.css({ 'background-image': 'none' });
        }

        // Box
        const boxBg      = getField('box_bg_color') || '';
        const boxOpacity = parseFloat(getField('box_bg_opacity') || 1);
        const boxRadius  = getField('box_border_radius') || '8';
        const boxPadding = getField('box_padding') || '24';
        const boxWidth   = Math.min(280, parseInt(getField('box_width') || 360));
        const boxShadow  = getField('box_shadow') !== '0' && getField('box_shadow') !== '' ? '0 4px 24px rgba(0,0,0,.18)' : 'none';

        $(sel.box).css({
            'background':    boxBg ? boxBg : 'transparent',
            'opacity':       boxOpacity,
            'border-radius': boxRadius + 'px',
            'padding':       boxPadding + 'px',
            'width':         boxWidth + 'px',
            'box-shadow':    boxShadow,
        });

        // Logo
        const logoUrl = $panel.find('[data-field="logo_image_url"]').val() || '';
        const logoH   = getField('logo_max_height') || '80';
        const logoW   = getField('logo_max_width')  || '200';
        if (logoUrl) {
            if (!$(sel.logoImg).length) {
                $(sel.logo).html('<img id="ts-al-logo-img" src="'+logoUrl+'" style="max-height:'+logoH+'px;max-width:'+logoW+'px" />');
            } else {
                $(sel.logoImg).attr('src', logoUrl).css({'max-height': logoH+'px', 'max-width': logoW+'px'});
            }
        } else {
            $(sel.logo).html('<div id="ts-al-logo-placeholder"><span class="dashicons dashicons-wordpress-alt" style="font-size:48px;color:'+( getField('text_color') || '#1d2327' )+'"></span></div>');
        }

        // Labels
        $(sel.label).css('color', getField('label_color') || '#1d2327');

        // Inputs
        const inputBg     = getField('input_bg')     || 'transparent';
        const inputBorder = getField('input_border') || 'transparent';
        const inputRadius = getField('input_radius') || '4';
        $(sel.input).css({ 'background': inputBg, 'border': '1px solid '+inputBorder, 'border-radius': inputRadius+'px' });

        // Button
        const btnBg     = getField('btn_bg')     || 'transparent';
        const btnColor  = getField('btn_color')  || 'inherit';
        const btnBorder = getField('btn_border') || 'transparent';
        const btnRadius = getField('btn_radius') || '3';
        const btnTr     = getField('btn_text_transform') || 'none';
        const btnFw     = getField('btn_font_weight')    || '600';
        $(sel.btn).css({
            'background':     btnBg,
            'color':          btnColor,
            'border':         '1px solid '+btnBorder,
            'border-radius':  btnRadius+'px',
            'text-transform': btnTr,
            'font-weight':    btnFw,
        });

        // Link
        $(sel.link).css('color', getField('link_color') || '#2271b1');
    }

    // Delegate: her field değişince preview'ı güncelle
    $(document).on('input change ts:field-change', '#module-admin_login [data-field]', function () {
        updatePreview();
    });

    // Opacity slider → yanındaki değeri güncelle
    $(document).on('input', '#module-admin_login .al-opacity-slider', function () {
        $(this).siblings('.al-opacity-value').text(parseFloat($(this).val()).toFixed(2));
    });

    // Color picker change
    $(document).on('iris:change', '#module-admin_login .ts-color-input', function () {
        updatePreview();
    });

    // Media removed/selected
    $(document).on('ts:media-changed', '#module-admin_login', function () {
        updatePreview();
    });

    // İlk yüklemede preview'ı doldur
    $(document).ready(function () {
        // Module aktifse direkt çalıştır
        if ($('#module-admin_login.active').length) {
            updatePreview();
        }
        // Module'e geçince de çalıştır
        $(document).on('click', '.theme-styles-modules-nav a[data-module="admin_login"]', function () {
            setTimeout(updatePreview, 50);
        });
    });

})(jQuery);
