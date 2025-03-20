function lcp_data(metric, type) {
    if (!metric || !metric.attribution) return '';
    let element, url;
    if(typeof metric.attribution.lcpEntry !== "undefined"){
        element = metric.attribution.lcpEntry.element;
        url = metric.attribution.lcpEntry.url || "";
    }else{
        element = metric.entries[0].element;
        url = metric.entries[0].url || "";
    }
    console.log("----------------------------");
    console.log(metric)
    console.log(element)

    
    //if (!element) return '';

    let preloadTag, preloadType = ""

    function getSelector(el) {
        if (!el) return null;
        return el.tagName.toLowerCase() + (el.className ? "." + el.className.trim().replace(/\s+/g, ".") : "");
    }

    function generateInlineCSS(el) {
        if (!el || el.tagName === "IMG") return "";

        let cssRules = `${getSelector(el)} { `;
        const computedStyles = window.getComputedStyle(el);
        const appliedStyles = new Map();

        // Temel stil özelliklerini ekle
        const importantStyles = [
            'color', 'background-color', 'font-size', 'font-weight', 'font-family'
        ];

        importantStyles.forEach((prop) => {
            const value = computedStyles.getPropertyValue(prop);
            if (value) {
                appliedStyles.set(prop, value);
            }
        });

        // Sayfadaki tüm stil sayfalarını kontrol et
        for (const sheet of document.styleSheets) {
            try {
                for (const rule of sheet.cssRules) {
                    if (rule.selectorText && el.matches(rule.selectorText)) {
                        const style = rule.style;
                        for (let i = 0; i < style.length; i++) {
                            const prop = style[i];
                            // Yukarıdaki önemli stil özelliklerini göz ardı et
                            if (!importantStyles.includes(prop)) {
                                appliedStyles.set(prop, computedStyles.getPropertyValue(prop));
                            }
                        }
                    }
                }
            } catch (e) {
                console.warn("CSS kuralına erişilemedi:", e);
            }
        }

        // Inline stil eklemeleri
        if (el.hasAttribute("style")) {
            const inlineStyles = el.getAttribute("style").split(";");
            inlineStyles.forEach((rule) => {
                const [prop, value] = rule.split(":").map((s) => s.trim());
                if (prop && value) {
                    appliedStyles.set(prop, value);
                }
            });
        }

        // Tüm stil kurallarını CSS'e ekle
        appliedStyles.forEach((value, prop) => {
            cssRules += `${prop}: ${value}; `;
        });

        cssRules += "} ";
        return cssRules;
    }

    let img_url = "";
    let media_type = "";
    if(["IMG", "VIDEO", "IFRAME"].includes(element.tagName)){

        if (element.tagName === 'IMG') {
            img_url = url;
            preloadType = "image";
        } else if (element.tagName === 'VIDEO') {
            img_url = url;
            preloadType = "video";
        } else if (element.tagName === 'IFRAME') {
            preloadType = "document";
        } else if (getComputedStyle(element).backgroundImage.includes('url(')) {
            const bgImageUrl = getComputedStyle(element).backgroundImage.match(/url\("(.*?)"\)/);
            if (bgImageUrl) {
                img_url = bgImageUrl[1];
                preloadType = "image";
            }
        }
    } else {
        // Elementin arka plan resmini kontrol et
        const computedStyle = getComputedStyle(element);
        if (computedStyle.backgroundImage.includes('url(')) {
            const bgImageUrl = computedStyle.backgroundImage.match(/url\(["']?(.*?)["']?\)/);
            if (bgImageUrl) {
                img_url = bgImageUrl[1];
            }
        }
        preloadType = "css";
        preloadTag = generateInlineCSS(element);
    }
    let code = { 
        type: preloadType,
        code: preloadTag,
        url: img_url,
        id: 0
    };
    return code;
}
function lcp_data_save(metric, type="desktop"){
    const lcpData = lcp_data(metric, type);
    console.log(type+" LCP:", lcpData);
    fetch(ajax_request_vars.url_admin, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "save_lcp_results",
        type: site_config.meta.type,
        id: site_config.meta.id,
        lcp_data: JSON.stringify({ [type]: lcpData }),
      }),
    })
    .then(response => response.json())
    .then(data => console.log("Desktop LCP Sonuçları kaydedildi:", data));
   /**/
    if (type == "mobile" && window.opener) {
        self.close();
    }
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
