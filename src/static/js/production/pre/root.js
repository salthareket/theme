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
        _hasUserLeft = false;
        document.body.classList.remove("loading", "loading-process");
        // bfcache reload kaldırıldı — gereksiz extra request yaratıyordu
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

// ─── Dinamik icerik CSS'i (modal / offcanvas / dropdown) — lazy, TEK KEZ ──────
// Sayfa CSS'i used-CSS ile purge edilir; AJAX ile gelen modal/offcanvas/dropdown
// icerigindeki class'lar (btn-primary vb.) sayfa HTML'inde olmadigindan purge'e
// takilir. Cozum: TAM main-combined.css'i, kullanici bir modal/offcanvas/dropdown
// TETIKLEYICISINE tikladigi anda (panel ACILMADAN ONCE) yukle. Tiklama ile acilis
// arasindaki sure (bootstrap animasyonu + AJAX) CSS'i orter. Idempotent — 1 kez.
(function() {
  var isRtl    = (document.documentElement.getAttribute('dir') === 'rtl') ||
                 (document.body && document.body.classList.contains('rtl'));
  var baseUrl  = (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.theme_url)
                 ? ajax_request_vars.theme_url : '';
  var ver      = (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.css_version)
                 ? ajax_request_vars.css_version : '1.0.0';
  var FULL_CSS_URL = baseUrl + '/static/css/main-combined' + (isRtl ? '-rtl' : '') + '.css?v=' + ver;
  var cssLoaded = false;

  function loadDynamicCss() {
    if (cssLoaded || !baseUrl) return;
    cssLoaded = true;
    var link = document.createElement('link');
    link.rel  = 'stylesheet';
    link.href = FULL_CSS_URL;
    link.setAttribute('data-sh-dynamic-css', '1');
    link.onload = function () {
      if (typeof debugJS === 'function') debugJS('%c[main-combined.css yuklendi]', 'color: #3cfa8c');
    };
    document.head.appendChild(link);
  }
  window.loadDynamicCss = loadDynamicCss;

  // Tetikleyiciler: modal/offcanvas/dropdown acan HER kontrol (acilmadan ONCE yakala).
  //  - data-ajax-method  → form_modal, page_modal, template_modal, ... (eski)
  //  - data-salt-offcanvas → source offcanvas (cart/favorites/reviews/reaction...)
  //  - data-bs-toggle="modal|offcanvas|dropdown" → bootstrap native toggler'lar
  //  - data-bs-source-modal / data-source → source modal'lari
  var SELECTOR = [
    '[data-ajax-method]',
    '[data-salt-offcanvas]',
    '[data-bs-toggle="modal"]',
    '[data-bs-toggle="offcanvas"]',
    '[data-bs-toggle="dropdown"]',
    '[data-bs-source-modal]',
    '.dropdown-notifications [data-bs-toggle]'
  ].join(',');

  document.addEventListener('click', function(e) {
    if (cssLoaded) return;
    if (e.target.closest(SELECTOR)) loadDynamicCss();
  }, true); // capture: acilis handler'larindan ONCE calissin
})();






