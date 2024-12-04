{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        $("body").removeClass("loading-process");
        response_view(response);
    }
};
