$required_setting = ENABLE_MEMBERSHIP_ACTIVATION;
{
    before: function(response, vars, form, objs) {
        $("body").addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        if(response.data.hasOwnProperty("otp_id")){
            $("input[name='otp_id']").val(response.data.otp_id);
            var obj = $(".countdown");
            var obj_new = obj.clone();
                obj_new.insertAfter(obj);
                obj.remove();
                obj_new.empty().off().attr("data-event-end", response.data.otp_expiry).removeClass("countdown-init");
            countdown();
        }
        response_view(response);
    }
}