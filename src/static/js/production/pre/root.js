// Global Değişkenler
var debug = false;
var _hasUserLeft = false;
let ajax_hooks = {};
window.methods_js_loading = false;
window.methods_js_loaded = false;

// Sayfa yenilendiğinde en üstten başlasın (isteğe bağlı)
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

// 1. Hata İşleyicileri
window.onerror = function(message, url, line) {
    // Üretim ortamında loglama gerekirse burası kullanılabilir.
};

// DOM tamamen yüklendiğinde
document.addEventListener('DOMContentLoaded', () => {

    // Yükleme sınıflarını kaldır
    document.body.classList.remove("loading", "loading-process");
    
    // Tarayıcı boyutu ve CSS değişkenlerini ayarla
    if (typeof root !== 'undefined') {
        size = root.browser.size();
        root.get_css_vars();
    }

    const formMain = document.querySelector(".form-main");
    if (formMain) {
        const doSomethingWhenUserStays = function() {
            // Kullanıcı sayfada kalmayı seçerse loading ekranını kapat
            if (!_hasUserLeft) {
                document.body.classList.remove("loading", "loading-process");
            }
        };

        window.addEventListener("beforeunload", function(e) {
            // Formda değişiklik yoksa uyarı verme
            if (!document.querySelector(".form-main.form-changed")) {
                return undefined;
            }

            // Kullanıcı "Kal" derse loading animasyonunu temizlemek için
            setTimeout(doSomethingWhenUserStays, 500);

            // Modern tarayıcı standart uyarı protokolü
            e.preventDefault(); 
            e.returnValue = ''; 
            return '';
        });
    }

    window.addEventListener('pagehide', function() {
        _hasUserLeft = true;
    });

    // 4. Sayfa yeniden gösterildiğinde (Geri/İleri tuşu veya bfcache)
    window.addEventListener('pageshow', (event) => {
        _hasUserLeft = false; // Sayfaya dönüldüğü için durumu sıfırla
        document.body.classList.remove("loading", "loading-process");
        
        // Eğer sayfa tarayıcı önbelleğinden (bfcache) geliyorsa ve tazelenmesi gerekiyorsa:
        if (event.persisted) {
             window.location.reload();
        }
    });

    // 5. Görünürlük Değişimi
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            document.body.classList.remove('loading-process', 'loading');
        }
    });

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

    // 2 & 3: Select URL Redirect ve Hash Scroll (Birleştirilmiş)
    document.addEventListener("change", function(e) {
        const el = e.target;

        // 1. Durum: URL Redirect (select-url)
        if (el.classList.contains("select-url")) {
            document.body.classList.add("loading-process");
            window.location.href = el.value;
            return; // İşlem tamam, aşağıya bakmasına gerek yok
        }

        // 2. Durum: Hash Scroll (select-hash)
        if (el.classList.contains("select-hash")) {
            const parts = el.value.split("#");
            const id = parts[1] ? "#" + parts[1] : null;
            if (id) {
                root.ui.scroll_to(id, true);
            }
        }
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
    }, 500);  
});

/*// Bekleyen Init İşlemleri (Modüllerin yüklenmesini bekleyen)
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
};*/

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
      link.onload = () => debugJS('%c[main-combined.css yüklendi 💅]', 'color: #3cfa8c');
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



/**
 * TurboQuery 2026 - Full Edition
 * High-Performance Fetch, Auto-Log & Smart Cache System
 */
let ajax_query_queue = [];
let ajax_query_process = false;
const ajax_hooks_url = `${ajax_request_vars.theme_url}static/js/methods.min.js`;
class ajax_query {
    constructor(method = null, vars = {}, form = {}, objs = {}) {
        this.method = method;
        this.vars = vars || {};
        this.form = form instanceof jQuery ? form : $(form);
        this.objs = objs || {};
        this.upload = false;
        this.skipBefore = false;
        this.ajax = null;
        
        // --- CACHE CONFIGURATION ---
        this.cacheEnabled = (this.vars.cache === true);

        if (!this.vars) this.vars = {};
        this.vars["lang"] = typeof root !== 'undefined' ? root.lang : 'tr';
        
        if (this.form.length > 0) {
            this.form[0].ajax_query = this;
        }
    }

    // Cache Key Oluşturucu
    getCacheKey() {
        return `turbo_${this.method}_${JSON.stringify(this.vars)}`;
    }

    // Response İşleyici (Hooks & Logic)
    async handleHooksAndCheck(result, obj, hooks) {
        const isArray = Array.isArray(result);
        const isError = result && !isArray && result.error;

        if (isError) {
            if (typeof response_view === "function") response_view(result);
        } else {
            // Hook'ları sırasıyla çalıştır
            if (hooks.after) await hooks.after(result, obj.vars, obj.form, obj.objs);
            if (hooks.done) await hooks.done(result, obj.vars, obj.form, obj.objs);
            if (obj.done) await obj.done(result, obj.vars, obj.form, obj.objs);

            if (!isArray && result !== null) {
                if (!hooks.after && !hooks.done && !obj.done) {
                    this.check(result);
                }
            } else {
                this.check(null);
            }
            if (typeof obj.after === "function") obj.after(result, obj.vars, obj.form, obj.objs);
        }
    }

