/*function _confirm(title, msg, size, className, btn_confirm, btn_cancel, callback){
	//dependencies: bootbox
	    var options = {
       	    	className: "modal-confirm nodal-alert text-center ",
	       	    message : ".",
	       	    buttons: {
			        cancel: {
			            label: 'No',
			            className: 'btn-outline-danger btn-extend pull-right '
			        },
			        confirm: {
			            label: 'Yes',
			            className: 'btn-success btn-extend pull-left '
			        }
			    }
		}
		if(!IsBlank(title)){
			options["title"] = title;
		}
		if(!IsBlank(msg)){
			options["message"] = msg;
		}else{
			options["className"] += "modal-alert-textless ";
		}
		if(!IsBlank(size)){
			options["size"] = size;
		}
		if(!IsBlank(className)){
			options["className"] = className;
		}
		if(!IsBlank(btn_confirm)){
			options["buttons"]["confirm"]["label"] = btn_confirm;
		}
		if(!IsBlank(btn_cancel)){
			options["buttons"]["cancel"]["label"] = btn_cancel;
		}
		if(!IsBlank(callback)){
			options["callback"] = function(result){ 
									    if(result){
									    	callback(result);
									    }
								  }
		}
        var modal = bootbox.confirm(options);
        if(IsBlank(title)){
           modal.find(".modal-header").remove();
        }
        if(IsBlank(msg)){
           modal.find(".modal-body").remove();
        }
        return modal;
}
function _alert(title, msg, size, className, btn_ok, callback, closeButton, centerContent){
	    if(!isLoadedJS("bootbox")){
	    	alert(title+"\n"+msg);
	    	return false;
	    }
	    var options = {
       	    	className: "modal-alert text-center ",
       	    	message : ".",
	       	    buttons: {
		       	    	ok : {
						    label: 'OK',
						    className: 'btn-outline-success btn-extend'
						}
				}
		}
		var fullscreen = false;
		var footer = true;
		var content_classes = "";
		if(!IsBlank(title)){
			options["title"] = title;
		}
		if(!IsBlank(msg)){
			options["message"] = msg;
		}else{
			options["className"] += "modal-alert-textless";
		}
		if(!IsBlank(size)){
			options["size"] = size;
		}
		if(!IsBlank(className)){
			var classes = className.split(" ");
			options["className"]  += " "+className;
			if(className.indexOf("modal-fullscreen") > -1){
				fullscreen = true;
			}
			for(var i=0;i<classes.length;i++){
				if(classes[i].indexOf("bg-") > -1 || classes[i].indexOf("text-") > -1 ){
                   content_classes += classes[i]+" ";
				}
			}
		}
		if(!IsBlank(closeButton)){
			options["closeButton"]  = closeButton;
		}
		if(!IsBlank(callback)){
			options["callback"] = function(){ 
				callback();
			}
		}
		if(!IsBlank(btn_ok)){
			options["buttons"]["ok"]["label"] = btn_ok;
		}else{
			footer = false;
		}
    
        var modal = bootbox.alert(options);
        if(fullscreen){
        	modal.find(".modal-dialog").addClass("modal-fullscreen");
        }
        if(!footer){
        	modal.find(".modal-footer").remove()
        }
        if(!IsBlank(centerContent)){
           modal.find(".modal-body").addClass("d-flex align-items-center justify-content-center");
        }
        if(!IsBlank(content_classes)){
           modal.find(".modal-content").addClass(content_classes);
        }
}
function _prompt(){
	//dependencies: bootbox
	var options = {
	    title: 'A custom dialog with buttons and callbacks',
	    message: "<p>This dialog has buttons. Each button has it's own callback function.</p>",
	    size: 'large',
	    buttons: {
	        cancel: {
	            label: "I'm a cancel button!",
	            className: 'btn-danger',
	            callback: function(){
	                debugJS('Custom cancel clicked');
	            }
	        },
	        noclose: {
	            label: "I don't close the modal!",
	            className: 'btn-warning',
	            callback: function(){
	                debugJS('Custom button clicked');
	                return false;
	            }
	        },
	        ok: {
	            label: "I'm an OK button!",
	            className: 'btn-info',
	            callback: function(){
	                debugJS('Custom OK clicked');
	            }
	        }
	    }
	};
	var dialog = bootbox.dialog(options);
}

function modal_confirm(){
	//{dependencies: [ 'bootbox' ]}
	var token_init = "modal-confirm-init";
    $("[data-toggle='confirm']").unbind("click").on("click", function(e){
       	e.preventDefault();
       	var url = $(this).attr("href");
       	var title = $(this).data("confirm-title");
       	var message = $(this).data("confirm-message");
       	var size = $(this).data("confirm-size");
       	var classname = $(this).data("confirm-classname");
       	var btn_ok = $(this).data("confirm-btn-ok");
       	var btn_cancel = $(this).data("confirm-btn-cancel");
       	var _callback = $(this).data("confirm-callback");
       	var callback = function(){};
       	if(IsUrl(url)){
	       	var callback = function(){
	       		$("body").addClass("loading");
	       	    window.location.href = url;
	       	}       	    	
       	}else if(!IsBlank(_callback)){
	       	var callback = function(){
	       	    eval(_callback)
	       	}
       	}
       	_confirm(title, message, size, classname, btn_ok, btn_cancel, callback);
    });	
}
function modal_alert(){
	//dependencies: bootbox
	var token_init = "modal-alert-init";
    $("[data-toggle='alert']").on("click", function(e){
       	e.preventDefault();
       	var url = $(this).attr("href");
        var title = $(this).data("alert-title");
        var message = $(this).data("alert-message");
        if(!IsBlank(message)){
	        if(message.indexOf("#")==0){
	           message = $(message).html();
	        }        	
        }
        var size = $(this).data("alert-size");
        var btn_ok = $(this).data("alert-btn-ok");
        var classname = $(this).data("alert-classname");
        var _callback = $(this).data("alert-callback");
        if(IsUrl(url)){
	       	var callback = function(){
	       		$("body").addClass("loading");
	       	    window.location.href = url;
	       	}       	    	
       	}else if(!IsBlank(_callback)){
	       	var callback = function(){
	       	    eval(_callback)
	       	}
       	}
       	_alert(title, message, size, classname, btn_ok, callback);
    });	
}
*/

function notification_alert(){
	var token_init = "notification-alert-init";
    $("[data-toggle='notification']").on("click", function(e){
       	e.preventDefault();
       	var target = $($(this).data("target"));
       	var message = $(this).data("notification-message");
       	target.prepend('<div class="alert alert-success text-center" role="alert">'+message+'</div>');
       	setTimeout(function(){
            target.find(".alert").first().fadeOut(500, function(){
               	  $(this).remove();
            })
       	}, 3000);
    });	
}



/*
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
	    	init_swiper_video_slide([], $obj)
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

                    //fade in
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
                    if($el.find("[data-bg-check]").length > 0){
                        bg_check();
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



function scrollable_init(){
	var token_init = "scrollable-init";
    $(".scrollable").not("."+token_init).each(function(e){
	    $(this).addClass(token_init);
        SimpleScrollbar.initEl($(this)[0]);
    });	
}*/

