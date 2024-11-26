
jQuery(document).ready(function($) {
    var countries = $('.acf-field-select[data-name="country"] select');
    var states = $('.acf-field-select[data-name="state"] select');
    var cities = $('.acf-field-select[data-name="city"] select');
    var state_val = $('.acf-field-select[data-name="state"] select option').first().val();
    var city_val = $('.acf-field-select[data-name="city"] select option').first().val();

    countries.change(function () {
        var country = $(this).val();
        states.attr( 'disabled', 'disabled' );
        cities.attr( 'disabled', 'disabled' );
        if(city_val == ""){
                        cities.html("");
                    }
        if( country != '' ) {
            data = {
                action: 'acf_stations_get_states',
                pa_nonce: pa_vars.pa_nonce,
                country: country,
            };
            $.post( ajaxurl, data, function(response) {
                //debugJS(state_val)
                if( Object.keys(response).length ){
                    states.html( $('<option></option>').val('0').html('Select City').attr({ selected: 'selected', disabled: 'disabled'}) );
                    $.each(response, function(val, text) {
                        states.append( $('<option></option>').val(val).html(text) );
                    });
                    if(state_val){
                        states.val(state_val);
                        states.find("option[value='"+state_val+"']").prop("selected", "selected");
                        state_val = "";                       
                    }
                    states.removeAttr( 'disabled' );
                }else{
                    states.html("");
                }
            });
        }
    }).change();

    states.change(function () {
        var state = $(this).val();
        cities.attr( 'disabled', 'disabled' );
        if( state != '' ) {
            data = {
                action: 'acf_stations_get_cities',
                pa_nonce: pa_vars.pa_nonce,
                state: state,
            };
            $.post( ajaxurl, data, function(response) {
                if( Object.keys(response).length ){
                    cities.html( $('<option></option>').val('0').html('Select District').attr({ selected: 'selected', disabled: 'disabled'}) );
                    $.each(response, function(val, text) {
                        cities.append( $('<option></option>').val(val).html(text) );
                    });
                    if(city_val){
                        cities.val(city_val);
                        cities.find("option[value='"+city_val+"']").prop("selected", "selected");
                        city_val = "";                       
                    }
                    cities.removeAttr( 'disabled' );
                }else{
                    cities.html("");
                }
            });
        }
    }).change();
});