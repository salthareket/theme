if ( typeof acf !== 'undefined' ) {
    
    ( function( $ ) {
        function initialize_post_type_field( $field ) {
            var name = $field.data("name");
            var select  = $field.find("select");
            var parent  = $field.closest(".acf-row");
            switch(name){
                case "menu_item_post_type" :
                    var chained_name = "menu_item_taxonomy";
                break;
            }
            var chained = parent.find("[data-name='"+chained_name+"']");
            if(chained.length > 0){
                $field.find("select")
                .off("change")
                .on("change", function(e) {
                    var target = jQuery(e.target);
                    var post_type = parent.find("[data-name='menu_item_post_type']").find("select").val();
                    var value = $(this).val();

                    if(!IsBlank(value)){
                        $(this).closest(".acf-field").attr("data-val", value);
                    }
                    
                    var selected = chained.attr("data-val");
                        selected = IsBlank(selected)?"":selected;
                        chained.addClass("loading-process");
                        chained.find("select").empty();
                        if (!value) {
                            return
                        }

                        var data = {
                            action: 'get_post_type_taxonomies',
                            post_type : post_type,
                            name: name,
                            value: value,
                            selected: selected,
                            post_id: acf.get('post_id'),
                        }
                        data = acf.prepareForAjax(data);
                        this.request = $.ajax({
                            url: acf.get('ajaxurl'),
                            data: data,
                            type : "post",
                            dataType: 'json',
                            success: function(json) {
                                if (!json) {
                                    return
                                }
                                if (json.error) {
                                    chained.removeClass("loading-process");
                                    chained.addClass("d-none");
                                    alert(json.message)
                                } else {
                                    chained.removeClass("loading-process")
                                    if(!IsBlank(json.html)){
                                        chained.find("select").html(json.html);
                                        if(["menu_item_post_type", "menu_item_taxonomy"].includes(name)){
                                            chained.find("select").trigger("change");
                                        }
                                    }                                   
                                }
                            }
                        })
                })
                .trigger("change");
            }
        }

        if( typeof acf.add_action !== 'undefined' ) {
            jQuery("[data-name='menu_populate']").find("[data-name='menu_item_post_type']").each(function(){
                initialize_post_type_field(jQuery(this));
                acf.add_action( 'ready_field/name=menu_item_post_type', initialize_post_type_field );
                acf.add_action( 'append_field/name=menu_item_post_type', initialize_post_type_field );
            });
        }

    } )( jQuery );
}

