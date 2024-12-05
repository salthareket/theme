function form_control_timepicker(){
    var token_init = "form-control-timepicker-init";
    if($(".form-control-timepicker").not("."+token_init).length > 0){
        $(".form-control-timepicker").not("."+token_init).each(function(i){
            var $field = $(this);
            var $data = $field.data();
                $data["listWidth"] = 1;
                $data["disableTextInput"] = true;
                $data["useSelect"] = true;
                $data["className"] = "form-select";
                $data["timeFormat"] = "H:i";
                debugJS($data)
                $field.timepicker($data);
                if($field.attr("required")){
                   $field.next("select").attr("required","");
               }
               if($field.attr("placeholder")){
                    var selected = "";
                    if(IsBlank($field.attr("value"))){
                       selected = "selected";
                    }
                    $field.next("select").prepend("<option value='' "+selected+">"+$field.attr("placeholder")+"</option>");
                }
                
        });
    }
}