/**
 * SaltOffcanvas — Bootstrap Offcanvas Yöneticisi
 *
 * Bootbox mantığında, Bootstrap offcanvas'ını wrap eden global API.
 * jQuery bağımlılığı yok — saf vanilla JS.
 *
 * ─── API ──────────────────────────────────────────────────────────────────
 *
 *   // ID ile kayıtlı offcanvas aç:
 *   SaltOffcanvas.open('cart');
 *
 *   // Inline içerikle aç:
 *   SaltOffcanvas.open({
 *       body:       '<p>İçerik</p>',
 *       title:      'Başlık',
 *       position:   'end',          // start | end | top | bottom
 *       visibility: 'offcanvas',    // offcanvas | offcanvas-sm | offcanvas-md | ...
 *       backdrop:   false,          // true | false | 'static'
 *       fullscreen: false,
 *       scrollable: false,
 *       className:  '',             // ekstra CSS class
 *       onShow:     null,
 *       onShown:    null,
 *       onHide:     null,
 *       onHidden:   null,
 *   });
 *
 *   // AJAX (method) ile aç:
 *   SaltOffcanvas.open({
 *       method: 'get_cart',   // TurboAPI method adı
 *       cached: true,         // ilk fetch sonrası DOM'u koru
 *       ...
 *   });
 *
 *   // Register (PHP tarafından sayfa yükünde çağrılır):
 *   SaltOffcanvas.register('cart', { method: 'get_cart', position: 'end', cached: true });
 *
 *   // Kapat:
 *   SaltOffcanvas.close();           // aktif olanı kapat
 *   SaltOffcanvas.close('cart');     // ID ile kapat
 *
 * ─── HTML TRIGGER ─────────────────────────────────────────────────────────
 *
 *   <button data-salt-offcanvas="cart">Sepet</button>
 *   <a href="#" data-salt-offcanvas="menu">Menü</a>
 *
 * @version 1.0.0
 */
