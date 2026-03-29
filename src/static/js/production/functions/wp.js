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
function isLoadedJS($name, $load = false, $callback = null) {
    if (typeof isLoadedJS.cache === 'undefined') {
        isLoadedJS.cache = null;
        isLoadedJS.loading = false;
    }

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

    let check_required = (typeof required_js !== "undefined" && required_js.indexOf($name) > -1);
    let check_conditional = (typeof conditional_js !== "undefined" && conditional_js.indexOf($name) > -1);

    if (check_required || check_conditional) {
        if (typeof $callback === "function") $callback();
        return true;
    }

    if (!$load) return false;

    const processLibrary = (data) => {
        const libConfig = data[$name];
        if (!libConfig) return;

        if (libConfig.css) {
            const cssFiles = Array.isArray(libConfig.css) ? libConfig.css : [libConfig.css];
            cssFiles.forEach(file => { if(typeof loadCSS === "function") loadCSS(file); });
        }

        const jsFiles = libConfig.js ? (Array.isArray(libConfig.js) ? [...libConfig.js] : [libConfig.js]) : [];

        const loadJSChain = (list, finalAction) => {
            if (list.length === 0) { finalAction(); return; }
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
                    try { new Function(item.callback_code)(); }
                    catch (e) { console.error("Callback Hatası:", e); }
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
            if (libConfig.js_init) { loadJS(libConfig.js_init).then(finalize); }
            else { finalize(); }
        });
    };

    if (isLoadedJS.cache) {
        processLibrary(isLoadedJS.cache);
    } else {
        if (isLoadedJS.loading) {
            setTimeout(() => isLoadedJS($name, $load, $callback), 50);
            return false;
        }
        isLoadedJS.loading = true;
        const configUrl = ajax_request_vars.theme_url + "/static/js/js_files_conditional_set.json";
        $.ajax({
            url: configUrl, dataType: 'json', cache: true,
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
            if (Array.isArray($params)) { window[$name].apply(null, $params); }
            else { window[$name]($params); }
        } else {
            console.error($name + ' is not a function...');
        }
    } else {
        console.error($plugin + ' is not loaded...');
    }
}


/**
 * CF7Manager — Contact Form 7 entegrasyonu.
 * Event listener'lar, form init, loader yönetimi, hata işaretleme.
 */
class CF7Manager {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        const _this = this;
        document.addEventListener('wpcf7submit', (e) => { _this.toggleLoader(false); _this.handleFormActions(e, 'submit'); }, false);
        document.addEventListener('wpcf7mailsent', (e) => { _this.handleFormActions(e, 'sent'); }, false);
        document.addEventListener('wpcf7invalid', (e) => { _this.handleInvalidFields(e); _this.toggleLoader(false); }, false);
        ['wpcf7mailfailed', 'wpcf7spam'].forEach(evt => {
            document.addEventListener(evt, () => _this.toggleLoader(false), false);
        });
    }

    initForms($scope = $("body")) {
        if (typeof wpcf7 !== 'undefined' && typeof wpcf7.init !== 'function') {
            document.dispatchEvent(new Event('DOMContentLoaded'));
        }
        const _this = this;
        $scope.find(".wpcf7-form").each(function() {
            const $form = $(this);
            if (typeof wpcf7 !== 'undefined' && typeof wpcf7.init === 'function') { wpcf7.init($form[0]); }
            if (typeof wpcf7cf === "object") { wpcf7cf.initForm($form); }
            $form.find(".btn-submit").off("click").on("click", function() {
                $(this).closest(".wpcf7-form").find(".accordion-item.error").removeClass("error");
                _this.toggleLoader(true);
            });
        });
    }

    toggleLoader(show) {
        const $target = $('.modal:visible').length > 0 ? $('.modal:visible').find(".modal-content") : $("body");
        if (show) $target.addClass("loading-process");
        else $target.removeClass("loading-process");
    }

    handleFormActions(e, type) {
        const $form = $(e.target);
        const $formSubmit = $form.find('.btn-submit');
        const $scrollTarget = $('.modal:visible').length > 0 ? $('.modal:visible') : $("html,body");
        const hasError = $form.hasClass('invalid') || $form.hasClass('failed') || $form.hasClass('unaccepted') || $form.hasClass('spam');

        if (type === "submit" && hasError) {
            this.scrollToElement($scrollTarget, $form);
            $formSubmit.prop('disabled', false).blur();
        } else if (type === "sent") {
            this.scrollToElement($scrollTarget, $form);
            $formSubmit.prop('disabled', false).blur();
            if (!hasError) { $form.removeClass("sent").addClass("init"); }
        }
    }

    handleInvalidFields(e) {
        const $form = $(e.target);
        $form.find(".accordion-item.error").removeClass("error");
        if (e.detail && e.detail.apiResponse && e.detail.apiResponse.invalid_fields) {
            e.detail.apiResponse.invalid_fields.forEach(fieldObj => {
                const $field = $form.find("[name='" + fieldObj.field + "']");
                if ($field.closest(".accordion-item").length > 0) { $field.closest(".accordion-item").addClass("error"); }
                $field.one("click", function() { $(this).removeClass("wpcf7-not-valid"); });
            });
        }
    }

    scrollToElement($container, $element) {
        const offset = $element.offset().top - ($("header#header").height() || 0) - 20;
        $container.animate({ scrollTop: offset }, 600);
    }
}
window.AppCF7 = new CF7Manager();

