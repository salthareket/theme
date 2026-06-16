/**
 * SwiperManager - Swiper.js initialization and management system
 * Handles all swiper-related functionality in a clean, modular way
 */
class SwiperManager {
    constructor() {
        this.instances = new Map();
        this.slideColorUpdateTimeout = null;
        this.luminanceCache = new WeakMap();
        
        // Configuration constants
        this.CONSTANTS = {
            INIT_TOKEN: 'swiper-slider-init',
            LUMINANCE_THRESHOLD: 0.6,
            DEBOUNCE_DELAY: 40,
            DEFAULT_DELAY: 5000,
            DEFAULT_SPEED: 750
        };
    }

    /**
     * Luminance Analysis Methods
     */
    getAverageLuminance(element) {
        return new Promise((resolve) => {
            if (!element) {
                console.warn("Element bulunamadı.");
                resolve(1); // Beyaz
                return;
            }

            // Cache kontrolü
            if (this.luminanceCache.has(element)) {
                return resolve(this.luminanceCache.get(element));
            }

            // Eğer video slide ise ve plyr__poster varsa → onun background'ına bak
            if (element.classList.contains("swiper-slide-video")) {
                const poster = element.querySelector(".plyr__poster");
                if (poster) {
                    const style = getComputedStyle(poster);
                    const bgImage = style.backgroundImage;
                    const bgColor = style.backgroundColor;
                    const imageUrlMatch = bgImage.match(/url\(["']?(.*?)["']?\)/);
                    if (imageUrlMatch && imageUrlMatch[1]) {
                        return requestIdleCallback(() => {
                            this.getImageLuminance(imageUrlMatch[1]).then(luminance => {
                                this.luminanceCache.set(element, luminance);
                                resolve(luminance);
                            });
                        });
                    }

                    if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                        const luminance = this.getComputedLuminance(poster);
                        this.luminanceCache.set(element, luminance);
                        return resolve(luminance);
                    }
                }
            }

            const img = element.querySelector("img");
            const hasVisibleImage = img && img.naturalWidth > 0 && getComputedStyle(img).display !== 'none';

            if (!hasVisibleImage) {
                console.warn("Görsel yok ya da görünmüyor, arka plan rengi kontrol ediliyor…");

                // bgColor varsa onu al, gradient ise html2canvas kullan
                const bg = getComputedStyle(element).backgroundImage;
                const bgColor = getComputedStyle(element).backgroundColor;

                debugJS(bg)

                if (bg && bg.includes("gradient")) {
                    debugJS("bg gradient içeriyor");
                    return requestIdleCallback(() => {
                        this.getRenderedLuminance(element).then(luminance => {
                            this.luminanceCache.set(element, luminance);
                            resolve(luminance);
                        });
                    });
                }

                if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                    const luminance = this.getComputedLuminance(element);
                    this.luminanceCache.set(element, luminance);
                    return resolve(luminance);
                }

                // hiçbiri yoksa son çare
                debugJS("son care");
                return requestIdleCallback(() => { 
                    this.getRenderedLuminance(element).then(luminance => {
                        this.luminanceCache.set(element, luminance);
                        resolve(luminance);
                    });
                });
            }

            // lazy-load olabilir
            if (!img.complete || img.naturalWidth === 0 || img.naturalHeight === 0) {
                console.warn("Resim yüklenmemiş, onload bekleniyor:", img.src);
                img.onload = () => this.processImage(img, (luminance) => {
                    this.luminanceCache.set(element, luminance);
                    resolve(luminance);
                });
                img.onerror = () => {
                    console.error("Resim yüklenirken hata:", img.src);
                    const luminance = this.getComputedLuminance(element);
                    this.luminanceCache.set(element, luminance);
                    resolve(luminance);
                };
                return;
            }

            // her şey tamamsa img ile devam
            this.processImage(img, (luminance) => {
                this.luminanceCache.set(element, luminance);
                resolve(luminance);
            });
        });
    }
    processImage(img, resolve) {
        try {
            if (img.naturalWidth === 0 || img.naturalHeight === 0) {
                console.error("Resmin genişliği veya yüksekliği sıfır:", img.src);
                resolve(1); // Beyaz olarak varsayılan parlaklık
                return;
            }

            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d");

            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;

            ctx.drawImage(img, 0, 0, img.naturalWidth, img.naturalHeight);

            // Eğer resim yüklenmemişse getImageData çalışmaz, bu yüzden güvenli hale getiriyoruz
            let imageData;
            try {
                imageData = ctx.getImageData(0, 0, img.naturalWidth, img.naturalHeight);
            } catch (e) {
                console.error("getImageData hatası:", e);
                resolve(this.getComputedLuminance(img)); // Arka plan rengini kullan
                return;
            }

            const data = imageData.data;
            let sumLuminance = 0;
            const totalPixels = data.length / 4;

            for (let i = 0; i < data.length; i += 4) {
                const r = data[i];
                const g = data[i + 1];
                const b = data[i + 2];
                const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
                sumLuminance += luminance;
            }

            resolve(sumLuminance / totalPixels);
        } catch (e) {
            console.error("processImage hatası:", e);
            resolve(1); // Beyaz olarak varsayılan parlaklık
        }
    }

    getImageLuminance(imageUrl) {
        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = "Anonymous";
            img.src = imageUrl;

            img.onload = () => this.processImage(img, resolve);

            img.onerror = () => {
                console.error("getImageLuminance: Resim yüklenemedi:", imageUrl);
                resolve(1); // Varsayılan parlaklık beyaz
            };
        });
    }

    getComputedLuminance(element) {
        const style = getComputedStyle(element);
        const bgColor = style.backgroundColor;
        const rgb = bgColor.match(/\d+/g)?.map(Number) || [255, 255, 255]; // Varsayılan beyaz
        const [r, g, b] = rgb;
        return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    }

    getAverageColorFromGradient(gradient) {
        const matches = gradient.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/g);
        if (!matches) return [255, 255, 255];
        let r = 0, g = 0, b = 0;
        matches.forEach(m => {
            const nums = m.match(/\d+/g).map(Number);
            r += nums[0];
            g += nums[1];
            b += nums[2];
        });
        const len = matches.length;
        return [Math.round(r / len), Math.round(g / len), Math.round(b / len)];
    }

    getComputedLuminanceFromGradient(gradient) {
        const [r, g, b] = this.getAverageColorFromGradient(gradient);
        return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    }
    /**
     * Render Edilen İçeriği Analiz Eden Fonksiyon (Optimize Edilmiş)
     * html-to-image kullanımını minimize eder (500ms kazanç)
     */
    getRenderedLuminance(element) {
        return new Promise((resolve) => {
            try {
                const style = getComputedStyle(element);
                const bgColor = style.backgroundColor;
                const bgImage = style.backgroundImage;

                // 1️⃣ Gradient varsa → matematiksel hesaplama (html-to-image KULLANMA!)
                if (bgImage && bgImage.includes('gradient')) {
                    return resolve(this.getComputedLuminanceFromGradient(bgImage));
                }

                // 2️⃣ Solid color varsa → direkt hesapla (html-to-image KULLANMA!)
                if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                    return resolve(this.getComputedLuminance(element));
                }

                // 3️⃣ İçerik yoksa → varsayılan değer (html-to-image KULLANMA!)
                const hasContent = (element.innerText && element.innerText.trim().length > 0) || 
                                   element.querySelector('i, svg, canvas, .icon');
                
                if (!hasContent) {
                    return resolve(0.5); // Nötr değer (ne açık ne koyu)
                }

                // 4️⃣ SADECE gerçekten gerekirse html-to-image kullan
                // (Karmaşık içerik + transparent background durumu - çok nadir)
                log('[Swiper] html-to-image kullanılıyor (nadir durum)', 'warn');
                
                if (typeof htmlToImage === 'undefined') {
                    requirePlugin("html-to-image", () => {
                        htmlToImage.toCanvas(element, {
                            pixelRatio: 0.1,
                            skipFonts: true,
                            backgroundColor: bgColor || '#ffffff'
                        }).then(canvas => {
                            const ctx = canvas.getContext("2d");
                            const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                            let total = 0;
                            for (let i = 0; i < data.length; i += 4) {
                                total += (0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) / 255;
                            }
                            resolve(total / (data.length / 4));
                        }).catch(() => resolve(this.getComputedLuminance(element)));
                    });
                    return;
                }

                htmlToImage.toCanvas(element, {
                    pixelRatio: 0.1,
                    skipFonts: true,
                    backgroundColor: bgColor || '#ffffff'
                }).then(canvas => {
                    const ctx = canvas.getContext("2d");
                    const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    let total = 0;
                    for (let i = 0; i < data.length; i += 4) {
                        total += (0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) / 255;
                    }
                    resolve(total / (data.length / 4));
                }).catch(() => resolve(this.getComputedLuminance(element)));
            } catch (e) {
                log('[Swiper] getRenderedLuminance hatası: ' + e.message, 'error');
                resolve(0.5); // Hata durumunda nötr değer
            }
        });
    }

    /**
     * Parlaklık Değerine Göre Body'e Class Basan Yardımcı
     */
    applyLuminanceClass(luminance) {
        const threshold = 0.5; // 0.5 altı koyu (dark), üstü açık (light)
        if (luminance < threshold) {
            document.body.classList.add("slide-dark");
            document.body.classList.remove("slide-light");
        } else {
            document.body.classList.add("slide-light");
            document.body.classList.remove("slide-dark");
        }
    }

    /**
     * Slide Renklerini Güncelleyen Ana Fonksiyon (Optimize Edilmiş)
     */
    async updateSlideColors(slider) {
        // 1. Senin orijinal slide bulma mantığın
        let activeSlide = slider.querySelector('.swiper-slide-active');
        if (!activeSlide) {
            activeSlide = slider.querySelector('.swiper-slide');
        }
        if (!activeSlide) return;

        // 2. ÖNCE CACHE'E BAK (Performans için yeni ekledik)
        // Eğer bu slide'ı daha önce hesapladıysak direkt hafızadan oku
        const cachedLuminance = activeSlide.getAttribute('data-luminance');
        let luminance;

        if (cachedLuminance !== null) {
            luminance = parseFloat(cachedLuminance);
        } else {
            // Hesapla ve bir sonraki sefer için slide'ın üzerine kaydet
            luminance = await this.getAverageLuminance(activeSlide);
            activeSlide.setAttribute('data-luminance', luminance);
        }

        // 3. Senin orijinal renk uygulama mantığın
        slider.classList.remove("slide-light", "slide-dark");
        activeSlide.classList.remove("slide-light", "slide-dark");

        const isDark = luminance === 0 ? false : luminance < this.CONSTANTS.LUMINANCE_THRESHOLD;
        const className = isDark ? 'slide-dark' : 'slide-light';

        activeSlide.classList.add(className);
        slider.classList.add(className);
    }

    safeUpdateSlideColors(obj, params = []) {
        clearTimeout(this.slideColorUpdateTimeout);
        this.slideColorUpdateTimeout = setTimeout(() => {
            // Eğer birden fazla slide görünüyorsa
            if (params?.slidesPerView > 1) {
                obj.classList.remove('slide-dark');
                obj.classList.add('slide-light');
            } else {
                // Tek slide görünüyorsa normal işlem
                this.updateSlideColors(obj);
            }
        }, this.CONSTANTS.DEBOUNCE_DELAY); // debounce
    }
    /**
     * Video Integration Methods
     */
    initSwiperVideoSlide(swiper, obj) {
        if (isLoadedJS("plyr")) {
            if (obj.find(".swiper-video").not(".inited").length > 0) {
                var video_slide = obj.find(".swiper-video").not(".inited");
                video_slide.addClass("inited");
                const player = init_plyr(video_slide.find('.player'));
                video_slide.data("plyr", player);
                return player;
            } else {
                if (obj.find(".swiper-video.inited").length > 0) {
                    return obj.find(".swiper-video.inited").data("plyr");
                }
            }
        }
    }

    initSwiperVideo(swiper) {
        if ($(swiper.el).find(".swiper-video").not(".inited").length > 0) {

            if (swiper.slides && swiper.slides.length === 0) {
                return;
            }

            var video = this.initSwiperVideoSlide(swiper, $(swiper.slides[0]));
            if (swiper.slides.length > 1) {
                const self = this; // SwiperManager instance'ını sakla
                swiper
                .on('slideChangeTransitionStart touchStart', function() {
                    var swiper = this;
                    var slide = $(swiper.slides[swiper.previousIndex]);

                    if (slide.find(".swiper-video").length > 0) {
                        slide.addClass("user-reacted");
                        var video_slide = $(swiper.slides[(swiper.activeIndex > swiper.previousIndex ? swiper.activeIndex : swiper.previousIndex)]);

                        if (slide.find(".plyr").length > 0) {
                            var video = slide.find(".plyr")[0].plyr;
                        } else {
                            video = self.initSwiperVideoSlide(swiper, slide);
                        }

                        if (video) {
                            if (video.playing) {
                                video.rewind(0);
                                video.pause();
                            }
                        }

                        if ($(swiper.el).data("sliderAutoplay")) {
                            if (swiper.params.autoplay.delay > 0 && !swiper.autoplay.running) {
                                swiper.autoplay.start();
                            }
                        }

                        slide.removeClass("paused").removeClass("playing");
                    }
                })
                .on('slideChangeTransitionEnd touchEnd', function() {
                    var swiper = this;
                    var slide = $(swiper.slides[swiper.activeIndex]);
                    if (slide.find(".swiper-video").length > 0) {

                        slide.find('iframe[data-src]').each(function() {
                            debugJS(this)
                            LazyLoad.load(this); // elle yükle
                            init_plyr($(this).closest(".video"));
                        });
                        slide.removeClass("user-reacted");
                        
                        if (slide.find(".plyr").length > 0) {
                            var video = slide.find(".plyr")[0].plyr;
                        } else {
                            video = self.initSwiperVideoSlide(swiper, slide);
                        }

                        if (slide.find(".swiper-video").length > 0) {
                            if (video && video.autoplay) {
                                if (slide.hasClass("ready")) {
                                   video.play();
                                } else {
                                    video.on('ready', (event) => {
                                       const instance = event.detail.plyr;
                                             instance.play();
                                    });                         
                                }
                                if ($(swiper.el).data("sliderAutoplay")) {
                                    if (swiper.params.autoplay.delay > 0 && swiper.autoplay.running) {
                                        swiper.autoplay.stop();
                                    }
                                }
                            } else {
                                if ($(swiper.el).data("sliderAutoplay")) {
                                    if (swiper.params.autoplay.delay > 0 && !swiper.autoplay.running) {
                                        swiper.autoplay.start();
                                    }
                                }
                            }
                        }

                    } else {

                        if ($(swiper.el).data("sliderAutoplay")) {
                            if (swiper.params.autoplay.delay > 0 && !swiper.autoplay.running) {
                                swiper.autoplay.start();
                            }
                        }

                    }
                });         
            }
        }
    }
    /**
     * Main Swiper Initialization Methods
     */
    initSwiper($obj) {
        if (!IsBlank($obj)) {
           if ($obj.not("." + this.CONSTANTS.INIT_TOKEN).length > 0) {
              $obj.addClass(this.CONSTANTS.INIT_TOKEN);
              return this.initSwiperObj($obj);
           }
        } else {
            $(".swiper-slider").not("." + this.CONSTANTS.INIT_TOKEN).each((index, element) => {
                $(element).addClass(this.CONSTANTS.INIT_TOKEN);
                this.initSwiperObj($(element));
            });
        }
    }
    initSwiperObj($obj) {
        if ($obj.find(".swiper-slide").length < 2) {
            this.initSwiperVideoSlide([], $obj);
            if ($obj.hasClass("fade")) {
                $obj.addClass("show");
            }
            if ($obj.hasClass("loading")) {
                $obj.removeClass("loading");
            }
            //remove if parent has loading
            if ($obj.closest(".loading").length > 0) {
                $obj.closest(".loading").removeClass("loading");
            }
            this.safeUpdateSlideColors($obj[0]);
            return;
        }
  
        var effect = $obj.data("slider-effect") ?? "slide";
        var crossFade = false;
        if (effect == "fade") {
           var crossFade = bool($obj.data("slider-cross-fade"), crossFade);
        }
        var auto_height = bool($obj.data("slider-autoheight"), false);
        var navigation = bool($obj.data("slider-navigation"), false);
        var pagination = $obj.data("slider-pagination") || "";
        var pagination_custom = $obj.data("slider-render-bullet") ?? "";
        var pagination_top = $obj.data("slider-pagination-top") ?? "";
        var pagination_visible = $obj.data("slider-pagination-visible") || 0;
        var pagination_thumbs = bool($obj.data("slider-pagination-thumbs"), false);
        var autoplay = bool($obj.data("slider-autoplay"), false);
        var autoplay_pause = bool($obj.data("slider-autoplay-pause"), false);
        var delay = $obj.data("slider-delay") ?? (autoplay ? this.CONSTANTS.DEFAULT_DELAY : 0);
        var speed = $obj.data("slider-speed") ?? this.CONSTANTS.DEFAULT_SPEED;
        var loop = bool($obj.data("slider-loop"), false);
        var lazy = bool($obj.data("slider-lazy"), false);
        var zoom = bool($obj.data("slider-zoom"), false);
        var free_mode = bool($obj.data("slider-free-mode"), false);
        var direction = IsBlank($obj.data("slider-direction")) || $obj.data("slider-direction") == "horizontal" ? "horizontal" : "vertical";
        var grab = bool($obj.data("slider-grab"), true);
        var allow_touch_move = bool($obj.data("slider-allow-touch-move"), true);
        var mousewheel = bool($obj.data("slider-mousewheel"), false);
        var scrollbar = false;
        var scrollbar_el = {};
        var scrollbar_draggable = bool($obj.data("slider-scrollbar-draggable"), true);
        var scrollbar_snap = bool($obj.data("slider-scrollbar-snap"), true);
        
        if ($obj.find(".swiper-scrollbar").length > 0) {
           scrollbar = true;
           scrollbar_el = $obj.find(".swiper-scrollbar");
        } else {
           scrollbar = bool($obj.data("slider-scrollbar"), false);
        }
        
        var slidesPerView = $obj.attr("data-slider-slides-per-view") ?? 1;
        var slidesPerGroup = $obj.attr("data-slider-slides-per-view") ?? 1;

        var card_slider = $obj.closest(".card").length > 0 ? $obj.closest(".card") : false;

        // Breakpoints configuration
        var breakpoints = this.getDefaultBreakpoints();
        var slidesPerView = slidesPerView || breakpoints["1599"]["slidesPerView"];
        var slidesPerGroup = slidesPerGroup || breakpoints["1599"]["slidesPerView"];

        var $breakpoints = $obj.data("slider-breakpoints");
        var $gaps = $obj.data("slider-gaps");
        var $gap = $obj.data("slider-gap");
        
        var $hasBreakPoints = this.processBreakpoints($breakpoints, $gaps, $gap, breakpoints);

        // Thumbnail setup
        var galleryThumbs;
        if (pagination_thumbs) {
            galleryThumbs = this.setupThumbnails($obj);
        }

        // Build swiper options
        var options = this.buildSwiperOptions({
            slidesPerView, scrollbar, scrollbar_el, scrollbar_draggable, scrollbar_snap,
            grab, allow_touch_move, auto_height, speed, delay, autoplay, autoplay_pause,
            loop, effect, crossFade, zoom, lazy, direction, free_mode, mousewheel,
            navigation, pagination, pagination_custom, pagination_visible, pagination_thumbs,
            card_slider, $obj, galleryThumbs, $hasBreakPoints, breakpoints, slidesPerGroup,
            $gaps, $gap
        });

        var dataAttr = $obj.data();
        if (dataAttr) {
            $.extend(options, dataAttr);
        }
        
        var swiper = new Swiper($obj[0], options);
        
        // Store instance for cleanup
        this.instances.set($obj[0], swiper);
        
        return swiper;
    }
    /**
     * Configuration Helper Methods
     */
    getDefaultBreakpoints() {
        return {
            1399: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            },
            1199: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            },
            991: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            },
            767: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            },
            575: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            },
            0: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: 15
            }
        };
    }

    processBreakpoints($breakpoints, $gaps, $gap, breakpoints) {
        var $hasBreakPoints = false;
        if (!IsBlank($breakpoints)) {
            if (Object.keys($breakpoints).length > 0) {
               $hasBreakPoints = true;
            }
        }

        if ($hasBreakPoints) {
            // Breakpoint tanımları (Genişlik: Anahtar)
            var bpMap = {
                "0": "xs",
                "575": "sm",
                "768": "md",
                "991": "lg",
                "1199": "xl",
                "1399": "xxl",
                "1599": "xxxl"
            };

            var hasProp = Object.prototype.hasOwnProperty;

            // Tüm breakpointleri tek döngüde dönüyoruz
            for (var width in bpMap) {
                var key = bpMap[width];

                // $breakpoints içinde bu anahtar (xs, sm vb.) var mı?
                if ($breakpoints && hasProp.call($breakpoints, key)) {
                    var val = $breakpoints[key];
                    
                    breakpoints[width] = {
                        slidesPerView: val === "auto" ? 1 : val,
                        slidesPerGroup: val
                    };

                    // Gap (boşluk) ayarları
                    if (!IsBlank($gaps)) {
                        if (hasProp.call($gaps, key)) {
                            breakpoints[width]["spaceBetween"] = $gaps[key];
                        }
                    } else if (!IsBlank($gap)) {
                        breakpoints[width]["spaceBetween"] = $gap;
                    }
                }
            }
        }
        
        return $hasBreakPoints;
    }

    setupThumbnails($obj) {
        if ($obj.find(".swiper-thumbs").length == 0) {
           $obj.append("<div class='swiper-thumbs'></div>");
        }
        return new Swiper($obj.find(".swiper-thumbs"), {
            spaceBetween: 10,
            slidesPerView: 4,
            freeMode: true,
            watchSlidesVisibility: true,
            watchSlidesProgress: true,
            slideToClickedSlide: true
        });
    }    buildSwiperOptions(config) {
        const {
            slidesPerView, scrollbar, scrollbar_el, scrollbar_draggable, scrollbar_snap,
            grab, allow_touch_move, auto_height, speed, delay, autoplay, autoplay_pause,
            loop, effect, crossFade, zoom, lazy, direction, free_mode, mousewheel,
            navigation, pagination, pagination_custom, pagination_visible, pagination_thumbs,
            card_slider, $obj, galleryThumbs, $hasBreakPoints, breakpoints
        } = config;

        // Breakpoint yoksa standart gap ataması
        var spaceBetween = "";
        if (config.$gaps) {
            spaceBetween = config.$gaps;
        } else if (!IsBlank(config.$gap)) {
            spaceBetween = config.$gap;
        }

        var options = {
            slidesPerView: slidesPerView,
            spaceBetween: IsBlank(spaceBetween) ? 0 : spaceBetween,
            resistance: '100%',
            resistanceRatio: 0,
            watchOverflow: true,
            grabCursor: grab,
            centeredSlides: false,
            watchSlidesVisibility: true,
            centerInsufficientSlides: true,
            preventInteractionOnTransition: true,
            speed: speed,
            autoplay: false,
            allowTouchMove: allow_touch_move,
            autoHeight: auto_height,
            on: {
                init: function() {
                    var slider = this;
                    swiperManager.initSwiperVideo(slider);
                    var $el = $(slider.el);
                    swiperManager.safeUpdateSlideColors(slider.el, slider.params);

                    //fade in
                    debugJS($el)
                    if ($el.hasClass("fade")) {
                        $el.addClass("show");
                    }
                    if ($el.hasClass("loading")) {
                        $el.removeClass("loading");
                    }
                    //remove if parent has loading
                    if ($el.closest(".loading").length > 0) {
                        $el.closest(".loading").removeClass("loading");
                    }
                },
                
                loopFix: function() {
                   lazyLoadInstance.update();
                },

                slideChangeTransitionStart: function(e) {
                    if ($(e.slides[e.activeIndex]).find(".swiper-container").length > 0) {
                        var nested = $(e.slides[e.activeIndex]).find(".swiper-container")[0].swiper;
                        if (typeof nested !== "undefined") {
                            nested.autoplay.stop();
                            nested.slideTo(0);
                        }
                    }
                },
                
                slideChangeTransitionEnd: function(e) {
                    debugJS("slideChangeTransitionEnd");
                    const swiper = this;
                    const $activeSlide = $(swiper.slides[swiper.activeIndex]);
                    $activeSlide.find('iframe[data-src-backup]').each(function() {
                        debugJS("active slide--------------------")
                        $(this).closest(".lazy-container").removeClass("lazy-container");
                        $(this).parent().find(">.plyr__poster").remove();
                        $(this).parent().addClass("lazy-loaded");
                        $(this).attr("data-src", $(this).attr("data-src-backup"));
                        $(this).removeAttr("data-src-backup");
                        LazyLoad.load(this); // elle yükle
                    });
                    swiperManager.safeUpdateSlideColors(swiper.el, swiper.params);
                },
                resize: function() {
                    swiperManager.safeUpdateSlideColors(this.el, this.params);
                },
                slidesGridLengthChange: function() {
                    if (this.params.slidesPerView == "auto") {
                        if (this.params.loop) {
                           this.params.loopedSlides = this.slides.length;
                        }
                    }
                    debugJS("slidesGridLengthChange")
                    swiperManager.safeUpdateSlideColors(this.el, this.params);
                }
            }
        };

        // Breakpoints
        if ($hasBreakPoints) {
            options["breakpoints"] = breakpoints;
            if (slidesPerView) {
                options["slidesPerView"] = slidesPerView;
                options["slidesPerGroup"] = config.slidesPerGroup;
            }
        }

        // Scrollbar
        if (scrollbar) {
            this.setupScrollbar(options, scrollbar_el, $obj, scrollbar_draggable, scrollbar_snap);
        }

        // Navigation
        if (navigation) {
            this.setupNavigation(options, $obj, card_slider);
        }

        // Pagination
        if (!IsBlank(pagination)) {
            this.setupPagination(options, $obj, card_slider, pagination, pagination_custom, pagination_visible);
        }

        // Thumbnails
        if (pagination_thumbs) {
            options["thumbs"] = {
                swiper: galleryThumbs
            }
        }

        // Autoplay
        if (autoplay || delay) {
            options["autoplay"] = {
                enabled: autoplay,
                delay: delay,
            }
            if (autoplay_pause) {
                options["autoplay"]["disableOnInteraction"] = false;
                options["autoplay"]["pauseOnMouseEnter"] = autoplay_pause;
            }
        }

        // Loop
        if (loop) {
            options["loop"] = loop;
        }

        // Effects
        this.setupEffects(options, effect, crossFade);

        // Additional features
        if (zoom) {
            options["zoom"] = zoom;
        }

        if ($("body").hasClass("rtl")) {
            $obj.attr("dir", "rtl");
        }

        if (lazy) {
            options["preloadImages"] = false;
            options["lazy"] = {
                loadPrevNext: true,
            }
        }

        if (direction) {
            options["direction"] = direction;
        }

        if (free_mode) {
            options["freeMode"] = {
                enabled: true
            };
        }

        if (mousewheel) {
            options["mousewheel"] = {
                enabled: true
            };
        }

        return options;
    }

    setupScrollbar(options, scrollbar_el, $obj, scrollbar_draggable, scrollbar_snap) {
        if (scrollbar_el.length > 0) {
            options["scrollbar"] = {
              el: scrollbar_el[0]
            }
       } else {
            if ($obj.parent().find(".swiper-scrollbar").length > 0) {
                options["scrollbar"] = {
                  el: $obj.parent().find('.swiper-scrollbar')[0]
                }
            } else {
                $obj.append("<div class='swiper-scrollbar'></div>");
                options["scrollbar"] = {
                  el: $obj.find('.swiper-scrollbar')[0]
                }
            }
       }
       if (scrollbar_draggable) {
          options["scrollbar"]["draggable"] = true;
       }
       if (scrollbar_snap) {
          options["scrollbar"]["snapOnRelease"] = true;
       }
    }

    setupNavigation(options, $obj, card_slider) {
        var prevEl = $obj.find('.swiper-button-prev')[0];
        var nextEl = $obj.find('.swiper-button-next')[0];
        if (!prevEl && !nextEl) {
            if (card_slider) {
                prevEl = card_slider.find('.swiper-button-prev')[0];
                nextEl = card_slider.find('.swiper-button-next')[0];
            } else {
                prevEl = $obj.parent().find('.swiper-button-prev')[0];
                nextEl = $obj.parent().find('.swiper-button-next')[0];
            }
            if (!prevEl && !nextEl) {
                $obj.append('<a href="#" class="swiper-button-prev"></a><a href="#" class="swiper-button-next"></a>');
                prevEl = $obj.find('.swiper-button-prev')[0];
                nextEl = $obj.find('.swiper-button-next')[0];
            }
        }
        options["navigation"] = {
            prevEl: prevEl,
            nextEl: nextEl
        }
    }

    setupPagination(options, $obj, card_slider, pagination, pagination_custom, pagination_visible) {
        var pagination_obj = $obj.find('.swiper-pagination')[0];
        if (!pagination_obj) {
            if (card_slider) {
                if (card_slider.find(".swiper-pagination").length > 0) {
                    pagination_obj = card_slider.find(".swiper-pagination")[0];
                } else {
                   card_slider.append("<div class='swiper-pagination'></div>");
                   pagination_obj = card_slider.find(".swiper-pagination")[0];
                }
            } else {
                pagination_obj = $obj.parent().find('.swiper-pagination')[0];
                if (!pagination_obj) {
                    $obj.append("<div class='swiper-pagination'></div>");
                    pagination_obj = $obj.find('.swiper-pagination')[0];
                }
            }  
        }
        options["pagination"] = {
            el: pagination_obj,
            clickable: true,
            type: pagination
        }
        if (pagination == "bullets") {
           if (pagination_visible > 0) {
              options["pagination"]["dynamicBullets"] = true;
              options["pagination"]["dynamicMainBullets"] = pagination_visible;
           }
        }
        if (pagination == "custom" && !IsBlank(pagination_custom)) {
            options["pagination"]["renderBullet"] = pagination_custom;
        }
    }

    setupEffects(options, effect, crossFade) {
        switch (effect) {
            case "fade":
                options["effect"] = effect;
                options["fadeEffect"] = {
                    crossFade: crossFade
                }
                break;
            case "coverflow":
                options["effect"] = effect;
                options["coverflowEffect"] = {
                    rotate: 30,
                    slideShadows: false
                }
                break;
            case "flip":
                options["effect"] = effect;
                options["flipEffect"] = {
                    rotate: 30,
                    slideShadows: false
                }
                break;
            case "cube":
                options["effect"] = effect;
                options["cubeEffect"] = {
                    slideShadows: false
                }
                break;
        }
    }

    /**
     * Cleanup Methods
     */
    destroy(element) {
        if (this.instances.has(element)) {
            const swiper = this.instances.get(element);
            swiper.destroy(true, true);
            this.instances.delete(element);
        }
    }

    destroyAll() {
        this.instances.forEach((swiper, element) => {
            swiper.destroy(true, true);
        });
        this.instances.clear();
        clearTimeout(this.slideColorUpdateTimeout);
    }
}

// Global instance
const swiperManager = new SwiperManager();

// Export only the main initialization functions that are actually used externally
function init_swiper($obj) {
    return swiperManager.initSwiper($obj);
}

function init_swiper_obj($obj) {
    return swiperManager.initSwiperObj($obj);
}

function init_swiper_video_slide(swiper, obj) {
    return swiperManager.initSwiperVideoSlide(swiper, obj);
}

function init_swiper_video(swiper) {
    return swiperManager.initSwiperVideo(swiper);
}

// Product gallery initialization (legacy support)
$(".slider-product-gallery").each(function(){
    var swiper = new Swiper($(this), {
        slidesPerView: 1,
        spaceBetween: 0,
        resistance: '100%',
        resistanceRatio: 0,
        grabCursor: true,
        preloadImages: false,
        lazy: true,
        lazy: {
            loadPrevNext: true,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
            type: 'bullets'
        }
    });  
});