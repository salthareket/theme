$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        console.log(response)
        if(!form){
           form = objs.form;
        }
        if(form.length){
            form.addClass("loading-process");
        }
    },
    after: function(response, vars, form, objs) {
        console.log(response)
        if(!form){
           form = objs.form;
        }
        if(form.length){
            form.removeClass("loading-process");
        }
        response_view(response);
    }
};