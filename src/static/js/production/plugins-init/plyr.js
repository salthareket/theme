/**
 * PlyrManager - Clean, encapsulated video player management
 * No global namespace pollution, easy to extend and maintain
 */

class PlyrManager {
    constructor() {
        this.config = {
            delays: {
                posterInit: 100,
                containerSetup: 200,
                posterSet: 500
            },
            classes: {
                initToken: 'plyr-init',
                showButton: 'plyr--show-play-button',
                lazyContainer: 'lazy-container'
            },
            devices: {
                phone: { size: 360, max: 767 },
                tablet: { size: 480, min: 768, max: 1024 },
                desktop: { size: 720, min: 1025 }
            }
        };
        
        this.instances = new Map(); // Track all video instances
        this.eventHandlers = new Map(); // Track event handlers for cleanup
    }

    // ========================================
    // PUBLIC API
    // ========================================
    
    /**
     * Initialize all players on page
     */
    initAll() {
        if (!isLoadedJS("plyr")) return false;
        
        $(".player").each((index, element) => {
            const $element = $(element);
            if ($element.closest(".swiper-slide").length === 0 && 
                !$element.hasClass(this.config.classes.initToken)) {
                this.init($element);
            }
        });
        
        return this;
    }

    /**
     * Initialize single player
     */
    init($obj) {
        if (!isLoadedJS("plyr")) return false;

        // Parameter validation and auto-discovery
        if (!$obj || $obj.length === 0) {
            return this._initMultiple();
        }

        // Container handling
        if ($obj.hasClass('plyr-container') || !$obj.hasClass('player')) {
            return this._initFromContainer($obj);
        }

        // Skip if already initialized
        if (this._isAlreadyInitialized($obj)) {
            return this.instances.get($obj[0]);
        }

        // Create and setup player
        const player = this._createPlayer($obj);
        if (player) {
            this.instances.set($obj[0], player);
        }
        
        return player;
    }

    /**
     * Destroy player instance
     */
    destroy($obj) {
        const element = $obj[0];
        const player = this.instances.get(element);
        
        if (player) {
            // Cleanup event handlers
            const handlers = this.eventHandlers.get(element);
            if (handlers) {
                handlers.forEach(handler => handler.cleanup());
                this.eventHandlers.delete(element);
            }
            
            // Destroy Plyr instance
            if (player.destroy) {
                player.destroy();
            }
            
            // Remove from tracking
            this.instances.delete(element);
            $obj.removeClass(this.config.classes.initToken);
        }
        
        return this;
    }

    /**
     * Get player instance
     */
    getPlayer($obj) {
        return this.instances.get($obj[0]);
    }

    // ========================================
    // PRIVATE METHODS
    // ========================================
    
    _initMultiple() {
        const selector = `.player:not(.${this.config.classes.initToken}, .${this.config.classes.lazyContainer})`;
        const $players = $(selector);
        
        if ($players.length > 0) {
            $players.each((index, element) => this.init($(element)));
        }
        
        return true;
    }

    _initFromContainer($obj) {
        const $player = $obj.find('.player').first();
        return $player.length > 0 ? this.init($player) : false;
    }

    _isAlreadyInitialized($obj) {
        return $obj.hasClass(this.config.classes.initToken) || 
               $obj.hasClass(this.config.classes.lazyContainer);
    }

    _createPlayer($obj) {
        const config = this._getPlyrConfig($obj);
        const type = this._getVideoType($obj);
        const videoContainer = this._getVideoContainer($obj);
        
        // Background video setup
        if ($obj.hasClass("video-bg") && $obj.find("iframe").length > 0) {
            $obj.addClass("plyr--bg");
        }

        // Create Plyr instance
        const video = new Plyr($obj, config);
        debugJS(video);

        // Store reference
        if (video.elements.container) {
            video.elements.container.plyr = video;
        }

        // Setup custom poster for embed videos
        this._setupCustomPoster($obj, video);

        // Setup all functionality
        this._setupEventHandlers($obj, video, videoContainer, type);
        this._setupViewportControl($obj, video, videoContainer);
        this._setupVisibilityControl($obj, video, videoContainer);
        this._setupFullscreenControl($obj, video, videoContainer, type);

        // Finalize initialization
        debugJS(waiting_init);
        waiting_init.initElement();
        $obj.addClass(this.config.classes.initToken);

        // Jarallax compatibility
        this._handleJarallax($obj, videoContainer);

        return video;
    }

