/*acf.addAction('select2_init', function($select, args, settings, field) {
    var fieldName = field.$el.data("name");
    if (field.$el.hasClass("acf-color-classes") || field.$el.hasClass("acf-color-classes-custom")) {
        args.minimumResultsForSearch = -1;  // Arama özelliğini devre dışı bırakır
        args.ajax = false;                  // AJAX isteğini tamamen kapatır
        args['templateResult'] = function(state) {
            debugJS(state)
            if (!state.id) {
                return state.text;
            } else {
                var colorCode = state.element.value;
                debugJS("color:"+colorCode+" name:"+state.text);
                var $state = $(
                    '<span style="display: flex; align-items: center;">' +
                    '<span style="width: 15px; height: 15px; display: inline-block; margin-right: 8px; background-color: var(--bs-' + colorCode + ');"></span>' +
                    state.text +
                    '</span>'
                );
                return $state;
            }
        };
        $select.select2(args);
    }
});*/