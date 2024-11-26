$required_setting = ENABLE_NOTIFICATIONS;

if ( typeof acf !== 'undefined' ) {

// show active / passive carriers on accordion header title
    function notification_carriers_activate($container){
        if($container.length > 0){
            var notification_carriers = ["notification_email", "notification_alert", "notification_sms"];
            for(var i=0;i<notification_carriers.length;i++){
                var switcher = $container.find(".acf-row:not(.acf-clone)").find(".acf-field[data-name='"+notification_carriers[i]+"']");
                if(switcher.length > 0){
                    switcher.each(function(){
                        var accordion = $(this).closest("[data-type='accordion']");
                        var checkbox = $(this).find("input[type='checkbox']");
                        checkbox.on("change", function(){
                            if($(this).is(":checked")){
                                accordion.find(".acf-accordion-title").addClass("bg-success text-white");
                            }else{
                                accordion.find(".acf-accordion-title").removeClass("bg-success text-white");
                            }
                        }).trigger("change");
                    });
                }        
            }            
        }
    }
    var $repeater_obj = $(".acf-field[data-name='notifications']");
    if($repeater_obj.length > 0){
        $repeater_obj = acf.getField($repeater_obj.data("key"));
        notification_carriers_activate($($repeater_obj.$el));
        $repeater_obj.$el
        .on('click', '.acf-repeater-add-row', function() {
            notification_carriers_activate($($repeater_obj.$el));
        });
        notification_filters();      
    }

    function notification_filters(){
        //alert("sss")
        var filters = $(".acf-field[data-name='notifications_filter']");
        if(filters.length > 0){
            var roles = filters.find(".acf-field[data-name='notification_role_filter'] select");
            var events = filters.find(".acf-field[data-name='notification_event_filter'] select");
            roles.on("change", function(){
                var $args = {role: roles.val(), event: events.val()};
                notification_filters_apply($args);
            });
            events.on("change", function(){
                var $args = {role: roles.val(), event: events.val()};
                notification_filters_apply($args);
            });
        }
    }
    function notification_filters_apply($args) {
        debugJS($args);
        var $repeater_obj = $(".acf-field[data-name='notifications']");
        var $rows = $repeater_obj.find(".acf-row:not(.acf-clone)");
        //debugJS($rows)
        $rows.each(function() {
            var $row = $(this);
            //debugJS($row);
            var role = $row.find('.acf-field[data-name*="notification_role"] select').val();
            var event = $row.find('.acf-field[data-name*="notification_event"] select').val();
            debugJS($args, role, event);
            //if(($args.role == "" && $args.event == "") || role == $args.role || event == $args.event){
            if(($args.role === "" || role == $args.role) && ($args.event === "" || event == $args.event)){
                $row.removeClass("d-none");
            }else{
                $row.addClass("d-none");
            }
            /*
            if ((selectedRole === 'show-all' || role === selectedRole) && (selectedEvent === 'show-all' || event === selectedEvent)) {
                $row.show();
            } else {
                $row.hide();
            }*/
        });
    }

}