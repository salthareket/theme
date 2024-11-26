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

/*
function input_spinner_init(){
    var token_init = "input-spinner-init";
    $("input[type='number']").not("."+token_init).each(function(i){
        $(this).addClass(token_init);
        var classSize = "lg";
        if($(this).hasClass("size-md")){
            classSize="md";
        }
        var classWidth = "lg";
        if($(this).hasClass("width-md")){
            classWidth="md";
        }
        $(this).inputSpinner({
            incrementButton: "<strong>+</strong>",
            decrementButton: "<strong>-</strong>",
            groupClass: "input-group-quantity input-group-quantity-right input-group-"+classSize+" input-group-quantity-"+classWidth,
            buttonsClass: "",
            buttonsWidth: "2.5rem",
            textAlign: "center",
            autoDelay: 500,
            autoInterval: 100,
            boostThreshold: 10,
            boostMultiplier: "auto",
            locale: null
        });
        //$(this).next().find(".input-spinner-init").attr("name",$(this).attr("name")+"-fake-"+i)       
    });
}
*/

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
/*
function form_control_password_strength(){
    var token_init = "form-control-password-strength-init";
    if($("input[type='password'].form-control-password-strength").not("."+token_init).length > 0){
        $("input[type='password'].form-control-password-strength").not("."+token_init).each(function(i){
            var $el = $(this);
            $el.addClass(token_init);
            $el.closest(".form-group").append("<div class='form-text'><span></span></div>");

            var options = {};
                options.common = {
                    minChar : 8,
                    usernameField : $("input[name='email_new']"),
                    debug:true
                }
                options.ui = {
                    container: $el.closest(".form-group").find('.form-text'),
                    showStatus: true,
                    showProgressBar: false,
                    showPopover:false,
                    showVerdictsInsideProgressBar : false,
                    viewports: {
                        verdict: $el.closest(".form-group").find('span'),
                        errors: $el.closest(".form-group").find('span')
                    },
                    verdicts : ["Weak", "Normal", "Medium", "Strong", "Very Strong"],
                    errorMessages: {
                          password_too_short: "The Password is too short",
                          email_as_password: "Do not use your email as your password",
                          same_as_username: "Your password cannot contain your username",
                          two_character_classes: "Use different character classes",
                          repeated_character: "Too many repetitions",
                          sequence_found: "Your password contains sequences"
                    },
                    spanError : function (options, key) {
                          var text = options.ui.errorMessages[key];
                          debugJS(key);
                          return '<span style="color: #d52929">' + text + '</span>';
                    },
                    showErrors: false
                };
                options.rules = {
                    activated: {
                        wordMaxLength: true,
                        wordInvalidChar: true
                    }
                };
            $($el).pwstrength(options);
        });
    }   
}
*/
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
/*
function form_control_datepicker(){
    var token_init = "form-control-datepicker-init";
    $('.input-group.date, .datepicker').not("."+token_init).each(function(){
        $(this).addClass(token_init);
        var options = {
            language: root.lang,
            weekStart: 1,
            todayHighlight: false,
            //startView: "years",
            autoclose: true,
            //startDate : new Date()
            templates : {
                leftArrow: '<i class="fa fa-angle-left"></i>',
                rightArrow: '<i class="fa fa-angle-right"></i>'
            },
            //format: {
            //    toDisplay: function (date, format, language) {
            //        var d = new Date(date);
            //        return moment(d).format('YYYY-MM-DD');
            //    },
            //    toValue: function (date, format, language) {
            //        var d = new Date(date);
            //        return moment(d).format('YYYY-MM-DD');
            //    }
            //}
        };
        if($(this).hasClass("form-control-date-min-today")){
           options["startDate"] =  new Date();
        }
        if($(this).hasClass("form-control-date-monthyear")){
            options["maxViewMode"] = "years";
            options["minViewMode"] = "months";
            options["format"]      = "mm.yyyy";
        }
        var picker = $(this)
        .datepicker(options)
        .on("changeDate", function(e) {
            var obj = $(e.target);
            debugJS($(e.target));
            if(obj.hasClass("date-start")){
                var dateRelated = $(obj.attr("data-related"))
                var startDate = e.date;
                if(typeof startDate === "undefined"){
                    startDate = obj.datepicker("getDate");
                }
                var endDate   = dateRelated.datepicker("getDate");
                var startDateParsed = new Date(startDate);
                var endDateParsed = new Date(endDate);
                if(startDateParsed > endDateParsed && !IsBlank(endDate)){
                    dateRelated.datepicker("clearDates");
                }
                dateRelated.datepicker("setStartDate", startDate);
                debugJS(dateRelated);
                debugJS(startDate);
            }
        })
        .on('hide', function(e) {
            e.preventDefault();
            e.stopPropagation();
        })
        //.trigger("changeDate");
        if(!IsBlank(picker.data("date-start"))){
            startDate = picker.data("date-start");
            picker.datepicker("setStartDate", startDate);
        }
        if(!IsBlank(picker.data("date-end"))){
            endDate = new Date(picker.data("date-end"));
            picker.datepicker("setEndDate", endDate);
         }
    });
    $('.input-daterange').not("."+token_init).each(function(){
        $(this).addClass(token_init);
        $(this)
        .datepicker({
            maxViewMode: 0,
            weekStart: 1,
            todayBtn: "linked",
            language: root.lang,
            //startView: "years",
            autoclose: true
         });
    })  
}
*/
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
/*
function select_2(){
    var token_init = "select-2-init";
    $(".select-2").not("."+token_init).each(function(){
        var $obj = $(this);
        var $args = {
            theme: 'bootstrap-5',
            width: '100%',
            closeOnSelect : false
        };

        if($obj.data("hide-search")){
            $args["minimumResultsForSearch"] = -1;
        }

        var classes = "";
        if(bool($obj.data("checkbox"), false)){
             classes += "select2-checkbox";
        }
        if(bool($obj.data("hide-selected"), true)){
             classes += "select2-hide-selected";
        }
        $args["dropdownCssClass"] = classes;

        if($(this).attr("min")){
           if($(this).attr("min") > 0){
              $args["minimumInputLength"] = $(this).attr("min");
           }
        }
        if($(this).attr("placeholder")){
           $args["placeholder"] =  $(this).attr("placeholder");
        }
        if($(this).data("tags")){
            $args["tags"] = true;
            $args["tokenSeparators"] = [','];
            $args["createTag"] = function (params) {
                var term = $.trim(params.term);

                if (term === '') {
                  return null;
                }

                return {
                  id: term,
                  text: term,
                  newTag: true // add additional parameters
                }
            }
        }
        if($(this).data("autocomplete")){
            $args["ajax"] =  {
                dataType: 'json',
                delay: 250,
                url: ajax_request_vars.url+"?ajax=query",
                method : "post",
                data: function (params) {
                  var query = {
                    method : "autocomplete_terms",
                    keyword: params.term,
                    _wpnonce : ajax_request_vars.ajax_nonce,
                    vars : {
                        type : $(this).data("type"),
                        count : $(this).data("count"),
                        response_extra : $(this).data("response-extra"),
                        page: params.page || 1,
                        selected : function(){
                            var selected = "";
                            if(bool($obj.data("hide-selected"), true)){
                                var data = $obj.select2('data');
                                selected = data.map(function(a) { return a.id; })                                
                            }
                            return selected;
                        }
                    }
                  }
                  return query;
                },
                processResults: function (data, params) {
                    return {
                        results: data.data.results,
                        pagination: data.data.pagination
                    };
                },
                cache: true
              }
              $args["templateResult"] = formatRepo;
              if(bool($obj.data("minimal-view"), false)){
                 $args["templateSelection"] = formatMinimalSelection;
              }else{
                 $args["templateSelection"] = formatRepoSelection;
              }
        }
        $(this).select2($args).addClass(token_init);

        if($(this).data("sortable")){
            $(this).next().find("ul.select2-selection__rendered").sortable({
                containment: 'parent'
            });
        }
        if(!$(this).data("dropdown")){
            $(this).on('select2:opening select2:close', function(e){
                //$('body').toggleClass('kill-all-select2-dropdowns', e.type=='select2:opening');
            });
        }
        /*
        //$(this).on('select2:close', function() {
        //    let select = $(this)
        //    $(this).next('span.select2').find('ul').html(function() {
        //       let count = select.select2('data').length
        //       return "<li class='w-100 d-flex'>" + select.attr("placeholder") + "<div class='badge rounded-pill text-bg-primary ms-auto'>" + count + "</div></li>"
        //    });
        //});


        //fix default selected items title re-print
        //$(this).next(".select2").find(".select2-selection__choice__display").each(function(){
        //    if(IsBlank($(this).text())){
        //        var title = $(this).closest(".select2-selection__choice").attr("title");
        //        $(this).text(title);
        //    }
        //});
        //$(this).on('change', function (e) {
        //   setTimeout(function(){
        //        $(e.target).next(".select2").find(".select2-selection__choice__display").each(function(){
        //            if(IsBlank($(this).text())){
        //                var title = $(this).closest(".select2-selection__choice").attr("title");
        //                $(this).text(title);
        //            }
        //        });                
        //    }, 1);
        //});
    });
    
    function getSelected(){
        debugJS($(this).select2('data'))
        return $(this).select2('data')                   
    }

    function formatRepo (repo) {
        if (repo.loading) {
            return repo.text;
        }
        var $container = $(
            "<div class='select2-result-item'>" +
                "<div class='select2-result-item__title'></div>" +
            "</div>"
        );
        $container.find(".select2-result-item__title").text(repo.text);

        return $container;
    }

    function formatRepoSelection (repo) {
      return repo.text || repo.name;
    }
    function formatMinimalSelection(data, $obj) {
        //debugJS(data)
        //debugJS($obj)
        let select = $obj;
        var count = select.find('option:selected').length;
        //$obj.next('span.select2').find('ul').html(function() {
               //let count = select.select2('data').length
               //alert(count)
               return "<li class='w-100 d-flex'>" + select.attr("placeholder") + "<div class='badge rounded-pill text-bg-primary ms-auto'>" + count + "</div></li>"
        //});
    }
}

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

function fileinput(){
    var token_init = "fileinput-init";
    if($(".fileinput").not("."+token_init).length>0){
        $('.fileinput').not("."+token_init).each(function(){
            var defaults = {
                showPreview: false,
                showUploadStats: false,
                showUpload: false,
                language: "en",
                browseClass: "btn btn-outline-success",
                browseLabel: "BROWSE",
                removeClass: "btn btn-slim-default",
                removeLabel: "",
                removeIcon: "<i class='fa fa-times'></i>",
                previewFileIcon: "<i class='far fa-image'></i>"
                //allowedFileTypes : ['image'],
                //allowedFileExtensions : ['jpg', 'jpeg', 'gif', 'png', 'bmp']
            };
            var fileTypes = $(this).data("file-types");
            if (!IsBlank(fileTypes)) {
                fileTypes = fileTypes.split(",");
                if (fileTypes.length > 0) {
                    defaults["allowedFileTypes"] = fileTypes;//['image', 'html', 'text', 'video', 'audio', 'flash', 'object']
                }
            }
            var fileFormats = $(this).data("file-formats")
            if (!IsBlank(fileFormats)) {
                fileFormats = fileFormats.split(",");
                if (fileFormats.length > 0) {
                    defaults["allowedFileExtensions"] = fileFormats;
                }
            }
            $(this).removeAttr("data-file-types").removeAttr("data-file-formats");
            $(this).fileinput(defaults)
                .on('fileerror', function (event, data, msg) {
                    _alert('', msg);
                    $(this).fileinput('clear');
                })
                .on('fileselect', function (event, numFiles, label) {
                    debugJS(event);
                    debugJS(numFiles);
                    debugJS(label);
                    var pluginData = $(event.target).data("fileinput");
                    var component = $(event.target).closest('.file-input');
                    var filesCount = $(pluginData.$element).fileinput('getFilesCount');
                    var preview = "";
                    debugJS(pluginData);
                    debugJS(component);
                    if (filesCount > 0) {
                        var ext = getFileExtension(this.files[0].name);
                        alert(ext);
                        var fileType = ["jpg", "jpeg", "png", "gif", "bmp"].indexOf(ext) > -1 ? "image" : ["html", "doc", "docx", "rtf", "xls", "xlsx", "txt", "ppt", "pdf"].indexOf(ext) > -1 ? "doc" : "";
                        if (fileType == "image" || fileType == "doc") {
                            var btnBrowse = component.find(".btn-file");
                            btnBrowse.find("span").text("Preview");

                            if (fileType == "image") {
                                var img = $('<img/>', {
                                    class: 'img-fluid'
                                });
                                var file = this.files[0];
                                var reader = new FileReader();
                                reader.onload = function (e) {
                                    img.attr('src', e.target.result);
                                }
                                reader.readAsDataURL(file);
                                preview = $(img)[0].outerHTML;
                            }

                            if (fileType == "doc") {
                                var docUrl = URL.createObjectURL(this.files[0]);
                                var preview = '<iframe id="fake-preview" frameborder="0" scrolling="no" width="100%" height="500" src="' + docUrl + '"></iframe>';
                            }

                            btnBrowse.on("click", function (e) {
                                e.preventDefault();
                                switch (fileType) {
                                    case "doc":
                                        if (ext == "pdf" || ext == "html") {
                                            _alert('', preview, 'lg');
                                        } else {
                                            var fakePreview = $("body").find("#fake-preview");
                                            if (fakePreview.length > 0) {
                                                fakePreview.remove();
                                            }
                                            var hiddenPreview = $(preview);
                                            hiddenPreview.css("display", "none");
                                            $("body").append(hiddenPreview);
                                        }
                                        break;

                                    case "image":
                                        preview = $(img)[0].outerHTML;
                                        _alert('', preview, 'lg');
                                        break;
                                }
                            });

                        }
                    }
                })
                .on('filecleared', function (event) {
                    $("body").find("#fake-preview").remove();
                    var pluginData = $(event.target).data("fileinput");
                    var component = $(event.target).closest('.file-input');
                    var btnBrowse = component.find(".btn-file");
                    btnBrowse.find("span").text(pluginData.browseLabel);
                    btnBrowse.unbind("click");
                });
        });
    }
}

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
            //$repeater.find("[data-repeater-create]").on("click", function(){
            //  if(){
            //    }
            //})
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
}*/

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

