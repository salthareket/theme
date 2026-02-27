function loadCSS(href) {
    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = () => resolve();
        link.onerror = () => reject(`CSS yüklenemedi: ${href}`);
        document.head.appendChild(link);
    });
}
function loadJS(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(`JS yüklenemedi: ${src}`);
        document.head.appendChild(script);
    });
}
/*
function isLoadedJS_v1($name, $load = false, $callback = "") {
    var $loaded = false;

    if (typeof required_js !== "undefined" && required_js.indexOf($name) > -1) {
        $loaded = true;
    }

    if (!$loaded && typeof conditional_js !== "undefined" && conditional_js.indexOf($name) > -1) {
        $loaded = true;
    }

    // Eğer zaten yüklüyse, sadece true dön
    if ($loaded) {
        return true;
    }

    // Eğer yükleme istenmiyorsa, false dön
    if (!$load) {
        return false;
    }

    // Buradan sonrası: yükle, init et, callback’i çağır
    const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
    fetch(configUrl)
        .then(response => response.json())
        .then(data => {
            const libConfig = data[$name];
            if (!libConfig) {
                alert($name + " için tanım bulunamadı!");
                return;
            }

            const promises = [];

            debugJS(libConfig)

            if (libConfig.css) {
                debugJS(libConfig.css)
                promises.push(loadCSS(libConfig.css));
            }

            if (libConfig.js) {
                debugJS(libConfig.js)
                promises.push(loadJS(libConfig.js));
            }

            if (libConfig.js_init) {
                debugJS(libConfig.js_init)
                promises.push(loadJS(libConfig.js_init));
            }

            Promise.all(promises).then(() => {
                if (typeof conditional_js !== "undefined") {
                    conditional_js.push($name);
                }

                if (libConfig.init && typeof window[libConfig.init] === 'function') {
                    window[libConfig.init]();
                }

                if (typeof $callback === "function") {
                    $callback();
                }
            }).catch(err => {
                console.error(err);
                alert($name + " yüklenirken hata oluştu!");
            });
        })
        .catch(err => {
            console.error(err);
            alert("Yapılandırma dosyası çekilemedi!");
        });

    return false;
}
function isLoadedJS_v2($name, $load = false, $callback = null) {
    // 1. Array ise tek tek ve güvenli yükle
    if (Array.isArray($name)) {
        if ($name.length === 0) {
            if (typeof $callback === "function") $callback();
            return true;
        }
        let list = [...$name]; // Orijinal listeyi bozma
        let current = list.shift();
        isLoadedJS(current, $load, () => {
            isLoadedJS(list, $load, $callback);
        });
        return false;
    }

    // 2. Yüklü mü kontrolü (En basit haliyle)
    let check_required = (typeof required_js !== "undefined" && required_js.indexOf($name) > -1);
    let check_conditional = (typeof conditional_js !== "undefined" && conditional_js.indexOf($name) > -1);

    if (check_required || check_conditional) {
        if (typeof $callback === "function") $callback();
        return true;
    }

    if (!$load) return false;

    // 3. Yükleme Başlat (Callback yapısı, await yok ki kilitlemesin)
    const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
    
    $.ajax({
        url: configUrl,
        dataType: 'json',
        cache: true, // Her seferinde çekmesin
        success: function(data) {
            console.log(data)
            console.log($name)
            const libConfig = data[$name];
            if (!libConfig) {
                console.error($name + " configde yok.");
                return;
            }

            // Dosyaları sırayla yükle (Önce CSS, sonra JS)
            let loadTasks = [];
            if (libConfig.css) loadTasks.push(loadCSS(libConfig.css));
            if (libConfig.js) loadTasks.push(loadJS(libConfig.js));

            Promise.all(loadTasks).then(() => {
                if (libConfig.js_init) return loadJS(libConfig.js_init);
            }).then(() => {
                // Kaydet ve Init çalıştır
                if (typeof conditional_js !== "undefined") conditional_js.push($name);
                if (libConfig.init && typeof window[libConfig.init] === 'function') {
                    window[libConfig.init]();
                }
                if (typeof $callback === "function") $callback();
            });
        }
    });

    return false;
}
function isLoadedJS_v3($name, $load = false, $callback = null) {
    // 1. Array ise (Dışarıdan gelen liste) tek tek ve güvenli yükle
    if (Array.isArray($name)) {
        if ($name.length === 0) {
            if (typeof $callback === "function") $callback();
            return true;
        }
        let list = [...$name];
        let current = list.shift();
        isLoadedJS(current, $load, () => {
            isLoadedJS(list, $load, $callback);
        });
        return false;
    }

    // 2. Yüklü mü kontrolü
    let check_required = (typeof required_js !== "undefined" && required_js.indexOf($name) > -1);
    let check_conditional = (typeof conditional_js !== "undefined" && conditional_js.indexOf($name) > -1);

    if (check_required || check_conditional) {
        if (typeof $callback === "function") $callback();
        return true;
    }

    if (!$load) return false;

    // 3. Yükleme Başlat
    const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
    
    $.ajax({
        url: configUrl,
        dataType: 'json',
        cache: true,
        success: function(data) {
            const libConfig = data[$name];
            if (!libConfig) {
                console.error($name + " configde yok.");
                return;
            }

            // --- CSS'leri hemen yükle ---
            if (libConfig.css) {
                const cssFiles = Array.isArray(libConfig.css) ? libConfig.css : [libConfig.css];
                cssFiles.forEach(file => {
                    if(typeof loadCSS === "function") loadCSS(file);
                });
            }

            // --- JS'leri SIRAYLA yükle (Vagon Mantığı) ---
            const jsFiles = libConfig.js ? (Array.isArray(libConfig.js) ? [...libConfig.js] : [libConfig.js]) : [];

            const loadJSChain = (list, finalAction) => {
                if (list.length === 0) {
                    finalAction();
                    return;
                }

                let file = list.shift();
                let finalUrl = '';

                // URL Çözümleme
                if (file.startsWith('http')) {
                    finalUrl = file;
                } else if (file.startsWith('wp-includes/')) {
                    finalUrl = ajax_request_vars.site_url + '/' + file;
                } else if (file.startsWith('/')) {
                    finalUrl = ajax_request_vars.site_url + file;
                } else {
                    finalUrl = ajax_request_vars.plugin_url + '/' + file;
                }

                console.log("Yükleme Sırasında: " + file);

                // loadJS Promise döndürmeli ve script.async = false içermeli!
                loadJS(finalUrl).then(() => {
                    console.log("Başarıyla Çalıştı: " + file);
                    loadJSChain(list, finalAction); // Biri bitmeden (onload olmadan) diğerine geçmez
                }).catch(err => {
                    console.error("Zincir kırıldı! Dosya yüklenemedi: " + finalUrl, err);
                });
            };

            // Zinciri başlat ve bittiğinde Init/Callback çalıştır
            loadJSChain(jsFiles, () => {
                
                const finalize = () => {
                    if (typeof conditional_js !== "undefined") conditional_js.push($name);
                    
                    // Init Fonksiyonu (Noktalı yapıları destekler örn: AppCF7.initForms)
                    if (libConfig.init) {
                        const parts = libConfig.init.split('.');
                        let func = window;
                        parts.forEach(p => { if(func) func = func[p]; });

                        if (typeof func === 'function') {
                            console.log("Init Çalışıyor: " + libConfig.init);
                            // Modal açıksa sadece modalı tarasın
                            const scope = $('.modal:visible').length ? $('.modal:visible') : $("body");
                            func(scope);
                        }
                    }
                    
                    if (typeof $callback === "function") $callback();
                };

                // Eğer ek bir js_init dosyası varsa önce onu yükle
                if (libConfig.js_init) {
                    loadJS(libConfig.js_init).then(finalize);
                } else {
                    finalize();
                }
            });
        }
    });

    return false;
}*/
function isLoadedJS($name, $load = false, $callback = null) {
    // Statik değişkenleri ilk kez çalışırken tanımla (Namespace koruması)
    if (typeof isLoadedJS.cache === 'undefined') {
        isLoadedJS.cache = null;
        isLoadedJS.loading = false;
    }

    // 1. Array Desteği
    if (Array.isArray($name)) {
        if ($name.length === 0) {
            if (typeof $callback === "function") $callback();
            return true;
        }
        let list = [...$name];
        let current = list.shift();
        isLoadedJS(current, $load, () => {
            isLoadedJS(list, $load, $callback);
        });
        return false;
    }

    // 2. Yüklü mü kontrolü
    let check_required = (typeof required_js !== "undefined" && required_js.indexOf($name) > -1);
    let check_conditional = (typeof conditional_js !== "undefined" && conditional_js.indexOf($name) > -1);

    if (check_required || check_conditional) {
        if (typeof $callback === "function") $callback();
        return true;
    }

    if (!$load) return false;

    // 3. Kütüphane İşleme Motoru
    const processLibrary = (data) => {
        const libConfig = data[$name];
        if (!libConfig) return;

        if (libConfig.css) {
            const cssFiles = Array.isArray(libConfig.css) ? libConfig.css : [libConfig.css];
            cssFiles.forEach(file => { if(typeof loadCSS === "function") loadCSS(file); });
        }

        const jsFiles = libConfig.js ? (Array.isArray(libConfig.js) ? [...libConfig.js] : [libConfig.js]) : [];

        const loadJSChain = (list, finalAction) => {
            if (list.length === 0) {
                finalAction();
                return;
            }

            let item = list.shift();
            let isObj = (typeof item === 'object' && item !== null);
            let finalUrl = isObj ? item.url : item;

            if (!finalUrl.startsWith('http')) {
                if (finalUrl.startsWith('wp-includes/')) {
                    finalUrl = ajax_request_vars.site_url + '/' + finalUrl;
                } else if (finalUrl.startsWith('/')) {
                    finalUrl = ajax_request_vars.site_url + finalUrl;
                } else {
                    finalUrl = ajax_request_vars.plugin_url + '/' + finalUrl;
                }
            }

            loadJS(finalUrl).then(() => {
                if (isObj && item.callback_code) {
                    try {
                        new Function(item.callback_code)();
                    } catch (e) { console.error("Callback Hatası:", e); }
                }
                loadJSChain(list, finalAction); 
            }).catch(() => loadJSChain(list, finalAction));
        };

        loadJSChain(jsFiles, () => {
            const finalize = () => {
                if (typeof conditional_js !== "undefined" && conditional_js.indexOf($name) === -1) {
                    conditional_js.push($name);
                }
                
                if (libConfig.init) {
                    const parts = libConfig.init.split('.');
                    let func = window;
                    parts.forEach(p => { if(func && func[p]) func = func[p]; });

                    if (typeof func === 'function') {
                        const scope = $('.modal:visible').length ? $('.modal:visible') : $("body");
                        func(scope);
                    }
                }
                if (typeof $callback === "function") $callback();
            };

            if (libConfig.js_init) {
                loadJS(libConfig.js_init).then(finalize);
            } else {
                finalize();
            }
        });
    };

    // 4. Hafıza (Cache) Yönetimi
    if (isLoadedJS.cache) {
        processLibrary(isLoadedJS.cache);
    } else {
        if (isLoadedJS.loading) {
            // Eğer yükleme devam ediyorsa, kısa bir süre sonra tekrar dene
            setTimeout(() => isLoadedJS($name, $load, $callback), 50);
            return false;
        }

        isLoadedJS.loading = true;
        const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
        
        $.ajax({
            url: configUrl,
            dataType: 'json',
            cache: true,
            success: function(data) {
                isLoadedJS.cache = data;
                isLoadedJS.loading = false;
                processLibrary(data);
            },
            error: function() {
                isLoadedJS.loading = false;
                console.error("JS Config dosyası yüklenemedi.");
            }
        });
    }

    return false;
}
function function_secure($plugin, $name, $params) {
    debugJS($plugin, $name, $params)
    if (isLoadedJS($plugin)) {
        if (typeof window[$name] === 'function') {
            if (Array.isArray($params)) {
                window[$name].apply(null, $params);
            } else {
                window[$name]($params);
            }
        } else {
            console.error($name + ' is not a function...');
        }
    } else {
        console.error($plugin + ' is not loaded...');
    }
}