    async ensureMethodsLoaded() {
        if (window.methods_js_loaded) return true;
        if (window.methods_js_loading) {
            return new Promise(resolve => {
                const check = setInterval(() => {
                    if (window.methods_js_loaded) { clearInterval(check); resolve(true); }
                }, 50);
            });
        }
        window.methods_js_loading = true;
        try {
            await this.loadScript(ajax_hooks_url);
            window.methods_js_loaded = true;
            window.methods_js_loading = false;
            return true;
        } catch (e) {
            window.methods_js_loading = false;
            return false;
        }
    }

    loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url; script.async = true;
            script.onload = resolve; script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async checkDependencies(obj) {
        const hooks = window.ajax_hooks?.[obj.method] || {};
        let requiredList = [...(hooks.required || []), ...(obj.vars?.required || [])];
        requiredList = [...new Set(requiredList)];
        if (requiredList.length === 0) return true;
        return new Promise((resolve) => {
            isLoadedJS(requiredList, true, () => {
                if (obj.vars.required) delete obj.vars.required;
                resolve(true);
            });
        });
    }

    getPayload() {
        this.vars["lang"] = typeof root !== 'undefined' ? root.lang : 'tr';
        const hasFiles = this.form.length > 0 && this.form.find('[type="file"]').length > 0;
        if (hasFiles) {
            const formData = new FormData(this.form[0]);
            Object.keys(this.vars).forEach(key => {
                formData.append(`vars[${key}]`, typeof this.vars[key] === 'object' ? JSON.stringify(this.vars[key]) : this.vars[key]);
            });
            return formData;
        }
        return JSON.stringify({ vars: this.vars });
    }

    queue() {
        ajax_query_process = false;
        if (this.form.length > 0) this.form.removeClass("ajax-processing");
        if (ajax_query_queue.length > 0) {
            const next = ajax_query_queue.shift();
            next.request();
        }
    }

    /*async function fetchWithRetry(url, options, retries = 3, backoff = 1000) {
        try {
            const response = await fetch(url, options);
            
            // Eğer 508 (Limit) veya 503 (Servis Yok) dönerse
            if (response.status === 508 || response.status === 503) {
                if (retries > 0) {
                    console.warn(`Limit aşıldı, ${backoff}ms sonra tekrar deneniyor...`);
                    await new Promise(resolve => setTimeout(resolve, backoff));
                    return fetchWithRetry(url, options, retries - 1, backoff * 2); // Her seferinde bekleme süresini artır
                }
            }
            return response;
        } catch (error) {
            if (retries > 0) {
                await new Promise(resolve => setTimeout(resolve, backoff));
                return fetchWithRetry(url, options, retries - 1, backoff * 2);
            }
            throw error;
        }
    }*/

    async request(obj = this) {
        if (!obj.method) return;

        if (window.wp && wp.heartbeat && wp.heartbeat.interval()) {
            wp.heartbeat.stop();
            debugJS("Heartbeat durduruldu (AJAX başladı).");
        }

        // 1. GLOBAL START: İstek başlar başlamaz tetikle
        $(document).trigger('ajax_query:start', [obj]);

        if (!await this.ensureMethodsLoaded()) return;
        
        obj.cacheEnabled = (obj.vars && obj.vars.cache === true);
        const cacheKey = obj.getCacheKey();

        // --- CACHE KONTROLÜ ---
        if (obj.cacheEnabled === true) {
            const cachedData = sessionStorage.getItem(cacheKey);
            if (cachedData) {
                const result = JSON.parse(cachedData);
                const hooks = window.ajax_hooks?.[obj.method] || {};
                
                await this.handleHooksAndCheck(result, obj, hooks);

                // CACHE OLSA BİLE EVENTLERİ ÇALIŞTIR (Senin istediğin kritik nokta)
                $(document).trigger('ajax_query:complete', [obj]);
                
                // Kuyrukta bekleyen başka iş yoksa stop tetikle
                if (ajax_query_queue.length === 0) {
                    $(document).trigger('ajax_query:stop', [obj]);
                    if (window.wp && wp.heartbeat && !wp.heartbeat.interval()) {
                        wp.heartbeat.start();
                        debugJS("Heartbeat yeniden başlatıldı (Tüm AJAX bitti).");
                    }
                }
                return; 
            }
        } else {
            sessionStorage.removeItem(cacheKey);
        }

        // --- REQUEST HAZIRLIK ---
        if (window.active_requests && window.active_requests[obj.method]) {
            window.active_requests[obj.method].abort();
        }

        const controller = new AbortController();
        if (!window.active_requests) window.active_requests = {};
        window.active_requests[obj.method] = controller;
        this.ajax = controller;
        
        try {
            const hooks = window.ajax_hooks?.[obj.method] || {};
            const hasDeps = (hooks.required?.length > 0) || (obj.vars?.required?.length > 0);
            if (hasDeps) await this.checkDependencies(obj);

            if (hooks.before && !obj.skipBefore) {
                if (obj.form.length > 0) obj.form.addClass("ajax-processing");
                const proceed = await hooks.before(null, obj.vars, obj.form, obj.objs);
                if (proceed === false) {
                    delete window.active_requests[obj.method];
                    this.queue(); return;
                }
            }

            // Kuyruk Yönetimi
            if (ajax_query_process && obj.method !== "search_store") { 
                ajax_query_queue.push(obj); return;
            }
            ajax_query_process = true;

            let baseUrl = ajax_request_vars.site_url;
            if (!baseUrl.endsWith('/')) baseUrl += '/';
            const apiUrl = `${baseUrl}api/${obj.method}/`;
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                signal: controller.signal,
                headers: (this.form.find('[type="file"]').length > 0) ? 
                    { 'X-WP-Nonce': ajax_request_vars.ajax_nonce } : 
                    { 'Content-Type': 'application/json', 'X-WP-Nonce': ajax_request_vars.ajax_nonce },
                body: this.getPayload()
            });

            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

