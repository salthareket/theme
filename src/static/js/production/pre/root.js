// Global Hata Ayıklama Değişkenleri
var debug = false;

// Hata İşleyicileri
window.onerror = function(message, url, line) {
    // Üretim ortamında konsola loglama veya sunucuya hata raporlama için kullanılabilir.
    // debugJS(message + ', ' + url + ', ' + line); // debug modu aktifse kullanılabilir
};

// beforeunload ve sayfa geçişleri için optimizasyon
window.addEventListener('beforeunload', (event) => {
    // Scroll pozisyonunu elle yönetmek için
    history.scrollRestoration = 'manual';
    event.returnValue = ''; // Bu satır, bazı tarayıcılarda uyarı kutusunu tetikler
});

// Sayfa arka planda/görünmez olduğunda loading sınıfını kaldır
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') {
        document.body.classList.remove('loading-process');
    }
});

// Sayfa yeniden gösterildiğinde (Back/Forward Cache'den geliyorsa)
window.addEventListener('pageshow', (event) => {
    document.body.classList.remove("loading", "loading-process");
    if (event.persisted) {
        // Hız/güvenlik için zorla yeniden yükleme
        //window.location.reload();
    }
});

// DOM tamamen yüklendiğinde
document.addEventListener('DOMContentLoaded', () => {
    // Yükleme sınıflarını kaldır
    document.body.classList.remove("loading", "loading-process");
    
    // Tarayıcı boyutu ve CSS değişkenlerini ayarla
    size = root.browser.size();
    root.get_css_vars();

    // Kullanıcı Yerelleştirme Bilgisi
    const userLoc = document.querySelector(".user-localization");
    if (userLoc) {
        const { user_country, user_country_code, user_language } = site_config;
        userLoc.textContent = `${user_country.toUpperCase()} - ${user_language.toUpperCase()}`;
        const parentA = userLoc.closest('a');
        if (parentA) {
            parentA.dataset.user_country_code = user_country_code;
            parentA.dataset.user_language = user_language;
        }
    }
    
    // Bootstrap Tooltip Init
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Form İşleyicileri
    
    // 1) Placeholder temizleme (focusin/focusout)
    document.addEventListener("focusin", function(e) {
        const el = e.target;
        if (el.tagName !== "INPUT" && el.tagName !== "TEXTAREA") return;
        if (!el.dataset.ph) {
            el.dataset.ph = el.getAttribute("placeholder") || "";
        }
        el.setAttribute("placeholder", "");
    });

    document.addEventListener("focusout", function(e) {
        const el = e.target;
        if (el.dataset && el.dataset.ph !== undefined) {
            el.setAttribute("placeholder", el.dataset.ph);
        }
    });


    // 2) Select URL değişince redirect
    document.addEventListener("change", function(e) {
        const el = e.target;
        if (!el.classList.contains("select-url")) return;

        document.body.classList.add("loading-process");
        window.location.href = el.value;
    });


    // 3) Select Hash scrollto
    document.addEventListener("change", function(e) {
        const el = e.target;
        if (!el.classList.contains("select-hash")) return;

        const parts = el.value.split("#");
        const id = parts[1] ? "#" + parts[1] : null;
        if (id) root.ui.scroll_to(id, true);
    });

    // Utility: matches polyfill (safety)
    const matches = (el, selector) => {
        return (el.matches || el.msMatchesSelector || el.webkitMatchesSelector).call(el, selector);
    }
    
    // 4) Form changed + auto-submit
    const formObserver = (e) => {
        const el = e.target;

        if (!matches(el, ".form input, .form select, .form textarea")) return;

        if (el.classList.contains("multiselect-search") ||
            el.classList.contains("form-control-autocomplete")) {
            return;
        }

        const form = el.closest(".form");
        if (!form) return;

        form.classList.add("form-changed");

        if (form.dataset.autoSubmit && !form.classList.contains("ajax-processing")) {

            // Minimum karakter kontrolü
            if (el.hasAttribute("min") && (el.tagName === "INPUT" || el.tagName === "TEXTAREA")) {
                if (el.value.length < parseInt(el.getAttribute("min"))) {
                    return;
                }
            }

            // sayfa sıfırlama
            const pageInput = form.querySelector("input[name='page']");
            if (pageInput) pageInput.value = 1;

            form.submit();
        }
    }
    setTimeout(() => {
        document.addEventListener("input", formObserver, true);
        document.addEventListener("change", formObserver, true);
        document.addEventListener("paste", formObserver, true);
    }, 500); // 500 milisaniye gecikme
    
    // Form Main (Değişiklik yapılmışsa uyarı)
    if ($(".form-main").length > 0) {
        var _hasUserLeft = false;
        const doSomethingWhenUserStays = function doSomethingWhenUserStays() {
            if (!_hasUserLeft) {
                $("body").removeClass("loading loading-process");
            }
        }
        window.addEventListener("beforeunload", function(e) {
            if ($(".form-main.form-changed").length == 0) return undefined;

            setTimeout(doSomethingWhenUserStays, 500);
            var confirmationMessage = 'It looks like you have been editing something. If you leave before saving, your changes will be lost.';
            (e || window.event).returnValue = confirmationMessage;
            return confirmationMessage;
        });
        window.addEventListener('unload', function onUnload() {
            _hasUserLeft = true;
        });
    }
});

// Bekleyen Init İşlemleri (Modüllerin yüklenmesini bekleyen)
window.waiting_init = {
    elements: [],
    add: function(elements, callback) {
        this.elements.push({
            elements: elements,
            callback: callback
        });
    },
    initElement: function() {
        this.elements.forEach(function(item) {
            item.elements.forEach(function(element) {
                item.callback(element);
            });
        });
        this.elements = [];
    }
};

