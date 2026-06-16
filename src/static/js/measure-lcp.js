/**
 * LCP Element Data Collector
 * Her element tipine göre doğru veriyi toplar:
 * - image/img  → url (src), preload as="image"
 * - video      → url (poster), preload as="image"
 * - iframe     → url (src), preload as="document"
 * - text/block → font_url (custom font varsa), critical CSS (font-size, color, font-family, line-height)
 * - bg-image   → url (background-image url), preload as="image", critical CSS
 */
function lcp_data(metric, type) {
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

    if (!element) {
        return { type: "unknown", code: "", url: "", font_url: "", id: 0, selectors: [] };
    }

    // ─── HELPERS ─────────────────────────────────────────

    const isUnique = (selector) => {
        if (!selector || typeof selector !== "string") return false;
        selector = selector.trim();
        if (selector.startsWith(">")) selector = selector.substring(1).trim();
        try { return document.querySelectorAll(selector).length === 1; }
        catch (e) { return false; }
    };

    function getUniqueSelector(el) {
        if (!el || !(el instanceof Element)) return "";

        const ignoredClasses = ["loaded", "entered", "lazy", "plyr-init", "init-me", "inited", "ready", "paused", "stopped"];
        let selector = el.tagName.toLowerCase();

        if (el.classList.length > 0) {
            let validClasses = Array.from(el.classList)
                .filter(cls => cls.trim() !== "" && !ignoredClasses.includes(cls))
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

    // Computed style'dan belirli property'leri al
    function getStyles(el, props) {
        const cs = window.getComputedStyle(el);
        const result = {};
        props.forEach(p => {
            const val = cs.getPropertyValue(p);
            if (val && val !== 'none' && val !== 'normal' && val !== 'auto') {
                result[p] = val;
            }
        });
        return result;
    }

    // CSS object → string
    function stylesToCSS(selector, styles) {
        if (!selector || !Object.keys(styles).length) return "";
        let css = `${selector} {\n`;
        Object.entries(styles).forEach(([k, v]) => { css += `  ${k}: ${v};\n`; });
        css += "}\n";
        return css;
    }

    // Background-image URL'sini çıkar
    function getBgImageUrl(el) {
        const cs = window.getComputedStyle(el);
        const bg = cs.backgroundImage;
        if (!bg || bg === 'none') return "";
        const match = bg.match(/url\(["']?([^"')]+)["']?\)/);
        return match ? match[1] : "";
    }

    // Custom font URL'sini bul (font-face'den)
    function getFontUrl(fontFamily) {
        if (!fontFamily) return "";
        // Sistemin yüklü fontlarını kontrol et
        const systemFonts = ['Arial', 'Helvetica', 'Times', 'Georgia', 'Verdana', 'Tahoma', 'initial', 'inherit', 'sans-serif', 'serif', 'monospace'];
        const firstFont = fontFamily.split(',')[0].trim().replace(/['"]/g, '');
        if (systemFonts.some(f => firstFont.toLowerCase().includes(f.toLowerCase()))) return "";

        // document.fonts API ile font URL'sini bul
        if (document.fonts) {
            for (const font of document.fonts) {
                if (font.family.replace(/['"]/g, '').trim() === firstFont) {
                    // FontFace'in source URL'sini almak için stylesheet'leri tara
                    break;
                }
            }
        }

        // Stylesheet'lerden @font-face src'yi bul
        try {
            for (const sheet of document.styleSheets) {
                try {
                    for (const rule of sheet.cssRules || []) {
                        if (rule.type === CSSRule.FONT_FACE_RULE) {
                            const family = rule.style.getPropertyValue('font-family').replace(/['"]/g, '').trim();
                            if (family === firstFont) {
                                const src = rule.style.getPropertyValue('src');
                                const urlMatch = src.match(/url\(["']?([^"')]+\.(?:woff2|woff|ttf|otf))["']?\)/i);
                                if (urlMatch) return urlMatch[1];
                            }
                        }
                    }
                } catch(e) {} // cross-origin stylesheet erişim hatası
            }
        } catch(e) {}

        return "";
    }

    // ─── ELEMENT TYPE DETECTION ───────────────────────────

    const tag = element.tagName.toLowerCase();
    let lcpType = "text"; // default
    let preloadUrl = url || "";
    let fontUrl = "";
    let criticalCSS = "";
    const selector = getUniqueSelector(element);

    if (tag === "img") {
        // ── IMAGE ──
        lcpType = "image";
        preloadUrl = url || element.getAttribute("src") || element.getAttribute("data-src") || "";
        // img için critical CSS: aspect-ratio, width, height (layout shift önleme)
        const styles = getStyles(element, ['width', 'height', 'aspect-ratio', 'object-fit', 'object-position']);
        criticalCSS = stylesToCSS(selector, styles);

    } else if (tag === "video") {
        // ── VIDEO ──
        lcpType = "video";
        // Poster URL'sini al
        preloadUrl = element.getAttribute("data-poster") || element.getAttribute("poster") || url || "";
        // Plyr wrapper'dan dene
        if (!preloadUrl) {
            const plyrWrapper = element.closest(".plyr__video-wrapper");
            if (plyrWrapper) {
                const posterDiv = plyrWrapper.querySelector(".plyr__poster");
                if (posterDiv) {
                    const bgStyle = posterDiv.style.backgroundImage;
                    const match = bgStyle.match(/url\(["']?([^"')]+)["']?\)/);
                    if (match) preloadUrl = match[1];
                }
            }
        }
        // Video için CSS gerekmez - poster preload yeterli

    } else if (tag === "iframe") {
        // ── IFRAME ──
        lcpType = "iframe";
        preloadUrl = element.getAttribute("src") || url || "";
        // iframe için CSS gerekmez

    } else {
        // ── TEXT / BLOCK ELEMENT (h1, h2, h3, p, div, section, span...) ──
        lcpType = "text";
        preloadUrl = ""; // text için URL yok

        // Background-image var mı?
        const bgUrl = getBgImageUrl(element);
        if (bgUrl) {
            lcpType = "bg-image";
            preloadUrl = bgUrl;
        }

        // Critical CSS: text render için gerekli stiller
        const textProps = [
            'font-size', 'font-family', 'font-weight', 'font-style',
            'line-height', 'letter-spacing', 'color',
            'text-align', 'text-transform',
            'width', 'height', 'min-height',
            'display', 'padding', 'margin'
        ];
        const styles = getStyles(element, textProps);

        // background-image varsa onu da ekle
        if (bgUrl) {
            styles['background-image'] = `url('${bgUrl}')`;
            styles['background-size'] = window.getComputedStyle(element).backgroundSize || 'cover';
            styles['background-position'] = window.getComputedStyle(element).backgroundPosition || 'center';
        }

        criticalCSS = stylesToCSS(selector, styles);

        // Custom font varsa preload için URL'sini bul
        const fontFamily = window.getComputedStyle(element).fontFamily;
        fontUrl = getFontUrl(fontFamily);
        if (fontUrl) {
            preloadUrl = fontUrl; // font preload için
        }
    }

    const result = {
        type: lcpType,
        code: criticalCSS,
        url: preloadUrl,
        font_url: fontUrl,
        id: 0,
        selectors: getCriticalSelectors(element)
    };

    debugJS("LCP Data:", result);
    return result;
}

/*function lcp_data_save(metric, type = "desktop") {
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
}*/

function lcp_data_save(metric, type = "desktop") {
    // LCP verisini hazırla
    const lcpData = typeof lcp_data === 'function' ? lcp_data(metric, type) : metric;
    const pageUrl = window.location.href;

    log('LCP Kayıt Başladı -> ' + type, 'info');
    log(lcpData, 'group');

    const body = new URLSearchParams({
        action: "save_lcp_results",
        type: site_config.meta.type,
        id: site_config.meta.id,
        lang: site_config.user_language,
        lcp_data: JSON.stringify({ [type]: lcpData }),
        url: pageUrl
    }).toString();

    // keepalive: true → sayfa kapanırken bile isteği tamamlar
    fetch(ajax_request_vars.url_admin, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body,
        keepalive: true
    })
    .then(response => response.json())
    .then(data => {
        log(type + ' LCP kaydedildi: ' + JSON.stringify(data));
    })
    .catch(error => {
        log('LCP Kayıt Hatası: ' + error, 'error');
    });
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