$required_setting = ENABLE_ECOMMERCE;

{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        response_view(response);
    }
};