/*
        wpcf7invalid — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because there are fields with invalid input.
        wpcf7spam — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because a possible spam activity has been detected.
        wpcf7mailsent — Fires when an Ajax form submission has completed successfully, and mail has been sent.
        wpcf7mailfailed — Fires when an Ajax form submission has completed successfully, but it has failed in sending mail.
        wpcf7submit — Fires when an Ajax form submission has completed successfully, regardless of other incidents.*/
        /*detail.contactFormId  The ID of the contact form.
        detail.pluginVersion    The version of Contact Form 7 plugin.
        detail.contactFormLocale    The locale code of the contact form.
        detail.unitTag  The unit-tag of the contact form.
        detail.containerPostId  The ID of the post that the contact form is placed in.
*/
class CF7Manager {
    constructor() {
        this.initEventListeners();
    }

    // 1. Statik Global Dinleyiciler (Sayfa ömrü boyunca 1 kez kurulur)
    initEventListeners() {
        const _this = this;

        // Form gönderilmeye başlandığında (Hata veya Başarı fark etmez)
        document.addEventListener('wpcf7submit', (e) => {
            _this.toggleLoader(false);
            _this.handleFormActions(e, 'submit');
        }, false);

        // Mail başarıyla gittiğinde
        document.addEventListener('wpcf7mailsent', (e) => {
            _this.handleFormActions(e, 'sent');
        }, false);

        // Hatalı form gönderimi
        document.addEventListener('wpcf7invalid', (e) => {
            _this.handleInvalidFields(e);
            _this.toggleLoader(false);
        }, false);

        // Diğer durumlar için loader kapat
        ['wpcf7mailfailed', 'wpcf7spam'].forEach(evt => {
            document.addEventListener(evt, () => _this.toggleLoader(false), false);
        });
    }

