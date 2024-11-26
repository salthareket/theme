if ( typeof acf !== 'undefined' ) {
    
    ( function( $ ) {
        function initialize_type_field( $field ){
                var select    = $field.find("select");
                var selected  = select.val()
                var parent    = $field.closest(".acf-fields");
                var post_type = parent.find("[data-name='post_type']");
                var taxonomy  = parent.find("[data-name='taxonomy']");
                var terms     = parent.find("[data-name='terms']");
                initialize_types_field( post_type );
                initialize_types_field( taxonomy );
                select
                .on("change", function(e) {
                    var selected  = select.val();
                    if(selected == "post_type" || selected == "taxonomy"){
                        if(selected == "taxonomy"){
                            taxonomy.removeClass("d-none");
                        }
                        parent.find("[data-name='post_type']").find("select").trigger("change");
                    }
                })
                .trigger("change");
        }

        function initialize_types_field( $field ) {
            var name = $field.data("name");
            var select  = $field.find("select");
            var parent  = $field.closest(".acf-fields");
            switch(name){
                case "post_type" :
                    var chained_name = "taxonomy";
                break;
                case "taxonomy" :
                    var chained_name = "terms";
                break;
            }
            var chained = parent.find("[data-name='"+chained_name+"']");
            if(chained.length > 0){
                $field.find("select")
                .off("change")
                .on("change", function(e) {
                    var target = jQuery(e.target);
                    var type = parent.find("[data-name='type']").find("select").val();
                    var post_type = parent.find("[data-name='post_type']").find("select").val();
                    var value = value = $(this).val();

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

                        if(type == "taxonomy"){
                           post_type = "";
                           if(name == "post_type"){
                               value = "";
                           }
                        }

                        chained.removeClass("d-none");
                        if(["taxonomy"].includes(chained_name)){
                            var terms = parent.find("[data-name='terms']");
                                //terms.find("select").empty();
                                terms.addClass("d-none");
                        }
                        
                        var data = {
                            action: 'get_posts_type_taxonomies',
                            type : type,
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
                            type: 'post',
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
                                        if(["post_type", "taxonomy"].includes(name)){
                                            chained.find("select").trigger("change");
                                        }
                                    }else{
                                        chained.addClass("d-none");
                                        if(["taxonomy"].includes(chained_name)){
                                            var terms = parent.find("[data-name='terms']");
                                                terms.find("select").empty();
                                                terms.addClass("d-none");
                                        }
                                    }                                    
                                }
                            }
                        })
                });
            }
        }

        if( typeof acf.add_action !== 'undefined' ) {
            acf.add_action( 'ready_field/name=type', initialize_type_field );
            acf.add_action( 'append_field/name=type', initialize_type_field );
        }

    } )( jQuery );
}

