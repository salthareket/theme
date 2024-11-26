{
    before: function(response, vars, form, objs) {
        debugJS(response)
        $("body").addClass("loading");
        /*var comment_new = [];
        for (var i = 0; i < vars["comment"].length; i++) {
            var title = vars["comment_title"][i];
            var comment = vars["comment"][i];
            comment_new.push({ title: title, comment: comment });
        }
        delete vars["comment_title"];
        vars["comment"] = JSON.stringify(comment_new); //comment_new;*/
        response["vars"] = vars;
        $(form).addClass("form-reviewed");
        return response;
        //ajax_hooks["comment_product"]["after"](response, vars, form, objs);
        /*var query = new ajax_query();
            query.method = form.data("ajax-method");//create_tour_plan";
            query.vars   = vars;
            query.form   = {};
            query.request();*/
        //$(form).removeClass("form-reviewed");
        //return false;
    },
    after: function(response, vars, form, objs) {
        $("#form-comment-product").find("textarea").addClass("form-control-editable");
        if (!IsBlank(response.data)) {
            $("#form-comment-product").find("input[name='comment_id']").val(response.data);
        }
        $("#form-comment-product").find(".btn-submit").html("Update Your Comment");
        form_control_editable();
        $("body").removeClass("loading");
        $(form).removeClass("form-reviewed");
        _alert("", response.message);
    }
};