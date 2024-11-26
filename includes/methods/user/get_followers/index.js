$required_setting = ENABLE_FOLLOW;

{
     before: function(response, vars, form, objs) {
       
    },
    after: function(response, vars, form, objs) {
     
        debugJS(response, vars);
        if (vars.posts_per_page) {
            objs.obj.find(".list-cards").append(response.html);
        } else {
            objs.obj.find(".list-cards").html(response.html);
        }
       
    }
}