// lazy-load main-combined.css only once, when a matching modal is triggered
(function() {
  const VALID_METHODS = ['form_modal', 'form_page', 'form_map', 'iframe_modal', 'template_modal'];
  const FULL_CSS_URL = ajax_request_vars.theme_url+'/static/css/main-combined.css?v=1.0.0'; // buraya kendi yolunu yaz
  let cssLoaded = false;

  // Dinle: herhangi bir tıklama olayı
  document.addEventListener('click', function(e) {
    const trigger = e.target.closest('[data-ajax-method]');
    if (!trigger) return;

    const method = trigger.getAttribute('data-ajax-method');
    if (!VALID_METHODS.includes(method)) return;

    // Modal tetiklendiyse ve CSS henüz yüklenmediyse
    if (!cssLoaded) {
      cssLoaded = true;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = FULL_CSS_URL;
      link.onload = () => console.log('%c[main-combined.css yüklendi 💅]', 'color: #3cfa8c');
      document.head.appendChild(link);
    }
  });

  /* opsiyonel: preload, kullanıcı modal’a tıklamadan önce indirsin
  const preload = document.createElement('link');
  preload.rel = 'preload';
  preload.as = 'style';
  preload.href = FULL_CSS_URL;
  document.head.appendChild(preload);*/
})();

// LazyLoad için Özel Fonksiyonlar
window.lazyFunctions = {
    masonry: function(element) {
        // debugJS($(element));
    }
}


$.ajaxQueue = [];
var ajax_query_queue = $.ajaxQueue;
var ajax_query_process = false;

class ajax_query {
    constructor(method, vars, form, objs) {
        this.method = method;
        this.vars = vars || {};
        this.form = form || {};
        this.objs = objs || {};
        this.upload = false;
        this.skipBefore = false;

        if (Object.keys(this.form).length > 0) {
            this.form[0].ajax_query = this;
        }
        this.vars["lang"] = root.lang;
    }

    data() {
        if (Object.keys(this.form).length > 0 && this.form.find('[type="file"]').length > 0) {
            this.upload = true;
            var form = this.form[0];
            var data = new FormData(form);
            deleteFormData(data, this.vars);
            data.append("ajax", "query");
            data.append("method", this.method);
            data.append("_wpnonce", ajax_request_vars.ajax_nonce);
            createFormData(data, "vars", this.vars);
            return data;
        } else {
            this.upload = false;
            return {
                ajax: "query",
                method: this.method,
                vars: this.vars,
                _wpnonce: ajax_request_vars.ajax_nonce
            };
        }
    }

    abort() {
        if (this.ajax && this.ajax.abort) {
            this.ajax.abort();
        }
    }

    queue() {
        ajax_query_process = false;
        if (this.form.length > 0) {
            this.form.removeClass("ajax-processing");
        }
        if (ajax_query_queue.length > 0) {
            this.request(ajax_query_queue.shift());
        }
    }

    request(obj) {
        const $obj = obj || this;
        let objs = Object.keys($obj.objs).length > 0 ? $obj.objs : (this.vars.hasOwnProperty("objs") ? (delete this.vars.objs, this.vars.objs) : (ajax_objs.hasOwnProperty($obj.method) ? ajax_objs[$obj.method] : {}));
        $obj.objs = objs;

        if (!ajax_hooks.hasOwnProperty($obj.method)) {
            if (isLoadedJS("bootbox")) {
                _alert("Ajax JS Error", $obj.method + " is not defined.");
            } else {
                console.error("Ajax JS Error", $obj.method + " is not defined.");
            }
            return false;
        }

        const hooks = ajax_hooks[$obj.method];

        if (hooks.hasOwnProperty("required")) {
            if (!isLoadedJS(hooks.required, true, () => this.request($obj))) {
                return false;
            }
        }

        if (hooks.hasOwnProperty("before") && !$obj.skipBefore) {
            let $obj_update = true;
            if ($obj.form.length > 0) {
                $obj.form.addClass("ajax-processing");
                if ($obj.form.hasClass("form-review") && !$obj.form.hasClass("form-reviewed")) {
                    return hooks.before($obj, $obj.vars, $obj.form, $obj.objs);
                }
                $obj_update = hooks.before($obj, $obj.vars, $obj.form, $obj.objs);
            } else {
                $obj_update = hooks.before($obj, $obj.vars, $obj.form, $obj.objs);
            }

            if ($obj_update === false || $obj_update === "false" || $obj_update === 0) {
                $obj.form.removeClass("ajax-processing");
                return false;
            }
            // $obj = $obj_update; // Obje güncelleniyorsa, ancak burada varsayılan olarak $obj kullanmaya devam ediyoruz.
        }

        if (typeof $obj.before === "function") {
            $obj.before($obj, $obj.vars, $obj.form, $obj.objs);
        }

        // Kuyruk kontrolü
        if (ajax_query_process || (!site_config.logged && !site_config.loaded && $obj.method !== "site_config")) {
            ajax_query_queue.push($obj);
            return false;
        }
        ajax_query_process = true;

        const data = $obj.data();

        this.ajax = $.ajax({
                queue: true,
                url: ajax_request_vars.url,
                type: 'post',
                data: data,
                enctype: $obj.upload ? 'multipart/form-data' : undefined,
                contentType: $obj.upload ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                processData: !$obj.upload
            })
            .fail(function() {
                $obj.queue();
                //_alert("", "error");
                $("body").removeClass("loading");
            })
            .done(function(response) {
                response = ajaxResponseFilter(response);
                if (isJson(response)) {
                    response = $.parseJSON(response);
                }

                if (response && response.hasOwnProperty("error") && response.error) {
                    response_view(response);
                    $obj.queue();
                    return false;
                }

                $obj.objs = objs;

                if (hooks.hasOwnProperty("after") || hooks.hasOwnProperty("done") || $obj.hasOwnProperty("done")) {
                    if (hooks.hasOwnProperty("after")) hooks.after(response, $obj.vars, $obj.form, $obj.objs);
                    if (hooks.hasOwnProperty("done")) hooks.done(response, $obj.vars, $obj.form, $obj.objs);
                    if ($obj.hasOwnProperty("done")) $obj.done(response, $obj.vars, $obj.form, $obj.objs);
                } else {
                    $obj.check(response);
                }

                if (typeof $obj.after === "function") {
                    $obj.after(response, $obj.vars, $obj.form, $obj.objs);
                }

                $obj.queue();
            });
    }