function star_rating_readonly(){
	var token_init = "star-rating-readonly-init";
    if($(".star-rating-readonly-ui").not("."+token_init).length>0){
        $(".star-rating-readonly-ui").not("."+token_init).each(function(){
           	$(this).addClass(token_init);
            var stars = $(this).data("stars") || 5;
            var value = $(this).data("value");
           	$(this).html(get_star_rating_readonly(stars, value, "", "", "" ));
        });
	}
}
function get_star_rating_readonly($stars, $value, $count, $star_front, $star_back ){
    $stars = parseInt($stars);
    $stars = IsBlank($stars)||isNaN($stars)?5:$stars;
    $value = parseFloat($value);
    if(typeof $count === "undefined"){
      $count="";
    }else{
      if($count>0){
         $count='<span class="count">('+$count+')</span>';
      }else{
         $count = "";
      }
    }
    var $className = "";
    if($value == 0 ){
       //return "";*  
       $className = " not-reviewed ";
    }
    $value = IsBlank($value)||isNaN($value)?0:$value;
    $star_front = IsBlank($star_front)?"fas fa-star":$star_front;
    $star_back = IsBlank($star_back)?"fas fa-star":$star_back;
    var $percentage = (100 * $value)/$stars;
    var $code ='<div class="star-rating-custom star-rating-readonly '+$className+'" title="' + $value + '">' +
                    '<div class="back">';
                            for ($i = 1; $i <= $stars; $i++) {
                                 $code += '<i class="'+$star_back+'" aria-hidden="true"></i>';
                            };
                      $code += '<div class="front" style="width:'+$percentage+'%;">';
                                   for ($i = 1; $i <= $stars; $i++) {
                                        $code += '<i class="'+$star_front+'" aria-hidden="true"></i>';
                                   };
                      $code += '</div>' +
                    '</div>' +
                    '<div class="sum text-nowrap">'+$value.toFixed(1) + $count +'</div>' +
               '</div>';
    return $code;
}
function star_rating(){
	var token_init = "star-rating-readonly-init";
    if($(".star-rating-ui").not("."+token_init).length>0){
        $(".star-rating-ui").not("."+token_init).each(function(){
           	$(this).addClass(token_init);
            var stars = $(this).data("stars") || 5;
            var value = $(this).data("value");
            var required = $(this).hasAttr("required").length;
           	$(this).html(get_star_rating(stars, value, "", "", "", required));
        });
	}
}
function get_star_rating($stars, $value, $count, $star_front, $star_back, $required=false ){
    $stars = parseInt($stars);
    $stars = IsBlank($stars)||isNaN($stars)?5:$stars;
    $value = parseFloat($value);
    var $id = generateCode(5);
    var $className = "";
    if($value == 0 ){
       //return "";*  
       $className = " not-reviewed ";
    }
    $value = IsBlank($value)||isNaN($value)?0:$value;
    $star_front = IsBlank($star_front)?"fas fa-star":$star_front;
    $star_back = IsBlank($star_back)?"fas fa-star":$star_back;
    var $code ='<div id="star-rating-'+$id+'" class="star-rating-custom star-rating '+$className+'" title="' + $value + '">' +
                    '<div class="back">';
                            for ($i = 1; $i <= $stars; $i++) {
                                 $code += '<i class="'+$star_back+'" aria-hidden="true"></i>';
                            };
                      $code += '<div class="front">';
                                   for ($i = $stars; $i > 0; $i--) {
                                        //$code += '<i class="'+$star_front+'" aria-hidden="true"></i>';
                                        $code += '<input class="star-rating-input" id="star-rating-'+$id+'-'+$i+'" type="radio" name="rating" value="'+$i+'" '+($required?"required":"")+' '+($value==$i?"checked":"")+'>';
                                        $code += '<label class="star-rating-star '+$star_front+'" for="star-rating-'+$id+'-'+$i+'" title="'+$i+' out of '+$stars+' stars"></label>';
                                   };
                      $code += '</div>' +
                    '</div>' +
                    '<div class="sum text-nowrap">'+$value.toFixed(1) +'</div>' +
               '</div>';
               $code += '<script>$( document ).ready(function() {$("#star-rating-'+$id+'").find("input").on("change", function(){var value = parseFloat($(this).val());value = IsBlank(value)||isNaN(value)?0:value;debugJS(value);$("#star-rating-'+$id+'").find(".sum").html(value.toFixed(1))})});</script>';
    return $code;
}

function btn_loading(){
	var token_init = "btn-loading-init";
	$(".btn-loading").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
            $(this).addClass("loading disabled");
        })
        .addClass(token_init);
    });	
}
function btn_loading_page(){
	var token_init = "btn-loading-page-init";
	$(".btn-loading-page").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
        	if(IsUrl($(this).attr("href"))){
        		if($(this).attr("target") == "_blank"){
        			e.preventDefault();
        			redirect_polyfill($(this).attr("href"), true);
        		}else{
        			$("body").addClass("loading-process");
        		}
        	}
        })
        .addClass(token_init);
	});
	window.addEventListener('popstate', function () {
        // Geri tuşuna basıldığında sınıfı kaldır
	    $("body").removeClass("loading-process");
	});
}
function btn_loading_page_hide(){
	var token_init = "btn-loading-page-hide-init";
	$(".btn-loading-page-hide").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
        	if(IsUrl($(this).attr("href"))){
			    $("body").addClass("loading-hide");
			}
        })
        .addClass(token_init);
	});
}
function btn_loading_self(){
	var token_init = "btn-loading-self-init";
	$(".btn-loading-self").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
            $(this).addClass("loading disabled");
        })
        .addClass(token_init);
    });	
}
function btn_loading_parent(){
	var token_init = "btn-loading-parent-init";
	$(".btn-loading-parent").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
            $(this).parent().addClass("loading-process disabled");
        })
        .addClass(token_init);
    });	
}
function btn_ajax_method(){ /// ***new*** updated function
	var token_init = "btn-ajax-method-init";
	function init_ajax($obj, $button){
		debugJS("btn_ajax_method="+$obj.data("ajax-method"))
		debugJS("obj")
		debugJS($obj)
		debugJS("button")
		debugJS($button)
		    var $data = $obj.data();
        	var $form = {};
        	if($data.hasOwnProperty("form")){
               $data["form"] = $($data["form"])
        	}
			delete $data["method"];
			var callback = function(){
	            var query = new ajax_query();
				    query.method  =  $obj.data("ajax-method");
				    query.vars    = $data;
					query.form    = $form;
					/*query.objs = {
		        		"btn" : $obj
		        	}*/
		        	if($button){
		        		query.objs = {
			        		"btn" : $obj
			        	}
		        	}else{
		        		query.objs = $obj;
		        	}
					query.request();				
			}
			if($data["confirm"]){
				var confirm_message = $data["confirm-message"];
				if(IsBlank(confirm_message)){
                   confirm_message =  "Are you sure?";
				}
				var modal = _confirm(confirm_message, "", "md", "modal-confirm", "Yes", "No", callback);
			}else{
                callback();
			}
	}
	$("[data-ajax-method]").not("."+token_init).not("[data-ajax-init='false']").each(function(){
		var $obj = $(this);
		var is_button = $obj.is('button, a, input[type="button"], input[type="submit"], [role="button"]');
        $obj
        .addClass(token_init);
        if(is_button){
	        $obj
	        .on("click", function(e){
	        	e.preventDefault();
			    init_ajax($(this), true);
	        });
        }else{
            init_ajax($obj, is_button);
        }
    });

    var token_init = "btn-ajax-submit-init";
	$("[data-ajax-submit]").not("."+token_init).each(function(){
		var $obj = $(this);
        $obj
        .addClass(token_init)
        .on("click", function(e){
        	var form = $($(this).attr("data-ajax-submit"));
        	if(form.length > 0){
        		form.submit();
        	}
        });
    });
}



