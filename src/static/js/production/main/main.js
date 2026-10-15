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

    // bottom-start modu: data-affix-offset = body.scrollHeight - header.offsetHeight
    // Bu deger updateHeaderOffset() ile yaziliyor ama requestAnimationFrame icinde (async)
    // ScrollPosStyler.init() oncesinde bu degerin yazilmis olmasi gerekiyor
    // Bu yuzden bottom-start icin offset'i burada sync hesaplayip yaziyoruz
    if (headerEl.classList.contains("fixed-bottom-start")) {
        const h = headerEl.offsetHeight || 0;
        // CSS: top: calc(100vh - header-height) — viewport bazli
        // Offset = viewport yuksekligi - header yuksekligi
        // Bu kadar scroll yapilinca header viewport disina cikiyor, affix olmali
        // Lenis aktifse window.lenis.scroll kullan, yoksa window.pageYOffset
        const offset = Math.max(1, window.innerHeight - h);
        headerEl.setAttribute("data-affix-offset", Math.round(offset));
    }

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

// Resize throttle - guard ile tekrar tanımlamayı önle
if (typeof window._resizeTimeoutGuard === 'undefined') {
    window._resizeTimeoutGuard = true;
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            fitToContainer();
        }, 150);
    });
}

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
            log(">>> Methods yüklenemedi!", 'error');
        }
    });
};

/**
 * init_nav_equal — AJAX sonrasi .nav-equal elementlerini esitler.
 * ajax-init-manager.js tarafindan cagirilir.
 * @param {HTMLElement} context - AJAX ile yuklenen container
 */
