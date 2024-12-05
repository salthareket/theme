function multiSelect(){
    var token_init = "form-multi-select-init";
    if($(".form-multi-select").not("."+token_init).length>0){
        $('.form-multi-select').not("."+token_init).each(function(){

            var $data = $(this).data();

            var onChange = function(select){
                var $obj = select;
                var $data = $($obj.$select).data();
                var $placeholder = $($obj.$select).attr("placeholder");
                if($($obj.$select).hasAttr("multiple").length){
                    var $selectedText = "";
                    if(!IsBlank($placeholder)){
                       $selectedText = $placeholder;
                    }
                    var $checkedItems = $($obj.$popupContainer).find("input:checked");
                    debugJS($checkedItems);
                    var $count = $checkedItems.length;
                    if($count > 0){
                        if($data["tokens"]){
                            $selectedText = "";
                            for(var i=0;i<$count; i++){
                                $selectedText += "<div class='badge rounded-pill text-bg-primary m-1 py-2 px-3'>" + $($checkedItems[i]).next("label").text() + "</div>"
                            }
                        }else{
                            $selectedText += "<div class='badge rounded-pill text-bg-primary ms-auto'>" + $count + "</div>";
                        }
                        $($obj.$button).addClass("active");
                        // hide reset
                        $($obj.$popupContainer).find(".btn-reset").removeClass("d-none");
                        $($obj.$popupContainer).find(".multiselect-reset").removeClass("d-none");
                    }else{
                        $($obj.$button).removeClass("active");
                        // show reset
                        $($obj.$popupContainer).find(".btn-reset").addClass("d-none");
                        if(IsBlank($data["selectAll"])){
                            $($obj.$popupContainer).find(".multiselect-reset").addClass("d-none");
                        }
                    }
                    $($obj.$button).find(".multiselect-text").html($selectedText);
                }
                if($($obj.$select).hasAttr("multiple").length == 0){
                    if(IsBlank($($obj.$select).val())){
                        $($obj.$button).removeClass("active");
                        if(!IsBlank($placeholder)){
                           $($obj.$button).find(".multiselect-text").html($placeholder);
                        }
                    }else{
                        $($obj.$button).addClass("active");
                        $($obj.$button).find(".multiselect-text").html($($obj.$select).find("option:selected").text());
                    }
                }
                if($($obj.$button).closest("form").data("auto-submit")){
                    $($obj.$button).next(".multiselect-container").dropdown("hide");
                }
            };

            var onInit = function(select, container){
                var $data = $(select).data();
                var $obj = $(select).next(".btn-group").find(".multiselect-container");
                $obj.wrapInner("<div class='multiselect-container-set'></div>");
                $obj.find(".multiselect-group").each(function(){
                  $(this).nextUntil(".multiselect-group").addBack().wrapAll('<div class="multiselect-group-item"></div>')
                });
                
                if(!IsBlank($data["unselect"])){
                    if($(select).hasAttr("multiple").length == 0 && $(select).find("option[selected]").length == 0 ){
                        $(select).val("");
                        $(container).find("input[type='radio']:checked").prop("checked", false);
                    }
                }

                if($data["columns"]){
                    var column_count = $(select).data("columns");
                    $obj.find(".multiselect-container-set").attr("style","column-count:"+column_count+";")
                }

                if(!IsBlank($placeholder)){// && $(select).hasAttr("multiple").length){
                    $(select).next(".btn-group").find(".multiselect-selected-text").removeClass("multiselect-selected-text").addClass("multiselect-text d-flex align-items-center flex-wrap")
                }
                
                if(!IsBlank($data["reset"])){
                   var reset = $(select).next(".btn-group").find('.multiselect-reset');
                       reset.prependTo($obj);
                       if($(select).closest("form").data("auto-submit")){
                            reset.on("click", function(){
                                if(!$(select).closest("form").hasClass("ajax-processing")){
                                    if($(select).closest("form").find("input[name='page']").length > 0){
                                       $(select).closest("form").find("input[name='page']").val(1);
                                    }
                                    $(select).closest("form").submit();                                    
                                }
                            });
                       }
                }
                if(!IsBlank($data["search"])){
                    var search = $(select).next(".btn-group").find('.multiselect-filter');
                        search.prependTo($obj);
                }
                if(!IsBlank($data["selectAll"])){
                    var selectAll = $(select).next(".btn-group").find('.multiselect-all');
                    if(!IsBlank($data["reset"])){
                       selectAll.prependTo($obj.find('.multiselect-reset'));
                    }else{
                       selectAll.prependTo($obj).wrap("<div class='multiselect-all-container p-2'></div>");
                    }
                    $obj.find('.multiselect-reset').removeClass("d-none");
                }

                $($(select).next(".btn-group").find('.multiselect-search'))
                .on('input', function(e){
                    setTimeout(function(){
                        $obj.find(".multiselect-group-item").each(function(){
                           var resultCount = $(this).find(".multiselect-option").not(".multiselect-filter-hidden").length;
                           if(!resultCount){
                              $(this).find(".multiselect-group").addClass("d-none");
                           }else{
                              $(this).find(".multiselect-group").removeClass("d-none");
                           }
                        });                    
                    }, 500);
                });

                $(select).next(".btn-group").find('.multiselect-reset').find(".btn-reset")
                .on("click", function(e){
                    onChange($data["multiselect"]);
                });

                if($data["opened"]){
                    $obj.addClass("show").addClass("test").css("display", "block");
                }

            };

            var $options = {
                maxHeight: 350,
                indentGroupOptions : false,
                templates : {
                    resetButton: '<div class="multiselect-reset p-2 d-none"><button type="button" class="btn-reset btn btn-sm btn-block btn-outline-secondary d-none"></button></div>'
                },
                onInitialized: function(select, container) {
                    onInit(select, container);
                    onChange(this);
                },
                onChange: function(option, checked) {
                    onChange(this);
                },
                onDropdownHide: function(event) {
                    var select = $(event.target).parent().prev("select");
                    debugJS(select);
                    select.trigger("click");
                }
            }

            var $placeholder = $(this).attr("placeholder");
            if(!IsBlank($placeholder)){
                $options["nonSelectedText"] = $placeholder;
            }
            if(!IsBlank($data["search"])){
               $options["enableFiltering"] = true;
               $options["enableCaseInsensitiveFiltering"] = true;
            }
            if(!IsBlank($data["reset"])){
               $options["includeResetOption"] = true;
            }
            if(!IsBlank($data["selectAll"])){
               $options["includeSelectAllOption"] = true;
               $options["onSelectAll"] = $options["onChange"];
               $options["onDeselectAll"] = $options["onChange"];
            }
            if(!IsBlank($data["tokens"])){
               $options["numberDisplayed"] = 0;
            }

            $(this).multiselect($options);

        });
    }
}