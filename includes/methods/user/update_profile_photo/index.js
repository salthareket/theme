$required_setting = ENABLE_MEMBERSHIP;

{
    before: function(response, vars, form, objs) {

        form.find(".image-uploader").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {

        form.find(".image-uploader").removeClass("loading-process");
    }
}