function init_nav_equal(context) {
    const $navEqual = context ? $(context).find('.nav-equal').addBack('.nav-equal') : $('.nav-equal');
    if (!$navEqual.length) return;

    $navEqual.sameSize(true, undefined, true);
    navbar_visibility();

    // init tamamlandi olarak isaretle
    $navEqual.addClass('nav-equal-initialized');
}
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

			    // init tamamlandi — AJAX initMap'in tekrar calismamasi icin
			    $('.nav-equal').addClass('nav-equal-initialized');
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


        /*
        if(site_config.enable_reactions){
        	btn_favorite();
        }
        
        
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

		/**
		 * SOURCE_REGISTRY — yalnızca convention'dan SAPAN source'lar burada tanımlanır.
		 * Kayıtta olmayan source convention'a düşer: method = 'get_{source}', vars = { view }.
		 *
		 *   handler(fn)  → tamamen özel yükleme (cart: window.cart / WC entegrasyonu).
		 *   method(str)  → ajax method adı (convention 'get_{source}' yerine).
		 *   vars(fn)     → ajax vars üretici: ($container, view, $trigger) => {...}.
		 *
		 * Yeni source eklemek için: methods/user/get_{source}/index.php +
		 * partials/source/{source}/{archive,tease}.twig yeterli — buraya EKLEME GEREKMEZ.
		 */
		window.SOURCE_REGISTRY = {
		    cart: {
		        handler: function ($container, view) {
		            // enable_ecommerce false ise WooCommerce devre dışı
		            if (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.enable_ecommerce === false) {
		                $container.removeClass('loading-process')
		                    .find('.load-container').html('<p style="text-align:center;padding:20px;color:#999;font-size:13px">🛒 Cart service is currently disabled.</p>');
		                return;
		            }
		            if (window.cart) { window.cart.get($container, view); }
		            else { $container.removeClass('loading-process'); }
		        }
		    },
		    // favorites = reaction:favorite kısayolu. Filtre attribute'ları $trigger'dan.
		    favorites: {
		        method: 'get_reactions',
		        vars: function ($container, view, $trigger) {
		            var v = { reaction_type: 'favorite', object_type: 'post', view: view, posts_per_page: 12 };
		            _applyReactionFilters(v, $container, $trigger);
		            return v;
		        }
		    },
		    notifications: {
		        method: 'get_notifications',
		        vars: function ($c, view) { return { view: view, set_seen: true, order: 'desc', posts_per_page: 15 }; }
		    },
		    messages: {
		        method: 'get_messages',
		        vars: function ($c, view) { return { view: view, order: 'desc', posts_per_page: 15 }; }
		    }
		    // reviews: KAYIT YOK → convention (get_reviews, { view }).
		    // reaction:*: aşağıdaki reaction dalı (filtre vars $trigger data-*'tan).
		};

		/**
		 * _applyReactionFilters — reaction vars'ına post_type/role/taxonomy filtrelerini ekler.
		 */
		function _applyReactionFilters(vars, $container, $trigger) {
		    var $src      = ($trigger && $trigger.length) ? $trigger : $container;
		    var postType  = $src.attr('data-post-type')    || $src.data('post-type')    || '';
		    var commentType = $src.attr('data-comment-type') || $src.data('comment-type') || '';
		    var role      = $src.attr('data-role')         || $src.data('role')         || '';
		    var taxonomy  = $src.attr('data-taxonomy')     || $src.data('taxonomy')     || '';
		    if (postType)    { vars.post_type    = postType; }
		    if (commentType) { vars.comment_type = commentType; }
		    if (role)        { vars.role         = role; }
		    if (taxonomy)    { vars.taxonomy     = taxonomy; }
		    return vars;
		}

		/**
		 * loadSourceContent — TÜM view'lar (offcanvas/modal/dropdown) için tek yükleme yolu.
		 * SOURCE_REGISTRY istisnalarını çözer; yoksa convention'a düşer.
		 */
		function loadSourceContent($container, source, view_type, $parent) {
		    view_type = view_type || 'offcanvas';
		    $parent   = $parent  || $container;
		    if (!source || !$container || !$container.length) return;

		    $container.addClass('loading-process');

		    // reaction:* / reaction-* ailesi — convention + filtre vars ($trigger data-*).
		    if (source.indexOf('reaction:') === 0 || source.indexOf('reaction-') === 0) {
		        var reactionType = source.replace(/^reaction[:-]/, '');
		        var objType      = $container.data('object-type') || ($parent && $parent.data('object-type')) || 'post';
		        var rxVars       = { reaction_type: reactionType, object_type: objType, view: view_type };
		        _applyReactionFilters(rxVars, $container, $parent);
		        return _sourceAjax($container, 'get_reactions', rxVars, view_type, 'reaction:' + reactionType);
		    }

		    var entry = window.SOURCE_REGISTRY[source] || null;

		    // Özel handler (cart) — WC entegrasyonu vb.
		    if (entry && typeof entry.handler === 'function') {
		        return entry.handler($container, view_type, $parent);
		    }

		    // Generic: kayıt varsa method/vars, yoksa convention (get_{source}, { view }).
		    var method = (entry && entry.method) || ('get_' + source);
		    var vars   = (entry && typeof entry.vars === 'function')
		        ? entry.vars($container, view_type, $parent)
		        : { view: view_type };

		    // favorites badge'i reaction:favorite data-type'ına yazılır.
		    var badgeSource = (source === 'favorites') ? 'reaction:favorite' : source;
		    _sourceAjax($container, method, vars, view_type, badgeSource);
		}

		/**
		 * _sourceAjax — standart ajax_query + _renderSourceResult + badge güncelleme (tek nokta).
		 * source verilirse response.data'dan badge otomatik güncellenir.
		 */
		function _sourceAjax($container, method, vars, view_type, source) {
		    var q = new ajax_query();
		    q.method = method;
		    q.vars   = vars;
		    q.done   = function (res) {
		        // login_required intercept — response_view() bypass et, container'a prompt render et
		        if (res && res.login_required && res.html) {
		            _renderSourceResult($container, res, view_type);
		            return;
		        }
		        if (res && res.data && source) { updateSourceBadge(source, res.data); }
		        if (res && res.html) { _renderSourceResult($container, res, view_type); }
		        else { $container.removeClass('loading-process'); }
		    };
		    q.request();
		}

		/**
		 * updateSourceBadge — bir source'un badge sayacını response.data'dan günceller.
		 * Badge hedefi: [data-type="{source}"] .notification-count (reaction için reaction-{type}).
		 * unread_count varsa onu, yoksa count'u kullanır (badge = okunmamış/toplam).
		 *
		 * @param {string} source  cart | notifications | messages | reviews | reaction:{type}
		 * @param {object} data    response.data ({ count, unread_count, ... })
		 */
		window.updateSourceBadge = function updateSourceBadge(source, data) {
		    if (!source || !data) return;
		    var dataType = source.indexOf('reaction:') === 0
		        ? 'reaction-' + source.slice('reaction:'.length)
		        : source;
		    var count = (typeof data.unread_count !== 'undefined' && data.unread_count !== null)
		        ? parseInt(data.unread_count, 10)
		        : parseInt(data.count, 10);
		    if (isNaN(count)) return;

		    $('[data-type="' + dataType + '"]').each(function () {
		        var $badge = $(this).find('.notification-count');
		        if (!$badge.length) return;
		        $badge.text(count);
		        $badge.toggleClass('d-none', count <= 0);
		    });
		};

		/**
		 * _renderSourceResult — source ajax response'ını container'a yazar.
		 */
		window._renderSourceResult = function _renderSourceResult($container, res, view_type) {
		    var $html        = $('<div class="temp">' + res.html + '</div>');
		    var bodyKey      = view_type + '-body';
		    var footKey      = view_type + '-footer';
		    var $bodyContent = $html.find('.' + bodyKey);
		    var $footContent = $html.find('.' + footKey);

		    log('[_renderSourceResult] view_type=' + view_type, 'info');
		    log('[_renderSourceResult] $container classes=' + $container.attr('class'), 'info');
		    log('[_renderSourceResult] bodyKey=' + bodyKey + ' found=' + $bodyContent.length, 'info');
		    log('[_renderSourceResult] footKey=' + footKey + ' found=' + $footContent.length, 'info');
		    log('[_renderSourceResult] res.html=' + (res.html ? res.html.substring(0,200) : 'EMPTY'), 'info');

		    if ( ! $bodyContent.length ) {
		        // Eski format fallback
		        log('[_renderSourceResult] FALLBACK — bodyKey not found, writing raw html', 'warn');
		        $container.html( res.html ).removeClass('loading-process');
		        return;
		    }

		    if ( view_type === 'modal' ) {
		        // Modal: $container = modal-content veya içindeki bir element
		        var $modalContent = $container.hasClass('modal-content')
		            ? $container
		            : $container.closest('.modal-content');
		        if ( ! $modalContent.length ) {
		            $modalContent = $container.closest('.bootbox').find('.modal-content');
		        }
		        if ( $modalContent.length ) {
		            // modal-body içeriğini güncelle — kendisini değil içini
		            var $modalBody = $modalContent.find('.modal-body');
		            $modalBody.html( $bodyContent.html() ).removeClass('loading-process');

		            // Source pagination — modal-body içindeki source-container için
		            setTimeout(function() { _initSourcePagination($modalBody, 'modal'); }, 50);

		            // modal-footer: varsa güncelle, yoksa ekle
		            var $existingFoot = $modalContent.find('.modal-footer');
		            if ( $footContent.length ) {
		                if ( $existingFoot.length ) {
		                    $existingFoot.html( $footContent.html() ).removeClass('d-none');
		                } else {
		                    $modalContent.append( '<div class="modal-footer">' + $footContent.html() + '</div>' );
		                }
		            } else {
		                $existingFoot.addClass('d-none');
		            }
		        } else {
		            $container.html( $bodyContent.html() ).removeClass('loading-process');
		        }
		    } else {
		        // Offcanvas / Dropdown
		        // Kural: response HTML'deki wrapper ({view}-body, {view}-footer) hiçbir zaman
		        // DOM'a tekrar yazılmaz. Sadece İÇERİĞİ alınır ve mevcut wrapper'a eklenir.
		        // Response wrapper'ında extra class'lar varsa → mevcut DOM wrapper'ına eklenir.

		        // ── Body ─────────────────────────────────────────────────────
		        // DOM'da hedef {view}-body'yi bul
		        var $targetBody = $container.hasClass(bodyKey)
		            ? $container
		            : $container.closest('.offcanvas, .dropdown-menu').find('.' + bodyKey);

		        log('[_renderSourceResult] $targetBody found=' + $targetBody.length + ' classes=' + ($targetBody.length ? $targetBody.attr('class') : 'N/A'), 'info');

		        if ( ! $targetBody.length ) {
		            // Fallback: hedef yok, direkt yaz
		            log('[_renderSourceResult] targetBody not found, fallback direct write', 'warn');
		            $container.html( $bodyContent.html() ).removeClass('loading-process');
		        } else {
		            // Response wrapper'ındaki extra class'ları DOM wrapper'ına ekle
		            var extraBodyClasses = $bodyContent[0].className
		                .replace(bodyKey, '').trim();
		            if ( extraBodyClasses ) {
		                log('[_renderSourceResult] adding extra classes to targetBody: ' + extraBodyClasses, 'info');
		                $targetBody.addClass(extraBodyClasses);
		            }
		            // İçeriği yaz
		            log('[_renderSourceResult] writing to targetBody, content length=' + $bodyContent.html().length, 'info');
		            $targetBody.html( $bodyContent.html() ).removeClass('loading-process');

		            // ajax_paginate re-init — yazılan içerikte .ajax-paginate varsa init et
		            if ( typeof ajax_paginate === 'function' ) {
		                setTimeout(function() { ajax_paginate(); }, 50);
		            }

		            // Source pagination — source-container için scroll + load-more
		            setTimeout(function() { _initSourcePagination($targetBody, view_type); }, 50);
		        }

		        // ── Footer ────────────────────────────────────────────────────
		        var $targetFoot = $container
		            .closest('.offcanvas, .dropdown-menu')
		            .find('.' + footKey);
		        if ( $targetFoot.length ) {
		            if ( $footContent.length ) {
		                // Extra class'ları ekle
		                var extraFootClasses = $footContent[0].className
		                    .replace(footKey, '').replace('d-none', '').trim();
		                if ( extraFootClasses ) {
		                    $targetFoot.addClass(extraFootClasses);
		                }
		                $targetFoot.html( $footContent.html() ).removeClass('d-none');
		            } else {
		                $targetFoot.addClass('d-none');
		            }
		        }
		    }
		}

		/**
		 * _applyCustomStyle — data-*-style attribute'undaki CSS variable string'ini
		 * hedef element'in inline style'ına uygular. Cascade'de root.css'i override eder.
		 *
		 * @param {jQuery} $el    - style uygulanacak element (modal, offcanvas, dropdown-menu vb.)
		 * @param {string} vars   - "--modal-bg:#1a1a1a;--modal-border-radius:12px;" formatında string
		 */
		window._applyCustomStyle = function _applyCustomStyle($el, vars) {
		    if ( ! $el || ! $el.length || ! vars ) return;
		    var hasBg = false;
		    vars.split(';').forEach(function(pair) {
		        pair = pair.trim();
		        if ( ! pair ) return;
		        var idx = pair.indexOf(':');
		        if ( idx === -1 ) return;
		        var prop = pair.substring(0, idx).trim();
		        var val  = pair.substring(idx + 1).trim();
		        if ( prop && val ) {
		            $el[0].style.setProperty(prop, val);
		            // bg set ediliyorsa gradient/image'ı temizle
		            if ( prop === '--modal-bg' || prop === '--offcanvas-bg' ) hasBg = true;
		        }
		    });
		    // bg override varsa gradient/image'ı da temizle ki bg rengi görünsün
		    if ( hasBg ) {
		        $el[0].style.setProperty('--modal-bg-gradient', 'none');
		        $el[0].style.setProperty('--modal-bg-image', 'none');
		        $el[0].style.setProperty('--offcanvas-bg-gradient', 'none');
		        $el[0].style.setProperty('--offcanvas-bg-image', 'none');
		    }
		}

		/**
		 * _initSourcePagination — source-container içindeki pagination'ı başlatır.
		 * Scroll-based: container sonuna yaklaşınca otomatik yükler.
		 * Button: source-load-more-btn tıklanınca yükler.
		 */
		function _initSourcePagination($body, view_type) {
		    var $container = $body.find('.source-container');
		    if ( ! $container.length ) return;

		    function loadNextPage() {
		        var page      = parseInt( $container.data('page') ) || 1;
		        var pageTotal = parseInt( $container.data('page-total') ) || 1;
		        var method    = $container.data('source');
		        var view      = $container.data('view') || view_type;
		        var ppp       = parseInt( $container.data('posts-per-page') ) || 15;
		        var nextPage  = page + 1;

		        if ( nextPage > pageTotal ) return; // son sayfa

		        var $btn = $container.find('.source-load-more-btn');
		        if ( $btn.hasClass('loading') || $container.hasClass('src-loading') ) return; // zaten yukleniyor
		        $btn.addClass('loading disabled');

		        // Alt loading gostergesi — scroll ile yuklemede buton gizli oldugu icin
		        // liste sonuna tema loader'i (.loading-inline) ekle; icerik gelince kaldirilir.
		        $container.addClass('src-loading');
		        var $loader = $container.find('.source-loading');
		        if ( ! $loader.length ) {
		            $loader = $('<div class="source-loading loading-inline loading-inline-sm" role="status" aria-live="polite"></div>');
		            $container.find('.source-list').after( $loader );
		        }
		        $loader.removeClass('d-none');

		        var methodMap = {
		            'notifications':     'get_notifications',
		            'messages':          'get_messages',
		            'reviews':           'get_reviews',
		            'cart':              'get_cart',
		            'reaction:favorite': 'get_reactions',
		            'reaction:like':     'get_reactions',
		            'reaction:bookmark': 'get_reactions',
		            'reaction:follow':   'get_reactions',
		            'reaction:clap':     'get_reactions',
		        };
		        var ajaxMethod = methodMap[method] || ( method.indexOf('reaction:') === 0 ? 'get_reactions' : 'get_' + method );

		        var q = new ajax_query();
		        q.method = ajaxMethod;
		        q.vars   = { view: view, page: nextPage, posts_per_page: ppp, order: 'desc', set_seen: true };

		        // reactions için ek parametreler
		        if ( method.indexOf('reaction:') === 0 ) {
		            q.vars.reaction_type = method.replace('reaction:', '');
		            q.vars.object_type   = $container.data('object-type') || 'post';
		            var _pt  = $container.data('post-type')  || $container.closest('[data-post-type]').data('post-type')   || '';
		            var _rl  = $container.data('role')       || $container.closest('[data-role]').data('role')             || '';
		            var _tx  = $container.data('taxonomy')   || $container.closest('[data-taxonomy]').data('taxonomy')     || '';
		            if (_pt) q.vars.post_type = _pt;
		            if (_rl) q.vars.role      = _rl;
		            if (_tx) q.vars.taxonomy  = _tx;
		        }
		        q.done   = function(res) {
		            // Loading gostergesini kaldir (basari/hata farketmez).
		            $container.removeClass('src-loading');
		            $container.find('.source-loading').remove();

		            if ( res && res.html ) {
		                // Sadece <li> item'ları append et
		                var $temp = $('<div>' + res.html + '</div>');
		                var $items = $temp.find('.source-list li');
		                if ( $items.length ) {
		                    $container.find('.source-list').append( $items );
		                }

		                // data-page güncelle
		                $container.data('page', nextPage);

		                // load-more butonu güncelle
		                var newTotal = parseInt( (res.data && res.data.page_total) ) || pageTotal;
		                $container.data('page-total', newTotal);

		                if ( nextPage >= newTotal ) {
		                    $container.find('.source-load-more').remove();
		                } else {
		                    $btn.data('next-page', nextPage + 1)
		                        .removeClass('loading disabled');
		                }
		            } else {
		                $btn.removeClass('loading disabled');
		            }
		        };
		        q.request();
		    }

		    // ── Load-more button ───────────────────────────────────────────
		    $body.off('click.src-paginate').on('click.src-paginate', '.source-load-more-btn', function(e) {
		        e.preventDefault();
		        loadNextPage();
		    });

		    // ── Scroll-based ───────────────────────────────────────────────
		    // Modal: .modal-dialog-scrollable varsa modal-body scroll eder,
		    //        yoksa .modal wrapper (overflow-y: auto) scroll eder.
		    // Offcanvas/Dropdown: $body doğrudan scroll eder.
		    var $scrollTarget;
		    if ( view_type === 'modal' ) {
		        var $modalDialog = $body.closest('.modal-dialog');
		        if ( $modalDialog.hasClass('modal-dialog-scrollable') ) {
		            $scrollTarget = $body; // modal-body scrolls
		        } else {
		            $scrollTarget = $body.closest('.modal'); // modal itself scrolls
		        }
		    } else {
		        $scrollTarget = $body;
		    }

		    var scrollEl = $scrollTarget[0];

		    function _onSrcScroll() {
		        var bottom = scrollEl.scrollTop + scrollEl.clientHeight;
		        var total  = scrollEl.scrollHeight;
		        if ( total - bottom <= 200 ) { loadNextPage(); }
		    }
		    if ( scrollEl._srcPaginate ) scrollEl.removeEventListener('scroll', scrollEl._srcPaginate, true);
		    scrollEl._srcPaginate = _onSrcScroll;
		    scrollEl.addEventListener('scroll', _onSrcScroll, true);

		    // Offcanvas: parent .offcanvas da dinle (capture=true)
		    if ( view_type === 'offcanvas' ) {
		        var $offcanvas = $body.closest('.offcanvas');
		        if ( $offcanvas.length ) {
		            var ocEl = $offcanvas[0];
		            function _onOcScroll() {
		                var bottom = ocEl.scrollTop + ocEl.clientHeight;
		                var total  = ocEl.scrollHeight;
		                if ( total - bottom <= 200 ) { loadNextPage(); }
		            }
		            if ( ocEl._srcPaginate ) ocEl.removeEventListener('scroll', ocEl._srcPaginate, true);
		            ocEl._srcPaginate = _onOcScroll;
		            ocEl.addEventListener('scroll', _onOcScroll, true);
		        }
		    }
		}

		// Dropdown'u KESIN kapat — Bootstrap state'i (hover ile) senkronsuz olabildiginden
		// hem menu hem parent'tan 'show' temizlenir, aria-expanded false, backdrop (body class)
		// kaldirilir, Bootstrap instance da hide edilir (varsa). Tek dogru kapatma noktasi.
		function _forceCloseDropdown($wrap) {
		    if (!$wrap || !$wrap.length) return;
		    var $toggle = $wrap.find('[data-bs-toggle="dropdown"], .dropdown-toggle').first();
		    var $menu   = $wrap.find('.dropdown-menu').first();
		    $wrap.removeClass('show');
		    $menu.removeClass('show').removeAttr('style'); // Popper inline style de temizlensin
		    if ($toggle.length) $toggle.attr('aria-expanded', 'false');
		    // Backdrop / body state
		    document.body.classList.remove('notifications-open');
		    $wrap.parent().removeClass('active');
		    $wrap.trigger('hidden.bs.dropdown');
		}
		window._forceCloseDropdown = _forceCloseDropdown;

		function bs_events_dropdown() {
		    const _this = this;
		    const body = document.body;

		    // Dis alana tiklama → acik hover dropdown'lari kapat (Bootstrap auto-close senkronsuz
		    // state'te calismayabildiginden elle garanti).
		    $(document).on('click.shDropdownOutside', function (e) {
		        var $openHover = $('.dropdown-hover.show, .dropdown-hover .dropdown-menu.show').closest('.dropdown-hover');
		        if (!$openHover.length) return;
		        $openHover.each(function () {
		            if (!$(this).is(e.target) && $(this).has(e.target).length === 0) {
		                _forceCloseDropdown($(this));
		            }
		        });
		    });

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

		        // Lenis — dropdown scrollable body'sinde native scroll çalışsın
		        const scrollableEl = $parent.find('.dropdown-body.scrollable')[0];
		        if (scrollableEl && window.lenis) {
		            scrollableEl.addEventListener('wheel', function(e) {
		                e.stopPropagation();
		            }, { passive: true });
		            // Lenis'i dropdown açıkken durdurma — sadece event propagation engelle
		        }
		        // (slinky DOM'a geç eklendi ya da gizliyken init edildi)
		        if ( typeof init_slinky === 'function' ) {
		            setTimeout(function() {
		                $parent.find('.slinky-menu').each(function() {
		                    // Token varsa zaten init edilmiş — atla
		                    if ( !$(this).hasClass('slinky-menu-init') ) {
		                        init_slinky();
		                    } else {
		                        // Init edilmiş ama gizliyken oluştu — height'i düzelt
		                        var sl = $(this).data('slinky');
		                        if (sl && sl.menu) {
		                            sl.menu.css('height', '');
		                            sl.menu.find('ul').first().css('height', '');
		                        }
		                    }
		                });
		            }, 50);
		        }

		        // --- Veri Yükleme — loadSourceContent ile merkezi yönetim ---
		        if ($container.length && type) {
		            // Dropdown için $parent = .dropdown-notifications div — reaction filter data-* yok.
		            // Trigger <a> (data-bs-toggle=dropdown olan ebeveyn <li>'nin içindeki) data-*'ı taşır.
		            var $dropTrigger = $parent.find('[data-type]').first();
		            if (!$dropTrigger.length) $dropTrigger = $parent.closest('li[data-type]').first();
		            loadSourceContent($container, type, 'dropdown', $dropTrigger.length ? $dropTrigger : $parent);
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
		    // 3️⃣ Manuel Dismiss (close butonu)
		    .on('click', '[data-bs-dismiss="dropdown"]', function (e) {
		        e.preventDefault();
		        _forceCloseDropdown($(this).closest('.dropdown'));
		    });

		    // 4️⃣ Dropdown Hover — SADECE ACAR, mouseleave ile KAPATMAZ.
		    // Istek: hover ile acilir; kapanma yalnizca (a) dis alana tiklama,
		    // (b) toggle butonuna tiklama, (c) close butonuna tiklama ile olur.
		    //
		    // NOT (kritik): Bootstrap Dropdown.show() API'si .dropdown-menu'ye 'show' ekler ama
		    // parent .dropdown'a EKLEMEZ ve internal _isShown state'ini bizim hover ile senkron
		    // tutmaz. Sonuc: parent.show=false, menu.show=true -> Bootstrap toggle/hide "zaten
		    // kapali" sanip menu'yu kapatamaz (DevTools ile dogrulandi). Cozum: hover'da state'i
		    // ELLE senkronla (parent.show + aria-expanded), kapatmayi da _forceCloseDropdown ile
		    // ELLE garanti et (Bootstrap .hide()'a guvenme).
		    // TAM MANUEL yaklasim — Bootstrap Dropdown JS'ini bu dropdown'larda KULLANMIYORUZ.
		    // Sebep: Bootstrap.show() API'si parent.show'u set etmiyor (state senkronsuz), ve
		    // toggle'daki data-bs-toggle="dropdown" Bootstrap'in kendi document-delegated click
		    // listener'ini tetikleyip bizim handler'la yarisiyor -> toggle click'te menu kapanmiyor
		    // (sadece backdrop gidiyor). Cozum: acma/kapama tamamen bizim class toggle'imizla
		    // (_openDropdown/_forceCloseDropdown), Bootstrap toggle click'i CAPTURE phase'de iptal.
		    function _openDropdown($wrap, $toggle, $menu) {
		        $wrap.addClass('show');
		        $menu.addClass('show');
		        $toggle.attr('aria-expanded', 'true');
        document.body.classList.add('notifications-open');
		        $wrap.parent().addClass('active');
		        // shown.bs.dropdown dinleyicisi (loadSourceContent) — manuel tetikle ($wrap = .dropdown-notifications).
		        $wrap.trigger('shown.bs.dropdown');
		    }

		    $('.dropdown-hover').each(function() {
		        var $wrap   = $(this);
		        var $toggle = $wrap.find('[data-bs-toggle="dropdown"]').first();
		        var $menu   = $wrap.find('.dropdown-menu').first();
		        if (!$toggle.length || !$menu.length) return;

		        // Bootstrap'in bu toggle'daki dropdown davranisini KAPAT
		        try {
		            var _inst = bootstrap.Dropdown.getInstance($toggle[0]);
		            if (_inst) _inst.dispose();
		        } catch (err) {}
		        $toggle.removeAttr('data-bs-toggle');

		        // data attribute'lardan delay oku — override edilebilir
		        var openDelay  = parseInt( $wrap.data('hover-delay')       || 300, 10 );
		        var closeDelay = parseInt( $wrap.data('hover-leave-delay') || 200, 10 );

		        var _openTimer  = null;
		        var _closeTimer = null;

		        // Hover → delay sonra ac
		        $wrap.on('mouseenter', function() {
		            clearTimeout(_closeTimer);
		            _closeTimer = null;
		            if (!$menu.hasClass('show')) {
		                _openTimer = setTimeout(function() {
		                    _openDropdown($wrap, $toggle, $menu);
		                }, openDelay);
		            }
		        });

		        // Mouseleave → delay sonra kapat
		        $wrap.on('mouseleave', function() {
		            clearTimeout(_openTimer);
		            _openTimer = null;
		            if ($menu.hasClass('show')) {
		                _closeTimer = setTimeout(function() {
		                    _forceCloseDropdown($wrap);
		                }, closeDelay);
		            }
		        });

		        // Toggle click → anında aç/kapat (delay yok, kasıtlı tıklama)
		        $toggle[0].addEventListener('click', function(e) {
		            e.preventDefault();
		            e.stopPropagation();
		            clearTimeout(_openTimer);
		            clearTimeout(_closeTimer);
		            if ($menu.hasClass('show')) _forceCloseDropdown($wrap);
		            else _openDropdown($wrap, $toggle, $menu);
		        }, true);
		    });
		};
		bs_events_dropdown();

		// Per-button offcanvas custom style — sayfa yüklenince statik offcanvas'lara uygula
		$(function() {
		    $('[data-offcanvas-style]').each(function() {
		        var _ocStyle = $(this).data('offcanvas-style') || '';
		        if ( _ocStyle ) {
		            var ocId = $(this).data('salt-offcanvas') || $(this).attr('data-bs-target') || '';
		            if ( ocId ) {
		                var $oc = $(ocId.charAt(0) === '#' ? ocId : '#' + ocId);
		                if ( $oc.length ) { _applyCustomStyle($oc, _ocStyle); }
		            }
		        }
		    });
		});

		// 5️⃣ Favorites — geriye dönük uyum sarmalayıcı.
		// favorites artık SOURCE_REGISTRY'de (reaction:favorite kısayolu). Eski çağrılar
		// (modal handler vb.) bozulmasın diye loadSourceContent'e yönlendiriyoruz.
		function handle_favorites_load($parent, $container, view_type) {
		    loadSourceContent($container, 'favorites', view_type || 'dropdown', $parent);
		}


		/********************************************
		/*
		/*   PANEL INTERACTIONS (dropdown + offcanvas)
		/*   Merkezi event delegation sistemi.
		/*   Yeni type eklemek icin sadece panelTypes objesine ekle.
		/*
		/*******************************************/

		function init_panel_interactions() {

		    // ── Type tanimlari ──────────────────────────────
		    // Her type icin: manager instance, remove selector, remove handler
		    var panelTypes = {
		        cart: {
		            removeSelector: ".cart-item-remove",
		            remove: function($item, view) {
		                if (window.cart) cart.removeItem($item, view);
		            }
		        },
		        favorites: {
		            removeSelector: ".favorites-remove",
		            remove: function($item, view) {
		                if (window.favorites) favorites.remove($item);
		            }
		        },
		        messages: {
		            removeSelector: null, // messages'da item remove yok
		            remove: null
		        },
		        notifications: {
		            removeSelector: null,
		            remove: null
		        }
		    };

		    // ── Yardimci: view type tespit ──────────────────
		    function detectView($el) {
		        return $el.closest(".offcanvas").length > 0 ? "offcanvas" : "dropdown";
		    }

		    // ── Yardimci: scrollbar init ────────────────────
		    function initScrollbar($container) {
		        var $scrollable = $container.find(".dropdown-body.scrollable, .offcanvas-body");
		        if ($scrollable.length && typeof SimpleScrollbar !== "undefined") {
		            $scrollable.each(function() { SimpleScrollbar.initEl(this); });
		        }
		    }

		    // ── Yardimci: has-dropdown-item toggle ──────────
		    function toggleEmptyState($container, hasItems) {
		        $container.toggleClass("has-dropdown-item", hasItems);
		        $container.find(".content-centered").toggle(!hasItems);
		        $container.find(".dropdown-footer, .offcanvas-footer").toggleClass("d-none", !hasItems);
		    }

		    // ── 1. ITEM REMOVE — Event Delegation ──────────
		    // Tek bir listener, tum type'lar icin calisiyor.
		    $(document).on("click", "[class*='-item-remove'], [class*='-remove']", function(e) {
		        e.preventDefault();
		        var $btn = $(this);
		        var $item = $btn.closest(".notification-item");
		        var type = $item.data("type") || $btn.data("type");
		        var view = detectView($btn);

		        if (type && panelTypes[type] && panelTypes[type].remove) {
		            panelTypes[type].remove($item, view);
		        }
		    });

		    // ── 2. AJAX COMPLETE — Post-load islemleri ─────
		    // AJAX ile icerik yuklendikten sonra scrollbar, lazyload vs. init et.
		    $(document).on("ajax_query:complete", function(event, obj) {
		        if (!obj || !obj.method) return;

		        var affectedTypes = {
		            "get_cart": "cart",
		            "wc_cart_item_remove": "cart",
		            "favorites_get": "favorites",
		            "favorites_add": "favorites",
		            "favorites_remove": "favorites",
		            "get_messages": "messages",
		            "get_notifications": "notifications"
		        };

		        var type = affectedTypes[obj.method];
		        if (!type) return;

		        // Tum bu type'in container'larini bul ve scrollbar init et
		        setTimeout(function() {
		            $(".dropdown-notifications[data-type='" + type + "'] .dropdown-container").each(function() {
		                initScrollbar($(this));
		            });
		            $(".offcanvas-" + type + " .load-container, .offcanvas-" + type + " .offcanvas-body").each(function() {
		                initScrollbar($(this));
		            });
		            // LazyLoad guncelle
		            if (typeof lazyLoadInstance !== "undefined") lazyLoadInstance.update();
		        }, 100);
		    });

		}

		// Init
		init_panel_interactions();

		// ── _checkEmptyState — liste boşalınca empty HTML göster ─────────────
		function _checkEmptyState($container, source, count) {
		    if (!$container || !$container.length) return;

		    // Count parametresi verilmemişse DOM'dan hesapla
		    if (typeof count === 'undefined' || count === null) {
		        count = $container.find('.source-item, .notification-item, li[data-id]').length;
		    }

		    if (count > 0) return;

		    // source-container'daki data-source'dan empty template belirle
		    var sourceType = source || $container.data('source') || '';
		    var emptyHtml  = _getEmptyHtml(sourceType);

		    // source-list varsa oraya yaz, yoksa container'a
		    var $list = $container.find('.source-list, .list-cards, .dropdown-body .content');
		    if ($list.length) {
		        $list.html(emptyHtml);
		    } else {
		        $container.html(emptyHtml);
		    }

		    // Footer'ı gizle (load-more + cart toplam/checkout footer'i vb.)
		    // NOT: footer $container'in KARDESI degil — .offcanvas/.modal/.dropdown
		    // kabinin altinda, .offcanvas-body'nin kardesidir. Bu yuzden siblings()
		    // ile bulunamiyordu ve sepet bosalinca footer kaliyordu. closest().find()
		    // ile kabi bulup footer'i gizle (_renderSourceResult ile ayni yol).
		    $container.find('.source-load-more').addClass('d-none');
		    var $footer = $container
		        .closest('.offcanvas, .dropdown-menu, .modal-content, .dropdown-notifications')
		        .find('.offcanvas-footer, .dropdown-footer, .modal-footer');
		    if ($footer.length) $footer.addClass('d-none');

		    // has-dropdown-item class'ını kaldır
		    $container.closest('.dropdown-container, .load-container').removeClass('has-dropdown-item');
		}

		// ── _getEmptyHtml — source tipine göre empty state HTML'i ────────────
		function _getEmptyHtml(source) {
		    var icon = '';
		    var message = '';

		    if (source === 'cart') {
		        icon = fa_icon('cart', 'fa-3x opacity-25');
		        message = translate('Your cart is empty.');
		    } else if (source === 'favorites' || source.indexOf('reaction:favorite') === 0) {
		        icon = fa_icon('favorite', 'fa-3x opacity-25');
		        message = translate('Your favorites list is empty.');
		    } else if (source.indexOf('reaction:') === 0) {
		        var rxKey = source.replace(/^reaction[:-]/, '');
		        icon = fa_icon(rxKey, 'fa-3x opacity-25') || fa_icon('empty', 'fa-3x opacity-25');
		        message = translate('No items found.');
		    } else if (source === 'notifications') {
		        icon = fa_icon('notifications', 'fa-3x opacity-25');
		        message = translate('No notifications.');
		    } else if (source === 'messages') {
		        icon = fa_icon('messages', 'fa-3x opacity-25');
		        message = translate('No messages.');
		    } else if (source === 'reviews') {
		        icon = fa_icon('reviews', 'fa-3x opacity-25');
		        message = translate('No reviews.');
		    } else {
		        icon = fa_icon('empty', 'fa-3x opacity-25');
		        message = translate('No items found.');
		    }

		    return '<div class="source-empty text-center py-5">'
		        + '<div class="d-flex flex-column align-items-center gap-3">'
		        + icon
		        + '<p class="text-muted mb-0">' + message + '</p>'
		        + '</div>'
		        + '</div>';
		}

		// Global erişim (diğer modüller kullanabilsin)
		window._checkEmptyState = _checkEmptyState;

		// ── .btn-remove — evrensel kaldır handler ────────────────────────────
		// data-source="cart"     → cart item kaldır (data-key gerekli)
		// data-reaction-type     → reaction toggle (kaldır)
		// Event delegation — AJAX ile yüklenen içerik için de çalışır
		$(document).on('click', '.btn-remove', function(e) {
		    e.preventDefault();
		    e.stopPropagation();
		    var $btn = $(this);
		    if ($btn.hasClass('loading')) return;

		    var source       = $btn.data('source') || '';
		    var reactionType = $btn.data('reaction-type') || '';
		    var objectType   = $btn.data('object-type') || 'post';
		    var objectId     = $btn.data('id') || 0;
		    var $item        = $btn.closest('.source-item, .notification-item, li[data-id]');
		    var $container   = $btn.closest('.source-container, .load-container, .dropdown-container, .offcanvas-body, .modal-body');

		    // ── Cart item kaldır ──────────────────────────────────────────
		    if ( source === 'cart' || $btn.hasClass('cart-item-remove') ) {
		        var cartKey = $btn.data('key') || '';
		        if (!cartKey) return;
		        $btn.addClass('loading');
		        var q = new ajax_query();
		        q.method = 'wc_cart_item_remove';
		        q.vars   = { key: cartKey };
		        q.done   = function(res) {
		            $btn.removeClass('loading');
		            if (res && !res.error) {
		                // Badge güncelle
		                var count = (res.data && typeof res.data.count !== 'undefined') ? res.data.count : null;
		                if (count !== null && typeof cart !== 'undefined') {
		                    cart.updateBadge('cart', count);
		                }
		                // Item'ı kaldır
		                $item.fadeOut(200, function(){
		                    $(this).remove();
		                    // Empty state kontrolü
		                    _checkEmptyState($container, 'cart', count);
		                });
		            }
		        };
		        q.request();
		        return;
		    }

		    // ── Reaction kaldır ───────────────────────────────────────────
		    if ( reactionType && objectId ) {
		        $btn.addClass('loading');
		        var rq = new ajax_query();
		        rq.method = 'reaction_toggle';
		        rq.vars   = { type: reactionType, object_id: objectId, object_type: objectType };
		        rq.done   = function(res) {
		            $btn.removeClass('loading');
		            if (res && !res.error && res.action === 'removed') {
		                // Badge güncelle — salt:reaction:toggled event dispatch
		                document.dispatchEvent(new CustomEvent('salt:reaction:toggled', {
		                    detail: { type: reactionType, action: 'removed', object_id: objectId, object_type: objectType }
		                }));
		                // Item'ı kaldır
		                $item.fadeOut(200, function(){
		                    $(this).remove();
		                    // Empty state kontrolü
		                    _checkEmptyState($container, 'reaction:' + reactionType);
		                });
		            }
		        };
		        rq.request();
		        return;
		    }
		});

		// ── Reaction toggle → notification-count badge anlık güncelleme ──────
		document.addEventListener('salt:reaction:toggled', function(e) {
		    var d = e.detail;
		    if (!d) return;

		    var dataType = 'reaction-' + d.type;
		    var $items = $('[data-type="' + dataType + '"]');
		    if (!$items.length) return;

		    $items.each(function() {
		        var $badge = $(this).find('.notification-count');
		        if (!$badge.length) return;

		        // badge'deki mevcut user toplam count'u al
		        var current = parseInt($badge.text()) || 0;
		        // action: 'added' veya 'removed' — reactions.js'den geliyor
		        // 'exists' = zaten vardı, değişmedi — badge değiştirme
		        if (d.action === 'added') {
		            var newCount = current + 1;
		        } else if (d.action === 'removed') {
		            var newCount = Math.max(0, current - 1);
		        } else {
		            return; // exists veya başka — değiştirme
		        }

		        $badge.text(newCount);
		        if (newCount > 0) {
		            $badge.removeClass('d-none');
		        } else {
		            $badge.addClass('d-none');
		        }
		    });
		});

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

		    // ── Source-based modal: bootbox'ı direkt JS'den aç ───────────────
		    $doc.on('click', '[data-bs-source-modal]', function(e) {
		        e.preventDefault();
		        const $btn    = $(this);

		        // Son tıklanan source-modal trigger'ını kaydet — show.bs.modal'da kullanılır
		        window._lastSourceModalBtn = $btn;
		        const source  = $btn.data('bs-source-modal');
		        const size    = $btn.data('size') || 'lg';
		        const title   = $btn.data('title') || '';
		        const cls     = $btn.data('class') || '';
		        const scroll  = $btn.data('scrollable') ? true : false;
		        const closeBtn = $btn.data('close') !== false && $btn.data('close') !== undefined ? true : false;
		        const modalStyle = $btn.data('modal-style') || '';

		        // CSS lazy load varsa yüklenene kadar bekle, sonra modal aç
		        function _openSourceModal() {
		            // bootbox yüklü değilse önce yükle, sonra aç
		            if (typeof bootbox === 'undefined') {
		                if (typeof function_secure === 'function') {
		                    function_secure('bootbox', 'init_bootbox', []);
		                }
		                // bootbox async yüklenince tekrar dene
		                var _bbRetry = 0;
		                var _bbWait = setInterval(function() {
		                    _bbRetry++;
		                    if (typeof bootbox !== 'undefined') {
		                        clearInterval(_bbWait);
		                        _openSourceModal();
		                    } else if (_bbRetry > 40) { // 2sn timeout
		                        clearInterval(_bbWait);
		                        console.error('[modal] bootbox yüklenemedi');
		                    }
		                }, 50);
		                return;
		            }
		            const dialog = bootbox.dialog({
		                title:      title,
		                message:    '<div class="load-container loading-process"></div>',
		                size:       size,
		                scrollable: scroll,
		                onEscape:   true,
		                closeButton: true,
		                className:  'modal-source modal-source-' + source.replace(':', '-') + (cls ? ' ' + cls : ''),
		                buttons: {}
		            });

		        // show.bs.modal: custom style'ı modal görünmeden önce uygula
		        dialog.on('show.bs.modal', function() {
		            if ( modalStyle ) {
		                var $mc = $(this).find('.modal-content');
		                _applyCustomStyle($mc, modalStyle);
		            }
		        });

		        // shown.bs.modal: içeriği yükle
		        dialog.on('shown.bs.modal', function() {
		            var $dialog       = $(this);
		            var $modalContent = $dialog.find('.modal-content');
		            var $modalBody    = $modalContent.find('.modal-body');
		            $modalBody.addClass('loading-process');

		            // AJAX ile içeriği çek
		            var q = new ajax_query();
		            q.method = 'get_' + (source === 'cart' ? 'cart' :
		                        source === 'notifications' ? 'notifications' :
		                        source === 'messages' ? 'messages' :
		                        source === 'reviews' ? 'reviews' :
		                        source.indexOf('reaction:') === 0 ? 'reactions' : source);

		            q.vars = { view: 'modal' };

		            if ( source.indexOf('reaction:') === 0 ) {
		                q.vars.reaction_type = source.replace('reaction:', '');
		                q.vars.object_type   = $btn.data('object-type') || $btn.attr('data-object-type') || 'post';
		                // Reaction filter: post_type / comment_type / role / taxonomy — <a> elementinden oku
		                var _rxPostType    = $btn.attr('data-post-type')    || '';
		                var _rxCommentType = $btn.attr('data-comment-type') || '';
		                var _rxRole        = $btn.attr('data-role')         || '';
		                var _rxTaxonomy    = $btn.attr('data-taxonomy')     || '';
		                if (_rxPostType)    q.vars.post_type    = _rxPostType;
		                if (_rxCommentType) q.vars.comment_type = _rxCommentType;
		                if (_rxRole)        q.vars.role         = _rxRole;
		                if (_rxTaxonomy)    q.vars.taxonomy     = _rxTaxonomy;
		            }

		            q.done = function(res) {
		                if (!res || !res.html) { $modalBody.removeClass('loading-process'); return; }
		                var $html      = $('<div>' + res.html + '</div>');
		                var $newBody   = $html.find('.modal-body');
		                var $newFooter = $html.find('.modal-footer');

		                if ( $newBody.length ) {
		                    // İçeriği güncelle, class'ları merge et
		                    var extraClasses = $newBody.attr('class')
		                        .replace('modal-body', '').trim();
		                    if ( extraClasses ) $modalBody.addClass(extraClasses);
		                    $modalBody.html( $newBody.html() ).removeClass('loading-process');
		                } else {
		                    $modalBody.html( res.html ).removeClass('loading-process');
		                }

		                if ( $newFooter.length ) {
		                    var $existFoot = $modalContent.find('.modal-footer');
		                    if ( $existFoot.length ) {
		                        $existFoot.html( $newFooter.html() ).removeClass('d-none');
		                    } else {
		                        $modalContent.append( $newFooter );
		                    }
		                }

		                // Source pagination — scroll-based yükleme
		                setTimeout(function() { _initSourcePagination($modalBody, 'modal'); }, 50);
		            };
		            q.request();
		        });
		        } // _openSourceModal end

		        // CSS lazy load tamamlanmış mı? Tamamlandıysa direkt aç,
		        // değilse yüklenen <link> onload'ını bekle
		        var _dynCssLink = document.querySelector('link[data-sh-dynamic-css]');
		        if ( _dynCssLink && !_dynCssLink.sheet ) {
		            _dynCssLink.addEventListener('load', _openSourceModal, { once: true });
		        } else {
		            _openSourceModal();
		        }
		    });
		    // ─────────────────────────────────────────────────────────────────

		    $doc
		    .on('show.bs.modal', '.modal', function (e) {
		        // Offcanvas açıksa kapat
		        const openOffcanvas = document.querySelector('.offcanvas.show');
		        if (openOffcanvas) {
		            bootstrap.Offcanvas.getOrCreateInstance(openOffcanvas).hide();
		        }

		        // ── Per-button custom CSS variables — modal görünmeden önce uygula ──
		        var $modal   = $(e.target);
		        var modalId  = $modal.attr('id') || '';
		        var $trigger = $();

		        // 1. Standart BS5 trigger
		        if ( modalId ) {
		            $trigger = $('[data-bs-target="#' + modalId + '"], [href="#' + modalId + '"]').first();
		        }
		        // 2. Bootbox / data-bs-source-modal — son tıklanan source modal trigger'ı
		        if ( ! $trigger.length && window._lastSourceModalBtn ) {
		            $trigger = window._lastSourceModalBtn;
		        }

		        log('[customStyle show.bs.modal] modalId=' + modalId + ' trigger found=' + $trigger.length, 'info');

		        if ( $trigger.length ) {
		            var _modalStyle = $trigger.data('modal-style') || '';
		            log('[customStyle show.bs.modal] style=' + _modalStyle, 'info');
		            if ( _modalStyle ) {
		                _applyCustomStyle($modal.find('.modal-content'), _modalStyle);
		            }
		        }		    })
		    .on('shown.bs.modal', '.modal', function (e) {
		        const $modal = $(e.target);//$(this);
		        const visibleModals = $('.modal:visible');
		        const modalCount = visibleModals.length;

		        const el = e.currentTarget;
			    if (window.bsStateStack.indexOf(el) === -1) {
			        window.bsStateStack.push(el);
			        history.pushState({ bsOpen: true, type: 'modal' }, "");
			    }

		// ── Source-based modal content loading — tek yol: loadSourceContent ──
		        const source = $modal.data('source');
		        if (source) {
		            const $body = $modal.find('.modal-body');
		            if ($body.length) {
		                loadSourceContent($body, source, 'modal', $modal);
		            }
		        }
		        // ─────────────────────────────────────────────────────────────
		        
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

		        // Per-button custom style temizle — bir sonraki açılışta temiz başlasın
		        var $mc = $modal.find('.modal-content');
		        if ( $mc.length ) {
		            ['--modal-bg','--modal-bg-gradient','--modal-bg-image',
		             '--modal-border-radius','--modal-box-shadow','--modal-color',
		             '--modal-header-bg','--modal-header-color','--modal-header-padding',
		             '--modal-padding','--modal-footer-bg','--modal-footer-padding',
		             '--modal-transition','--modal-fade-transform'].forEach(function(prop) {
		                $mc[0].style.removeProperty(prop);
		            });
		        }
		        window._lastSourceModalBtn = null;

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

		    // ── Responsive offcanvas (.offcanvas-{bp}) dismiss fix ──────────────
		    // Bootstrap'in native data-bs-dismiss="offcanvas" handler'ı sadece base
		    // `.offcanvas` class'ını arar. `.offcanvas-md` gibi responsive class'larda
		    // (page-settings sidebar) close butonu çalışmaz. Burada manuel kapatıyoruz.
		    $(document).on('click', '[data-bs-dismiss="offcanvas"]', function (e) {
		        const $target = $(e.target).closest('[data-bs-dismiss="offcanvas"]');
		        // En yakın offcanvas kabını bul (responsive dahil)
		        const $oc = $target.closest('.offcanvas, .offcanvas-sm, .offcanvas-md, .offcanvas-lg, .offcanvas-xl, .offcanvas-xxl');
		        if ($oc.length && !$oc.hasClass('offcanvas')) {
		            // Sadece responsive (base .offcanvas olmayan) durumda müdahale et;
		            // normal .offcanvas'ı Bootstrap kendi hallediyor.
		            e.preventDefault();
		            e.stopPropagation();
		            const inst = bootstrap.Offcanvas.getInstance($oc[0]) || bootstrap.Offcanvas.getOrCreateInstance($oc[0]);
		            if (inst) inst.hide();
		        }
		    });

		    $(document)
		    .on('show.bs.offcanvas', '.offcanvas', function (e) {
		        const $obj = $(e.target);
		        const $container = $obj.find(".offcanvas-body");

		        $body.addClass("offcanvas-open");

		        // ── Per-button custom CSS variables ──────────────────────────
		        var _ocTrigger = $('[data-bs-target="#' + $obj.attr('id') + '"], [data-salt-offcanvas="' + $obj.attr('id') + '"]').first();
		        if ( _ocTrigger.length ) {
		            var _ocStyle = _ocTrigger.data('offcanvas-style') || '';
		            if ( _ocStyle ) { _applyCustomStyle($obj, _ocStyle); }
		        }

		        // Dropdown içinden açıldıysa dropdown'ı kapat
		        const $openDropdown = $('.dropdown-menu.show').closest('.dropdown').find('[data-bs-toggle="dropdown"]');
		        if ($openDropdown.length) {
		            bootstrap.Dropdown.getOrCreateInstance($openDropdown[0]).hide();
		        }
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

		        // ── Source offcanvas'ları — TEK YOL: loadSourceContent ──────────────
		        // Kaynak (source) offcanvas'ının id'si source slug'ıdır:
		        //   offcanvas-cart → id='cart', offcanvas-reviews → id='reviews',
		        //   reaction → id='reaction-{type}' → loadSourceContent normalize eder.
		        // Named class'lı SABIT offcanvas'lar (offcanvas-{source}) ile dinamik
		        // source offcanvas'ları AYNI generic yoldan yüklenir. cart özel handler'ı
		        // loadSourceContent içindeki SOURCE_REGISTRY'de (window.cart) çözülür.
		        //
		        // YENI SISTEM MUAFIYETI: sh-oc-* prefix'li offcanvas'lar SaltOffcanvas.js
		        // tarafından yönetilir. loadSourceContent onlara KARIŞMAZ.
		        var _ocId = $obj.attr('id') || '';
		        var _isSaltManaged = _ocId.indexOf('sh-oc-') === 0;
		        // Source OLMAYAN sistem offcanvas'ları (search/menu vb.) generic yola girmesin.
		        var _isSystemOc = $obj.hasClass('offcanvas-search') || $obj.hasClass('offcanvas-menu') ||
		                          $obj.hasClass('offcanvas-show-header');

		        if (_ocId && !_isSaltManaged && !_isSystemOc && $container.children().length === 0) {
		            // source slug'ı = offcanvas id'si (_button.twig source offcanvas'a id={source} verir;
		            // reaction için id='reaction-{type}' → loadSourceContent normalize eder).
		            var _source = _ocId;
		            // Eski/statik named offcanvas'lar (id camelCase olabilir) için offcanvas-{source}
		            // class'ından source türet — yalnızca bilinen named source'lar (güvenli).
		            var _named = ['cart','favorites','notifications','messages','reviews'];
		            for (var _i = 0; _i < _named.length; _i++) {
		                if ($obj.hasClass('offcanvas-' + _named[_i])) { _source = _named[_i]; break; }
		            }
		            // Trigger li'yi bul — data-post-type/role/taxonomy oradan okunur.
		            var _trigger = $('[data-bs-target="#' + _ocId + '"], [data-salt-offcanvas="' + _ocId + '"]').closest('li[data-type]').first();
		            if (!_trigger.length) _trigger = $('[data-bs-target="#' + _ocId + '"]').first();
		            loadSourceContent($container, _source, 'offcanvas', _trigger.length ? _trigger : $obj);
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
			log("🚀 İşlem Başladı: " + obj.method, 'info');
		})
		$(document).on('ajax_query:complete', function(event, obj) {
		    log("✅ İşlem Bitti: " + obj.method, 'info');
		    
		    // Smart AJAX Init Manager ile yüklenen içeriği init et
		    if (typeof window.AjaxInitManager !== 'undefined') {
		        // AJAX response'dan container'ı tespit et
		        let $container = document;
		        
		        // Eğer response'da target container varsa onu kullan
		        if (obj && obj.objs && obj.objs.container) {
		            $container = obj.objs.container;
		        } else if (obj && obj.objs && obj.objs.obj) {
		            // Pagination gibi durumlarda obj.objs.obj container olabilir
		            $container = obj.objs.obj;
		        }
		        
		        // Smart init çalıştır
		        window.AjaxInitManager.initInContext($container);
		    } else {
		        // Fallback: Eski sistem
		        if(isLoadedJS("vanilla-lazyload")){
		            lazyLoadInstance.update();
		        }
		        $.fn.matchHeight._update();
		        if(isLoadedJS("swiper")){
		            init_swiper();
		        }
		        btn_ajax_method();
		        btn_loading_page();
		    }
		})
		.on('ajax_query:stop', function(event, obj) {
		    log("🛑 Tüm AJAX Trafiği Durdu.");
		});


        //woocommerce events — init_panel_interactions() icinde yonetiliyor
});







// WC select2'yi init ettikten sonra bootstrap-5 theme uygula
function shApplySelect2Bootstrap() {
    if (!$.fn.select2) return;
    $('select.select2-hidden-accessible').each(function(){
        var $sel = $(this);
        // Zaten bootstrap-5 uygulanmışsa atla
        if ($sel.data('select2') && $sel.data('select2').$container.hasClass('select2-container--bootstrap-5')) return;
        // Mevcut select2'yi destroy edip yeniden init et
        try {
            $sel.select2('destroy');
        } catch(e) {}
        $sel.select2({ theme: 'bootstrap-5' });
    });
}

// WC select2 init'inden sonra çalış
$(document).on('change', 'select[name="billing_country"], select[name="shipping_country"]', function(){
    setTimeout(shApplySelect2Bootstrap, 100);
});

// Sayfa ilk yüklenince WC init'i bitmeden önce polling yap
var _s2Timer = setInterval(function(){
    if ($('select.select2-hidden-accessible').length > 0) {
        clearInterval(_s2Timer);
        shApplySelect2Bootstrap();
    }
}, 100);
// 5 saniye sonra polling'i durdur
setTimeout(function(){ clearInterval(_s2Timer); }, 5000);