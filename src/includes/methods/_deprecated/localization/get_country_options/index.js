{
    before: function(response, vars, form, objs) {

        if (objs.hasClass("selectpicker")) {
            objs.parent(".bootstrap-select").addClass("loading-xs loading-process");
        } else {
            //$("body").addClass("loading-process");
            objs.find("option").not(".d-none").remove();
            var text = objs.find("option").first().text();
            objs.find("option").first().attr("data-title", text);
            var text = objs.find("option").first().text("Loading...");
        }
    },
    after: function(response, vars, form, objs) {

        if (objs.hasClass("selectpicker")) {
            objs.parent(".bootstrap-select").removeClass("loading-xs loading-process");
        } else {
            $("body").removeClass("loading-process");
            var text = objs.find("option").first().data("title");
            objs.find("option").first().text(text);
        }
        if (response) {
            if (!objs.data("chain-all")) {
                objs.find("option[value=''].all").addClass(" ajax d-none");
            }
            objs.find("option").not("[value='']").remove();
            var selected = false;
            for (var i = 0; i < response.length; i++) {
                objs.append("<option class='" + (IsBlank(response[i].slug) ? "all" : "") + "'' value='" + response[i].slug + "' " + (response[i].selected ? "selected" : "") + ">" + response[i].name + "</option>");
                if (!selected) {
                    selected = response[i].selected;
                }
            }
            if (objs.hasClass("selectpicker")) {
                if (!selected) {
                    objs.find("option").not(".d-none").first().prop("selected", true);
                } else {
                    objs.val(selected);
                }
                objs.selectpicker("refresh");
                objs.selectpicker("show");
            }
            objs.trigger("change");
        }
    }
}