$required_setting = ENABLE_FAVORITES;

{
    before: function(response, vars, form, objs) {
        switch (vars["action"]) {
            case "get":
                break;
        }
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        switch (vars["action"]) {
            case "get":
                var total = pluralize("{} favorite found", "{} favorites found", response.data.total, "Nothing found");
                $(".item-total").html(total);
                if (vars.posts_per_page) {
                    $(".list-cards").append(response.html);
                } else {
                    $(".list-cards").html(response.html);
                }
                star_rating_readonly();
                btn_favorite();
                break;
        }
        $("body").removeClass("loading-process");
        response_view(response);
    }
}