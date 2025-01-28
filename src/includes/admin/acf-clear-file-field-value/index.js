if ( typeof acf !== 'undefined' ) {
    
    ( function( $ ) {
        $(document).on('click', '.acf-file-uploader .acf-icon.-cancel', function () {
            const fileInput = $(this).closest('.acf-file-uploader').find('input[type="hidden"]');
            fileInput.val(''); // Input değerini sıfırla
        });
    })(jQuery);

}