    _getPlyrConfig($obj) {
        const configData = $obj.attr("data-plyr-config");
        const config = !IsBlank(configData) ? JSON.parse(configData) : {};
        
        // youtube-nocookie.com kullan → third-party cookie yok
        config.youtube = {
            noCookie: true,        // youtube-nocookie.com domain'i kullan
            rel: 0,                // ilgili video önerme
            showinfo: 0,
            iv_load_policy: 3,     // annotation yok
            modestbranding: 1      // YouTube logo küçük
        };

        return config;
    }

    _getVideoType($obj) {
        return $obj.hasClass("plyr__video-embed") ? 'embed' : $obj[0].tagName.toLowerCase();
    }

    _getVideoContainer($obj) {
        const swiper = $obj.closest(".swiper").length > 0 ? $obj.closest(".swiper")[0].swiper : false;
        return swiper ? $obj.closest(".swiper-slide") : $obj.parent();
    }

    _setupCustomPoster($obj, video) {
        const poster = $obj.attr("data-poster");
        if (!IsBlank(poster) && this._isEmbedVideo($obj)) {
            if ($obj.hasClass('lcp-element') || $obj.attr('fetchpriority') === 'high') {
                video.poster = poster;
                log('🎬 LCP video poster set immediately: ' + poster);
            } else {
                setTimeout(() => { 
                    video.poster = poster; 
                }, this.config.delays.posterSet);
            }
        }
    }

    _isEmbedVideo($obj) {
        return $obj.hasClass("plyr--youtube") || 
               $obj.hasClass("plyr--vimeo") || 
               $obj.hasClass("plyr--dailymotion");
    }

    // ========================================
    // EVENT HANDLING
    // ========================================
    
    _setupEventHandlers($obj, video, videoContainer, type) {
        const handlers = [];
        
        // Ready event
        const readyHandler = () => {
            this._handleReady($obj, video, videoContainer, type);
        };
        $obj.on('ready', readyHandler);
        handlers.push({ event: 'ready', handler: readyHandler });

        // Play event
        const playHandler = () => {
            videoContainer.removeClass("loading").addClass("playing").removeClass("paused").removeClass("inited");
            if (type === "embed") {
                log('🎮 Video playing');
            }
            // Bu video oynarken diğer tüm videoları durdur
            this._pauseOthers($obj);
            $(window).trigger("resize scroll visibilitychange");
        };
        $obj.on("play", playHandler);
        handlers.push({ event: 'play', handler: playHandler });

        // Pause event
        const pauseHandler = () => {
            videoContainer.removeClass("playing").addClass("paused");
            if (type === "embed") {
                log('🎮 Video paused (CSS controlled)');
            }
        };
        $obj.on("pause", pauseHandler);
        handlers.push({ event: 'pause', handler: pauseHandler });

        // End event
        const endHandler = () => {
            this._handleVideoEnd($obj, video, videoContainer);
        };
        $obj.on("ended", endHandler);
        handlers.push({ event: 'ended', handler: endHandler });

        // Store handlers for cleanup
        this.eventHandlers.set($obj[0], handlers.map(h => ({
            ...h,
            cleanup: () => $obj.off(h.event, h.handler)
        })));
    }

