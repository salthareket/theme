<?php

function acf_load_city_choices( $field ) {
    global $post;
    $field['choices'] = array();
    $cities = get_states(wc_get_base_country());
    if( is_array($cities) ) {
        foreach( $cities as $key => $city ) {
            $field['choices'][ $key ] = $city;
        }
    }
    return $field;
}
add_filter('acf/load_field/name=city', 'acf_load_city_choices');

function acf_admin_head(){
    global $post;

        $district = get_post_meta( $post->ID, "district", true);
        ?>
        <script type="text/javascript">
            jQuery(function($){
                $('select').on('change', function() {
                    var field_name = $(this).closest(".acf-field").data("name");
                    switch(field_name){
                        case "city" :
                           var obj = $(".acf-field[data-name='district']").find("select");
                               obj.prop("disabled", true);
                           var city = this.value;
                            $.post(ajax_request_vars.url+"?ajax=query", { method : "get_districts", vars : { city : city } })
                            .fail(function() {
                                alert( "error" );
                            })
                            .done(function( response ) {
                                response = $.parseJSON(response);   
                                obj.empty().val(null).trigger('change');
                                for(var i=0;i<response.length;i++){
                                    var selected = i==0?true:false;
                                    if("<?php echo $district;?>" == response[i]){
                                       selected = true;
                                    }
                                    var district = response[i];
                                    var newOption = new Option(district, district, selected, selected);
                                    obj.append(newOption);                      
                                }
                                obj.trigger('change').prop("disabled", false);
                            });
                        break;
                    }
                }).trigger("change");
            });
        </script>       

<?php
}
//add_action('acf/input/admin_head', 'acf_admin_head');