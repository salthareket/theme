function lcp_data(metric, type) {
    if (!metric || !metric.attribution) return '';
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

        // CSS selector'ün sayfada unique olup olmadığını kontrol et
        const isUnique = (selector) => {
            if (!selector || typeof selector !== "string") return false;

            selector = selector.trim();
            if (selector.startsWith(">")) selector = selector.substring(1).trim(); // Baştaki ">" varsa kaldır

            try {
                return document.querySelectorAll(selector).length === 1;
            } catch (e) {
                console.error(`Geçersiz CSS seçici: ${selector}`, e);
                return false;
            }
        };

        // İlk olarak sadece tag + class'ı deneyelim
        let selector = el.tagName.toLowerCase();

        if (el.classList.length > 0) {
            let validClasses = Array.from(el.classList)
                .filter(cls => cls.trim() !== "" && !["loaded", "entered", "lazy"].includes(cls)) // Buraya filtreyi ekledim
                .map(cls => `.${CSS.escape(cls)}`)
                .join("");

            selector += validClasses;
        }

        if (isUnique(selector)) return selector; // Eğer unique ise işlemi bitir

        // Eğer ID varsa, direkt ID ile return yap (Başına `>` ekleme)
        if (el.id) return `#${CSS.escape(el.id)}`;

        // Eğer yukarıdaki işlemler yeterli olmadıysa, parent'ı kontrol et (Recursive)
        const getParentSelector = (element) => {
            if (!element || element.tagName.toLowerCase() === "html") return ""; // En üst seviyeye çıkınca dur

            let parentSelector = getUniqueSelector(element.parentElement); // Recursive olarak parent'ı al
            let newSelector = `${parentSelector} > ${selector}`.trim();

            return isUnique(newSelector) ? newSelector : getParentSelector(element.parentElement);
        };

        return getParentSelector(el);
    }

    function getElementStyles(el) {
        if (!el) return {};
        const computedStyles = window.getComputedStyle(el);
        let styles = {
            //"width": computedStyles.width,
            //"height": computedStyles.height,
            "font-size": computedStyles.fontSize,
            "font-family": computedStyles.fontFamily,
            "color": computedStyles.color,
            "background-color": computedStyles.backgroundColor,
            "padding": computedStyles.padding,
            "margin": computedStyles.margin,
            "border": computedStyles.border
        };

        // Eğer height "auto" ise, parent'ı kontrol et
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

    const selectorList = getCriticalSelectors(element);


    if (element) {
        preloadType = element.tagName.toLowerCase();
        switch(preloadType){
            case "img" :
                preloadType = "image";
                break;
            case "iframe" :
                preloadType = "document";
                break;
            case "video" :
                preloadType = "video";
                break;
            default :
                preloadType = "image";
                break;
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

    return code;
}

function lcp_data_save(metric, type = "desktop") {
    const lcpData = lcp_data(metric, type);
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

/*function getCriticalSelectors(element) {
    const selectors = new Set();

    const add = (el) => {
        if (!el || !(el instanceof Element)) return;
        if (el.id) selectors.add(`#${CSS.escape(el.id)}`);
        el.classList.forEach(cls => {
            if (cls && !["lazy", "loaded", "entered"].includes(cls)) {
                selectors.add(`.${CSS.escape(cls)}`);
            }
        });
    };

    // 1. LCP element ve parent’ları
    let current = element;
    while (current && current !== document.body) {
        add(current);
        current = current.parentElement;
    }

    // 2. Aktif slide içindeki tüm elemanlar (first viewport)
    const activeEls = document.querySelectorAll('.swiper-slide-active *');
    activeEls.forEach(el => add(el));

    return Array.from(selectors);
}*/
function getCriticalSelectors(lcpElement) {
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
}