    // 2. Formu Başlatma (Senin o bahsettiğin 'Olmuyor' dediğin yeri çözen kısım)
    initForms($scope = $("body")) {
        // --- KRİTİK UYANDIRMA ALARMI ---
        // Eğer wpcf7 objesi henüz fonksiyonlarını yüklemediyse (DOMContentLoaded geçildiyse)
        if (typeof wpcf7 !== 'undefined' && typeof wpcf7.init !== 'function') {
            console.log("CF7 Kapsülü Class içinden patlatılıyor...");
            document.dispatchEvent(new Event('DOMContentLoaded'));
        }

        const _this = this;
        
        $scope.find(".wpcf7-form").each(function() {
            const $form = $(this);
            const formEl = $form[0];

            // CF7'nin ana init metodunu çağır (Artık wpcf7.init var olduğundan eminiz)
            if (typeof wpcf7 !== 'undefined' && typeof wpcf7.init === 'function') {
                wpcf7.init(formEl);
            }

            // Conditional Fields Desteği
            if (typeof wpcf7cf === "object") {
                wpcf7cf.initForm($form);
            }

            // Submit butonu loader tetikleyici
            $form.find(".btn-submit").off("click").on("click", function() {
                $(this).closest(".wpcf7-form").find(".accordion-item.error").removeClass("error");
                _this.toggleLoader(true);
            });
        });
    }