    check(data) {
        if (!data.resubmit) $("body").removeClass("loading");

        if (!IsBlank(data.message)) {
            if ($(".modal.show").length > 0 && this.form.find("#message").length > 0) {
                this.form.find(".message").html("<div class='alert alert-" + (data.error ? "danger" : "success") + " text-center' role='alert'>" + data.message + "</div>");
            } else {
                response_view(data);
            }
            if (data.error) return false;
        }

        if (!IsBlank(data.redirect) && IsUrl(data.redirect)) {
            if ($("body").hasClass("loading-steps")) {
                var loading = $(this.form).data("loading");
                loading.steps("completed").close();
            }
            window.location.href = data.redirect;
            return false;
        }

        if (!IsBlank(data.html)) return "html";
        if (data.resubmit) return "resubmit";
        return "";
    }
}

class ResponsiveManager {
    constructor() {
        this.body = document.documentElement;
        this.header = document.querySelector("header#header.fixed-bottom-start");
        this.breakpoints = [
            { name: "size-xs", max: 575 },
            { name: "size-sm", min: 576, max: 767 },
            { name: "size-md", min: 768, max: 991 },
            { name: "size-lg", min: 992, max: 1199 },
            { name: "size-xl", min: 1200, max: 1399 },
            { name: "size-xxl", min: 1400, max: 1599 },
            { name: "size-xxxl", min: 1600 }
        ];
        this.init();
    }

    init() {
        if (typeof enquire === "undefined") return;

        // KRİTİK OLANLAR (Orientation handlers) - Senkron kalabilir
        // Tarayıcı yönlendirme olaylarını hemen kaydetmek mantıklıdır.
        enquire.register("screen and (orientation: landscape)", {
            match: () => this.body.classList.add("orientation-ls"),
            unmatch: () => this.body.classList.remove("orientation-ls")
        });
        enquire.register("screen and (orientation: portrait)", {
            match: () => this.body.classList.add("orientation-pr"),
            unmatch: () => this.body.classList.remove("orientation-pr")
        });

        // AĞIR VE KRİTİK OLMAYAN İŞLEMLERİ ERTELEME (requestIdleCallback)
        const scheduleHeavyTasks = () => {
            
            // Breakpoint handlers
            this.breakpoints.forEach(bp => {
                let query;
                if (bp.min && bp.max) query = `(min-width: ${bp.min}px) and (max-width: ${bp.max}px)`;
                else if (bp.max) query = `(max-width: ${bp.max}px)`;
                else if (bp.min) query = `(min-width: ${bp.min}px)`;

                enquire.register(query, {
                    match: () => this.updateBreakpoint(bp.name),
                    unmatch: () => this.body.classList.remove(bp.name)
                });
            });

            // Custom Breakpoint Handlers (hcSticky gibi ağır JQuery işlemleri içeriyor)
            enquire
                .register("(min-width: 1200px)", {
                    match: function() { /* match */ }
                })
                .register("(max-width: 1199px)", {
                    match: function() { /* match */ }
                })
                .register("(min-width: 992px)", {
                    match: function() {
                        // root.map.init(); / google_map.init();
                    }
                })
                .register("(min-width: 780px)", {
                    match: function() { /* match */ }
                })
                .register("(min-width: 0px) and (max-width: 991px)", {
                    match: function() {
                        if ($(".stick-top").length > 0) {
                            $(".stick-top").each(function() {
                                var obj = $(this);
                                $(this).hcSticky('update', {
                                    top: stickyOptions.assign(obj).top
                                });
                            });
                        }
                        // root.map.init(); / google_map.init();
                    }
                })
                .register("(min-width: 0px)", {
                    match: function() { /* match */ }
                });

            // Resize listener'ı da buraya taşıyarak ilk yükte CPU'yu rahatlat
            window.addEventListener("resize", () => {
                this.updateHeaderOffset();
                // navbar_visibility fonksiyonunu direkt çağırmak yerine bir throttle/debounce mekanizması önerilir.
                navbar_visibility();
            });
            this.updateHeaderOffset(); // init call
        };

        // requestIdleCallback destekleniyorsa kullan (ideal çözüm)
        if (window.requestIdleCallback) {
            window.requestIdleCallback(scheduleHeavyTasks);
        } else {
            // Desteklenmiyorsa, 100ms gecikmeli çalıştır (fallback)
            setTimeout(scheduleHeavyTasks, 100);
        }
    }

    updateBreakpoint(className) {
        this.breakpoints.forEach(bp => this.body.classList.remove(bp.name));
        this.body.classList.add(className);
        this.updateHeaderOffset();
    }

    updateHeaderOffset() {
        if (!this.header) return;
        this.header.setAttribute(
            "data-affix-offset",
            window.innerHeight - parseFloat(getComputedStyle(this.header).getPropertyValue("--header-height"))
        );
    }
}
new ResponsiveManager();

