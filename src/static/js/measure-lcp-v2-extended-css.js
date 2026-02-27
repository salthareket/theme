/*function lcp_data(metric, type) {
    debugJS("is cached:" + site_config.cached);
    if (!metric || !metric.attribution || site_config.cached) return '';
    let element, url;
    if (typeof metric.attribution.lcpEntry !== "undefined") {
        element = metric.attribution.lcpEntry.element;
        url = metric.attribution.lcpEntry.url || "";
    } else {
        element = metric.entries[0].element;
        url = metric.entries[0].url || "";
    }

    debugJS("----------------------------");
    debugJS(metric);
    debugJS(element);

    let preloadTag = "";
    let preloadType = "";

    function getUniqueSelector(el) {
        if (!el || !(el instanceof Element)) return "";

        const isUnique = (selector) => {
            if (!selector || typeof selector !== "string") return false;
            selector = selector.trim();
            if (selector.startsWith(">")) selector = selector.substring(1).trim();
            try {
                return document.querySelectorAll(selector).length === 1;
            } catch (e) {
                console.error(`Geçersiz CSS seçici: ${selector}`, e);
                return false;
            }
        };

        let selector = el.tagName.toLowerCase();

        if (el.classList.length > 0) {
            let validClasses = Array.from(el.classList)
                .filter(cls => cls.trim() !== "" && !["loaded", "entered", "lazy"].includes(cls))// measure-lcp.js'ten:
                .map(cls => `.${CSS.escape(cls)}`)
                .join("");
            selector += validClasses;
        }

        if (isUnique(selector)) return selector;

        if (el.id) return `#${CSS.escape(el.id)}`;

        const getParentSelector = (element) => {
            if (!element || element.tagName.toLowerCase() === "html") return "";
            let parentSelector = getUniqueSelector(element.parentElement);
            let newSelector = `${parentSelector} > ${selector}`.trim();
            return isUnique(newSelector) ? newSelector : getParentSelector(element.parentElement);
        };

        return getParentSelector(el);
    }

    function getElementStyles(el) {
        if (!el) return {};
        const computedStyles = window.getComputedStyle(el);
        let styles = {
            "font-size": computedStyles.fontSize,
            "font-family": computedStyles.fontFamily,
            "color": computedStyles.color,
            "background-color": computedStyles.backgroundColor,
            "padding": computedStyles.padding,
            "margin": computedStyles.margin,
            "border": computedStyles.border
        };

        if (styles["height"] === 'auto' || styles["height"] === '') {
            let parent = el.parentElement;
            while (parent && parent !== document.body) {
                let parentStyles = window.getComputedStyle(parent);
                if (parentStyles.height && parentStyles.height !== 'auto' && parentStyles.height !== '') {
                    styles["height"] = parentStyles.height;
                    break;
                }
                parent = parent.parentElement;
            }
        }

        return styles;
    }

    const selectorList = [];//getCriticalSelectors(element);

    if (element) {
        preloadType = element.tagName.toLowerCase();
        switch (preloadType) {
            case "img": preloadType = "image"; break;
            case "iframe": preloadType = "document"; break;
            case "video": preloadType = "video"; break;
            default: preloadType = "image"; break;
        }
        const selector = getUniqueSelector(element);
        const styles = getElementStyles(element);

        preloadTag = `${selector} {\n`;
        Object.entries(styles).forEach(([key, value]) => {
            if (value && value !== 'auto') {
                preloadTag += `    ${key}: ${value};\n`;
            }
        });
        preloadTag += "  }\n";
    } else {
        preloadType = "css";
        preloadTag = "";
    }

    let img_url = url || "";
    let code = {
        type: preloadType,
        code: preloadTag,
        url: img_url,
        id: 0,
        selectors: selectorList
    };

    debugJS(code);

    return code;
}*/

