function header_tools_condition($el){
    $el = $el || {};
    var data = {
            "languages"     : ["dropdown", "inactive", "all"],
            "favorites"     : ["offcanvas", "dropdown"],
            "messages"      : ["offcanvas", "dropdown"],
            "notifications" : ["offcanvas", "dropdown"],
            "cart"          : ["offcanvas", "dropdown"],
            "user-menu"     : ["offcanvas", "dropdown"],
            "navigation"    : ["offcanvas", "dropdown"],
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
        if (!window.location.search.includes('page=header')) return;
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

/*(function ($) {
    // ACF hazır olduğunda başlat
    if (typeof acf !== 'undefined') {
        acf.add_action('ready', function () {
            const $rows = $(".acf-repeater").find(".acf-row").not(".acf-clone");

            // İlk yüklemede uygula
            header_tools_condition($rows);

            // Yeni satır eklendiğinde tekrar uygula
            $(".acf-repeater-add-row").on("click", function () {
                const $newRows = $(this).closest(".acf-repeater").find(".acf-row").not(".acf-clone");
                setTimeout(() => {
                    header_tools_condition($newRows);
                }, 1500);
            });
        });
    }

    function header_tools_condition($el) {
        if (!$el || !$el.length) return;

        const config = {
            "languages": ["dropdown", "inactive", "all"],
            "favorites": ["offcanvas", "dropdown"],
            "messages": ["offcanvas", "dropdown"],
            "notifications": ["offcanvas", "dropdown"],
            "cart": ["offcanvas", "dropdown"],
            "user-menu": ["offcanvas", "dropdown"],
            "navigation": ["offcanvas"],
            "search": ["offcanvas"]
        };

        const menu_items = $el.find(".acf-field[data-name='menu_item']");
        if (!menu_items.length) return;

        menu_items.not(".header-tools-inited").each(function () {
            $(this).addClass("header-tools-inited");

            $(this).find('select').on('change', function () {
                const val = $(this).val();
                const menu_type = $(this).closest(".acf-row").find(".acf-field[data-name='menu_type']");
                const menu_type_val = menu_type.find('select option:selected').val();

                const $select = menu_type.find('select');
                const $options = $select.find("option");

                if (config.hasOwnProperty(val)) {
                    $options.addClass("d-none");
                    config[val].forEach(type => {
                        $options.filter(`[value="${type}"]`).removeClass("d-none");
                    });
                } else {
                    $options.removeClass("d-none");
                }

                $options.prop('selected', false);
                if (!menu_type_val) {
                    $options.not(".d-none").first().prop('selected', true);
                } else {
                    $options.filter(`[value="${menu_type_val}"]`).not(".d-none").prop('selected', true);
                }

                $select.trigger("change");

                // Geliştirme sırasında aktifse kullanılabilir
                // console.debug({ val, menu_type_val, $select });
            }).trigger("change");
        });
    }
})(jQuery);
*/
