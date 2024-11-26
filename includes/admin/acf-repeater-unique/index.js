if ( typeof acf !== 'undefined' ) {
// make repeater field unique : just add "unique" class to select field
    function updateUniqueSelect($repeater) {
            var $repeater_obj = acf.getField($repeater.data("key"));
            var $select = $repeater.find(".unique select");
            var $maxItems = $select.find("option").length;
            
            $repeater_obj.$el
            .on('change', '.unique select', function() {
                $repeater.find('.unique select option').prop("disabled", false).removeClass("d-none");
                $repeater.find('.acf-row:not(.acf-clone)').each(function() {
                    var $currentRow = $(this);
                    var $currentSelect = $currentRow.find('.unique select');
                    var selectedValue = $currentSelect.val();
                    if (selectedValue) {
                        $repeater.find('.unique select option[value="' + selectedValue + '"]:not(:selected)').prop("disabled", true).addClass("d-none");;
                    }
                });
            })
            .on('click', '.acf-repeater-add-row', function() {
                var rows = $repeater.find('.acf-row:not(.acf-clone)');
                debugJS(rows)
                var $newRow = $(this).closest(".acf-repeater").find(".acf-row:last-child");
                
                var selectedOptions = [];
                rows.each(function() {
                    var $currentRow = $(this);
                    var $currentSelect = $currentRow.find('.unique select');
                    var selectedValue = $currentSelect.val();
                    if (selectedValue) {
                        selectedOptions.push(selectedValue);
                    }
                });
                
                $newRow.find('.unique select option').prop("disabled", false).removeClass("d-none");
                $newRow.find('.unique select option').each(function() {
                    var optionValue = $(this).val();
                    if (selectedOptions.includes(optionValue)) {
                        $(this).prop("disabled", true);
                    }
                });
                $newRow.find('.unique select option:not(:disabled)').first().prop("selected", true).removeClass("d-none");
                
                var currentItems = rows.length;
                if (currentItems >= $maxItems) {
                    $repeater_obj.$el.find('.acf-repeater-add-row').addClass('disabled');
                } else {
                    $repeater_obj.$el.find('.acf-repeater-add-row').removeClass('disabled');
                }
                $newRow.find('.unique select').trigger('change');
            });
            
            var key = $select.closest(".acf-field").data("key");
            acf.addAction('remove_field/key=' + key, function(item) {
                $repeater.find('.unique select option').prop("disabled", false).removeClass("d-none");
                var rows = $repeater.find('.acf-row:not(.acf-clone)');
                var $deletedRow = item.$el.closest('.acf-row');
                var deletedRowSelectedValue = item.$el.find('.unique select').val();
                if (deletedRowSelectedValue) {
                    rows.each(function() {
                        var $currentRow = $(this);
                        var $currentSelect = $currentRow.find('.unique select');
                        var selectedValue = $currentSelect.val();
                        
                        if (selectedValue && selectedValue !== deletedRowSelectedValue) {
                            $repeater.find('.unique select option[value="' + selectedValue + '"]').prop("disabled", true).addClass("d-none");
                        }
                    });
                } else {
                    rows.each(function() {
                        var $currentRow = $(this);
                        var $currentSelect = $currentRow.find('.unique select');
                        var selectedValue = $currentSelect.val();
                        
                        if (selectedValue) {
                            var $otherRowSelect = $deletedRow.siblings('.acf-row').find('.unique select');
                            var otherSelectedValue = $otherRowSelect.val();
                            if (selectedValue === otherSelectedValue) {
                                $repeater.find('.unique select option[value="' + selectedValue + '"]').prop("disabled", true).addClass("d-none");
                            }
                        }
                    });
                }
            });
    }
    var unique_field = $(".acf-field-repeater .acf-row.acf-clone").find(".unique select");
    
    if (unique_field.length > 0) {
       unique_field.each(function(){
            var $repeater = $(this).closest(".acf-field-repeater");
            updateUniqueSelect($repeater);         
       });
    }
}