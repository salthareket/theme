document.addEventListener('wpcf7invalid', function(event) {
    var invalidFields = event.detail.apiResponse.invalid_fields;
    if (invalidFields.length === 1 && invalidFields[0].field === 'your-email') {
        var form = event.target;
        var messageElement = form.querySelector('.wpcf7-response-output');
            messageElement.classList.add('d-none');
        setTimeout(function() {
            messageElement.innerHTML = invalidFields[0].message;
            messageElement.classList.remove('d-none');
        }, 100);
    }
}, false);

// ── Product Tease — Multi-image hover zones ──────────────────
(function () {
    'use strict';

    function initTeaseImageZones(container) {
        var cards = (container || document).querySelectorAll('.tease-product');
        cards.forEach(function (card) {
            var zones = card.querySelectorAll('.tease-product__zone');
            if (!zones.length) return;

            var imgs = card.querySelectorAll('.tease-product__img-item');
            var dots = card.querySelectorAll('.tease-product__dot');
            if (imgs.length < 2) return;

            if (card.dataset.imgZoneInit) return;
            card.dataset.imgZoneInit = '1';

            function showIndex(idx) {
                imgs.forEach(function (img, i) {
                    if (i === idx) {
                        // Lazy görsel henüz yüklenmemişse yükle
                        if (img.dataset.src && !img.dataset.llStatus) {
                            img.src = img.dataset.src;
                            img.dataset.llStatus = 'loaded';
                            img.removeAttribute('data-src');
                        }
                        img.style.opacity = '1';
                        img.style.pointerEvents = '';
                        img.style.zIndex = '1';
                    } else {
                        img.style.opacity = '0';
                        img.style.pointerEvents = 'none';
                        img.style.zIndex = '0';
                    }
                });
                dots.forEach(function (dot, i) {
                    dot.classList.toggle('active', i === idx);
                });
            }

            zones.forEach(function (zone) {
                zone.addEventListener('mouseenter', function () {
                    showIndex(parseInt(zone.dataset.zone, 10));
                });
            });

            card.addEventListener('mouseleave', function () {
                showIndex(0);
            });
        });
    }

    // İlk yükleme
    document.addEventListener('DOMContentLoaded', function () {
        initTeaseImageZones();
    });

    // AJAX pagination sonrası yeniden init
    document.addEventListener('ajax_query:complete', function () {
        initTeaseImageZones();
    });

    // jQuery ile tetiklenen ajax_query:complete event'ini de yakala
    $(document).on('ajax_query:complete', function(event, obj) {
        var container = null;
        if (obj && obj.objs) {
            container = obj.objs.obj || obj.objs.container || null;
        }
        initTeaseImageZones(container ? $(container)[0] : null);
    });

    // AjaxInitManager ile de çalışsın
    window.init_tease_image_zones = function ($container) {
        initTeaseImageZones($container ? $container[0] : null);
    };

    // ── Swatch hover → görsel değiştir (crossfade) ──────────
    // ── Görsel hover → swatch highlight + tooltip (ters yön) ─
    function initTeaseSwatchHover(container) {
        var cards = (container || document).querySelectorAll('.tease-product');
        cards.forEach(function (card) {
            var swatches = card.querySelectorAll('.tease-product__swatch[data-swatch-img]');
            if (!swatches.length) return;
            if (card.dataset.swatchHoverInit) return;
            card.dataset.swatchHoverInit = '1';

            var firstImg = card.querySelector('.tease-product__img-item');
            if (!firstImg) return;

            var showTooltip = card.dataset.swatchTooltip === '1';

            // ── Crossfade img ──────────────────────────────────
            var crossImg = document.createElement('img');
            crossImg.className = firstImg.className;
            crossImg.alt = firstImg.alt;
            crossImg.style.cssText = firstImg.style.cssText;
            crossImg.style.opacity = '0';
            crossImg.style.zIndex = '2';
            crossImg.style.transition = 'opacity .25s ease';
            firstImg.parentNode.insertBefore(crossImg, firstImg.nextSibling);

            var originalSrc = firstImg.src || '';
            var currentSrc  = originalSrc;

            // ── Tooltip element ────────────────────────────────
            var tooltip = null;
            if (showTooltip) {
                tooltip = document.createElement('span');
                tooltip.className = 'tease-product__swatch-tooltip';
                tooltip.style.cssText = [
                    'position:absolute',
                    'bottom:calc(100% + 5px)',
                    'left:50%',
                    'transform:translateX(-50%)',
                    'background:rgba(0,0,0,.75)',
                    'color:#fff',
                    'font-size:10px',
                    'font-weight:600',
                    'padding:3px 7px',
                    'border-radius:4px',
                    'white-space:nowrap',
                    'pointer-events:none',
                    'opacity:0',
                    'transition:opacity .15s ease',
                    'z-index:10',
                ].join(';');
                // Swatches container'ına ekle (position:relative gerekli)
                var swatchesWrap = card.querySelector('.tease-product__swatches');
                if (swatchesWrap) {
                    swatchesWrap.style.position = 'relative';
                    swatchesWrap.appendChild(tooltip);
                }
            }

            // ── Helpers ────────────────────────────────────────
            function swapTo(newSrc) {
                if (newSrc === currentSrc) return;
                currentSrc = newSrc;
                crossImg.src = newSrc;
                crossImg.style.opacity = '1';
                firstImg.style.opacity = '0';
            }

            function resetImg() {
                if (currentSrc === originalSrc) return;
                currentSrc = originalSrc;
                crossImg.style.opacity = '0';
                firstImg.style.opacity = '1';
            }

            function highlightSwatch(index) {
                swatches.forEach(function (s) {
                    var isActive = s.dataset.swatchIndex == index;
                    s.style.borderColor = isActive ? 'rgba(0,0,0,.5)' : '';
                    s.style.transform   = isActive ? 'scale(1.2)' : '';
                });
            }

            function clearSwatchHighlight() {
                swatches.forEach(function (s) {
                    s.style.borderColor = '';
                    s.style.transform   = '';
                });
            }

            function showSwatchTooltip(name, swatchEl) {
                if (!tooltip || !name) return;
                tooltip.textContent = name;
                // Tooltip'i aktif swatch'ın üstüne konumlandır
                var swatchesWrap = card.querySelector('.tease-product__swatches');
                if (swatchesWrap && swatchEl) {
                    var wrapRect  = swatchesWrap.getBoundingClientRect();
                    var swatchRect = swatchEl.getBoundingClientRect();
                    var left = swatchRect.left - wrapRect.left + swatchRect.width / 2;
                    tooltip.style.left = left + 'px';
                }
                tooltip.style.opacity = '1';
            }

            function hideTooltip() {
                if (!tooltip) return;
                tooltip.style.opacity = '0';
            }

            // ── Swatch hover → görsel değiştir ────────────────
            swatches.forEach(function (swatch) {
                swatch.addEventListener('mouseenter', function () {
                    var img = swatch.dataset.swatchImg;
                    if (img) swapTo(img);
                    if (showTooltip) showSwatchTooltip(swatch.dataset.swatchName, swatch);
                });
                swatch.addEventListener('mouseleave', function () {
                    hideTooltip();
                });
            });

            // ── Görsel zone hover → swatch highlight ──────────
            // multiple_images varsa zone'lar var, her zone bir img index'e karşılık gelir
            // Swatch index = img index (aynı sırada ekleniyor)
            var zones = card.querySelectorAll('.tease-product__zone');
            zones.forEach(function (zone) {
                zone.addEventListener('mouseenter', function () {
                    var zoneIndex = parseInt(zone.dataset.zone, 10);
                    // Bu zone index'e karşılık gelen swatch'ı bul
                    var matchSwatch = card.querySelector('.tease-product__swatch[data-swatch-index="' + zoneIndex + '"]');
                    if (matchSwatch) {
                        highlightSwatch(zoneIndex);
                        if (showTooltip) showSwatchTooltip(matchSwatch.dataset.swatchName, matchSwatch);
                    }
                });
                zone.addEventListener('mouseleave', function () {
                    clearSwatchHighlight();
                    hideTooltip();
                });
            });

            card.addEventListener('mouseleave', function () {
                resetImg();
                clearSwatchHighlight();
                hideTooltip();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTeaseSwatchHover();
    });

    document.addEventListener('ajax_query:complete', function () {
        initTeaseSwatchHover();
    });

    // jQuery ile tetiklenen ajax_query:complete event'ini de yakala
    $(document).on('ajax_query:complete', function(event, obj) {
        var container = null;
        if (obj && obj.objs) {
            container = obj.objs.obj || obj.objs.container || null;
        }
        initTeaseSwatchHover(container ? $(container)[0] : null);
    });

    window.init_tease_swatch_hover = function ($container) {
        initTeaseSwatchHover($container ? $container[0] : null);
    };

})();
