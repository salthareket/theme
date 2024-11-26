$required_setting = DISABLE_REVIEW_APPROVE;

{
    before: function(response, vars, form, objs) {
        debugJS(vars);
        debugJS(objs)
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        $("body").removeClass("loading-process");
        response_view(response);
    }
};