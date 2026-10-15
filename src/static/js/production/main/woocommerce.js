/**
 * WooCommerce JS — Sadece WC aktifken yüklenir.
 *
 * Buraya taşınan bölümler:
 *   1. WooCommerce Events (cart fragment update, store API intercept)
 *   2. Cart item kaldır (.btn-remove[data-source=cart])
 *   3. Product Gallery + found_variation listener
 *   4. Quantity Stepper
 *   5. Sticky Add to Cart
 *
 * Yükleme: minify-rules.php → plugins['woocommerce-main'] → "c": false, ENABLE_ECOMMERCE kontrolü
 * HandlesFrontendJs → loadDevJs / loadProductionJs zaten bu dosyayı sadece WC aktifse enqueue eder.
 */

(function($) {
    'use strict';

    // Guard — WC disabled ise hiç çalışma
    if (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.enable_ecommerce === false) {
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. WooCommerce Events
    // ─────────────────────────────────────────────────────────────────────────

    // Sepete ekleme/cikarma vs. sonrasi cart guncelle.
    $(document.body).on(
        "added_to_cart removed_from_cart wc_fragments_loaded updated_cart_totals",
        function(e) {
            // Cart dropdown guncelle
            var $cartDropdown = $(".dropdown-notifications[data-type='cart']").find(".dropdown-container");
            if ($cartDropdown.length > 0 && typeof cart !== "undefined") {
                cart.get($cartDropdown, "dropdown");
            }
            // Cart offcanvas guncelle
            var $cartOffcanvas = $("#offcanvasCart .load-container");
            if ($cartOffcanvas.length > 0 && typeof cart !== "undefined") {
                cart.get($cartOffcanvas, "offcanvas");
            }
        }
    );

    // WC Store API Intercept
    // Native WC cart/checkout block'lari Store API kullanir.
    // Fetch response'larindan items_count alip badge guncelle.
    (function() {
        var _origFetch = window.fetch;
        window.fetch = function(url, opts) {
            return _origFetch.apply(this, arguments).then(function(response) {
                // Sadece WC Store API cart endpoint'lerini dinle
                if (typeof url === "string" && url.indexOf("/wc/store/") !== -1 &&
                    (url.indexOf("/cart") !== -1 || url.indexOf("/batch") !== -1)) {
                    // Response'u clone et (body sadece 1 kez okunabilir)
                    response.clone().json().then(function(data) {
                        var count = null;

                        // Direkt cart response
                        if (data && typeof data.items_count !== "undefined") {
                            count = data.items_count;
                        }
                        // Batch response (remove-item vs.)
                        else if (data && data.responses && data.responses.length > 0) {
                            var body = data.responses[0].body;
                            if (body && typeof body.items_count !== "undefined") {
                                count = body.items_count;
                            }
                        }

                        if (count !== null && typeof cart !== "undefined") {
                            cart.updateBadge("cart", count);
                        }
                    }).catch(function() {});
                }
                return response;
            });
        };
    })();


    // ─────────────────────────────────────────────────────────────────────────
    // 2. Product Gallery + found_variation listener
    // ─────────────────────────────────────────────────────────────────────────

    $(document).ready(function () {

        // Thumb swiper'ları init et (swiper.js'de tanımlı)
        if (typeof init_product_gallery_thumbs === 'function') {
            init_product_gallery_thumbs();
        }

        // Variation seçilince galeri güncelle
        var _galleryPageLoaded = false; // ilk otomatik found_variation'ı atla
        var _galleryCache      = {};    // variation_id → { images, thumbImages }

        function _fetchAndUpdateGallery($gallery, productId, galleryType, callback) {
            if ( _galleryCache[productId] ) {
                callback( _galleryCache[productId] );
                return;
            }
            $gallery.addClass('loading-process');
            var query = new ajax_query();
            query.method = 'woo_get_variation_gallery';
            query.vars   = { product_id: productId, size: 'woocommerce_single' };
            query.done   = function(res) {
                $gallery.removeClass('loading-process');
                if ( ! res || res.error || ! res.data || ! res.data.images || ! res.data.images.length ) return;
                var data = {
                    images:     res.data.images,
                    thumbImages: res.data.thumb_images || res.data.images,
                    zoomImages:  res.data.zoom_images  || res.data.images
                };
                _galleryCache[productId] = data;
                callback(data);
            };
            query.request();
        }

        $(document).on('found_variation', function (e, variation) {
            var $gallery = $('[data-product-gallery]');

            // Variation description → ana ürün açıklamasını replace et
            var $shortDesc = $('.woocommerce-product-details__short-description');
            if ( $shortDesc.length ) {
                if ( ! $shortDesc.data('original-html') ) {
                    $shortDesc.data('original-html', $shortDesc.html());
                }
                if ( variation.variation_description && variation.variation_description.trim() !== '' ) {
                    $shortDesc.html('<p>' + variation.variation_description + '</p>');
                } else {
                    $shortDesc.html( $shortDesc.data('original-html') || '' );
                }
            }

            // WC'nin native variation description div'ini gizle
            $('.woocommerce-variation-description').hide();

            // Parent fiyat aralığını gizle — variation fiyatı .woocommerce-variation-price'ta görünür
            $('.summary .price').not('.woocommerce-variation-price .price').hide();

            if ( ! _galleryPageLoaded ) {
                _galleryPageLoaded = true;
                var initVarId = variation.variation_id || 0;
                if ( initVarId ) {
                    window.shGalleryCurrentId = initVarId;
                    var $lgInit = $('[data-product-gallery]').find('.lightgallery');
                    if ( $lgInit.length && typeof init_lightGallery === 'function' ) {
                        $lgInit.addClass('init-me');
                        setTimeout(init_lightGallery, 300);
                    }
                }
                // Ana ürün URL'inde (variation seçili değil) galeriyi güncelleme
                var hasVarInPath = (function() {
                    var $gal = $('[data-product-gallery]');
                    if (!$gal.length) return false;
                    var base = ($gal.data('product-gallery-base-url') || '').replace(/\/$/, '');
                    if (!base) return false;
                    var path = window.location.pathname.replace(/\/$/, '');
                    return path.length > base.length && path.indexOf(base) === 0;
                })();
                if (!hasVarInPath) return;
            }

            if ( ! $gallery.length ) return;

            var galleryType = $gallery.data('gallery-type') || 'swiper';
            var productId   = variation.variation_id || 0;
            if ( ! productId ) return;

            window.shGalleryCurrentId = productId;

            _fetchAndUpdateGallery($gallery, productId, galleryType, function(data) {
                if ( galleryType === 'swiper' ) {
                    _updateProductGallerySwiper($gallery, data.images, data.thumbImages, data.zoomImages);
                } else if ( galleryType === 'single' ) {
                    _updateProductGallerySingle($gallery, data.images[0], data.zoomImages ? data.zoomImages[0] : null);
                } else {
                    _updateProductGalleryStack($gallery, data.images, data.zoomImages);
                }
            });
        });

        // Variation sıfırlanınca (Clear) parent ürünün görsellerine dön
        $(document).on('reset_data', function () {
            $('.form-label[class*="title-pa_"]').each(function() {
                $(this).contents().filter(function(){ return this.nodeType === 3; }).first().replaceWith('');
            });

            // Ana ürün açıklamasını geri getir
            var $shortDesc = $('.woocommerce-product-details__short-description');
            if ( $shortDesc.length && $shortDesc.data('original-html') ) {
                $shortDesc.html( $shortDesc.data('original-html') );
            }
            // 200ms gecikmeli de uygula (diğer handler'lar sonra yazabilir)
            setTimeout(function(){
                if ( $shortDesc.length && $shortDesc.data('original-html') ) {
                    $shortDesc.html( $shortDesc.data('original-html') );
                }
                $('.woocommerce-variation-description').empty();
                $('.product-description').html( $shortDesc.data('original-html') || '' );
            }, 200);

            $('.woocommerce-variation-description').show();
            $('.summary .price').not('.woocommerce-variation-price .price').show();

            var $gallery = $('[data-product-gallery]');
            if ( ! $gallery.length ) return;

            var galleryType = $gallery.data('gallery-type') || 'swiper';
            var parentId    = parseInt( $gallery.data('product-gallery') ) || 0;
            if ( ! parentId ) return;

            _fetchAndUpdateGallery($gallery, parentId, galleryType, function(data) {
                window.shGalleryCurrentId = parentId;

                window.shGalleryData = window.shGalleryData || {};
                window.shGalleryData[parentId] = (data.images || []).map(function(url, i) {
                    return { src: url, zoom: (data.zoomImages && data.zoomImages[i]) ? data.zoomImages[i] : url };
                });

                if ( galleryType === 'swiper' ) {
                    _updateProductGallerySwiper($gallery, data.images, data.thumbImages, data.zoomImages);
                } else if ( galleryType === 'single' ) {
                    _updateProductGallerySingle($gallery, data.images[0], data.zoomImages ? data.zoomImages[0] : null);
                } else {
                    _updateProductGalleryStack($gallery, data.images, data.zoomImages);
                }
            });
        });

        // İlk yüklemede orijinal görselleri sakla
        var $gallery = $('[data-product-gallery]');
        if ( $gallery.length && ! $gallery.data('original-images') ) {
            var origImages = [];
            $gallery.find('img.product-gallery__img').each(function () {
                var src = $(this).attr('src') || $(this).data('src');
                if (src && src.indexOf('data:') !== 0) origImages.push(src);
            });
            if (origImages.length) {
                $gallery.data('original-images', origImages);
            }
        }
    });

    /**
     * Swiper galerisini yeni görsellerle güncelle.
     */
    function _updateProductGallerySwiper($gallery, images, thumbImages, zoomImages) {
        thumbImages = thumbImages || images;
        zoomImages  = zoomImages  || images;
        var $mainEl = $gallery.find('.product-gallery__main');
        if ( ! $mainEl.length ) return;

        var cfg = {};
        try { cfg = JSON.parse( $gallery.attr('data-gallery-config') || '{}' ); } catch(e) {}

        var ratioMap  = { '1x1': 'ratio-1x1', '4x3': 'ratio-4x3', '3x4': 'ratio-3x4', '16x9': 'ratio-16x9', 'original': '' };
        var ratioCls  = ( ratioMap[ cfg.ratio ] !== undefined ) ? ratioMap[ cfg.ratio ] : 'ratio-4x3';
        var ratioWrap = ratioCls ? 'ratio ' + ratioCls : '';
        var objectFit = cfg.object_fit || 'cover';
        var hover     = cfg.hover || 'none';
        var hasZoom     = hover === 'zoom'     || hover === 'both';
        var hasLightbox = hover === 'lightbox' || hover === 'both';

        if ( typeof swiperManager !== 'undefined' && swiperManager.instances.has($mainEl[0]) ) {
            var oldSwiper = swiperManager.instances.get($mainEl[0]);
            try { oldSwiper.destroy(true, true); } catch(e) {}
            swiperManager.instances.delete($mainEl[0]);
        }
        $mainEl.removeClass('swiper-slider-init');

        var $wrapper = $mainEl.find('.swiper-wrapper');
        $wrapper.empty();

        images.forEach(function (imgUrl, i) {
            var zoomUrl      = zoomImages[i] || imgUrl;
            var zoomAttr     = hasZoom     ? ' data-zoom="' + zoomUrl + '"' : '';
            var lightboxAttr = hasLightbox ? '<a href="' + imgUrl + '" class="gallery-item d-block w-100 h-100">' : '';
            var lightboxClose= hasLightbox ? '</a>' : '';
            var $slide = $(
                '<div class="swiper-slide">' +
                '<div class="product-gallery__frame' + (ratioWrap ? ' ' + ratioWrap : '') + '"' + zoomAttr + '>' +
                lightboxAttr +
                '<img src="' + imgUrl + '"' +
                ' alt=""' +
                ' class="product-gallery__img w-100 h-100 object-fit-' + objectFit + (i > 0 ? ' lazy' : '') + (hasLightbox ? ' gallery-item' : '') + '"' +
                (hasLightbox ? ' data-lg-src="' + imgUrl + '"' : '') +
                (i === 0 ? ' fetchpriority="high"' : ' data-src="' + imgUrl + '"') + '/>' +
                lightboxClose +
                '</div></div>'
            );
            $wrapper.append($slide);
        });

        var $thumbsEl = $gallery.find('[data-product-gallery-thumbs]');
        if ( $thumbsEl.length ) {
            if ( images.length > 1 ) {
                var $thumbWrapper = $thumbsEl.find('.swiper-wrapper');
                $thumbWrapper.empty();
                thumbImages.forEach(function (imgUrl, i) {
                    $thumbWrapper.append(
                        '<div class="swiper-slide product-gallery__thumb-slide' + (i === 0 ? ' active' : '') + '">' +
                        '<div class="ratio ratio-1x1 product-gallery__thumb-frame">' +
                        '<img src="' + imgUrl + '" alt="" class="product-gallery__thumb-img w-100 h-100 object-fit-' + objectFit + '" loading="lazy"/>' +
                        '</div></div>'
                    );
                });
                $thumbsEl.show();
            } else {
                $thumbsEl.hide();
            }
        }

        // Navigation okları — tek görselde gizle
        if ( images.length <= 1 ) {
            $mainEl.find('.swiper-button-prev, .swiper-button-next').hide();
            $mainEl.find('.swiper-pagination').hide();
            $mainEl.attr('data-loop', '0');
            $mainEl.removeClass('swiper-loop');
        } else {
            $mainEl.find('.swiper-button-prev, .swiper-button-next').show();
            $mainEl.find('.swiper-pagination').show();
            $mainEl.attr('data-loop', '1');
        }

        if (typeof init_swiper === 'function') {
            init_swiper($mainEl);
        }
        if (typeof init_product_gallery_thumbs === 'function') {
            $thumbsEl.removeClass('pg-thumbs-init');
            init_product_gallery_thumbs();
        }

        if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance) {
            lazyLoadInstance.update();
        }

        if (hasLightbox) {
            var lgImages = images.map(function(url){ return { src: url }; });
            $mainEl.data('gallery-images', lgImages);
            $mainEl.attr('data-gallery-images', JSON.stringify(lgImages));
            var prevLg = $mainEl.data('lg-instance');
            if (prevLg) {
                try { prevLg.destroy(); } catch(e) {}
                $mainEl.removeData('lg-instance');
            }
            if (typeof init_lightGallery === 'function') {
                $mainEl.addClass('init-me');
                setTimeout(init_lightGallery, 100);
            }
        }
    }

    /**
     * Single görsel galerisini güncelle.
     */
    function _updateProductGallerySingle($gallery, imgUrl, zoomUrl) {
        if (!imgUrl) return;
        zoomUrl = zoomUrl || imgUrl;

        var $img   = $gallery.find('img.product-gallery__img').first();
        var $frame = $gallery.find('.product-gallery__frame').first();
        var $link  = $frame.find('a.gallery-item');

        if ($img.length) {
            $img.attr('src', imgUrl).attr('data-src', imgUrl).attr('data-lg-src', imgUrl);
            $img.removeClass('lazy').addClass('entered loaded');
        }
        if ($link.length) $link.attr('href', imgUrl);
        if ($frame.attr('data-zoom')) $frame.attr('data-zoom', zoomUrl);

        if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance) lazyLoadInstance.update();
    }

    /**
     * Stack galeri güncelleme.
     */
    function _updateProductGalleryStack($gallery, images, zoomImages) {
        zoomImages = zoomImages || images;

        var cfg = {};
        try { cfg = JSON.parse( $gallery.attr('data-gallery-config') || '{}' ); } catch(e) {}
        var objectFit   = cfg.object_fit || 'cover';
        var hasLightbox = (cfg.hover || '') === 'lightbox' || (cfg.hover || '') === 'both';

        var $frames = $gallery.find('.product-gallery__frame');

        images.forEach(function(imgUrl, i) {
            var zoomUrl = zoomImages[i] || imgUrl;

            if ( i < $frames.length ) {
                var $frame = $frames.eq(i);
                var $img   = $frame.find('img.product-gallery__img');
                $img.attr('src', imgUrl).attr('data-src', imgUrl).attr('data-lg-src', imgUrl);
                $img.removeClass('lazy').addClass('entered loaded');
                if ( hasLightbox ) {
                    $frame.find('a.gallery-item').attr('href', imgUrl);
                }
                if ( $frame.attr('data-zoom') ) {
                    $frame.attr('data-zoom', zoomUrl);
                }
            } else {
                var lightboxOpen  = hasLightbox ? '<a href="' + imgUrl + '" class="gallery-item d-block w-100 h-100">' : '';
                var lightboxClose = hasLightbox ? '</a>' : '';
                var $newFrame = $(
                    '<div class="product-gallery__frame">' +
                    lightboxOpen +
                    '<img src="' + imgUrl + '" alt="" class="product-gallery__img w-100 h-100 object-fit-' + objectFit + ( hasLightbox ? ' gallery-item' : '' ) + '"' +
                    ' data-src="' + imgUrl + '"' +
                    ( hasLightbox ? ' data-lg-src="' + imgUrl + '"' : '' ) +
                    ' loading="lazy"/>' +
                    lightboxClose +
                    '</div>'
                );
                $gallery.append($newFrame);
            }
        });

        $gallery.find('.product-gallery__frame').filter(function(i) {
            return i >= images.length;
        }).remove();

        if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance) lazyLoadInstance.update();
    }


    // ─────────────────────────────────────────────────────────────────────────
    // 3. Quantity Stepper
    // ─────────────────────────────────────────────────────────────────────────

    $(document).on('click', '.sh-qty-stepper .qty-stepper__btn', function () {
        var $btn    = $(this);
        var $stepper = $btn.closest('.sh-qty-stepper');
        var $input  = $stepper.find('input.qty, .qty-stepper__input');
        if ( ! $input.length ) return;

        var step    = parseFloat( $input.attr('step') ) || 1;
        var min     = parseFloat( $input.attr('min') )  || 0;
        var max     = parseFloat( $input.attr('max') )  || Infinity;
        var current = parseFloat( $input.val() )        || min || 1;
        var dir     = $btn.data('qty-step') === -1 || $btn.hasClass('qty-stepper__btn--minus') ? -1 : 1;
        var next    = Math.min( Math.max( current + ( step * dir ), min ), max );

        if ( next === current ) return;

        $input.val( next ).trigger('change').trigger('input');
    });

    $(document).on('change input', '.sh-qty-stepper input.qty, .sh-qty-stepper .qty-stepper__input', function () {
        var $input = $(this);
        var val    = parseFloat( $input.val() );
        var min    = parseFloat( $input.attr('min') ) || 0;
        var max    = parseFloat( $input.attr('max') );
        if ( isNaN( val ) || val < min ) { $input.val( min ); return; }
        if ( ! isNaN( max ) && max > 0 && val > max ) $input.val( max );

        var $stepper = $input.closest('.sh-qty-stepper');
        $stepper.find('.qty-stepper__btn--minus').prop('disabled', parseFloat( $input.val() ) <= min );
        $stepper.find('.qty-stepper__btn--plus').prop('disabled', ! isNaN( max ) && max > 0 && parseFloat( $input.val() ) >= max );
    });


    // ─────────────────────────────────────────────────────────────────────────
    // 4. Sticky Add to Cart
    // ─────────────────────────────────────────────────────────────────────────

    (function() {
        var $bar = $('#sh-sticky-atc');
        if ( ! $bar.length ) return;

        var threshold = parseInt( $bar.data('threshold') ) || 300;
        var $mainBtn  = $('.single_add_to_cart_button').first();
        var $priceEl  = $bar.find('[data-sticky-price]');
        var $btnText  = $bar.find('.sh-sticky-btn-text');
        var $imgEl    = $bar.find('[data-sticky-img]');
        var $titleEl  = $bar.find('[data-sticky-title]');

        function syncPrice() {
            if ( ! $priceEl.length ) return;
            var $varPrice = $('.woocommerce-variation-price .price');
            if ( $varPrice.length && $varPrice.is(':visible') ) {
                $priceEl.html( $varPrice.html() );
                return;
            }
            var $srcPrice = $('.summary .price').not('.woocommerce-variation-price .price').first();
            if ( $srcPrice.length ) $priceEl.html( $srcPrice.html() );
        }

        function syncBtnText() {
            if ( ! $btnText.length || ! $mainBtn.length ) return;
            var mainText = $mainBtn.text().trim();
            if ( mainText ) $btnText.text( mainText );
        }

        function syncTitle() {
            if ( ! $titleEl.length ) return;
            var title = $('.product_title').text().trim();
            if ( title ) $titleEl.text( title );
        }

        function syncImg() {
            if ( ! $imgEl.length ) return;
            var $firstImg = $('[data-product-gallery]').find('.product-gallery__img').first();
            if ( ! $firstImg.length ) $firstImg = $('[data-product-gallery]').find('img').first();
            if ( $firstImg.length ) {
                var src = $firstImg.attr('src') || $firstImg.data('src');
                if ( src && src.indexOf('data:') !== 0 ) {
                    $imgEl.attr('src', src);
                    return;
                }
            }
        }

        function syncFromVariation( variation ) {
            if ( $priceEl.length && variation.price_html ) {
                $priceEl.html( variation.price_html );
            }
            if ( $imgEl.length && variation.image ) {
                var imgSrc = variation.image.thumb_src || variation.image.src || '';
                if ( imgSrc ) $imgEl.attr('src', imgSrc);
            }
            syncTitle();
            syncBtnText();
        }

        syncPrice();
        syncBtnText();
        syncTitle();
        syncImg();

        $(document).on('found_variation', function( e, variation ) {
            syncFromVariation( variation );
        });

        $(document).on('reset_data', function() {
            setTimeout(function() {
                syncPrice();
                syncBtnText();
                syncTitle();
                setTimeout( syncImg, 300 );
            }, 100);
        });

        $bar.removeClass('d-none');

        var ticking = false;
        function checkVisibility() {
            var shouldShow = false;
            if ( $mainBtn.length ) {
                var rect = $mainBtn[0].getBoundingClientRect();
                shouldShow = ( rect.bottom < 0 || rect.top > window.innerHeight ) && window.scrollY > threshold;
            } else {
                shouldShow = window.scrollY > threshold;
            }
            if ( shouldShow ) {
                $bar.addClass('is-visible').attr('aria-hidden', 'false');
            } else {
                $bar.removeClass('is-visible').attr('aria-hidden', 'true');
            }
        }

        $(window).on('scroll.sticky-atc resize.sticky-atc', function() {
            if ( ticking ) return;
            ticking = true;
            requestAnimationFrame(function() { checkVisibility(); ticking = false; });
        });

        setTimeout( checkVisibility, 500 );

        $('#sh-sticky-atc-btn').on('click', function() {
            if ( $mainBtn.length ) {
                $mainBtn[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function() { $mainBtn.trigger('click'); }, 400);
            }
        });
    })();

})(jQuery);
