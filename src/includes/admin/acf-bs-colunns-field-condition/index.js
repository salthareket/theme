if ( typeof acf !== 'undefined' ) {
    
    ( function( $ ) {

        function initialize_bs_columns_field($field){
            if($($field).closest(".wp-block").data("type") == "acf/bootstrap-columns"){
                let block = $($field).closest(".wp-block");

                let row_cols = block.find("[data-name='row_cols']").first();
                let breakpoints  = block.find("[data-name='acf_block_columns']").first().find(".values").first().find(">.layout").not("[data-layout='block-bootstrap-columns']").find("[data-name='breakpoints']");
                row_cols.find("input[type='checkbox']").on("change", function(){
                    console.log(breakpoints.length)
                    if($(this).is(":checked")){
                        breakpoints.addClass("acf-hidden");
                    }else{
                        breakpoints.removeClass("acf-hidden");
                    }
                });
                acf.add_action( 'acfe/modal/open', function($modal, $args){
                    let breakpoints = $($modal.$el).find("[data-name='breakpoints']").first();
                    let row_cols = $($modal.$el).closest(".acf-block-fields").find("[data-name='row_cols']").first();
                    if(row_cols.find("input[type='checkbox']").is(":checked")){
                        breakpoints.addClass("acf-hidden");
                    }else{
                        breakpoints.removeClass("acf-hidden");
                    }
                });
                
            }
        }

        if( typeof acf.add_action !== 'undefined' ) {

            acf.add_action( 'ready_field/type=acf_bs_breakpoints', initialize_bs_columns_field );
            acf.add_action( 'append_field/type=acf_bs_breakpoints', initialize_bs_columns_field );
            /*acf.addAction('append', function($el) {
                if ($el.hasClass('layout') && $el.closest('.acf-flexible-content').length && $el.data("layout") == "block-bootstrap-columns") {
                    console.log('Flexible Content alanına yeni bir öğe eklendi:', $el);
                    initialize_bs_columns_field($el);
                }
            });*/
        }

    } )( jQuery );
}