/*
function input_masks(){
    
    $("[data-slug]")
    .inputmask({
        min:8,
        max:25,
        onKeyValidation: function(key, result){
            debugJS(key, result)
            debugJS($(this).val());
            var slug = "";
            if (key){
              slug = $(this).val().replace(/\s+/g,'-').replace(/[^a-zA-Z0-9\-]/g,'').toLowerCase().replace(/\-{2,}/g,'-');
            }
            $(this).val(slug)
        }
        //mask: function (a) {
        //    debugJS(a)
        //    return ["[1-]AAA-999", "[1-]999-AAA"];
        //}
    });

    $("[data-alphaonly]")
        .inputmask({
            regex: "[A-ZÇŞİĞÖÜ a-zçşığöü]*",
            jitMasking: true,
            casing: "upper",
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("pattern","[A-ZÇŞİĞÖÜ a-zçşığöü]*");

    $("[data-alphanumericonly]")
        .inputmask({
            regex: "[A-Za-z0-9]*",
            jitMasking: true,
            casing: "upper",
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("pattern","[A-Za-z0-9]*");

    $("[data-numericonly]")
        .inputmask({
            regex: "[0-9]*",
            jitMasking: true,
            min:1,
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("inputmode", "numeric");

    $(".form-control-percentage").inputmask({
        //alias : 'percentage'
        alias: "numeric",
        digits: 2,
        digitsOptional: false,
        radixPoint: ".",
        placeholder: "00,00",
        groupSeparator: "",
        min: 0,
        max: 100,
        suffix: "",
        allowMinus: false,
        numericInput: true,
        autoGroup: true
    })
    .attr("inputmode", "decimal");

    $(".form-control-date").not(".inited").inputmask({
        alias: 'datetime',
        placeholder: "__.__.____",
        mask: '99.99.9999'
    });

    $(".form-control-date-monthyear").not(".inited").inputmask({
        alias: 'datetime',
        placeholder: "__.____",
        mask: '99.9999'
    });

    $(".form-control-tckn")
    .inputmask({
        placeholder: "___________",
        mask: '99999999999',
        casing: "upper",
        autoUnmask : true
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 11)
    .attr("data-msg-minlength", "TC Kimlik numaranız en az 11 karakter içermelidir");

    $(".form-control-tckn-serial")
    .inputmask({
        placeholder: "___________",
        mask: 'A99A99999',
        casing: "upper",
        autoUnmask : true
    })
    .attr("data-rule-minlength", 9)
    .attr("data-msg-minlength", "Kimlik seri numaranız en az 9 karakter içermelidir");

    $(".form-control-tckn-old").inputmask({
        placeholder: "______",
        mask: '999999',
        casing: "upper"
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 6)
    .attr("data-msg-minlength", "TC Kimlik numaranız en az 6 karakter içermelidir");

    $(".form-control-tckn-old-serial").inputmask({
        placeholder: "___",
        mask: 'A99',
        casing: "upper"
    })

    $(".form-control-taxno").inputmask({
        placeholder: "__________",
        mask: '9999999999',
        casing: "upper"
    })
    .attr("inputmode", "numric");

    $(".form-control-vkn").inputmask({
        placeholder: "__________",
        mask: '9999999999',
        casing: "upper"
    })
    .attr("inputmode", "numeric");

    $(".form-control-license")
        .inputmask({
            mask: '99A[A][A]9999',
            jitMasking: true,
            casing: "upper",
        })

    $(".form-control-iban").inputmask({
        placeholder: '__ ____ ____ ____ ____ ____ __',
        //mask : '99 9999 9999 9999 9999 9999 99'
        mask: '** **** **** **** **** **** **',
        jitMasking: true,
        casing: "upper",
    })
    .attr("inputmode", "numeric");

    $(".form-control-email").inputmask({
        mask: "*{1,20}[.*{1,20}][.*{1,20}][.*{1,20}]@*{1,20}[.*{2,6}][.*{1,2}]",
        //mask : "{1,20}@{1,20}.{3}[.{2}]",
        greedy: false,
        //autoUnmask : true,
        //onUnMask: function(maskedValue, unmaskedValue) {
        //    return unmaskedValue;
        //},
        onBeforePaste: function (pastedValue, opts) {
            pastedValue = pastedValue.toLowerCase();
            //return pastedValue;
        },
        definitions: {
            '*': {
                validator: "[0-9A-Za-z!#$%&'*+/=?^_`{|}~\-]",
                casing: "lower"
            }
        }
    })
    .bind("paste", function (e) {
        var pastedData = e.originalEvent.clipboardData.getData('text');
        $(this).val("");
    });

    $(".form-control-phone")
    .inputmask({
        placeholder: "0___ ___ __ __",
        mask: '0999 999 99 99',
        autoUnmask : true
    })
    .attr("inputmode", "tel")
    .attr("data-rule-minlength", 10)
    .attr("data-msg-minlength", "Telefon numaranız en az 11 karakter içermelidir");

    $(".form-control-gsm")
    .inputmask({
        placeholder: "05__ ___ __ __",
        mask: '0599 999 99 99',
        autoUnmask : true
    })
    .attr("inputmode", "tel")
    .attr("data-rule-minlength", 9)
    .attr("data-msg-minlength", "Telefon numaranız en az 11 karakter içermelidir");

    $(".form-control-postal-code").inputmask({
        placeholder: "_____",
        mask: '99999',
        autoUnmask : true
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 5)
    .attr("data-msg-minlength", "Posta kodunuz en az 5 karakter içermelidir");
    
    $(".form-control-currency, .form-control-currency-minus").each(function () {
        if($(this).hasClass("form-control-currency-minus")){
           $(this).attr("inputmode", "decimal")
        }
        $(this).attr("placeholder", "0.00");
        if (!IsBlank($(this).val())) {
            value = $(this).val().replace(",", "")
            value = value.split(".")[0];//parseInt($(this).val());
            value = numeral(value).format('0,0,0.00');
            $(this).val(value);
        }
    });

    $(".form-control-currency, .form-control-currency-minus")
        .on("keydown keyup", function () {
            if ($(this).val() == "-") {
                if ($(this).hasClass("form-control-currency-minus")) {
                    var value = "-";
                } else {
                    var value = "";
                }
            } else {
                var value = numeral($(this).val()).value();
            }

            var hasMinus = false;
            if (!IsBlank(value)) {
                debugJS(value)
                if (value < 0 || value == "-") {
                    hasMinus = true;
                }
            }
            if (!hasMinus) {
                if (isNaN(value)) {
                    value = 0;
                } else {
                    value = numeral($(this).val()).value();
                }
            }

            if (value > 0 || hasMinus && value != "-") {
                value = numeral(value).format();
            }
            $(this).val(value);
        })
        .on("focus", function () {
            var value = numeral($(this).val()).value();
            var hasMinus = false;
            if (!IsBlank(value)) {
                debugJS(value)
                if (value < 0) {
                    hasMinus = true;
                }
            }
            if (isNaN(value)) {
                value = 0;
            } else {
                value = numeral($(this).val()).value();
            }
            if (value > 0 || hasMinus) {
                value = numeral(value).format();
            } else {
                value = "";
            }
            $(this).val(value);

        })
        .on("blur", function () {
            var value = numeral($(this).val()).value();
            if(IsBlank(value) && value != 0){
               return;
            }
            var hasMinus = false;
            if (!IsBlank(value)) {
                if (value < 0) {
                    hasMinus = true;
                }
            }
            if (isNaN(value) || value == null) {
                value = 0;
            } else {
                value = numeral($(this).val()).value();
            }
            if (value > 0 || hasMinus) {
                value = numeral(value).format();
            }
            $(this).val(value + ",00");
        });
}

function form_validate_update_remote_objs(el, rule, objs) {
    for (var key in objs) {
      var inputName = objs[key];
      var value = $("[name='" + inputName + "']").val();
      var validator = el.data("validator");
      validator["settings"]["rules"][rule]["remote"]["data"]["vars"][key] = value;
      el.data("validator", validator);
    }
}
function form_validate(){
    if($(".form-validate").length > 0){
            $(".form-validate").each(function(){
                var rules = {};
                var remote_objs = {};
                $(this).find("[data-remote]").not(".no-validate").each(function(){
                    var name = $(this).attr("name");
                    var method = $(this).data("remote");
                    var param = $(this).data("remote-param");
                    var params = $(this).attr("data-remote-params");
                    var objs = $(this).attr("data-remote-objs");
                    var exclude = $(this).data("remote-exclude");
                    var rule = {
                         remote : {
                            url: ajax_request_vars.url,
                            type: "post",
                            data: {
                                    ajax : "query",
                                    method : method,
                                    vars :  {}
                            },
                            dataFilter: function(response) {
                                var data = $.parseJSON(response);
                                var obj = $( "[name="+name+"]" );
                                var message = obj.attr("data-msg");
                                if(data.error){
                                    return "\"" + data.message + "\"";
                                }else{
                                    return "\"" + true + "\"";
                                }
                            }
                         }
                    }
                    rules[name] = rule;

                    if(!IsBlank(exclude)){
                        rules[name]["remote"]["data"]["vars"]["exclude"] = exclude;
                    }
                    
                    if(!IsBlank(params)){
                        if(isJson(params)){
                            params = $.parseJSON(params);
                            for(var p in params){
                                rules[name]["remote"]["data"]["vars"][p] = function() {
                                     return params[p];
                                };                                
                            }
                        }
                    }

                    if(!IsBlank(objs)){
                        if(isJson(objs)){
                            remote_objs = $.parseJSON(objs);
                            var i = 0;
                            var fields = [];
                            for(var p in remote_objs){
                                var value = remote_objs[p];
                                if(!IsBlank(p) && !IsBlank(value)){
                                    fields[i] = "input[name='"+p+"']";
                                    rules[name]["remote"]["data"]["vars"][p] = $( "[name="+value+"]" ).val();
                                    i++;
                               }
                            }
                            if(fields){
                                debugJS(fields)
                                fields = fields.join(",").replaceAll('"',"");
                                debugJS(fields);
                                $(fields).on('change keyup', function() {
                                    var el = $(this).closest(".form-validate");
                                    if(el.length>0){
                                        form_validate_update_remote_objs(el, name, remote_objs);                                
                                    }
                                });                                
                            }
                        }
                    }
                    rules[name]["remote"]["data"]["vars"][param] = function() {
                         return $( "[name="+name+"]" ).val();
                    }; 
                });

                var validator = $(this).validate({
                      rules: rules,
                      errorPlacement: function(error, element) {
                            debugJS(element)
                            error.appendTo( element.closest(".form-group") );
                            $.fn.matchHeight._update();
                      },
                      ignore: ".ignore",
                      errorElement: "em",
                      errorClass: "is-invalid",
                      validClass: "",//"is-valid",
                      focusInvalid: false,
                      focusCleanup: true,
                      //onkeyup: true,
                      //onclick: false,
                      invalidHandler: function(e, validator) {
                            //for (var i=0;i<validator.errorList.length;i++){   
                                //$(validator.errorList[i].element).closest('.card-collapse.collapse').not(".show")
                            debugJS(validator.errorList)
                            if(validator.errorList.length > 0){

                                for(var i=0;i<validator.errorList.length;i++){
                                    if($(validator.errorList[i].element).hasClass("is-invalid")){
                                       $(validator.errorList[i].element).closest(".form-group").find(".btn-form-control-edit").not(".active").trigger("click");
                                    }                                   
                                }

                                var collapseHidden = $(validator.errorList[0].element).closest('.card-collapse.collapse').not(".show");
                                if(collapseHidden.length > 0){
                                    debugJS("error collapse validate")
                                    debugJS(collapseHidden)
                                    collapseHidden.collapse('show')
                                    .on('shown.bs.collapse', function (e) {
                                        debugJS(e)
                                        debugJS("validate shown collapse and scroll to")
                                        debugJS("#"+$(e.target).attr("id"))
                                        //root.ui.scroll_to("#"+$(e.target).attr("id"), true);
                                    });
                                    //var active = $(validator.errorList[0].element).closest(".card-collapse").find(".collapse.show");
                                    //if(active.length > 0){
                                    //    active.collapse('show');
                                    //}
                                }else{
                                    
                                    debugJS($(validator.errorList[0].element))
                                    if($(validator.errorList[0].element).closest('.card-collapse.collapse').length > 0){
                                       debugJS("error scroll open collapse validate")
                                       debugJS("#"+$(validator.errorList[0].element).closest('.card-collapse.collapse').attr("id"));
                                       root.ui.scroll_to("#"+$(validator.errorList[0].element).closest('.card-collapse.collapse').attr("id"), true, false);
                                    }else{

                                        if($(validator.errorList[0].element).closest('.tab-pane').length > 0){
                                            $('[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                                                if(validator.errorList.length > 0){
                                                    root.ui.scroll_to($(validator.errorList[0].element), true, false);
                                                }
                                            });
                                            var tabId = $(validator.errorList[0].element).closest('.tab-pane').attr("id");
                                            var triggerEl = document.querySelector('[data-bs-target="#'+tabId+'"]')
                                            var errorTab = new bootstrap.Tab(triggerEl);
                                            errorTab.show();
                                        }else{
                                            debugJS("error scroll to element")
                                            if($(validator.errorList[0].element).is(":visible")){
                                              root.ui.scroll_to($(validator.errorList[0].element), true, false);  
                                            }
                                        }
                                       
                                    }
                                }
                                
                            }
                            //}
                      },
                      submitHandler: function(form) {
                            var method = $(form).data("ajax-method");
                            var url = $(form).data("ajax-url");
                            if(!IsBlank(method)){
                                var $data = $(form).not('[value=""]').serializeJSON();

                                if(!IsBlank(url)){
                                    $data["url"] = url;
                                }
                                var form_id = $(form).attr("id");
                                var paginate = $(".ajax-paginate[data-form='#"+form_id+"']")

                                var objs = {};
                                if($data.hasOwnProperty("objs")){
                                    objs = $data["objs"];
                                    delete $data["objs"]                
                                }else{
                                    if(ajax_objs.hasOwnProperty(method)){
                                        objs = ajax_objs[method];
                                    }
                                }

                                function request_func(){
                                    var query = new ajax_query();
                                    query.method = method;
                                    query.vars   = $data;
                                    query.form   = $(form);
                                    if(paginate.length > 0){
                                        query.objs = {
                                            obj : paginate
                                        }
                                    }else{
                                        query.objs = objs;
                                    }
                                    query.request();
                                }

                                if($(form).data("confirm")){
                                    var confirm_message = $(form).data("confirm-message");
                                    if(IsBlank(confirm_message)){
                                       confirm_message =  "Are you sure?";
                                    }
                                    var modal = _confirm(confirm_message, "", "md", "modal-confirm", "Yes", "No", request_func);
                                }else{
                                   request_func();
                                }
                                return false;
                            }else{
                                if($(form).hasClass("form-review") && !$(form).hasClass("form-reviewed")){
                                    var $data = $(form).serializeJSON();
                                    var data = [];
                                    for(var i in $data){
                                        var el = $("[name='"+i+"']");
                                        if(el.hasClass("no-review")){
                                           continue;
                                        }
                                        var title = el.data("title");
                                        var value = $data[i];
                                        if(Array.isArray(value)){
                                            el = $("[name='"+i+"[]']");
                                            title = el.first().data("title");
                                        }
                                        var type = "";
                                        if(el.hasClass("form-review-title")){
                                            title = "form-review-title"
                                        }
                                        if(el.hasClass("form-control-iban")){
                                           value = "TR"+value;
                                        }
                                        if(el.hasClass("form-control-currency") || el.hasClass("form-control-currency-minus")){
                                           value = value+" TL";
                                        }
                                        if(el.hasClass("form-control-gsm")){
                                           value = "05"+value;
                                        }
                                        if(el.hasClass("form-control-phone")){
                                           value = "0"+value;
                                        }
                                        if(IsBlank(title)){
                                           title = el.closest(".form-group").find(".form-label").text();
                                        }
                                        data.push({title:title,value:value});
                                    }
                                    twig({
                                        href : host+"assets/templates/form-review.twig",
                                        async : false,
                                        allowInlineIncludes : true,
                                        load: function(template) {
                                            var html = template.render({data:data});
                                            var callback = function(){
                                                $(form).addClass("form-reviewed").submit();
                                            }
                                            var title = "";
                                            if(!IsBlank($(form).data("form-review-title"))){
                                                title = $(form).data("form-review-title");
                                            }
                                            _confirm(title, html, "lg", "modal-form-review modal-fullscreen", "Submit", "Back", callback);
                                        }
                                    });
                                    return false;
                                }else{
                                    $(form).removeClass("form-changed");
                                    $("body").addClass("loading");
                                }
                            }
                            form.submit();
                      },
                      debug:false
                });

                if($(this).find(".select-2").length > 0){
                    $(document).on("change", ".select2-offscreen", function() {
                        validator.form();
                    });
                    $(this).find(".select-2").on("select2:close", function (e) {  
                        $(this).valid(); 
                    });
                }
                
                $.validator.addMethod('username-rule', function (value, element, params) {
                    if (/^[a-z0-9-]+$/.test(value)) {
                        return true;
                    } else {
                        return false;
                    };
                });

                $.validator.addMethod("email", function(value, element, params) {
                  return this.optional( element ) || /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
                });


                $.validator.addMethod("password-rules", function(value) {
                    return /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d*.!@$%^&():;<>,.?/~_+-=|]{7,9}$/.test(value)
                });

                $.validator.addMethod("youtube", function(value) {
                    if(!IsBlank(value)){
                        return /(http:|https:)?\/\/(www\.)?(youtube.com|youtu.be)\/(watch)?(\?v=)?(\S+)?/.test(value);
                    }else{
                        return true;
                    }
                });

                $.validator.addMethod("url-check", function(value, element) {
                    if(!IsBlank(value)){
                        var http = /^(http:|https:)?\/\//.test(value);
                        //var url =  /^[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)$/.test(value);
                        //var url = /^[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)$/.test(value);
                        //var url = /^(https:\/\/www\.|http:\/\/www\.|https:\/\/|http:\/\/)?[a-zA-Z0-9]{2,}\.[a-zA-Z0-9]{2,}\.[a-zA-Z0-9]{2,}(\.[a-zA-Z0-9]{2,})?/.test(value);
                        var url = /^(https?:\/\/)?(www\.)?[a-zA-Z0-9-]+(\.[a-zA-Z]{2,})+$/.test(value);
                        if(!http || !url){
                               return false;
                        }
                    }else{
                        return true;
                    }
                }, function(value, element){
                        var value =$(element).val();
                        var http = /^(http:|https:)?\/\//.test(value);
                        //var url =  /^[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)$/.test(value);
                        //var url = /^[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)$/.test(value);
                        //var url = /^(https:\/\/www\.|http:\/\/www\.|https:\/\/|http:\/\/)?[a-zA-Z0-9]{2,}\.[a-zA-Z0-9]{2,}\.[a-zA-Z0-9]{2,}(\.[a-zA-Z0-9]{2,})?/.test(value);
                        var url = /^(https?:\/\/)?(www\.)?[a-zA-Z0-9-]+(\.[a-zA-Z]{2,})+$/.test(value);
                        if(!url){
                            return "Please enter a valid url";
                        }else{
                            if(!http){
                                return "Url must start http:// or https://";
                            }
                        }

                });

                

                $.validator.addMethod("currency-filter-min", function(value, element, params) {
                    //value = value.split(".")[0].replace(",", "");
                    //value = value.split(",")[0].replace(".", "");
                     var value = numeral(value).value();
                    return this.optional(element) || value >= params;
                }, $.validator.format("Please enter a value greater then {0}"));

                $.validator.addMethod("currency-filter-max", function(value, element, params) {
                    //value = value.split(".")[0].replace(",", "");
                    //value = value.split(",")[0].replace(".", "");
                     var value = numeral(value).value();
                    return this.optional(element) || value <= params;
                }, $.validator.format("Please enter a value lower then {0}"));

                $.validator.addMethod("currency-filter-range", function(value, element, params) {
                    var value = numeral(value).value();
                    debugJS(value)
                    return this.optional(element) || (value >= params[0] && value <= params[1]);
                }, $.validator.format("Please enter a value between {0} and {1}"));

                $(this).data("validator", validator)


            });
    }
}
function form_error_collapsed(errorList){
    if(errorList.length > 0){
        for(var i=0;i<errorList.length;i++){
            if($(errorList[i]).hasClass("is-invalid")){
                $(errorList[i]).closest(".form-group").find(".btn-form-control-edit").not(".active").trigger("click");
            }                                   
        }
        var collapseHidden = $(errorList[0]).closest('.card-collapse.collapse').not(".show");
        if(collapseHidden.length > 0){
            collapseHidden
            .collapse('show')
            .on('shown.bs.collapse', function (e) {
                //root.ui.scroll_to("#"+$(e.target).attr("id"), true);
            });
            //var active = $(validator.errorList[0].element).closest(".card-collapse").find(".collapse.show");
            //if(active.length > 0){
            //    active.collapse('show');
            //}
        }else{
            if($(errorList[0]).closest('.card-collapse.collapse').length > 0){
               if($('.modal:visible').length>0){
                  $('.modal:visible').animate({ scrollTop: $("#"+$(errorList[0]).closest('.card-collapse.collapse').attr("id")).offset().top },600);
               }else{
                  root.ui.scroll_to("#"+$(errorList[0]).closest('.card-collapse.collapse').attr("id"), true, false);
               }
            }else{
               root.ui.scroll_to($(errorList[0]), true, false);
            }
        }                       
    }
}

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
*/
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
/*
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

function form_control_autocomplete(){
    // type : 
    //   response-type :
    //   count :
    //   page :
    //   selected :

    var token_init = "form-control-autocomplete-init";
    $(".form-control-autocomplete").not("."+token_init).each(function(){
        $(this).addClass(token_init);
        if(IsBlank($(this).attr("id"))){
            var id = generateCode(8, "alpha");
            $(this).attr("id", id);
        }
        var queryType = $(this).data("query-type");
        function get_autocomplete_keyword($obj){
            return $obj.val();
        }
        AutoComplete({
            EmptyMessage: "No item found",
            HttpMethod: "post",
            HttpHeaders: {
                "HTTP_X_CSRF_TOKEN": ajax_request_vars.ajax_nonce
            },
            MinChars: 2,
            QueryArg: {
                ajax:"query",
                method:"autocomplete_terms",

                type:queryType,
                "response-type":"autocomplete",
                _wpnonce:ajax_request_vars.ajax_nonce

            },//"ajax=query&method=autocomplete_terms&type="+queryType+"&response-type=autocomplete&_wpnonce="+ajax_request_vars.ajax_nonce+"&keyword",
            Url: ajax_request_vars.url,//+"?ajax=query&method=autocomplete_terms&type="+queryType+"&response-type=autocomplete&_wpnonce="+ajax_request_vars.ajax_nonce,
            _Select: function(item) {
                debugJS(item)
                //debugJS(this.Request.response);
                $(this.Input).val($(item).text());
            }
        }, "#"+id);
    });
}*/