(function () {
    'use strict';

    // ─── Registry ─────────────────────────────────────────────────────────
    // { id: { options, bsInstance, el, contentLoaded } }
    var _registry = {};

    // Sayfa yükünde PHP'den inject edilen offcanvas tanımları
    var _phpRegistry = window.shOffcanvasRegistry || {};

    // TurboAPI URL — PHP tarafından inject edilir: home_url('/api/')
    var _apiUrl = window.shOffcanvasApiUrl || (window.location.origin + '/api/');

    // Nonce — site_config'den al (her sayfada mevcut)
    var _nonce  = (window.site_config && window.site_config.ajax && window.site_config.ajax.ajax_nonce)
               || '';

    // ─── Defaults ─────────────────────────────────────────────────────────
    var _defaults = {
        body:       '',
        title:      '',
        position:   'end',
        visibility: 'offcanvas',
        backdrop:   false,
        fullscreen:  false,
        scrollable:  false,
        className:  '',
        method:     '',
        cached:     true,
        onShow:     null,
        onShown:    null,
        onHide:     null,
        onHidden:   null,
    };
    // ─── Helpers ──────────────────────────────────────────────────────────

    function _uid() {
        return 'soc-' + Math.random().toString(36).slice(2, 9);
    }

    function _merge() {
        var result = {};
        for (var i = 0; i < arguments.length; i++) {
            var obj = arguments[i];
            if (!obj) continue;
            for (var k in obj) {
                if (obj.hasOwnProperty(k)) result[k] = obj[k];
            }
        }
        return result;
    }

    // ─── DOM Builder ──────────────────────────────────────────────────────

    function _buildEl(id, opts) {
        var el = document.createElement('div');

        // visibility class (offcanvas, offcanvas-lg, vb.)
        var visClass = opts.visibility || 'offcanvas';
        // position class
        var posClass = 'salt-offcanvas offcanvas-' + (opts.position || 'end');

        el.className = [
            visClass,
            posClass,
            opts.fullscreen ? 'offcanvas-fullscreen' : '',
            opts.className  || '',
        ].filter(Boolean).join(' ');

        el.setAttribute('tabindex', '-1');
        el.setAttribute('id', id);
        el.setAttribute('aria-labelledby', id + '-label');

        if (opts.scrollable) {
            // body scroll açık — Bootstrap bsOptions ile yönetiliyor
        }

        // Header
        var headerHtml = '';
        if (opts.title) {
            headerHtml = '<div class="offcanvas-header">'
                + '<h5 class="offcanvas-title" id="' + id + '-label">' + opts.title + '</h5>'
                + '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>'
                + '</div>';
        }

        // Body — scrollable ise 'scrollable' class'i ekle. Source offcanvas'ta
        // scroll container'i (infinite scroll + SimpleScrollbar) .scrollable ile
        // secilir; class olmazsa scroll listener/scrollbar yanlis elemente (or.
        // tek bir li.source-item'a) baglaniyordu.
        var bodyClass = 'offcanvas-body' + (opts.scrollable ? ' scrollable' : '');
        var bodyHtml = '<div class="' + bodyClass + '">';
        if (opts.method) {
            // AJAX — önce spinner göster
            bodyHtml += '<div class="soc-loading d-flex justify-content-center align-items-center py-5">'
                + '<div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>'
                + '</div>';
        } else {
            bodyHtml += (opts.body || '');
        }
        bodyHtml += '</div>';

        // Footer slot — bos ve gizli baslar. Source offcanvas (cart vb.) AJAX
        // yaniti footer HTML dondururse main.js loadSourceContent bunu doldurup
        // d-none'i kaldirir (or. sepet toplami + checkout butonu). Icerik yoksa
        // gizli kalir. Eski statik print'te bu slot vardi; _buildEl'e (bootbox)
        // gecince kaybolmustu → cart footer'i gelmiyordu.
        var footerHtml = '<div class="offcanvas-footer d-none"></div>';

        el.innerHTML = headerHtml + bodyHtml + footerHtml;
        document.body.appendChild(el);
        return el;
    }

    // ─── AJAX Content Load ────────────────────────────────────────────────
    // Sadece dynamic offcanvas (method var) için çalışır.
    // Static offcanvas sayfaya önceden basılmıştır — buraya girmez.

    function _loadContent(entry) {
        var opts = entry.opts;
        var el   = entry.el;
        var body = el.querySelector('.offcanvas-body');
        if (!body) return;

        // İçerik kaynağı yoksa (ne method ne template) yükleme yapma
        var hasMethod   = !!opts.method;
        var hasTemplate = !hasMethod && !!opts.template;
        if (!hasMethod && !hasTemplate) return;

        // Cached ve daha önce yüklendiyse tekrar fetch yapma
        if (opts.cached && entry.contentLoaded) return;

        // Loading spinner
        body.classList.add('loading', 'loading-process');
        body.innerHTML = '<div class="d-flex justify-content-center align-items-center py-5">'
            + '<div class="spinner-border text-secondary" role="status">'
            + '<span class="visually-hidden">Yükleniyor...</span>'
            + '</div></div>';

        // İki kaynak:
        //  - method  → TurboAPI: POST /api/{method}
        //  - template → WP AJAX: sh_offcanvas_render (template post içeriğini render eder)
        var fetchPromise;
        if (hasMethod) {
            fetchPromise = fetch(_apiUrl + opts.method, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': _nonce },
                body:    JSON.stringify({ vars: {} }),
            }).then(function(r) { return r.json(); });
        } else {
            // Template dynamic render — admin-ajax sh_offcanvas_render
            // shOffcanvasAjaxUrl PHP'den admin_url() ile gelir → subdirectory (/salthub/)
            // kurulumlarda dogru path. origin+'/wp-admin/...' fallback'i subdir'de YANLIS
            // (404) oldugu icin en sona alindi.
            var ajaxUrl = window.shOffcanvasAjaxUrl
                       || (window.site_config && window.site_config.ajax && window.site_config.ajax.ajax_url)
                       || (window.ajaxurl)
                       || (window.location.origin + '/wp-admin/admin-ajax.php');
            var fd = new URLSearchParams();
            fd.append('action', 'sh_offcanvas_render');
            fd.append('post_id', opts.template);
            fetchPromise = fetch(ajaxUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    fd.toString(),
            }).then(function(r) { return r.json(); });
        }

        fetchPromise
        .then(function(res) {
            var html = '';
            if (res && res.html !== undefined)                        html = res.html;
            else if (res && res.data && res.data.html !== undefined)  html = res.data.html;
            else if (res && res.data && res.data.content !== undefined) html = res.data.content;

            // Response HTML'de offcanvas-body wrapper varsa → içeriğini al, wrapper'ı yazma
            // Aksi halde direkt yaz
            var finalHtml = html;
            if (html) {
                var $temp = document.createElement('div');
                $temp.innerHTML = html;
                var innerBody = $temp.querySelector('.offcanvas-body');
                if (innerBody) {
                    // Extra class'ları mevcut body'ye aktar
                    var extraClasses = innerBody.className.replace('offcanvas-body', '').trim();
                    if (extraClasses) {
                        extraClasses.split(' ').forEach(function(c) {
                            if (c) body.classList.add(c);
                        });
                    }
                    finalHtml = innerBody.innerHTML;
                }
                var innerFooter = $temp.querySelector('.offcanvas-footer');
                if (innerFooter) {
                    var existingFooter = el.querySelector('.offcanvas-footer');
                    if (existingFooter) {
                        existingFooter.innerHTML = innerFooter.innerHTML;
                        existingFooter.classList.remove('d-none');
                    }
                }
            }

            // Loading class'larini kaldir (baslangicta loading + loading-process eklendi)
            body.classList.remove('soc-loading', 'loading', 'loading-process');
            body.innerHTML = finalHtml || '';
            entry.contentLoaded = true;

            // Script'leri execute et
            body.querySelectorAll('script').forEach(function(s) {
                var ns = document.createElement('script');
                if (s.src) ns.src = s.src;
                else ns.textContent = s.textContent;
                document.head.appendChild(ns).parentNode.removeChild(ns);
            });

            el.dispatchEvent(new CustomEvent('soc:loaded', { bubbles: true, detail: res }));
        })
        .catch(function() {
            body.classList.remove('soc-loading', 'loading', 'loading-process');
            body.innerHTML = '<p class="text-danger p-3">İçerik yüklenemedi.</p>';
        });
    }

    // ─── Open ─────────────────────────────────────────────────────────────

    function _open(idOrOpts) {
        var id, opts;

        if (typeof idOrOpts === 'string') {
            // ID ile aç — registry'den al
            id = idOrOpts;
            var phpDef = _phpRegistry[id] || {};
            opts = _merge(_defaults, phpDef);
        } else {
            // Options objesi ile aç
            opts = _merge(_defaults, idOrOpts);
            id = idOrOpts.id || _uid();
        }

        // Zaten açık mı?
        if (_registry[id] && _registry[id].bsInstance) {
            var existing = _registry[id];
            // Method varsa içeriği yenile (cached=false ise)
            if (opts.method && !opts.cached) {
                existing.contentLoaded = false;
                _loadContent(existing);
            }
            existing.bsInstance.show();
            return existing;
        }

        // render_mode'a göre static tespiti (DOM varlığına göre DEĞİL):
        //  - static            → içerik DOM'da hazır, AJAX yok.
        //  - dynamic + sidebar → DOM'da boş basılı olabilir; içerik AJAX ile yüklenir.
        //  - dynamic + normal  → DOM'da yok; _buildEl ile oluşturulur, AJAX ile yüklenir.
        var isStatic = (opts.render_mode === 'static');

        var el = document.getElementById(id);
        if (!el) {
            // DOM'da yok → oluştur (dynamic normal offcanvas)
            el = _buildEl(id, opts);
        }
        // DOM'da varsa (static ya da boş basılmış dynamic sidebar) aynen kullan.

        // Bootstrap Offcanvas options
        var bsOpts = {
            backdrop: opts.backdrop === 'static' ? 'static' : !!opts.backdrop,
            scroll:   !!opts.scrollable,
            keyboard: true,
        };

        // getOrCreateInstance — element icin zaten bir Bootstrap instance varsa onu kullan,
        // yoksa olustur. 'new' ile ikinci instance yaratmak "ilk tik acmiyor, ikinci aciyor"
        // sorununa yol aciyordu (cift instance / backdrop cakismasi).
        var bsInstance = bootstrap.Offcanvas.getOrCreateInstance( el, bsOpts );

        var entry = {
            id:            id,
            opts:          opts,
            el:            el,
            bsInstance:    bsInstance,
            isStatic:      isStatic,
            contentLoaded: isStatic, // static ise içerik zaten var
        };

        _registry[id] = entry;

        // Event'ler
        el.addEventListener('show.bs.offcanvas', function () {
            // Dynamic offcanvas ise içeriği yükle (method VEYA dynamic template).
            // Static ise (isStatic) içerik zaten DOM'da — yükleme yapma.
            if (!isStatic && (opts.method || opts.template)) _loadContent(entry);
            if (typeof opts.onShow === 'function') opts.onShow(el);
        });
        el.addEventListener('shown.bs.offcanvas', function () {
            if (typeof opts.onShown === 'function') opts.onShown(el);
        });
        el.addEventListener('hide.bs.offcanvas', function () {
            if (typeof opts.onHide === 'function') opts.onHide(el);
        });
        el.addEventListener('hidden.bs.offcanvas', function () {
            if (typeof opts.onHidden === 'function') opts.onHidden(el);
            // DOM'dan kaldırma kuralı:
            //  - static ise: asla kaldırma (SEO içeriği kalıcı).
            //  - sidebar ise: asla kaldırma (breakpoint üstünde tekrar görünecek).
            //  - dynamic normal offcanvas: cache yoksa kaldır (bir sonraki açılışta taze build).
            var keepInDom = isStatic || opts.is_sidebar;
            if (!keepInDom && !opts.cached) {
                bsInstance.dispose();
                el.parentNode && el.parentNode.removeChild(el);
                delete _registry[id];
            }
        });

        bsInstance.show();

        return entry;
    }

    // ─── Close ────────────────────────────────────────────────────────────

    function _close(id) {
        if (id) {
            var entry = _registry[id];
            if (entry && entry.bsInstance) entry.bsInstance.hide();
        } else {
            // Tümünü kapat
            for (var k in _registry) {
                if (_registry.hasOwnProperty(k) && _registry[k].bsInstance) {
                    _registry[k].bsInstance.hide();
                }
            }
        }
    }

    // ─── Register ─────────────────────────────────────────────────────────

    function _register(id, opts) {
        _phpRegistry[id] = _merge(_defaults, opts, { id: id });
    }

    // ─── Auto click handler ───────────────────────────────────────────────

    // CAPTURE aşamasında dinle (3. arg = true): bubble aşamasında bir parent
    // stopPropagation yapsa bile capture en üstte (document'ten aşağı) önce çalışır,
    // ilk tık kesin yakalanır. (Sorun: header'daki bir handler ilk tıkta event'i
    // yutuyordu → ilk tık açmıyor, ikinci açıyordu.)
    // Trigger element'inden offcanvas'i acar. Source offcanvas (registry'de
    // olmayan) icin opts trigger'in data-soc-* attribute'larindan okunur;
    // boylece _buildEl dogru position/visibility/backdrop/source class ile
    // <body>'ye olusturur. Registry'li (sh-oc-*) toggler'lar bu attribute'lari
    // tasimaz → _open(id) string yolu (registry'den opts).
    // NOT: Hem click handler hem lazy-load replay ayni fonksiyonu kullanir —
    // aksi halde replay string yolundan acip backdrop/position'i kaybediyordu.
    function _openFromTrigger(trigger) {
        if (!trigger) return;
        var id = trigger.getAttribute('data-salt-offcanvas');
        if (!id) return;
        if (trigger.hasAttribute('data-soc-position') || trigger.hasAttribute('data-soc-class')) {
            _open({
                id:         id,
                position:   trigger.getAttribute('data-soc-position') || 'end',
                visibility: trigger.getAttribute('data-soc-visibility') || 'offcanvas',
                className:  trigger.getAttribute('data-soc-class') || '',
                backdrop:   trigger.getAttribute('data-soc-backdrop') !== '0',
                scrollable: trigger.getAttribute('data-soc-scrollable') === '1',
                fullscreen: trigger.getAttribute('data-soc-fullscreen') === '1',
                method:     '',  // icerik loadSourceContent'ten gelir
            });
        } else {
            _open(id);
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-salt-offcanvas]');
        if (!trigger) return;
        e.preventDefault();
        _openFromTrigger(trigger);
    }, true);

    // ─── Lazy-load "ilk tık" telafisi ───────────────────────────────────────
    // Bu script tema tarafindan ILK ETKILESIMDE (lazy) yukleniyor. Yani scripti
    // yukleten ilk tik, listener henuz bagli olmadigi icin kayboluyordu
    // ("ilk tik acmiyor, ikinci aciyor"). Script yuklenir yuklenmez, o tikin
    // hedefledigi toggler'i tespit edip offcanvas'i aciyoruz (telafi).
    //
    // Tespit: tarayici <a>/<button> tiklaninca onu focuslar → document.activeElement.
    // Ayrica pointer'in uzerinde oldugu elementi de (:hover) yedek olarak kontrol et.
    (function _replayFirstInteraction() {
        try {
            var candidates = [];
            if (document.activeElement) candidates.push(document.activeElement);
            var hovered = document.querySelectorAll('[data-salt-offcanvas]:hover');
            for (var h = 0; h < hovered.length; h++) candidates.push(hovered[h]);

            for (var i = 0; i < candidates.length; i++) {
                var trg = candidates[i] && candidates[i].closest
                        ? candidates[i].closest('[data-salt-offcanvas]') : null;
                if (trg) {
                    // _openFromTrigger — data-soc-* opts'larini da okur (backdrop/
                    // position/source class). Eski hali _open(rid) string idi ve
                    // ilk tikta opts'lari kaybediyordu (backdrop gelmiyordu).
                    _openFromTrigger(trg);
                    break;
                }
            }
        } catch (e) { /* sessiz geç */ }
    })();

    // ─── Sidebar auto-load ──────────────────────────────────────────────────
    // Sidebar modu (visibility offcanvas-{bp}) breakpoint ÜSTÜNDE hep görünür ve
    // açılma eventi (show.bs.offcanvas) tetiklenmez. Bu yüzden dynamic/method
    // içerikli sidebar boş kalır. Sayfa yüklenince, DOM'da basılı boş sidebar'ların
    // içeriğini otomatik AJAX ile doldururuz.
    //
    // Not: Static sidebar (içerik zaten basılı) atlanır. Sadece dynamic/method + is_sidebar.

    function _bpMinWidth(vis) {
        // offcanvas-md → md breakpoint min genişliği. Bootstrap 5 default değerleri.
        var map = { sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 };
        var m = /offcanvas-(sm|md|lg|xl|xxl)/.exec(vis || '');
        return m ? (map[m[1]] || 0) : 0;
    }

    function _autoLoadSidebars() {
        for (var id in _phpRegistry) {
            if (!_phpRegistry.hasOwnProperty(id)) continue;
            var def = _phpRegistry[id];
            if (!def.is_sidebar) continue;                 // sadece sidebar
            if (def.render_mode === 'static') continue;     // static zaten dolu
            if (!def.method && !def.template) continue;     // yüklenecek kaynak yok

            var el = document.getElementById(id);
            if (!el) continue;                              // DOM'da basılı olmalı (boş sidebar)

            // Breakpoint üstünde miyiz? (sidebar görünür durumda mı)
            var minW = _bpMinWidth(def.visibility);
            var isSidebarVisible = window.innerWidth >= minW;
            if (!isSidebarVisible) continue;                // altındaysa offcanvas modu → açılınca yüklenir

            // Zaten yüklenmişse atla
            if (el.getAttribute('data-soc-loaded') === '1') continue;

            var opts  = _merge(_defaults, def);
            var entry = _registry[id] || {
                id: id, opts: opts, el: el,
                bsInstance: null, isStatic: false, contentLoaded: false,
            };
            _registry[id] = entry;
            el.setAttribute('data-soc-loaded', '1');
            _loadContent(entry);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _autoLoadSidebars);
    } else {
        _autoLoadSidebars();
    }
    // Breakpoint geçişlerinde (resize) altından üstüne geçince de yükle
    var _resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(_autoLoadSidebars, 200);
    });

    // ─── Back-button ──────────────────────────────────────────────────────
    // TODO: back tuşuyla offcanvas kapatma — henüz implement edilmedi.

    // ─── Public API ───────────────────────────────────────────────────────

    window.SaltOffcanvas = {
        open:     _open,
        close:    _close,
        register: _register,

        /** Kayıtlı tüm offcanvas tanımlarını döndür (debug için) */
        getRegistry: function () { return _registry; },
    };

})();