    _handleReady($obj, video, videoContainer, type) {
        // Type specific setup
        if (type === "embed") {
            this._setupEmbedVideo($obj, video);
        } else if (type === "video") {
            this._setupQualityControl($obj, video);
        }

        // Embed background fit
        if (type === "embed") {
            $obj.find(".plyr__video-embed").fitEmbedBackground();
        }

        // Container state setup
        videoContainer.addClass("loaded ready inited");

        // Lazy loaded handling
        if ($obj.hasClass("lazy-loaded")) {
            video.pause();
            video.restart();
        }

        // Autoplay handling
        this._handleAutoplay(video, videoContainer, video.config);
        
        // Trigger lazy load update
        this._updateLazyLoad();
        $(window).trigger("resize");
    }

    // ========================================
    // EMBED VIDEO HANDLING
    // ========================================
    
    _setupEmbedVideo($obj, video) {
        const customPoster = $obj.attr("data-poster");
        
        // Add custom poster if exists
        if (customPoster) {
            this._safeContainerAction(video, ($container) => {
                $container.find('.plyr__poster').remove();
                const posterHtml = `<div class="plyr__poster" style="background-image: url(&quot;${customPoster}&quot;);"></div>`;
                $container.prepend(posterHtml);
                log('🎬 Custom embed poster added: ' + customPoster);
            });
        }
        
        this._safeContainerAction(video, ($container) => {
            $container.addClass(this.config.classes.showButton);
            log('🎮 Embed video container ready');
        }, this.config.delays.containerSetup);
        
        // Dailymotion specific handling
        if ($obj.find("iframe").attr("src")?.includes("dailymotion.com")) {
            $obj.addClass("plyr plyr--full-ui plyr--video plyr--dailymotion");
            $obj.find("iframe").wrap('<div class="plyr__video-wrapper plyr__video-embed"></div>');
            $obj.find(".plyr__video-embed").fitEmbedBackground();
        }
    }

    // ========================================
    // QUALITY CONTROL
    // ========================================
    
    _setupQualityControl($obj, video) {
        const setQuality = () => {
            const source = $(video.elements.original).find("source");
            if (source.length <= 1) return;

            const width = window.innerWidth;
            const sources = Array.from(source);
            const sizes = sources.map(s => parseInt(s.getAttribute('size')));
            const sortedSizes = sizes.sort((a, b) => b - a);
            
            let selectedSize = sortedSizes[0];
            const device = this._getDeviceForWidth(width);
            
            if (device) {
                selectedSize = this._getBestSizeForDevice(device, sizes);
            }
            
            const quality = selectedSize === "undefined" ? sortedSizes[0] : selectedSize;
            if (video.quality !== quality) {
                video.quality = quality;
            }
        };

        // Initial quality set
        setQuality();

        // Setup resize handlers
        const debounce = resizeDebounce(setQuality, 10);
        $(window).on('resize', debounce);
        $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', debounce);
    }

    _getDeviceForWidth(width) {
        for (let deviceName in this.config.devices) {
            const device = this.config.devices[deviceName];
            const min = device.min || 0;
            const max = device.max || Infinity;
            if (width >= min && width <= max) {
                return deviceName;
            }
        }
        return null;
    }

    _getBestSizeForDevice(deviceName, sizes) {
        const targetSize = this.config.devices[deviceName].size;
        return sizes.reduce((prev, curr) => {
            return Math.abs(curr - targetSize) < Math.abs(prev - targetSize) ? curr : prev;
        });
    }

    // ========================================
    // VIEWPORT & VISIBILITY CONTROL
    // ========================================
    
    _setupViewportControl($obj, video, videoContainer) {
        const checkViewport = () => {
            if (!videoContainer.hasClass("ready")) return;
            
            if (!videoContainer.inViewport()) {
                if (video.playing) {
                    videoContainer.addClass("viewport-paused");
                    video.pause();
                }
            } else if (videoContainer.hasClass("viewport-paused")) {
                videoContainer.removeClass("viewport-paused");
                video.play();
            }
        };

        $(window).on('scroll resize', throttle(checkViewport, 100));
    }

    _setupVisibilityControl($obj, video, videoContainer) {
        const visibilityHandler = () => {
            if (document.hidden) {
                if (video.playing) {
                    videoContainer.addClass("tab-paused");
                    video.pause();
                }
            } else if (videoContainer.hasClass("tab-paused")) {
                videoContainer.removeClass("tab-paused");
                video.play();
            }
        };

        document.addEventListener("visibilitychange", visibilityHandler);
    }

