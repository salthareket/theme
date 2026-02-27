$.fn.fitEmbedBackground = function() {
        return this.each(function() {
            var container = $(this),
                iframe = container.find('iframe');

            // Video boyutunu yeniden hesapla
            function resizeVideo() {
                var containerWidth = container.width(),
                    containerHeight = container.height(),
                    containerRatio = containerWidth / containerHeight,
                    videoRatio = 16 / 9;

                // Genişlik/Yükseklik oranlarına göre iframe boyutunu ayarla
                if (containerRatio > videoRatio) {
                    iframe.css({
                        width: containerWidth + 'px',
                        height: (containerWidth / videoRatio) + 'px'
                    });
                } else {
                    iframe.css({
                        width: (containerHeight * videoRatio) + 'px',
                        height: containerHeight + 'px'
                    });
                }
            }

            // İlk çalıştırma
            resizeVideo();

            // Gecikmeli resize
            var debounce = resizeDebounce(resizeVideo, 10);

            // Resize ve fullscreen olaylarını dinle
            $(window).on('resize', debounce);
            $(document).on(
                'fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange',
                debounce
            );

        });
};

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
	/*var token_init = "btn-loading-page-init";
	$(".btn-loading-page").not("."+token_init).each(function(){
        $(this)
        .on("click", function(e){
        	$("body").removeClass("init");
        	if(IsUrl($(this).attr("href"))){
        		if($(this).attr("target") == "_blank"){
        			e.preventDefault();
        			redirect_polyfill($(this).attr("href"), true);
        		}else{
        			$("body").addClass("loading-process");
        			setTimeout(() => {
					    window.location.href = $(this).attr("href");
					}, 50);

        		}
        	}
        })
        .addClass(token_init);
	});*/
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

	function init_ajax_v1($obj, $button){
		console.log("btn_ajax_method="+$obj.data("ajax-method"))
		console.log("obj")
		console.log($obj)
		console.log("button")
		console.log($button)
		    var $data = $obj.data();
        	var $form = {};
        	if ($data && Object.prototype.hasOwnProperty.call($data, "form")) {
			    $data["form"] = $($data["form"]);
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

	function init_ajax($obj, $button){
	    // 1. Önce butondaki TÜM datayı temizce alalım
	    var $data = JSON.parse(JSON.stringify($obj.data())); 
	    
	    // 2. Metodun pagination_ajax olduğundan emin olalım
	    var currentMethod = $obj.attr("data-ajax-method"); 
	    
	    var callback = function(){
	        var query = new ajax_query();
	        query.method = currentMethod; // Direkt attribute'dan oku, risk alma
	        query.vars = $data;
	        
	        // Pagination için butonun kendisi lazım, paketleme yapma direkt gönder
	        query.objs = $obj; 
	        
	        console.log("Giden Sorgu:", query.method, query.vars); // Buraya bak abi ne gidiyor?
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
	        	let $btn = $(this);
	        	console.log("buttona tıklandı")
			    init_ajax($btn, true);
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
					/*case "scroll":
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
					break;*/
					case "scroll":
					    if(!btn.hasClass("ajax-load-scroll")){
					        btn.addClass("ajax-load-scroll");
					        const checkAjaxLoadViewport = function() {
					            if( btn.is(":in-viewport")) {
					                ajax_paginate_load(btn);
					            }
					        };
					        $(window).on('scroll resize', throttle(checkAjaxLoadViewport, 100));
					        setTimeout(checkAjaxLoadViewport, 150); 
					    } else {
					        ajax_paginate_load(btn); 
					    }
					break;
				}

		    }
		});
	}
}

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

/**
 * @function table_responsive_stack
 * @description Tabloları akıllıca kart yapısına (stack) dönüştürür.
 */
 function table_responsive_stack() {
    // Sınıfı içeren tüm tabloları yakala (Stack, Auto veya Breakpoint fark etmeksizin)
    const tables = document.querySelectorAll('[class*="table-responsive-stack"]');
    if (!tables.length) return;

    // --- İç Fonksiyon: Başlık Senkronu (data-label) ---
    const syncHeaders = (table) => {
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => {
            row.querySelectorAll('td').forEach((td, index) => {
                if (headers[index] && !td.getAttribute('data-label')) {
                    td.setAttribute('data-label', headers[index]);
                }
            });
        });
    };

    // --- Ana Döngü ---
    tables.forEach(table => {
        syncHeaders(table);

        const classList = Array.from(table.classList);
        const hasAuto = classList.some(cls => cls === 'table-responsive-stack-auto');
        const hasSpecificBp = classList.some(cls => /table-responsive-stack-(sm|md|lg|xl)/.test(cls));
        
        // 1. SÜPER ZEKA (Auto Modu)
        if (hasAuto) {
            const checkAuto = () => {
                const isOverflowing = table.parentElement.scrollWidth > table.parentElement.clientWidth;
                table.classList.toggle('is-stacked', isOverflowing);
            };
            window.addEventListener('resize', checkAuto);
            checkAuto();
        } 
        
        // 2. DEFAULT DURUM (Eğer ne Auto ne de manuel BP verilmişse)
        // Sadece .table-responsive-stack yazıldıysa 'lg' default olsun
        else if (!hasSpecificBp && table.classList.contains('table-responsive-stack')) {
            table.classList.add('table-responsive-stack-lg'); 
        }
    });
}





/**
 * UI Ajax Yönetim Sistemi
 * Favorites, Cart ve Messages için merkezi yönetim
 */

