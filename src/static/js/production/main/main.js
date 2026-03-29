root.init();
var host = root.get_host();
var lang = root.lang;
var hash = root.hash;
var is_home = root.is_home;

// Açık olan modal ve offcanvasların ID'lerini burada tutacağız
window.bsStateStack = window.bsStateStack || [];
window.addEventListener('popstate', function (event) {
    if (window.bsStateStack.length > 0) {
        const el = window.bsStateStack.pop();
        const modal = bootstrap.Modal.getInstance(el);
        const offcanvas = bootstrap.Offcanvas.getInstance(el);
        if (modal) modal.hide();
        if (offcanvas) offcanvas.hide();
    }
});

const handleHashScroll = () => {
    const hash = window.location.hash;
    if (hash && typeof root !== "undefined" && root.ui && root.ui.scroll_to) {
        // Native scroll'u durdurmak için küçük bir hile
        setTimeout(() => {
            root.hash = ""; // Root içindeki eski hash'i temizle
            root.ui.scroll_to(hash, true);
        }, 100); 
    }
};

if (isLoadedJS("lenis")) {
    window.lenis = new Lenis({
        duration: 1.2,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smoothWheel: true
    });
    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
}

const initHeaderAffix = () => {
    const headerEl = document.getElementById("header");
    if (!headerEl) return;

    // ScrollPosStyler Init
    if (window["ScrollPosStyler"] && !headerEl.classList.contains("affix")) {
        ScrollPosStyler.init({
            spsClass: "affixed",
            classAbove: "affix-top",
            classBelow: "affix",
            offsetTag: "data-affix-offset",
            scrollOffsetY: 0
        });
    }

    // Sayfa ilk yüklendiğinde mevcut scroll pozisyonuna göre header'ı düzelt
    // Manuel kontrolü sadece ScrollPosStyler'ın yetişemediği ilk an için yapıyoruz
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
    const headerHeight = (typeof root !== "undefined") ? root.get_css_var("header-height") : 0;

    if (currentScroll > headerHeight && headerEl.classList.contains("fixed-top")) {
        headerEl.classList.add("affix");
        headerEl.classList.remove("affix-top");
    }
};

const promotionTop = document.getElementById('promotion-top');
if (promotionTop) {
	promotionTop.addEventListener('closed.bs.alert', () => {
		document.body.classList.remove("has-promotion-top");
		// Layout değiştiği için Lenis ve Header Offset'i güncelle
		if (typeof lenis !== "undefined") lenis.resize();
	});
}

const pageNotification = document.querySelector(".page-notification-top");
if (pageNotification) {
    pageNotification.addEventListener('closed.bs.alert', () => {
        document.body.classList.remove("has-page-notification-top");
        if (window.lenis) {
            window.lenis.resize();
        }
        if (window.ResponsiveManagerInstance) {
            window.ResponsiveManagerInstance.updateHeaderOffset();
        } else {
            window.dispatchEvent(new Event('resize'));
        }
    });
}

// Event Listeners: Hepsini tek bir çatı altında topluyoruz
window.addEventListener('load', () => {
    fitToContainer();
    initHeaderAffix();
    handleHashScroll();
});

// Resize'ı throttle (frenleme) ile çalıştırıyoruz ki CPU patlamasın
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        fitToContainer();
    }, 150);
});

window.withMethods = function(callback) {
    // 1. Değişken var mı VE içi dolu mu (site_config gelmiş mi?)
    const isReady = (typeof ajax_hooks !== 'undefined' && Object.keys(ajax_hooks).length > 0);

    if (window.methods_js_loaded || isReady) {
        window.methods_js_loaded = true;
        debugJS(">>> Methods hazır, çalıştırılıyor.");
        if (typeof callback === "function") callback();
        return;
    }

    // 2. Yükleme devam ediyorsa bekle
    if (window.methods_js_loading) {
        setTimeout(() => window.withMethods(callback), 50);
        return;
    }

    // 3. Yükleme başlat
    debugJS(">>> Methods yükleniyor...");
    window.methods_js_loading = true;
    const targetUrl = (typeof ajax_hooks_url !== 'undefined') ? ajax_hooks_url : (ajax_request_vars.theme_url + "static/js/methods.min.js");

    $.ajax({
        url: targetUrl,
        dataType: "script",
        cache: true,
        global: false,
        success: () => {
            window.methods_js_loaded = true;
            window.methods_js_loading = false;
            if (typeof callback === "function") callback();
        },
        error: () => {
            window.methods_js_loading = false;
            console.error(">>> Methods yüklenemedi!");
        }
    });
};

