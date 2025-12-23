	root.init({
		navigation : "",
		footer : "",
		affix : false
	});
	var host = root.get_host();
	var lang = root.lang;
	var hash = root.hash;
	var is_home = root.is_home;


if(isLoadedJS("lenis")){
	lenis = new Lenis()
	/*lenis.on('scroll', (e) => {
	  debugJS(e)
	});*/
	function raf(time) {
	  lenis.raf(time)
	  requestAnimationFrame(raf)
	}
	requestAnimationFrame(raf);
}

window.addEventListener('load', fitToContainer);
window.addEventListener('resize', fitToContainer);






// Header Affix Init (ScrollPosStyler'ın özel kullanımı)
if (window["ScrollPosStyler"] && document.getElementById("header")) {
    var header = document.getElementById("header");
    if (!header.classList.contains("affix")) {
        ScrollPosStyler.init({
            spsClass: "affixed",
            classAbove: "affix-top",
            classBelow: "affix",
            offsetTag: "data-affix-offset",
            scrollOffsetY: 50
        });
    }
}
// Ana içerik scroll (Eski yöntem - ScrollPosStyler ile çakışabilir, ancak mevcut mantık korundu.)
var main = document.querySelector("#main");
var header = document.querySelector("#header");

if (main && header && root && root.classes) {
    var scrollTop = main.scrollTop || document.documentElement.scrollTop;
    var headerHeight = root.get_css_var("header-height");
    if (scrollTop > headerHeight) {
        if (header.classList.contains("fixed-top")) {
            root.classes.addClass(header, "affix");
            root.classes.removeClass(header, "affix-top");
        }
    }
}


function init_functions(){
	root.ui.scroll_dedect();
	//function_secure("", "root.ui.scroll_dedect", []);

	    /*
	    $(".player.init-me").each(function(){
	    	plyr_init($(this));
	    })

	    $('.wp-block-video video').each(function() {
	        // Plyr ile videoyu değiştir
	        $(this).addClass("player");
	        plyr_init($(this));
	    });*/

	    /*// Ses bloklarını hedefle
	    $('.wp-block-audio audio').each(function() {
	        // Plyr ile sesi değiştir
	        new Plyr(this);
	    });

	    // Embed videolarını hedefle
	    $(".wp-block-embed").not(".is-type-rich").find('.wp-block-embed__wrapper').each(function() {
	        // Plyr ile gömülü videoları değiştir
	        $(this).addClass("player");
	        plyr_init($(this));
	    });*/
        
        if($('.nav-equal').length > 0){
	        const runSameSizeAndVisibility = () => {
			    // 1. Önce Genişlikleri Eşitle ve Ölç (Reflow zorlamalı)
			    // true: width eşitle, undefined: max değer yok, true: keepMin true
			    $('.nav-equal').sameSize(true, undefined, true); 
			    
			    // 2. Ardından Görünürlük Kontrolünü Yap (Eşitlenmiş sütunları d-none yapmayacak şekilde)
			    navbar_visibility();
			};
			runSameSizeAndVisibility();
			let sameSizeRafId = null;
			$(window).off('resize.sameSizeAndVisibility').on('resize.sameSizeAndVisibility', () => { 
			  if (sameSizeRafId) cancelAnimationFrame(sameSizeRafId);
			  sameSizeRafId = requestAnimationFrame(() => {
			    sameSizeRafId = null;
			    runSameSizeAndVisibility();
			  });
			});
		}

        document.querySelectorAll("a[href='#']").forEach(el => {
		  el.addEventListener("click", e => e.preventDefault());
		});

        if ($.fn.disableAutoFill) {
			$('form').disableAutoFill();
		}

		if (typeof window["init_plugins"] === 'function') {
		 	init_plugins();
		}

		btn_loading_page();

		//btn_loading_page_hide();
		//btn_loading_self();
		//btn_loading_parent();

		btn_ajax_method();
		//function_secure("", "btn_ajax_method", []);

	    ajax_paginate();
		//function_secure("", "ajax_paginate", []);

        //selectChain();
		//$("input[type='number']").inputSpinner();


        if(site_config.enable_favorites){
        	btn_favorite();
        }
        /*
        
        if(site_config.enable_notifications && site_config.logged){
		    ajax_hooks.get_notification_alerts.init();
		}*/

		/*
        ajax_hooks["get_nearest_locations"].init({
			post_type : "satis-noktalari"
		});*/
}

