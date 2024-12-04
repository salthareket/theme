$required_setting = ENABLE_ECOMMERCE;

{
    init: function($vars) {
        var query = new ajax_query();
            query.method = "salt_recently_viewed_products";
            query.vars = $vars;
            query.request();
    },
    after: function(response, vars, form, objs) {
        $("#" + vars["id"]).html(response.html).removeClass("loading")
        if (response.html) {
            init_swiper();
        }
    }
};