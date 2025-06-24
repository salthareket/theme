{
    required : "bootbox",
    before: function(response, vars, form, objs) {
       /* if(!isLoadedJS("bootbox")){
            alert("Bootbox required");
            return
        }*/
        var className = "modal-form loading " + (vars.class?vars.class:'');
        var scrollable = bool(vars.scrollable, false);
        var close = bool(vars.close, true);
        var dialog = bootbox.dialog({
            className: className,
            title: "<div></div>",
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
        dialog.data("response", response);
        dialog.find(".bootbox-close-button").addClass("btn-close").empty();
        objs["modal"] = dialog;
        response.objs = {
            "modal": dialog,
            "btn": objs.btn
        }
        if(window["modal_"+vars["id"]]){
           window["modal_"+vars["id"]](); 
        }


        /*function loadWPCF7Script(callback="") {
            if (!window.wpcf7) {
                var scripts = [
                    ajax_request_vars.url + 'wp-includes/js/dist/vendor/wp-polyfill.min.js', // WordPress polyfill
                    ajax_request_vars.url + 'wp-content/plugins/contact-form-7/includes/js/index.js' // Contact Form 7 JavaScript dosyası
                ];
                var index = 0;
                function loadNextScript() {
                    if (index < scripts.length) {
                        var script = document.createElement('script');
                        script.src = scripts[index];
                        script.onload = function() {
                            index++;
                            loadNextScript();
                        };
                        document.head.appendChild(script);
                    } else {
                        //callback();
                    }
                }
            }
            loadNextScript();
        }
        loadWPCF7Script("");*/
        return response;
    },
    after: function(response, vars, form, objs) {
        /*if(!isLoadedJS("bootbox")){
            return
        }*/
        var modal = objs.modal;
        if (response.error) {
            modal.addClass("remove-on-hidden").modal("hide");
            if (response.message) {
                //_alert("", response.message);
                response_view(response);
            }
            return false;
        }
        modal.find(".modal-title").html(response.data.title);
        modal.find(".modal-body").html(response.data.content);
        initContactForm();
        root.form.init();
        if(isLoadedJS("autosize")){
            autosize($('textarea'));
        }
        if (modal.find("input[name='defaults']").length > 0) {
            var params = modal.find("input[name='defaults']").val();
            if (!IsBlank(params)) {
                params = params.replaceAll("'", '"');
                params = $.parseJSON(params);
                if (Object.keys(params).length > 0) {
                    for (param in params) {
                        var el = $("[name='" + param + "']");
                        if (el.length > 0) {
                            el.val(params[param]);
                            el.closest(".defaults-" + param).removeClass("d-none");
                        }
                    }
                }
                debugJS(params);
            }
        }
        modal.removeClass("loading");
        if(modal.find(".selectpicker").length > 0){
           modal.find(".selectpicker").selectpicker();
        }
        if(typeof recaptchaCallback !== "undefined"){
            recaptchaCallback();
        }
    }
};