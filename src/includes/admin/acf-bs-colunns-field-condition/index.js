if (typeof acf !== 'undefined') {
    (function($) {
        function initialize_bs_columns_field($field) {
            var $block = $($field).closest('.wp-block');
            if ($block.data('type') !== 'acf/bootstrap-columns') return;

            var $rowCols     = $block.find("[data-name='row_cols']").first();
            var $breakpoints = $block.find("[data-name='acf_block_columns']").first()
                .find('.values').first()
                .find('> .layout').not("[data-layout='block-bootstrap-columns']")
                .find("[data-name='breakpoints']");

            $rowCols.find("input[type='checkbox']").on('change', function() {
                $breakpoints.toggleClass('acf-hidden', $(this).is(':checked'));
            });

            acf.add_action('acfe/modal/open', function($modal) {
                var $bp = $($modal.$el).find("[data-name='breakpoints']").first();
                var $rc = $($modal.$el).closest('.acf-block-fields').find("[data-name='row_cols']").first();
                $bp.toggleClass('acf-hidden', $rc.find("input[type='checkbox']").is(':checked'));
            });
        }

        if (typeof acf.add_action !== 'undefined') {
            acf.add_action('ready_field/type=acf_bs_breakpoints', initialize_bs_columns_field);
            acf.add_action('append_field/type=acf_bs_breakpoints', initialize_bs_columns_field);
        }
    })(jQuery);
}
