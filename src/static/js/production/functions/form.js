function resetFormItems(obj) {
    obj.find(".form-control").val("");
    var selectpicker = obj.find(".selectpicker");
    if(selectpicker.length>0){
        selectpicker.each(function(){
            $(this).val($(this).find("option").eq(0).attr("value"));
            $(this).selectpicker("refresh");
            $(this).trigger("change");
        });
    }
    var dualbox = obj.find(".dualbox");
    if(dualbox.length>0){
        dualbox.each(function(){
            $(this).prev().find(".removeall").trigger("click");
        });
    }
    var range_slider = obj.find(".range-slider");
    if(range_slider.length>0){
        range_slider.each(function(){
            $(this).bootstrapSlider('setValue', $(this).data("slider-min"));
        });        
    }
    var select2 = obj.find(".select-2");
    if(select2.length>0){
        select2.val(null).trigger('change');
        /*select2.each(function(){
            $(this).empty();
        });*/
    }
    var form_select = obj.find(".form-select").not(".form-multi-select");
    if(form_select.length>0){
        form_select.each(function(){
            $(this).val($(this).find("option").eq(0).attr("value"));
        });
    }
    var cl_switch = obj.find(".cl-switch");
    if(cl_switch.length>0){
        cl_switch.each(function(){
            $(this).removeClass("active");
        });
    }
    var radio = obj.find("input[type='radio']:checked");
    if(radio.length>0){
        radio.each(function(){
            $("input[name='"+$(this).attr("name")+"']").first().prop("checked", true);
        });
    }
    var multiselect = obj.find(".form-multi-select");
    if(multiselect.length>0){
        multiselect.each(function(){
            $(this).val("");
            var $multiselect_obj = $(this).data()["multiselect"];
            if($(this).hasAttr("multiple").length == 0){
               $($multiselect_obj.$popupContainer).find(".multiselect-option").find("input[type='radio']").prop("checked", false);
               $($multiselect_obj.$popupContainer).find(".multiselect-option").first().find("input[type='radio']").prop("checked", true);
            }else{
               $($multiselect_obj.$popupContainer).find(".multiselect-option").find("input[type='checkbox']").prop("checked", false);
            }
            var $placeholder = $($multiselect_obj.$select).attr("placeholder");
                if(!IsBlank($placeholder)){
                    $($multiselect_obj.$button).find(".multiselect-text").html($placeholder);
                    $($multiselect_obj.$button).find(".multiselect-selected-text").html($placeholder);
                }
            $($multiselect_obj.$button).removeClass("active");
            $($multiselect_obj.$popupContainer).find(".btn-reset").trigger("click");
            $(this).find("optio[selected]").prop("selected", false);
        });
    }
}

function fieldsValidations(element) {
    var isFilled = true;
    var fields = element
        .find("select, textarea, input").serializeArray();

    $.each(fields, function(i, field) {
        if (!field.value){
            isFilled = false;
            return false;
        }
    });
    return isFilled;
}

function serializeArray(form) {
        var field, l, s = [];
        debugJS(typeof form);
        if (typeof form == 'object' && form.nodeName == "FORM") {
            var len = form.elements.length;
            for (var i=0; i<len; i++) {
                field = form.elements[i];
                debugJS(field.name, field.type);
                if (field.name && !field.disabled && field.type != 'file' && field.type != 'reset' && field.type != 'submit' && field.type != 'button') {
                    if (field.type == 'select-multiple') {
                        l = form.elements[i].options.length; 
                        for (j=0; j<l; j++) {
                            if(field.options[j].selected)
                                s[s.length] = { name: field.name, value: field.options[j].value };
                        }
                    } else if ((field.type != 'checkbox' && field.type != 'radio') || field.checked) {
                        s[s.length] = { name: field.name, value: field.value };
                    }
                }
            }
        }
        return s;
}
function form_control_switch(){
    var token_init = "form-control-switch-init";
    $(".form-check-input[role='switch'][data-on]").not("."+token_init).each(function(i){
        $(this).addClass(token_init);
        $(this).on("change", function(){
            if($(this).is(":checked")){
               var $value = $(this).data("on");
            }else{
               var $value = $(this).data("off");
            }
            $(this).next(".form-check-label").find("span").text($value);
        }).trigger("change");
    });
}


