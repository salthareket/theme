function plyr_init_all(){
	if(!isLoadedJS("plyr")){
		return false;
	}
	$(".player").each(function(){
		if($(this).closest(".swiper-slide").length == 0 && !$(this).hasClass("plyr-init")){
			plyr_init($(this));			
		}
	});
}

function plyr_init($obj){
	if(!isLoadedJS("plyr")){
		return false;
	}
	var token_init = "plyr-init";
	if(!IsBlank($obj)){
		if($obj.hasClass(token_init)){
			return;
		}

		var config_data = $obj.attr("data-plyr-config");
		if(!IsBlank(config_data) && typeof config_data != "undefined"){
			config_data = JSON.parse(config_data);
		}else{
			config_data = {};
		}

		const video_bg = $obj.hasClass("video-bg");

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

	    const video = new Plyr($obj);
	    
	    if(video.elements.container){
	    	video.elements.container.plyr = video;
	    }

	    var poster = $obj.attr("data-poster");
        if(!IsBlank(poster) && ($obj.hasClass("plyr--youtube") || $obj.hasClass("plyr--vimeo"))){
		    setTimeout(() => {
				video.poster = poster;
			}, 500);
		}

	   var swiper = $obj.closest(".swiper").length>0?$obj.closest(".swiper")[0].swiper:false;
		var video_container = swiper?$obj.closest(".swiper-slide"):$obj.parent();

	    $obj
	    .on('ready', (e) => {
		  	const instance = e.detail.plyr;
		  	const config = instance.config;

		  	set_quality(video);

		  	$obj.find(".plyr__video-embed").fitEmbedBackground();

		  	video_container.addClass("loaded ready inited");

		  	if (document.hidden) {
		  		video_container.addClass("viewport-paused");
	            instance.pause();
	        } else if (!config.autoplay) {
	        	video_container.removeClass("viewport-paused")
	            video_container.addClass("paused");
	        }

        	if(!config.autoplay){
				video_container.addClass("paused");
			}else{
				if(video_container.is(":in-viewport") && !document.hidden){
				   video.play();
			    }
			}
        	$(window).trigger("resize");
		})
		.on("play", (e) => {
		  	video_container.removeClass("loading").addClass("playing").removeClass("paused").removeClass("inited");
		  	$(window).trigger("resize").trigger("scroll").trigger("visibilitychange");
		})
		.on("pause", (e) => {
		  	video_container.removeClass("playing").addClass("paused");
		})
		.on("ended", (e) => {
		  	var config = $(e.target).closest(".swiper-video").data("plyr");
		  	var slide = $(e.target).closest(".swiper-slide");

		  	video_container.removeClass("playing");
        	if(swiper){
        		if(slide.hasClass("user-reacted")){
                   return;
        		}
        		var activeIndex = slide.index();//swiper.activeIndex;
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
				    if(config.loop.active){
		               video.play();
		        	}
				}
			}else{
				if(config.loop.active){
	                video.play();
	        	}else{
	        	    video_container.addClass("ended");
	        	}
			}
		});

		$(window).scroll(function() {
        	if(video_container.hasClass("ready")){
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
        	}
		})
		.trigger("scroll");

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

		// Sayfa yüklendiğinde görünürlük durumunu kontrol et
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

		/*let resizeTimeout;
		window.addEventListener("resize", function() {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(function() {
	            set_quality(video);
	        }, 100);
		});*/

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

		waiting_init.initElement();

		$obj.addClass(token_init);

		if($obj.hasClass("jarallax-img")){
			$obj.removeClass("jarallax-img");
			video_container.closest(".plyr").addClass("jarallax-img");
		}

		if(video_bg && $obj.find("iframe").length > 0){
			$obj.addClass("plyr--bg");
		}

		return video;
	}
}

$(".player.init-me").each(function(){
	plyr_init($(this));
});

$('.wp-block-video video').each(function() {
	$(this).addClass("player");
	plyr_init($(this));
});