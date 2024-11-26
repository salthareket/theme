if ( typeof acf !== 'undefined' ) {
    // show event description on select2 field options
    acf.addAction('select2_init', function( $select, args, settings, field ){
        var field_name = field.$el.data("name");
        if(field_name == "notification_event"){

            $select.find("option").each(function(){
                let option = jQuery(this);
                let text   = option.text().split("|");
                    option.text(text[0]);
                    option.attr("data-description", text[1])
            });

            args['templateResult'] = function (state) {
                debugJS(state);
                if (!state.id) {
                    return state.text;
                } else {
                    let title = state.text;
                    let description = $(state.element).data("description");
                    let $state = '<div><strong>' + title + '</strong></div><div style="font-size:12px;color:#888;">' + description + '</div>'
                    return jQuery($state);
                }
            };
            $select.select2(args); 
        }
    });
}