{
    before: function(response, vars, form, objs) {
        $("body").removeClass("loading");
        var modal_id = "#modal_comment_product_detail";
        var index = comment_ids.indexOf(vars["id"].toString());
        var prev = index - 1;
        var next = index + 1;
        if (prev < 0) {
            prev = "";
        }
        if (next == comment_ids.length) {
            next = ""
        }
        if (!IsBlank(prev) || prev == 0) {
            vars["prev"] = comment_ids[prev];
        }
        if (!IsBlank(next)) {
            vars["next"] = comment_ids[next];
        }

        var load_modal = 1;
        if ($(modal_id).length > 0) {
            load_modal = 0;
            $(modal_id).addClass("loading").modal("show");
        } else {
            $("body").addClass("loading");
        }
        vars["load_modal"] = load_modal;
        response["vars"] = vars;
        return response;
    },
    after: function(response, vars, form, objs) {
        var modal_id = "#modal_comment_product_detail";
        if (!response.error) {
            if (!IsBlank(response.html)) {
                if (!vars.load_modal) {
                    $(modal_id).find(".modal-content").html(response.html);
                } else {
                    $("body").append(response.html);
                }
                $(modal_id).modal("show");
            } else {
                if (!IsBlank(response.data)) {
                    $(modal_id).find(".modal-header .modal-title").html(response.data.title);
                    $(modal_id).find(".modal-body").html(response.data.comment);
                } else {
                    _alert("", "error");
                }
            }
        } else {
            //_alert("", "error")
            response_view(response);
        }
        $("body").removeClass("loading");
        $(modal_id).removeClass("loading");
        star_rating_readonly();
    }
};