            let result = await response.json();
            if (typeof ajaxResponseFilter === "function") result = ajaxResponseFilter(result);

            // Başarılı sonucu cache'e yaz
            if (obj.cacheEnabled === true && result && !result.error) {
                sessionStorage.setItem(cacheKey, JSON.stringify(result));
            }

            await this.handleHooksAndCheck(result, obj, hooks);

        } catch (error) {
            if (error.name !== 'AbortError') {
                this.check(null);
            }
        } finally {
            delete window.active_requests[obj.method];
            ajax_query_process = false;

            // 2. GLOBAL COMPLETE: İstek bitti (Başarılı/Hatalı fark etmez)
            $(document).trigger('ajax_query:complete', [obj]);

            // 3. GLOBAL STOP: Kuyrukta bekleyen başka istek kalmadıysa
            if (ajax_query_queue.length === 0) {
                $(document).trigger('ajax_query:stop', [obj]);
                if (window.wp && wp.heartbeat && !wp.heartbeat.interval()) {
                    wp.heartbeat.start();
                    debugJS("Heartbeat yeniden başlatıldı (Tüm AJAX bitti).");
                }
            }

            this.queue();
        }
    }
    
    check(data) {
        if (!data || Array.isArray(data)) {
            $("body").removeClass("loading");
            if (this.form.length > 0) this.form.removeClass("ajax-processing");
            return;
        }
        
        if (data.resubmit !== true) {
            $("body").removeClass("loading");
            if (this.form.length > 0) this.form.removeClass("ajax-processing");
        }
        
        if (data.message && typeof response_view === "function") response_view(data);
        if (data.redirect) { window.location.href = data.redirect; }
    }

    abort() {
        if (this.ajax) this.ajax.abort();
    }
}

class ResponsiveManager {
    constructor() {
        // documentElement (<html>) kullanımı class yönetimi için daha performanslıdır
        this.body = document.documentElement; 
        this.header = document.querySelector("header#header");
        this.resizeTimer = null;

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

        // --- 1. ORIENTATION (Yönelim) KONTROLÜ ---
        const setOrientation = (orientation) => {
            const remove = orientation === 'ls' ? 'orientation-pr' : 'orientation-ls';
            this.body.classList.remove(remove);
            this.body.classList.add(`orientation-${orientation}`);
        };

        enquire.register("screen and (orientation: landscape)", { match: () => setOrientation('ls') });
        enquire.register("screen and (orientation: portrait)", { match: () => setOrientation('pr') });

        // --- 2. BREAKPOINT YÖNETİMİ ---
        this.breakpoints.forEach(bp => {
            let query = bp.min ? `(min-width: ${bp.min}px)` : "";
            if (bp.max) query += (query ? (query ? " and " : "") : "") + `(max-width: ${bp.max}px)`;
            // Safari/Eski tarayıcı düzeltmesi: query başta boşsa min-width yok demektir
            if (!bp.min) query = `(max-width: ${bp.max}px)`;

            enquire.register(query, {
                match: () => this.updateBreakpoint(bp.name)
                // unmatch eklemiyoruz, updateBreakpoint zaten temizliği yapıyor.
            });
        });

        // --- 3. AĞIR İŞLEMLER (Deferred / Idle) ---
        const scheduleTasks = () => {
            // Sticky elementleri sadece mobil/tablet geçişinde bir kez tetikle
            enquire.register("(max-width: 991px)", { match: () => this.refreshStickyElements() });

            window.addEventListener("resize", () => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => {
                    this.updateHeaderOffset();
                    if (typeof navbar_visibility === "function") navbar_visibility();
                }, 150);
            }, { passive: true });

