function formAutoFill(){
    if($("form.form-autofill").length > 0){
        $("form.form-autofill").each(function(){
            var $form = $(this);
            var $formMethod = $form.data("ajax-method");
            var $formData = Cookies.get($formMethod);
            if(!IsBlank($formData)){
                $formData = $.parseJSON($formData);
                debugJS($formData);
                populate($form[0], $formData);
                for(var $el in $formData){
                    var $obj = $("[name="+$el+"]");
                    var $value = $formData[$el];
                    if(/^\d+$/.test($value)){
                       $value = parseFloat($value);
                    }

                    if($obj.is(":checkbox") && !Number.isInteger($value)){
                        debugJS(":::checkbox - "+$el+" = "+$value)
                        if($value.toLowerCase() == 1 || $value.toLowerCase() == true || $value.toLowerCase() == "true" || $value.toLowerCase() == "yes" || $value.toLowerCase() == "ok"){
                            debugJS("setted")
                           $obj.prop("checked", true);//.trigger("click");
                        }                   
                    }

                    if($obj.hasClass("range-slider")){
                        if(Number.isInteger($value)){
                            var $range = [$value];
                        }else{
                            var $range = $value.split(",");
                                $range = $range.map(Number);                        
                        }
                        if($range.length == 1){
                            $obj
                            .attr("data-range", false)
                            .bootstrapSlider('setAttribute', 'range', false)
                            .bootstrapSlider('setAttribute', 'value', $range[0])
                            .bootstrapSlider('setValue', $range[0]);
                        }else{
                            $obj
                            .attr("data-range", true)
                            .bootstrapSlider('setAttribute', 'range', true)
                            .bootstrapSlider('setAttribute', 'value', $range)
                            .bootstrapSlider('setValue', $range);
                        }
                        $obj.bootstrapSlider('refresh', { useCurrentValue: true }).trigger("change"); 
                    }

                    if($obj.attr("type") == "number"){
                       $obj.trigger("input");
                       $obj.val($value);
                    }
                }
                if(root.logged()){
                   Cookies.remove($formMethod);
                }
            }
        });
    }
}
