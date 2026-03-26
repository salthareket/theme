{
    required: ["bootbox"],
    before: function(response, vars, form, objs) {
        const className   = `modal-page loading ${vars.class || ''}`;
        const scrollable  = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close       = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;
        const sizeValue   = (typeof IsBlank === 'function' ? !IsBlank(vars.size) : vars.size) ? vars.size : 'xl';

        const dialog = bootbox.dialog({
            className:     className,
            title:         "<div></div>",
            message:       '<div></div>',
            closeButton:   close,
            size:          sizeValue,
            scrollable:    scrollable,
            centerVertical: true,
            backdrop:      true,
            buttons:       {},
            onHidden: function() {
                if (response && response.abort) response.abort();
            }
        });

        if (vars.fullscreen) { dialog.find(".modal-dialog").addClass("modal-fullscreen"); }
        if (sizeValue)       { dialog.find(".modal-dialog").addClass(`modal-${sizeValue}`); }

        if (Array.isArray(vars.modal)) {
            vars.modal.forEach(item => {
                Object.entries(item).forEach(([key, value]) => {
                    dialog.find(`.${key}`).addClass(value);
                });
            });
        }

        objs["modal"] = dialog;
        const modalId = (typeof generateCode === 'function') ? generateCode(5) : `gen_modal_${Date.now()}`;
        dialog.attr("id", modalId);
        response.objs = { "modal": dialog, "btn": objs.btn };
        return response;
    },

    after: function(response, vars, form, objs) {
        const modal = objs.modal;

        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message && typeof response_view === 'function') { response_view(response); }
            return false;
        }

        if (response.data) {
            if (response.data.title   !== undefined) { modal.find(".modal-title").html(response.data.title); }
            if (response.data.content !== undefined) { modal.find(".modal-body").html(response.data.content); }
        }

        modal.removeClass("loading");

        // CF7 form varsa uyandır
        if (modal.find(".wpcf7-form").length > 0 && window.AppCF7) {
            window.AppCF7.initForms(modal);
        }

        // PHP'den gelen required_js listesini yükle → hepsi hazır olunca init et
        var required = (response.data && Array.isArray(response.data.required_js)) ? response.data.required_js : [];
        if (required.length > 0) {
            // Array'i { key: "" } map'ine çevir — modal_load_plugins_then_init bunu anlıyor
            var pluginMap = {};
            required.forEach(function(k) { pluginMap[k] = ""; });
            modal_load_plugins_then_init(pluginMap, modal);
        } else {
            if (typeof init_functions === 'function') { init_functions(); }
        }
    }
};