$( document ).ready(function() {

	    $.ajaxSetup({headers: {'X-CSRF-TOKEN': ajax_request_vars.ajax_nonce}});

		if(!$("body").hasClass("logged")){/* && (site_config.enable_favorites || site_config.enable_search_history)){*/
			ajax_hooks["site_config"].init(site_config.meta);
		}
		init_functions();

		var page_notification = $(".page-notification-top");
		if(page_notification.length > 0){
			page_notification.on('closed.bs.alert', function () {
				alert("closed")
	                              // do something, for instance, explicitly move focus to the most appropriate element,
	                              // so it doesn't get lost/reset to the start of the page
	                              // document.getElementById('...').focus()
	        });			
		}

	    if($(".form-search").length > 0){
		    $(".form-search").each(function(){
		    	var form = $(this);
		    	form.find(".btn-submit").on("click", function(e){
		    		e.preventDefault();
		    		var search_key = "s";
			    	if(form.hasClass("form-search-custom")){
			    		search_key = "q";
			    	}
		    		var url = form.attr("action");
		    		var value = form.find("input[name='"+search_key+"']").val();
		    		if(value.length >= form.find("input[name='"+search_key+"']").attr("minlength")){
		    			$("body").addClass("loading");
		    			window.location.href = url+value;
		    		}
		    		//form.attr("action", url+value).submit();
		    	});
		    });    	
	    }

        /*

		$(".mega-menu .nav-mega-menu > li > a[data-bs-toggle='tab']").on("click", function(e){
			var $image = $(this).data("image-url");
			var $container = $(this).closest(".mega-menu").find(".mega-menu-image");
			var $bgColor = $container.css("background-color");
			if($bgColor != "rgba(0, 0, 0, 0)" ){
				$container.closest(".mega-menu").css("background-color", $bgColor);
				$container.css("background-color", "rgba(0, 0, 0, 0)");
			}
			$container.stop().animate({opacity:0}, 150, function(){
				if(!IsBlank($image)){
				   $container.css("background-image", "url("+$image+")");
				}
				$container.css("left","-50px"); 
				$container.stop().animate({opacity:1,left:0}, 500);
			})
		});
		$(".mega-menu .nav-mega-menu > li:first-child > a[data-bs-toggle='tab']").trigger("click");

        $(".mega-menu .nav-mega-menu > li > a[data-image-id]").hover(function(e){
        	var id = $(this).attr("data-image-id");
        	var container = $(this).closest(".mega-menu-container").find(".image");
        	    container.find("[data-image-id]").removeClass("show");
        	    container.find("[data-image-id='"+id+"']").addClass("show");
        });*/
		
        /*if($('input,textarea').length > 0){
	        $('input,textarea').focus(function(){
	            if(IsBlank($(this).data("placeholder"))){
	           	    var placeholder = $(this).attr('placeholder');
	           	    $(this).data('placeholder',placeholder);
	            }
	            $(this).attr('placeholder','');
	        })
			.blur(function(){
			   $(this).attr('placeholder',$(this).data('placeholder'));
			});        	
        }


        if($(".select-url").length > 0){
           $(".select-url").on("change", function(){
           	   $("body").addClass("loading-process");
               window.location.href = $(this).val();
           });
	    }

	    if($(".select-hash").length > 0){
           $(".select-hash").on("change", function(){
               root.ui.scroll_to("#"+$(this).val().split("#")[1], true);
           });
	    }

        //form change or not
        if($('.form').length > 0){
			$('.form').on('keyup change paste', 'input, select, textarea', function(e){
				var obj = $(e.target);
				if(obj.hasClass("multiselect-search") || obj.hasClass("form-control-autocomplete")){
					return false;
				}
				var form = $(e.target).closest(".form");
			        form.addClass("form-changed");
			    if(form.data("auto-submit") && !form.hasClass("ajax-processing")){
			    	if(obj.attr("min") && (obj.prop("tagName") == "INPUT" || obj.prop("tagName") == "TEXTAREA" )){
			    	   if(obj.val().length < obj.attr("min")){
			    	   	  return false;
			    	   }
			    	}
			    	if(form.find("input[name='page']").length > 0){
			           form.find("input[name='page']").val(1);
			    	}
			        form.submit();
			    }
			});        	
        }*/


        


        /********************************************
		/*
		/*   D R O P D O W N
		/*
		/*******************************************/

		$(document)
		.on('shown.bs.dropdown', function (e) {

			var obj = $(e.target);

			if(obj.parent().hasClass("dropdown-notifications")){
				var container = obj.parent().find(".dropdown-container");
				obj.parent().parent().addClass("active");
				$(document.body).addClass('notifications-open');

				if(obj.parent().data("type") == "favorites"){
					var $favorites = site_config.favorites;
                    if(!$.isArray($favorites)){
                       var $favorites = $.parseJSON($favorites);
                    }
                    if($favorites.length > 0){
                    	$favorites = $favorites.sort();
						var ids = container.find(".notification-item")
								  .map(function() { return $(this).data("id"); })
								  .get().sort(); 
						if(!isEqual($favorites, ids)){
		                    container.addClass("loading-process");
							favorites.get(obj.parent().find(".dropdown-container"));
						}
					}					
				}

				if(obj.parent().data("type") == "messages"){
	                container.addClass("loading-process");
					messages.get(obj.parent().find(".dropdown-container"));				
				}

				if(obj.parent().data("type") == "cart"){
	                container.addClass("loading-process");
				    cart.get(obj.parent().find(".dropdown-container"));				
				}

				$(".collapse-search.show").collapse("hide");
			}
		})
		.on('hidden.bs.dropdown', function (e) {
			var obj = $(e.target);
			debugJS("hidden")
			debugJS(e)
            if(obj.parent().hasClass("dropdown-notifications")){
            	var type = obj.parent().data("type");
            	//if(IsBlank(type)){
	            	obj.parent().parent().removeClass("active");
					$(document.body).removeClass('notifications-open');
				//}
			}
		})
        
        //Update dropdown button text with selected option's text
		$(".dropdown-menu.updateable a").click(function(e){
			var $url = $(this).attr("href");
			if(IsUrl($url)){
               $("body").addClass("loading");
			}
		    var selText = $(this).html();
		    $(this).parents('.dropdown').find('.dropdown-toggle').html(selText+' <span class="caret"></span>');
		});

		$('.dropdown-hover').hover(function () {
            $(this).addClass('show');
            $(this).find('.dropdown-menu').addClass('show');
        }, function () {
            $(this).removeClass('show');
            $(this).find('.dropdown-menu').removeClass('show');
        });

        $("[data-bs-dismiss='dropdown']").on("click", function(e){
        	$(this).closest(".dropdown").find("[data-bs-toggle='dropdown']").dropdown("hide");
        });





        /********************************************
		/*
		/*   T A B S
		/*
		/*******************************************/

		// tab events
		$(document)
		.on('show.bs.tab', function (e) {
			var input = $(e.target).find("input");
			if(!input.is(":checked")){
			    input.trigger("click").prop("checked", true);
			}
			$(e.target).closest(".nav").find(".active").removeClass("active");
			$(e.target).parent().addClass("active");
		})
		.on('shown.bs.tab', function (e) {
			var obj = $($(e.target).attr("href"));
			obj
			.find("[data-required]")
		    .removeAttr("data-required")
		    .attr("required", true);    
		})
		.on('hide.bs.tab', function (e) {
		})
		.on('hidden.bs.tab', function (e) {
			$($(e.target).attr("href"))
			.find("[required]")
		    .removeAttr("required")
		    .attr("data-required", true)
		    .removeClass("is-invalid")
		    .val("");
		});
		$("[role=tab]").each(function(){
		    var input = $(this).find("input");
            if(input.is(":checked")){
           	   $(this).trigger("click");
           	   $($(this).attr("href")).tab("show");
            }
		});





        /********************************************
		/*
		/*    C O L L A P S E
		/*
		/*******************************************/

        // collapse events
		$(document)
		.on("show.bs.collapse", ".collapse", function (e) {
			var panel = $(this).closest(".accordion-item");
				panel.addClass("active");
				var panelCollapse = $(e.target);
				var parent = $(this).data("parent");
				var $open = $(parent).find('.collapse.show');
				var input = $(e.target).prev().find("input");
				if(!input.is(":checked")){
			       input.prop("checked",true);
				}

				$(parent).addClass("active");

				if(panelCollapse.hasClass("collapse-search")){
					$("body").addClass("search-open");
					if(isLoadedJS("smartmenus")){
						$("#navigation .navbar-nav").smartmenus('menuHideAll');
					}
				}
		})
		.on("shown.bs.collapse", ".collapse", function (e) {
			var obj = $(e.target);
			if(obj.data("scroll")){
			   root.ui.scroll_to(obj.prev(), true, false, function(){
			   	  $("body").addClass("header-hide");
			   });
			}
		})
		.on("hide.bs.collapse", ".collapse", function (e) {
			var input = $(e.target).prev().find("input");
			if(input.is(":checked")){
		       input.prop("checked",false);
			}
			if($(e.target).hasClass("collapse-search")){
			   $("body").removeClass("search-open");
			}
		})
		.on("hidden.bs.collapse", ".collapse", function (e) {
			    var obj = $(e.target);
			    if(obj.hasAttr("data-scroll-hidden").length > 0){
			        var scroll_type = obj.data("scroll-hidden");
			        switch(scroll_type){
			      	    case "parent":
			      	    	 var parent = $(obj.attr("data-bs-parent"));
						     if(parent.length > 0){
						        if(parent.find("[aria-expanded='true']").length == 0){
			                        root.ui.scroll_to(parent);
					            }
						    }
			      		break;
			      		case "top" :
			      			var parent = $(obj.attr("data-bs-parent"));
			      			if(parent.length > 0){
			      				debugJS(parent)
							   if(parent.find(".collapse.collapsing").length == 0 && parent.find(".collapse.show").length == 0 && parent.find("[aria-expanded='true']").length == 0){
					      		    $("html, body").stop().animate({
			                      	       scrollTop: 0
			                   		}, 600);
					      		}
				      		}
			      		break;
			        }
			    }
			    
				var panel = $(this).closest(".accordion-item");
				panel.removeClass("active");

		});






        /********************************************
		/*
		/*   M O D A L
		/*
		/*******************************************/

		//modal events
		$(document)
		.on('show.bs.modal', '.modal', function (e) {
            $(".offcanvas.show").offcanvas("hide");
		})
		.on('shown.bs.modal', '.modal', function (e) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
            }, 0);
            if ($(".modal-backdrop").length > 1) {
                //$(".modal-backdrop").not(':first').remove();
            }
            $(".modal").scrollTop(0);
            $("body").removeClass("loading").removeClass("loading-process");
            if($(e.target).find(".map-google").length > 0){
            	//root.map.init();
            	//google_map.init();
            	var map = $(e.target).find(".map-google");
            	var btn = $(e.relatedTarget);
            	var title = btn.data("title");
            	$(e.target).find(".modal-title").html(title);
            	if(!IsBlank(btn.data("lat")) && !IsBlank(btn.data("lng"))){
            		debugJS(map.data("map"))
            	    map.data("map").setCenter(new google.maps.LatLng(btn.data("lat"), btn.data("lng")));
            	}
            }
            if(isLoadedJS("lenis")){
				$("body").attr("data-lenis-prevent", "");
    		}
        })
        //multiple modals scrollbar fix
        .on('hidden.bs.modal', '.modal', function (e) {
        	var modal = $(e.target);
		    $('.modal:visible').length && $(document.body).addClass('modal-open');
		    if(modal.hasClass("remove-on-hidden")){
		    	modal.remove();
		    }
		    if(isLoadedJS("lenis")){
				$("body").removeAttr("data-lenis-prevent");
    		}
		});

		$(document).click(function(e) {
			if($(".collapse-search.show").length>0){
				debugJS($(e.target))
				if ($(e.target).is('body')) {
			    	$(".collapse-search.show").collapse("hide");
			    }				
			}
		});


        

        /********************************************
		/*
		/*   O F F C A N V A S
		/*
		/*******************************************/

		var scrollPosition = 0;

		$(document)
		.on('show.bs.offcanvas', '.offcanvas', function (e) {
		    var $obj = $(e.target);

		    $("body").addClass("offcanvas-open");

		    if($obj.hasClass("offcanvas-search")){
		   	   $("body").addClass("search-open");
		    }
		    if($obj.hasClass("offcanvas-menu")){
		   	   $("body").addClass("menu-open");
		    }

		    if($obj.hasClass("offcanvas-show-header")){
		   	   $("body").addClass("menu-show-header");
		    }

		    if($obj.hasClass("offcanvas-fullscreen")){
		    	$("body").addClass("offcanvas-fullscreen-open");
		    	if($(".plyr--playing").length > 0){
		    		var player = $(".plyr--playing")[0].plyr;
		    		if(player.playing){
		    			$(".plyr--playing").addClass("plyr--paused-manual");
		    			player.pause();
		    		}
		    	}
		    	if($("header.fixed-bottom-start").not(".affix").length > 0){
		    		$("header.fixed-bottom-start").addClass("affix");
		    	}

		    	scrollPosition = window.scrollY;
                document.body.style.top = `-${scrollPosition}px`;
		    	$("body").addClass("position-fixed overflow-hidden w-100");

		    }

		    var container = $obj.find(".offcanvas-body");

		    if($obj.hasClass("offcanvas-messages")){
		    	container.addClass("loading-process");
				messages.get(container);
		    }

		    if($obj.hasClass("offcanvas-cart")){
	            container.addClass("loading-process");
			    cart.get(container, "offcanvas");			
			}
		})
	    .on('shown.bs.offcanvas', '.offcanvas', function (e) {
		  	var $obj = $(e.target);
		    var target = "#"+$obj.attr("id");
		    if($("[href='"+target+"']").length> 0){
			 	history.pushState(target, document.title, window.location.pathname + target);
	            root.hash = target;		    	
		    }
		    if(isLoadedJS("lenis")){
				$("body").attr("data-lenis-prevent", "");
    		}
		})
		.on('hidden.bs.offcanvas', '.offcanvas', function (e) {
		  	var $obj = $(e.target);
		    var target = "#"+$obj.attr("id");
		    //$(target).css("position", "static");
		    if($("[href='"+target+"']").length> 0){
		  	   history.pushState("", document.title, window.location.pathname + window.location.search);
		    }
		  	if($obj.hasClass("offcanvas-search")){
		   	   $("body").removeClass("search-open");
		    }
		    if($obj.hasClass("offcanvas-menu")){
		   	   $("body").removeClass("menu-open");
		    }
		    $("body").removeClass("offcanvas-open");
		    $("body").removeClass("offcanvas-fullscreen-open");
		    $("body").removeClass("menu-show-header");

		    if($(".plyr--paused-manual").length > 0){
		        var player = $(".plyr--paused-manual")[0].plyr;
		    	if(player.paused){
		    		$(".plyr--manual-paused").removeClass("plyr--paused-manual");
		    		player.play();
		   		}
		    }
            
            if($obj.hasClass("offcanvas-fullscreen")){
			   $("body").removeClass("position-fixed overflow-hidden w-100");
			   document.body.style.top = '';
			   var scroll = parseFloat(document.body.style.top);
               window.scrollTo(0, -scroll);
		    }
		    if(isLoadedJS("lenis")){
				$("body").removeAttr("data-lenis-prevent");
    		}
		});
		  
		window.onhashchange = event => {
		  	var $el = $(".offcanvas.show");
		  	if($el.length > 0){
		  	   bootstrap.Offcanvas.getInstance($el).hide()
		  	}
		};


        /********************************************
		/*
		/*   A J A X   E V E N T S
		/*
		/*******************************************/

		/*var querystring = url2json(window.location.href);
		if(querystring.hasOwnProperty("tour-plan-offer-id")){
		   //ajaxData.data["tour-plan-offer-id"] = querystring["tour-plan-offer-id"];
		}*/
		
		var ajaxData = {
			data : {}
		};
		if(Object.keys(ajaxData.data).length){
			debugJS(ajaxData);
			$.ajaxSetup(ajaxData);
		}
	    $(document)
	    .ajaxStart(function(e) {
           debugJS("ajaxStart()")
           debugJS(e);
	    })
	    .ajaxComplete(function(e) {
	    	if(isLoadedJS("vanilla-lazyload")){
		    	lazyLoadInstance.update();
		    }
	    	$.fn.matchHeight._update();
	    	if(isLoadedJS("swiper")){
		    	init_swiper();
		    }
	    	btn_ajax_method();
	    	btn_loading_page();
	    	//init_functions();
	    })
	    .ajaxStop(function(e){
		});
	
		var hash = window.location.hash;
        if (!IsBlank(hash)) {
            root.hash = "";
            //history.pushState("", document.title, window.location.pathname);
            root.ui.scroll_to(hash, true);
        }

        var promotion_top = $('#promotion-top');
        if(promotion_top.length > 0){
            promotion_top[0].addEventListener('closed.bs.alert', function(){
                $("body").removeClass("has-promotion-top");
            });         
        }

        //woocommerce events
        /*jQuery(document.body).on('removed_from_cart', function(e){
            //debugJS(e)
            //debugJS('init_checkout triggered');
            cart.get($(".dropdown-notifications[data-type='cart']").find(".dropdown-container"));
        });

        $(document).on(
           "init_checkout payment_method_selected update_checkout updated_checkout checkout_error applied_coupon_in_checkout removed_coupon_in_checkout adding_to_cart added_to_cart removed_from_cart wc_cart_button_updated cart_page_refreshed cart_totals_refreshed wc_fragments_loaded init_add_payment_method wc_cart_emptied updated_wc_div updated_cart_totals country_to_state_changed updated_shipping_method applied_coupon removed_coupon",
            function (e) {
                //debugJS(e.type);
                switch(e.type){
              	    case "updated_cart_totals" :
              	  		$("input[type='number']").inputSpinner();
              	    break;
                }
            }
        );*/
});