function btn_forgot_password(){
	//dependencies: bootbox
	var token_init = "btn-forgot-password-init";
	$(".btn-forgot-password").not("."+token_init).each(function(e){
		var dialog = bootbox.dialog({
			title: 'Forgot Password',
			message: //'<p>We will send a password reset link to your e-mail address.</p>' +
				'<form id="lostPasswordForm" class="form form-validate" autocomplete="off" method="post" action="" data-ajax-method="create_lost_password">' +
					'<div id="message"></div>' +
					'<div class="form-group form-group-slim">' +
						'<label class="form-label form-label-lg">Email Address</label>' +
						'<input class="form-control form-control-lg" type="email" name="username" placeholder="Email Address" required/>' +
					'</div>' +
				'</form>',
				size: 'md',
				class : "modal-lost-password modal-fullscreen",
				buttons: {
					cancel: {
						label: "Cancel",
						className: 'btn-danger',
						callback: function(){
							debugJS('Custom cancel clicked');
						}
					},
					ok: {
						label: "Send my password",
						className: 'btn-info',
						callback: function(){
							var form	= $("form#lostPasswordForm");
							var vars = {
								user_login:	form.find("[name='username']").val()															
							};
							this.find(".modal-content").addClass("loading-process");
							var query = new ajax_query();
								query.method = "lost_password";
								query.vars   = vars;
								query.form   = $(form);
								query.request();
								return false;
						}
					}
				}
			});
    });
}


/*
function selectpicker_change(){
	//dependencies: bootstrap-select
	$(".selectpicker.selectpicker-url").on("change",function(e){
			var url = $(this).val();
			if(IsUrl(url)){
				$("body").addClass("loading");
				window.location.href = url;
			}else{
				url = $(this).find("option[value='"+url+"']").data("value");
				if(IsUrl(url)){
					$("body").addClass("loading");
					window.location.href = url;
				}
			}
	});

	$(".selectpicker.selectpicker-url-update").on("change",function(){
            var url = $(this).val();
            var title = $(this).find("option[value='"+url+"']").text();
            window.history.pushState('data', title, url);
            document.title = title;
	});

	$(".selectpicker.selectpicker-country").each(function(){
			$(this).on("change",function(){
	            var vars =  {
	            	          id : $(this).val(),
	            	          state : $(this).data("state")
	            	        };
	            var query = new ajax_query();
				    query.method = "get_states";
				    query.vars = vars;
				    query.request();
			})
	}).trigger("change");
}
*/

//new
function btn_card_option(){
	var token_init = "btn-card-option-init";
	$(".btn-card-option").find("input[checked]").parent().addClass("active").closest(".card").addClass("active");
	$(".btn-card-option").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
        	$(this).addClass("active");
        	var card = $(this).closest(".card");
        	    card.parent().find(".card.active").removeClass("active").find(".btn-card-option.active").removeClass("active");
                card
                .addClass("active")
                .find("input[type='radio'], input[type='checkbox']").prop("checked", true);
        })
        .addClass(token_init);
    });		
}
//new
function btn_list_group_option(){
	var token_init = "btn-list-group-option-init";
	$(".list-group-options").each(function(){
		var list = $(this);
		list.find(".list-group-item").not(".list-group-item-content").each(function(){
            var option = $(this);
                var input = option.find("input");
                if(input.is(":checked")){
                   option.addClass("active");
                }
                option.on("click", function(e){
                	//e.preventDefault();
                	if(input.attr("type") == "radio"){
                	   input.prop("checked", true);
                	   list.find(".active").removeClass("active");
                	   option.addClass("active");
                	}else{
                	   if(input.is(":checked")){
                	   	  input.focus().prop("checked", false);
                	   	  option.removeClass("active");
                	   }else{
                	   	  input.focus().prop("checked", true);
                	   	  option.addClass("active");
                	   }
                	}
                });
		})
		list.addClass(token_init);
	})	
}



function getEvents(obj, calendar, month, year){
	var vars = {
	 	month : month,
	 	year  : year,
	 	date  : year+"-"+month
	 };
	 var objs = {
	 		obj      : obj,
	 		calendar : calendar
	 };
	 var query = new ajax_query();
		 query.method = "get_events";
  		 query.vars   = vars;
		 query.form   = {};
		 query.objs   = objs;
		 query.request();
}
/*function calendar(){
	var token_init = "calendar-init";
    if($(".calendar").not("."+token_init).length > 0){
    	var currentMonth = moment().format('MM');
		var currentYear = moment().format('YYYY');
		var nextMonth    = moment().add(1,'month').format('YYYY-MM');
	    $(".calendar").not("."+token_init).each(function(){
	    	eventsThisMonth: [ ];
	        var events_list=[];

	        var $calendar = $(this);
	            $calendar.addClass(token_init);
	       	var $template = $calendar.data("template");
	       	if(!IsBlank($template)){
	        	twig({
						href : ajax_request_vars.assets_url+"static/templates/"+$template+".twig",
						async : false,
						allowInlineIncludes : true,
						load: function(template) {
							moment.locale(root.lang);
							debugJS(moment().calendar())
							$calendar.clndr({
								moment: moment,
							    render : function(data){
							  	        return template.render(data);
							    },
							    startWithMonth: moment(),
							    clickEvents: {
								    // fired whenever a calendar box is clicked.
								    // returns a 'target' object containing the DOM element, any events, and the date as a moment.js object.
								    click: function(target){
								    	  $(".popover").each(function(){
											 var id=$(this).attr("id");
											 $("[aria-describedby="+id+"]").popover("destroy");
										  });
										 
										  if(target.events.length) {
											 var today = new Date();
	                                             
										     var eventDate = new Date(target.events[0].date);
											 debugJS(today+" = "+eventDate)
											  //if(eventDate<=today){
											     window.location.href=target.events[0].url;
											  //}
										  }
								    },
								    // fired when a user goes forward a month. returns a moment.js object set to the correct month.
								    nextMonth: function(month){ },
								    // fired when a user goes back a month. returns a moment.js object set to the correct month.
								    previousMonth: function(month){ },
								    // fired when a user goes back OR forward a month. returns a moment.js object set to the correct month.
								    onMonthChange: function(month){
								    	moment.locale("en");
								    	debugJS(month)

								    	getEvents($calendar, this, month.locale('en').format('M'), month.locale('en').format('YYYY'));
								    },
								    // fired when a user goes to the current month/year. returns a moment.js object set to the correct month.
								    today: function(month){ }
								},
							    events: [],
								doneRendering: function(am){ 
									     var events=this.options.events;
									     debugJS(this);
									     if(!IsBlank(events)){
								             for(var event in events){
												 var eventDay=events[event];
												 var obj=$(".calendar-day-"+eventDay.date);
												 obj.attr("id",eventDay.date.replaceAll("-","_"));
												 obj.attr("role","button");
												 obj.attr("data-content",eventDay.title);
												 obj.attr("data-trigger","focus");//"focus");
												 obj.attr("data-html","true");
												 obj.attr("data-container","body");
												 obj.attr("data-template",'<div class="popover text-xs" role="tooltip"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>');
												 obj.on("mouseover",function(){
													$(this).popover("show") 
												 });
												 obj.on("mouseout",function(){
													$(this).popover("hide") 
												 });
											 }
										}
								},
								ready:function(aa){
									getEvents($calendar, this, currentMonth, currentYear)
								}
							});
						}
				});
	        }
	    });
	}
}



function readmore_js(){
	//dependencies: readmore-js
    $(".readmore-js").each(function(){
    	var height = $(this).data("height") || 300;
    	if(IsBlank($(this).attr("id"))){
    		$(this).attr("id", generateCode(5));
    	}
        $(this).readmore({
            speed: 75,
            collapsedHeight: height,
            moreLink: '<a href="#" class="btn btn-link btn-slim float-right btn-more" style="display:inline-block;width:auto;margin-top:10px">Read more</a>',
            lessLink: '<a href="#" class="btn btn-link btn-slim float-right btn-less" style="display:inline-block;width:auto;margin-top:10px">Read less</a>',
            beforeToggle: function(trigger, element, expanded) {
	            debugJS(element)
	            if(expanded){
	            	$('html,body').animate({ scrollTop: element.offset().top }, 75);
	            }
            }
        });
    });
}*/


