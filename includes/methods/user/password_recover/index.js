$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {
        form.addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        form.closest(".loading-process").removeClass("loading-process");
        var holder = form.find(".message");
        holder.find(".alert").remove();
        if (response.error) {
            holder.prepend("<div class='alert alert-danger text-center'>" + response.message + "</div>");
        } else {
            holder.empty().html("<div class='alert alert-success text-center'>" + response.message + "</div>");
        }
    }
};