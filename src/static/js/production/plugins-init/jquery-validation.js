/**
 * Form validation summary alert — form'un üstünde tüm hataları listeler.
 * Her form-validate için tek bir #form-errors-{id} alert bloğu yaratılır/güncellenir.
 */
function form_validate_show_summary($form, errorList) {
    var formId = $form.attr('id') || 'form-' + Math.random().toString(36).slice(2, 7);
    if (!$form.attr('id')) $form.attr('id', formId);

    var alertId = 'form-errors-' + formId;
    var $alert  = $form.find('#' + alertId);

    if (!errorList || errorList.length === 0) {
        $alert.fadeOut(200, function(){ $(this).remove(); });
        return;
    }

    var items = errorList.map(function(err){
        var label = $(err.element).closest('.form-group, .mb-3, .col-md-6, .col-12')
                        .find('label').first().text().trim();
        if (!label) label = $(err.element).attr('placeholder') || $(err.element).attr('name') || '';
        label = label.replace(/\s*\*\s*$/, ''); // trailing asterisk temizle
        return '<li>' + (label ? '<strong>' + $('<span>').text(label).html() + ':</strong> ' : '') + $('<span>').text(err.message).html() + '</li>';
    }).join('');

    var html = '<div id="' + alertId + '" class="alert alert-danger alert-dismissible fade show mb-3" role="alert">' +
               '<ul class="mb-0 ps-3">' + items + '</ul>' +
               '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
               '</div>';

    if ($alert.length) {
        $alert.replaceWith(html);
    } else {
        $form.prepend(html);
    }

    // Summary'e smooth scroll
    var $newAlert = $form.find('#' + alertId);
    if ($newAlert.length && typeof root !== 'undefined' && root.ui && root.ui.scroll_to) {
        root.ui.scroll_to($newAlert, true, false);
    } else if ($newAlert.length) {
        $('html, body').animate({ scrollTop: $newAlert.offset().top - 80 }, 300);
    }
}

function form_validate_update_remote_objs(el, rule, objs) {
    for (var key in objs) {
      var inputName = objs[key];
      var value = $("[name='" + inputName + "']").val();
      var validator = el.data("validator");
      validator["settings"]["rules"][rule]["sh-remote"]["data"]["vars"][key] = value;
      el.data("validator", validator);
    }
}