$(document).ready(function() {
    window.AppCF7.initForms();
});


/* ============================================================
 *  MODAL ORTAK YARDIMCILAR
 *  Tüm modal türleri (custom, template, page, map, form, iframe)
 *  bu fonksiyonları kullanır — tekrar yok, tek nokta.
 * ============================================================ */

/**
 * modal_create_dialog
 * Bootbox dialog oluşturur, fullscreen + özel class'ları uygular, ID atar.
 *
 * @param {Object} vars       Ajax hook'tan gelen vars objesi
 * @param {Object} response   Ajax response objesi (onHidden için)
 * @param {Object} objs       Ajax objs objesi
 * @param {Object} opts       Ek seçenekler: { className, defaultClose, defaultCentered, defaultBackdrop }
 * @returns {jQuery}          Oluşturulan dialog jQuery objesi
 */
function modal_create_dialog(vars, response, objs, opts) {
    opts = opts || {};

    var className  = (opts.className || 'modal-page') + ' loading ' + (vars.class || '');
    var scrollable = bool(vars.scrollable, false);
    var close      = bool(vars.close,      opts.defaultClose    !== undefined ? opts.defaultClose    : true);
    var centered   = bool(vars.centered,   opts.defaultCentered !== undefined ? opts.defaultCentered : true);
    var animate    = bool(vars.animate,    false);
    var backdrop   = bool(vars.backdrop,   opts.defaultBackdrop !== undefined ? opts.defaultBackdrop : true);
    var size       = !IsBlank(vars.size)   ? vars.size : 'xl';

    var dialog = bootbox.dialog({
        className:     className,
        title:         '<div></div>',
        message:       '<div></div>',
        closeButton:   close,
        size:          size,
        scrollable:    scrollable,
        centerVertical: centered,
        animate:       animate,
        backdrop:      backdrop,
        buttons:       {},
        onHidden: function() { if (response && response.abort) response.abort(); }
    });

    // Fullscreen
    if (vars.fullscreen) {
        dialog.find('.modal-dialog').addClass(
            vars.fullscreen === true ? 'modal-fullscreen' : vars.fullscreen
        );
    }

    // Özel modal class'ları: [{ "modal-dialog": "my-class" }, ...]
    if (Array.isArray(vars.modal)) {
        vars.modal.forEach(function(item) {
            Object.entries(item).forEach(function(entry) {
                dialog.find('.' + entry[0]).addClass(entry[1]);
            });
        });
    }

    // Eşsiz ID
    dialog.attr('id', typeof generateCode === 'function' ? generateCode(5) : 'modal_' + Date.now());

    // objs ve response güncelle
    objs.modal       = dialog;
    response.objs    = { modal: dialog, btn: objs.btn };

    return dialog;
}

/**
 * modal_handle_error
 * Hata durumunda modal'ı gizler ve mesajı gösterir.
 * @returns {boolean} false — after hook'undan erken çıkmak için kullan
 */
function modal_handle_error(response, modal) {
    modal.addClass('remove-on-hidden').modal('hide');
    if (response.message && typeof response_view === 'function') {
        response_view(response);
    }
    return false;
}

/**
 * modal_set_content
 * Response data'sına göre modal içeriğini yerleştirir.
 * content varsa → .modal-content'e, yoksa title+body ayrı ayrı.
 */
