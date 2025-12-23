function getAverageLuminance(element) {
    return new Promise((resolve) => {
        if (!element) {
            console.warn("Element bulunamadı.");
            resolve(1); // Beyaz
            return;
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
                        getImageLuminance(imageUrlMatch[1]).then(resolve);
                    });
                }

                if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                    return resolve(getComputedLuminance(poster));
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

            console.log(bg)

            if (bg && bg.includes("gradient")) {
                console.log("bg gradient içeriyor");
                return requestIdleCallback(() => {
                     getRenderedLuminance(element).then(resolve);
                });
            }

            if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                return resolve(getComputedLuminance(element));
            }

            // hiçbiri yoksa son çare
            console.log("son care");
            return requestIdleCallback(() => { 
                getRenderedLuminance(element).then(resolve);
            });
        }

        // lazy-load olabilir
        if (!img.complete || img.naturalWidth === 0 || img.naturalHeight === 0) {
            console.warn("Resim yüklenmemiş, onload bekleniyor:", img.src);
            img.onload = () => processImage(img, resolve);
            img.onerror = () => {
                console.error("Resim yüklenirken hata:", img.src);
                resolve(getComputedLuminance(element));
            };
            return;
        }

        // her şey tamamsa img ile devam
        processImage(img, resolve);
    });
}
function processImage(img, resolve) {
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
            resolve(getComputedLuminance(img)); // Arka plan rengini kullan
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
function getImageLuminance(imageUrl) {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = "Anonymous";
        img.src = imageUrl;

        img.onload = () => processImage(img, resolve);

        img.onerror = () => {
            console.error("getImageLuminance: Resim yüklenemedi:", imageUrl);
            resolve(1); // Varsayılan parlaklık beyaz
        };
    });
}
function getComputedLuminance(element) {
    const style = getComputedStyle(element);
    const bgColor = style.backgroundColor;
    const rgb = bgColor.match(/\d+/g)?.map(Number) || [255, 255, 255]; // Varsayılan beyaz
    const [r, g, b] = rgb;
    return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
}

function getAverageColorFromGradient(gradient) {
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
function getComputedLuminanceFromGradient(gradient) {
    const [r, g, b] = getAverageColorFromGradient(gradient);
    return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
}
function getRenderedLuminance(element) {
    return new Promise((resolve) => {
        try {
            const style = getComputedStyle(element);
            const bg = style.backgroundImage;
            const bgColor = style.backgroundColor;

            // 1️⃣ Gradient varsa, html-to-image kullanma → ortalama renk
            if (bg && bg.includes("gradient")) {
                return resolve(getComputedLuminanceFromGradient(bg));
            }

            // 2️⃣ Sadece düz renk varsa
            if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                return resolve(getComputedLuminance(element));
            }

            // 3️⃣ Hiçbiri yoksa canvas render (son çare)
            const rect = element.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return resolve(1);

            htmlToImage.toCanvas(element, {
                width: rect.width,
                height: rect.height,
                backgroundColor: null,
                pixelRatio: 0.25,
                style: { transform: 'scale(0.25)', transformOrigin: 'top left' },
                cacheBust: true,
                skipFonts: false,
            }).then(canvas => {
                const ctx = canvas.getContext("2d");
                const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

                let totalLuminance = 0;
                const totalPixels = data.length / 4;

                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
                    totalLuminance += luminance;
                }

                resolve(totalLuminance / totalPixels);
            }).catch(err => {
                console.error("html-to-image hatası:", err);
                resolve(1); // hata durumunda beyaz
            });

        } catch (e) {
            console.error("getRenderedLuminance hatası:", e);
            resolve(1);
        }
    });
}

