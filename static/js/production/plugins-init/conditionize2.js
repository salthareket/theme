function form_item_attributes($obj, $status){
    if($status){
        $obj.each(function(){
            var condition = $(this).attr("data-condition");
            var condition_obj = $(this);
            condition_obj
            .removeClass("d-none");
            //var fields = condition_obj.find("[data-required]").not(".d-none");
            var fields = condition_obj.find("input,select,textarea").not(".d-none");
            fields.each(function(){
                var field = $(this);
                if(field.closest("[data-condition]").attr("data-condition") == condition){
                    field
                    //.removeAttr("data-required")
                    //.attr("required", true)
                    .prop("disabled", false);
                    if ( typeof field.attr("data-required") !== "undefined" ) {
                       field
                       .removeAttr("data-required")
                       .attr("required", true);
                    }
                    if(field.hasClass("selectpicker")){
                       field.selectpicker("refresh");
                    }
                }
            })
        });
    }else{
        $obj.each(function(){
            var condition = $(this).attr("data-condition");
            var condition_obj = $(this);
            condition_obj
            .addClass("d-none");
            //var fields = condition_obj.find("[required]").not(".d-none");
            var fields = condition_obj.find("input,select,textarea").not(".d-none");
            fields.each(function(){
                var field = $(this);
                if(field.closest("[data-condition]").attr("data-condition") == condition){
                    field
                    //.removeAttr("required")
                    //.attr("data-required", true)
                    .prop("disabled", true)
                    //.closest(".form-group").removeClass("has-error has-danger")
                    //.find(".help-block").html("");
                    if ( typeof field.attr("required") !== "undefined" ) {
                       field
                       .removeAttr("required")
                       .attr("data-required", true)
                       .closest(".form-group").removeClass("has-error has-danger")
                       .find(".help-block").html("");
                    }
                    if(field.hasClass("selectpicker")){
                       field.selectpicker("refresh");
                    }
                    if(condition_obj.attr("data-condition-reset")){
                        resetFormItems(condition_obj);
                    }
                }
            });
        });
    }
    $.fn.matchHeight._update();
}

function form_conditions(){
    var token_init = "form-conditions-init";
    $("[data-condition]").not("."+token_init).each(function(){
        $(this).addClass(token_init).conditionize({
            ifTrue: function(obj){
                form_item_attributes(obj, true);
            },
            ifFalse:function(obj){
                form_item_attributes(obj, false);
            }
        });
    });
}