function lcp_data(metric, type) {
    debugJS("is cached:" + site_config.cached);
    if (!metric || !metric.attribution || site_config.cached) return '';
    let element, url;

    // LCP Elementini bulma
    if (typeof metric.attribution.lcpEntry !== "undefined") {
        element = metric.attribution.lcpEntry.element;
        url = metric.attribution.lcpEntry.url || "";
    } else {
        element = metric.entries[0].element;
        url = metric.entries[0].url || "";
    }

    debugJS("----------------------------");
    debugJS(metric);
    debugJS(element);

    let criticalCssRules = []; 
    let preloadType = "";

    // -----------------------------------------
    // 1. getUniqueSelector (Aynı Kaldı)
    // -----------------------------------------
    function getUniqueSelector(el) {
        if (!el || !(el instanceof Element)) return "";

        const isUnique = (selector) => {
            if (!selector || typeof selector !== "string") return false;
            selector = selector.trim();
            if (selector.startsWith(">")) selector = selector.substring(1).trim();
            try {
                return document.querySelectorAll(selector).length === 1; 
            } catch (e) {
                console.error(`Geçersiz CSS seçici: ${selector}`, e);
                return false;
            }
        };

        let selector = el.tagName.toLowerCase();

        if (el.classList.length > 0) {
            let validClasses = Array.from(el.classList)
                .filter(cls => cls.trim() !== "" && !["loaded", "entered", "lazy"].includes(cls))
                .map(cls => `.${CSS.escape(cls)}`)
                .join("");
            selector += validClasses;
        }

        if (isUnique(selector)) return selector;

        if (el.id) return `#${CSS.escape(el.id)}`;

        const getParentSelector = (element) => {
            if (!element || element.tagName.toLowerCase() === "html") return "";
            let parentSelector = getUniqueSelector(element.parentElement);
            let newSelector = `${parentSelector} > ${selector}`.trim(); 
            return isUnique(newSelector) ? newSelector : getParentSelector(element.parentElement);
        };

        return getParentSelector(el);
    }
    
    // -----------------------------------------
    // 2. getElementStyles (KRİTİK GÜNCELLEME)
    // -----------------------------------------
    function getElementStyles(el) {
        if (!el) return {};
        const computedStyles = window.getComputedStyle(el);
        let styles = {};

        // LCP'nin 100vh yüksekliğini sağlayan KRİTİK stiller
        const criticalProps = [
            "position", "display", "z-index", 
            "width", "height", "min-height", "max-height", // <-- 100vh burada
            "margin", "padding", "box-sizing",
            "flex-direction", "justify-content", "align-items", "gap", // <-- Flex/Grid Layout
            "font-size", "color", "background-color", "background-image", "border",
            "object-fit", "object-position" // Resimler için
        ];

        criticalProps.forEach(prop => {
            const value = computedStyles.getPropertyValue(prop);
            // Varsayılan ve gereksiz değerleri atla
            if (value && value !== 'initial' && value !== 'auto' && value !== 'none' && value !== '0px') {
                
                 // URL içeren arka plan resimlerini preload için sakla
                if(prop === 'background-image' && value.includes('url(')) {
                    // Bu URL'i preload URL'i olarak almak için saklıyoruz
                    styles['background-image-url'] = value.match(/url\(['"]?(.*?)['"]?\)/)[1];
                }
                styles[prop] = value;
            }
        });
        
        return styles;
    }

    // -----------------------------------------
    // 3. collectCriticalStyles (YENİ: Parent Taraması)
    // -----------------------------------------
    function collectCriticalStyles(lcpElement) {
        const rules = [];
        let current = lcpElement;
        
        // LCP elementinden başlayıp BODY'ye kadar tırmanma
        while (current && current.tagName.toLowerCase() !== 'body' && current.tagName.toLowerCase() !== 'html') {
            const selector = getUniqueSelector(current);
            const styles = getElementStyles(current);

            // Stiller varsa ve benzersiz bir seçici bulunabildiyse, kural oluştur
            if (Object.keys(styles).length > 0 && selector) {
                let cssText = "";
                Object.entries(styles).forEach(([key, value]) => {
                    if(key === 'background-image-url') return; // Preload URL'i CSS'e eklenmez
                    cssText += `  ${key}: ${value};\n`;
                });
                
                // unshift ile en içten dışa (LCP -> Parent -> ...) doğru sıralarız.
                rules.unshift({ 
                    selector: selector,
                    css: cssText,
                    preloadUrl: styles['background-image-url'] || ''
                });
            }
            
            current = current.parentElement;
        }

        // HTML ve BODY etiketlerini ekle (100vh zincirinin başlangıcı)
        ['html', 'body'].forEach(tag => {
            const el = document.querySelector(tag);
            const styles = getElementStyles(el);
             if (Object.keys(styles).length > 0) {
                let cssText = "";
                Object.entries(styles).forEach(([key, value]) => {
                     if(key === 'background-image-url') return;
                     cssText += `  ${key}: ${value};\n`;
                });
                rules.unshift({ // En üste eklenir (en dıştaki kurallar)
                    selector: tag,
                    css: cssText,
                    preloadUrl: styles['background-image-url'] || ''
                });
            }
        });
        
        return rules;
    }


    // -----------------------------------------
    // 4. LCP_DATA ÇIKTI OLUŞTURMA
    // -----------------------------------------

    if (element) {
        preloadType = element.tagName.toLowerCase();
        switch (preloadType) {
            case "img": preloadType = "image"; break;
            case "iframe": preloadType = "document"; break;
            case "video": preloadType = "video"; break;
            default: preloadType = "image"; break; 
        }

        criticalCssRules = collectCriticalStyles(element);
    } else {
        preloadType = "css";
    }

    // Tüm kuralları tek bir CSS metnine birleştirme
    let fullCriticalCss = "";
    let preloadUrlFromCss = ""; 

    criticalCssRules.forEach(rule => {
        fullCriticalCss += `${rule.selector} {\n${rule.css}}\n`;
        // Background image varsa preload URL'ini al
        if (rule.preloadUrl) preloadUrlFromCss = rule.preloadUrl;
    });

    let img_url = url || preloadUrlFromCss || ""; 
    
    // Sonuç objesi (PHP'ye gönderilecek veri)
    let code = {
        type: preloadType,
        code: fullCriticalCss, // <-- PHP'nin <style> içine enjekte edeceği CSS
        url: img_url,
        id: 0,
        selectors: criticalCssRules.map(r => r.selector)
    };

    debugJS(code);

    return code;
}

function lcp_data_save(metric, type = "desktop") {
    // sayfa tam yüklenince stil al
   // window.addEventListener("load", () => {
        const lcpData = lcp_data(metric, type);
        debugJS("fetched")
        const pageUrl = window.location.href;
        debugJS(type + " LCP:", lcpData);
        fetch(ajax_request_vars.url_admin, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "save_lcp_results",
                type: site_config.meta.type,
                id: site_config.meta.id,
                lang: site_config.user_language,
                lcp_data: JSON.stringify({ [type]: lcpData }),
                url: pageUrl,
            }),
        })
            .then(response => response.json())
            .then(data => {
                debugJS(type + " LCP Sonuçları kaydedildi:", data);
                if (type === "mobile" && window.opener) {
                    self.close();
                }
            });
    //});
}

