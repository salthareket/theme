$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        response_view(response);
        $("body").removeClass("loading-process");
    }
}