// 1. TEMEL YÖNETİCİ (Tüm ortak işler burada)
class BaseManager {
    constructor() {
        this.nonce = typeof ajax_request_vars !== 'undefined' ? ajax_request_vars.ajax_nonce : '';
        this.host = typeof host !== 'undefined' ? host : '';
    }

    // Ortak AJAX isteği metodu
    async request(obj, data) {
        // Standart veri eklemeleri
        data["vars"] = data["vars"] || {};
        data["vars"]["ajax"] = true;
        data["ajax"] = "query";
        data["_wpnonce"] = this.nonce;

        try {
            const response = await $.post(this.host, data);
            const parsed = typeof response === "string" ? JSON.parse(response) : response;

            // Global hata görünümü kontrolü
            if (typeof errorView === "function" && errorView(parsed)) {
                obj.removeClass("disabled loading loading-process");
                return null;
            }
            return parsed;
        } catch (error) {
            console.error("UI Request Error:", error);
            obj.removeClass("disabled loading loading-process");
            // alert("error"); // İsteğe bağlı
            return null;
        }
    }

    // Bildirim sayılarını güncelleyen ortak metod
    updateBadge(type, count) {
        const container = $(`.dropdown-notifications[data-type='${type}'] > a`);
        let counter = container.find(".notification-count");

        if (count > 0) {
            if (counter.length === 0) {
                container.prepend("<div class='notification-count'></div>");
                counter = container.find(".notification-count");
            }
            counter.html(count);
        } else if (counter.length > 0) {
            counter.remove();
        }
    }
}

// 2. FAVORİ YÖNETİMİ
class FavoritesManager extends BaseManager {
    constructor() {
        super();
        this.classTease = ".card-profile-tease";
    }

    async add(obj) {
        const id = obj.data("id");
        obj.addClass("disabled loading");
        
        const res = await this.request(obj, { method: "favorites_add", vars: { id } });
        if (res) {
            obj.removeClass("disabled loading").addClass("active");
            $(`.btn-favorite[data-id='${id}']`).addClass("active");
            this.sync(res.data);
            this.updateBadge("favorites", res.count);
            if (res.html) obj.find(".info").html(res.html);
            this.notify(res.message, 'add');
        }
    }

    async remove(obj) {
        const id = obj.data("id");
        obj.addClass("disabled loading");
        if (obj.data("type") == "favorites") obj.closest(this.classTease).addClass("loading-process");

        const res = await this.request(obj, { method: "favorites_remove", vars: { id } });
        if (res) {
            obj.removeClass("active disabled loading");
            $(`.btn-favorite[data-id='${id}']`).removeClass("active");
            
            // Eğer sayfa favoriler sayfasıysa kartı kaldır
            if (obj.data("type") == "favorites") {
                obj.closest(this.classTease).parent().remove();
            }

            this.sync(res.data);
            this.updateBadge("favorites", res.count);
            this.notify(res.message, 'remove');
        }
    }

    sync(data) {
        if (typeof site_config !== 'undefined') site_config.favorites = data;
        $(".dropdown-notifications[data-type='favorites']").toggleClass("active", data.length > 0);
    }

    notify(msg, type) {
        if (typeof toast_notification === "function") {
            const img = type === 'add' ? 'favorites-add.jpg' : 'favorites-remove.jpg';
            toast_notification({
                message: msg,
                sender: { image: `<img src='${ajax_request_vars.theme_url}/static/img/notification/${img}' class='img-fluid' />` }
            });
        }
    }
}
var favorites = new FavoritesManager();

// 3. SEPET YÖNETİMİ
class CartManager extends BaseManager {
    async get(obj, type) {
        obj.addClass("loading-process");
        const res = await this.request(obj, { method: "get_cart", vars: { type } });
        if (res) this.render(obj, res);
    }

    async removeItem(obj, type) {
        const key = obj.data("key");
        const container = obj.closest(".load-container");
        obj.addClass("loading-process");
        
        const res = await this.request(container, { method: "wc_cart_item_remove", vars: { key, type } });
        if (res) this.render(container, res);
    }

    render(obj, res) {
        const $html = $("<div class='temp'>" + res.html + "</div>");
        let footer = "";
        
        if ($html.find(".offcanvas-footer").length > 0) {
            footer = $html.find(".offcanvas-footer").html();
            $html.find(".offcanvas-footer").remove();
        }

        obj.html($html.html()).removeClass("loading-process");
        const footerObj = obj.next(".offcanvas-footer");
        if (footerObj.length) footerObj.html(footer).toggleClass("d-none", !footer);

        const count = (res.data && res.data.count) || 0;
        this.updateBadge("cart", count);
        $(".dropdown-notifications[data-type='cart'] .dropdown-container").toggleClass("has-dropdown-item", count > 0);
    }
}
var cart = new CartManager();

// 4. MESAJ YÖNETİMİ
class MessageManager extends BaseManager {
    async get(obj) {
        obj.addClass("loading-process");
        const res = await this.request(obj, { 
            method: "get_messages", 
            vars: { template: "partials/offcanvas/archive" } 
        });
        
        if (res) {
            obj.html(res.html).removeClass("loading-process");
            this.updateBadge("messages", (res.data && res.data.count) || 0);
            if (typeof SimpleScrollbar !== 'undefined') {
                const scrollEl = obj.find(".dropdown-body")[0];
                if (scrollEl) SimpleScrollbar.initEl(scrollEl);
            }
        }
    }
}
var messages = new MessageManager();