// jQuery Validation remote'u application/x-www-form-urlencoded kullanır,
// JSON gönderemez. Bu custom method tam $.ajax kontrolü sağlar.
function sh_register_remote_method() {
    if (typeof $.validator === 'undefined' || $.validator.methods['sh-remote']) return;
    $.validator.addMethod("sh-remote", function(value, element, params) {
        if (this.optional(element)) return true;

        var self = this;
        var vars = $.extend({}, params.data ? params.data.vars : {});

        // Dinamik vars (function olan alanları çalıştır)
        for (var k in vars) {
            if (typeof vars[k] === 'function') vars[k] = vars[k]();
        }

        // Mevcut field değerini de ekle (param key'i ile)
        if (params.paramKey) vars[params.paramKey] = value;

        $.ajax({
            url: params.url,
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': ajax_request_vars.ajax_nonce || '' },
            data: JSON.stringify({ vars: vars }),
            async: false,  // validate sync beklediği için
            success: function(res) {
                if (res && res.error) {
                    self.settings.messages[element.name] = self.settings.messages[element.name] || {};
                    self.settings.messages[element.name]['sh-remote'] = res.message || 'Invalid';
                    self.lastRemoteResult = false;
                } else {
                    self.lastRemoteResult = true;
                }
            },
            error: function() {
                self.lastRemoteResult = true; // network hatasında geçer say
            }
        });

        return this.lastRemoteResult !== false;
    }, function(params, element) {
        var validator = $(element).closest('.form-validate').data('validator');
        if (validator && validator.settings.messages[element.name] && validator.settings.messages[element.name]['sh-remote']) {
            return validator.settings.messages[element.name]['sh-remote'];
        }
        return $(element).data('msg') || 'This field is invalid.';
    });
}
function form_validate(){
    if($(".form-validate").length > 0){
            $(".form-validate").each(function(){
                sh_register_remote_method();
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
                         "sh-remote" : {
                            url: (ajax_request_vars.site_url || ajax_request_vars.url).replace(/\/?$/, '') + '/api/' + method + '/',
                            data: { vars: {} },
                            paramKey: param
                         }
                    }
                    rules[name] = rule;

                    if(!IsBlank(exclude)){
                        rules[name]["sh-remote"]["data"]["vars"]["exclude"] = exclude;
                    }
                    
                    if(!IsBlank(params)){
                        if(isJson(params)){
                            params = JSON.parse(params);
                            for(var p in params){
                                rules[name]["sh-remote"]["data"]["vars"][p] = function() {
                                     return params[p];
                                };                                
                            }
                        }
                    }

                    if(!IsBlank(objs)){
                        if(isJson(objs)){
                            remote_objs = JSON.parse(objs);
                            var i = 0;
                            var fields = [];
                            for(var p in remote_objs){
                                var value = remote_objs[p];
                                if(!IsBlank(p) && !IsBlank(value)){
                                    fields[i] = "input[name='"+p+"']";
                                    rules[name]["sh-remote"]["data"]["vars"][p] = $( "[name="+value+"]" ).val();
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
                    // paramKey sh-remote method'a geçildi, burada ayrıca eklemeye gerek yok
                });

                var $form = $(this);

                // Postcode field'ları için ülkeye göre pattern rule'u hazırla
                var postcodeRules = {};
                $form.find('.form-control-postal-code, #billing_postcode, #shipping_postcode').each(function(){
                    var $el      = $(this);
                    var name     = $el.attr('name');
                    if (!name) return;
                    var $country = $el.closest('form').find('#billing_country, #shipping_country, [name="billing_country"], [name="shipping_country"]').first();
                    var country  = ($country.val() || 'default').toUpperCase();
                    var postcodes = (window.site_config && site_config.postcodes) ? site_config.postcodes : {};
                    var regex    = postcodes[country] || '';
                    if (regex) {
                        postcodeRules[name] = {
                            pattern: new RegExp(regex),
                            messages: { pattern: 'Please enter a valid postcode for ' + country + '.' }
                        };
                    }
                });

                var validator = $form.validate({
                      rules: $.extend(true, {}, rules, postcodeRules),
                      errorPlacement: function(error, element) {
                            debugJS(element)
                            // form-group varsa oraya, yoksa WC'nin form-row'una, o da yoksa input'un arkasına
                            var $container = element.closest(".form-group");
                            if ( !$container.length ) $container = element.closest(".form-row, .woocommerce-form-row");
                            if ( $container.length ) {
                                error.appendTo( $container );
                            } else {
                                error.insertAfter( element );
                            }
                            if (typeof $.fn.matchHeight !== 'undefined') $.fn.matchHeight._update();
                      },
                      ignore: ".ignore",/*":not([name])",*/
                      errorElement: "em",
                      errorClass: "is-invalid",
                      validClass: "",//"is-valid",
                      focusInvalid: false,
                      focusCleanup: true,
                      //onkeyup: true,
                      //onclick: false,
                      // Bir field valid hale gelince summary'i güncelle
                      success: function(label, element) {
                            $(element).removeClass("is-invalid");
                            // Kalan hatalı field varsa summary güncelle, yoksa kaldır
                            var remaining = $form.find(".is-invalid");
                            if (remaining.length === 0) {
                                $form.find(".form-validate-summary").fadeOut(200, function(){ $(this).remove(); });
                            }
                      },
                      invalidHandler: function(e, validator) {
                            debugJS(validator.errorList)
                            if(validator.errorList.length > 0){

                                // ── Hatalı field'lara is-invalid ekle + form-group edit trigger ──
                                for(var i=0;i<validator.errorList.length;i++){
                                    $(validator.errorList[i].element).addClass("is-invalid");
                                    if($(validator.errorList[i].element).hasClass("is-invalid")){
                                       $(validator.errorList[i].element).closest(".form-group").find(".btn-form-control-edit").not(".active").trigger("click");
                                    }
                                }

                                // ── Form üstüne hata özet alert'i ──
                                var alertId  = 'fv-summary-' + ($form.attr('id') || Math.random().toString(36).slice(2, 6));
                                $form.attr('id', $form.attr('id') || alertId.replace('fv-summary-','form-'));

                                var items = validator.errorList.map(function(err){
                                    // Field label'ını bulmaya çalış
                                    var $el    = $(err.element);
                                    var label  = '';
                                    var $group = $el.closest('.form-group, .mb-3, .col-md-6, .col-12');
                                    if ($group.length) {
                                        label = $group.find('label').first().text().trim().replace(/\s*\*\s*$/, '');
                                    }
                                    if (!label) label = $el.attr('placeholder') || $el.attr('name') || '';
                                    var escapedLabel   = $('<span>').text(label).html();
                                    var escapedMessage = $('<span>').text(err.message).html();
                                    return '<li>' + (escapedLabel ? '<strong>' + escapedLabel + ':</strong> ' : '') + escapedMessage + '</li>';
                                }).join('');

                                var alertHtml = '<div id="' + alertId + '" class="form-validate-summary alert alert-danger alert-dismissible fade show mb-3" role="alert">' +
                                                '<ul class="mb-0 ps-3">' + items + '</ul>' +
                                                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                                                '</div>';

                                // sh-no-error-summary class'ı varsa üst alert gösterme
                                if ($form.hasClass('sh-no-error-summary')) return;

                                var $existing = $form.find('.form-validate-summary');
                                if ($existing.length) {
                                    $existing.replaceWith(alertHtml);
                                } else {
                                    $form.prepend(alertHtml);
                                }

                                // ── Collapse / Tab / Scroll mantığı ──
                                var collapseHidden = $(validator.errorList[0].element).closest('.card-collapse.collapse').not(".show");
                                if(collapseHidden.length > 0){
                                    debugJS("error collapse validate")
                                    collapseHidden.collapse('show');
                                }else{
                                    if($(validator.errorList[0].element).closest('.card-collapse.collapse').length > 0){
                                       root.ui.scroll_to("#"+$(validator.errorList[0].element).closest('.card-collapse.collapse').attr("id"), true, false);
                                    }else{
                                        if($(validator.errorList[0].element).closest('.tab-pane').length > 0){
                                            $('[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                                                if(validator.errorList.length > 0){
                                                    root.ui.scroll_to($(validator.errorList[0].element), true, false);
                                                }
                                            });
                                            var tabId = $(validator.errorList[0].element).closest('.tab-pane').attr("id");
                                            var triggerEl = document.querySelector('[data-bs-target="#'+tabId+'"]');
                                            if (triggerEl) new bootstrap.Tab(triggerEl).show();
                                        }else{
                                            // Summary alert'e scroll — en üste görünsün
                                            var $summary = $form.find('.form-validate-summary');
                                            if ($summary.length) {
                                                if (typeof root !== 'undefined' && root.ui && root.ui.scroll_to) {
                                                    root.ui.scroll_to($summary, true, false);
                                                } else {
                                                    $('html,body').animate({ scrollTop: $summary.offset().top - 80 }, 300);
                                                }
                                            }
                                        }
                                    }
                                }

                            }
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
                                // Güvenli hasProp yardımcı değişkenini kullanalım (veya direkt Object.prototype üzerinden)
                                var hasProp = Object.prototype.hasOwnProperty;
                                if ($data && hasProp.call($data, "objs")) {
                                    objs = $data["objs"];
                                    delete $data["objs"]; // Veriyi temizleyip devam ediyoruz
                                } else {
                                    // ajax_hooks objesinin varlığını da kontrol ederek ilerliyoruz
                                    if (ajax_hooks && hasProp.call(ajax_hooks, method)) {
                                        objs = ajax_hooks[method];
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
                                    // Başarılı response'da summary alert'i temizle
                                    query.done = function(res){
                                        if (res && !res.error) {
                                            $(form).find('.form-validate-summary').fadeOut(200, function(){ $(this).remove(); });
                                        }
                                    };
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
                                    requirePlugin("twig", function() {
                                        twig({
                                            href : host+"assets/templates/form-review.twig",
                                            async : true,
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

                if($form.find(".select-2").length > 0){
                    $(document).on("change", ".select2-offscreen", function() {
                        validator.form();
                    });
                    $form.find(".select-2").on("select2:close", function (e) {  
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

                $form.data("validator", validator)


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