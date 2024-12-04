function header_tools_condition($el){
    $el = $el || {};
    var data = {
            "languages"     : ["dropdown", "inactive", "all"],
            "favorites"     : ["offcanvas", "dropdown"],
            "messages"      : ["offcanvas", "dropdown"],
            "notifications" : ["offcanvas", "dropdown"],
            "cart"          : ["offcanvas", "dropdown"],
            "user-menu"     : ["offcanvas", "dropdown"],
            "navigation"    : ["offcanvas"],
            "search"        : ["offcanvas"]
    };
    if($el.length > 0){
        var menu_item = $el.find(".acf-field[data-name='menu_item']");
    }else{
        var menu_item = $(".acf-field[data-name='menu_item']");
    }
    if(menu_item.length > 0){
        menu_item.not(".header-tools-inited").each(function(){
            $(this).addClass("header-tools-inited");
            $(this).find('select').on('change', function() {
                var val = $(this).val();
                var menu_type = $(this).closest(".acf-row").find(".acf-field[data-name='menu_type']");
                var menu_type_val = menu_type.find('select option:selected').val();
                if(data.hasOwnProperty(val)){
                    menu_type.find('select option').addClass("d-none");
                    for(var i=0;i<data[val].length;i++){
                        var type = data[val][i];
                        menu_type.find('select option[value="'+type+'"]').removeClass("d-none");
                    }
                }else{
                    menu_type.find('select option').removeClass("d-none");
                }
                menu_type.find("select option").prop('selected', false);
                if(menu_type_val == ""){
                    menu_type.find("select option").not(".d-none").first().prop('selected', true);
                }else{
                    menu_type.find("select option[value='"+menu_type_val+"']").not(".d-none").prop('selected', true);
                }
                
                menu_type.trigger("change");
                debugJS(val)
                debugJS(menu_type)
                debugJS(menu_type_val)
                debugJS(menu_type.find("select"));
                debugJS(menu_type.find("select option").not(".d-none").first());
            })
            .trigger("change");
        });                
    }       
}
(function($) {
    if($(".acf-repeater-add-row").length > 0){
        $(".acf-repeater-add-row").on("click", function(){
            debugJS("add row klik")
            var obj = $(this);
            setTimeout(function(){
                header_tools_condition(obj.closest(".acf-repeater").find(".acf-row").not(".acf-clone"));
            }, 1500);
        })
        header_tools_condition($(".acf-repeater").find(".acf-row").not(".acf-clone"));        
    }
})(jQuery);
