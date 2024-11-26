
    function acfGalleryHeight(){
        if($(".acf-field.acf-field-gallery").length > 0){
            $(".acf-field.acf-field-gallery").each(function(){
                if($(this).find('.acf-gallery-attachments').children().length == 0){
                    $(this).find(".acf-gallery.ui-resizable").css("height", "0px");
                }else{
                    $(this).find(".acf-gallery.ui-resizable").css("height", "400px");
                }
            });
        } 
    }
    document.addEventListener('click', function() {
        setTimeout(function(){
            acfGalleryHeight();
        }, 1000);
    });
    acfGalleryHeight();