document.addEventListener('click', function (e) {
    const link = e.target.closest('a');

    // Temel kontroller: Link yoksa, href yoksa veya yeni sekmede açılıyorsa siktir et
    if (!link || !link.href || e.metaKey || e.ctrlKey || e.shiftKey) return;

    // 1. Standart İstisnalar (Harici link, mail, tel vb.)
    const url = new URL(link.href);
    const isExternal = url.hostname !== window.location.hostname;
    const isTargetBlank = link.target === '_blank';
    const isAction = link.href.startsWith('mailto:') || link.href.startsWith('tel:') || link.hasAttribute('download');

    if (isExternal || isTargetBlank || isAction) return;

    if (link.getAttribute('href').startsWith('#') || link.href.split('#')[0] === window.location.href.split('#')[0]) {
        return;
    }

    setTimeout(() => {
        if (e.defaultPrevented) {
            log('Başka bir script bu linki yakaladı, loading iptal.');
            return;
        }
        document.body.classList.add('loading');
    }, 0); 
}, false);






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
            // Error durumunda da obj.done çağır — caller kendi error handling'ini yapabilsin
            if (obj.done) await obj.done(result, obj.vars, obj.form, obj.objs);
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
        const forceMultipart = this.form.length > 0 && this.form[0] && this.form[0].dataset && this.form[0].dataset.ajaxMultipart === 'true';
        if (hasFiles || forceMultipart) {
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

        if (!await this.ensureMethodsLoaded()) {
            console.error('[ajax_query] ensureMethodsLoaded FAILED for', obj.method);
            return;
        }
        
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
                const proceed = await hooks.before(obj, obj.vars, obj.form, obj.objs);
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
            if (error.name === 'AbortError') {
                console.warn('[ajax_query] ABORTED (previous request cancelled):', obj.method);
            } else {
                console.error('[ajax_query] ERROR in', obj.method, ':', error.message, error.stack);
                // 500 hatası — WooCommerce veya servis devre dışı olabilir
                // Açık modal/offcanvas'ı kapat, kullanıcıya bilgi ver
                if (error.message && error.message.indexOf('500') !== -1) {
                    var _serviceMsg = '<p style="text-align:center;padding:30px 20px;color:#999;font-size:13px">⚠️ This service is currently unavailable.</p>';
                    // Açık offcanvas load-container'larına mesaj yaz
                    document.querySelectorAll('.offcanvas.show .load-container.loading-process').forEach(function(el) {
                        el.classList.remove('loading-process');
                        el.innerHTML = _serviceMsg;
                    });
                    // Açık modal load-container'larına mesaj yaz
                    document.querySelectorAll('.modal.show .load-container.loading-process').forEach(function(el) {
                        el.classList.remove('loading-process');
                        el.innerHTML = _serviceMsg;
                    });
                    // Toast göster
                    if (typeof window.shGsToast === 'function') {
                        window.shGsToast('This service is currently unavailable.', false);
                    }
                }
            }
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

        // bottom-start affix mode icin updateHeaderOffset'i hemen de calistir
        // (load event'tan once data-affix-offset yazilmasi gerekiyor)
        this.updateHeaderOffset();
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

            // data-affix-offset hesapla:
            // fixed-bottom-start: body.scrollHeight - header.offsetHeight
            //   = header'in sayfadaki absolute top konumu (jQuery: $body.height() - $header.outerHeight())
            //   ScrollPosStyler scroll bu degeri gecince "affix" ekler → header tepede sabitlenir
            // Diger modlar: header.offsetHeight (kaydirinca affix olsun)
            if (this.header.classList.contains('fixed-bottom-start') ||
                (this.header.classList.contains('affix') && this.header.dataset.affixMode === 'viewport')) {
                // CSS: top: calc(100vh - header-height) — viewport bazli konum
                // Offset = viewport yuksekligi - header yuksekligi
                const offset = Math.max(1, window.innerHeight - h);
                this.header.setAttribute("data-affix-offset", Math.round(offset));
            } else {
                this.header.setAttribute("data-affix-offset", Math.round(h));
            }

            this.body.style.setProperty('--header-height', h + 'px');

            // --header-height-affix: hide_on_affix row'larini toplam yukseklikten cikar.
            // Sadece hide_on_affix row'u varsa DOM ölçümünü kullan.
            // Yoksa theme styles root.css'deki değeri koru — JS override etmesin.
            let hiddenOnAffixH = 0;
            this.header.querySelectorAll('.header-row-hide-on-affix').forEach(row => {
                hiddenOnAffixH += row.offsetHeight || 0;
            });

            if (hiddenOnAffixH > 0) {
                // Hide-on-affix row var — DOM'dan hesapla
                const affixedH = Math.max(0, h - hiddenOnAffixH);
                this.body.style.setProperty('--header-height-affix', affixedH + 'px');
            }
            // Hide-on-affix row yoksa --header-height-affix'e dokunma:
            // theme styles root.css'deki değer geçerli kalır (örn. 70px)
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
            arr['header-height'] = parseFloat(obj.getPropertyValue('--header-height').trim());
            arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());
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

        size: function() {
            const bodyObj = window.document.documentElement;
            const match = bodyObj.className.match(/size-([a-z0-9]+)/);
            return match ? match[1] : "xxxl"; // match[1] 'xxxl', 'lg' vb. döner
        },

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

                    if (obj.inViewport()) {
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


// ─── CSP Violation Listener ──────────────────────────────────────────────────
// root.js'de çünkü sayfa en başında synchronous yükleniyor — violation'lar
// sayfa yüklenirken oluşur, measure-lcp.js gibi dinamik inject'ten önce.
// ajax_request_vars henüz set olmamış olabilir — sadece gönderme sırasında kontrol et.
(function() {
    var _violations = [];
    var _sent       = false;
    var _timer      = null;

    document.addEventListener('securitypolicyviolation', function(e) {
        var blocked   = e.blockedURI   || '';
        var directive = (e.violatedDirective || e.effectiveDirective || '').split(' ')[0];

        console.log('[CSP] İhlal:', directive, blocked);

        // keyword'leri atla
        if (!blocked || blocked === 'data' || blocked === 'blob' ||
            blocked === 'self' || blocked === 'inline' || blocked === 'eval') return;

        var domain = blocked;
        try { domain = new URL(blocked).origin; } catch(ex) {}

        var key = directive + '|' + domain;
        for (var i = 0; i < _violations.length; i++) {
            if (_violations[i]._key === key) return; // dup
        }
        _violations.push({ directive: directive, domain: domain, page: window.location.pathname, _key: key });

        clearTimeout(_timer);
        _timer = setTimeout(_send, 3000);
    });

    function _send() {
        if (_sent || !_violations.length) return;
        // ajax_request_vars bu noktada hazır (site_config AJAX dönmüş)
        if (!window.ajax_request_vars || !ajax_request_vars.url_admin) return;
        if (!ajax_request_vars.csp_active) return;

        _sent = true;
        console.log('[CSP] Gönderiliyor:', _violations.length, 'ihlal');

        var clean = _violations.map(function(v) {
            return { directive: v.directive, domain: v.domain, page: v.page };
        });

        fetch(ajax_request_vars.url_admin, {
            method:    'POST',
            headers:   { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:      new URLSearchParams({
                action:     'sh_save_csp_violations',
                nonce:      ajax_request_vars.ajax_nonce || '',
                violations: JSON.stringify(clean)
            }).toString(),
            keepalive: true
        }).catch(function() {});
    }

    window.addEventListener('pagehide',     _send);
    window.addEventListener('beforeunload', _send);
})();