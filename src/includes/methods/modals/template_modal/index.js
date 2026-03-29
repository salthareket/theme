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
        modal_load_plugins_then_init((response.data && response.data.plugins) || {}, modal);
    }
};