function form_control_clear(){
    var token_init = "form-control-clear-init";
    if($('.form-control.form-control-clear').not("."+token_init).length > 0){
        $('.form-control-clear').not("."+token_init).each(function(){
            var $field = $(this);
            $field.addClass(token_init); 
            $field.wrap("<div class='input-group'/>"); 
            //if($field.next().find(".btn").length > 1){
            //   $("<div class='btn-form-control-clear'></div>").insertAfter( $field.next().next() );
            //}else{
               $("<div class='btn-form-control-clear'></div>").insertAfter( $field );
            //}
            $field
            .on('input propertychange', function() {
                var $this = $(this);
                var visible = Boolean($this.val());
                $this.siblings('.btn-form-control-clear').toggleClass('d-none', !visible);
            })
            .siblings('.btn-form-control-clear').click(function() {
                $(this).siblings('.form-control-clear').val('').trigger('propertychange').focus();
                if($(this).siblings('.form-control-clear').hasClass("typeahead")){
                   var typeahead = $(this).siblings('.form-control-clear').data("typeahead");
                       typeahead.$menu
                       .removeClass("not-found")
                       .empty();
                }
                var form = $field.closest("form");
                if(form.length > 0){
                    if(form.data("auto-submit") && !form.hasClass("ajax-processing")){
                        if(form.find("input[name='page']").length > 0){
                           form.find("input[name='page']").val(1);
                        }
                        form.submit();
                    }                    
                }
            })
            .trigger('propertychange');     
        });
    }   
}
function form_control_password_toggle(){
    var token_init = "form-control-password-toggle-init";
    if($("input[type='password'].form-control-password-toggle").not("."+token_init).length > 0){
        $("input[type='password'].form-control-password-toggle").not("."+token_init).each(function(i){
            var $field = $(this);
                $field.addClass(token_init);
                $field.wrap("<div class='input-group'/>");
                var className="";
                if($field.hasClass("border-0")){
                   className = "border-0";
                }
                $field.parent().append('<a href="#" class="btn '+className+' bg-white"><i class="icon fa fa-eye-slash" aria-hidden="true"></i></a>');
            var $toggle = $field.next("a");
            var $icon = $toggle.find(".icon");
            if($field.hasClass("form-control-lg")) {
               $field.parent().addClass("input-group-lg");
            }
            $toggle.on('click', function(e) {
                e.preventDefault();
                if($field.attr("type") == "text"){
                    $field.attr('type', 'password');
                    $icon
                    .addClass( "fa-eye-slash" )
                    .removeClass( "fa-eye" );
                }else if($field.attr("type") == "password"){
                    $field.attr('type', 'text');
                    $icon
                    .removeClass( "fa-eye-slash" )
                    .addClass( "fa-eye" );
                }
            });
        });
    }
}
function form_control_readonly(){
    var token_init = "form-control-readonly-init";
    if($("input[readonly].form-control-readonly").not("."+token_init).length > 0){
        $("input[readonly].form-control-readonly").not("."+token_init).each(function(i){
            var $field = $(this);
                $field.addClass(token_init);
                $field.wrap("<div class='input-group'/>");
                $field.parent().append('<div class="btn btn-unlinked"><i class="icon fa fa-2x fa-lock" aria-hidden="true"></i></div>');
            if($field.hasClass("form-control-lg")) {
               $field.parent().addClass("input-group-lg");
            }
        });
    }
}
function form_control_editable(){
    var token_init = "form-control-editable-init";
    if($(".form-control-editable").not("."+token_init).length > 0){
        $(".form-control-editable").not("."+token_init).each(function(i){
            var $field = $(this);
            if($field.is("input") || $field.is("textarea") || $field.is("select")){
                $field.addClass(token_init);
                if($field.is("input") || $field.is("textarea")){
                    $field.removeClass("form-control").addClass("form-control-plaintext").prop("readonly", true);
                }
                if($field.is("select")){
                   $field.addClass("select-editable");
                }
                var label = $field.closest(".form-group").find("label.form-label");
                if(label.length>0){
                   label.addClass("align-items-center flex-row").append("<a href='#' class='btn-form-control-edit'>(Edit)</a>");
                   var btn_edit = label.find(".btn-form-control-edit");
                       btn_edit.on("click", function(e){
                           e.preventDefault();
                           if($(this).hasClass("active")){
                                if($field.is("input") || $field.is("textarea")){
                                    $field.removeClass("form-control").addClass("form-control-plaintext").prop("readonly", true);
                                }
                                if($field.is("select")){
                                   $field.addClass("select-editable");
                                }
                                $(this).removeClass("active");
                           }else{
                               if($field.is("input") || $field.is("textarea")){
                                  $field.removeClass("form-control-plaintext").addClass("form-control").prop("readonly", false);
                               }
                               if($field.is("select")){
                                  $field.removeClass("select-editable");
                               }
                               $(this).addClass("active");
                           }                       
                       });
                }
                if($field.is("input") || $field.is("textarea")){
                    if(IsBlank($field.val())){
                       if(label.length>0){
                          btn_edit.trigger("click");
                       }else{
                          $field.removeClass("form-control-plaintext").addClass("form-control").prop("readonly", false);
                       }
                    }
                    $field.on("focus", function(e){
                        e.preventDefault();
                        if(!btn_edit.hasClass("active")){
                            btn_edit.trigger("click");
                        }
                    });
                    document.addEventListener('click', function(event) {
                        var isClickInside = $field[0].contains(event.target);
                        if (!isClickInside) {
                            if(!$(event.target).hasClass("btn-form-control-edit")){
                                if(btn_edit.hasClass("active")){
                                   btn_edit.trigger("click");
                                }                       
                            }
                        }
                    });
                }
                if($field.is("select")){
                    $field.on('rendered.bs.select', function (e) {
                        if(IsBlank($field.val())){
                           if(label.length>0){
                              btn_edit.trigger("click");
                           }else{
                              $field.removeClass("select-editable");
                           }
                        }
                        $(e.target).parent().on("click", function(e){
                            e.preventDefault();
                            var btn_edit = $(this).closest(".form-group").find(".btn-form-control-edit");
                            if(!btn_edit.hasClass("active")){
                                btn_edit.trigger("click");
                            }
                        });
                        document.addEventListener('click', function(event) {
                            var isClickInside =  $(e.target).parent()[0].contains(event.target);
                            debugJS(event.target);
                            if (!isClickInside) {
                                if(!$(event.target).hasClass("btn-form-control-edit")){
                                    if(btn_edit.hasClass("active")){
                                       btn_edit.trigger("click");
                                    }                       
                                }
                            }
                        });
                    });
                }
                /*onClassChange($field, "is-invalid", function(){
                    btn_edit.trigger("click");
                });*/       
            }
        });
    }
}
function getFormData(formData, data, previousKey) {
  if(data instanceof Object) {
    Object.keys(data).forEach(function(key){
      const value = data[key];
      if (value instanceof Object && !Array.isArray(value)) {
        return this.getFormData(formData, value, key);
      }
      if (previousKey) {
        key = `${previousKey}[${key}]`;
      }
      if (Array.isArray(value)) {
        value.forEach(function(val){
          formData.append(`${key}[]`, val);
        });
      } else {
        formData.append(key, value);
      }
    });
  }
}
function createFormData(formData, key, data) {
    if (data === Object(data) || Array.isArray(data)) {
        for (var i in data) {
            createFormData(formData, key + '[' + i + ']', data[i]);
        }
    } else {
        formData.append(key, data);
    }
}
function deleteFormData(formData, key) {
    if (key === Object(key) || Array.isArray(key)) {
        for (var i in key) {
            deleteFormData(formData, key[i]);
        }
    } else {
        debugJS("delete")
        debugJS(key)
        formData.delete(key);
    }
}
function selectChain(){
    var token_init = "form-select-chain-init";
    if($(".form-select-chain").not("."+token_init).length>0){
        $('.form-select-chain').not("."+token_init).each(function(){
            obj = $(this);
            obj.addClass(token_init);
            var obj_name = obj.attr("name");
            var method = obj.data("method");
            var chain = obj.data("chain");
            var chain_on_select = Boolean(obj.data("chain-on-select"));
            var chain_value = obj.data("chain-value");
            var obj_chain = $("[name='"+chain+"']");
            if(obj_chain.length > 0 ){
                var obj_chain_all = obj_chain.attr("data-chain-all");
                obj.on("change", function(e){
                    if(chain_on_select && e.isTrigger){
                        return false;
                    }
                    if(obj_chain.hasClass("selectpicker")){
                        obj_chain.val("").selectpicker("refresh");
                    }
                    resetFormItems(obj_chain.parent());
                    if(!IsBlank($(this).val())){
                        var $data = {};
                            $data[obj_name] = $(this).val();
                            $data["all"] = obj_chain_all;
                            $data["selected"] = obj_chain.attr("data-chain-value");
                        var query = new ajax_query();
                            query.method = method;
                            query.vars   = $data;
                            query.form   = {};
                            query.objs   = obj_chain;
                            query.request();                        
                    }else{
                        if(!Boolean(obj_chain.data("chain-on-select"))){
                           obj_chain.find("option[value='']").addClass("js d-none");
                           obj_chain.find("option").not("[value='']").remove(); 
                        }
                        if(obj_chain.hasClass("selectpicker")){
                           obj_chain.find("option").first().prop("selected", true);
                           obj_chain.selectpicker("refresh");
                           obj_chain.selectpicker("show");
                        }
                    }

                    if(!IsBlank(obj_chain.data("chain-value"))){
                        obj_chain.val(obj_chain.data("chain-value"));
                        if(obj_chain.hasClass("selectpicker")){
                            obj_chain.selectpicker("refresh");
                        }
                    }
                });
                if(IsBlank(chain_value)){
                  obj.trigger("change");
                }else{
                    if(!IsBlank(obj_chain.data("chain-value"))){
                        obj_chain.val(obj_chain.data("chain-value"));
                        if(obj_chain.hasClass("selectpicker")){
                           obj_chain.selectpicker("refresh");
                        }
                    }
                }
            }

        });
    }
}