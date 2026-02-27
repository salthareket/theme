function init_lightGallery() {
    $(".lightgallery.init-me").each(function() {
        var $el = $(this); // Kapsamı korumak için değişkene atadık
        var $closestModal = $el.closest('.modal');

        // --- MODAL KONTROLÜ ---
        // Eğer modal içindeyse ve modal henüz tam açılmadıysa bekle
        if ($closestModal.length > 0 && !$closestModal.hasClass('show')) {
            $closestModal.one('shown.bs.modal', function() {
                init_lightGallery(); // Modal açılınca tekrar dene
            });
            return; // Mevcut döngüden çık
        }

        $el.removeClass("init-me");
        var id = $el.attr("id");
        if (IsBlank(id)) {
            id = "gallery-" + generateCode(5);
            $el.attr("id", id);
        }

        var gallery_type = $el.data("gallery-type");
        var lightbox = bool($el.data("lightbox"), true);
        var gallery_source = [];
        let plugins = [];

        // Plugin Belirleme
        if (gallery_type != "dynamic") {
            if ($el.find("[data-src]").length > 0 || $el.find("[data-video]").length > 0) {
                plugins = [lgVideo];
            }
        } else {
            gallery_source = window[id.replaceAll("-", "_")];
            var hasVideo = gallery_source && gallery_source.some(item => item.poster || item.video);
            if (hasVideo) plugins = [lgVideo];
        }

        if (gallery_type == "justified") {

        	if (typeof jQuery.type !== "function") {
                jQuery.type = function(obj) {
                    return Object.prototype.toString.call(obj).replace(/^\[object (.+)\]$/, "$1").toLowerCase();
                };
            }
            
            // setTimeout içinde $el kullanarak 'this' karmaşasını çözdük
            setTimeout(function() {
                $el.justifiedGallery({
                    captions: $el.data("item-captions"),
                    lastRow: $el.data("item-last-row"),
                    rowHeight: $el.data("item-height"),
                    margins: $el.data("item-margin"),
                    selector: '.col', // PHP'den gelen grid yapısına göre
                    imgSelector: 'img'
                })
                .on("jg.complete", function () {
                    $el.removeClass("loading-hide");
                    if (lightbox) {
                        lightGallery(document.getElementById(id), {
                            selector: ".gallery-item",
                            download: false,
                            galleryId: id,
                            getCaptionFromTitleOrAlt: false,
                            plugins: plugins,
                            licenseKey: "1111-1111-111-1111",
                            mobileSettings: { controls: true, showCloseIcon: true, download: false } 
                        });
                    }
                });
            }, 50); // Modal genişliğinin tam oturması için 50ms idealdir

        } else if (gallery_type == "dynamic") {
            $el.removeClass("loading-hide");
            let dynamicGallery = window.lightGallery(document.getElementById(id), {
                dynamic: true,
                dynamicEl: gallery_source,
                plugins: plugins,
                licenseKey: "1111-1111-111-1111",
                mobileSettings: { controls: true, showCloseIcon: true, download: false } 
            });
            $el.on("click", function() {
                dynamicGallery.openGallery(0);
            });

        } else {
            $el.removeClass("loading-hide");
            if (lightbox) {
                lightGallery(document.getElementById(id), {
                    selector: ".gallery-item",
                    download: false,
                    galleryId: id,
                    getCaptionFromTitleOrAlt: false,
                    plugins: plugins,
                    licenseKey: "1111-1111-111-1111",
                    mobileSettings: { controls: true, showCloseIcon: true, download: false } 
                });
            }
        }
    });
}