/**
 * AJAX Init Manager - Smart Content-Based Initialization
 * AJAX sonrası yüklenen içeriği scan edip gerekli init'leri çalıştırır
 * 
 * @version 3.1.0 - JSON-Based Dynamic Plugin System
 * @author SaltHareket
 */

window.AjaxInitManager = {
    
    // Element-Init Mapping (Selector → Function Name)
    initMap: {
        // Buttons & Interactive Elements
        '.btn-favorite': 'btn_favorite',
        '.btn-ajax-method:not(.btn-ajax-method-init)': 'btn_ajax_method',
        '.btn-loading-page:not(.btn-loading-page-init)': 'btn_loading_page',
        '.btn-loading-self:not(.btn-loading-self-init)': 'btn_loading_self',
        '.btn-loading-parent:not(.btn-loading-parent-init)': 'btn_loading_parent',
        
        // AJAX & Pagination
        '.ajax-paginate:not(.ajax-paginate-init)': 'ajax_paginate',
        
        // Forms
        'form:not(.form-initialized)': 'init_forms',
        
        // Custom Components
        '.nav-equal:not(.nav-equal-initialized)': 'init_nav_equal',
        '.match-height:not(.match-height-initialized)': 'init_match_height',

        // Product Tease
        '.tease-product:not([data-img-zone-init])': 'init_tease_image_zones',
        '.tease-product:not([data-swatch-hover-init])': 'init_tease_swatch_hover'
    },
    
    // Dynamic Plugin Map (JSON'dan otomatik çekilir)
    pluginMap: {},
    
    // Plugin config data (JSON'dan yüklenen ham data)
    pluginConfigData: {},
    
    // Critical init'ler (her zaman çalışmalı)
    criticalInits: ['btn_favorite', 'btn_ajax_method'],
    
    // Container-aware fonksiyonlar (container parametresi alabilir)
    containerAwareFunctions: [
        'btn_ajax_method',
        'btn_favorite',
        'ajax_paginate',
        'init_forms',
        'init_tease_image_zones',
        'init_tease_swatch_hover'
    ],
    
    /**
     * Sistem başlatma - Plugin map'i dinamik olarak oluştur
     */
    init: function() {
        this.buildDynamicPluginMap();
        log('🚀 AjaxInitManager v3.1 initialized with JSON-based plugin system');
    },
    
    /**
     * JSON'dan dinamik plugin map oluştur
     */
    buildDynamicPluginMap: function() {
        // 1. Öncelik: JavaScript global variable
        if (typeof window.compile_files_config !== 'undefined') {
            const plugins = window.compile_files_config.js?.plugins || {};
            this.buildPluginMapFromConfig(plugins);
            return;
        }
        
        // 2. JSON dosyasından çek (en performanslı)
        this.loadPluginConfigFromJSON();
    },
    
    /**
     * JSON dosyasından plugin config'i yükle
     */
    loadPluginConfigFromJSON: function() {
        const jsonUrl = (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.theme_url) 
            ? ajax_request_vars.theme_url + 'static/js/js_files_conditional_set.json'
            : '/wp-content/themes/salthareket/static/js/js_files_conditional_set.json';
        
        // Cache kontrolü
        const cacheKey = 'ajaxInitManager_pluginCache_v3';
        const cached = localStorage.getItem(cacheKey);
        
        if (cached) {
            try {
                const cachedData = JSON.parse(cached);
                if (cachedData.timestamp && (Date.now() - cachedData.timestamp < 3600000)) { // 1 saat cache
                    this.buildPluginMapFromJSON(cachedData.plugins);
                    return;
                }
            } catch (e) {
                log('Plugin cache parse error: ' + e, 'warn');
            }
        }
        
        // JSON dosyasını fetch et
        fetch(jsonUrl)
            .then(response => response.json())
            .then(plugins => {
                // Cache'e kaydet
                localStorage.setItem(cacheKey, JSON.stringify({
                    plugins: plugins,
                    timestamp: Date.now()
                }));
                
                this.buildPluginMapFromJSON(plugins);
            })
            .catch(error => {
                log('JSON plugin config fetch failed, using fallback: ' + error, 'warn');
                this.buildFallbackPluginMap();
            });
    },
    
    /**
     * JSON'dan plugin map oluştur
     */
    buildPluginMapFromJSON: function(plugins) {
        log('🔌 Building plugin map from JSON: ' + JSON.stringify(Object.keys(plugins)));
        
        // Plugin config data'yı sakla
        this.pluginConfigData = plugins;
        
        Object.keys(plugins).forEach(pluginKey => {
            const plugin = plugins[pluginKey];
            const initFunc = plugin.init || `init_${pluginKey}`;
            
            if (!initFunc || initFunc === '') return;
            
            const selectors = this.getSelectorsForPlugin(pluginKey);
            
            if (selectors.length > 0) {
                this.pluginMap[pluginKey] = {};
                selectors.forEach(selector => {
                    this.pluginMap[pluginKey][selector] = initFunc;
                });
            }
        });
        
        log('🔌 Dynamic plugin map built from JSON: ' + JSON.stringify(Object.keys(this.pluginMap)));
    },
    
    /**
     * Config'den plugin map oluştur (fallback)
     */
    buildPluginMapFromConfig: function(plugins) {
        log('🔌 Building plugin map from config: ' + JSON.stringify(Object.keys(plugins)));
        
        this.pluginConfigData = plugins;
        
        Object.keys(plugins).forEach(pluginKey => {
            const plugin = plugins[pluginKey];
            const initFunc = plugin.init || `init_${pluginKey}`;
            
            const selectors = this.getSelectorsForPlugin(pluginKey);
            
            if (selectors.length > 0) {
                this.pluginMap[pluginKey] = {};
                selectors.forEach(selector => {
                    this.pluginMap[pluginKey][selector] = initFunc;
                });
            }
        });
        
        log('🔌 Dynamic plugin map built from config: ' + JSON.stringify(Object.keys(this.pluginMap)));
    },
    
    /**
     * Fallback plugin map (bilinen plugin'ler)
     */
    buildFallbackPluginMap: function() {
        log('🔌 Using fallback plugin map');
        
        const fallbackPlugins = {
            'swiper': 'init_swiper',
            'leaflet': 'init_leaflet',
            'plyr': 'init_plyr',
            'aos': 'init_aos',
            'lightgallery': 'init_lightGallery',
            'bootbox': 'init_bootbox',
            'locomotive-scroll': 'init_locomotive_scroll',
            'jquery-slinky': 'init_slinky',
            'textillate': 'text_effect',
            'jquery.simple-text-rotator': 'init_text_rotator',
            'markerclusterer': 'init_google_maps',
            'smarquee': 'init_smarquee',
            'jquery-zoom': 'init_jquery_zoom',
            'panzoom': 'init_panzoom',
            'simplebar': 'init_simplebar'
        };
        
        Object.keys(fallbackPlugins).forEach(pluginKey => {
            const initFunc = fallbackPlugins[pluginKey];
            const selectors = this.getSelectorsForPlugin(pluginKey);
            
            if (selectors.length > 0) {
                this.pluginMap[pluginKey] = {};
                selectors.forEach(selector => {
                    this.pluginMap[pluginKey][selector] = initFunc;
                });
            }
        });
    },
    
    /**
     * Plugin'e göre selector'ları belirle - JSON'dan dinamik olarak
     */
    getSelectorsForPlugin: function(pluginKey) {
        if (this.pluginConfigData && this.pluginConfigData[pluginKey] && this.pluginConfigData[pluginKey].selector) {
            const selectorData = this.pluginConfigData[pluginKey].selector;
            if (typeof selectorData === 'string') return [selectorData];
            if (Array.isArray(selectorData)) return selectorData;
        }
        
        const fallbackSelectorMap = {
            'swiper': ['.swiper:not(.swiper-initialized)'],
            'leaflet': ['.leaflet-map:not(.leaflet-initialized)', '[data-map="leaflet"]:not(.leaflet-initialized)'],
            'plyr': ['.plyr:not(.plyr-initialized)', '[data-plyr]:not(.plyr-initialized)'],
            'aos': ['[data-aos]:not(.aos-initialized)'],
            'lightgallery': ['.lightgallery:not(.lightgallery-initialized)', '[data-lightgallery]:not(.lightgallery-initialized)'],
            'bootbox': ['.bootbox-trigger:not(.bootbox-initialized)'],
            'locomotive-scroll': ['.locomotive-scroll:not(.locomotive-initialized)', '[data-locomotive]:not(.locomotive-initialized)'],
            'jquery-slinky': ['.slinky-menu:not(.slinky-initialized)'],
            'textillate': ['.textillate:not(.textillate-initialized)', '[data-textillate]:not(.textillate-initialized)'],
            'jquery.simple-text-rotator': ['.text-rotator:not(.text-rotator-initialized)', '[data-text-rotator]:not(.text-rotator-initialized)'],
            'markerclusterer': ['.google-map:not(.google-map-initialized)', '[data-map="google"]:not(.google-map-initialized)'],
            'smarquee': ['.smarquee:not(.smarquee-initialized)', '[data-smarquee]:not(.smarquee-initialized)'],
            'jquery-zoom': ['.jquery-zoom:not(.jquery-zoom-initialized)', '[data-zoom]:not(.jquery-zoom-initialized)'],
            'panzoom': ['.panzoom:not(.panzoom-initialized)', '[data-panzoom]:not(.panzoom-initialized)'],
            'simplebar': ['.simplebar:not(.simplebar-initialized)', '[data-simplebar]:not(.simplebar-initialized)']
        };
        
        return fallbackSelectorMap[pluginKey] || [];
    },
    
    /**
     * Ana init fonksiyonu - Container içindeki elementleri scan eder
     */
    initInContext: function(container = document, options = {}) {
        const $container = $(container);
        const startTime = performance.now();
        
        log('🔄 AjaxInitManager: Init başlıyor...');
        
        let initCount = 0;
        const initResults = {};
        
        // 1. Temel init'leri çalıştır
        Object.keys(this.initMap).forEach(selector => {
            try {
                if (!this.isValidSelector(selector)) {
                    log('⚠️ Geçersiz temel selector atlandı: ' + selector, 'warn');
                    return;
                }
                
                const $elements = $container.find(selector);
                
                if ($elements.length > 0) {
                    const initFuncName = this.initMap[selector];
                    const initFunc = this.getInitFunction(initFuncName);
                    
                    if (initFunc) {
                        log('🎯 Init: ' + initFuncName + ' (' + $elements.length + ' element)');
                        
                        if (this.isContainerAware(initFuncName)) {
                            initFunc($container);
                        } else {
                            initFunc();
                        }
                        
                        initCount++;
                        initResults[initFuncName] = $elements.length;
                    } else {
                        log('⚠️ Init function bulunamadı: ' + initFuncName, 'warn');
                    }
                }
            } catch (error) {
                log('❌ Init hatası (' + selector + '): ' + error, 'error');
            }
        });
        
        // 2. Plugin-based init'leri çalıştır
        this.initPluginsInContext($container, initResults);
        
        // 3. Özel init'ler
        this.runSpecialInits($container);
        
        const endTime = performance.now();
        log('✅ AjaxInitManager tamamlandı: ' + initCount + ' init, ' + Math.round(endTime - startTime) + 'ms');
        
        $(document).trigger('ajax_init:complete', [{
            container: container,
            initCount: initCount,
            results: initResults,
            duration: endTime - startTime
        }]);
    },
    
    /**
     * Plugin-based initialization
     */
    initPluginsInContext: function($container, initResults = {}) {
        const requiredPlugins = {};
        
        Object.keys(this.pluginMap).forEach(pluginKey => {
            const selectors = this.pluginMap[pluginKey];
            
            Object.keys(selectors).forEach(selector => {
                try {
                    if (!this.isValidSelector(selector)) {
                        log('⚠️ Geçersiz selector atlandı: ' + selector, 'warn');
                        return;
                    }
                    
                    const $elements = $container.find(selector);
                    
                    if ($elements.length > 0) {
                        const initFuncName = selectors[selector];
                        
                        if (!requiredPlugins[pluginKey]) {
                            requiredPlugins[pluginKey] = [];
                        }
                        
                        requiredPlugins[pluginKey].push({
                            selector: selector,
                            initFunc: initFuncName,
                            elements: $elements.length
                        });
                    }
                } catch (error) {
                    log('❌ Selector hatası (' + selector + '): ' + error, 'error');
                }
            });
        });
        
        if (Object.keys(requiredPlugins).length > 0) {
            log('🔌 Plugin init gerekli: ' + JSON.stringify(Object.keys(requiredPlugins)));
            
            Object.keys(requiredPlugins).forEach(pluginKey => {
                requiredPlugins[pluginKey].forEach(pluginInfo => {
                    try {
                        if (typeof function_secure === 'function') {
                            log('🔌 Plugin Init: ' + pluginKey + ' → ' + pluginInfo.initFunc + ' (' + pluginInfo.elements + ' element)');
                            function_secure(pluginKey, pluginInfo.initFunc, [$container]);
                            initResults[`plugin_${pluginKey}_${pluginInfo.initFunc}`] = pluginInfo.elements;
                        } else {
                            const initFunc = this.getInitFunction(pluginInfo.initFunc);
                            if (initFunc) {
                                log('🔌 Direct Plugin Init: ' + pluginInfo.initFunc + ' (' + pluginInfo.elements + ' element)');
                                initFunc($container);
                                initResults[`direct_${pluginInfo.initFunc}`] = pluginInfo.elements;
                            }
                        }
                    } catch (error) {
                        log('❌ Plugin init hatası (' + pluginKey + '): ' + error, 'error');
                    }
                });
            });
        }
    },
    
    /**
     * Selector'ın geçerli olup olmadığını kontrol et
     */
    isValidSelector: function(selector) {
        if (!selector || typeof selector !== 'string' || selector.trim() === '') return false;
        
        if (selector.includes('data-toggle="') && !selector.startsWith('[') && !selector.endsWith(']')) {
            return false;
        }
        
        try {
            $(document.createElement('div')).find(selector);
            return true;
        } catch (error) {
            return false;
        }
    },
    
    /**
     * Init fonksiyonunu güvenli şekilde al
     */
    getInitFunction: function(funcName) {
        if (typeof window[funcName] === 'function') return window[funcName];
        
        const parts = funcName.split('.');
        let func = window;
        
        for (const part of parts) {
            if (func && typeof func[part] !== 'undefined') {
                func = func[part];
            } else {
                return null;
            }
        }
        
        return typeof func === 'function' ? func : null;
    },
    
    /**
     * Fonksiyonun container-aware olup olmadığını kontrol et
     */
    isContainerAware: function(funcName) {
        return this.containerAwareFunctions.includes(funcName);
    },
    
    /**
     * Özel init'leri çalıştır
     */
    runSpecialInits: function($container) {
        if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance.update) {
            lazyLoadInstance.update();
        }
        
        if ($.fn.matchHeight && $.fn.matchHeight._update) {
            $.fn.matchHeight._update();
        }
        
        if ($container.find('[data-bs-toggle="tooltip"]').length > 0) {
            this.initBootstrapTooltips($container);
        }
        
        if ($container.find('[data-bs-toggle="popover"]').length > 0) {
            this.initBootstrapPopovers($container);
        }
    },
    
    initBootstrapTooltips: function($container) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            $container.find('[data-bs-toggle="tooltip"]:not([data-bs-original-title])').each(function() {
                new bootstrap.Tooltip(this);
            });
        }
    },
    
    initBootstrapPopovers: function($container) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            $container.find('[data-bs-toggle="popover"]:not(.popover-initialized)').each(function() {
                new bootstrap.Popover(this);
                $(this).addClass('popover-initialized');
            });
        }
    },
    
    clearPluginCache: function() {
        localStorage.removeItem('ajaxInitManager_pluginCache_v3');
        log('🗑️ Plugin cache cleared');
    },
    
    rebuildPluginMap: function() {
        this.pluginMap = {};
        this.clearPluginCache();
        this.buildDynamicPluginMap();
        log('🔄 Plugin map rebuilt');
    },
    
    /**
     * Debug bilgisi - sadece debug modunda çalışır
     */
    debug: function() {
        log.group('🔍 AjaxInitManager v3.1 Debug');
        log.table(this.initMap);
        log.table(this.pluginMap);
        log('Critical Inits: ' + JSON.stringify(this.criticalInits));
        log('Container-Aware: ' + JSON.stringify(this.containerAwareFunctions));
    }
};

// Sistem başlatma
$(document).ready(function() {
    window.AjaxInitManager.init();
});

// Global erişim için
window.initInContext = window.AjaxInitManager.initInContext.bind(window.AjaxInitManager);
