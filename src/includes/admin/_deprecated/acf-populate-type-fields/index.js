if (typeof acf !== 'undefined') {
    (function($) {
        function initialize_post_type_field($field) {
            var name   = $field.data('name');
            var parent = $field.closest('.acf-row');

            var chained_name = (name === 'menu_item_post_type') ? 'menu_item_taxonomy' : null;
            if (!chained_name) return;

            var $chained = parent.find("[data-name='" + chained_name + "']");
            if (!$chained.length) return;

            $field.find('select').off('change').on('change', function() {
                var value     = $(this).val();
                var post_type = parent.find("[data-name='menu_item_post_type'] select").val();
                var selected  = $chained.attr('data-val') || '';

                if (value) $(this).closest('.acf-field').attr('data-val', value);

                $chained.addClass('loading-process').find('select').empty();
                if (!value) return;

                var data = acf.prepareForAjax({
                    action:   'get_post_type_taxonomies',
                    post_type: post_type,
                    name:      name,
                    value:     value,
                    selected:  selected,
                    post_id:   acf.get('post_id')
                });

                $.ajax({
                    url:      acf.get('ajaxurl'),
                    data:     data,
                    type:     'post',
                    dataType: 'json',
                    success: function(json) {
                        $chained.removeClass('loading-process');
                        if (!json) return;
                        if (json.error) {
                            $chained.addClass('d-none');
                            alert(json.message);
                            return;
                        }
                        if (json.html) {
                            $chained.find('select').html(json.html);
                            if (['menu_item_post_type', 'menu_item_taxonomy'].indexOf(name) !== -1) {
                                $chained.find('select').trigger('change');
                            }
                        }
                    }
                });
            }).trigger('change');
        }

        if (typeof acf.add_action !== 'undefined') {
            $("[data-name='menu_populate'] [data-name='menu_item_post_type']").each(function() {
                initialize_post_type_field($(this));
                acf.add_action('ready_field/name=menu_item_post_type', initialize_post_type_field);
                acf.add_action('append_field/name=menu_item_post_type', initialize_post_type_field);
            });
        }
    })(jQuery);
}
