if (typeof acf !== 'undefined') {
    (function($) {
        $(document).on('click', '.acf-file-uploader .acf-icon.-cancel', function() {
            $(this).closest('.acf-file-uploader').find('input[type="hidden"]').val('');
        });
    })(jQuery);
}