    // 3. Loader Yönetimi (Modal veya Body otomatik algılar)
    toggleLoader(show) {
        const $target = $('.modal:visible').length > 0 
            ? $('.modal:visible').find(".modal-content") 
            : $("body");

        if (show) $target.addClass("loading-process");
        else $target.removeClass("loading-process");
    }

    // 4. Form Aksiyonları (Modal/Sayfa ayrımını tek fonksiyonda birleştirdik)
    handleFormActions(e, type) {
        const $form = $(e.target);
        const $formSubmit = $form.find('.btn-submit');
        const isModal = $('.modal:visible').length > 0;
        const $scrollTarget = isModal ? $('.modal:visible') : $("html,body");
        
        // Hata durumları
        const hasError = $form.hasClass('invalid') || $form.hasClass('failed') || 
                         $form.hasClass('unaccepted') || $form.hasClass('spam');

        if (type === "submit" && hasError) {
            this.scrollToElement($scrollTarget, $form);
            $formSubmit.prop('disabled', false).blur();
        } 
        
        else if (type === "sent") {
            this.scrollToElement($scrollTarget, $form);
            $formSubmit.prop('disabled', false).blur();
            if (!hasError) {
                $form.removeClass("sent").addClass("init");
                // Gerekirse mesajı burada decode edip alert basabilirsin
            }
        }
    }