    _setupFullscreenControl($obj, video, videoContainer, type) {
        const fullscreenHandler = () => {
            const isFullscreen = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen;
            videoContainer.toggleClass("fullscreen", isFullscreen);
            if (type === "video") {
                this._setupQualityControl($obj, video);
            }
        };

        $obj.bind('webkitfullscreenchange mozfullscreenchange fullscreenchange', fullscreenHandler);
    }

    // ========================================
    // UTILITY METHODS
    // ========================================
    
    _safeContainerAction(video, callback, delay = this.config.delays.posterInit) {
        setTimeout(() => {
            if (video.elements?.container) {
                callback($(video.elements.container));
            }
        }, delay);
    }

    _handleAutoplay(video, videoContainer, config) {
        if (document.hidden) {
            videoContainer.addClass("viewport-paused");
            video.pause();
            return;
        }

        if (!config?.autoplay) {
            videoContainer.addClass("paused");
            return;
        }

        if (videoContainer.inViewport() && !document.hidden) {
                const swiper = videoContainer.closest(".swiper")[0]?.swiper;
                if (swiper && !videoContainer.hasClass("swiper-slide-active")) {
                    video.pause();
                } else {
                    video.play().catch((error) => {
                        log('⚠️ Autoplay blocked: ' + error, 'warn');
                        videoContainer.addClass("paused");
                    });
                }
            }
    }

    _handleVideoEnd($obj, video, videoContainer) {
        const config = $obj.closest(".swiper-video").data("plyr");
        const slide = $obj.closest(".swiper-slide");
        const swiper = $obj.closest(".swiper")[0]?.swiper;

        videoContainer.removeClass("playing");
        
        if (swiper && !slide.hasClass("user-reacted")) {
            const activeIndex = slide.index();
            if ($(swiper.el).data("sliderAutoplay")) {
                const nextIndex = activeIndex === swiper.slides.length - 1 ? 0 : activeIndex + 1;
                swiper.slideTo(nextIndex);
                if (swiper.params.autoplay.delay > 0 && !swiper.autoplay.running) {
                    swiper.autoplay.start();
                }
            } else if (config?.loop?.active) {
                video.play();
            }
        } else if (config?.loop?.active) {
            video.play();
        } else {
            videoContainer.addClass("ended");
        }
    }

    _updateLazyLoad() {
        if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance.update) {
            setTimeout(() => {
                lazyLoadInstance.update();
                log('🔄 LazyLoad updated');
            }, this.config.delays.posterInit);
        }
    }

    _handleJarallax($obj, videoContainer) {
        if ($obj.hasClass("jarallax-img")) {
            $obj.removeClass("jarallax-img");
            videoContainer.closest(".plyr").addClass("jarallax-img");
        }
    }

    /**
     * Bu video oynarken diğer tüm aktif videoları durdur
     */
    _pauseOthers($currentObj) {
        this.instances.forEach((player, element) => {
            // Kendisi değilse ve oynuyorsa durdur
            if (element !== $currentObj[0] && player.playing) {
                player.pause();
                log('⏸ Diğer video durduruldu: ' + ($(element).attr('id') || element));
            }
        });
    }
}

// ========================================
// GLOBAL INSTANCE & LEGACY SUPPORT
// ========================================

// Create global instance
window.plyrManager = new PlyrManager();

// Legacy function support (for backward compatibility)
function init_plyr_all() {
    return window.plyrManager.initAll();
}

function init_plyr($obj) {
    return window.plyrManager.init($obj);
}

// ========================================
// AUTO INITIALIZATION
// ========================================
$(".player.init-me").not(`.${window.plyrManager.config.classes.lazyContainer}`).each(function() {
    window.plyrManager.init($(this));
});

$('.wp-block-video video').each(function() {
    $(this).addClass("player");
    window.plyrManager.init($(this));
});