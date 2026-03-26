{
    required: ["bootbox"],
    before: function(response, vars, form, objs) {
        // Buton href'ini URL olarak sakla
        if (objs.btn && objs.btn.attr('href')) {
            response.vars.url = objs.btn.attr('href');
        }
        modal_create_dialog(vars, response, objs, { className: 'modal-page' });
        return response;
    },
    after: function(response, vars, form, objs) {
        var modal = objs.modal;
        if (response.error) return modal_handle_error(response, modal);

        if (vars.title !== undefined) modal.find('.modal-title').html(vars.title);
        if (response.html)            modal.find('.modal-body').html(response.html);

        modal.removeClass('loading');
    }
};
