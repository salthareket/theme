{
    required: ["bootbox", "leaflet"],
    before: function(response, vars, form, objs) {
        const className = `modal-map loading ${vars.class || ''}`;
        const scrollable = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;
        
        const dialog = bootbox.dialog({
            className: className,
            title: '<div></div>',
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

        if (Array.isArray(vars.modal)) {
            vars.modal.forEach(item => {
                Object.entries(item).forEach(([key, value]) => {
                    dialog.find(`.${key}`).addClass(value);
                });
            });
        }

        objs["modal"] = dialog;
        
        const modalId = (typeof generateCode === 'function') ? generateCode(5) : `map_modal_${Date.now()}`;
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

        // 1. Önce Başlık ve İçeriği Bas (Harita div'i burada gelir)
        if (response.data) {
            if (response.data.title) modal.find(".modal-title").html(response.data.title);
            if (response.data.content) modal.find(".modal-body").html(response.data.content);
        }

        // 2. Kütüphaneyi ve Haritayı Başlat
        // 'leaflet' zaten required listesinde olduğu için çoğu zaman yüklenmiştir 
        // ama isLoadedJS callback'i ile işi sağlama alıyoruz.
        isLoadedJS("leaflet", true, function() {
            if (typeof init_leaflet === "function") {
                // Harita container'ının DOM'da olduğundan %100 eminiz
                init_leaflet(modal); 
            }
            
            // Eğer harita içinde CF7 formu varsa (nadirdir ama olur mu olur)
            if (modal.find(".wpcf7-form").length > 0 && window.ContactFormHandler) {
                window.ContactFormHandler.initForms(modal);
            }

            modal.removeClass("loading");
            
            // Leaflet haritaları gizli/yeni açılan containerlarda bazen render hatası yapar.
            // Bu yüzden 'invalidateSize' tetiklemek gerekebilir.
            setTimeout(() => {
                if (window.L && window.currentMapInstance) { 
                    window.currentMapInstance.invalidateSize();
                }
            }, 200);
        });
    }
};