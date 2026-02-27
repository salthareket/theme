{
    required: ["bootbox"],
    before: function(response, vars, form, objs) {
        // Buton href'ini URL olarak sakla
        if (objs.btn && objs.btn.attr("href")) {
            response["vars"]["url"] = objs.btn.attr("href");
        }

        const className = `modal-page loading ${vars.class || ''}`;
        const scrollable = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;
        
        const dialog = bootbox.dialog({
            className: className,
            title: "<div></div>",
            message: '<div></div>',
            closeButton: close,
            size: (typeof IsBlank === 'function' ? !IsBlank(vars.size) : vars.size) ? vars.size : 'xl',
            scrollable: scrollable,
            centerVertical: true,
            backdrop: true,
            buttons: {},
            onHidden: function() {
                if (response && response.abort) response.abort();
            }
        });

        // Fullscreen ve özel modal class atamaları
        if (vars.fullscreen) {
            dialog.find(".modal-dialog").addClass("modal-fullscreen");
        }

        if (Array.isArray(vars.modal)) {
            vars.modal.forEach(item => {
                Object.entries(item).forEach(([key, value]) => {
                    dialog.find(`.${key}`).addClass(value);
                });
            });
        }

        objs["modal"] = dialog;

        // ID üretme kısmını daha güvenli hale getirelim
        const modalId = (typeof generateCode === 'function') ? generateCode(5) : `modal_${Math.random().toString(36).substr(2, 5)}`;
        dialog.attr("id", modalId);

        response.objs = {
            "modal": dialog,
            "btn": objs.btn
        };

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

        // Başlık setleme (Vars'tan geliyorsa)
        if (vars && vars.title !== undefined) {
            modal.find(".modal-title").html(vars.title);
        }

        // İçeriği basma ( response.html senin PHP'den gelen temizlenmiş HTML'in )
        if (response.html) {
            modal.find(".modal-body").html(response.html);
        }

        // --- KRİTİK EKLEME: Gelen sayfa içinde form varsa uyandır ---
        // Eğer gelen HTML içinde bir CF7 formu varsa, bizim handler'ı çalıştıralım.
        if (modal.find(".wpcf7-form").length > 0 && window.ContactFormHandler) {
            window.ContactFormHandler.initForms(modal);
        }

        modal.removeClass("loading");
    }
};