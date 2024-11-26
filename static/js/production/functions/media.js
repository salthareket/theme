/*function plyr_init_all(){
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
		$obj.addClass(token_init);
		var config_data = $obj.attr("data-plyr-config");
		    config_data = JSON.parse(config_data);
	    const video = new Plyr($obj);//, config);
	    	  video.elements.container.plyr = video;
  
	    var swiper = $obj.closest(".swiper").length>0?$obj.closest(".swiper")[0].swiper:false;
		var video_container = swiper?$obj.closest(".swiper-slide"):$obj.parent();

	    $obj
	    .on('ready', (e) => {
		  	const instance = e.detail.plyr;
		  	const config = instance.config;

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

	    $obj
		.bind('webkitfullscreenchange mozfullscreenchange fullscreenchange', function(e) {
			var state = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen;
		    if(state){
                video_container.addClass("fullscreen");
		    }else{
                video_container.removeClass("fullscreen");
			}    
		});

		waiting_init.initElement();
		
		return video;
	}
}*/

jQuery.fn.allLazyLoaded = function(fn){
    if(this.length){
        var loadingClass, toLoadClass;
        var $ = jQuery;
        var isConfigured = function(){
            var hasLazySizes = !!window.lazySizes;

            if(!loadingClass && hasLazySizes){
                loadingClass = '.' + lazySizes.cfg.loadingClass;
                toLoadClass = '.' + lazySizes.cfg.lazyClass;
            }

            return hasLazySizes;
        };

        var isComplete = function(){
            return !('complete' in this) || this.complete;
        };

        this.each(function(){
            var container = this;
            var testLoad = function(){

                if(isConfigured() && !$(toLoadClass, container).length && !$(loadingClass, container).not(isComplete).length){
                    container.removeEventListener('load', rAFedTestLoad, true);
                    if(fn){
                        fn.call(container, container);
                    }
                    $(container).trigger('containerlazyloaded');
                }
            };
            var rAFedTestLoad = function(){
                requestAnimationFrame(testLoad);
            };

            container.addEventListener('load', rAFedTestLoad, true);
            rAFedTestLoad();
        });
    }
    return this;
};