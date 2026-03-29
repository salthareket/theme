function acfGalleryHeight() {
    $('.acf-field.acf-field-gallery').each(function() {
        var $gallery = $(this).find('.acf-gallery.ui-resizable');
        var hasItems = $(this).find('.acf-gallery-attachments').children().length > 0;
        $gallery.css('height', hasItems ? '400px' : '0px');
    });
}

document.addEventListener('click', function() {
    setTimeout(acfGalleryHeight, 1000);
});
acfGalleryHeight();
