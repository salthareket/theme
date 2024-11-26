$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        if (vars.action == "expertise" || vars.action == "upgrade") {
            //packs_data();
        }
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        if (vars.action == "upgrade_management") {
            $(".table-expert-request").find("#user-" + vars.id).remove();
        }
        var required_pages = "";
        debugJS(response);
        if (response["data"].hasOwnProperty("profile_completion")) {
            if (response["data"].hasOwnProperty("profile_completion").success) {
                $(".alert-notification-top").remove();
                $("a[data-action='account_settings'] .icon").remove();
            }
            for(item in response["data"].profile_completion) {
                if(item != "success"){
                    if(response["data"].profile_completion[item].success){
                      $("a[data-action='"+item+"'] .icon").remove();
                    }else{
                      required_pages += "<a href='"+site_config.base_urls.account+item+"' class='btn btn-primary mx-2 btn-extend'>"+item+"</a>"
                    }
                }
            }
            if(!IsBlank(required_pages)){
                required_pages = "<h5 class='text-danger mt-3'>Please complete following pages</h5>"+required_pages;
                response["message"] = response["message"] + required_pages;
            }
        }
        response_view(response);
        $("body").removeClass("loading-process");
    }
}