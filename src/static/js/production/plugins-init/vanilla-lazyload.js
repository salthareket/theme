let lazyLoadInstance;

window.addEventListener('unhandledrejection', function(event) {
    // "The fetching process for the media resource was aborted" hatasını yakala
    if (event.reason && event.reason.name === 'AbortError' || (event.reason.message && event.reason.message.includes('aborted'))) {
        event.preventDefault(); // Konsola basılmasını engelle
        debugJS("Video yüklemesi kasıtlı olarak durduruldu, hata susturuldu.");
    }
});

function init_vanilla_lazyload(){
    lazyLoadInstance = new LazyLoad({
        elements_selector: ".lazy",
        // Swiper içindeki video/iframe'lerin sadece aktif slide'da yüklenmesi için optimizasyon
        callback_loading: function(e) {
            var obj = $(e);

            let slide = $(e).closest(".swiper-slide");
            if (slide.length > 0 && !slide.hasClass("swiper-slide-active")) {
                return false; // Native lazyload/loading sürecini iptal eder
            }

            if (obj[0].nodeName == 'IFRAME' && obj.hasClass('video')) {
                let slide = obj.closest(".swiper-slide");
                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("loading");
                    obj.removeAttr("data-ll-status");
                    debugJS("Swiper aktif değil, iframe src yüklenmeyecek.");
                    return false;
                }
            }
        },
        callback_loaded: function(e) {
            var obj = $(e);

            if (obj.hasClass("ratio")) {
                obj.parent().removeClass("loading").removeClass("loading-hide");
            }
            if (obj[0].nodeName == 'IMG') {
                obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                if (obj.parent().hasClass("img-placeholder")) {
                    obj.closest(".img-placeholder").removeClass("loading loading-hide loading-process");
                }
            }

            // Yeniden düzenleme (layout) gerektiren kütüphaneler için
            if ($("[data-masonry]").length > 0) $("[data-masonry]").data('masonry').layout();
            if (obj.closest("[data-isotope]").length > 0) {
                obj.closest("[data-isotope]").data('isotope').layout().reloadItems();
            }

            if (obj.hasClass("video")) {
                let slide = obj.closest(".swiper-slide");

                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("loaded");
                    obj.removeAttr("data-ll-status");
                    debugJS("Swiper slide aktif değil, video yüklenmeyecek. calvack_loaded");
                    return false;
                }

                obj.closest(".lazy-container").removeClass("lazy-container");
                obj.parent().find(">.plyr__poster").remove();
                obj.parent().addClass("lazy-loaded");
                init_plyr(obj.parent()); // init_plyr varsayılıyor
            }

            $.fn.matchHeight._update();
        },
        callback_error: function(e) {
            var obj = $(e);
            if (obj[0].nodeName === 'VIDEO') {
                return; 
            }
            if (obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")) {
                // Placeholder görseli yükle
                obj.attr("data-src", ajax_request_vars.theme_url + "/static/img/placeholder/img-" + obj.attr("data-placeholder") + ".jpg");
                obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                if (obj.parent().hasClass("img-placeholder")) {
                    obj.unwrap();
                }
                LazyLoad.load(obj[0]);
            }
        },
        callback_enter: function(e) {
            var obj = $(e);

            let slide = obj.closest(".swiper-slide");
            if (slide.length > 0 && !slide.hasClass("swiper-slide-active")) {
                // Bu elemanı şimdilik atla, yüklemeye çalışma
                return false; 
            }

            // Placeholder kontrolü
            if (obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")) {
                if (IsBlank(obj.attr("data-src")) && IsBlank(obj.attr("src"))) {
                    obj.attr("data-src", ajax_request_vars.theme_url + "/static/img/placeholder/img-" + obj.attr("data-placeholder") + ".jpg");
                    obj.closest(".loading, .loading-hide, .loading-process").removeClass("loading loading-hide loading-process");
                    LazyLoad.load(obj[0]);
                }
            }

            // Swiper video kontrolü (src yüklemesini engelleme)
            if (obj[0].nodeName == 'IFRAME' && obj.hasClass('video')) {
                let slide = obj.closest(".swiper-slide");
                if (slide.length > 0 && slide.index() > 0) {
                    obj.removeClass("entered");
                    obj.removeAttr("data-ll-status");
                    obj.attr("data-src-backup", obj.attr("data-src"));
                    obj.removeAttr("data-src");
                    debugJS("Swiper aktif değil, iframe src yüklenmeyecek.");
                    return false;
                }
            }

            // AOS yenileme
            if (typeof window["AOS"] === "object") {
                AOS.refreshHard();
            }

            // Tembel Fonksiyon Çağrısı
            var lazyFunctionName = e.getAttribute("data-lazy-function");
            if (lazyFunctionName) {
                var lazyFunction = window.lazyFunctions[lazyFunctionName];
                if (lazyFunction) lazyFunction(e);
            }
        }
    });

    // LazyLoad Olay Dinleyicileri
    document.addEventListener('lazyloaded', function(e) {
        var obj = $(e.target);
        if (obj.hasClass("swiper-bg")) {
            obj.closest(".swiper-slide").addClass("image-loaded");
        }
        $.fn.matchHeight._update();
    });

    $(document).on('lazyload', function(e) {
        $.fn.matchHeight._update();
        $(window).trigger("resize");
    });
}