            this.updateHeaderOffset();
        };

        //this.handleOffcanvasTogglerVisibility();

        if (window.requestIdleCallback) {
            window.requestIdleCallback(scheduleTasks);
        } else {
            setTimeout(scheduleTasks, 200);
        }
    }

    updateBreakpoint(className) {
        // Sadece 'size-' ile başlayan sınıfları temizle (diğerlerini koru)
        const toRemove = Array.from(this.body.classList).filter(c => c.startsWith('size-'));
        if (toRemove.length > 0) this.body.classList.remove(...toRemove);
        
        this.body.classList.add(className);

        // Yan fonksiyonlar
        this.closeOffcanvasOnBreakpointChange(className);
        this.updateHeaderOffset();
    }

    refreshStickyElements() {
        const $sticky = $(".stick-top");
        if ($sticky.length && $.fn.hcSticky) {
            $sticky.hcSticky('update', {
                top: typeof stickyOptions !== "undefined" ? 0 : 0 // Burayı kendi mantığına göre bağlarsın abi
            });
        }
    }

    /*handleOffcanvasTogglerVisibility() {
        // 1. Senin projedeki geçerli Bootstrap/Custom breakpoint'lerin
        const validBreakpoints = ['sm', 'md', 'lg', 'xl', 'xxl', 'xxxl'];

        // 2. İçinde "offcanvas-" geçen tüm elementleri bul
        const offcanvases = document.querySelectorAll('[class*="offcanvas-"]');

        offcanvases.forEach(off => {
            const classes = Array.from(off.classList);
            let foundBreakpoint = null;

            // 3. Sadece bizim listemizde olan gerçek bir breakpoint class'ı var mı kontrol et
            // Örn: 'offcanvas-xxxl' -> 'xxxl' (valid mi? Evet)
            classes.forEach(cls => {
                const part = cls.replace('offcanvas-', '');
                if (validBreakpoints.includes(part)) {
                    foundBreakpoint = part;
                }
            });

            // 4. Eğer gerçek bir breakpoint (responsive yapısı) bulduysak toggler'ları işle
            if (foundBreakpoint) {
                const offId = off.id;
                const togglers = document.querySelectorAll(`[data-bs-toggle="offcanvas"][data-bs-target="#${offId}"], [data-bs-toggle="offcanvas"][href="#${offId}"]`);

                togglers.forEach(btn => {
                    // Sadece bulduğumuz breakpoint için gizleme class'ını ekle
                    // Örn: d-xxxl-none
                    btn.classList.add(`d-${foundBreakpoint}-none`);
                });
            }
        });
    }*/

    closeOffcanvasOnBreakpointChange(currentBreakpoint) {
        const openOffcanvas = document.querySelector('.offcanvas.show');
        if (!openOffcanvas) return;

        const bpSuffix = currentBreakpoint.replace('size-', '');
        if (openOffcanvas.classList.contains(`offcanvas-${bpSuffix}`)) {
            const instance = typeof bootstrap !== "undefined" ? bootstrap.Offcanvas.getInstance(openOffcanvas) : null;
            if (instance) instance.hide();
            else {
                openOffcanvas.classList.remove('show');
                document.querySelector('.offcanvas-backdrop')?.remove();
                this.body.style.overflow = '';
            }
        }
    }

    updateHeaderOffset() {
        if (!this.header) return;
        requestAnimationFrame(() => {
            const h = this.header.offsetHeight || 0;
            const offset = window.innerHeight - h;
            this.header.setAttribute("data-affix-offset", Math.round(offset));
            this.body.style.setProperty('--header-height', h + 'px');
        });
    }
}

// Başlat
window.ResponsiveManagerInstance = new ResponsiveManager();

