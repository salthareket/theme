/**
 * ========================================================================
 * BİRLEŞTİRİLMİŞ VE OPTİMİZE EDİLMİŞ JAVASCRIPT DOSYASI
 * (defaults.js ve 1-custom.js birleştirildi)
 * ========================================================================
 */

// Global Hata Ayıklama Değişkenleri
var debug = false;

// ========================================================================
// I. GENEL YARDIMCI İŞLEVLER VE DEĞİŞKENLER
// ========================================================================

// Hata İşleyicileri
window.onerror = function(message, url, line) {
    // Üretim ortamında konsola loglama veya sunucuya hata raporlama için kullanılabilir.
    // debugJS(message + ', ' + url + ', ' + line); // debug modu aktifse kullanılabilir
};

// Yönlendirme Polifili (Güvenli Yönlendirme)
function redirect_polyfill($url, $blank = false) {
    var linkElement = document.createElement('a');
    linkElement.href = $url;
    if ($blank) {
        linkElement.target = "_blank";
    }
    // Elementi DOM'a eklemeye gerek yok, doğrudan click() metodu çoğu modern tarayıcıda çalışır.
    // Ancak daha güvenli bir polifil için ekleyip çıkaralım.
    document.body.appendChild(linkElement);
    linkElement.click();
    document.body.removeChild(linkElement);
}

// Sunucudan Gelen Yanıtı İşleme (Ajax)
function errorView($data) {
    if ($data.error) {
        _alert('', $data.message); // Varsayılan _alert() fonksiyonunu kullanır
        return true;
    }
    return false;
}

function response_view(response) {
    var modal = $(".modal.show");
    if (response.error) {
        $("body").removeClass("loading-process");
        if (response.hasOwnProperty("error_type") && response.error_type == "nonce") {
            if (modal.length > 0) modal.modal("hide");
            _alert(response.message, response.description, "", "", "Refresh Page", function() {
                window.location.reload();
            });
        } else {
            _alert(response.message, response.description, "", "", "", "", true);
        }
    } else {
        if (response.redirect) {
            if (response.message) {
                if (modal.length > 0) modal.addClass("remove-on-hidden").modal("hide");
                _alert(response.message, response.description);
            }
            redirect_polyfill(response.redirect, response.redirect_blank);
        } else if (response.refresh) {
            $("body").addClass("loading");
            window.location.reload();
        } else if (response.refresh_confirm) {
            _alert(response.message, response.description, "", "", "Tamam", function() {
                window.location.reload();
            });
        } else {
            if (response.message) {
                if (modal.length > 0) modal.addClass("remove-on-hidden").modal("hide");
                _alert(response.message, response.description);
            }
            $("body").removeClass("loading-process");
        }
    }
}

