{
    before: function(response, vars, form, objs) {
        if (objs.hasClass("selectpicker")) {
            objs.parent(".bootstrap-select").addClass("loading-xs loading-process");
        } else {
            //$("body").addClass("loading-process");
            objs.wrap("<div class='loading-xs loading-process position-relative'></div>");
        }
    },
    after: function(response, vars, form, objs) {
        var show_count = bool(objs.data("count"), false);
        var count_type = objs.data("count-type");

        if (objs.hasClass("selectpicker")) {
            objs.parent(".bootstrap-select").removeClass("loading-xs loading-process");
        } else {
            //$("body").removeClass("loading-process");
            objs.unwrap();
        }
        if (response) {
            objs.find("option[value='']").addClass("d-none");
            objs.find("option").not("[value='']").remove();
            var selected = false;
            for (var i = 0; i < response.length; i++) {
                var count = response[i][count_type+"_count"];
                debugJS(response[i].name+" - "+show_count+" - "+count);
                if(show_count && count == 0){
                    continue;
                }
                var counter = show_count?"("+count+")":"";
                objs.append("<option value='" + response[i].id + "' " + (vars.selected == response[i].id ? "selected" : "") + ">" + response[i].name + counter + "</option>");
            }
            /*if (!selected) {
                selected = vars.selected;
            }*/
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