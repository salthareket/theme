function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
};

jQuery(function($){
    // now you can use jQuery code here with $ shortcut formatting
    // this executes immediately - before the page finishes loading

    /**
     * Newsletter support
     */
    $('#newsletter')
        .attr('novalidate', true)
        .each( function() {
            var $this = $(this),
                $name = $this.find( 'input[name="nn"]'),
                $surname = $this.find( 'input[name="ns"]'),
                $input = $this.find( 'input[name="ne"]'),
                $accept = $this.find( 'input[name="ny"]'),
                $noti = $input.prev(),
                $submit = $this.find( 'button[type="submit"]'),
                $submit_text = $submit.text();

            // Submit handler
            $this.submit( function(e) {
                var serializedData = $this.serialize();

                $noti = $input.prev();
                
                debugJS( 'INFO: Form submit.' );

                e.preventDefault();

                var messages = site_config.dictionary//.newsletter;

                alert(isLoadedJS("bootbox"))

                if(IsBlank($name.val())){
                    //if(messages.hasOwnProperty("name_error")){
                        _alert("Please write your name.")
                    //}
                    return false; 
                }

                if(IsBlank($surname.val())){
                   _alert("Please wite your last name.");
                   return false; 
                }

                // validate
                if( validateEmail( $input.val() ) ) { 
                    var data = {};

                    if($accept.length>0 && !$accept.is(":checked")){
                       _alert("Please accept");
                       return false;
                    }

                    // Prepare ajax data
                    data = {
                        action: 'realhero_subscribe',
                        nonce: newsletter_ajax.ajax_nonce,
                        data: serializedData
                    }
                    //debugJS(data);
                    // send ajax request
                    $.ajax({
                        method: "POST",
                        url: newsletter_ajax.url,
                        data: data,
                        beforeSend: function() {
                            $input.prop( 'disabled', true );
                            $submit.text('Wait').prop( 'disabled', true );
                        },
                        success: function( data ) {
                            $input.prop( 'disabled', false );
                            $submit.text($submit_text).prop( 'disabled', false );
                            if( data.status == 'success' ) {
                                _alert(data.msg);
                                //debugJS( 'INFO: OK!' );
                            } else {
                                _alert(data.msg);
                                //debugJS( 'INFO: Bad response.' );
                            }
                        }
                    });

                    debugJS( 'INFO: Email ok.' );

                } else { 
                    _alert("Please write a valid email address" );
                };
            });
        });

});