var root = {

    options: {},

    lang: document.getElementsByTagName("html")[0].getAttribute("lang"),

    host: location.protocol + "//" + window.location.hostname + (window.location.port > 80 ? ":" + window.location.port + "/" : "/") + (!IsBlank(window.location.pathname) ? window.location.pathname.split("/")[1] + "/" : ""),

    get_host: function(){
      return location.protocol + "//" + window.location.hostname + 
        (window.location.port && window.location.port != 80 && window.location.port != 443 ? ":" + window.location.port : "") + "/";
    },

    hash: window.location.hash,

    Date : Date.now,

    is_home: function() {
        return this.classes.hasClass(document.body, "home")
    },

    logged: function() {
        return this.classes.hasClass(document.body, "logged")
    },

    on_resize: {},

    classes: {
        addClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.add($class);
            }
        },
        removeClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.remove($class);
            }
        },
        toggleClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.toggle($class);
            }
        },
        hasClass: function($obj, $class) {
            return $obj.classList.contains($class);
        }
    },

    css_vars: [],

    get_css_vars: function() {
        setTimeout(function() {
            var arr = {};
            var obj = getComputedStyle(document.documentElement);
            /*arr['header-height-xxxl'] = parseFloat(obj.getPropertyValue('--header-height-xxxl').trim());
            arr['header-height-xxl'] = parseFloat(obj.getPropertyValue('--header-height-xxl').trim());
            arr['header-height-xl'] = parseFloat(obj.getPropertyValue('--header-height-xl').trim());
            arr['header-height-lg'] = parseFloat(obj.getPropertyValue('--header-height-lg').trim());
            arr['header-height-md'] = parseFloat(obj.getPropertyValue('--header-height-md').trim());
            arr['header-height-sm'] = parseFloat(obj.getPropertyValue('--header-height-sm').trim());
            arr['header-height-xs'] = parseFloat(obj.getPropertyValue('--header-height-xs').trim());*/

            arr['header-height'] = parseFloat(obj.getPropertyValue('--header-height').trim());

            /*arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());
            arr['header-height-xxxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxxl-affix').trim());
            arr['header-height-xxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxl-affix').trim());
            arr['header-height-xl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xl-affix').trim());
            arr['header-height-lg-affix'] = parseFloat(obj.getPropertyValue('--header-height-lg-affix').trim());
            arr['header-height-md-affix'] = parseFloat(obj.getPropertyValue('--header-height-md-affix').trim());
            arr['header-height-sm-affix'] = parseFloat(obj.getPropertyValue('--header-height-sm-affix').trim());
            arr['header-height-xs-affix'] = parseFloat(obj.getPropertyValue('--header-height-xs-affix').trim());*/

            arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());

            arr['hero-height-xxxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxxl').trim());
            arr['hero-height-xxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxl').trim());
            arr['hero-height-xl'] = parseFloat(obj.getPropertyValue('--hero-height-xl').trim());
            arr['hero-height-lg'] = parseFloat(obj.getPropertyValue('--hero-height-lg').trim());
            arr['hero-height-md'] = parseFloat(obj.getPropertyValue('--hero-height-md').trim());
            arr['hero-height-sm'] = parseFloat(obj.getPropertyValue('--hero-height-sm').trim());
            arr['hero-height-xs'] = parseFloat(obj.getPropertyValue('--hero-height-xs').trim());
            root.css_vars = arr;
        }, 50);
    },

    get_css_var: function($var) {
        var obj = getComputedStyle(document.documentElement);
        return parseFloat(obj.getPropertyValue('--' + $var).trim());
    },

    throttle: function(fn, wait) {
        let timeout = null;
        return function() {
            const context = this, args = arguments;
            if (!timeout) {
                timeout = setTimeout(function() {
                    timeout = null;
                    fn.apply(context, args);
                }, wait);
            }
        };
    },

    browser: {

        device: function() {
            BrowserDetect.init();
            root.classes.addClass(document.getElementsByTagName("html")[0], BrowserDetect.browser);
            root.classes.addClass(document.getElementsByTagName("html")[0], isMobile.any());
        },

        disable_contextmenu: function() {
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        },

        size_compare : function(a,b){
            var sizes = ["xs", "sm", "md", "lg", "xl", "xxl", "xxxl"];
            return (sizes.indexOf(a) > sizes.indexOf(b));
        },

        size: function() {
            var bodyObj, bodyClass;
            bodyObj = window.document.documentElement; //document.body;
            bodyClass = "xxxl";
            bodyClass = root.classes.hasClass(bodyObj, "size-xxxl") ? "xxxl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xxl") ? "xxl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xl") ? "xl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-lg") ? "lg" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-md") ? "md" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-sm") ? "sm" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xs") ? "xs" : bodyClass;
            return bodyClass;
        },

        closeOffcanvas: function($breakpoint){
            let breakpoints = ["xs", "sm", "md", "lg", "xl", "xxl", "xxxl"];
            let offcanvas = $(".offcanvas.show");
            if (!$offcanvas.length) return;
            breakpoints.forEach(function(bp){
                if (bp === $breakpoint) return;
                if ($offcanvas.hasClass(`offcanvas-${bp}`)) {
                    let bsOffcanvas = bootstrap.Offcanvas.getInstance($offcanvas[0]);
                    if (bsOffcanvas) {
                        bsOffcanvas.hide();
                    } else {
                        $offcanvas.removeClass("show").hide(); // fallback
                    }
                }
            });
        },

    },

    ui: {

        reloadImage: function(img) {
            if (IsBlank(img.attr("data-src"))) {
                img.attr("data-src", img.attr("src")).addClass("fade");
            }
            var src = img.attr("data-src");
            var new_src = src + "?rnd=" + Math.random();
            img.removeClass("in").attr("src", "").attr("src", new_src).on("load", function() {
                $(this).addClass("in");
            });
        },

        viewport: function() {
            const checkViewportStatus = function() {
                $('.viewport').each(function() {
                    var obj = $(this);
                    var posY = obj.offset().top - $(window).scrollTop();
                    if(posY < 0){
                        obj.addClass('out-viewport');
                    }else{
                        obj.removeClass('out-viewport');
                    }

                    if (obj.is(":in-viewport")) {
                        obj.addClass('in-viewport');
                        if(!IsBlank(obj.data("viewport-func"))){
                            window[obj.data("viewport-func")](obj);
                            obj.data("viewport-func", "");
                        }
                    } else {
                        obj.removeClass('in-viewport');
                    }
                });
            };
            $(window).on('scroll resize', root.throttle(checkViewportStatus, 100));
            setTimeout(checkViewportStatus, 150);
        },

        navigation: function() {
            switch (root.options.navigation) {
                case "full":
                    var obj = $('#navbar_container');
                    break;
                case "":
                default:
                    var obj = $('#navigation');
                    break;
            }
            if (obj.find(".link-onepage-home").length > 0) {
                obj.find(".link-onepage-home a").attr("href", "#home");
            }
            obj
                .on('show.bs.collapse', function(e) {
                    if ($("header#header").hasClass("navbar-fixed-top")) {
                        $('.navbar-collapse')[0].body_position = $("html,body").scrollTop();
                        var active_links = [];
                        obj.find("li.active a").each(function(index) {
                            active_links[index] = $(this).attr("href");
                        })
                        $('.navbar-collapse')[0].active_links = active_links;
                    }
                    $("body").addClass("mobile-menu-open");
                })
                .on('shown.bs.collapse', function(e) {})
                .on('hide.bs.collapse', function(e) {
                    $("body").addClass("mobile-menu-closing");
                    if ($("header#header").hasClass("navbar-fixed-top")) {
                        $("html, body").scrollTop($('.navbar-collapse')[0].body_position);
                    }
                })
                .on('hidden.bs.collapse', function(e) {
                    $("body").removeClass("mobile-menu-open").removeClass("mobile-menu-closing");
                });

            $(".dropdown-toggle").on("click", function() {
                if ($(this).parent().hasClass("open")) {
                    var obj = $(this);
                } else {
                    var obj = $(this).closest(".nav").find(".dropdown.open");
                }
                if (obj.length > 0) {
                    obj.removeClass("open");
                    obj.find("a").removeClass("has-submenu highlighted");
                    obj.find("ul.dropdown-menu").attr("aria-hidden", true).attr("aria-expanded", false).css("display", "none");
                }
            });

            /*$('body.home').scrollspy({
                target: '#navigation',
                offset: 110
            });
            $('body.home').on('activate.bs.scrollspy', function(e) {
                if (!$("body").hasClass("mobile-menu-open")) {
                    root.hash = $(e.target).find("a").attr("href");
                    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                }
            });

            $(window).on("click.Bst", function(e) {
                if ($('header#header').has(e.target).length == 0 && !$('header#header').is(e.target)) {
                    if ($(".navbar-collapse").hasClass("in")) {
                        $(".navbar-collapse").collapse("hide");
                    }
                }
            });*/
        },

        offset_top: function() {
            var size = root.browser.size();
            var headerHeight = root.get_css_var("header-height");
            var headerHeightAffix = root.get_css_var("header-height-affix");
            if ($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) {
                return headerHeight;
            } else {
                return headerHeightAffix;
            }
        },

        scroll_dedect: function(up, down) {
            var scrollPos = window.pageYOffset || document.documentElement.scrollTop;
            var header_hide = $("body").hasClass("header-hide-on-scroll");
            $("body").addClass("scroll-dedect");

            var sticky_tops = document.querySelectorAll('.sticky-top');
            var sticky_bottoms = document.querySelectorAll('.sticky-bottom'); // <--- sticky bottom

            window.addEventListener('scroll', function() {
                var currentPos = window.pageYOffset || document.documentElement.scrollTop;

                // sticky top işlemleri
                sticky_tops.forEach(function(sticky_top) {
                    var sticky_top_style = window.getComputedStyle(sticky_top);
                    var top = parseInt(sticky_top_style.top);

                    if (sticky_top.getBoundingClientRect().top === top) {
                        sticky_top.classList.add('sticked');
                    } else {
                        sticky_top.classList.remove('sticked');
                    }
                });

                // sticky bottom işlemleri
                sticky_bottoms.forEach(function(sticky_bottom) {
                    var sticky_bottom_style = window.getComputedStyle(sticky_bottom);
                    var bottom = parseInt(sticky_bottom_style.bottom);

                    if (sticky_bottom.getBoundingClientRect().bottom === window.innerHeight - bottom) {
                        sticky_bottom.classList.add('sticked');
                    } else {
                        sticky_bottom.classList.remove('sticked');
                    }
                });

                if (currentPos <= scrollPos) {
                    $("body").removeClass("header-hide").removeClass("scroll-down").addClass("scroll-up");
                    if (!IsBlank(up)) {
                        if (typeof up === "function") {
                            up(scrollPos);
                        }
                    }
                    if (currentPos <= 0) {
                        $("body").removeClass("scroll-down").removeClass("scroll-up");
                    }
                } else {
                    $("body").removeClass("scroll-up").addClass("scroll-down");
                    if (currentPos > $("header#header").height()) {
                        if (header_hide) {
                            $("body").addClass("header-hide");
                        }
                        $(window).trigger("resize");
                    }
                    if (!IsBlank(down)) {
                        if (typeof down === "function") {
                            down(scrollPos);
                        }
                    }
                }
                scrollPos = currentPos;
            });
        },

        scroll_top: function() {
            if ($('.scroll-to-top').length > 0) {
                var show = $('.scroll-to-top').data("show");
                var duration = $('.scroll-to-top').data("duration");
                $(window).scroll(function() {
                    if(show == "scroll" || show == "always"){
                        if ($(this).scrollTop() > 1) {
                            $('.scroll-to-top').addClass("show");
                        } else {
                            $('.scroll-to-top').removeClass("show");
                        }                        
                    }
                    if(show == "scroll_more"){
                        if ($(this).scrollTop() > window.innerHeight/2) {
                            $('.scroll-to-top').addClass("show");
                        } else {
                            $('.scroll-to-top').removeClass("show");
                        }                        
                    }
                });
                $('.scroll-to-top').on("click", function(e) {
                    e.preventDefault();
                    if ($(".navbar-collapse").hasClass("show")) {
                        $(".navbar-collapse").collapse("hide");
                    }
                    $("html, body").stop().animate({
                        scrollTop: 0
                    }, duration);
                });
            }
        },

        scroll_to: function($hash, $animate, $outside, $callback) {

            if (typeof $hash === "object") {
                if (IsBlank($hash.attr("id"))) {
                    $hash_id = generateCode(5);
                    $hash.attr("id", $hash_id);
                }
                $hash = "#" + $hash.attr("id")
            }

            if (!IsBlank($hash) && typeof $hash !== "undefined") {
                var _history = true;

                //if hash is bs toggle
                if ($($hash).hasClass("tab-pane")) {
                    _history = false;
                }

                $outside = IsBlank($outside) ? false : true;
                var target = $hash;
                if ($(target).length > 0) {
                    root.hash = $hash;

                    if ($(target).hasClass("card-merged") | $(target).hasClass("collapse")) {
                        root.hash = "";
                    }

                    if ($(target).not(".show").hasClass("collapse")) {
                        $(target).collapse("show");
                    }

                    if ($(target).not(".active").hasClass("tab-pane")) {
                        $("a[href='" + target + "']").trigger("click");
                    }

                    var posY = $(target).offset().top;

                    var size = root.browser.size();
                    var headerHeight = root.get_css_var("header-height");
                    var headerHeightAffix = root.get_css_var("header-height-affix");

                    if($(".offcanvas.show").length > 0){
                         $(".offcanvas.show").offcanvas("hide");
                    }
                    if ($(".navbar-collapse").hasClass("show")) {
                        $(".navbar-collapse")
                            .collapse("hide")
                            .on('hidden.bs.collapse', function() {
                                var posY = $(target_section).offset().top;
                                if ($("header#header").hasClass("affix") || $("header#header").hasClass("navbar-fixed-top")) {
                                    posY -= $("header#header").height()
                                }
                                if ($("stick-top.sticky").length > 0) {
                                    $("stick-top.sticky").each(function() {
                                        posY -= $(this).height()
                                    });
                                }
                                $("html, body").stop().animate({
                                    scrollTop: posY
                                }, 600, function() {
                                    if (_history) {
                                        root.hash = target;
                                    }
                                });
                            });
                        return false;
                    }

                    if($(target).hasClass("offcanvas")){
                       $(target).offcanvas("show");
                       if($("[href='"+root.hash+"']").length > 0){
                           posY = $("[href='"+root.hash+"']").offset().top;
                       }
                    }

                    if (($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) ) {
                        if (posY >= headerHeight) {
                            posY -= headerHeight;
                        } else {
                            posY -= headerHeightAffix;
                        }
                    }
                    if(root.browser.size_compare(size,"md")){
                        if($('html').offset().top > posY){//hedef buyukse yukarı, kucukse asağı
                            //yukari
                            if($("header#header").hasClass("fixed-top")){
                                 posY -= headerHeightAffix;
                            }
                        }else{
                            //asagi
                        }
                    }else{
                        posY -= headerHeightAffix;
                    }

                    if ($(".stick-top.sticky").length > 0) {
                        posY -= $(".stick-top.sticky").outerHeight(true);
                    }

                    if ($animate) {
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 600, function() {
                            if (!$outside && !IsBlank(root.hash)) {
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                $callback();
                            }
                        });
                    } else {

                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 0, function() {

                            if (!$outside && !IsBlank(root.hash)) {
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                $callback();
                            }
                        });
                    }
                    return false;
                }
            }
        },

        scroll_to_actions: function() {
            $(document).on("click", "a:not([data-bs-toggle]):not([data-ajax-method]):not(.scroll-to-init)", function(e){
                var btn = $(this);
                btn.addClass("scroll-to-init");
                var target = this.hash;
                if(btn.attr("href").indexOf("#") > -1){
                    var outside = false;
                    if(window.location.href != btn.attr("href").split("#")[0]){
                       outside = true;
                       return;
                    }
                    if($(target).length == 0){
                        var events = $.data(btn.get(0), 'events');
                        if (events == null || typeof events === "undefined") {
                            e.preventDefault();
                        }
                    }else{
                        e.preventDefault();
                        var offcanvas = $(this).closest(".offcanvas");
                        if(offcanvas){
                           offcanvas.offcanvas("hide");
                        }
                        root.ui.scroll_to(target, true);
                    }                    
                }
           });
        },

        prev_next: function() {
            $(document).keydown(function(e) {

                // Input ve textarea focus kontrolü
                if ($(e.target).is('input, textarea')) {
                    return;
                }

                var prev = $("link[rel=prev]").attr("href");
                var next = $("link[rel=next]").attr("href");
                if (prev || next) {
                    switch (e.which) {
                        case 37: // left
                            if (!IsBlank(prev)) {
                                if (prev.indexOf("#") < 0) {
                                    pageLoadUrl(prev);
                                } else {
                                    window.location.href = prev;
                                }
                            }
                            break;
                        case 38: // up
                            break;
                        case 39: // right
                            if (!IsBlank(next)) {
                                if (next.indexOf("#") < 0) {
                                    pageLoadUrl(next);
                                } else {
                                    window.location.href = next;
                                }
                            }
                            break;
                        case 40: // down
                            break;
                        default:
                            return;
                    }
                    e.preventDefault();
                }
            });
        },

        resizing : function(){
            let timer;
            function start() { $("body").addClass("resizing"); }
            function stop() { $("body").removeClass("resizing"); }
            $(window).on("resize", function(){
                start();
                clearTimeout(timer);
                timer = setTimeout(stop, 250);
            });
        },


        tree_menu : function(){
            var obj = $(".nav-tree");
            obj.each(function(){
                var menu = $(this);
                var single_parent = $(this).data("single-parent");
                if(single_parent){
                    $(this).find("a + ul").on('show.bs.collapse', function (e) {
                            var parent = $(e.target).parents();
                            menu.find("ul.collapse.show").not(parent).collapse('hide');
                        //}
                    });
                }
            });
        }

    },

    form: {
        init: function() {
            /*button text on form submit*/
            $('.btn-submit').attr("disabled", false).removeClass("processing");
        }
    },

    card: function() {
        //add active class to each opened panel item in panel-group
        $(document).on("show.bs.collapse", ".card-collapse", function(e) {
            var card = $(e.target).closest(".card")
            card.addClass("active");
            var cardCollapse = $(e.target);
            var parent = card.find("[href='#" + cardCollapse.attr("id") + "']").attr("data-parent");
            if (!IsBlank(parent)) {
                if (card.parent().attr("id") != parent) {
                    $(parent).find(".card-collapse").not(cardCollapse).collapse("hide");
                }
            }
        }).on("hide.bs.collapse", ".card-collapse", function(e) {
            var card = $(e.target).closest(".card")
            card.removeClass("active");
        });
    },

    responsive: {
        table: function() {
            if ($('.table.table-responsive-data').length > 0) {
                $(".table.table-responsive-data").each(function() {
                    var headers = [];
                    $(this).find("thead th").each(function(index) {
                        headers[index] = $(this).text();
                    });
                    $(this).find("tbody tr").each(function() {
                        $(this).find("td").each(function(index) {
                            $(this).attr("data-th", headers[index]);
                        })
                    });
                });
            }
        },

        tab: function() {
            if ($('.tab-collapse').length > 0) {
                $('.tab-collapse').tabCollapse({
                        tabsClass: 'd-lg-flex d-md-none d-sm-none d-none',
                        accordionClass: 'd-lg-none d-md-block s-sm-block d-block card-merged card-collapse card-collapse-scroll'
                    })
                    .on('show-accordion.bs.tabcollapse', function(e) {
                        $(window).trigger("resize");
                        var active_tab = $(e.target).find("li.active").find("a").attr("href");
                        this.activate_tab = active_tab + "-collapse";
                    })
                    .on('shown-accordion.bs.tabcollapse', function(e) {
                        $(this.activate_tab).closest(".card").addClass("active");
                    })
                    .on('show-tabs.bs.tabcollapse', function(e) {

                    })
                    .on('shown-tabs.bs.tabcollapse', function(e) {

                    })
                    .on('shown.bs.tab', function(e) {
                        /*////debugJS(root["map-google"]);
                        if(!IsBlank(root["map-google"])){
                        	eval(root["map-google"])();
                        }*/
                    })


                $(document).on("shown.bs.collapse", ".card-collapse", function(e) {
                        $(e.target).closest(".card").addClass("active");
                        var posY = $(e.target).closest(".card").offset().top;
                        posY = root.ui.fixed_top() ? posY - ($("header#header").height() + 10) : posY;
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 500);
                        //activate tab button
                        var tab_index = $(e.target).closest(".card").index();
                        var tab_id = $(e.target).attr("id").replace("-collapse", "");
                        var tab_buttons = $(e.target).closest(".card-group").prev(".tab-collapse");
                        tab_buttons.find("li").removeClass("active")
                        tab_buttons.find("li").eq(tab_index).addClass("active");
                        //activate tab content
                        var tab_content = $(e.target).closest(".card-group").next(".tab-content");
                        tab_content.find(".tab-pane").removeClass("active").removeClass("in");
                        tab_content.find(".tab-pane#" + tab_id).addClass("active").addClass("in");
                    })
                    .on("hide.bs.collapse", ".card-collapse", function(e) {
                        $(e.target).closest(".card").removeClass("active");
                        //activate tab button
                        var tab_index = $(e.target).closest(".card").index();
                        var tab_id = $(e.target).attr("id").replace("-collapse", "");
                        var tab_buttons = $(e.target).closest(".card-group").prev(".tab-collapse");
                        tab_buttons.find("li").removeClass("active")
                        tab_buttons.find("li").eq(tab_index).addClass("active");
                        //activate tab content
                        var tab_content = $(e.target).closest(".card-group").next(".tab-content");
                        tab_content.find(".tab-pane").removeClass("active").removeClass("in");
                        tab_content.find(".tab-pane#" + tab_id).addClass("active").addClass("in");
                    });
            }
        }
    },

    get_location: function($obj) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        var pos = {
                            lat: position.coords.latitude,
                            lon: position.coords.longitude
                        };
                        if ($obj.hasOwnProperty("callback")) {
                            var obj = {
                                pos: pos,
                                status : true
                            };
                            if ($obj.hasOwnProperty("map")) {
                                obj["map"] = $obj.map;
                            }
                            if ($obj.hasOwnProperty("end")) {
                                obj["end"] = $obj.end;
                            }
                            $obj.callback(obj);
                        } else {
                            return pos;
                        }
                        //infoWindow.setPosition(pos);
                        //infoWindow.setContent('Location found.');
                        //map.setCenter(pos);
                    },
                    function() {
                        //if ($obj.hasOwnProperty("map")) {
                        //    handleLocationError(true, infoWindow, $obj.map.getCenter());
                        //} else {
                        if ($obj.hasOwnProperty("callback")) {
                            $obj.callback({status: false});
                        }
                        _alert("Lütfen browser ayarlarınızdan konum erişimine izin verin.");
                        //}
                    }
                );
            } else {
                // Browser doesn't support Geolocation
                //if ($obj.hasOwnProperty("map")) {
                //    handleLocationError(false, infoWindow, map.getCenter());
                //} else {
                if ($obj.hasOwnProperty("callback")) {
                    $obj.callback(false);
                }
                _alert("Your browser dowsn't support Geolocation");
                //}
            }

            function handleLocationError(browserHasGeolocation, infoWindow, pos) {
                ////debugJS(browserHasGeolocation)
                infoWindow.setPosition(pos);
                infoWindow.setContent(browserHasGeolocation ?
                    'Error: The Geolocation service failed.' :
                    'Error: Your browser doesn\'t support geolocation.');
                /*switch(error.code) {
                                                case error.PERMISSION_DENIED:
                                                  x.innerHTML = "User denied the request for Geolocation."
                                                  break;
                                                case error.POSITION_UNAVAILABLE:
                                                  x.innerHTML = "Location information is unavailable."
                                                  break;
                                                case error.TIMEOUT:
                                                  x.innerHTML = "The request to get user location timed out."
                                                  break;
                                                case error.UNKNOWN_ERROR:
                                                  x.innerHTML = "An unknown error occurred."
                                                  break;
                                            }*/
            }
    },

    init: function(options) {
        root.options = options;
        //root.browser.enquire();
        root.browser.device();
        $(document).ready(function() {
            root.ui.navigation();
            root.get_css_vars();
            root.ui.scroll_top();
            ///root.ui.scroll_to_actions();
            root.ui.prev_next();
            root.ui.viewport();
            root.ui.resizing();
            root.form.init();
            root.ui.tree_menu();

            //root.responsive.table();
            //root.responsive.tab();

            function onResize() {
                for (var func in root.on_resize) {
                    if (root.on_resize.hasOwnProperty(func)) {
                        root.on_resize[func]();
                    }
                }
            };

        });
    }
}