function btn_favorite(){
    $(".btn-favorite").unbind("click").on("click", function(e){
		e.preventDefault();
		if($(this).hasClass("active")){
			favorites.remove($(this));
		}else{
			favorites.add($(this));
		}
	});
}
/*
function stickyScroll(){
	var token_init = "sticky-scroll-init";
	if($(".stick-top").not("."+token_init).length > 0){
		if(typeof stickyOptions !== "undefined"){
			var $options_tmp = stickyOptions;
	        $(".stick-top").not("."+token_init).each(function(){
	        	$(this).addClass(token_init);
	            var $options = $options_tmp;
	        	var $args = $options["assign"]($(this));
	        	if(Object.keys($args).length>0){
	        	   $options = nestedObjectAssign({}, $options, $args);
	        	}
	           	$(this).hcSticky($options);
	           	debugJS($options)
	            $(this).hcSticky('update', $options);
	        });
	        //delete $options_tmp["assign"];			
		}

    }
}


function toast_notification($notification){
	//dependencies: jquery-toast-plugin
	        var text = "";
	        if(!IsBlank($notification.url)){
	        	text += "<a href='"+$notification.url+"' class='jq-toast-text-linked'>";
	        }
	        text += "<div class='jq-toast-text-wrapper'>";
	        if(!IsBlank($notification.sender.image)){
		       text += $notification.sender.image;
		    }
		    if(!IsBlank($notification.message)){
		       text += "<div class='jq-toast-text'>"+$notification.message;
		    }
		    	if(!IsBlank($notification.time)){
			       text += "<small class='jq-toast-text-date'>"+$notification.time+"</small>";
			    }
		    if(!IsBlank($notification.message)){
		       text += "</div>";
		    }
            text += "</div>";
	        if(!IsBlank($notification.url)){
	        	text += "</a'>";
	        }
            $.toast({
			    //heading: response[i].title,
			    text: text,
			    stack: 4,
			    position: 'bottom-left',
			    icon : false,
			    bgColor: '#fff',
                textColor: '#333',
                hideAfter: 6000,
                loaderBg: '#bf1e2d',
                showHideTransition : 'fade',
                beforeShow: function () {
			        $("body").addClass('toast-open');
			    },
			    afterShown: function () {
			    },
			    beforeHide: function () {
			    },
			    afterHidden: function () {
			        $("body").removeClass('toast-open');
			    }
			});
			myToast.update({
			    position: 'top-left',
			    stack : 1,
			    showHideTransition : 'slide'
			});
}
*/

