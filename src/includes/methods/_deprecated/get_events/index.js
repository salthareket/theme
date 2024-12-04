{
    before: function(response, vars, form, objs) {
        debugJS(vars);
        debugJS(objs)
        objs.obj.addClass("loading-process");
    },
    after: function(response, vars, form, objs) {
        var events = response.data;
        debugJS(events)
        objs.obj.removeClass("loading-process");
        objs.calendar.setEvents(events);
        debugJS(objs.obj)
        if (events.length > 0) {
            for (var event_item = 0; event_item < events.length; event_item++) {
                debugJS(events[event_item].date);
                debugJS(events[event_item].color);
                objs.obj.find(".calendar-day-" + events[event_item].date).css("background-color", events[event_item].color);
            }
        }
    }
};