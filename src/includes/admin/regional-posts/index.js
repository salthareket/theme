$required_setting = ENABLE_REGIONAL_POSTS;

if (typeof acf !== 'undefined') {
    (function($) {
        function acf_regional_post_taxonomy(e) {
            var $target   = $(e.target);
            var value     = $target.val();
            var $row      = $target.closest('.acf-row');
            var $taxonomy = $row.find("[data-name='taxonomy'] select");
            var $container = $target.closest('.acf-repeater');

            if (!value) return;

            var data = acf.prepareForAjax({
                action:  'get_regional_posts_type_taxonomies',
                type:    value,
                name:    $target.closest('.acf-field').attr('data-name'),
                value:   value,
                post_id: acf.get('post_id')
            });

            $container.addClass('loading-process');

            $.ajax({
                url:      acf.get('ajaxurl'),
                data:     data,
                type:     'post',
                dataType: 'json',
                success: function(json) {
                    $container.removeClass('loading-process');
                    if (!json) return;
                    if (json.error) { alert(json.message); return; }
                    $taxonomy.html("<option value=''>Choose a taxonomy</option>" + json.html);
                }
            });
        }

        $(".acf-row").not('.acf-clone')
            .find("[data-name='post_type'] select")
            .on('change', acf_regional_post_taxonomy)
            .trigger('change');
    })(jQuery);
}
