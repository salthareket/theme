function translate($text){
    if(!IsBlank($text)){
        if(site_config.dictionary.hasOwnProperty($text)){
            $text = site_config.dictionary[$text];
        }
    }
    return $text;
}

function isLoadedJS($name){
    var $loaded = false;
    if(typeof required_js !== "undefined"){
        if(required_js.indexOf($name) >-1){
            $loaded = true
        }
    }
    if(!$loaded){
        if(typeof conditional_js !== "undefined"){
            if(conditional_js.indexOf($name) >-1){
                $loaded = true
            }
        }
    }
    return $loaded; 
}

function function_secure($plugin, $name, $params) {
    console.log($plugin, $name, $params)
    if (isLoadedJS($plugin)) {
        if (typeof window[$name] === 'function') {
            if (Array.isArray($params)) {
                window[$name].apply(null, $params);
            } else {
                window[$name]($params);
            }
        } else {
            console.error($name + ' is not a function...');
        }
    }
}

/*
function function_secure($plugin, $name, $params){
    if(isLoadedJS($plugin)){
        if (Array.isArray($params)) {
            window[$name].apply(null, $params);
        } else {
            window[$name]($params);
        }
    }
}*/

function initContactForm(){
    var obj = wpcf7;
        obj.initForm = function ( el ) { 
            obj.init( el[0] ); 
        }
        $(".wpcf7-form").each(function(){
            var wpcf7_form = $(this);
            obj.initForm(wpcf7_form);

            //init conditional fields
            if(typeof wpcf7cf === "object"){
                wpcf7cf.initForm(wpcf7_form);
            }

            wpcf7_form.find(".btn-submit").on("click", function(){
                $(this).find(".accordion-item.error").removeClass("error");
                if($('.modal:visible').length > 0){
                    $('.modal:visible').find(".modal-content").addClass("loading-process");
                }else{
                    $("body").addClass("loading-process");
                }
            });            

        });
}
function modalFormActions(e, type){
    var form = $('.modal:visible').find(e.target);
    var formSubmit = form.find('.btn-submit');
    if(type == "submit"){
        if(form.hasClass('invalid')||form.hasClass('failed')||form.hasClass('unaccepted')||form.hasClass('spam')){
           $('.modal:visible').animate({ scrollTop:form.offset().top - $("header#header").height()  },600, function(){
               formSubmit.attr('disabled',false).blur();
           });
        }else{
            form.removeClass("sent").addClass("init");
            var message = decodeHtml(e.detail.apiResponse.message);
            //debugJS(e);
            $('.modal:visible').modal("hide");
            _alert("", message, "xxl", "modal-fullscreen bg-tertiary text-white", "", "", true, true);
        }
    };
    if(type == "sent"){
       $('.modal:visible').animate({ scrollTop:form.offset().top - $("header#header").height()  },600);
       formSubmit.attr('disabled',false).blur();       
    }
    
    //$(".modal").animate({ scrollTop: $(".wpcf7-response-output").offset().top }, 600);
};
function contactFormActions(e, type){
    var form = $(e.target);
    var formSubmit = form.find('.btn-submit');
    if(type == "submit"){
        if(form.hasClass('invalid')||form.hasClass('failed')||form.hasClass('unaccepted')||form.hasClass('spam')){
           $("html,body").animate({ scrollTop:form.offset().top - $("header#header").height()  }, 600);
           formSubmit.attr('disabled', false).blur();
        }else{
            form.removeClass("sent").addClass("init");
            var message = decodeHtml(e.detail.apiResponse.message);
            //debugJS(e);
            _alert("", message, "xxl", "modal-fullscreen bg-tertiary text-white", "", "", true, true);
        }
    };
    if(type == "sent"){
       $("html,body").animate({ scrollTop:form.offset().top - $("header#header").height()  }, 600);
       formSubmit.attr('disabled', false).blur();
    }
};
function contactform_sent(e){
    var formId = e.detail.contactFormId;
    if($('.modal:visible').length>0){
        $('.modal:visible').find(".modal-content").removeClass("loading-process");
        modalFormActions(e, 'sent');
    }else{
        $("body").removeClass("loading-process");
        contactFormActions(e, 'sent');
    }
}
function contactform_invalid(e){
     $(e.target).find(".accordion-item.error").removeClass("error");
     $(e.detail.apiResponse.invalid_fields).each(function(i, obj) {
        obj = $("[name='"+obj.field+"']");
        if(obj.closest(".accordion-item").length > 0){
            obj.closest(".accordion-item").addClass("error");
        }
        obj.on("click", function(e){
            $(this).removeClass("wpcf7-not-valid");
         });
     });
}
$( document ).ready(function() {
        /*wpcf7invalid — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because there are fields with invalid input.
        wpcf7spam — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because a possible spam activity has been detected.
        wpcf7mailsent — Fires when an Ajax form submission has completed successfully, and mail has been sent.
        wpcf7mailfailed — Fires when an Ajax form submission has completed successfully, but it has failed in sending mail.
        wpcf7submit — Fires when an Ajax form submission has completed successfully, regardless of other incidents.*/
        /*detail.contactFormId  The ID of the contact form.
        detail.pluginVersion    The version of Contact Form 7 plugin.
        detail.contactFormLocale    The locale code of the contact form.
        detail.unitTag  The unit-tag of the contact form.
        detail.containerPostId  The ID of the post that the contact form is placed in.*/
        $( '.wpcf7' ).each(function(){
                var form = $(this);
                form.find(".btn-submit").on("click", function(){
                    $(this).find(".accordion-item.error").removeClass("error");
                    if($('.modal:visible').length > 0){
                       $('.modal:visible').find(".modal-content").addClass("loading-process");
                    }else{
                        $("body").addClass("loading-process");
                    }
                });            
        });/**/
        document.addEventListener( 'wpcf7submit', function( e ) {
            //event.detail.contactFormId;
            //$(e.target).find(".accordion-item.error").removeClass("error");
            if($('.modal:visible').length > 0){
                $('.modal:visible').find(".modal-content").removeClass("loading-process");
                modalFormActions(e, 'submit');
            }else{
                $("body").removeClass("loading-process");
                contactFormActions(e, 'submit');
            }
        }, false );
        document.addEventListener( 'wpcf7mailsent', function( e ) {
            //event.detail.contactFormId;
            contactform_sent(e);
        }, false );
        document.addEventListener( 'wpcf7mailfailed', function( e ) {
            $("body").removeClass("loading-process");
        }, false );
        document.addEventListener( 'wpcf7spam', function( e ) {
            $("body").removeClass("loading-process");
        }, false );
        document.addEventListener( 'wpcf7invalid', function( e ) {
            contactform_invalid(e)
            $("body").removeClass("loading-process");
        }, false );
});