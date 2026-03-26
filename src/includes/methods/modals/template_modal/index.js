{
    required : ["bootbox"],
    before: function(response, vars, form, objs) {
        var className = "modal-page loading " + (vars.class ? vars.class : '');
        var scrollable = bool(vars.scrollable, false);
        var close      = bool(vars.close, true);
        var dialog = bootbox.dialog({
            className:     className,
            title:         "<div></div>",
            message:       '<div></div>',
            closeButton:   close,
            size:          !IsBlank(vars["size"]) ? vars["size"] : 'xl',
            scrollable:    scrollable,
            centerVertical: true,
            buttons:       {},
            onHidden: function(e) { response.abort(); }
        });
        if (vars.fullscreen) { dialog.find(".modal-dialog").addClass("modal-fullscreen"); }
        if (vars.modal) {
            vars.modal.forEach(item => {
                for (const [key, value] of Object.entries(item)) {
                    dialog.find("." + key).addClass(value);
                }
            });
        }
        objs["modal"] = dialog;
        var id = generateCode(5);
        dialog.attr("id", id);
        response.objs = { "modal": dialog, "btn": objs.btn };
        return response;
    },
    after: function(response, vars, form, objs) {
        var modal = objs.modal;
        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message) { response_view(response); }
            return false;
        }

        // İçeriği modal'a yerleştir
        if (response.data.hasOwnProperty("content")) {
            modal.find(".modal-content").html(response.data.content);
        } else {
            if (response.data.hasOwnProperty("title")) { modal.find(".modal-title").html(response.data.title); }
            if (response.data.hasOwnProperty("body"))  { modal.find(".modal-body").html(response.data.body); }
        }
        modal.removeClass("loading");

        // PHP'den gelen plugin listesi varsa yükle → yoksa direkt init
        var plugins = (response.data && response.data.plugins) ? response.data.plugins : {};
        modal_load_plugins_then_init(plugins, modal);
    }
};
