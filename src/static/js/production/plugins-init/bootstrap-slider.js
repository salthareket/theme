function range_slider(){
    var token_init = "form-range-slider-init";
    if($("input.range-slider").not("."+token_init).length>0){
        $('input.range-slider').not("."+token_init).each(function(){
            $(this).addClass(token_init).wrap("<div class='slider-wrapper'/>").parent().append("<div class='view'/>");
            var formatter = $(this).data("formatter");
            var handle_number = $(this).data("handle-number");
            var viewer = $(this).closest("form").find("#"+$(this).attr("id")+"-value");
            var range = $(this).bootstrapSlider({
                tooltip: 'hide'
            });
            range.on("change", function(e){
                if(!IsBlank(formatter)){
                    var value = $(this).bootstrapSlider('getValue');
                    if(Array.isArray(value)){
                        if(value[1]==65){
                           value[1]="65+"
                        }
                    }else{
                        if(value==65){
                           value="65+"
                        }
                    }
                    if(Array.isArray(value)){
                       value = formatter.replace("$value", value.join(" - "));
                       $(e.target).parent().find(".view").removeClass("d-none");
                    }else{
                       value = formatter.replace("$value", value);
                       $(e.target).parent().find(".view").addClass("d-none");
                    }
                    $(e.target).parent().find(".view").html(value);

                    if(viewer.length > 0){
                        if(viewer.get(0).tagName.toLowerCase() == "input"){
                            viewer.val(value).focus();
                        }else{
                            viewer.html(value);
                        }                        
                    }
                }
                if(handle_number){
                    var value = $(this).bootstrapSlider('getValue');
                    if(Array.isArray(value)){
                        if(value[1]==65){
                           value[1]="65+"
                        }
                   }else{
                        if(value==65){
                           value="65+"
                        }
                   }
                    if(Array.isArray(value)){
                        $(this).parent().find(".min-slider-handle").html(value[0]);
                        $(this).parent().find(".max-slider-handle").html(value[1]);
                    }else{
                        $(this).parent().find(".min-slider-handle").html(value);
                    }
                }
            }).trigger("change");

            if(viewer.length > 0 && viewer.get(0).tagName.toLowerCase() == "input"){
               viewer.on("change", function(){
                  var value = $(this).val();
                  if(!IsBlank(value)){
                     value = value.split(".")[0].replaceAll(",","");
                     range.bootstrapSlider('setValue', value);
                  }else{
                     range.bootstrapSlider('setValue', range.data("slider-min"));
                     viewer.val(range.data("slider-min")).focus();
                  }
               })
            }
        });
    }   
}