function ajax_paginate($obj){
	var token_init = "ajax-paginate-init";
    
    //reset paginate
	if(!IsBlank($obj)){
		var $data = getDataAttributes(obj);

			$obj
			.removeClass(token_init)
			.attr("data-page", 1)
			.removeAttr("data-page-total")
			.find(".list-cards").empty();
			$obj
			.find(".card-footer")
			.removeClass("d-none")
			.find(".btn").removeClass("processing").removeClass("completed");/**/
	
	}

    if($(".ajax-paginate").not("."+token_init).length>0){
        $(".ajax-paginate").not("."+token_init).each(function(){

        	var obj = $(this)
           	obj.addClass(token_init)
			//delete $data["method"];
			var btn = obj.find(".btn-next-page");
			var $data = getDataAttributes(obj);
			var loader = "";
			if(obj.find(".list-cards").length > 0){
			   loader = obj.find(".list-cards").prop("tagName").toLowerCase();
			}
			$data["loader"] = loader;
			if(IsBlank($data.load_type) || typeof $data.load_type === "undefined"){
               $data["load_type"] = "button";
			}
			if($data.form){
			    var btn_submit = $($data.form).find("[type='submit']");
			    if(btn_submit.length == 0){
			    	btn_submit = $("[data-ajax-submit='"+$data.form+"']");
			    }
			    btn_submit.on("click", function(e){
			   	   e.preventDefault();
			   	   $($data.form).find("input[name='page']").val(1);
			   	   obj.find(".list-cards").empty();
			   	   $($data.form).submit();
			   });
			}

			function ajax_paginate_load(btn){
                
				    if(btn.hasClass("processing") || btn.hasClass("completed")){
				    	return;
				    }
				    btn.addClass("loading processing");
			    	
			    	var $data = getDataAttributes(obj);

			    	debugJS($data)

			    	var loader = obj.find(".list-cards").prop("tagName").toLowerCase();
			        $data["loader"] = loader;

			    	if($data.form){

			    	    var method = $($data.form).attr("data-ajax-method");
			    	    ajax_hooks[method]["done"] = function(response, vars, form, objs){
			    	   	debugJS(response)
			    	   	debugJS(vars)

			    	   	    //if(typeof response.data !== "undefined"){
				    	   	    var total = parseInt(response.data.count);
					    	   	var page = parseInt(response.data.page);
					    	   	var page_total = parseInt(response.data.page_total);
					    	   	var posts_per_page = parseInt(vars.posts_per_page);
			    			    form.find("input[name='page']").val(page + 1);
			    			    //if(response.data.page >= response.data.page_total){
			    			    if(page == page_total){
							       btn.closest(".card-footer").addClass("d-none");
							       btn.addClass("completed").removeClass("loading processing");
							    }else{
							       btn.closest(".card-footer").removeClass("d-none");
							       btn.removeClass("loading processing");
							    }
							    if(btn.find(".item-left").length > 0){
							    	debugJS(total , posts_per_page,page)
							       btn.find(".item-left").text(total - posts_per_page * page);
	                               //btn.find(".item-left").text(total - (page * Math.ceil(total/page_total)));
							    }
							    if(btn.hasClass("ajax-load-scroll")){
	                               $(window).trigger("scroll");
								}			    	   	    	
			    	   	    //}
						}
			    	    $($data.form).submit();

			    	}else{

			            var query = new ajax_query();
						    query.method = obj.attr("data-ajax-method");
						    query.vars = $data;
						    query.objs = {
						    	obj : obj
						    }
						    query.after = function(response, vars, form, objs){

						    	btn.removeClass("loading processing");
						    
							    	var total = parseInt(response.data.count);
					    	   	    var page = parseInt(response.data.page);
					    	   	    var page_total = parseInt(response.data.page_total);
					    	   	    var posts_per_page = parseInt(vars.posts_per_page);
					    	   	    //alert("aaa")
					    	   	    debugJS(response.data);
							    	obj.attr("data-page", page + 1);
							    	obj.attr("data-page-total", page_total);
							    	obj.attr("data-count", response.data.count);
							    	if(total > 0){
							    	   obj.addClass("has-post");
							    	}

							    	if(page == page_total || page_total == 0){
							    	//if((total - posts_per_page*page) <= 0){
								       btn.closest(".card-footer").addClass("d-none");
								       btn.addClass("completed").removeClass("loading processing");
								       if(page_total == 0 && IsBlank(vars["notfound"])){
								       	  btn.closest(".card-footer").remove();
								       	  if(response.data.loader == "ul"){
								       	  	 obj.find(".list-cards").parent().remove();
								       	  }else{
								       	  	 obj.find(".list-cards").remove();
								       	  }
								       	  debugJS(response)
								       	  debugJS(vars)
								       }
								    }else{
								       btn.closest(".card-footer").removeClass("d-none");
								       btn.removeClass("loading processing");
								    }
								    if(btn.find(".item-left").length>0){
								    	debugJS(total , posts_per_page, page)
								       btn.find(".item-left").text(total - posts_per_page*page);
		                               //btn.find(".item-left").text(total - (page * Math.ceil(total/page_total)));
								    }
								    if(btn.hasClass("ajax-load-scroll")){
	                                   $(window).trigger("scroll");
								    }
								//}
						    }
							query.request();			    		
					}
			//});
		    }

		    if($data.load_type == "count" && !$data.preload){

               ajax_paginate_load(btn);

		    }else{


				switch($data.load_type){
					case "button":
					case "":
					    btn.addClass("ajax-load-click")
					    btn.on("click", function(e){
				    	   e.preventDefault();
				    	   ajax_paginate_load($(this));
				        });
						if(btn.data("init")){
						    btn.trigger("click");
						}
					break;
					case "scroll":
						if(!btn.hasClass("ajax-load-scroll")){
		                    btn.addClass("ajax-load-scroll")
							$(window).scroll(function() {
						        if( btn.is(":in-viewport")) {
				                    ajax_paginate_load(btn);
						        }
						    }).trigger("scroll");							
						}else{
							ajax_paginate_load(btn);
						}
					break;
				}

		    }
		});
	}
}

