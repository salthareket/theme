function init_plyr_all(){
	if(!isLoadedJS("plyr")){
		return false;
	}
	$(".player").each(function(){
		if($(this).closest(".swiper-slide").length == 0 && !$(this).hasClass("plyr-init")){
			init_plyr($(this));			
		}
	});
}

function init_plyr($obj){
	if(!isLoadedJS("plyr")){
		return false;
	}
	
	// Parametre kontrolü - eğer parametre yoksa veya container ise içindeki .player'ı bul
	if(!$obj || $obj.length === 0){
		// Parametresiz çağrıldıysa, sayfadaki tüm .player elementlerini init et
		var $players = $('.player').not('.plyr-init, .lazy-container');
		if($players.length > 0){
			$players.each(function(){
				init_plyr($(this));
			});
		}
		return true;
	}
	
	// Eğer container geçildiyse, içindeki .player'ı bul
	if($obj.hasClass('plyr-container') || !$obj.hasClass('player')){
		var $player = $obj.find('.player').first();
		if($player.length > 0){
			return init_plyr($player);
		}
		return false;
	}
	
	var token_init = "plyr-init";
	
	if(!IsBlank($obj)){
		if($obj.hasClass(token_init) || $obj.hasClass("lazy-container")){
			return;
		}
		
		var config_data = $obj.attr("data-plyr-config");
		if(!IsBlank(config_data) && typeof config_data != "undefined"){
			config_data = JSON.parse(config_data);
			config_data["youtube"] = {
		        noCookie: true,
		    };
		}else{
			config_data = {};
		}

		const type = $obj.hasClass("plyr__video-embed") ? 'embed' : $obj[0].tagName.toLowerCase();
		
		const lazy_loaded = $obj.hasClass("lazy-loaded");

		const video_bg = $obj.hasClass("video-bg");

		if(video_bg && $obj.find("iframe").length > 0){
			$obj.addClass("plyr--bg");
		}
		
		function set_quality(video){
			let devices = {phone: {size: 360, max: 767}, "tablet": {size: 480, min: 768, max: 1024}, "desktop" : {size: 720, min: 1025} };
			let quality = "";
			let source = $(video.elements.original).find("source");
	        if(source.length > 1){
	        	let width = window.innerWidth;
	        	let sources = Array.from(source);
	        	let sizes = sources.map(source => parseInt(source.getAttribute('size')));
				let sortedSizes = sizes.sort((a, b) => b - a);
			    let selectedSize = sortedSizes[0];
			    let device = getDeviceForWidth(width, devices);
				if (device) {
				    selectedSize = getBestSizeForDevice(device, devices, sizes);
				} else {
				}
				quality = selectedSize === "undefined" ? sortedSizes[0] : selectedSize;
	        }
	        if(video.quality != quality){
	        	video.quality = quality;
	        }
		}
		function getDeviceForWidth(width, devices) {
		    for (let device in devices) {
		        let min = devices[device].min || 0;
		        let max = devices[device].max || Infinity;
		        if (width >= min && width <= max) {
		            return device;
		        }
		    }
		    return null;
		}
		function getBestSizeForDevice(device, devices, sizes) {
		    let targetSize = devices[device].size;
		    let bestSize = sizes.reduce((prev, curr) => {
		        return Math.abs(curr - targetSize) < Math.abs(prev - targetSize) ? curr : prev;
		    });
		    return bestSize;
		}

	    const video = new Plyr($obj, config_data);

	    debugJS(video);

	    if(type == "embed"){
			if($obj.find("iframe").attr("src").includes("dailymotion.com")){
				$obj.addClass("plyr plyr--full-ui plyr--video plyr--dailymotion");
				$obj.find("iframe").wrap('<div class="plyr__video-wrapper plyr__video-embed"></div>');
				$obj.find(".plyr__video-embed").fitEmbedBackground();
			}
		}

	    if(video.elements.container){
	    	video.elements.container.plyr = video;
	    }
        
        // custom poster image
	    var poster = $obj.attr("data-poster");
        if(!IsBlank(poster) && ($obj.hasClass("plyr--youtube") || $obj.hasClass("plyr--vimeo") || $obj.hasClass("plyr--dailymotion"))){
		    setTimeout(() => {
				video.poster = poster;
			}, 500);
		}

	    var swiper = $obj.closest(".swiper").length>0?$obj.closest(".swiper")[0].swiper:false;
		var video_container = swiper?$obj.closest(".swiper-slide"):$obj.parent();

	    $obj
	    .on('ready', (e) => {
		  	const instance = e.detail?.plyr;
		  	const config = instance?.config;

            // Embed video için custom poster handling
            if(type == "embed"){
                var customPoster = $obj.attr("data-poster");
                if(customPoster){
                    // Custom poster varsa direkt ekle (artık lazy değil)
                    setTimeout(function(){
                        if(video.elements && video.elements.container){
                            // Mevcut poster'ları temizle
                            $(video.elements.container).find('.plyr__poster').remove();
                            
                            // Custom poster'ı ekle
                            var posterHtml = '<div class="plyr__poster" style="background-image: url(&quot;' + customPoster + '&quot;);"></div>';
                            $(video.elements.container).prepend(posterHtml);
                            
                            console.log('🎬 Custom embed poster eklendi:', customPoster);
                        }
                    }, 100);
                }
                
                // Embed video'lar için play button'ı kontrol et
                setTimeout(function(){
                    if(video.elements && video.elements.container){
                        var $container = $(video.elements.container);
                        
                        // Container'a custom class ekle (CSS'de styling var)
                        $container.addClass('plyr--show-play-button');
                        
                        console.log('🎮 Embed video container hazırlandı (CSS ile kontrol edilecek)');
                    }
                }, 200);
            }

            // Normal poster'ı sadece custom image yoksa kaldır
            var hasPoster = $obj.attr("data-poster") || $obj.attr("poster");
            if(!hasPoster && type !== "embed"){
                $obj.find(">.plyr__poster").remove();
            }
            
            if(type == "video"){
            	set_quality(video);
            }
            
            if(type == "embed"){
		  		$obj.find(".plyr__video-embed").fitEmbedBackground();
		    }

		  	video_container.addClass("loaded ready inited");

		  	if(lazy_loaded){
		  		video.pause();
		  		video.restart();
		  	}

			if (document.hidden) {
				video_container.addClass("viewport-paused");
				instance.pause();
			} else if (!config?.autoplay) {
				video_container.removeClass("viewport-paused")
				video_container.addClass("paused");
			}

			if(!config?.autoplay){
				video_container.addClass("paused");
				debugJS("pausedddddd")
			}else{
				try {
					if(video_container.is(":in-viewport") && !document.hidden){
						if(swiper && !video_container.hasClass("swiper-slide-active")){
	                        video.pause();
						}else{
							video.play().catch(function(error) {
								console.warn('⚠️ Autoplay engellendi:', error);
								video_container.addClass("paused");
							});
							debugJS("plaaaayyyiiiiiingg");
						}
					}
				} catch(e) {
					console.warn('⚠️ is-in-viewport plugin bulunamadı');
				}
			}
			
			// Lazy load'u trigger et
			if(typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance.update){
				setTimeout(function(){
					lazyLoadInstance.update();
					console.log('🔄 LazyLoad güncellendi');
				}, 100);
			}
			
			$(window).trigger("resize");
		})
		.on("play", (e) => {
		  	video_container.removeClass("loading").addClass("playing").removeClass("paused").removeClass("inited");
		  	
		  	// Embed video'da artık CSS ile kontrol ediliyor, inline style gereksiz
		  	if(type == "embed"){
		  	    console.log('🎮 Video oynatıldı (CSS ile play button ve poster gizlendi)');
		  	}
		  	
		  	$(window).trigger("resize").trigger("scroll").trigger("visibilitychange");
		})
		.on("pause", (e) => {
		  	video_container.removeClass("playing").addClass("paused");
		  	
		  	// Embed video'da artık CSS ile kontrol ediliyor, inline style gereksiz
		  	if(type == "embed"){
		  	    console.log('🎮 Video durduruldu (CSS ile play button ve poster gösterildi)');
		  	}
		})
		.on("ended", (e) => {
		  	var config = $(e.target).closest(".swiper-video").data("plyr");
		  	var slide = $(e.target).closest(".swiper-slide");

		  	video_container.removeClass("playing");
        	if(swiper){
        		if(slide.hasClass("user-reacted")){
                   return;
        		}
        		var activeIndex = slide.index();
        		if($(swiper.el).data("sliderAutoplay")){
				    if(activeIndex == swiper.slides.length-1){
                        swiper.slideTo(0);
		        	}else{
		        		swiper.slideNext();
		        	}
		        	if(swiper.params.autoplay.delay > 0 && !swiper.autoplay.running){
			        	swiper.autoplay.start();
			        }
				}else{
				    if(config?.loop?.active){
		               video.play();
		        	}
				}
			}else{
				if(config?.loop?.active){
	                video.play();
	        	}else{
	        	    video_container.addClass("ended");
	        	}
			}
		});

		const checkVideoViewport = function() {
		    if(video_container.hasClass("ready")){
		        try {
		            if(!video_container.is(":in-viewport")){
		                if(video.playing){
		                     video_container.addClass("viewport-paused");
		                     video.pause();
		                }
		            }else{
		                if(video_container.hasClass("viewport-paused")){
		                   video_container.removeClass("viewport-paused")
		                   video.play();
		                }
		            }
		        } catch(e) {
		            // :in-viewport plugin yüklü değil
		        }
		    }
		};
		$(window).on('scroll resize', throttle(checkVideoViewport, 100));

		document.addEventListener("visibilitychange", function() {
			if (document.hidden){
			    if(video.playing){
				   	video_container.addClass("tab-paused");
				   	video.pause();
				}
			} else {
			    if(video_container.hasClass("tab-paused")){
					video_container.removeClass("tab-paused");
					video.play();
				}
			}
	    });

		document.addEventListener("DOMContentLoaded", function() {
		    if (document.hidden) {
		    	video_container.removeClass("tab-paused");
		        video.pause();
		    }
		});

		var debounce = resizeDebounce(set_quality, 10);
		$(window).on('resize', function() {
            debounce(video);
        });
        $(document).on(
            'fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange',
            function() {
               debounce(video);
            }
        );

	    $obj
		.bind('webkitfullscreenchange mozfullscreenchange fullscreenchange', function(e) {
			var state = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen;
		    if(state){
              video_container.addClass("fullscreen");
		    }else{
              video_container.removeClass("fullscreen");
			}
			debounce(video);
		});

		debugJS(waiting_init);

		waiting_init.initElement();

		$obj.addClass(token_init);

		if($obj.hasClass("jarallax-img")){
			$obj.removeClass("jarallax-img");
			video_container.closest(".plyr").addClass("jarallax-img");
		}
		
		return video;
	}
}

$(".player.init-me").not(".lazy-container").each(function(){
	init_plyr($(this));
});

$('.wp-block-video video').each(function() {
	$(this).addClass("player");
	init_plyr($(this));
});