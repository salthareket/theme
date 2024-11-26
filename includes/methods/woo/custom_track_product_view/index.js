$required_setting = ENABLE_ECOMMERCE;

{
    init: function($vars) {
        var query = new ajax_query();
        query.method = "custom_track_product_view";
        query.vars = $vars;
        query.request();
    }
};
