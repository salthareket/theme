$required_setting = ENABLE_FOLLOW;

{
    before: function(response, vars, form, objs) {
        objs.btn.wrapInner('<span style="display:block;opacity:.3;text-indent:0;"></span>')
        objs.btn.addClass("loading");
    },
    after: function(response, vars, form, objs) {
        if(response.error){
            objs.btn.removeClass("loading disabled")
            response_view(response);
            return;
        }
        objs.btn.html(objs.btn.text())
        objs.btn.removeClass("loading disabled").html(response.html);
        var count = 0;
        var counter = $(".count-followers[data-id='"+vars.id+"']");
        if(counter.length > 0){
           count = parseInt(counter.text());
        }

        if(isNumber(response.data)){
            objs.btn.addClass("active").addClass("loading-light").removeClass("loading-dark");
            count += 1;
        }else{
            objs.btn.removeClass("active").addClass("loading-dark").removeClass("loading-light");
            count -= 1;
        }
        count = count<0?0:count;
        if(counter){
            counter.html(count);
        }
    }
}