var favorites = {
    class_tease: ".card-profile-tease",
    add: function(obj) {
        var id = obj.data("id");
        var vars = { id: id };
        var data = { method: "favorites_add", vars: vars, _wpnonce: ajax_request_vars.ajax_nonce };
        obj.addClass("disabled loading");
        this.request(obj, data);
    },
    remove: function(obj) {
        var id = obj.data("id");
        var vars = { id: id };
        var data = { method: "favorites_remove", vars: vars, _wpnonce: ajax_request_vars.ajax_nonce };
        obj.addClass("disabled loading");
        if (obj.data("type") == "favorites") {
            obj.closest(this.class_tease).addClass("loading-process");
        }
        this.request(obj, data);
    },
    get: function(obj) {
        var template = "partials/dropdown/archive";
        var vars = { template: template };
        var data = { method: "favorites_get", vars: vars, _wpnonce: ajax_request_vars.ajax_nonce };
        this.request(obj, data);
    },
    update: function($data) {
        site_config.favorites = $data;
        var $data_parsed = $data;
        var dropdown = $(".dropdown-notifications[data-type='favorites']");
        dropdown.toggleClass("active", $data_parsed.length > 0);
    },
    request: function(obj, data) {
        data["vars"]["ajax"] = true;
        data["ajax"] = "query";
        data["_wpnonce"] = ajax_request_vars.ajax_nonce;
        $.post(host, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };

                switch (data["method"]) {
                    case "favorites_add":
                        obj.removeClass("disabled loading").addClass("active");
                        $(".btn-favorite[data-id='" + obj.data("id") + "']").addClass("active");
                        favorites.update(response.data);
                        $(".count-favorites").text(response.count);
                        obj.find(".info").html(response.html);
                        if(typeof $.toast === "function") toast_notification({
                            url: "", sender: { image: "<img src='" + ajax_request_vars.theme_url + "/static/img/notification/favorites-add.jpg' class='img-fluid' alt='Added to favorites'/>" },
                            message: response.message
                        });
                        break;
                    case "favorites_remove":
                        obj.removeClass("active disabled loading");
                        var dropdownBody = obj.closest(".dropdown-body");
                        if (obj.data("type") == "favorites") {
                            obj.closest(favorites.class_tease).parent().remove();
                            // Geri kalan DOM güncellemeleri...
                        }
                        $(".btn-favorite[data-id='" + obj.data("id") + "']").each(function() {
                            $(this).removeClass("active");
                            if ($(this).data("type") == "favorites") {
                                $(this).closest(favorites.class_tease).parent().remove();
                            }
                        });
                        favorites.update(response.data);
                        $(".count-favorites").text(response.count);
                        if(typeof $.toast === "function") toast_notification({
                            url: "", sender: { image: "<img src='" + ajax_request_vars.theme_url + "/static/img/notification/favorites-remove.jpg' class='img-fluid' alt='Removed from favorites'/>" },
                            message: response.message
                        });
                        break;
                    case "favorites_get":
                        obj.html(response.html).removeClass("loading-process");
                        obj.find(".favorites-remove").on("click", function(e) {
                            e.preventDefault();
                            favorites.remove($(this));
                        });
                        obj.toggleClass("has-dropdown-item", response.post_count > 0);
                        SimpleScrollbar.initEl(obj.find(".dropdown-body")[0]);
                        break;
                }
            });
    }
}

