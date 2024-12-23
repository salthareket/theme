function init_swiper_video_slide(swiper, obj){
	if(isLoadedJS("plyr")){
		if(obj.find(".swiper-video").not(".inited").length > 0){
			var video_slide = obj.find(".swiper-video").not(".inited");
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
					slide.removeClass("user-reacted");
                    
                    if(slide.find(".plyr").length > 0){
						var video = slide.find(".plyr")[0].plyr;
                    }else{
                    	video = init_swiper_video_slide(swiper, slide);
                    }

					if(slide.find(".swiper-video").length > 0){
	                    if(video.autoplay){
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
    console.log("inited swiper")
	var token_init = "swiper-slider-init";
	if(!IsBlank($obj)){
	   if($obj.not("."+token_init).length > 0){
	   	  $(this).addClass(token_init);
          return init_swiper_obj($obj);
       };
	}else{
	    $(".swiper-slider").not("."+token_init).each(function() {
	    	$(this).addClass(token_init);
             console.log($(this))
			init_swiper_obj($(this));
		});
	}
}
function init_swiper_obj($obj) {
    console.log($obj)
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
        return;
    }
  
        var effect = $obj.data("slider-effect")||"slide";
        var crossFade = false;
        if(effect == "fade"){
           var crossFade = bool($obj.data("slider-cross-fade"), crossFade);
        }
        var auto_height = bool($obj.data("slider-autoheight"), false);
        var navigation = bool($obj.data("slider-navigation"), false);
        var pagination = $obj.data("slider-pagination")||"";
        var pagination_top = $obj.data("slider-pagination-top")||"";
        var pagination_visible = $obj.data("slider-pagination-visible")||0;
        var pagination_thumbs = bool($obj.data("slider-pagination-thumbs"), false);
        var autoplay = bool($obj.data("slider-autoplay"), false);
        var autoplay_pause = bool($obj.data("slider-autoplay-pause"), true);
        var delay = $obj.data("slider-delay")||(autoplay?5000:0);
        var loop = bool($obj.data("slider-loop"), false);
        var lazy = bool($obj.data("slider-lazy"), false);
        var zoom = bool($obj.data("slider-zoom"), false);
        var direction = IsBlank($obj.data("slider-direction"))||$obj.data("slider-direction")=="horizontal"?"horizontal":"vertical";
        var grab = bool($obj.data("slider-grab"), true);
        var allow_touch_move = bool($obj.data("slider-allow-touch-move"), true);
        var scrollbar = false;
        var scrollbar_el = {};
        if($obj.find(".swiper-scrollbar").length>0){
           scrollbar = true;
           scrollbar_el = $obj.find(".swiper-scrollbar");
        }else{
           scrollbar = bool($obj.data("slider-scrollbar"),false);
        }
        var slidesPerView = $obj.attr("data-slider-slides-per-view")||1;
        var slidesPerGroup = $obj.attr("data-slider-slides-per-view")||1;

        var card_slider = $obj.parent(".card-body").length>0|| $obj.parent().parent(".card-body").length>0?true:false;
            card_slider = card_slider?$obj.closest(".card"):card_slider;

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
            var galleryThumbs = new Swiper($obj.find(".swiper-thumbs")[0], {
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
            speed: 750,
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

                    /*$(".link-initial a").on("click", function() {
                        debugJS($("#home").next("section").attr("id"))
                        root.ui.scroll_to("#" + $(".main-content").find("section").first().attr("id"));
                    });*/

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
                    //footer visibility
                    if (card_slider) {
                        if (card_slider.find(">.card-footer .swiper-pagination").length > 0) {
                            if (slider.slides.length <= slider.params.slidesPerView) {
                                card_slider.find(">.card-footer").addClass("d-none");
                            } else {
                                card_slider.find(">.card-footer").removeClass("d-none");
                            }
                        }
                    }
                    if($el.find("[data-bg-check]").length > 0 && isLoadedJS("backgroundcheck")){
                        bg_check();
                    }
                    if($el.find(pagination_top)){
                        $el.find(".swiper-pagination").addClass(pagination_top);
                    }
                },
                loopFix : function(){
                   lazyLoadInstance.update();
                },

                slideChangeTransitionStart: function (e) {
                	//debugJS($(e.slides[e.activeIndex]))
                    if($(e.slides[e.activeIndex]).find(".swiper-container").length > 0){
                    	var nested = $(e.slides[e.activeIndex]).find(".swiper-container")[0].swiper;
                    	if(typeof nested !== "undefined"){
                    		nested.autoplay.stop();
                    	    nested.slideTo(0);
                    	}
                    }
                },
                
                slideChangeTransitionEnd: function (e) {
                    if($(this.el).find("[data-bg-check]").length > 0 && isLoadedJS("background-check")){
                       BackgroundCheck.refresh();
                    }
                },
                resize: function() {
                    if (card_slider) {
                        if (card_slider.find(">.card-footer .swiper-pagination").length > 0) {
                            if (this.slides.length <= this.params.slidesPerView) {
                                card_slider.find(">.card-footer").addClass("d-none");
                            } else {
                                card_slider.find(">.card-footer").removeClass("d-none");
                            }
                        }
                        if($(this.el).find("[data-bg-check]").length > 0 && isLoadedJS("background-check")){
                        	BackgroundCheck.refresh();
                        }
                    }
                },
                slidesGridLengthChange: function() {
                    //footer visibility
                    if (card_slider) {
                        if (card_slider.find(">.card-footer .swiper-pagination").length > 0) {
                            if (this.slides.length <= this.params.slidesPerView) {
                                card_slider.find(">.card-footer").addClass("d-none");
                            } else {
                                card_slider.find(">.card-footer").removeClass("d-none");
                            }
                        }
                    }
                    if(this.params.slidesPerView == "auto"){
                    	//this.params.freeMode = true;
                    	if(this.params.loop){
	                       this.params.loopedSlides = this.slides.length;
                    	}
                    }else{
                    	//this.params.freeMode = false;
                    }
                    if($(this.el).find("[data-bg-check]").length > 0 && isLoadedJS("background-check")){
                      	BackgroundCheck.refresh();
                    }
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
        }

        if (navigation) {
	        var prevEl = $obj.find('.swiper-button-prev')[0];
	        var nextEl = $obj.find('.swiper-button-next')[0];
	        if (!prevEl && !nextEl) {
	            prevEl = $obj.parent().find('.swiper-button-prev')[0];
	            nextEl = $obj.parent().find('.swiper-button-next')[0];
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
	                if (card_slider.find(">.card-footer .swiper-pagination").length > 0) {
	                    pagination_obj = card_slider.find(">.card-footer .swiper-pagination")[0];
	                }else{
	            	   card_slider.find(">.card-footer").prepend("<div class='swiper-pagination'></div>");
	            	   pagination_obj = card_slider.find(">.card-footer .swiper-pagination")[0];
	            	}
	            }else{
	            	if($obj.parent().find('.swiper-pagination').length > 0){
	            	   pagination_obj = $obj.parent().find('.swiper-pagination')[0];
	            	}else{
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
            if(pagination == "bullets"){
               if(pagination_visible > 0){
               	  options["pagination"]["dynamicBullets"] = true;
               	  options["pagination"]["dynamicMainBullets"] = pagination_visible;
               }
            }
            if(pagination == "custom"){
            	options["pagination"]["renderCustom"] =  function (swiper, current, total) {
			      return ('0' + current).slice(-2) + '/' + ('0' + total).slice(-2);
			    }
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
            if(!autoplay_pause){
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