/*window.onload = function () {
    document.body.classList.remove("loading", "loading-process");
};

window.addEventListener('pagehide', (event) => {
});
window.addEventListener('pageshow', (event) => {
  	document.body.classList.remove("loading", "loading-process");
  	// Tarayıcı geri veya ileri butonlarıyla sayfa önbellekleme durumunu kontrol et
    if (event.persisted) {
        // Eğer sayfa önbellekleme durumu varsa, sayfayı yenile
        window.location.reload();
    }
});
window.addEventListener('unload', function() {
    // Sayfa boşaltıldığında, tarayıcıya sayfanın önbelleğe alınmasına izin ver
    history.scrollRestoration = 'manual';
});
*/

window.addEventListener('beforeunload', function(event) {
    // Sayfa boşaltıldığında, tarayıcıya sayfanın önbelleğe alınmasına izin ver
    history.scrollRestoration = 'manual';
    event.returnValue = '';
});
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.remove("loading", "loading-process");
});
window.addEventListener('pagehide', (event) => {
});
window.addEventListener('pageshow', (event) => {
    document.body.classList.remove("loading", "loading-process");
    if (event.persisted) {
        window.location.reload();
    }
});




if(document.getElementsByClassName('user-localization').length){
	var obj = document.querySelector(".user-localization");
	var userCountry = site_config.user_country;//getCookie("user_country");
	var userCountryCode = site_config.user_country_code;
	var userLanguage = site_config.user_language;
    obj.innerHTML = userCountry.toUpperCase()+" - "+userLanguage.toUpperCase();
    obj.closest('a').setAttribute("data-user_country_code", userCountryCode);
    obj.closest('a').setAttribute("data-user_language", userLanguage)
}

var size = root.browser.size();
root.get_css_vars();

if(isLoadedJS("vanilla-lazyload")){
	var lazyLoadInstance = new LazyLoad({
	    elements_selector: ".lazy",
	    //use_native : true,
	    //unobserve_entered: true,
	    callback_loaded : function(e){
			var obj = $(e);
		    if(obj.hasClass("ratio")){
		       obj.parent().removeClass("loading").removeClass("loading-hide");
		    }
		    if(obj[0].nodeName == 'IMG'){
		        obj.closest(".loading").removeClass("loading")
		        obj.closest(".loading-hide").removeClass("loading-hide");
		        obj.closest(".loading-process").removeClass("loading-process");
		        if(obj.parent().hasClass("img-placeholder")){
		       	   //obj.unwrap();
		        }
		    }
			if($("[data-masonry]").length>0){
				$("[data-masonry]").data('masonry').layout();
			}
			if(obj.closest("[data-isotope]").length>0){
				obj.closest("[data-isotope]").data('isotope').layout();
				obj.closest("[data-isotope]").data('isotope').reloadItems();
			}
			if(obj.closest(".jarallax").length>0){
			   //obj.closest(".jarallax").newJarallax();
			}
			$.fn.matchHeight._update();
			if(isLoadedJS("background-check")){
				BackgroundCheck.refresh();
			}

	    },
	    callback_error : function(e){
	    	var obj = $(e);
	    	debugJS(obj.attr("data-placeholder"))
	    	if(obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")){
	    	    obj.attr("data-src", template_uri+"/static/img/placeholder/img-"+obj.attr("data-placeholder")+".jpg");
	    	    obj.closest(".loading").removeClass("loading")
		        obj.closest(".loading-hide").removeClass("loading-hide");
		        obj.closest(".loading-process").removeClass("loading-process");
		        if(obj.parent().hasClass("img-placeholder")){
		       	   obj.unwrap();
		        }
		        LazyLoad.load(obj[0]);
		    }
	    },
	    callback_enter : function(e){
	    	var obj = $(e);
	    	if(obj[0].nodeName == 'IMG' && obj.attr("data-placeholder")){
	           if(IsBlank(obj.attr("data-src")) && IsBlank(obj.attr("src"))){
		    	   obj.attr("data-src", template_uri+"/static/img/placeholder/img-"+obj.attr("data-placeholder")+".jpg");
		    	   obj.closest(".loading").removeClass("loading")
		           obj.closest(".loading-hide").removeClass("loading-hide");
		           obj.closest(".loading-process").removeClass("loading-process");
			       if(obj.parent().hasClass("img-placeholder")){
			       	   //obj.unwrap();
			       } 
			       LazyLoad.load(obj[0]);          	
	           }
		    }
		    var lazyFunctionName = e.getAttribute("data-lazy-function");
			var lazyFunction = window.lazyFunctions[lazyFunctionName];
			if (!lazyFunction) return;
			lazyFunction(e);
			if(typeof window["AOS"] === "object"){
				AOS.refreshHard();
			}
	    }
	});
	//for ajax
	//lazyLoadInstance.update();
	document.addEventListener('lazyloaded', function(e){
		var obj = $(e.target);
		if(obj.hasClass("swiper-bg")){
	       obj.closest(".swiper-slide").addClass("image-loaded");
		}
		$.fn.matchHeight._update();
		//debugJS("lazyloaded belowwww")
		//debugJS(e);
		if(isLoadedJS("background-check")){
			setTimeout(function(){
				bg_check();
			},500);
		}
	});
	$(document)
	.on('lazyload', function(e){
		$.fn.matchHeight._update();
		if(isLoadedJS("background-check")){
			bg_check();
		}
		$(window).trigger("resize");
	});
}