var cart = {
    get: function(obj, type) {
        var vars = { type: type };
        var data = { method: "get_cart", vars: vars };
        this.request(obj, data);
    },
    remove_item: function(obj, type) {
        var key = obj.data("key");
        var vars = { key: key, type: type };
        var data = { method: "wc_cart_item_remove", vars: vars };
        obj.addClass("loading-process");
        this.request(obj.closest(".load-container"), data);
    },
    request: function(obj, data) {
        data["ajax"] = "query";
        $.post(host, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };

                switch (data["method"]) {
                    case "get_cart":
                    case "wc_cart_item_remove":
                        var html = $("<div class='temp'>" + response.html + "</div>");
                        var footer = "";
                        if (html.find(".offcanvas-footer").length > 0) {
                            footer = html.find(".offcanvas-footer").html();
                            html.find(".offcanvas-footer").remove();
                        }
                        obj.html(html.html()).removeClass("loading-process");
                        
                        var footerObj = obj.next(".offcanvas-footer");
                        footerObj.html(footer).toggleClass("d-none", IsBlank(footer));

                        var count = response.data && response.data.count || 0;
                        var counter = $(".dropdown-notifications[data-type='cart'] > a").find(".notification-count");
                        
                        if (counter.length == 0 && count > 0) {
                            $(".dropdown-notifications[data-type='cart'] > a").prepend("<div class='notification-count'></div>");
                            counter = $(".dropdown-notifications[data-type='cart']").find(".notification-count");
                        }
                        
                        if (count == 0) {
                            counter.remove();
                        } else {
                            counter.html(count);
                        }
                        
                        $(".dropdown-notifications[data-type='cart'] .dropdown-container").toggleClass("has-dropdown-item", count > 0);
                        
                        obj.find(".cart-item-remove").not(".init").each(function() {
                            $(this).addClass("init").on("click", function(e) {
                                e.preventDefault();
                                cart.remove_item($(this).closest(".notification-item"), data.vars.type);
                            });
                        });
                        break;
                }
            });
    }
}

