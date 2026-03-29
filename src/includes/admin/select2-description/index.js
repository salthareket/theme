if (typeof acf !== 'undefined') {
    acf.addAction('select2_init', function($select, args, settings, field) {
        if (field.$el.data('name') !== 'notification_event') return;

        $select.find('option').each(function() {
            var $opt  = jQuery(this);
            var parts = $opt.text().split('|');
            $opt.text(parts[0]);
            if (parts[1]) $opt.attr('data-description', parts[1]);
        });

        args.templateResult = function(state) {
            if (!state.id) return state.text;
            var desc = $(state.element).data('description') || '';
            return jQuery(
                '<div><strong>' + state.text + '</strong></div>' +
                '<div style="font-size:12px;color:#888">' + desc + '</div>'
            );
        };

        $select.select2(args);
    });
}