// Bekleyen Init İşlemleri (Modüllerin yüklenmesini bekleyen)
var waiting_init = {
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

// Yerelleştirme ve Çeviri İşlevi
function translate(str, count = 1, replacements = {}) {
    if(str == ""){
        return str;
    }
    const defaultLang = site_config.language_default;
    const currentLang = site_config.user_language;

    let entry = str;

    const dictEntry = site_config.dictionary?.[str];

    // Dil farklıysa dictionary'den çeviri yap
    if (defaultLang !== currentLang && dictEntry !== undefined) {
        if (Array.isArray(dictEntry)) {
            const safeCount = parseInt(count, 10);
            entry = safeCount === 1 ? dictEntry[0] : dictEntry[1] || dictEntry[0];
        } else if (typeof dictEntry === 'string') {
            entry = dictEntry;
        }
    }

    // HER ZAMAN %count ve diğer %placeholder’ları replace et
    entry = String(entry).replace('%count', count);

    for (const key in replacements) {
        entry = entry.replaceAll(key, replacements[key]);
    }

    return entry;
}

// LazyLoad için Özel Fonksiyonlar
window.lazyFunctions = {
    masonry: function(element) {
        // debugJS($(element));
    }
}

// AJAX Queue ve Sınıf Tanımları
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
                _alert("", "error");
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

// ========================================================================
// II. SCROLLPOS-STYLER (Throttle ve Passive ile Performans İyileştirmesi)
// ========================================================================

var ScrollPosStyler = (function(document, window) {
    "use strict";

    // Throttle: Fonksiyonun belirli bir süreden daha sık çalışmasını engeller.
    function throttle(fn, wait) {
        let timeout = null;
        return function() {
            const context = this,
                args = arguments;
            if (!timeout) {
                timeout = setTimeout(function() {
                    timeout = null;
                    fn.apply(context, args);
                }, wait);
            }
        };
    }

    var scrollPos = 0,
        isTicking = false,
        defaultScrollOffsetY = 1,
        defaultSpsClass = "sps",
        spsElements = document.getElementsByClassName(defaultSpsClass),
        defaultClassAbove = "sps--abv",
        defaultClassBelow = "sps--blw",
        defaultOffsetTag = "data-sps-offset";

    var currentSpsClass = defaultSpsClass,
        currentScrollOffsetY = defaultScrollOffsetY,
        currentClassAbove = defaultClassAbove,
        currentClassBelow = defaultClassBelow,
        currentOffsetTag = defaultOffsetTag;

    function calculateClassChanges(force) {
        var changes = [];
        // OKUMA İŞLEMİ (Read)
        scrollPos = window.pageYOffset;

        for (var t = 0; spsElements[t]; ++t) {
            var element = spsElements[t],
                // offset değeri okunur
                offsetY = element.getAttribute(currentOffsetTag) || currentScrollOffsetY,
                hasClassAbove = element.classList.contains(currentClassAbove);

            if ((force || hasClassAbove) && offsetY < scrollPos) {
                changes.push({
                    element: element,
                    addClass: currentClassBelow,
                    removeClass: currentClassAbove
                });
            } else if ((force || !hasClassAbove) && scrollPos <= offsetY) {
                changes.push({
                    element: element,
                    addClass: currentClassAbove,
                    removeClass: currentClassBelow
                });
            }
        }
        return changes;
    }

    function applyClassChanges(changes) {
        // YAZMA İŞLEMİ (Write)
        for (var e = 0; changes[e]; ++e) {
            var change = changes[e];
            change.element.classList.add(change.addClass);
            change.element.classList.remove(change.removeClass);
        }
        isTicking = false;
    }

    var publicAPI = {
        init: function(s) {
            isTicking = true;

            if (s) {
                if (s.spsClass) {
                    currentSpsClass = s.spsClass;
                    spsElements = document.getElementsByClassName(currentSpsClass);
                }
                currentScrollOffsetY = s.scrollOffsetY || defaultScrollOffsetY;
                currentClassAbove = s.classAbove || defaultClassAbove;
                currentClassBelow = s.classBelow || defaultClassBelow;
                currentOffsetTag = s.offsetTag || defaultOffsetTag;
            }

            var changes = calculateClassChanges(true);

            if (changes.length > 0) {
                // DOM değişiklikleri için requestAnimationFrame
                window.requestAnimationFrame(function() {
                    applyClassChanges(changes);
                });
            } else {
                isTicking = false;
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function() {
        // Init'i 50ms geciktirerek tarayıcıya kritik işleri bitirmesi için zaman tanır.
        setTimeout(function() {
            publicAPI.init();
        }, 50);
    });

    // SCROLL OLAYI (100ms throttle ve passive: true)
    window.addEventListener("scroll", throttle(function() {
        if (!isTicking) {
            var changes = calculateClassChanges(false);

            if (changes.length > 0) {
                isTicking = true;
                window.requestAnimationFrame(function() {
                    applyClassChanges(changes);
                });
            }
        }
    }, 100), {
        passive: true
    });

    return publicAPI;
})(document, window);

// Header Affix Init (ScrollPosStyler'ın özel kullanımı)
if (window["ScrollPosStyler"] && document.getElementById("header")) {
    var header = document.getElementById("header");
    if (!header.classList.contains("affix")) {
        ScrollPosStyler.init({
            spsClass: "affixed",
            classAbove: "affix-top",
            classBelow: "affix",
            offsetTag: "data-affix-offset",
            scrollOffsetY: 50
        });
        // console.log(ScrollPosStyler)
    }
}

// Ana içerik scroll (Eski yöntem - ScrollPosStyler ile çakışabilir, ancak mevcut mantık korundu.)
var main = document.querySelector("#main");
var header = document.querySelector("#header");

if (main && header && root && root.classes) {
    var scrollTop = main.scrollTop || document.documentElement.scrollTop;
    var headerHeight = root.get_css_var("header-height");

    if (scrollTop > headerHeight) {
        if (header.classList.contains("fixed-top")) {
            root.classes.addClass(header, "affix");
            root.classes.removeClass(header, "affix-top");
        }
    }
}


// ========================================================================
// III. RESPONSIVE YÖNETİMİ VE ENQUIRE.JS (Tekrar eden enquire kodları birleştirildi)
// ========================================================================

/*class ResponsiveManager {
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

        // Orientation handlers
        enquire.register("screen and (orientation: landscape)", {
            match: () => this.body.classList.add("orientation-ls"),
            unmatch: () => this.body.classList.remove("orientation-ls")
        });
        enquire.register("screen and (orientation: portrait)", {
            match: () => this.body.classList.add("orientation-pr"),
            unmatch: () => this.body.classList.remove("orientation-pr")
        });

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
        
        // Custom Breakpoint Handlers (1-custom.js'ten gelenler)
        enquire
            .register("(min-width: 1200px)", {
                match: function() { }
            })
            .register("(max-width: 1199px)", {
                match: function() { }
            })
            .register("(min-width: 992px)", {
                match: function() {
                    // root.map.init(); / google_map.init();
                }
            })
            .register("(min-width: 780px)", {
                match: function() { }
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
                match: function() { }
            });

        // Tekrarlanan resizeDebounce kaldırıldı, onun yerine tek bir window.addEventListener kullanıldı.
        window.addEventListener("resize", () => {
            this.updateHeaderOffset();
            // navbar_visibility fonksiyonunu direkt çağırmak yerine bir throttle/debounce mekanizması önerilir.
            // Bu örnekte, hızlı olması için doğrudan çağrıldı.
            navbar_visibility(); 
        });
        this.updateHeaderOffset(); // init call
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
*/
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


// Navbar Görünürlük Kontrolü
function navbar_visibility() {
    const isVisible = (el) => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    };

    const checkVisibility = (parent, selector) => {
        const children = parent.querySelectorAll(selector);
        const visible = Array.from(children).some(isVisible);
        parent.classList.toggle('d-none', !visible);
    };

    const headerTools = document.querySelector('.navbar-top .header-tools');
    if (headerTools) checkVisibility(headerTools, 'ul > li');

    document.querySelectorAll('.navbar-top > *').forEach(el => {
        checkVisibility(el, ':scope > *');
    });
}


// ========================================================================
// IV. SAYFA YÜKLEME VE Olay İşleyicileri (En başa alındı)
// ========================================================================

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

// ========================================================================
// V. LAZYLOAD İŞLEYİCİSİ (Performans için kritik)
// ========================================================================

if (isLoadedJS("vanilla-lazyload")) {
    lazyLoadInstance = new LazyLoad({
        elements_selector: ".lazy",
        // Swiper içindeki video/iframe'lerin sadece aktif slide'da yüklenmesi için optimizasyon
        callback_loading: function(e) {
            var obj = $(e);
            if (obj[0].nodeName == 'IFRAME' && obj.hasClass('video')) {
                let slide = obj.closest(".swiper-slide");
                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("loading");
                    obj.removeAttr("data-ll-status");
                    console.log("Swiper aktif değil, iframe src yüklenmeyecek.");
                    return false;
                }
            }
        },
        callback_loaded: function(e) {
            var obj = $(e);

            if (obj.hasClass("ratio")) {
                obj.parent().removeClass("loading").removeClass("loading-hide");
            }
            if (obj[0].nodeName == 'IMG') {
                obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                if (obj.parent().hasClass("img-placeholder")) {
                    obj.closest(".img-placeholder").removeClass("loading loading-hide loading-process");
                }
            }

            // Yeniden düzenleme (layout) gerektiren kütüphaneler için
            if ($("[data-masonry]").length > 0) $("[data-masonry]").data('masonry').layout();
            if (obj.closest("[data-isotope]").length > 0) {
                obj.closest("[data-isotope]").data('isotope').layout().reloadItems();
            }

            if (obj.hasClass("video")) {
                let slide = obj.closest(".swiper-slide");

                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("loaded");
                    obj.removeAttr("data-ll-status");
                    console.log("Swiper slide aktif değil, video yüklenmeyecek. calvack_loaded");
                    return false;
                }

                obj.closest(".lazy-container").removeClass("lazy-container");
                obj.parent().find(">.plyr__poster").remove();
                obj.parent().addClass("lazy-loaded");
                plyr_init(obj.parent()); // plyr_init varsayılıyor
            }

            $.fn.matchHeight._update();
        },
        callback_error: function(e) {
            var obj = $(e);
            if (obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")) {
                // Placeholder görseli yükle
                obj.attr("data-src", ajax_request_vars.theme_url + "/static/img/placeholder/img-" + obj.attr("data-placeholder") + ".jpg");
                obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                if (obj.parent().hasClass("img-placeholder")) {
                    obj.unwrap();
                }
                LazyLoad.load(obj[0]);
            }
        },
        callback_enter: function(e) {
            var obj = $(e);
            // Placeholder kontrolü
            if (obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")) {
                if (IsBlank(obj.attr("data-src")) && IsBlank(obj.attr("src"))) {
                    obj.attr("data-src", ajax_request_vars.theme_url + "/static/img/placeholder/img-" + obj.attr("data-placeholder") + ".jpg");
                    obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                    LazyLoad.load(obj[0]);
                }
            }

            // Swiper video kontrolü (src yüklemesini engelleme)
            if (obj[0].nodeName == 'IFRAME' && obj.hasClass('video')) {
                let slide = obj.closest(".swiper-slide");
                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("entered");
                    obj.removeAttr("data-ll-status");
                    obj.attr("data-src-backup", obj.attr("data-src"));
                    obj.removeAttr("data-src");
                    console.log("Swiper aktif değil, iframe src yüklenmeyecek.");
                    return false;
                }
            }

            // AOS yenileme
            if (typeof window["AOS"] === "object") {
                AOS.refreshHard();
            }

            // Tembel Fonksiyon Çağrısı
            var lazyFunctionName = e.getAttribute("data-lazy-function");
            if (lazyFunctionName) {
                var lazyFunction = window.lazyFunctions[lazyFunctionName];
                if (lazyFunction) lazyFunction(e);
            }
        }
    });

    // LazyLoad Olay Dinleyicileri
    document.addEventListener('lazyloaded', function(e) {
        var obj = $(e.target);
        if (obj.hasClass("swiper-bg")) {
            obj.closest(".swiper-slide").addClass("image-loaded");
        }
        $.fn.matchHeight._update();
    });

    $(document).on('lazyload', function(e) {
        $.fn.matchHeight._update();
        $(window).trigger("resize");
    });
}

// ========================================================================
// VI. Modül/API Tanımları (jQuery tabanlı)
// ========================================================================

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