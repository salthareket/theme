{
    required: ["bootbox", "leaflet"],
    before: function(response, vars, form, objs) {
        const className = `modal-map loading ${vars.class || ''}`;
        const scrollable = typeof bool === 'function' ? bool(vars.scrollable, false) : !!vars.scrollable;
        const close      = typeof bool === 'function' ? bool(vars.close, true) : vars.close !== false;

        const dialog = bootbox.dialog({
            className:     className,
            title:         '<div></div>',
            message:       '<div></div>',
            closeButton:   close,
            size:          (typeof IsBlank === 'function' ? !IsBlank(vars.size) : vars.size) ? vars.size : 'xl',
            scrollable:    scrollable,
            backdrop:      true,
            buttons:       {},
            onHidden: function() {
                if (response && response.abort) response.abort();
            }
        });

        if (vars.fullscreen) { dialog.find(".modal-dialog").addClass("modal-fullscreen"); }

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
            if (response.data.title)   { modal.find(".modal-title").html(response.data.title); }
            if (response.data.content) { modal.find(".modal-body").html(response.data.content); }
        }

        // leaflet yüklü değilse yükle, yüklüyse direkt init et
        // modal_load_plugins_then_init dependencies zincirini de hallediyor
        modal_load_plugins_then_init({ "leaflet": "init_leaflet" }, modal);

        modal.removeClass("loading");
    }
};
