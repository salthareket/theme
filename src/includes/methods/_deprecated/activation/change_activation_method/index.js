$required_setting = ENABLE_MEMBERSHIP_ACTIVATION;
{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        response_view(response);
    }
}