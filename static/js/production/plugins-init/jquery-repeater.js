function form_repeater(){
    var token_init = "form-repeater-init";
    if($(".repeater").not("."+token_init).length>0){
        $('.repeater').not("."+token_init).each(function(){
            $repeater = $(this);
            $repeater.addClass(token_init);
            var repeaterId = $repeater.find("[data-repeater-list]").data("repeater-list");
            var repeaterForm = $repeater.closest("form").data("ajax-method");
            var uniqueChoice = bool($repeater.data("unique-choice"), false);
            var initEmpty = bool($repeater.data("show-first"), false);
            var isFirstItemUndeletable = bool($repeater.data("unremovable-first"), false);
            var removeConfirm = bool($repeater.data("remove-confirm"), false);
            var options = {
                //initEmpty: initEmpty,
                isFirstItemUndeletable: isFirstItemUndeletable,
                show: function () {
                    var item = $(this);
                    var $callback = item.data("callback");
                    item.slideDown(100, function(){
                        if(typeof window[$callback] === "function"){
                            window[$callback](item);
                        }
                        if(item.find(".selectpicker").length > 0){
                           item.find(".selectpicker").selectpicker();
                        }
                    });
                    if(uniqueChoice){
                        item.find('select.unique').on("change", function(e, clickedIndex, isSelected, previousValue){
                            repeaterSelectChange($(this).closest(".form-group"), previousValue);
                            var max = $(this).find("option").not("[value='']").length;
                            if($(this).find("option").not("[value='']").not(".d-none").length == 1 || $repeater.find("[data-repeater-item]").length >= max){
                               $repeater.find("[data-repeater-create]").addClass("d-none"); 
                            }
                            $(this).closest(".form-group").find('select.unique').removeClass("loading-xs loading-process");
                        }).trigger("change");
                    }
                },
                hide: function (deleteElement) {
                    var item = $(this);
                    if(removeConfirm){
                         _confirm(
                            "", 
                            "Are you sure you want to delete this?",
                            "sm",
                            "",
                            "",
                            "",
                            function(){
                                repeaterOnRemove(item);
                                item.slideUp(deleteElement);
                                if(uniqueChoice){
                                    $repeater.find("[data-repeater-create]").removeClass("d-none");
                                }
                            }
                         );                        
                     }else{
                        repeaterOnRemove(item);
                        item.slideUp(deleteElement);
                        if(uniqueChoice){
                            $repeater.find("[data-repeater-create]").removeClass("d-none");
                        }
                     }
                },
                ready: function (setIndexes) {
                    //$dragAndDrop.on('drop', setIndexes);
                }
            };
            if(!initEmpty){
                options["initEmpty"] = initEmpty;
            }
            debugJS(options)
            if($repeater.find(".inner-repeater").length > 0){
                var initEmpty = bool($repeater.find(".inner-repeater").data("show-first"), false);
                var isFirstItemUndeletable = bool($repeater.find(".inner-repeater").data("unremovable-first"), false)
                options["repeaters"] = [{
                    selector: '.inner-repeater',
                    initEmpty: initEmpty,
                    isFirstItemUndeletable: isFirstItemUndeletable,
                    show: function () {
                        var item = $(this);
                        var $callback = item.data("callback");
                        item.slideDown(0, function(){
                            if(typeof window[$callback] === "function"){
                                window[$callback](item);
                            }
                        });
                    }
                }];
            }
            var repeater_obj = $repeater.repeater(options);
            var setList = [];
            if(typeof window[repeaterId] !== "undefined"){
                if(window[repeaterId]){
                    setList = window[repeaterId]
                }
            }
            if((setList.length > 0 || !initEmpty) && !$repeater.hasClass("set-values")){
                $repeater.addClass("set-values");
                repeater_obj.setList(window[repeaterId]);
                //repeater_obj.repeater(options); note: bu 2. kez init ediyordu.
            }else{
                if(!IsBlank(repeaterForm)){
                    var cookie = Cookies.get(repeaterForm);
                    if(!IsBlank(cookie)){
                        cookie = $.parseJSON(cookie);
                        var values = cookie[repeaterId];
                        if(!IsBlank(values)){
                            repeater_obj.setList(values);
                            //repeater_obj.repeater(options); note: bu 2. kez init ediyordu.
                        }
                    }                       
                }
            }
            if(typeof window[repeaterId+"_ready"] !== "undefined"){
                window[repeaterId+"_ready"]($repeater);
            }
            if(uniqueChoice){
                $repeater.find('select.unique').on("change", function(e, clickedIndex, isSelected, previousValue){
                    repeaterSelectChange($(this).closest(".form-group"), previousValue);
                    $(this).closest(".form-group").find('select.unique').removeClass("loading-xs loading-process");
                }).trigger("change");
            }
            /*$repeater.find("[data-repeater-create]").on("click", function(){
                if(){

                }
            })*/
            $repeater.removeClass("loading-process");
        });
    }   
}
function repeaterSelectChange($obj, previousValue){
    var selectpicker = $obj.find('select.unique');
    var value = selectpicker.find("option:selected").val();
    var parent = $obj.closest("[data-repeater-list]");
    var selectedValues = Array.from(parent.find("select.unique option:selected")).map(function(el){ return el.value});
    parent.find('select.unique').find("option[disabled]").prop("disabled", false).removeClass("d-none");
    if(!IsBlank(selectpicker.find("option:selected").attr("data-placeholder"))){
        if($obj.find("[data-update-placeholder]").length>0){
            $obj.find("[data-update-placeholder]").attr("placeholder", selectpicker.find("option:selected").attr("data-placeholder"));
        }
    }
    if(selectedValues){
       for(var i=0;i<selectedValues.length;i++){
           if(selectpicker.attr("name") != parent.find("option[value='"+selectedValues[i]+"']").closest("select.unique").attr("name")){
              parent.find("option[value='"+selectedValues[i]+"']").not(":selected").prop("disabled", true).addClass("d-none");
           }
       }
    }
}
function repeaterOnRemove($obj){
    var selectpicker = $obj.find('select');
    var value = selectpicker.find("option:selected").val();
    var parent = $obj.closest("[data-repeater-list]");
    parent.find("option[value='"+value+"']").each(function(){
        $(this).prop("disabled", false).removeClass("d-none");
    });
    parent.next().removeClass("disabled");

    var $callback = $obj.find("[data-repeater-delete]").data("callback");
    if(typeof window[$callback] === "function"){
        window[$callback]($obj);
    }
    if($obj.find(".selectpicker").length > 0){
        $obj.find(".selectpicker").selectpicker();
    }
}