    // 5. Hatalı Alanları İşaretleme (Accordion desteği dahil)
    handleInvalidFields(e) {
        const $form = $(e.target);
        $form.find(".accordion-item.error").removeClass("error");

        if (e.detail && e.detail.apiResponse && e.detail.apiResponse.invalid_fields) {
            e.detail.apiResponse.invalid_fields.forEach(fieldObj => {
                const $field = $form.find("[name='" + fieldObj.field + "']");
                
                // Eğer alan bir accordion içindeyse onu işaretle
                if ($field.closest(".accordion-item").length > 0) {
                    $field.closest(".accordion-item").addClass("error");
                }

                // Alana tıklandığında kırmızı uyarıyı temizle
                $field.one("click", function() {
                    $(this).removeClass("wpcf7-not-valid");
                });
            });
        }
    }

    // Yardımcı: Scroll işlemi
    scrollToElement($container, $element) {
        const headerHeight = $("header#header").height() || 0;
        const offset = $element.offset().top - headerHeight - 20;
        
        $container.animate({ scrollTop: offset }, 600);
    }
}
window.AppCF7 = new CF7Manager();
function initContactForm(){}
/*
function initContactForm(){
    var obj = wpcf7;
        obj.initForm = function ( el ) { 
            obj.init( el[0] ); 
        }
        $(".wpcf7-form").each(function(){
            var wpcf7_form = $(this);
            obj.initForm(wpcf7_form);

            //init conditional fields
            if(typeof wpcf7cf === "object"){
                wpcf7cf.initForm(wpcf7_form);
            }

            wpcf7_form.find(".btn-submit").on("click", function(){
                $(this).find(".accordion-item.error").removeClass("error");
                if($('.modal:visible').length > 0){
                    $('.modal:visible').find(".modal-content").addClass("loading-process");
                }else{
                    $("body").addClass("loading-process");
                }
            });            

        });
}
function modalFormActions(e, type){
    var form = $('.modal:visible').find(e.target);
    var formSubmit = form.find('.btn-submit');
    if(type == "submit"){
        if(form.hasClass('invalid')||form.hasClass('failed')||form.hasClass('unaccepted')||form.hasClass('spam')){
           $('.modal:visible').animate({ scrollTop:form.offset().top - $("header#header").height()  },600, function(){
               formSubmit.attr('disabled',false).blur();
           });
        }else{
            form.removeClass("sent").addClass("init");
            var message = decodeHtml(e.detail.apiResponse.message);
            //debugJS(e);
            //$('.modal:visible').modal("hide");
            //_alert("", message, "xxl", "modal-fullscreen bg-tertiary text-white", "", "", true, true);
        }
    };
    if(type == "sent"){
       $('.modal:visible').animate({ scrollTop:form.offset().top - $("header#header").height()  },600);
       formSubmit.attr('disabled',false).blur();       
    }
    
    //$(".modal").animate({ scrollTop: $(".wpcf7-response-output").offset().top }, 600);
};
function contactFormActions(e, type){
    var form = $(e.target);
    var formSubmit = form.find('.btn-submit');
    if(type == "submit"){
        if(form.hasClass('invalid')||form.hasClass('failed')||form.hasClass('unaccepted')||form.hasClass('spam')){
           $("html,body").animate({ scrollTop:form.offset().top - $("header#header").height()  }, 600);
           formSubmit.attr('disabled', false).blur();
        }else{
            form.removeClass("sent").addClass("init");
            var message = decodeHtml(e.detail.apiResponse.message);
            //debugJS(e);
            //_alert("", message, "xxl", "modal-fullscreen bg-tertiary text-white", "", "", true, true);
        }
    };
    if(type == "sent"){
       $("html,body").animate({ scrollTop:form.offset().top - $("header#header").height()  }, 600);
       formSubmit.attr('disabled', false).blur();
    }
};
function contactform_sent(e){
    var formId = e.detail.contactFormId;
    if($('.modal:visible').length>0){
        $('.modal:visible').find(".modal-content").removeClass("loading-process");
        modalFormActions(e, 'sent');
    }else{
        $("body").removeClass("loading-process");
        contactFormActions(e, 'sent');
    }
}
function contactform_invalid(e){
     $(e.target).find(".accordion-item.error").removeClass("error");
     $(e.detail.apiResponse.invalid_fields).each(function(i, obj) {
        obj = $("[name='"+obj.field+"']");
        if(obj.closest(".accordion-item").length > 0){
            obj.closest(".accordion-item").addClass("error");
        }
        obj.on("click", function(e){
            $(this).removeClass("wpcf7-not-valid");
         });
     });
}*/
$( document ).ready(function() {
    window.AppCF7.initForms();
        /*wpcf7invalid — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because there are fields with invalid input.
        wpcf7spam — Fires when an Ajax form submission has completed successfully, but mail hasn’t been sent because a possible spam activity has been detected.
        wpcf7mailsent — Fires when an Ajax form submission has completed successfully, and mail has been sent.
        wpcf7mailfailed — Fires when an Ajax form submission has completed successfully, but it has failed in sending mail.
        wpcf7submit — Fires when an Ajax form submission has completed successfully, regardless of other incidents.*/
        /*detail.contactFormId  The ID of the contact form.
        detail.pluginVersion    The version of Contact Form 7 plugin.
        detail.contactFormLocale    The locale code of the contact form.
        detail.unitTag  The unit-tag of the contact form.
        detail.containerPostId  The ID of the post that the contact form is placed in.
        $( '.wpcf7' ).each(function(){
                var form = $(this);
                form.find(".btn-submit").on("click", function(){
                    $(this).find(".accordion-item.error").removeClass("error");
                    if($('.modal:visible').length > 0){
                       $('.modal:visible').find(".modal-content").addClass("loading-process");
                    }else{
                        $("body").addClass("loading-process");
                    }
                });            
        });
        document.addEventListener( 'wpcf7submit', function( e ) {
            //event.detail.contactFormId;
            //$(e.target).find(".accordion-item.error").removeClass("error");
            if($('.modal:visible').length > 0){
                $('.modal:visible').find(".modal-content").removeClass("loading-process");
                modalFormActions(e, 'submit');
            }else{
                $("body").removeClass("loading-process");
                contactFormActions(e, 'submit');
            }
        }, false );
        document.addEventListener( 'wpcf7mailsent', function( e ) {
            //event.detail.contactFormId;
            contactform_sent(e);
        }, false );
        document.addEventListener( 'wpcf7mailfailed', function( e ) {
            $("body").removeClass("loading-process");
        }, false );
        document.addEventListener( 'wpcf7spam', function( e ) {
            $("body").removeClass("loading-process");
        }, false );
        document.addEventListener( 'wpcf7invalid', function( e ) {
            contactform_invalid(e)
            $("body").removeClass("loading-process");
        }, false );*/
});