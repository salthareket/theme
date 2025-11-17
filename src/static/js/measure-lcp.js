function lcp_data(metric, type) {
    console.log("is cached:" + site_config.cached);
    if (!metric || !metric.attribution || site_config.cached) return '';
    let element, url;
    if (typeof metric.attribution.lcpEntry !== "undefined") {
        element = metric.attribution.lcpEntry.element;
        url = metric.attribution.lcpEntry.url || "";
    } else {
        element = metric.entries[0].element;
        url = metric.entries[0].url || "";
    }

    console.log("----------------------------");
    console.log(metric);
    console.log(element);

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

    console.log(code);

    return code;
}

function lcp_data_save(metric, type = "desktop") {
    // sayfa tam yüklenince stil al
   // window.addEventListener("load", () => {
        const lcpData = lcp_data(metric, type);
        console.log("fetched")
        const pageUrl = window.location.href;
        console.log(type + " LCP:", lcpData);
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
                console.log(type + " LCP Sonuçları kaydedildi:", data);
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