function modal_set_content(response, modal) {
    var data = response.data;
    if (!data) return;

    if (data.hasOwnProperty('content')) {
        modal.find('.modal-content').html(data.content);
    } else {
        if (data.title   !== undefined) modal.find('.modal-title').html(data.title);
        if (data.body    !== undefined) modal.find('.modal-body').html(data.body);
        if (data.content !== undefined) modal.find('.modal-body').html(data.content);
    }
}

/**
 * modal_load_plugins_then_init
 *
 * Modal içeriği yüklendikten sonra gereken plugin'leri sırayla yükler,
 * hepsi hazır olunca init_functions() çağırır.
 *
 * @param {Object} plugins  { pluginKey: "initFuncName", ... }  (custom_modal PHP'den geliyor)
 *                          ya da string[] array  (sadece key listesi)
 * @param {jQuery} scope    Modal jQuery objesi — init scope'u için
 */
function modal_load_plugins_then_init(plugins, scope) {

    // Normalize: array ise { key: "" } objesine çevir
    var pluginMap = {};
    if (Array.isArray(plugins)) {
        plugins.forEach(function(k) { pluginMap[k] = ""; });
    } else if (plugins && typeof plugins === 'object') {
        pluginMap = plugins;
    }

    var keys = Object.keys(pluginMap);

    if (!keys.length) {
        // Yüklenecek plugin yok, direkt init
        if (typeof init_functions === 'function') { init_functions(); }
        return;
    }

    // Bağımlılık sıralaması: js_files_conditional_set.json'daki dependencies alanını oku
    // isLoadedJS zaten cache'liyor, ikinci çağrıda JSON isteği atmaz
    var _loadChain = function(list, onDone) {
        if (!list.length) { onDone(); return; }
        var name = list.shift();

        // Önce dependencies'i yükle (varsa)
        var _loadWithDeps = function(pluginName, cb) {
            var config = isLoadedJS.cache ? isLoadedJS.cache[pluginName] : null;
            var deps   = (config && Array.isArray(config.dependencies)) ? config.dependencies.slice() : [];

            if (!deps.length) {
                // Bağımlılık yok, direkt yükle
                isLoadedJS(pluginName, true, cb);
                return;
            }

            // Önce bağımlılıkları yükle, sonra asıl plugin'i
            _loadChain(deps, function() {
                isLoadedJS(pluginName, true, cb);
            });
        };

        _loadWithDeps(name, function() {
            _loadChain(list, onDone);
        });
    };

    // isLoadedJS cache'i hazır mı? Hazır değilse önce bir dummy çağrıyla yüklet
    var _run = function() {
        _loadChain(keys.slice(), function() {
            // Hepsi yüklendi — direkt init et, init_functions'a bırakma
            // init_functions içindeki function_secure isLoadedJS(key, false) ile kontrol ediyor
            // ve sayfada olmayan plugin'ler için false dönebiliyor.
            // Biz zaten yükledik, direkt window[initFunc]() çağırıyoruz.
            Object.entries(pluginMap).forEach(function(entry) {
                var pluginKey  = entry[0]; // "leaflet"
                var initFunc   = entry[1]; // "init_leaflet"
                if (!initFunc) return;

                // "a.b.c" dot notation desteği (örn: "AppCF7.initForms")
                var parts = initFunc.split('.');
                var fn = window;
                parts.forEach(function(p) { if (fn && fn[p]) fn = fn[p]; });

                if (typeof fn === 'function') {
                    var modalScope = (scope && scope.length) ? scope : $('.modal:visible').length ? $('.modal:visible') : $("body");
                    fn(modalScope);
                }
            });

            // Genel init (scroll, btn_ajax_method vs.) için de çağır ama plugins olmadan
            if (typeof init_functions === 'function') { init_functions(); }
        });
    };

    if (isLoadedJS.cache) {
        _run();
    } else {
        var firstKey = keys[0];
        isLoadedJS(firstKey, true, function() {
            var remaining = keys.slice(1);
            var _finish = function() {
                Object.entries(pluginMap).forEach(function(entry) {
                    var initFunc = entry[1];
                    if (!initFunc) return;
                    var parts = initFunc.split('.');
                    var fn = window;
                    parts.forEach(function(p) { if (fn && fn[p]) fn = fn[p]; });
                    if (typeof fn === 'function') {
                        var modalScope = (scope && scope.length) ? scope : $('.modal:visible').length ? $('.modal:visible') : $("body");
                        fn(modalScope);
                    }
                });
                if (typeof init_functions === 'function') { init_functions(); }
            };
            if (!remaining.length) { _finish(); return; }
            _loadChain(remaining, _finish);
        });
    }
}