function errorView($data){
	if($data.error){
		_alert('', $data.message);
		return true;
	}
}

if (window["ScrollPosStyler"] && document.getElementById("header")) {
    var header = document.getElementById("header");
    if (!header.classList.contains("affix")) {
        ScrollPosStyler.init({
            spsClass: "affixed",
            classAbove: "affix-top",
            classBelow: "affix",
            offsetTag : "data-affix-offset",
            scrollOffsetY: 50
        });
    }
}

if (document.querySelector("#main") && document.querySelector("#header") && (document.querySelector("#main").scrollTop || document.documentElement.scrollTop) > (root && root.css_vars && root.css_vars["header-height-" + size])) {
    var header = document.querySelector("#header");
    if (root && root.classes && header.classList.contains("fixed-top")) {
        root.classes.addClass(header, "affix");
        root.classes.removeClass(header, "affix-top");
    }
}




if(window["enquire"]){
	enquire
	.register("(min-width: 1200px)", {
			match: function () {
			}
	})
	.register("(max-width: 1199px)", {
			match: function () {
				//$(".header-search.show").collapse("hide");
			}
	})
	.register("(min-width: 992px)", {
			match: function () {
	            //root.map.init();
	            //google_map.init();
			}
	})
	.register("(min-width: 780px)", {
			match: function () {
			}
	})
	.register("(min-width: 0px) and (max-width: 991px)", {
			match: function () {
				if($(".stick-top").length>0){
	                $(".stick-top").each(function(){
	                	var obj = $(this);
	            	    $(this).hcSticky('update', {
						  	top: stickyOptions.assign(obj).top
						});
	            	});
	            }
	            //root.map.init();
	            //google_map.init();
			}
	})
	.register("(min-width: 0px)", {
			match: function () {
			}
	});
}

const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

$(window).on("resize", function(){
    ///$('.nav-equal').sameSize(true);
    navbar_visibility()
});

function navbar_visibility() {
    // Belirtilen elementin görünür olup olmadığını kontrol eden fonksiyon
    const isVisible = (el) => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    };

    // Bir grup elemanın görünürlüğüne göre parent elemente d-none ekleme/çıkarma
    const checkVisibility = (parent, selector) => {
        const children = parent.querySelectorAll(selector);
        const visible = Array.from(children).some(isVisible);
        parent.classList.toggle('d-none', !visible);
    };

    // header-tools için kontrol
    const headerTools = document.querySelector('.navbar-top .header-tools');
    if (headerTools) checkVisibility(headerTools, 'ul > li');

    // navbar-top'un birinci seviyedeki elementlerini kontrol et
    document.querySelectorAll('.navbar-top > *').forEach(el => {
        checkVisibility(el, ':scope > *');
    });
}