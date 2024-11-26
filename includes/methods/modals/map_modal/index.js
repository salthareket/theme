{
    before: function(response, vars, form, objs) {
        if(!isLoadedJS("bootbox")){
            alert("Bootbox required");
            return
        }
        var className = "modal-map loading " + (vars.class?vars.class:'');
        var scrollable = bool(vars.scrollable, false);
        var close = bool(vars.close, true);
        var dialog = bootbox.dialog({
            className: className,
            title: '<div></div>',
            message: '<div></div>',
            closeButton: close,
            size: !IsBlank(vars["size"]) ? vars["size"] : 'xl',
            scrollable: scrollable,
            backdrop: true,
            buttons: {},
            onHidden: function(e) {
                response.abort();
            }
        });
        if(vars.fullscreen){
            dialog.find(".modal-dialog").addClass("modal-fullscreen");
        }
        if(vars.modal){
            vars.modal.forEach(item => {
                for (const [key, value] of Object.entries(item)) {
                    dialog.find("."+key).addClass(value);
                }
            });
        }
        objs["modal"] = dialog;
        var id = generateCode(5);
        dialog.attr("id", id);
        response.objs = {
            "modal": dialog,
            "btn": objs.btn
        }
        return response;
    },
    after: function(response, vars, form, objs) {
        if(!isLoadedJS("bootbox")){
            return
        }
        var modal = objs.modal;
        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message) {
                //_alert("", response.message);
                response_view(response);
            }
            return false;
        }
        if (response.data.hasOwnProperty("title")) {
            modal.find(".modal-title").html(response.data.title);
        }
        modal.find(".modal-body").html(response.data.content);
        modal.removeClass("loading");
    }
};