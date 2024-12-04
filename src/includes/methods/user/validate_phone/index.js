$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        if(response.error){
           $("body").removeClass("loading-process"); 
        }
        response_view(response);
    }
}