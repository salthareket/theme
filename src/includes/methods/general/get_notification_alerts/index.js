$required_setting = ENABLE_NOTIFICATIONS;

{
    init: function() {
        if (!site_config.enable_notifications) {
            return false;
        }
        if (IsBlank(ajax_request_vars.title)) {
            ajax_request_vars.title = document.title;
        }
        var query = new ajax_query();
        query.method = "get_notification_alerts";
        query.request();
    },
    after: function(response, vars, form) {
        debugJS(response);
        if(!response.error){
            if (response.data.hasOwnProperty("notifications")) {
                if (response.data.notifications.length > 0) {
                    for (var i = 0; i < response.data.notifications.length; i++) {
                        var notification = response.data.notifications[i];
                        toast_notification(notification);

                        if(notification["update"]){
                            var container = $(notification["update"]["container"]);
                            container.addClass("position-relative started loading-process").html(notification["update"]["html"]);
                            var iframe_id = "#"+$(notification["update"]["html"]).attr("id");
                            $(iframe_id).on('load', function () {
                                container.removeClass("loading-process");
                                iFrameResize({
                                    autoResize : true,
                                    sizeHeight : true,
                                    sizeWidth  : false,
                                    resizeFrom : 'child',
                                    heightCalculationMethod : 'max',
                                    onResized: function(message){
                                        container.height(message.height);
                                    }
                                }, iframe_id);
                            });
                        }

                    }
                }
            }
            var account_dropdown = $(".dropdown-notifications[data-type='account']");
            var messages_dropdown = $(".dropdown-notifications[data-type='messages']");
            var messages_menu_item = $("[data-action='messages'] .icon");
            var notification_menu_item = $("[data-action='notifications'] .icon");
            var notification_type = messages_dropdown.length>0?"messages":"account";

            var messages_dropdown_item = $(".dropdown-notifications[data-type='account']");

            var desktop_counter = $(".dropdown-notifications[data-type='"+notification_type+"'] > a .notification-count");
            var mobile_counter = $(".navbar-toggler .notification-count");
            if (response.data.count.message > 0 || response.data.count.notification > 0) {

                if(response.data.count.notification > 0) {
                    if (desktop_counter.length == 0) {
                        $(".dropdown-notifications[data-type='"+notification_type+"'] > a").prepend('<div class="notification-count">' + response.data.count.notification + '</div>');
                    } else {
                        desktop_counter.html(response.data.count.notification);
                    }
                    if (mobile_counter.length == 0) {
                        $(".navbar-toggler").prepend('<div class="notification-count">' + response.data.count.notification + '</div>');
                    } else {
                        mobile_counter.html(response.data.count.notification);
                    }
                    if(notification_menu_item.length > 0){
                        notification_menu_item.html(response.data.count.notification)
                    }
                    $(".nav-toggler-custom").addClass("has-notification");
                }

                if(response.data.count.message > 0) {
                    if(account_dropdown.find("[data-type='messages']").length > 0){
                       if(account_dropdown.find("[data-type='messages']").find(".notification-count").length > 0){
                          account_dropdown.find("[data-type='messages']").find(".notification-count").html(response.data.count.message);
                       }else{
                          account_dropdown.find("[data-type='messages']").prepend("<span class='notification-count text-primary ms-auto fw-bold'>"+response.data.count.message+"</span>");
                       }
                    }
                    if(account_dropdown.find("[data-type='messages']").length > 0){
                       if(account_dropdown.find("[data-type='messages']").find(".notification-count").length > 0){
                          account_dropdown.find("[data-type='messages']").find(".notification-count").html(response.data.count.message);
                       }else{
                          account_dropdown.find("[data-type='messages']").prepend("<span class='notification-count text-primary ms-auto fw-bold'>"+response.data.count.message+"</span>");
                       }
                    }
                    if(messages_menu_item.length > 0){
                        messages_menu_item.html(response.data.count.message)
                    }
                    $(".nav-item-messages").addClass("has-notification");
                    document.title = "(" + response.data.count.message + ") " + ajax_request_vars.title;
                }else{
                    document.title = ajax_request_vars.title;
                    $(".nav-item-messages").removeClass("has-notification");
                }

            } else {
                desktop_counter.find(".notification-count").remove();
                mobile_counter.find(".notification-count").remove();
                account_dropdown.find("[data-type='messages']").find(".notification-count").remove();
                document.title = ajax_request_vars.title;
            }
            setTimeout('ajax_hooks["get_notification_alerts"].init()', 10000);
        }else{
            response_view(response);
        }
    }
};