/*
function init_leaflet(){
	//dependencies: leaflet
	var token_init = "leaflet-init";
    if($(".leaflet").not("."+token_init).length>0){
        $(".leaflet").not("."+token_init).each(function(){
        	var obj = $(this);
           	obj.addClass(token_init);
           	var id = obj.attr("id");
           	var nearest = obj.data("nearest");
           	var search = obj.data("search");
           	if(IsBlank(id)){
           	   id = generateCode(5);
           	}
    		obj.attr("id", id);

			    		//L.Map = L.Map.extend({
						//    openPopup: function(popup) {
						        //        this.closePopup();  // just comment this
						//      this._popup = popup;

						//        return this.addLayer(popup).fire('popupopen', {
						//            popup: this._popup
						//        });
						//    }
						//});

			L.Popup = L.Popup.extend({
			    getEvents: function () {
			        var events = L.DivOverlay.prototype.getEvents.call(this);
			        if ('closeOnClick' in this.options ? this.options.closeOnClick : this._map.options.closePopupOnClick) {
			            //events.preclick = this._close;
			        }
			        if (this.options.keepInView) {
			            events.moveend = this._adjustPan;
			        }
			        return events;
			    },
			});

    		var map = L.map(id,{
    			scrollWheelZoom: false,
    			dragging: !L.Browser.mobile
    		}).setView([51.505, -0.09], 13);

    		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			    maxZoom: 19,
			    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
			}).addTo(map);

    		var locations = obj.data("locations");
    		var callback = obj.data("callback");
    		var markers = L.markerClusterGroup({
				spiderfyOnMaxZoom: true,
				showCoverageOnHover: true,
				zoomToBoundsOnClick: true,
				//iconCreateFunction: function(cluster) {
				//	return L.divIcon({ html: '<b>' + cluster.getChildCount() + '</b>' });
				//}
				//spiderLegPolylineOptions: { weight: 1.5, color: '#222', opacity: 0.5 }
			});
    		var marker=[];
    		for (var i = 0; i < locations.length; i++) { 
    			var latlng = L.latLng(locations[i].lat, locations[i].lon);
						//var myIcon = L.icon({
						//    iconUrl: locations[i].avatar,
						//    iconSize: [50, 50],
						//    iconAnchor: [22, 94],
						//    popupAnchor: [-3, -76]
						//});
						//L.marker([locations[i].lat, locations[i].lon], {icon: myIcon}).addTo(map);
		
				//var popup_content = "<div class='row g-0 align-items-center text-center'><div class='col-12'><img src='"+locations[i].image+"' class='img-fluid' /></div><div class='col-12'>"+locations[i].title+"</div></div>";
				if(locations[i].hasOwnProperty("popup") && typeof window["leaflet_popup"] !== "undefined"){
                    
                    //popup_content = locations[i].popup;
                    var comptempl = _.template(leaflet_popup); 
					var popup_content = comptempl(locations[i]); 
	                var popup = L.popup(latlng, {
				    	content: popup_content, 
				    	minWidth : 120,
				    	maxWidth : 120,
				    	closeButton : false,
				    	closeOnClick : false,
				    	autoClose : false,
				    	className : "leaflet-popup-custom"
				    	//keepInView : true
				    }).openOn(map).openPopup();
				    map.addLayer(popup)
				    popup.openPopup();
				    marker[i]=popup;

				}else{

					if(locations[i].marker){
						var myIcon = L.icon({
							iconUrl: locations[i].marker.icon,
							iconSize: [locations[i].marker.width, locations[i].marker.height],
							iconAnchor: [locations[i].marker.width/2, locations[i].marker.height],
							popupAnchor: [0, 0-locations[i].marker.height]
					    });						
					}else{
						var myIcon = [];
					}

					marker[i] = L.marker([locations[i].lat, locations[i].lon], {icon: myIcon})//.addTo(map);
					if(typeof window["leaflet_popup"] !== "undefined"){
						var comptempl = _.template(leaflet_popup); 
						var popup_content = comptempl(locations[i]); 
						var popup = L.popup(latlng, {
					    	content: popup_content, 
					    	minWidth : 120,
					    	maxWidth : 120,
					    	closeButton : false,
					    	closeOnClick : true,
					    	autoClose : true,
					    	className : "leaflet-popup-custom"
					    	//keepInView : true
					    });
					    marker[i].bindPopup(popup.getContent());//.openPopup();
				        marker[i].on('mouseover', function (e) {
				            this.openPopup();
				        });
				        marker[i].on('mouseout', function (e) {
				            this.closePopup();
				        });
				    }
				}
				if(typeof window[callback] === "function"){
					marker[i].post_id = locations[i].id;
					marker[i].on('click', function (e) {
                        e.preventDefault
				        window[callback](this.post_id);
				    });					
				}
		    }

		    marker.forEach(function(marker) {
			    markers.addLayer(marker);
			    map.addLayer(markers);
			});

			//var group = new L.featureGroup(marker);
            //map.fitBounds(group.getBounds());

            function fitMapToWindow() {
			    var group = new L.featureGroup(marker);
                map.fitBounds(group.getBounds());
			}
			fitMapToWindow();

			L.Control.Button = L.Control.extend({
				  options: {
				    position: 'bottomleft'
				  },
				  initialize: function (options) {
				    this._button = {};
				    this.setButton(options);
				  },

				  onAdd: function (map) {
				    this._map = map;

				    this._container = L.DomUtil.create('div', 'leaflet-control-button leaflet-bar');

				    this._update();
				    return this._container;
				  },

				  onRemove: function (map) {
				    this._button = {};
				    this._update();
				  },

				  setButton: function (options) {
				    var button = {
				      'class': options.class,
				      'text': options.text,
				      'onClick': options.onClick,
				      'title': options.title,
				      'data' :options.data
				    };

				    this._button = button;
				    this._update();
				  },

				  _update: function () {
				    if (!this._map) {
				      return;
				    }

				    this._container.innerHTML = '';
				    this._makeButton(this._button);
				  },

				  _makeButton: function (button) {
				    var newButton = L.DomUtil.create('a', 'leaflet-buttons-control-button '+button.class, this._container);
				    newButton.href = '#';
				    newButton.innerHTML = button.text;
				    newButton.title = button.title;
				    if(button.data){
				    	for (var key in button.data) {
						    if (button.data.hasOwnProperty(key)) {
						        newButton.setAttribute('data-' + key, button.data[key]);
						    }
						}	
				    }

				    onClick = function(event) {
				      button.onClick(event, newButton);
				    };
				
				    L.DomEvent.addListener(newButton, 'click', onClick, this);
				    return newButton;// from https://gist.github.com/emtiu/6098482
				}
            });

            if(nearest){
	            var nearestButton = new L.Control.Button({
					text: '',
			        title: 'Nearest Locations',
					class : "btn-leaflet btn-nearest-locations",
					data : {
						"bs-title" : "En yakın istasyon",
						"bs-toggle" : "tooltip"
					},
					onClick : function(e){
						e.preventDefault();
						$("body").addClass("loading-process")
						$(".btn-get-location").trigger("click");
					}
			    }).addTo(map);   

            };
            if(search){
	            var searchButton = new L.Control.Button({
					text: '',
			        title: 'Search Locations',
					class : "btn-leaflet btn-search-locations",
					data : {
						"bs-title" : "İstasyon ara",
						"bs-toggle" : "tooltip"
					},
					onClick : function(e){
						e.preventDefault();
						$("#offcanvasMap").offcanvas("show");
					}
			    }).addTo(map);       	
            };
            var fitButton = new L.Control.Button({
					text: '',
			        title: 'Fit all',
					class : "btn-leaflet btn-fit-all",
					data : {
						"bs-title" : "Tüm istasyonlar",
						"bs-toggle" : "tooltip"
					},
					onClick : function(e){
						e.preventDefault();
                       fitMapToWindow();
					}
			}).addTo(map);     

            window.addEventListener('resize', fitMapToWindow);

            obj.data("map", map);

            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

            return obj;

	    });
    }
}


function countdown(){
	//dependencies : jquery-countdown
	var token_init = "countdown-init";
    if($(".countdown").not("."+token_init).length>0){
        $(".countdown").not("."+token_init).each(function(){
        	$(this).addClass(token_init);
			var start = $(this).data("event-start");
			var end = $(this).data("event-end");
			var completed = $(this).data("event-completed");
			var completed_callback = $(this).data("event-completed-callback");
			var timezone = $(this).data("event-timezone");
			var live_text = $(this).data("event-live");
			var live_callback = $(this).data("event-live-callback");
			if(IsBlank(live)){
				//live_text = "Session is live";
			}
			if(!IsBlank(timezone)){
				var client_timezone = moment.tz.guess();	
                if(!IsBlank(start)){
					var date_start = moment.tz(start, "GMT");
					    date_start = moment.tz(date_start, client_timezone);                	
                }
                if(!IsBlank(end)){
					var date_end = moment.tz(end, "GMT");
					    date_end = moment.tz(date_end, client_timezone);
                }
			}else{
				if(!IsBlank(start)){
					var date_start = moment(start);              	
                }
                if(!IsBlank(end)){
					var date_end = moment(end);
                }
			}

			if(!IsBlank(date_start)){
				var countdown = date_start.toDate();//start;
			}

			var live = "";
			var now = moment.tz(new Date(), "GMT");

			if(!IsBlank(date_start)){
			    if(moment.tz(date_start, "GMT").isBefore(now)){
					countdown = date_end.toDate();//end;
					if(IsBlank(live_text) && typeof live_text !== "undefined"){
						live = "<div>"+live_text+"</div>";
					}
				}				
			}else{
				countdown = date_end.toDate();//end;
				if(IsBlank(live_text) && typeof live_text !== "undefined"){
					live = "<div>"+live_text+"</div>";
				}
			}

			$(this).countdown(countdown)
			.on('update.countdown', function(event) {
				  var format = '%H:%M:%S';
				  if(event.offset.totalDays > 0) {
				    format = '%-d day%!d ' + format;
				  }
				  if(event.offset.weeks > 0) {
				    format = '%-w week%!w ' + format;
				  }
				  $(this).html(live+event.strftime(format));
			})
			.on('finish.countdown', function(event) {
				countdown = "";
				var live = "";
			    var now = moment.tz(new Date(), "GMT");
				 	if(moment.tz(date_start, "GMT").isBefore(now)){
					countdown = date_end.toDate();//end;
					live = "<div>"+live_text+"</div>";
				}
				if(IsBlank(countdown)){
				 	$(this).html(completed).parent().addClass('disabled');
				 	if(!IsBlank(live_callback)){
				       	if(typeof window[live_callback] === "function"){
				       	    window[live_callback]();
				       	}
				    }
				}else{
				 	$(this).countdown(countdown);
				 	if(!IsBlank(completed_callback)){
				       	if(typeof window[completed_callback] === "function"){
				       	    window[completed_callback]();
				       	}
				    }
				}
			});
	    });
    }
}*/

