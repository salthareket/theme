{
    required: ["bootbox"],
    before: function(response, vars, form, objs) {
        modal_create_dialog(vars, response, objs, { className: 'modal-page' });
        return response;
    },
    after: function(response, vars, form, objs) {
        var modal = objs.modal;
        if (response.error) return modal_handle_error(response, modal);
        modal_set_content(response, modal);
        modal.removeClass('loading');

        // CF7 form varsa uyandır
        if (modal.find('.wpcf7-form').length > 0 && window.AppCF7) {
            window.AppCF7.initForms(modal);
        }

        // required_js listesini yükle → init et
        var required = (response.data && Array.isArray(response.data.required_js)) ? response.data.required_js : [];
        var pluginMap = {};
        required.forEach(function(k) { pluginMap[k] = ''; });
        modal_load_plugins_then_init(pluginMap, modal);
    }
};