function lcp_for_mobile(url) {
    let iframeWindow = window.open('', 'mobileWindow', 'width=412,height=823');
    iframeWindow.onload = function() {
        let viewportMetaTag = iframeWindow.document.createElement('meta');
        viewportMetaTag.name = 'viewport';
        viewportMetaTag.content = 'width=412, initial-scale=1.0, user-scalable=no';
        iframeWindow.document.head.appendChild(viewportMetaTag);
        // User Agent ve Mobile Özelliklerini Simüle Et
        Object.defineProperty(iframeWindow.navigator, "userAgent", {
            get: function () {
                return "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Mobile Safari/537.36";
            }
        });
        Object.defineProperty(iframeWindow.navigator, "maxTouchPoints", {
            get: function () {
                return 5;
            }
        });
        // Mobil Bağlantı Simülasyonu (3G)
        if ('connection' in iframeWindow.navigator) {
            Object.defineProperty(iframeWindow.navigator.connection, "effectiveType", {
                get: function () {
                    return '3g';
                }
            });
            Object.defineProperty(iframeWindow.navigator.connection, "downlink", {
                get: function () {
                    return 1.5;
                }
            });
        }
        iframeWindow.document.body.style.margin = '0';
    };
    iframeWindow.location = url;
}

/*function getCriticalSelectors(lcpElement) {
    const selectors = new Set();

    const add = (el) => {
        if (!el || !(el instanceof Element)) return;
        if (el.id) selectors.add(`#${CSS.escape(el.id)}`);
        el.classList.forEach(cls => {
            if (
                cls &&
                !["lazy", "loaded", "entered", "swiper-lazy", "swiper-slide-duplicate"].includes(cls)
            ) {
                selectors.add(`.${CSS.escape(cls)}`);
            }
        });
    };

    // LCP element ve parent’ları
    let current = lcpElement;
    while (current && current !== document.body) {
        add(current);
        current = current.parentElement;
    }

    // Viewport içindeki görünür elementler
    const viewportEls = document.querySelectorAll('body *');
    viewportEls.forEach(el => {
        const rect = el.getBoundingClientRect();
        const visible = rect.bottom > 0 && rect.top < window.innerHeight;
        const style = window.getComputedStyle(el);
        const isHidden = style.display === "none" || style.visibility === "hidden" || style.opacity === "0";
        if (visible && !isHidden) {
            add(el);
        }
    });

    // Temel yapılar
    add(document.body);
    add(document.documentElement);
    document.querySelectorAll('.swiper-slide-active, .swiper-slide-active *').forEach(add);

    return Array.from(selectors);
}*/

