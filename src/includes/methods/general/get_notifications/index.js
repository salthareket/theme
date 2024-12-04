$required_setting = ENABLE_NOTIFICATIONS;

{
    before: function(response, vars, form, objs) {
        //$("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        debugJS(response, vars, form, objs);
        objs.obj.find(">.card-body").append(response.html);
        //$("body").removeClass("loading-process");
    }
}