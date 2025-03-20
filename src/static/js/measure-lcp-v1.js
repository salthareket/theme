function measureLCP() {
  const results = { desktop: null, mobile: null };

  function getSelector(el) {
    if (!el) return null;
    return el.tagName.toLowerCase() + (el.className ? "." + el.className.trim().replace(/\s+/g, ".") : "");
  }

  function generateInlineCSS(el) {
    if (!el || el.tagName === "IMG") return "";

    let cssRules = `${getSelector(el)} { `;
    const computedStyles = window.getComputedStyle(el);
    const appliedStyles = new Map();

    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) {
          if (rule.selectorText && el.matches(rule.selectorText)) {
            const style = rule.style;
            for (let i = 0; i < style.length; i++) {
              const prop = style[i];
              appliedStyles.set(prop, computedStyles.getPropertyValue(prop));
            }
          }
        }
      } catch (e) {
        console.warn("CSS kuralına erişilemedi:", e);
      }
    }

    if (el.hasAttribute("style")) {
      const inlineStyles = el.getAttribute("style").split(";");
      inlineStyles.forEach((rule) => {
        const [prop, value] = rule.split(":").map((s) => s.trim());
        if (prop && value) {
          appliedStyles.set(prop, value);
        }
      });
    }

    appliedStyles.forEach((value, prop) => {
      cssRules += `${prop}: ${value}; `;
    });

    cssRules += "} ";
    return cssRules;
  }

  function addInlineCSS(selector, css) {
    let styleTag = document.getElementById("lcp-inline-css");
    if (!styleTag) {
      styleTag = document.createElement("style");
      styleTag.id = "lcp-inline-css";
      document.head.appendChild(styleTag);
    }
    styleTag.textContent += css;
  }

  function getLCP(deviceType, win = window, callback) {
    if ("PerformanceObserver" in win) {
      const observer = new win.PerformanceObserver((entryList) => {
        const entries = entryList.getEntries();
        const lcpEntry = entries[entries.length - 1];

        if (lcpEntry && lcpEntry.element) {
          const element = lcpEntry.element;
          console.log(element);
          let lcpData = {
            element: element.tagName,
            startTime: lcpEntry.startTime,
            selector: getSelector(element),
            css: generateInlineCSS(element),
          };

          if (element.tagName === "IMG") {
            lcpData.src = element.currentSrc || element.src || null;
            if (element.hasAttribute("srcset")) {
              lcpData.srcset = element.getAttribute("srcset");
              lcpData.sizes = element.getAttribute("sizes") || null;
            }
          }

          results[deviceType] = lcpData;
          addInlineCSS(lcpData.selector, lcpData.css);
        }

        observer.disconnect();
        if (callback) callback();
      });

      observer.observe({ type: "largest-contentful-paint", buffered: true });
    }
  }

  // Desktop için LCP ölç
  getLCP(window.innerWidth <= 768 ? "mobile" : "desktop", window, () => {
    console.log("Desktop ölçüm tamamlandı:", results);
  });

  // Mobil testi için iframe oluştur
  const mobileFrame = document.createElement("iframe");
  mobileFrame.style.width = "430px";
  mobileFrame.style.height = "932px";
  mobileFrame.style.position = "absolute";
  mobileFrame.style.top = "0px";
  mobileFrame.style.transform = "scale(0.5)"; // %50 scale ekledim
  document.body.appendChild(mobileFrame);

  mobileFrame.onload = () => {
    mobileFrame.contentWindow.navigator.__defineGetter__('userAgent', function () {
      return "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Mobile Safari/537.36";
    });
    const script = `
    (function() {
      const results = { mobile: null };

      function getSelector(el) {
        if (!el) return null;
        return el.tagName.toLowerCase() + (el.className ? "." + el.className.trim().replace(/\\s+/g, ".") : "");
      }

      function generateInlineCSS(el) {
        if (!el || el.tagName === "IMG") return ""; // Eğer element IMG ise CSS ekleme

        let cssRules = \`\${getSelector(el)} { \`;
        const computedStyles = window.getComputedStyle(el);
        const appliedStyles = new Map();

        // Tüm stylesheet'leri gez
        for (const sheet of document.styleSheets) {
          try {
            for (const rule of sheet.cssRules) {
              if (rule.selectorText && el.matches(rule.selectorText)) {
                const style = rule.style;
                for (let i = 0; i < style.length; i++) {
                  const prop = style[i];
                  appliedStyles.set(prop, computedStyles.getPropertyValue(prop));
                }
              }
            }
          } catch (e) {
            console.warn("CSS kuralına erişilemedi:", e);
          }
        }

        // Inline stilleri de dahil et
        if (el.style) {
          for (let i = 0; i < el.style.length; i++) {
            const prop = el.style[i];
            appliedStyles.set(prop, computedStyles.getPropertyValue(prop));
          }
        }

        // Sadece gerçekten atanmış stilleri ekle
        appliedStyles.forEach((value, prop) => {
          cssRules += \`\${prop}: \${value}; \`;
        });

        cssRules += "} ";
        return cssRules;
      }

      function getLCP(deviceType, win = window, callback) {
        if ("PerformanceObserver" in win) {
          const observer = new win.PerformanceObserver((entryList) => {
            const entries = entryList.getEntries();
            const lcpEntry = entries[entries.length - 1];

            if (lcpEntry && lcpEntry.element) {
              const element = lcpEntry.element;
              console.log(element);
              let lcpData = {
                element: element.tagName,
                startTime: lcpEntry.startTime,
                selector: getSelector(element),
                css: generateInlineCSS(element),
              };

              if (element.tagName === "IMG") {
                lcpData.src = element.currentSrc || element.src || null;
                if (element.hasAttribute("srcset")) {
                  lcpData.srcset = element.getAttribute("srcset");
                  lcpData.sizes = element.getAttribute("sizes") || null;
                }
              }

              results[deviceType] = lcpData;
            }

            observer.disconnect();
            if (callback) callback();
          });

          observer.observe({ type: "largest-contentful-paint", buffered: true });
        }
      }

      getLCP('mobile', window, () => {
        window.parent.postMessage({ type: 'mobileLCP', data: results.mobile }, '*');
      });
    })();
    `;

    // iframe'e script ekle
    const scriptTag = document.createElement("script");
    scriptTag.type = "text/javascript";
    scriptTag.innerHTML = script;
    mobileFrame.contentWindow.document.body.appendChild(scriptTag);
  };

  mobileFrame.src = window.location.href;

  // Mobile ölçüm sonucu geldiğinde iframe'i sil ve AJAX ile gönder
  window.addEventListener("message", (event) => {
    if (event.data && event.data.type === "mobileLCP") {
      results.mobile = event.data.data;
      console.log("Final LCP Sonuçları:", results);
      console.log(site_config);
      if (site_config.meta.type != "" && site_config.meta.id != "") {
        // AJAX ile WordPress'e gönder
        fetch(ajax_request_vars.url_admin, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "save_lcp_results",
            type: site_config.meta.type,
            id: site_config.meta.id,
            lcp_data: JSON.stringify(results),
          }),
        })
        .then((response) => response.json())
        .then((data) => console.log("LCP sonuçları kaydedildi:", data));
      } else {
        console.log(site_config.meta);
        console.log(site_config.meta + " eksik...");
      }
      document.body.removeChild(mobileFrame);
    }
  });
}
measureLCP();