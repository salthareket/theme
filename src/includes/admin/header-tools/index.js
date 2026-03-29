function header_tools_condition($el) {
    $el = $el || {};
    var config = {
        'languages':     ['dropdown', 'inactive', 'all'],
        'favorites':     ['offcanvas', 'dropdown'],
        'messages':      ['offcanvas', 'dropdown'],
        'notifications': ['offcanvas', 'dropdown'],
        'cart':          ['offcanvas', 'dropdown'],
        'user-menu':     ['offcanvas', 'dropdown'],
        'navigation':    ['offcanvas', 'dropdown'],
        'search':        ['offcanvas']
    };

    var menu_item = ($el.length > 0)
        ? $el.find(".acf-field[data-name='menu_item']")
        : $(".acf-field[data-name='menu_item']");

    if (!menu_item.length) return;

    menu_item.not('.header-tools-inited').each(function() {
        $(this).addClass('header-tools-inited');

        $(this).find('select').on('change', function() {
            var val       = $(this).val();
            var menu_type = $(this).closest('.acf-row').find(".acf-field[data-name='menu_type']");
            var prev_val  = menu_type.find('select option:selected').val();
            var $options  = menu_type.find('select option');

            if (config.hasOwnProperty(val)) {
                $options.addClass('d-none');
                config[val].forEach(function(type) {
                    $options.filter('[value="' + type + '"]').removeClass('d-none');
                });
            } else {
                $options.removeClass('d-none');
            }

            $options.prop('selected', false);
            if (!prev_val) {
                $options.not('.d-none').first().prop('selected', true);
            } else {
                $options.filter('[value="' + prev_val + '"]').not('.d-none').prop('selected', true);
            }

            menu_type.trigger('change');
        }).trigger('change');
    });
}

(function($) {
    if (!$('.acf-repeater-add-row').length) return;
    if (!window.location.search.includes('page=header')) return;

    $('.acf-repeater-add-row').on('click', function() {
        var obj = $(this);
        setTimeout(function() {
            header_tools_condition(obj.closest('.acf-repeater').find('.acf-row').not('.acf-clone'));
        }, 1500);
    });

    header_tools_condition($('.acf-repeater').find('.acf-row').not('.acf-clone'));
})(jQuery);