// measure-lcp.js dosyasında
function getCriticalSelectors(lcpElement) {
    const selectors = new Set();
    // Çok genel ve zararsız/filtrelenmesi zor etiketleri elemek için bir istisna listesi
    const ignoredTags = ['div', 'span', 'i', 'a', 'p', 'br', 'ul', 'li', 'svg', 'g', 'path', 'rect', 'use'];

    const add = (el) => {
        if (!el || !(el instanceof Element)) return;
        
        // Sadece ID'leri ekle
        if (el.id) selectors.add(`#${CSS.escape(el.id)}`);
        
        // Sadece Class'ları ekle
        el.classList.forEach(cls => {
            if (
                cls &&
                !["lazy", "loaded", "entered", "swiper-lazy", "swiper-slide-duplicate", "no-js"].includes(cls)
            ) {
                // Sadece temel (utility olmayan) veya benzersiz sınıfları eklemeyi düşün
                // Bu adım çok riskli olduğu için şimdilik tüm sınıfları ekliyoruz
                selectors.add(`.${CSS.escape(cls)}`);
            }
        });

        // Temel Element Etiketlerini Ekle (body, html) - Çok önemli
        const tag = el.tagName.toLowerCase();
        if (tag === 'body' || tag === 'html') {
             selectors.add(tag);
        }
    };

    // LCP element ve parent’ları
    let current = lcpElement;
    while (current && current !== document.body) {
        if (!ignoredTags.includes(current.tagName.toLowerCase())) {
            add(current);
        }
        current = current.parentElement;
    }
    // Body'i son kez ekle
    add(document.body);


    // Viewport içindeki görünür elementler
    // Burayı kısıtlıyoruz: Sadece LCP'yi ve ana yapıları hedefleyelim.
    // Tüm 'body *' taraması çok fazla gereksiz sınıf ekleyebilir.
    // Sadece Head ve Navigasyon gibi yapısal elementleri kontrol edelim.
    document.querySelectorAll('#header, #navigation, .main-nav, .swiper-slide-active, .swiper-slide-active *').forEach(add);

    return Array.from(selectors);
}

/*
// measure-lcp.js dosyasında
function getCriticalSelectors(lcpElement) {
    const selectors = new Set();
    
    // Eksik olan veya atlanan yapısal/utility sınıfları listesi
    const structuralClasses = [
        '.row', '.container', '.container-fluid', 
        '.col', '.col-sm', '.col-md', '.col-lg', '.col-xl', '.col-xxl', 
        '.d-flex', '.justify-content-center', '.align-items-center', 
        '.flex-column', '.my-auto', '.mx-auto',
    ];

    const add = (el) => {
        if (!el || !(el instanceof Element)) return;
        if (el.id) selectors.add(`#${CSS.escape(el.id)}`);
        el.classList.forEach(cls => {
            if (
                cls &&
                !["lazy", "loaded", "entered", "swiper-lazy", "swiper-slide-duplicate", "no-js"].includes(cls)
            ) {
                selectors.add(`.${CSS.escape(cls)}`);
            }
        });
        // Temel Element Etiketlerini Ekle (body, html) - Çok önemli
        const tag = el.tagName.toLowerCase();
        if (tag === 'body' || tag === 'html') {
             selectors.add(tag);
        }
    };

    // 1. LCP element ve tüm ebeveynleri
    let current = lcpElement;
    while (current && current !== document.body) {
        add(current);
        current = current.parentElement;
    }
    add(document.body);
    add(document.documentElement);
    
    // 2. YENİ EKLEME: Zorunlu Yapısal Sınıfları Garantiye Al
    // Bu, .row, .align-items-center gibi sınıfların atlanmamasını garanti eder.
    structuralClasses.forEach(selector => {
        document.querySelectorAll(selector).forEach(add);
    });

    // 3. Viewport içindeki Kilit Alanları Tara (Ana Header/Navigasyon)
    document.querySelectorAll('#header, #navigation, .main-nav, .swiper-slide-active, .swiper-slide-active *').forEach(add);
    
    // NOT: Bu tarama, eski geniş Viewport taramasından daha kısıtlıdır ve performansı artırır.
    
    return Array.from(selectors);
}*/