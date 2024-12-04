{
    before: function(response, vars, form, objs) {
        var state = vars["state"];
        var select = $(".selectpicker[name='" + state + "']");
        var text = $(".form-control[name='" + state + "']");
        text.addClass("d-none").val("").removeAttr("required").attr("data-required", true).prop("disabled", true);
        select.addClass("d-none").removeAttr("required").attr("data-required", true).prop("disabled", true);
        select.closest(".bootstrap-select").addClass("d-none");
        text.closest(".form-group").addClass("loading-hide");
        //return true;
    },
    after: function(response, vars, form, objs) {
        var use_select = false;
        var state = vars["state"];
        var select = $(".selectpicker[name='" + state + "']");
        var text = $(".form-control[name='" + state + "']");

        text.val("").closest(".form-group").removeClass("loading-hide");

        if (response != "false" && response != "[]") {
            if (Object.keys(response).length > 0) {
                use_select = true;
            }
        }

        if (use_select) {
            var val = select.data("val");
            var options = ""; //<option value=''>Please Choose</option>";
            for (var i in response) {
                //var checked = i==val?"checked":"";
                options += "<option value='" + i + "'>" + response[i] + "</option>";
            }
            select.removeClass("d-none").removeAttr("data-required").attr("required", true).prop("disabled", false);
            select.closest(".bootstrap-select").removeClass("d-none");
            select.html(options).selectpicker("refresh");
            if (typeof response[val] !== "undefined") {
                select.val(val);
                select.selectpicker("render");
            } else {
                select.find("option").first().prop("selected", true);
                select.selectpicker("render");
            }
            text.addClass("d-none").removeAttr("required").attr("data-required", true).prop("disabled", true);
        } else {
            select.html("").selectpicker("refresh");
            select.addClass("d-none").removeAttr("required").attr("data-required", true).prop("disabled", true);
            select.closest(".bootstrap-select").addClass("d-none");
            text.removeClass("d-none").removeAttr("data-required").attr("required", true).prop("disabled", false);
        }
        text.closest(".form-group").removeClass("loading");
        if (!text.parent().is(':visible') && !select.parent().is(':visible')) {
            text.attr("data-required", true).prop("disabled", true);
            select.attr("data-required", true).prop("disabled", true);
        }
    }
};
