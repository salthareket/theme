{
    required: ["bootbox", "contact-form-7"],
    before: function(response, vars, form, objs) {
        // Modern atamalar ve varsayılan değer kontrolleri
        const className = "modal-form loading " + (vars.class ? vars.class : '');
        const scrollable = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;
        
        const dialog = bootbox.dialog({
            className: className,
            title: "<div></div>",
            message: '<div></div>',
            closeButton: close,
            size: (typeof IsBlank === 'function' ? !IsBlank(vars.size) : vars.size) ? vars.size : 'xl',
            scrollable: scrollable,
            backdrop: true,
            buttons: {},
            onHidden: function() {
                if (response && response.abort) response.abort();
            }
        });

        if (vars.fullscreen) {
            dialog.find(".modal-dialog").addClass("modal-fullscreen");
        }

        if (vars.modal && Array.isArray(vars.modal)) {
            vars.modal.forEach(item => {
                Object.entries(item).forEach(([key, value]) => {
                    dialog.find("." + key).addClass(value);
                });
            });
        }

        dialog.data("response", response);
        
        dialog.find(".bootbox-close-button").addClass("btn-close").empty();
        
        objs["modal"] = dialog;
        response.objs = {
            "modal": dialog,
            "btn": objs.btn
        };

        if (vars.id && window["modal_" + vars.id]) {
            window["modal_" + vars.id]();
        }

        return response;
    },

    after: function(response, vars, form, objs) {
        const modal = objs.modal;
        
        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message && typeof response_view === 'function') {
                response_view(response);
            }
            return false;
        }

        // İçerik basma
        if (response.data) {
            if (response.data.title) modal.find(".modal-title").html(response.data.title);
            if (response.data.content) modal.find(".modal-body").html(response.data.content);
        }

        // --- Form ve Plugin Başlatmaları ---
        // Yeni yazdığımız Class mevcutsa onu kullanır, yoksa eski func'a düşer
        if (window.AppCF7) {
            window.AppCF7.initForms(modal);
        }

        if (typeof isLoadedJS === 'function' && isLoadedJS("autosize")) {
            if (typeof autosize === 'function') autosize(modal.find('textarea'));
        }

        // Default Değerleri İşleme (Modernize Edildi)
        const $defaultsInput = modal.find("input[name='defaults']");
        if ($defaultsInput.length > 0) {
            let paramsStr = $defaultsInput.val();
            const isBlank = typeof IsBlank === 'function' ? IsBlank(paramsStr) : !paramsStr;
            
            if (!isBlank) {
                try {
                    // Tek tırnakları çift tırnağa çevirerek güvenli parse
                    paramsStr = paramsStr.replace(/'/g, '"');
                    const params = JSON.parse(paramsStr);
                    
                    if (Object.keys(params).length > 0) {
                        Object.keys(params).forEach(key => {
                            const $el = modal.find("[name='" + key + "']");
                            if ($el.length > 0) {
                                $el.val(params[key]);
                                modal.find(".defaults-" + key).removeClass("d-none");
                            }
                        });
                    }
                    if (typeof debugJS === 'function') debugJS(params);
                } catch (e) {
                    console.error("Defaults JSON parse error:", e);
                }
            }
        }

        modal.removeClass("loading");

        // Selectpicker Başlatma
        const $selects = modal.find(".selectpicker");
        if ($selects.length > 0 && $.fn.selectpicker) {
            $selects.selectpicker();
        }

        // ReCaptcha Desteği
        if (typeof recaptchaCallback !== "undefined") {
            recaptchaCallback();
        }
    }
};