function init_functions($plugins_req = []){
	root.ui.scroll_dedect();
	//function_secure("", "root.ui.scroll_dedect", []);


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

		$('.btn-submit').attr("disabled", false).removeClass("processing");

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

		if($plugins_req){
			if ($plugins_req && typeof $plugins_req === 'object') {
			    Object.entries($plugins_req).forEach(([name, plugin]) => {
			        // name = plugin key (leaflet), plugin = init func adı (init_leaflet)
			        if (typeof function_secure === 'function') {
			            function_secure(name, plugin); // düzeltildi: önce key, sonra func adı
			        }
			    });
			}
		}
}

$( document ).ready(function() {

		/* ACHTUNG: Blocked for some reason
		if (!$("body").hasClass("logged")) {
			withMethods(() => {
			    if (window.ajax_hooks["site_config"]) {
			        window.ajax_hooks["site_config"].init(site_config.meta);
			    }
			});
		}*/
		init_functions();

		/*var page_notification = $(".page-notification-top");
		if(page_notification.length > 0){
			page_notification.on('closed.bs.alert', function () {
				alert("closed")
	        });			
		}*/

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

		function bs_events_dropdown() {
		    const _this = this;
		    const body = document.body;

		    // 1️⃣ Global Listeners (Delegasyon ile tek merkezden yönetim)
		    $(document)
		    .on('shown.bs.dropdown', '.dropdown-notifications', function (e) {
		        const $parent = $(this);
		        const $container = $parent.find('.dropdown-container');
		        const type = $parent.data('type');

		        // UI Durumları
		        $parent.parent().addClass('active');
		        body.classList.add('notifications-open');

		        // Search açıksa kapat (Native API kullanımı)
		        const openSearch = document.querySelector('.collapse-search.show');
		        if (openSearch) {
		            bootstrap.Collapse.getOrCreateInstance(openSearch).hide();
		        }

		        // --- Veri Yükleme (Switch-Case ile daha temiz) ---
		        if ($container.length) {
		            switch(type) {
		                case 'favorites':
		                    handle_favorites_load($parent, $container);
		                    break;
		                case 'messages':
		                    if (window.messages) {
		                        $container.addClass('loading-process');
		                        window.messages.get($container);
		                    }
		                    break;
		                case 'cart':
		                    if (window.cart) {
		                        $container.addClass('loading-process');
		                        window.cart.get($container);
		                    }
		                    break;
		            }
		        }
		    })
		    .on('hidden.bs.dropdown', '.dropdown-notifications', function () {
		        $(this).parent().removeClass('active');
		        body.classList.remove('notifications-open');
		    })
		    // 2️⃣ Updateable Dropdowns (Event Delegation ile)
		    .on('click', '.dropdown-menu.updateable a', function (e) {
		        const $link = $(this);
		        const url = $link.attr('href');

		        // Sayfa yükleniyorsa loading çak
		        if (url && url !== '#' && !url.startsWith('javascript:')) {
		            body.classList.add('loading');
		        }

		        // Toggle metnini güncelle
		        const $toggle = $link.closest('.dropdown').find('.dropdown-toggle');
		        if ($toggle.length) {
		            $toggle.html(`${$link.html()} <span class="caret"></span>`);
		        }
		    })
		    // 3️⃣ Manuel Dismiss
		    .on('click', '[data-bs-dismiss="dropdown"]', function (e) {
		        const $dropdown = $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]');
		        if ($dropdown.length) {
		            bootstrap.Dropdown.getOrCreateInstance($dropdown[0]).hide();
		        }
		    });

		    // 4️⃣ Dropdown Hover (Native CSS alternatifi ama JS lazımsa en hızlısı bu)
		    // Hover'da class eklemek yerine Bootstrap API'sini tetiklemek daha sağlıklıdır
		    $('.dropdown-hover').on('mouseenter', function() {
		        const $toggle = $(this).find('[data-bs-toggle="dropdown"]');
		        if ($toggle.length) {
		            bootstrap.Dropdown.getOrCreateInstance($toggle[0]).show();
		        }
		    }).on('mouseleave', function() {
		        const $toggle = $(this).find('[data-bs-toggle="dropdown"]');
		        if ($toggle.length) {
		            bootstrap.Dropdown.getOrCreateInstance($toggle[0]).hide();
		        }
		    });
		};
		bs_events_dropdown();

		// 5️⃣ Favorites Karşılaştırma (Daha hızlı ve temiz)
		function handle_favorites_load($parent, $container) {
		    let favs = window.site_config?.favorites || [];
		    if (typeof favs === "string") favs = JSON.parse(favs);
		    
		    if (favs.length > 0) {
		        // Mevcut ID'leri bir Set içinde topla (Arama hızı O(1) olur)
		        const currentIds = new Set(
		            Array.from($container[0].querySelectorAll('.notification-item'))
		                 .map(el => el.dataset.id)
		        );

		        // Eğer favs içindeki herhangi bir ID mevcut Set'te yoksa veya sayılar tutmuyorsa yükle
		        const isDifferent = favs.length !== currentIds.size || favs.some(id => !currentIds.has(id.toString()));

		        if (isDifferent && window.favorites) {
		            $container.addClass('loading-process');
		            window.favorites.get($container);
		        }
		    } else {
		        $container.html('<div class="empty-notify">Henüz favori yok.</div>');
		    }
		}

        /********************************************
		/*
		/*   T A B S
		/*
		/*******************************************/

		function bs_events_tab() {
		    const _this = this;

		    $(document)
		    .on('show.bs.tab', '[data-bs-toggle="tab"], [role="tab"]', function (e) {
		        const $this = $(this);
		        const $container = $this.closest(".nav, .nav-container"); // Tab grubunu bul
		        
		        // 1️⃣ UI Temizliği: Önceki active'leri temizle, yeniyi işaretle
		        $container.find(".active").removeClass("active");
		        $this.parent().addClass("active");

		        // 2️⃣ Input Yönetimi: Click tetiklemek yerine doğrudan state değiştir
		        const $input = $this.find("input");
		        if ($input.length && !$input.prop("checked")) {
		            $input.prop("checked", true).change(); // Değişikliği haber ver ama click riski alma
		        }
		    })
		    .on('shown.bs.tab', '[data-bs-toggle="tab"], [role="tab"]', function (e) {
		        // Hedef paneli bul (href veya data-bs-target)
		        const targetSelector = $(e.target).attr("href") || $(e.target).data("bs-target");
		        const $pane = $(targetSelector);

		        if ($pane.length) {
		            // 3️⃣ Akıllı Validation: Panele girince data-required olanları gerçek required yap
		            $pane.find("[data-required]").each(function() {
		                const $input = $(this);
		                $input.prop("required", true).removeAttr("data-required");
		            });
		            
		            // Eğer içeride Swiper veya Slider varsa update et
		            if (typeof root.ui.init_sliders === "function") {
		                root.ui.init_sliders($pane);
		            }
		        }
		    })
		    .on('hidden.bs.tab', '[data-bs-toggle="tab"], [role="tab"]', function (e) {
		        const targetSelector = $(e.target).attr("href") || $(e.target).data("bs-target");
		        const $pane = $(targetSelector);

		        if ($pane.length) {
		            // 4️⃣ Hijyen: Gizlenen paneldeki zorunlu alanları pasifize et, hataları sil
		            // Form gönderirken gizli panellerdeki boş alanlar yüzünden "form gitmiyor" derdi biter
		            $pane.find("[required]").each(function() {
		                $(this).prop("required", false)
		                       .attr("data-required", "true")
		                       .removeClass("is-invalid is-valid")
		                       .next(".invalid-feedback").hide(); // Hata mesajlarını da gizle
		            });
		            
		            // Opsiyonel: Gizlenen paneldeki inputları sıfırla (val("") yapıyordun, devam edelim)
		            // Ama dikkat: type="radio" veya "checkbox" ise sıfırlama, sadece metinleri sil
		            $pane.find("input:not([type='checkbox']):not([type='radio']), textarea").val("");
		        }
		    });

		    // 5️⃣ Sayfa Açılışında Auto-Init (Each yerine daha hızlı yöntem)
		    // Sadece checked olan tabları bul ve tetikle
		    const $activeTab = $('[role="tab"] input:checked').closest('[role="tab"]');
		    if ($activeTab.length) {
		        const bootstrapTab = new bootstrap.Tab($activeTab[0]);
		        bootstrapTab.show();
		    }
		}
		bs_events_tab();


        /********************************************
		/*
		/*    C O L L A P S E
		/*
		/*******************************************/

        function bs_events_collapse() {
		    const _this = this;
		    const body = document.body;

		    $(document)
		    .on("show.bs.collapse", ".collapse", function (e) {
		        const $el = $(e.target);
		        const parentAttr = $el.data("parent"); // Manuel tanımlanan parent
		        const $parent = parentAttr ? $(parentAttr) : null;
		        const $item = $el.closest(".accordion-item, .card, .nav-tree");

		        // 1️⃣ Single Parent (Tree Menu) ve Accordion Mantığı
		        // Eğer eleman bir nav-tree içindeyse veya single-parent datasına sahipse diğerlerini kapat
		        if ($item.data("single-parent") || $item.hasClass("nav-tree")) {
		            $item.find(".collapse.show").not($el).collapse('hide');
		        }

		        // 2️⃣ UI Durumları (Active class ve Checkbox yönetimi)
		        $item.addClass("active");
		        if ($parent) $parent.addClass("active");

		        // Checkbox senkronizasyonu (Toggle input)
		        const $input = $el.prev().find("input[type='checkbox'], input[type='radio']");
		        if ($input.length && !$input.prop("checked")) {
		            $input.prop("checked", true);
		        }

		        // 3️⃣ Özel Durum: Search Open
		        if ($el.hasClass("collapse-search")) {
		            body.classList.add("search-open");
		            if (window.lenis) window.lenis.stop(); // Arama açıkken scrollu durdurmak istersen
		            if (typeof $.SmartMenus !== 'undefined') {
		                $('.navbar-nav').smartmenus('menuHideAll');
		            }
		        }
		    })
		    .on("shown.bs.collapse", ".collapse", function (e) {
		        const $el = $(e.target);
		        // 4️⃣ Akıllı Scroll (Lenis desteğiyle)
		        if ($el.data("scroll")) {
		            root.ui.scroll_to($el.prev(), true, false, () => {
		                body.classList.add("header-hide");
		            });
		        }
		    })
		    .on("hide.bs.collapse", ".collapse", function (e) {
		        const $el = $(e.target);
		        const $item = $el.closest(".accordion-item, .card, .nav-tree");

		        // UI Temizliği
		        $item.removeClass("active");
		        
		        const $input = $el.prev().find("input");
		        if ($input.is(":checked")) {
		            $input.prop("checked", false);
		        }

		        if ($el.hasClass("collapse-search")) {
		            body.classList.remove("search-open");
		            if (window.lenis) window.lenis.start();
		        }
		    })
		    .on("hidden.bs.collapse", ".collapse", function (e) {
		        const $el = $(e.target);
		        const scrollType = $el.data("scroll-hidden");

		        // 5️⃣ Gizlendikten Sonra Scroll Yönetimi
		        if (scrollType) {
		            const $bsParent = $($el.attr("data-bs-parent"));
		            
		            if (scrollType === "parent" && $bsParent.length) {
		                // Eğer grupta başka açık yoksa parent'a odaklan
		                if (!$bsParent.find("[aria-expanded='true']").length) {
		                    root.ui.scroll_to($bsParent);
		                }
		            } else if (scrollType === "top") {
		                // Her şey kapandıysa en tepeye çık (Native Smooth Scroll)
		                if (window.lenis) {
		                    window.lenis.scrollTo(0);
		                } else {
		                    window.scrollTo({ top: 0, behavior: 'smooth' });
		                }
		            }
		        }
		    });
		}
		bs_events_collapse();


        /********************************************
		/*
		/*   M O D A L
		/*
		/*******************************************/

		function bs_events_modal() {
		    const body = document.body;
		    const $doc = $(document);

		    $doc
		    .on('show.bs.modal', '.modal', function () {
		        // Offcanvas açıksa kapat (Zaten hızlı çalışıyor)
		        const openOffcanvas = document.querySelector('.offcanvas.show');
		        if (openOffcanvas) {
		            bootstrap.Offcanvas.getOrCreateInstance(openOffcanvas).hide();
		        }
		    })
		    .on('shown.bs.modal', '.modal', function (e) {
		        const $modal = $(e.target);//$(this);
		        const visibleModals = $('.modal:visible');
		        const modalCount = visibleModals.length;

		        const el = e.currentTarget;
			    if (window.bsStateStack.indexOf(el) === -1) {
			        window.bsStateStack.push(el);
			        history.pushState({ bsOpen: true, type: 'modal' }, "");
			    }
		        
		        if (modalCount > 1) {
		            // Bootstrap varsayılan z-index 1050'dir. 
		            // Her yeni modal için 10'ar 10'ar artırıyoruz.
		            const zIndex = 1050 + (10 * modalCount);
		            $modal.css('z-index', zIndex);
		            
		            // requestAnimationFrame, tarayıcının bir sonraki çizim anını bekler. 
		            // setTimeout(0)'dan çok daha stabildir.
		            requestAnimationFrame(() => {
		                $('.modal-backdrop').last()
		                    .css('z-index', zIndex - 1)
		                    .addClass('modal-stack');
		            });
		        }
		        
		        $modal.scrollTop(0);
		        body.classList.remove("loading", "loading-process");

		        if (window.lenis) {
		            window.lenis.stop();
		            body.setAttribute("data-lenis-prevent", "true");
		        }
		    })
		    .on('hidden.bs.modal', '.modal', function (e) {
		        const $modal = $(e.target);
		        const visibleModals = $('.modal:visible');

		        const el = e.currentTarget;
			    const index = window.bsStateStack.indexOf(el);
			    if (index > -1) {
			        window.bsStateStack.splice(index, 1);
			        if (history.state && history.state.bsOpen) {
			            history.back();
			        }
			    }

		        if (visibleModals.length > 0) {
		            // Hala modal varsa body'i kilitlemeye devam et
		            body.classList.add('modal-open');
		            
		            // Backdrop Tamiri
		            const lastModal = visibleModals.last();
		            const lastZIndex = parseInt(lastModal.css('z-index'));
		            if (!isNaN(lastZIndex)) {
		                $('.modal-backdrop').last().css('z-index', lastZIndex - 1);
		            }
		        } else {
		            // Tüm modallar kapandıysa Lenis'e dön
		            if (window.lenis) {
		                body.removeAttribute("data-lenis-prevent");
		                // 100ms gecikme, Bootstrap'in kendi temizliğini bitirmesi için iyidir.
		                setTimeout(() => {
		                    // Sadece Lenis varsa overflow müdahalesi yapıyoruz
		                    window.lenis.start();
		                    // Scroll zıplamasını önlemek için mevcut konumu tazele
		                    window.lenis.scrollTo(window.lenis.scroll, { immediate: true });
		                    // Body temizliği
		                    body.classList.remove("modal-open");
		                }, 150);
		            }
		        }

		        // DOM'u kirletmemek için dinamik modalları temizle
		        if ($modal.hasClass("remove-on-hidden")) {
		            $modal.remove();
		        }
		    });
		}
		bs_events_modal();


		/********************************************
		/*
		/*   O F F C A N V A S
		/*
		/*******************************************/

		function bs_events_offcanvas() {
		    const $body = $("body");

		    $(document)
		    .on('show.bs.offcanvas', '.offcanvas', function (e) {
		        const $obj = $(e.target);
		        const $container = $obj.find(".offcanvas-body");

		        $body.addClass("offcanvas-open");
		        
		        if($obj.hasClass("offcanvas-search")) $body.addClass("search-open");
		        if($obj.hasClass("offcanvas-menu")) $body.addClass("menu-open");
		        if($obj.hasClass("offcanvas-show-header")) $body.addClass("menu-show-header");

		        if($obj.hasClass("offcanvas-fullscreen")) {
		            $body.addClass("offcanvas-fullscreen-open");
		            const $playingVideo = $(".plyr--playing");
		            if($playingVideo.length > 0 && $playingVideo[0].plyr) {
		                $playingVideo[0].plyr.pause();
		                $playingVideo.addClass("plyr--paused-manual");
		            }
		            $("header.fixed-bottom-start").not(".affix").addClass("affix");
		        }

		        if($obj.hasClass("offcanvas-messages") && window.messages) {
		            $container.addClass("loading-process");
		            window.messages.get($container);
		        }
		    })
		    .on('shown.bs.offcanvas', '.offcanvas', function (e) {
		        //const $obj = $(e.target);
		        //const targetId = "#" + $obj.attr("id");

		        const el = e.currentTarget;
			    const $obj = $(el);
			    const targetId = "#" + $obj.attr("id");
			    if (window.bsStateStack.indexOf(el) === -1) {
			        window.bsStateStack.push(el);
			        history.pushState({ bsOpen: true, type: 'offcanvas', id: targetId }, document.title, window.location.pathname + targetId);
			    }

		        //if($(`[href='${targetId}']`).length > 0) {
		        //    history.pushState(targetId, document.title, window.location.pathname + targetId);
		        //}

		        if(window.lenis) {
		            $body.attr("data-lenis-prevent", "");
		            window.lenis.stop();
		        }
		    })
		    .on('hidden.bs.offcanvas', '.offcanvas', function (e) {
		        const $obj = $(e.target);

		        const el = e.currentTarget;
			    const index = window.bsStateStack.indexOf(el);
			    if (index > -1) {
			        window.bsStateStack.splice(index, 1);
			        if (history.state && history.state.bsOpen) {
			            history.back();
			        }
			    }
					        
		        setTimeout(function() {
		            const $activeOffcanvas = $(".offcanvas.show");
		            
		            if ($activeOffcanvas.length > 0) {
		                if($obj.hasClass("offcanvas-search") && !$(".offcanvas-search.show").length) $body.removeClass("search-open");
		                if($obj.hasClass("offcanvas-menu") && !$(".offcanvas-menu.show").length) $body.removeClass("menu-open");
		                return; 
		            }

		            // Temizlik
		            $body.removeClass("offcanvas-open search-open menu-open menu-show-header offcanvas-fullscreen-open");
		            
		            if(history.state && String(history.state).startsWith("#offcanvas")) {
		                history.pushState("", document.title, window.location.pathname + window.location.search);
		            }

		            // Video devam et
		            const $pausedVideo = $(".plyr--paused-manual");
		            if($pausedVideo.length > 0 && $pausedVideo[0].plyr) {
		                $pausedVideo.removeClass("plyr--paused-manual");
		                $pausedVideo[0].plyr.play();
		            }

		            // Lenis Start
		            if(window.lenis) {
		                $body.removeAttr("data-lenis-prevent");
		                window.lenis.start();
		            }
                    
                    if($obj.find(".slinky-menu").length > 0){
			            const slinky = $obj.find('.slinky-menu').data('slinky'); // Eğer datada tutuyorsan
	    				slinky.home(false);                    	
                    }

		        }, 50);
		    });
		}
		bs_events_offcanvas();


        /********************************************
		/*
		/*   A J A X   E V E N T S
		/*
		/*******************************************/

		$(document)
		.on('ajax_query:start', function(event, obj) {
			console.log("🚀 İşlem Başladı: " + obj.method);
		})
		$(document).on('ajax_query:complete', function(event, obj) {
		    console.log("✅ İşlem Bitti: " + obj.method);
		    if(isLoadedJS("vanilla-lazyload")){
		    	lazyLoadInstance.update();
		    }
	    	$.fn.matchHeight._update();
	    	if(isLoadedJS("swiper")){
		    	init_swiper();
		    }
	    	btn_ajax_method();
	    	btn_loading_page();
		})
		.on('ajax_query:stop', function(event, obj) {
		    console.log("🛑 Tüm AJAX Trafiği Durdu.");
		});


        //woocommerce events
        /*$(document).on(
		    "init_checkout payment_method_selected update_checkout updated_checkout checkout_error " +
		    "applied_coupon_in_checkout removed_coupon_in_checkout adding_to_cart added_to_cart " +
		    "removed_from_cart wc_cart_button_updated cart_page_refreshed cart_totals_refreshed " +
		    "wc_fragments_loaded init_add_payment_method wc_cart_emptied updated_wc_div " +
		    "updated_cart_totals country_to_state_changed updated_shipping_method applied_coupon removed_coupon",
		    function (e) {
		        
		        // 1. inputSpinner Güncellemesi (Sadece ilgili eventlerde çalışsın)
		        const spinnerEvents = ["updated_cart_totals", "updated_checkout", "updated_wc_div"];
		        if (spinnerEvents.includes(e.type)) {
		            const $inputs = $("input[type='number']");
		            if ($inputs.length > 0 && typeof $.fn.inputSpinner !== "undefined") {
		                $inputs.inputSpinner();
		            }
		        }

		        // 2. Sepet Güncellemesi (Sadece kart içeriği değiştiğinde)
		        const cartUpdateEvents = ["added_to_cart", "removed_from_cart", "wc_fragments_loaded"];
		        if (cartUpdateEvents.includes(e.type)) {
		            const $cartContainer = $(".dropdown-notifications[data-type='cart']").find(".dropdown-container");
		            if ($cartContainer.length > 0 && typeof cart !== "undefined") {
		                cart.get($cartContainer);
		            }
		        }

		        // 3. Checkout Refresh (Ödeme metodu vb değiştiğinde)
		        if (e.type === "updated_checkout") {
		            // Checkout ekranına özel bir işlem gerekiyorsa buraya
		        }
		    }
		);*/
});