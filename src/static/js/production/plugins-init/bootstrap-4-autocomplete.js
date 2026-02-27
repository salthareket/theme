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
							data = JSON.parse(data);
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
					if(IsBlank(this.query)){
						var template = "empty";
					}else{
						if($item == ""){
							var template = "notfound";
						}else{
							 template = "product";
						}
					}
					return template_render(template, $item);
				},
				displayText: function($item){
					return $item.name
				},
				afterSelect: function($item){
					// Güvenli kontrol: Objenin tepesinden çağırıyoruz
					if ($item && Object.prototype.hasOwnProperty.call($item, "url")) {
					    $("body").addClass("loading");
					    window.location.href = $item.url;
					} else {
					    // Form bazlı yönlendirme mantığı
					    $("body").addClass("loading");
					    
					    // Güvenlik: closest("form") her zaman bir sonuç dönmeyebilir, null check iyidir
					    var $form = this.$element.closest("form");
					    var baseUrl = $form.attr("action") || "";
					    
					    // $item.name'in varlığını da kontrol ederek window.location'ı set ediyoruz
					    var itemName = ($item && $item.name) ? $item.name : "";
					    window.location.href = baseUrl + itemName;
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