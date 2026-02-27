{
    init: function(meta = []) {
        var query = new ajax_query();
        query.method = "site_config";
        let vars = {
            meta : meta
        }
        query.vars = vars;
        query.request();
    },
    after: function(response, vars, form) {
        let _self = this;
        site_config = response;
        if(site_config.hasOwnProperty("nonce")){
            if(!IsBlank(site_config)){
               ajax_request_vars["ajax_nonce"] = site_config.nonce;
            }
        }
        if (site_config.hasOwnProperty("favorites")) {
            if(!IsBlank(favorites)){
                var favorites = $.parseJSON(site_config.favorites);
                if (favorites.length > 0) {
                    debugJS(favorites)
                    $(".nav-item[data-type='favorites']").addClass("active");
                    $(".btn-favorite").each(function() {
                        var id = parseInt($(this).attr("data-id"));
                        $(this).removeClass("active");
                        debugJS();
                        if (inArray(id, favorites)) {
                            $(this).addClass("active");
                        }
                    });
                }                
            }
        }

        if (site_config.cart > 0) {
            var counter = $(".nav-item[data-type='cart'] > a").find(".notification-count");
            if (counter.length == 0) {
                $(".nav-item[data-type='cart'] > a").prepend("<div class='notification-count'>" + site_config.cart + "</div>");
            }
        }
        $("body").removeClass("not-logged");
        if (site_config.logged) {
            //get_notifications();
        }

        if (site_config.hasOwnProperty("lcp")) {
            const platformKey = window.innerWidth <= 768 ? "m" : "d";
            const platformFull = window.innerWidth <= 768 ? "mobile" : "desktop";
            if (site_config.lcp[platformKey] === 0) {
                _self.loadLCPMeasure(platformFull);
                console.log("[LCP] Veri eksik, ölçüm scripti yükleniyor...");
            } else {
                console.log("[LCP] Veri zaten mevcut, ölçüme gerek yok.");
            }
        }
    },
    loadLCPMeasure: function(platform, measureScriptPath) {
        if ($("#lcp-main-js").length > 0) return;
        let _self = this;
        
        let script = document.createElement('script');
        script.id = 'lcp-main-js';
        script.src = ajax_request_vars.theme_url + 'vendor/salthareket/theme/src/static/js/measure-lcp.js';
        script.onload = function() {
            console.log("[LCP] Ana dosya yüklendi, şimdi kütüphaneye geçiliyor...");
            // Kendi kendini tekrar çağır ama bu sefer path'i boş yolla (2. adıma geçsin)
            _self.loadWebVitals(platform);
        };
        document.head.appendChild(script);
        return; // İlk yükleme başladığı için buradan çıkıyoruz
    },
    loadWebVitals: function(platform) {
        if ($("#lcp-measure-js").length > 0) return; // Zaten yüklendiyse tekrar yükleme

        let script = document.createElement('script');
        script.id = 'lcp-measure-js';
        script.src = ajax_request_vars.theme_url + 'static/js/plugins/web-vitals.js'; 
        script.onload = function () {
            webVitals.onLCP((metric) => {
                if (typeof lcp_data_save === 'function') {
                    console.log(metric, platform);
                    lcp_data_save(metric, platform);
                }
            });
        };
        document.head.appendChild(script);
    }
};