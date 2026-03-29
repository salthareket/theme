$required_setting = ENABLE_NOTIFICATIONS;

if (typeof acf !== 'undefined') {

    function notification_carriers_activate($container) {
        if (!$container.length) return;

        var carriers = ['notification_email', 'notification_alert', 'notification_sms'];
        carriers.forEach(function(name) {
            var $switchers = $container.find(".acf-row:not(.acf-clone) .acf-field[data-name='" + name + "']");
            $switchers.each(function() {
                var $accordion = $(this).closest("[data-type='accordion']");
                $(this).find("input[type='checkbox']").on('change', function() {
                    $accordion.find('.acf-accordion-title').toggleClass('bg-success text-white', $(this).is(':checked'));
                }).trigger('change');
            });
        });
    }

    function notification_filters() {
        var $filters = $(".acf-field[data-name='notifications_filter']");
        if (!$filters.length) return;

        var $roles  = $filters.find("[data-name='notification_role_filter'] select");
        var $events = $filters.find("[data-name='notification_event_filter'] select");

        function apply() {
            var args = { role: $roles.val(), event: $events.val() };
            var $rows = $(".acf-field[data-name='notifications'] .acf-row:not(.acf-clone)");

            $rows.each(function() {
                var role  = $(this).find("[data-name*='notification_role'] select").val();
                var event = $(this).find("[data-name*='notification_event'] select").val();
                var match = (args.role === '' || role === args.role) && (args.event === '' || event === args.event);
                $(this).toggleClass('d-none', !match);
            });
        }

        $roles.on('change', apply);
        $events.on('change', apply);
    }

    var $repeater = $(".acf-field[data-name='notifications']");
    if ($repeater.length) {
        var $field = acf.getField($repeater.data('key'));
        notification_carriers_activate($field.$el);
        $field.$el.on('click', '.acf-repeater-add-row', function() {
            notification_carriers_activate($field.$el);
        });
        notification_filters();
    }
}
