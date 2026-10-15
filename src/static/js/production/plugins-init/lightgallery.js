function init_lightGallery() {
    $(".lightgallery.init-me").each(function() {
        var $el = $(this);
        var $closestModal = $el.closest('.modal');

        if ($closestModal.length > 0 && !$closestModal.hasClass('show')) {
            $closestModal.one('shown.bs.modal', function() { init_lightGallery(); });
            return;
        }

        $el.removeClass("init-me");
        var id = $el.attr("id");
        if (IsBlank(id)) { id = "gallery-" + generateCode(5); $el.attr("id", id); }

        var gallery_type = $el.data("gallery-type");
        var lightbox = bool($el.data("lightbox"), true);
        var gallery_source = [];
        var plugins = [];

        // ── data-gallery-images ile tanımlı galeriler (product gallery, review gallery vb.) ─
        // Her zaman attribute'dan oku — jQuery .data() cache stale olabilir.
        // Bu yol shGalleryData'dan bağımsızdır: kendi görsellerini taşır.
        var galleryImages = null;
        try {
            var rawAttr = $el.attr('data-gallery-images');
            if (rawAttr) galleryImages = JSON.parse(rawAttr);
        } catch(e) {}
        if (!galleryImages || !galleryImages.length) {
            galleryImages = $el.data("gallery-images") || null;
        }

        // ── Ürün galerisi: shGalleryData'dan güncel variation görsellerini al ─────────────
        // Sadece data-product-gallery attr'u taşıyan parent'ı olan galeriler için.
        // Bu sayede review/blog/genel galeriler ürün değişkenini etkilemez.
        var $productGallery = $el.closest('[data-product-gallery]');
        if ($productGallery.length) {
            var productGalleryId = parseInt($productGallery.data('product-gallery')) || 0;
            var currentGalleryId = window.shGalleryCurrentId || productGalleryId || 0;
            var shData = (window.shGalleryData && currentGalleryId && window.shGalleryData[currentGalleryId])
                ? window.shGalleryData[currentGalleryId]
                : null;
            if (shData && shData.length) {
                galleryImages = shData.map(function(item) { return { src: item.src }; });
            }
        }

        if (galleryImages && Array.isArray(galleryImages) && galleryImages.length) {
            var prevInstance = $el.data('lg-instance');
            if (prevInstance) {
                try { prevInstance.destroy(); } catch(e) {}
                $el.removeData('lg-instance');
            }
            $el.off('click.pglg');

            var lgInstance = lightGallery(document.getElementById(id), {
                dynamic: true,
                dynamicEl: galleryImages,
                plugins: plugins,
                licenseKey: "1111-1111-111-1111",
                mobileSettings: { controls: true, showCloseIcon: true, download: false }
            });

            $el.data('lg-instance', lgInstance);

            $el.on('click.pglg', 'a.gallery-item, img.gallery-item', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Güncel görsel listesi: product gallery ise shGalleryData'dan, değilse attribute'dan
                var cImages = galleryImages;
                if ($productGallery.length) {
                    var cId = window.shGalleryCurrentId || 0;
                    var cData = (window.shGalleryData && cId && window.shGalleryData[cId])
                        ? window.shGalleryData[cId]
                        : null;
                    if (cData && cData.length) {
                        cImages = cData.map(function(item) { return { src: item.src }; });
                    }
                }

                // Tıklanan URL
                var clickedSrc = $(this).attr('href')
                    || $(this).data('lg-src')
                    || $(this).find('img').data('lg-src')
                    || $(this).find('img').attr('src')
                    || '';

                var idx = 0;
                if (clickedSrc && Array.isArray(cImages)) {
                    for (var i = 0; i < cImages.length; i++) {
                        if (cImages[i] && cImages[i].src === clickedSrc) { idx = i; break; }
                    }
                }

                // Instance yenile gerekirse
                var inst = $el.data('lg-instance');
                try {
                    if (!inst || JSON.stringify(cImages) !== JSON.stringify(galleryImages)) {
                        if (inst) { try { inst.destroy(); } catch(e2) {} $el.removeData('lg-instance'); }
                        inst = lightGallery(document.getElementById(id), {
                            dynamic: true,
                            dynamicEl: cImages,
                            plugins: plugins,
                            licenseKey: "1111-1111-111-1111",
                            mobileSettings: { controls: true, showCloseIcon: true, download: false }
                        });
                        $el.data('lg-instance', inst);
                        galleryImages = cImages;
                    }
                } catch(e3) {}

                inst.openGallery(idx);
            });
            return;
        }

        // ── Standart (selector bazlı) lightgallery ───────────────────────────────────────
        if (gallery_type != "dynamic") {
            if ($el.find("[data-src]").length > 0 || $el.find("[data-video]").length > 0) {
                plugins = [lgVideo];
            }
        } else {
            gallery_source = window[id.replaceAll("-", "_")];
            var hasVideoDyn = gallery_source && gallery_source.some(function(item) { return item.poster || item.video; });
            if (hasVideoDyn) plugins = [lgVideo];
        }

        if (gallery_type == "justified") {
            if (typeof jQuery.type !== "function") {
                jQuery.type = function(obj) {
                    return Object.prototype.toString.call(obj).replace(/^\[object (.+)\]$/, "$1").toLowerCase();
                };
            }
            setTimeout(function() {
                $el.justifiedGallery({
                    captions: $el.data("item-captions"),
                    lastRow: $el.data("item-last-row"),
                    rowHeight: $el.data("item-height"),
                    maxRowHeight: $el.data("item-height"),
                    margins: $el.data("item-margin"),
                    selector: '.gallery-item',
                    imgSelector: 'img'
                }).on("jg.complete", function() {
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
            }, 50);
        } else if (gallery_type == "dynamic") {
            $el.removeClass("loading-hide");
            var dynamicGallery = window.lightGallery(document.getElementById(id), {
                dynamic: true,
                dynamicEl: gallery_source,
                plugins: plugins,
                licenseKey: "1111-1111-111-1111",
                mobileSettings: { controls: true, showCloseIcon: true, download: false }
            });
            $el.on("click", function() { dynamicGallery.openGallery(0); });
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
