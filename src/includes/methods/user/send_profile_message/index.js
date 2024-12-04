$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        form.closest(".modal-content").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        if (IsBlank(response.redirect)) {
            form.closest(".modal-content").removeClass("loading-process");
        }else{
            response_view(response);
        }
    }
}