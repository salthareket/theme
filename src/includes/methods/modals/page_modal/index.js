{
    required: ["bootbox"],
    before: function(response, vars, form, objs) {
        // Modern değişken atamaları
        const className = `modal-page loading ${vars.class || ''}`;
        const scrollable = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;
        const sizeValue = (typeof IsBlank === 'function' ? !IsBlank(vars.size) : vars.size) ? vars.size : 'xl';

        const dialog = bootbox.dialog({
            className: className,
            title: "<div></div>",
            message: '<div></div>',
            closeButton: close,
            size: sizeValue,
            scrollable: scrollable,
            centerVertical: true,
            backdrop: true, // Eksikti, güvenli olması için ekledim
            buttons: {},
            onHidden: function() {
                if (response && response.abort) response.abort();
            }
        });

        // Fullscreen desteği
        if (vars.fullscreen) {
            dialog.find(".modal-dialog").addClass("modal-fullscreen");
        }

        // Boyut sınıfı kontrolü (Daha temiz hale getirildi)
        if (sizeValue) {
            dialog.find(".modal-dialog").addClass(`modal-${sizeValue}`);
        }

        // Özel modal class'larını döngüyle basma
        if (Array.isArray(vars.modal)) {
            vars.modal.forEach(item => {
                Object.entries(item).forEach(([key, value]) => {
                    dialog.find(`.${key}`).addClass(value);
                });
            });
        }

        objs["modal"] = dialog;

        // Eşsiz ID üretimi
        const modalId = (typeof generateCode === 'function') ? generateCode(5) : `gen_modal_${Date.now()}`;
        dialog.attr("id", modalId);

        response.objs = {
            "modal": dialog,
            "btn": objs.btn
        };

        return response;
    },

    after: function(response, vars, form, objs) {
        const modal = objs.modal;

        // Hata Yönetimi
        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message && typeof response_view === 'function') {
                response_view(response);
            }
            return false;
        }

        // Başlık ve İçerik Basma (Performanslı seçiciler)
        if (response.data) {
            const $title = modal.find(".modal-title");
            const $body = modal.find(".modal-body");

            if (response.data.title !== undefined) {
                $title.html(response.data.title);
            }
            if (response.data.content !== undefined) {
                $body.html(response.data.content);
            }

            if (Array.isArray(response.data.required_js) && response.data.required_js.length > 0) {
                isLoadedJS(response.data.required_js, true, function() {
                    console.log("Sülale tamam abi, kütüphaneler yüklendi.");
                });
            }
        }

        // --- FORM UYANDIRMA ---
        // Gelen içerik içinde form varsa otomatik init eder
        if (modal.find(".wpcf7-form").length > 0 && window.AppCF7) {
            window.AppCF7.initForms(modal);
        }

        modal.removeClass("loading");


    }
};