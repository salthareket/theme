if (typeof acf !== 'undefined') {

    function updateUniqueSelect($repeater) {
        var $field    = acf.getField($repeater.data('key'));
        var $select   = $repeater.find('.unique select');
        var maxItems  = $select.find('option').length;

        function syncOptions() {
            $repeater.find('.unique select option').prop('disabled', false).removeClass('d-none');
            $repeater.find('.acf-row:not(.acf-clone)').each(function() {
                var val = $(this).find('.unique select').val();
                if (val) {
                    $repeater.find('.unique select option[value="' + val + '"]:not(:selected)')
                        .prop('disabled', true).addClass('d-none');
                }
            });
        }

        $field.$el.on('change', '.unique select', syncOptions);

        $field.$el.on('click', '.acf-repeater-add-row', function() {
            var rows    = $repeater.find('.acf-row:not(.acf-clone)');
            var $newRow = $(this).closest('.acf-repeater').find('.acf-row:last-child');

            var used = [];
            rows.each(function() {
                var v = $(this).find('.unique select').val();
                if (v) used.push(v);
            });

            $newRow.find('.unique select option').prop('disabled', false).removeClass('d-none');
            $newRow.find('.unique select option').each(function() {
                if (used.indexOf($(this).val()) !== -1) $(this).prop('disabled', true);
            });
            $newRow.find('.unique select option:not(:disabled)').first().prop('selected', true);

            var toggle = rows.length >= maxItems ? 'addClass' : 'removeClass';
            $field.$el.find('.acf-repeater-add-row')[toggle]('disabled');

            $newRow.find('.unique select').trigger('change');
        });

        var key = $select.closest('.acf-field').data('key');
        acf.addAction('remove_field/key=' + key, function() {
            syncOptions();
        });
    }

    var $unique = $('.acf-field-repeater .acf-row.acf-clone').find('.unique select');
    if ($unique.length) {
        $unique.each(function() {
            updateUniqueSelect($(this).closest('.acf-field-repeater'));
        });
    }
}
