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
                      ignore: ".ignore",/*":not([name])",*/
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
                                    /*var active = $(validator.errorList[0].element).closest(".card-collapse").find(".collapse.show");
                                    if(active.length > 0){
                                        active.collapse('show');
                                    }*/
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
            /*var active = $(validator.errorList[0].element).closest(".card-collapse").find(".collapse.show");
            if(active.length > 0){
                active.collapse('show');
            }*/
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