var messages = {
    get: function(obj) {
        var template = "partials/offcanvas/archive";
        var vars = { template: template };
        var data = { ajax: "query", method: "get_messages", vars: vars, _wpnonce: ajax_request_vars.ajax_nonce };
        this.request(obj, data);
    },
    remove_item: function(obj) {
        var key = obj.data("key");
        var vars = { key: key };
        var data = { method: "wc_cart_item_remove", vars: vars };
        obj.addClass("loading-process");
        this.request(obj.closest(".dropdown-container"), data);
    },
    request: function(obj, data) {
        $.post(ajax_request_vars.url, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };

                switch (data["method"]) {
                    case "get_messages":
                        obj.html(response.html).removeClass("loading-process");
                        var count = response.data && response.data.count || 0;
                        var counter = $(".dropdown-notifications[data-type='messages'] > a").find(".notification-count");
                        
                        if (counter.length == 0 && count > 0) {
                            $(".dropdown-notifications[data-type='messages'] > a").prepend("<div class='notification-count'></div>");
                            counter = $(".dropdown-notifications[data-type='messages']").find(".notification-count");
                        }
                        
                        if (count == 0) {
                            counter.remove();
                        } else {
                            counter.html(count);
                        }

                        obj.find(".cart-item-remove").not(".init").each(function() {
                            $(this).addClass("init").on("click", function(e) {
                                e.preventDefault();
                                messages.remove_item($(this).closest(".notification-item"));
                            });
                        });
                        SimpleScrollbar.initEl(obj.find(".dropdown-body")[0]);
                        break;
                }
            });
    }
}