function updateDonutChart(el, percent, donut) {
    percent = Math.round(percent);
    if (percent > 100) {
        percent = 100;
    } else if (percent < 0) {
        percent = 0;
    }
    var deg = Math.round(360 * (percent / 100));

    if (percent > 50) {
         el.find('.pie').css('clip', 'rect(auto, auto, auto, auto)');
         el.find('.right-side').css('transform', 'rotate(180deg)');
    } else {
         el.find('.pie').css('clip', 'rect(0, 1em, 1em, 0.5em)');
         el.find('.right-side').css('transform', 'rotate(0deg)');
    }
    if (donut) {
         el.find('.right-side').css('border-width', '0.1em');
         el.find('.left-side').css('border-width', '0.1em');
         el.find('.shadow').css('border-width', '0.1em');
    } else {
         el.find('.right-side').css('border-width', '0.5em');
         el.find('.left-side').css('border-width', '0.5em');
         el.find('.shadow').css('border-width', '0.5em');
    }
     //el.find('.num').text(percent);
     el.find('.left-side').css('transform', 'rotate(' + deg + 'deg)');
}

/*
function typeahead(){
	//dependencies: bootstrap-4-autocomplete
	var token_init = "typeahead-init";
    if($(".typeahead").not("."+token_init).length>0){
        typeahead.log($('.typeahead'));
	    var search_request;
		var templates = {
			product    : '<div class="media type-{{type}}">{% if image %}<img src="{{image}}" class="img-thumbnail img-fluid" alt="{{name}}">{% endif %}<div class="media-body"><h5 class="title">{{name}}</h5>{% if price %}<div class="price">{{price}}</div>{% endif %}</div></div>',
			empty      : '<span class="empty"><i class="icon far fa-clock"></i> {{name}}</span>',
			notfound   : '<span class="dropdown-item not-found text-center"><h5 class="title">Not found!</h5><div class="description d-none">You may click "Search" button to advanced search.</div></span>'
		}
		var template_render = function(template, $item){ 
			return twig({
				data                : templates[template],
				allowInlineIncludes : true
			}).render($item);
		};
		$(".typeahead").not("."+token_init).each(function(){
			var obj = $(this);
			var method = obj.data("method");
			var method_numeric = obj.data("method-numeric");
			if(IsBlank(method)){
				return false;
			}
			obj.typeahead({
				source: function (query, process, bum) {
					var $typeahead = this;
					var history = obj.data("history");
						history = IsBlank(history)?false:true;
					if(IsBlank(query)){
						if( site_config.search_history.length>0 && history){
							this.$menu.removeClass("loading").removeClass("not-found");
							//return this.process(site_config.search_history);	
							return $typeahead.render(site_config.search_history).show();	    			
						}
					}else{
						var vars = [];
						//if(!IsBlank(method_numeric) && IsNumeric(query)){
							//  method = method_numeric;
						//}
						if(!this.shown){
							this.show();
						}
						this.$menu.empty().addClass("loading").removeClass("not-found");
						if(typeof search_request !== "undefined"){
							search_request.abort();
						}
						search_request = $.post(host, { ajax: "query", method:method, keyword:query, vars:{count:$typeahead.options.items} }, function (data) {
							$typeahead.$menu.removeClass("loading").removeClass("not-found");
							data = $.parseJSON(data);
							debugJS(data);
							if(data.length>0){
								return $typeahead.render(data).show();
								//return process(data);
							}else{
								$typeahead.$menu.empty().addClass("not-found").html($typeahead.highlighter("",""));
								return false;
							}
						});		    		
					}
				},
				appendTo : obj.data("container"),
				autoSelect : false,
				fitToElement : true,
				minLength : 3,
				theme : "bootstrap4",
				showCategoryHeader : true,
				selectOnBlur:false,
				changeInputOnMove:false,
				items : 5,
				showHintOnFocus:false,
				highlighter: function($text, $item){
					debugJS($text, $item);
					if(IsBlank(this.query)){
						var template = "empty";
					}else{
						if($item == ""){
							var template = "notfound";
						}else{
							 template = "product";
						}
					}
					debugJS(template)
					return template_render(template, $item);
				},
				displayText: function($item){
					return $item.name
				},
				afterSelect: function($item){
					if($item.hasOwnProperty("url")){
						$("body").addClass("loading");
						window.location.href = $item.url;
					}else{
						//this.$element.closest("form").submit();
						$("body").addClass("loading");
						var url = this.$element.closest("form").attr("action");
						window.location.href = url+$item.name;
					}
				}
			})
			.on("focus", function(e){
				debugJS("focus")
				var typeahead = $(this).data("typeahead");
				var history = $(this).data("history");
					history = IsBlank(history)?false:true;
				//debugJS(typeahead);

				if(!typeahead.shown && (!IsBlank(typeahead.value) && (typeahead.value.length >= typeahead.options.minLength)) ){
					typeahead.show();
				}
						
				if(IsBlank($(this).val()) && site_config.search_history.length>0 && history){
					typeahead.query="";
					typeahead.source("");
					var header = typeahead.$menu.find(".dropdown-header");
					if(header.length>0){
						header.append("<a href='#' class='btn btn-search-terms-remove btn-link btn-sm'>Remove search history</a>");
						$(".btn-search-terms-remove").on("click", function(e){
							e.preventDefault();
							typeahead.$menu.removeClass("loading").addClass("loading-process");
							$.post(host, { ajax : "query", method : "search_terms_remove" })
							.fail(function() {
								_alert('', "An error occured, please try again later!");
							})
							.done(function( response ) {
								site_config.search_history = [];
								typeahead.$menu.removeClass("loading-process").empty().hide();
								_alert('', "Search history removed!");
							});
						});
					}
				}
			})
			.on("keydown, keyup", function(e){
				if(!IsBlank($(this).val())){
					var typeahead = $(this).data("typeahead");
					if($(this).val().length >= typeahead.options.minLength && !typeahead.$menu.hasClass("loading")){
						typeahead.$menu.empty().addClass("loading").removeClass("not-found");
					}else{
						//search_request.abort();
						//typeahead.$menu.empty().removeClass("loading").removeClass("not-found").hide();
					}
				}else{
					$(this).trigger("focus");
				}
			});

			//popular terms
			var popular_terms = obj.data("popular-terms");
			if(!IsBlank(popular_terms)){
				if($(popular_terms).length>0){
					$(popular_terms).find(".label").on("click", function(e){
						e.preventDefault();
						var term = $(this).text();
						obj.val(term).typeahead("lookup");
					});
				}
			}

		});
	}	
}



function slab_text(){
	var token_init = "slab-text-init";
    if($(".slab-text-container").not("."+token_init).length>0){
        $(".slab-text-container").not("."+token_init).each(function(){
        	$(this).addClass(token_init);
        	var maxFontSize = $(this).data("max-font-size");
        	if(typeof maxFontSize === "undefined"){
        		const computedStyle = window.getComputedStyle(this);
       			maxFontSize = computedStyle.getPropertyValue('font-size');
        	}
        	var options = {
	            viewportBreakpoint:380
	        };
	        if(!IsBlank(maxFontSize)){
	        	options["maxFontSize"] = maxFontSize;
	        }
	        debugJS(options);
	        $(this).slabText(options);
	        $(window).trigger("resize");
        });
    }
}


function progressCircle(){
	//dependencies: progressbar.js
	var token_init = "progress-circle-init";
    if($(".progress-circle").not("."+token_init).length>0){
        $(".progress-circle").not("."+token_init).each(function(){
        	$(this).addClass(token_init);

        	var progress = $(this).data("progress");

        	var progress_text = $(this).data("progress-text");
        	var progress_term = $(this).data("progress-term");
        	var text = progress_text+(progress_term?"<span>"+progress_term+"</span>":"");
        	var duration = $(this).data("duration");

        	var progress_start = $(this).data("progress-start");
        	var progress_end = $(this).data("progress-end");

        	var options = {
				strokeWidth: 6,
				easing: 'easeInOut',
				duration: duration||1400,
				color: '#FFEA82',
				trailColor: '#eee',
				trailWidth: 1,
				svgStyle: null,
				text : {
				   value : text
				},
			};
			if(progress_start && progress_end){
				options["from"] = { color: progress_start };
				options["to"] = { color: progress_end };
				options["step"] = function(state, circle, attachment) {
				   circle.path.setAttribute('stroke', state.color);
				};
			}
			var bar = new ProgressBar.Circle($(this)[0], options);
	            bar.animate(progress/100);
	    });
    }
}
*/
function table_responsive(){

	const responsiveTables = document.querySelectorAll('.table-responsive');

        // Her bir tabloyu dön
        responsiveTables.forEach(table => {
		  var bodyTrCollection = table.querySelectorAll('tbody tr');
		  var th = table.querySelectorAll('th');
		  var thCollection = Array.from(th);

		  for (var i = 0; i < bodyTrCollection.length; i++) {
		    var td = bodyTrCollection[i].querySelectorAll('td');
		    var tdCollection = Array.from(td);
		    for (var j = 0; j < tdCollection.length; j++) {
		      if (j === thCollection.length) {
		        continue;
		      }
		      var headerLabel = thCollection[j].innerHTML;
		      tdCollection[j].setAttribute('data-label', headerLabel);
		    }
		  }
		});
}