async function updateSlideColors(slider) {

    let activeSlide = slider.querySelector('.swiper-slide-active');

    if (!activeSlide) {
        activeSlide = slider.querySelector('.swiper-slide');
    }

    if (!activeSlide) return; // hiç slide yoksa, güvenlik için çık

    slider.classList.remove("slide-light", "slide-dark");
    activeSlide.classList.remove("slide-light", "slide-dark");

    if (activeSlide.classList.contains('slide-dark') || activeSlide.classList.contains('slide-light')) {
        if(activeSlide.classList.contains('slide-dark')){
            slider.classList.add("slide-dark");
        }
        if(activeSlide.classList.contains('slide-light')){
            slider.classList.add("slide-light");
        }
        return;
    }
    const luminance = await getAverageLuminance(activeSlide);
    //const isDark = luminance < 0.6; // 0.5 eşik değeri
    const isDark = luminance === 0 ? false : luminance < 0.6;
    activeSlide.classList.add(isDark ? 'slide-dark' : 'slide-light');
    slider.classList.add(isDark ? 'slide-dark' : 'slide-light');
}

let slideColorUpdateTimeout;
function safeUpdateSlideColors(obj, params = []) {

    clearTimeout(slideColorUpdateTimeout);
    slideColorUpdateTimeout = setTimeout(() => {
        // Eğer birden fazla slide görünüyorsa
        if (params?.slidesPerView > 1) {
            obj.classList.remove('slide-dark');
            obj.classList.add('slide-light');
        } else {
            // Tek slide görünüyorsa normal işlem
            updateSlideColors(obj);
        }
    }, 40); // debounce
}


