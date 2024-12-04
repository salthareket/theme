$required_setting = ENABLE_SEARCH_HISTORY;

{
    before: function(response, vars, form, objs) {
        debugJS(objs)
        objs.addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        objs.removeClass("loading-process").html(response.html);
        //$("body").removeClass("loading-process");
    }
}