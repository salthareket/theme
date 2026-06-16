$required_setting = ENABLE_IP2COUNTRY;

{
    before: function(response, vars, form, objs) {
        $(".modal.show .modal-content").addClass("loading-process")
    },
    after: function(response, vars, form, objs) {
        response_view(response);
    }
}