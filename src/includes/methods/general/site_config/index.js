{
    init: function(meta = []) {
        var query = new ajax_query();
        query.method = "site_config";
        let vars = {
            meta : meta
        }
        query.vars = vars;
        query.request();
    },
    after: function(response, vars, form) {
        site_config = response;
        if(site_config.hasOwnProperty("nonce")){
            if(!IsBlank(site_config)){
               ajax_request_vars["ajax_nonce"] = site_config.nonce;
            }
        }
        if (site_config.hasOwnProperty("favorites")) {
            if(!IsBlank(favorites)){
                var favorites = $.parseJSON(site_config.favorites);
                if (favorites.length > 0) {
                    debugJS(favorites)
                    $(".nav-item[data-type='favorites']").addClass("active");
                    $(".btn-favorite").each(function() {
                        var id = parseInt($(this).attr("data-id"));
                        $(this).removeClass("active");
                        debugJS();
                        if (inArray(id, favorites)) {
                            $(this).addClass("active");
                        }
                    });
                }                
            }

        }

        if (site_config.cart > 0) {
            var counter = $(".nav-item[data-type='cart'] > a").find(".notification-count");
            if (counter.length == 0) {
                $(".nav-item[data-type='cart'] > a").prepend("<div class='notification-count'>" + site_config.cart + "</div>");
            }
        }
        $("body").removeClass("not-logged");
        if (site_config.logged) {
            get_notifications();
        }
    }
};