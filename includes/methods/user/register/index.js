$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        form.addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        debugJS(response)
        form.removeClass("loading-process");
        response_view(response);
    }
};