var root = {

    //options: {},

    lang: document.getElementsByTagName("html")[0].getAttribute("lang"),

    host: location.protocol + "//" + window.location.hostname + (window.location.port > 80 ? ":" + window.location.port + "/" : "/") + (!IsBlank(window.location.pathname) ? window.location.pathname.split("/")[1] + "/" : ""),

    get_host: function(){
      return location.protocol + "//" + window.location.hostname + 
        (window.location.port && window.location.port != 80 && window.location.port != 443 ? ":" + window.location.port : "") + "/";
    },

    hash: window.location.hash,

    Date : Date.now,

    /*is_home: function() {
        return this.classes.hasClass(document.body, "home")
    },

    logged: function() {
        return this.classes.hasClass(document.body, "logged")
    },*/

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

            /*arr['hero-height-xxxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxxl').trim());
            arr['hero-height-xxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxl').trim());
            arr['hero-height-xl'] = parseFloat(obj.getPropertyValue('--hero-height-xl').trim());
            arr['hero-height-lg'] = parseFloat(obj.getPropertyValue('--hero-height-lg').trim());
            arr['hero-height-md'] = parseFloat(obj.getPropertyValue('--hero-height-md').trim());
            arr['hero-height-sm'] = parseFloat(obj.getPropertyValue('--hero-height-sm').trim());
            arr['hero-height-xs'] = parseFloat(obj.getPropertyValue('--hero-height-xs').trim());*/

            arr['hero-height'] = parseFloat(obj.getPropertyValue('--hero-height').trim());

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

        /*disable_contextmenu: function() {
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        },*/

        size_compare : function(a,b){
            var sizes = ["xs", "sm", "md", "lg", "xl", "xxl", "xxxl"];
            return (sizes.indexOf(a) > sizes.indexOf(b));
        },

        /*size: function() {
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
        },*/
        size: function() {
            const bodyObj = window.document.documentElement;
            const match = bodyObj.className.match(/size-([a-z0-9]+)/);
            return match ? match[1] : "xxxl"; // match[1] 'xxxl', 'lg' vb. döner
        },

        /*closeOffcanvas: function($breakpoint){
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
        },*/
    },

    ui: {

        /*reloadImage: function(img) {
            if (IsBlank(img.attr("data-src"))) {
                img.attr("data-src", img.attr("src")).addClass("fade");
            }
            var src = img.attr("data-src");
            var new_src = src + "?rnd=" + Math.random();
            img.removeClass("in").attr("src", "").attr("src", new_src).on("load", function() {
                $(this).addClass("in");
            });
        },*/

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

        /*navigation: function() {

            var menu = $('#navigation');
            if (menu.find(".link-onepage-home").length > 0) {
                menu.find(".link-onepage-home a").attr("href", "#home");
            }

            menu.on("click", ".dropdown-toggle", function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var $parent = $this.parent(".dropdown"); // Parent'ın .dropdown olduğundan emin olalım
                
                // Menü içindeki diğer açık olanları bul ama tıkladığımızı hariç tut
                var $openOthers = menu.find(".dropdown.open").not($parent);

                // 1. Diğerlerini temizle
                if ($openOthers.length > 0) {
                    $openOthers.removeClass("open");
                    $openOthers.find("a").removeClass("has-submenu highlighted");
                    $openOthers.find("ul.dropdown-menu").attr("aria-hidden", true).attr("aria-expanded", false).hide();
                }

                // 2. Tıklananı Toggle et
                if ($parent.hasClass("open")) {
                    $parent.removeClass("open");
                    $this.removeClass("has-submenu highlighted");
                    $parent.find("ul.dropdown-menu").attr("aria-hidden", true).attr("aria-expanded", false).hide();
                } else {
                    $parent.addClass("open");
                    $this.addClass("has-submenu highlighted");
                    $parent.find("ul.dropdown-menu").attr("aria-hidden", false).attr("aria-expanded", true).show();
                }
            });

        },*/

        scroll_dedect: function(up, down) {
            const self = this;
            const body = document.body;
            const header = document.querySelector("header#header");
            const header_hide_enabled = body.classList.contains("header-hide-on-scroll");
            
            let lastScrollPos = window.pageYOffset || document.documentElement.scrollTop;
            let ticking = false;

            body.classList.add("scroll-dedect");

            const handleSticky = () => {
                // Sadece sticky-top, sticky-bottom ve responsive olanları seç
                const stickies = document.querySelectorAll('.sticky-top, .sticky-bottom, [class*="sticky-"]');
                const currentSize = root.browser.size(); // Mevcut breakpoint (örn: "md")

                // Breakpoint hiyerarşisi (Büyükten küçüğe)
                const bpOrder = ['xxxl', 'xxl', 'xl', 'lg', 'md', 'sm', 'xs'];

                stickies.forEach(el => {
                    // --- 1. RESPONSIVE KONTROLÜ ---
                    // Sınıf listesinde "sticky-lg" gibi bir sınıf var mı bakıyoruz
                    const responsiveClass = Array.from(el.classList).find(cls => cls.startsWith('sticky-') && cls !== 'sticky-top' && cls !== 'sticky-bottom');
                    
                    if (responsiveClass) {
                        const targetBp = responsiveClass.split('-')[1]; // Sınıftan gelen (örn: "lg")
                        
                        // Mevcut ekran boyutu, hedef breakpoint'in altında mı (veya eşit mi)?
                        // örn: targetBp "lg" ise, currentSize "lg", "md", "sm" veya "xs" olmalı.
                        const isApplicable = bpOrder.indexOf(currentSize) >= bpOrder.indexOf(targetBp);

                        if (!isApplicable) {
                            el.classList.remove("sticked");
                            return; // Bu ekran boyutunda sticky değil, hesaplamaya gerek yok.
                        }
                    }

                    // --- 2. HESAPLAMA MANTIĞI (Aynı Kalıyor) ---
                    const rect = el.getBoundingClientRect();
                    const style = window.getComputedStyle(el);

                    // Sticky-Top Kontrolü
                    if (style.top !== 'auto') {
                        const topLimit = parseInt(style.top);
                        const parent = el.closest('.sticky-top-parent');
                        let isSticked = false;

                        if (parent) {
                            const parentRect = parent.getBoundingClientRect();
                            isSticked = Math.round(rect.top) === topLimit && rect.top > parentRect.top;
                        } else {
                            isSticked = Math.round(rect.top) === topLimit;
                        }
                        el.classList.toggle("sticked", isSticked);
                    }

                    // Sticky-Bottom Kontrolü
                    if (style.bottom !== 'auto') {
                        const bottomLimit = parseInt(style.bottom);
                        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                        const parent = el.closest('.sticky-bottom-parent');
                        let isSticked = false;

                        if (parent) {
                            const parentRect = parent.getBoundingClientRect();
                            isSticked = Math.round(rect.bottom) === (viewportHeight - bottomLimit) && rect.bottom < parentRect.bottom;
                        } else {
                            isSticked = Math.round(rect.bottom) === (viewportHeight - bottomLimit);
                        }
                        el.classList.toggle("sticked", isSticked);
                    }
                });
            };

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        const currentPos = window.pageYOffset || document.documentElement.scrollTop;

                        handleSticky();

                        // Yukarı/Aşağı scroll mantığı
                        if (currentPos < lastScrollPos) {
                            body.classList.remove("header-hide", "scroll-down");
                            body.classList.add("scroll-up");
                            if (typeof up === "function") up(currentPos);
                            if (currentPos <= 0) body.classList.remove("scroll-up");
                        } else if (currentPos > lastScrollPos && currentPos > 0) {
                            body.classList.remove("scroll-up");
                            body.classList.add("scroll-down");
                            if (currentPos > (header ? header.offsetHeight : 0) && header_hide_enabled) {
                                body.classList.add("header-hide");
                            }
                            if (typeof down === "function") down(currentPos);
                        }

                        lastScrollPos = currentPos;
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });

            handleSticky();
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

        /*scroll_to: function($hash, $animate = true, $outside = false, $callback = null) {
            //const self = this;
            
            // 1. Girdi Kontrolü ve ID Üretimi
            if (typeof $hash === "object") {
                if (IsBlank($hash.attr("id"))) {
                    $hash.attr("id", "sc-" + Math.random().toString(36).substr(2, 5));
                }
                $hash = "#" + $hash.attr("id");
            }

            if (IsBlank($hash)) return false;

            const $target = $($hash);
            if ($target.length === 0) return false;

            // 2. Durum Belirleme
            let _history = !$target.hasClass("tab-pane");
            const header = $("header#header");
            const isNavbarOpen = $(".navbar-collapse").hasClass("show");
            const isOffcanvasOpen = $(".offcanvas.show").length > 0;

            // 3. Bootstrap Bileşenlerini Tetikle (Show/Hide işlemleri)
            // Offcanvas açıksa kapat
            if (isOffcanvasOpen) {
                $(".offcanvas.show").offcanvas("hide");
            }

            // Collapse ise aç
            if ($target.hasClass("collapse") && !$target.hasClass("show")) {
                $target.collapse("show");
            }

            // Tab ise aktifleştir
            if ($target.hasClass("tab-pane") && !$target.hasClass("active")) {
                $("a[href='" + $hash + "']").trigger("click");
            }

            // 4. Pozisyon Hesaplama (Calculation Engine)
            const calculatePosition = () => {
                let posY = $target.offset().top;
                const headerHeight = root.get_css_var("header-height") || 0;
                const headerHeightAffix = root.get_css_var("header-height-affix") || 0;
                const currentSize = root.browser.size();

                // Header yükseklik düşümü
                if (header.hasClass("affix") || header.hasClass("fixed-top")) {
                    posY -= (posY >= headerHeight) ? headerHeight : headerHeightAffix;
                } else if (!root.browser.size_compare(currentSize, "md")) {
                    posY -= headerHeightAffix;
                }

                // Sticky elemanları hesapla (Loop yerine tek seferde)
                $(".stick-top.sticky, .sticky-top").each(function() {
                    if ($(this).is(":visible")) {
                        posY -= $(this).outerHeight(true);
                    }
                });

                return posY;
            };

            // 5. Kaydırma Fonksiyonu (Execution)
            const runScroll = (finalY) => {
                const duration = $animate ? 600 : 0;
                
                $("html, body").stop().animate({
                    scrollTop: Math.max(0, finalY) // Negatif değerleri engelle
                }, duration, function() {
                    // Callback ve History yönetimi
                    if (!_outside && _history && !IsBlank($hash)) {
                        // history.pushState(null, null, $hash); // İsteğe bağlı açılabilir
                    }
                    if (typeof $callback === "function") $callback();
                });
            };

            // 6. Navbar Kapalıysa Direkt Kaydır, Açıksa Kapanmasını Bekle
            if (isNavbarOpen) {
                $(".navbar-collapse").collapse("hide").one('hidden.bs.collapse', function() {
                    runScroll(calculatePosition());
                });
            } else {
                // Render gecikmelerine karşı küçük bir timeout (özellikle collapse açılıyorsa)
                setTimeout(() => runScroll(calculatePosition()), $target.hasClass("collapse") ? 350 : 0);
            }

            return false;
        },*/

        scroll_to: function($hash, $animate = true, $outside = false, $callback = null) {
            // 1. Girdi Kontrolü ve Hedef Saptama
            if (typeof $hash === "object") {
                if (!$hash.attr("id")) {
                    $hash.attr("id", "sc-" + Math.random().toString(36).substr(2, 5));
                }
                $hash = "#" + $hash.attr("id");
            }
            if (!$hash || $hash === "#") return false;

            const targetEl = document.querySelector($hash);
            if (!targetEl) return false;

            // 2. Bootstrap Bileşenlerini Yönet (Hızlı Kapatma)
            // bootstrap.Offcanvas.getInstance gibi native metodlar daha hızlıdır
            $('.offcanvas.show').each(function() {
                const instance = bootstrap.Offcanvas.getInstance(this);
                if (instance) instance.hide();
            });

            if (targetEl.classList.contains("collapse") && !targetEl.classList.contains("show")) {
                $(targetEl).collapse("show");
            }

            if (targetEl.classList.contains("tab-pane") && !targetEl.classList.contains("active")) {
                document.querySelector(`a[href='${$hash}']`)?.click();
            }

            // 3. Ofset Hesaplama Motoru (Optimize edilmiş)
            const getOffset = () => {
                let offset = 0;
                const header = document.querySelector("header#header");
                const hHeight = root.get_css_var("header-height") || 0;
                const hAffix = root.get_css_var("header-height-affix") || 0;

                if (header?.classList.contains("affix") || header?.classList.contains("fixed-top")) {
                    offset = hHeight > 0 ? hHeight : hAffix;
                }

                // Sticky elemanları tek seferde topla
                document.querySelectorAll(".stick-top.sticky, .sticky-top").forEach(el => {
                    if (el.offsetWidth > 0 && el.offsetHeight > 0) {
                        offset += el.offsetHeight;
                    }
                });
                return offset;
            };

            // 4. İcraat (Lenis varsa Lenis, yoksa Native Scroll)
            const performScroll = () => {
                const finalOffset = getOffset();
                
                if (window.lenis && $animate) {
                    // LENIS KULLANIMI (En performanslı ve akıcı yol)
                    window.lenis.scrollTo(targetEl, {
                        offset: -finalOffset,
                        duration: 1.2,
                        onComplete: () => {
                            if (typeof $callback === "function") $callback();
                        }
                    });
                } else {
                    // NATIVE MODERN SCROLL (jQuery'den daha hızlı)
                    const top = targetEl.getBoundingClientRect().top + window.pageYOffset - finalOffset;
                    window.scrollTo({
                        top: top,
                        behavior: $animate ? 'smooth' : 'auto'
                    });
                    if (typeof $callback === "function") $callback();
                }
            };

            // 5. Navbar Kapanmasını Bekle ve Tetikle
            const navbar = document.querySelector(".navbar-collapse.show");
            if (navbar) {
                $(navbar).collapse("hide").one('hidden.bs.collapse', performScroll);
            } else {
                // Collapse açılıyorsa DOM'un hesaplanması için minik bir bekleme
                setTimeout(performScroll, targetEl.classList.contains("collapse") ? 300 : 10);
            }

            return false;
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

        /*resizing : function(){
            let timer;
            function start() { $("body").addClass("resizing"); }
            function stop() { $("body").removeClass("resizing"); }
            $(window).on("resize", function(){
                start();
                clearTimeout(timer);
                timer = setTimeout(stop, 250);
            });
        },*/
        resizing: function() {
            let timer;
            let lastWidth = window.innerWidth;
            const body = document.body;

            // Pasif dinleyici ile en yüksek performans
            window.addEventListener("resize", () => {
                const currentWidth = window.innerWidth;

                // Sadece yatayda bir değişim varsa (Ekran dönmesi veya browser daraltma)
                if (currentWidth !== lastWidth) {
                    // Flag mantığı: Zaten class varsa tekrar ekleme yapmaz, CPU yemez
                    if (!body.classList.contains("resizing")) {
                        body.classList.add("resizing");
                    }
                    
                    lastWidth = currentWidth;

                    // Debounce: Kullanıcı boyutlandırmayı durdurduktan 250ms sonra class'ı kaldır
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        body.classList.remove("resizing");
                    }, 250);
                }
            }, { passive: true });
        },

        /*tree_menu : function(){
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
        },*/

        scrollspy: function() {
            // 1. Menüyü ve içindeki linkleri bul
            const spyMenu = document.querySelector('.nav-scrollspy');
            if (!spyMenu) return; // Menü yoksa yorma kendini

            const navLinks = spyMenu.querySelectorAll('.nav-link');
            const sections = [];

            // 2. Linklerin hedeflediği bölümleri (ID'leri) tespit et
            navLinks.forEach(link => {
                const targetId = link.getAttribute('href');
                if (targetId && targetId.startsWith('#')) {
                    const section = document.querySelector(targetId);
                    if (section) sections.push(section);
                }
            });

            // 3. Observer Ayarları (Hassasiyet)
            const observerOptions = {
                root: null,
                // Ekranın üstünden %20, altından %70 pay bırakıyoruz ki 
                // eleman tam ortaya/tepeye gelince "aktif" olsun.
                rootMargin: '-20% 0px -70% 0px',
                threshold: 0
            };

            const spyObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    // Eğer bölüm görüş alanına girdiyse
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        
                        // Menüdeki tüm aktifleri temizle, ilgili olanı yak
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, observerOptions);

            // 4. Tüm bölümleri izlemeye başla
            sections.forEach(section => spyObserver.observe(section));
        }

    },

    /*form: {
        init: function() {
            $('.btn-submit').attr("disabled", false).removeClass("processing");
        }
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
    },*/

    get_location: function($obj) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        var pos = {
                            lat: position.coords.latitude,
                            lon: position.coords.longitude
                        };
                        // Güvenli kontrol için yardımcı değişken
                        var hasProp = Object.prototype.hasOwnProperty;

                        if ($obj && hasProp.call($obj, "callback")) {
                            var obj = {
                                pos: pos,
                                status: true
                            };

                            // Harita objesi var mı?
                            if (hasProp.call($obj, "map")) {
                                obj["map"] = $obj.map;
                            }

                            // Bitiş noktası var mı?
                            if (hasProp.call($obj, "end")) {
                                obj["end"] = $obj.end;
                            }

                            // Callback fonksiyonunu güvenli bir şekilde tetikle
                            if (typeof $obj.callback === "function") {
                                $obj.callback(obj);
                            }
                        } else {
                            // Not: Eğer bu kod bir async (getCurrentPosition vb.) içindeyse
                            // bu return değeri ana çağıran yere ulaşmayacaktır.
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
                        // jQuery 4.0 ve Modern JS güvenli kontrolü
                        if ($obj && Object.prototype.hasOwnProperty.call($obj, "callback")) {
                            
                            // Güvenlik: callback'in gerçekten bir fonksiyon olduğundan emin olalım
                            if (typeof $obj.callback === "function") {
                                $obj.callback({ status: false });
                            }
                            
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
                if ($obj && Object.prototype.hasOwnProperty.call($obj, "callback")) {
                    if (typeof $obj.callback === "function") {
                        $obj.callback(false);
                    }
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

    init: function() {
        //root.options = options;
        root.browser.device();
        $(document).ready(function() {
            //root.ui.navigation();
            root.get_css_vars();
            root.ui.scroll_top();
            root.ui.prev_next();
            root.ui.viewport();
            root.ui.resizing();
            //root.form.init();
            //root.ui.tree_menu();
            root.ui.scrollspy();

            //root.responsive.table();
            //root.responsive.tab();

            function onResize() {
                // Objenin varlığını ve içinin dolu olduğunu kontrol edelim
                if (root && root.on_resize) {
                    for (var func in root.on_resize) {
                        // jQuery 4.0 ve Modern JS güvenli kontrolü
                        if (Object.prototype.hasOwnProperty.call(root.on_resize, func)) {
                            // Güvenlik: Çağırılacak olanın gerçekten bir fonksiyon olduğunu teyit edelim
                            if (typeof root.on_resize[func] === "function") {
                                root.on_resize[func]();
                            }
                        }
                    }
                }
            }

        });
    }
}

if (typeof $.isFunction !== "function") {
    $.isFunction = function(obj) {
        return typeof obj === "function";
    };
}

if (typeof $.parseJSON !== 'function') {
    $.parseJSON = function(data) {
        return JSON.parse(data);
    };
}

/*
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
                response = JSON.parse(response);
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
                response = JSON.parse(response);
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
                response = JSON.parse(response);
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
*/

