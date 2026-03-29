{
    required: ["bootbox", "contact-form-7"],
    before: function(response, vars, form, objs) {
        modal_create_dialog(vars, response, objs, { className: 'modal-form' });
        objs.modal.find('.bootbox-close-button').addClass('btn-close').empty();
        // Özel modal callback (varsa)
        if (vars.id && window['modal_' + vars.id]) { window['modal_' + vars.id](); }
        return response;
    },
    after: function(response, vars, form, objs) {
        var modal = objs.modal;
        if (response.error) return modal_handle_error(response, modal);
        modal_set_content(response, modal);

        // CF7 uyandır
        if (window.AppCF7) { window.AppCF7.initForms(modal); }

        // Autosize
        if (typeof isLoadedJS === 'function' && isLoadedJS('autosize') && typeof autosize === 'function') {
            autosize(modal.find('textarea'));
        }

        // Defaults input — form alanlarını önceden doldur
        var $defaults = modal.find("input[name='defaults']");
        if ($defaults.length > 0 && !IsBlank($defaults.val())) {
            try {
                var params = JSON.parse($defaults.val().replace(/'/g, '"'));
                Object.keys(params).forEach(function(key) {
                    var $el = modal.find("[name='" + key + "']");
                    if ($el.length) {
                        $el.val(params[key]);
                        modal.find('.defaults-' + key).removeClass('d-none');
                    }
                });
            } catch(e) { console.error('Defaults parse error:', e); }
        }

        modal.removeClass('loading');

        // Selectpicker
        var $selects = modal.find('.selectpicker');
        if ($selects.length && $.fn.selectpicker) { $selects.selectpicker(); }

        // ReCaptcha
        if (typeof recaptchaCallback !== 'undefined') { recaptchaCallback(); }
    }
};