/*
function printThis(){
	//dependencies: print-me
	var token_init = "print-init";
	if($("[data-print]").not("."+token_init).length > 0){
        $("[data-print]").not("."+token_init).each(function(){
        	var $obj = $(this);
        	$obj.addClass(token_init);
			$obj.on("click", function(e){
	            e.preventDefault();
	            var $args = {};
	            var target = $(this).data("print");
	            var title = $(this).data("print-title");
	            var header = title
	            if(title){
	            	$args["pageTitle"] = title;
	            }
	            if(header){
	            	$args["header"] = "<h3>"+header+"</h3>";
	            }
	            $(target).printThis($args);
			});
	    });
    }
}


function qrCode_item($obj){
	//dependencies: easyqrcodejs
	var token_init = "qrcode-init";
	$obj.addClass(token_init);
        	var text = $obj.data("text");
        	var width = $obj.data("width")||50;
        	var colorDark = $obj.data("color-dark")||"#000000";
        	var colorLight = $obj.data("color-light")||"#ffffff";

            if(!IsBlank(text)){
	            new QRCode($obj[0], {
					text: text,
					width: width,
					height: width,
					colorDark: colorDark,
					colorLight: colorLight,
					subTitleTop: 0,
					titleHeight: 0,
					
					//title: 'Ekosinerji',
					//titleFont: "bold 16px Arial",
					//titleColor: "#000000",
					//titleBgColor: "#fff",
					//titleHeight: 35,
					//titleTop: 0,
					
					//subTitle: '<?=time()?>',
					//subTitleFont: "14px Arial",
					//subTitleColor: "#004284",
					//subTitleTop: 0,
					
					//logo:"logo.png", // LOGO
					//logoWidth:80, // 
					//logoHeight:80,
					//logoBgColor:'#ffffff',
					//logoBgTransparent:false,
					correctLevel: QRCode.CorrectLevel.M // L, M, Q, H
				});
			} 	
}
function qrCode(){
	//dependencies: easyqrcodejs
	var token_init = "qrcode-init";
    if($(".qrcode").not("."+token_init).length>0){
        $(".qrcode").not("."+token_init).each(function(){
        	if(!$(this).hasClass("viewport")){
               qrCode_item($(this)); 
        	}else{
        	   $(this).data("viewport-func", "qrCode_item"); 
        	}
        });
    }
}

function sortable(){
	//{dependencies: [ 'sortablejs' ]}
	function addNestedClasses($element, level) {
	    $element.children("li").each(function() {
	        var $this = $(this);
	        var $ul = $this.children("ul");

	        $this.addClass("nested-" + level);

	        if ($ul.length > 0) {
	            $ul.addClass("nested-sortable");
	            addNestedClasses($ul, level + 1);
	        }
	    });
	}
	var token_init = "sortable-init";
    if($(".sortable").not("."+token_init).length>0){
        $(".sortable").not("."+token_init).each(function(){
        	var $obj = $(this);
        	var nested = $obj.data("nested");
        	var onEnd = $obj.data("on-end");
        	if(nested){
        		$obj.addClass("nested-sortable");
        		addNestedClasses($(this), 1);
        		$obj.wrap("<div class='sortable-wrapper'/>");
        		var nestedSortables = $(this).parent().find(".nested-sortable");
        		for (var i = 0; i < nestedSortables.length; i++) {
					new Sortable(nestedSortables[i], {
						handle: '.handle',
						group: 'nested',
						animation: 150,
						fallbackOnBody: true,
						swapThreshold: 0.65,
						onEnd: function (evt) {
							var itemEl = evt.item;  // dragged HTMLElement
							evt.to;    // target list
							evt.from;  // previous list
							evt.oldIndex;  // element's old index within old parent
							evt.newIndex;  // element's new index within new parent
							evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
							evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
							evt.clone // the clone element
							evt.pullMode;  // when item is in another sortable: `"clone"` if cloning, `true` if moving
							//debugJS($(itemEl).closest("li").attr("data-id"));
							if(onEnd && typeof window[onEnd] !== "undefined"){
								window[onEnd]($(itemEl));
							}
						},
					});
				}
        	}
        });
    }
}


function text_rotator(){
	//dependencies : jquery.simple-text-rotator
	$(".text-rotator").each(function(){
		var obj = $(this);
			obj.textrotator({
			  animation: obj.data("text-rotator-animation"),
			  separator: "|",
			  speed: obj.data("text-rotator-speed") || 2000
			});
			obj.removeClass("invisible");
	});
}
function text_effect(){
	//dependencies : textillate
	$(".text-effect").each(function(){
		var obj = $(this);
			obj.textillate();
			obj
			.on('inAnimationBegin.tlt', function () {
			  	obj.removeClass("invisible");
			});
	});
}*/