function init_swiper_video_slide(swiper, obj){
    if(isLoadedJS("plyr")){
        if(obj.find(".swiper-video").not(".inited").length > 0){
            var video_slide = obj.find(".swiper-video").not(".inited");
            /*if(video_slide.find('.player').hasClass("lazy")){
                lazyLoadInstance.load(video_slide.find('.player'));
            }*/
                video_slide.addClass("inited");
                const player = plyr_init(video_slide.find('.player'));//new Plyr(video_slide.find('.player')[0]);
                video_slide.data("plyr", player);
                return player;
        }else{
            if(obj.find(".swiper-video.inited").length > 0){
                return obj.find(".swiper-video.inited").data("plyr");
            }
        }
    }
}
function init_swiper_video(swiper){
    if($(swiper.el).find(".swiper-video").not(".inited").length > 0){

        if (swiper.slides && swiper.slides.length === 0) {
            return;
        }

        var video = init_swiper_video_slide(swiper, $(swiper.slides[0]));
        if(swiper.slides.length > 1){
            swiper
            .on('slideChangeTransitionStart touchStart', function () {
                var swiper = this;
                var slide = $(swiper.slides[swiper.previousIndex]);

                if(slide.find(".swiper-video").length > 0){
                    slide.addClass("user-reacted");
                    var video_slide = $(swiper.slides[(swiper.activeIndex > swiper.previousIndex?swiper.activeIndex:swiper.previousIndex)]);

                    if(slide.find(".plyr").length > 0){
                        var video = slide.find(".plyr")[0].plyr;
                    }else{
                        video = init_swiper_video_slide(swiper, slide);
                    }

                    if(video){
                        if(video.playing){
                            video.rewind(0);
                            video.pause();
                        }
                    }

                    if($(swiper.el).data("sliderAutoplay")){
                        if(swiper.params.autoplay.delay > 0 && !swiper.autoplay.running){
                            swiper.autoplay.start();
                        }
                    }

                    slide.removeClass("paused").removeClass("playing");
                }
            })
            .on('slideChangeTransitionEnd touchEnd', function () {
                var swiper = this;
                var slide = $(swiper.slides[swiper.activeIndex]);
                if(slide.find(".swiper-video").length > 0){

                    slide.find('iframe[data-src]').each(function(){
                        console.log(this)
                        LazyLoad.load(this); // elle yükle
                        plyr_init($(this).closest(".video"));
                    });
                    slide.removeClass("user-reacted");
                    
                    if(slide.find(".plyr").length > 0){
                        var video = slide.find(".plyr")[0].plyr;
                    }else{
                        video = init_swiper_video_slide(swiper, slide);
                    }

                    if(slide.find(".swiper-video").length > 0){
                        if(video && video.autoplay){
                            if(slide.hasClass("ready")){
                               video.play();
                            }else{
                                video.on('ready', (event) => {
                                   const instance = event.detail.plyr;
                                         instance.play();
                                });                         
                            }
                            if($(swiper.el).data("sliderAutoplay")){
                                if(swiper.params.autoplay.delay > 0 && swiper.autoplay.running){
                                    swiper.autoplay.stop();
                                }
                            }
                        }else{
                            if($(swiper.el).data("sliderAutoplay")){
                                if(swiper.params.autoplay.delay > 0 && !swiper.autoplay.running){
                                    swiper.autoplay.start();
                                }
                            }
                        }
                    }

                }else{

                    if($(swiper.el).data("sliderAutoplay")){
                        if(swiper.params.autoplay.delay > 0 && !swiper.autoplay.running){
                            swiper.autoplay.start();
                        }
                    }

                }
            });         
        }
    }
}
function init_swiper($obj){
    var token_init = "swiper-slider-init";
    if(!IsBlank($obj)){
       if($obj.not("."+token_init).length > 0){
          $(this).addClass(token_init);
          return init_swiper_obj($obj);
       };
    }else{
        $(".swiper-slider").not("."+token_init).each(function() {
            $(this).addClass(token_init);
            init_swiper_obj($(this));
        });
    }
}
function init_swiper_obj($obj) {
        if($obj.find(".swiper-slide").length < 2){
            init_swiper_video_slide([], $obj);
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
            safeUpdateSlideColors($obj[0]);
            return;
        }
  
        var effect = $obj.data("slider-effect") ?? "slide";
        var crossFade = false;
        if(effect == "fade"){
           var crossFade = bool($obj.data("slider-cross-fade"), crossFade);
        }
        var auto_height = bool($obj.data("slider-autoheight"), false);
        var navigation = bool($obj.data("slider-navigation"), false);
        var pagination = $obj.data("slider-pagination")||"";
        var pagination_custom = $obj.data("slider-render-bullet") ?? "";
        var pagination_top = $obj.data("slider-pagination-top") ?? "";
        var pagination_visible = $obj.data("slider-pagination-visible")||0;
        var pagination_thumbs = bool($obj.data("slider-pagination-thumbs"), false);
        var autoplay = bool($obj.data("slider-autoplay"), false);
        var autoplay_pause = bool($obj.data("slider-autoplay-pause"), false);
        var delay = $obj.data("slider-delay") ?? (autoplay ? 5000 : 0);
        var speed = $obj.data("slider-speed") ?? 750;
        var loop = bool($obj.data("slider-loop"), false);
        var lazy = bool($obj.data("slider-lazy"), false);
        var zoom = bool($obj.data("slider-zoom"), false);
        var free_mode = bool($obj.data("slider-free-mode"), false);
        var direction = IsBlank($obj.data("slider-direction"))||$obj.data("slider-direction")=="horizontal"?"horizontal":"vertical";
        var grab = bool($obj.data("slider-grab"), true);
        var allow_touch_move = bool($obj.data("slider-allow-touch-move"), true);
        var mousewheel = bool($obj.data("slider-mousewheel"), false);
        var scrollbar = false;
        var scrollbar_el = {};
        var scrollbar_draggable = bool($obj.data("slider-scrollbar-draggable"), true);
        var scrollbar_snap = bool($obj.data("slider-scrollbar-snap"), true);
        if($obj.find(".swiper-scrollbar").length>0){
           scrollbar = true;
           scrollbar_el = $obj.find(".swiper-scrollbar");
        }else{
           scrollbar = bool($obj.data("slider-scrollbar"), false);
        }
        var slidesPerView = $obj.attr("data-slider-slides-per-view") ?? 1;
        var slidesPerGroup = $obj.attr("data-slider-slides-per-view") ?? 1;

        var card_slider = $obj.closest(".card").length>0?$obj.closest(".card"):false;
            //card_slider = card_slider?$obj.closest(".card"):card_slider;

        var breakpoints = {
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

        var slidesPerView = slidesPerView||breakpoints["1599"]["slidesPerView"];
        var slidesPerGroup = slidesPerGroup||breakpoints["1599"]["slidesPerView"];

        var $breakpoints = $obj.data("slider-breakpoints");
        var $gaps = $obj.data("slider-gaps");
        var $gap = $obj.data("slider-gap");
        
        var $hasBreakPoints = false;
        if (!IsBlank($breakpoints)){
            //$breakpoints = $breakpoints.replaceAll("/", "");
            if(Object.keys($breakpoints).length > 0) {
               $hasBreakPoints = true;
            }
        }
        if (!IsBlank($gaps)){
           //$gaps = $gaps.replaceAll("/", "");
        }

        if ($hasBreakPoints) {
            if ($breakpoints.hasOwnProperty("xs")) {
                breakpoints["0"] = {
                    slidesPerView: $breakpoints.xs=="auto"?1:$breakpoints.xs,
                    slidesPerGroup: $breakpoints.xs
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("xs")) {
                        breakpoints["0"]["spaceBetween"] = $gaps.xs;
                    }                   
                }else if(!IsBlank($gap)){
                    breakpoints["0"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("sm")) {
                breakpoints["575"] = {
                    slidesPerView: $breakpoints.sm=="auto"?1:$breakpoints.sm,
                    slidesPerGroup: $breakpoints.sm
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("sm")) {
                        breakpoints["575"]["spaceBetween"] = $gaps.sm;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["575"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("md")) {
                breakpoints["768"] = {
                    slidesPerView: $breakpoints.md=="auto"?1:$breakpoints.md,
                    slidesPerGroup: $breakpoints.md
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("md")) {
                        breakpoints["768"]["spaceBetween"] = $gaps.md;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["768"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("lg")) {
                breakpoints["991"] = {
                    slidesPerView: $breakpoints.lg=="auto"?1:$breakpoints.lg,
                    slidesPerGroup: $breakpoints.lg
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("lg")) {
                        breakpoints["991"]["spaceBetween"] = $gaps.lg;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["991"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("xl")) {
                breakpoints["1199"] = {
                    slidesPerView: $breakpoints.xl=="auto"?1:$breakpoints.xl,
                    slidesPerGroup: $breakpoints.xl
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("xl")) {
                        breakpoints["1199"]["spaceBetween"] = $gaps.xl;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["1199"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("xxl")) {
                breakpoints["1399"] = {
                    slidesPerView: $breakpoints.xxl=="auto"?1:$breakpoints.xxl,
                    slidesPerGroup: $breakpoints.xxl
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("xxl")) {
                        breakpoints["1399"]["spaceBetween"] = $gaps.xxl;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["1399"]["spaceBetween"] = $gap;
                }
            }
            if ($breakpoints.hasOwnProperty("xxxl")) {
                breakpoints["1599"] = {
                    slidesPerView: $breakpoints.xxxl=="auto"?1:$breakpoints.xxxl,
                    slidesPerGroup: $breakpoints.xxxl
                }
                if(!IsBlank($gaps)){
                    if ($gaps.hasOwnProperty("xxxl")) {
                        breakpoints["1599"]["spaceBetween"] = $gaps.xxxl;
                    }
                }else if(!IsBlank($gap)){
                    breakpoints["1599"]["spaceBetween"] = $gap;
                }
            }
        }else{
            if($gaps){
                var spaceBetween = $gaps;
            }else if(!IsBlank($gap)){
                var spaceBetween = $gap;
            }
        }

        if (pagination_thumbs){
            if($obj.find(".swiper-thumbs").length == 0) {
               $obj.append("<div class='swiper-thumbs'></div>");
            }
            var galleryThumbs = new Swiper($obj.find(".swiper-thumbs"), {
                spaceBetween: 10,
                slidesPerView: 4,
                freeMode: true,
                watchSlidesVisibility: true,
                watchSlidesProgress: true,
                slideToClickedSlide:true
            });
        } 

        var options = {
            //cssMode: true,
            slidesPerView: slidesPerView,
            spaceBetween: IsBlank(spaceBetween)?0:spaceBetween,
            resistance: '100%',
            resistanceRatio: 0,
            watchOverflow: true,
            grabCursor: grab,
            centeredSlides: false,
            watchSlidesVisibility: true,
            centerInsufficientSlides : true,
            preventInteractionOnTransition : true,
            speed: speed,
            //breakpoints: breakpoints,
            autoplay : false,
            allowTouchMove : allow_touch_move,
            autoHeight : auto_height,
            on: {
                init: function() {
                    //$.fn.matchHeight._update();
                    var slider = this;

                    init_swiper_video(slider);

                    var $el = $(slider.el);

                    safeUpdateSlideColors(this.el, this.params);

                    //fade in
                    console.log($el)
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
                loopFix : function(){
                   lazyLoadInstance.update();
                },

                slideChangeTransitionStart: function (e) {
                    if($(e.slides[e.activeIndex]).find(".swiper-container").length > 0){
                        var nested = $(e.slides[e.activeIndex]).find(".swiper-container")[0].swiper;
                        if(typeof nested !== "undefined"){
                            nested.autoplay.stop();
                            nested.slideTo(0);
                        }
                    }
                },
                
                slideChangeTransitionEnd: function (e) {
                    console.log("slideChangeTransitionEnd");
                    //const activeSlide = $(e.slides[e.activeIndex]);
                    const swiper = this;
                    const $activeSlide = $(swiper.slides[swiper.activeIndex]);
                    $activeSlide.find('iframe[data-src-backup]').each(function(){
                        console.log("active slide--------------------")
                        $(this).closest(".lazy-container").removeClass("lazy-container");
                        $(this).parent().find(">.plyr__poster").remove();
                        $(this).parent().addClass("lazy-loaded");
                        $(this).attr("data-src", $(this).attr("data-src-backup"));
                        $(this).removeAttr("data-src-backup");
                        LazyLoad.load(this); // elle yükle
                    });
                    safeUpdateSlideColors(swiper.el, swiper.params);
                },
                resize: function() {
                    safeUpdateSlideColors(this.el, this.params);
                },
                slidesGridLengthChange: function() {
                    if(this.params.slidesPerView == "auto"){
                        //this.params.freeMode = true;
                        if(this.params.loop){
                           this.params.loopedSlides = this.slides.length;
                        }
                    }else{
                        //this.params.freeMode = false;
                    }
                    console.log("slidesGridLengthChange")
                    safeUpdateSlideColors(this.el, this.params);
                }
            }
        };

        if ($hasBreakPoints) {
            options["breakpoints"] = breakpoints;
            if(slidesPerView){
                options["slidesPerView"] = slidesPerView;
                options["slidesPerGroup"] = slidesPerGroup;
            }
        }

        if(scrollbar){
            if(scrollbar_el.length > 0){
                options["scrollbar"] = {
                  el: scrollbar_el[0]
                }
           }else{
                if ($obj.parent().find(".swiper-scrollbar").length > 0) {
                    options["scrollbar"] = {
                      el: $obj.parent().find('.swiper-scrollbar')[0]
                    }
                }else{
                    $obj.append("<div class='swiper-scrollbar'></div>");
                    options["scrollbar"] = {
                      el: $obj.find('.swiper-scrollbar')[0]
                    }
                }
           }
           if(scrollbar_draggable){
              options["scrollbar"]["draggable"] = true;
           }
           if(scrollbar_snap){
              options["scrollbar"]["snapOnRelease"] = true;
           }
        }

        if (navigation) {
            var prevEl = $obj.find('.swiper-button-prev')[0];
            var nextEl = $obj.find('.swiper-button-next')[0];
            if (!prevEl && !nextEl) {
                if(card_slider){
                    prevEl = card_slider.find('.swiper-button-prev')[0];
                    nextEl = card_slider.find('.swiper-button-next')[0];
                }
                if (!prevEl && !nextEl) {
                    $obj.append('<a href="#" class="swiper-button-prev"></a><a href="#" class="swiper-button-next"></a>');
                    prevEl = $obj.find('.swiper-button-prev')[0];
                    nextEl = $obj.find('.swiper-button-next')[0];
                }
            }
            if ($("body").hasClass("rtl")) {
                options["navigation"] = {
                    nextEl: prevEl,
                    prevEl: nextEl
                }
            } else {
                options["navigation"] = {
                    prevEl: prevEl,
                    nextEl: nextEl
                }
            }
        }
        if(!IsBlank(pagination)) {
            var pagination_obj = $obj.find('.swiper-pagination')[0];
            if(!pagination_obj){
                if(card_slider) {
                    if (card_slider.find(".swiper-pagination").length > 0) {
                        pagination_obj = card_slider.find(".swiper-pagination")[0];
                    }else{
                       card_slider.append("<div class='swiper-pagination'></div>");
                       pagination_obj = card_slider.find(".swiper-pagination")[0];
                    }
                }else{
                    $obj.append("<div class='swiper-pagination'></div>");
                    pagination_obj = $obj.find('.swiper-pagination')[0];
                }  
            }
            options["pagination"] = {
                el: pagination_obj,
                clickable: true,
                type: pagination
            }
            if(pagination == "bullets"){
               if(pagination_visible > 0){
                  options["pagination"]["dynamicBullets"] = true;
                  options["pagination"]["dynamicMainBullets"] = pagination_visible;
               }
            }
            if(pagination == "custom" && !IsBlank(pagination_custom)){
                options["pagination"]["renderBullet"] = pagination_custom;
            }
        };
        if (pagination_thumbs) {
            options["thumbs"] = {
                swiper: galleryThumbs
            }
        }
        if (autoplay || delay) {
            options["autoplay"] = {
                enabled: autoplay,
                delay: delay,
            }
            if(autoplay_pause){
                options["autoplay"]["disableOnInteraction"] = false;
                options["autoplay"]["pauseOnMouseEnter"] = autoplay_pause;
            }
        }
        if (loop) {
            options["loop"] = loop;
        }
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
        if (zoom) {
            options["zoom"] = zoom;
        }
        if ($("body").hasClass("rtl")) {
            $obj.attr("dir", "rtl");
        }
        if(lazy){
            options["preloadImages"] = false;
            options["lazy"] = {
                loadPrevNext: true,
            }
        }
        if(direction){
            options["direction"] = direction;
        }
        if(free_mode){
            options["freeMode"] = {
                enabled: true
            };
        }
        if(mousewheel){
            options["mousewheel"] = {
                enabled: true
            };
        }

        var dataAttr = $obj.data();
        if(dataAttr){
            $.extend(options, dataAttr);
        }
        var swiper = new Swiper($obj[0], options);
        return swiper;
}

$(".slider-product-gallery").each(function(){
    var swiper = new Swiper($(this) , {
        slidesPerView : 1,
        spaceBetween : 0,
        resistance : '100%',
        resistanceRatio :  0,
        grabCursor : true,
        preloadImages: false,
        lazy: true,
        lazy: {
            loadPrevNext: true,
        },
        pagination: {
            el : '.swiper-pagination',
            clickable : true,
            type : 'bullets'
        }
    });  
});