function generateFiltersActive(query_vars){
	$(".card-header-filters").remove();
	if(!IsBlank(query_vars)){
		debugJS(query_vars)
		alert(Object.keys(query_vars).length)
		if(Object.keys(query_vars).length>0){
	        var code = '<div class="card-header card-header-filters">' +
	                       '<div class="filters">' +
	                            '<ul class="list-inline list-inline-tags">';
	                            debugJS(query_vars);
	                            for(var i=0;i<Object.keys(query_vars).length;i++){
	                            	var query_var = query_vars[i];
	                            	for(var z=0;z<query_var.terms.length;z++){
	                                    var item = query_var.terms[z];
	                                    code += '<li class="list-inline-item">' +
	                                                '<a href="#" class="search-option-tag" data-name="'+query_var.slug+'" data-value="'+item+'">' +
	                                                    '<small>'+query_var.name+'</small>';
			                                            if(query_var.slug == "fiyat" || query_var.slug == "fiyat_araligi"){
			                                                code += item.replace('|', '-')+" <span class='currency'>"+site_config.currency+"</span>";
			                                            }else{
			                                               code += item;
			                                            }
	                                        code += '</a>' +
	                                    '</li>';
	                                }
	                            }
	                        code +='<li class="list-inline-item">' +
	                                '<a href="#" class="search-option-tag-clear">Temizle</a>' +
	                            '</li>' +
	                        '</ul>' +
	                     '</div>' +
	                '</div>';
	                $(".card-layout-product-category").prepend(code);
	    }		
	}
}

var ajax_product_request;
function generateFilterUrl(obj, name, value, single, multiple){
	var pagination_type = site_config["pagination_type"];
	  		debugJS(obj, name, value, single, multiple);
			if(!IsBlank(multiple)){
               var url_new = multiple;
			}else{
				single = IsBlank(single)?false:single;
				var url         = window.location.href.split('?')[0];
				    url.replace("#","");
			    var querystring = url2json(window.location.href.replace("#",""));

			    /*if(querystring.length > 0){
			        var page = 1;
			   		if(url.indexOf("/page/") > -1){
		                var pageCheck = url.match(/\/page\/(\d*)/);
				   		if(pageCheck.length>1){
		                   page = pageCheck[1];
		                   url = url.replace( new RegExp(/\/page\/(\d*)/),"");
				   		}
			   		}
			    }*/
			    
			    if(single){
			       var url_new = url+"?"+name+"="+value;
			           //url_new = url_new.replace( new RegExp(/\/page\/(\d*)/),"");
			    }else{
			    	if(name == "fiyat" && querystring.hasOwnProperty("fiyat_araligi")){
			    		delete querystring["fiyat_araligi"];
			    	}
				    if(querystring.hasOwnProperty(name)){
				   	    	var values = querystring[name].split(",");
				   	    	if(obj.is(":checked")){
		                        values[values.length] = value;
		                        querystring[name] = values.join(",");
				   	    	}else{
				   	    		values = arrayRemove(values, value);
				   	    		debugJS(values)
				   	    		if(values.length == 0 || IsBlank(values)){
				   	    		  delete querystring[name];// arrayRemove(querystring, name);
				   	    		  debugJS(querystring)
				   	    		}else{
				   	    		  querystring[name] = values.join(",");
				   	    		}
				   	    	}
				   	    	if(Object.keys(querystring).length>0){
		                       var url_new = url+"?"+json2url(querystring); 
				   	    	}else{
		                       var url_new = url;
				   	    	}
				   	}else{
				   	    	if(Object.keys(querystring).length>0){
		                       var url_new = url+"?"+(Object.keys(querystring).length>0?json2url(querystring)+"&":"")+name+"="+value; 
				   	    	}else{
				   	    	   if(obj.is(":checked")){
		                         var url_new = url+"?"+name+"="+value; 
				   	    	   }else{
				   	    	   	 var url_new = url;
				   	    	   }
				   	    	}
				   	}		    	
				}
				if(!ajax){
					url_new = url_new.replace( new RegExp(/\/page\/(\d*)/),"");
				}
			}
		   	if(ajax){
		   		var page = 1;
		   		if(url_new.indexOf("/page/") > -1){
	                var pageCheck = url_new.match(/\/page\/(\d*)/);
			   		if(pageCheck.length>1){
	                   page = pageCheck[1];
			   		}
		   		}
		   		var vars = url2json(url_new.replace("#",""));
                    
		   		if(ajax && (pagination_type=="paged" || pagination_type=="load_more") && Object.keys(vars).length>0 && (!obj.hasClass("btn-load-more") && !obj.hasClass("page-link"))){
		   			page = 1;
                	url_new = url_new.replace( new RegExp(/\/page\/(\d*)/),"");
                }
                if(pagination_type=="load_more" || pagination_type=="scroll"){
                   url_new = url_new.replace( new RegExp(/\/page\/(\d*)/),"");
                }

                vars["template"] = "woo/archive-ajax";
                vars["page"] = page;
                vars["ajax"] = ajax;
                //vars["is_search"] = is_search;
                //add query var if tax query
                if(Object.keys(query_var).length>0){
                	var query_var_term = Object.keys(query_var)[0];
                    vars[query_var_term] = query_var[query_var_term];
                }

                history.pushState(null, null, url_new);
                
                if(!IsBlank($(".list-products").data("category"))){
                   vars["kategori"] = $(".list-products").data("category");
                }
                if(!IsBlank($(".list-products").data("keyword"))){
                   vars["keyword"] = $(".list-products").data("keyword");
                }
                if(ajax && pagination_type == "paged"){
		            $('html,body').animate({scrollTop:0},500);
		        }
		        if(!IsBlank($("#product-filters").hasClass("update-me"))){
                   vars["product_filters"] = true;
                }
		        if(typeof ajax_product_request !== "undefined"){
				    ajax_product_request.abort();
		        }
                ajax_product_request = $.post(host, { ajax : "query", method : 'get_products', vars : vars})
		        .done(function( response ) {
		        	if(isJson(response)){
	 				   response = $.parseJSON(response);							
					}
		        	//debugJS(response);
		        	if(response.hasOwnProperty("query_vars")){
                       if(IsBlank(response.query_vars)){
                          response.query_vars = {};
                       }
		        	}else{
		        	    response.query_vars = {};	
		        	}
                    
		        	$(".header-post-count").text(response.post_count);
		        	debugJS(response.query_vars)
		        	alert("response.query_vars")
		        	generateFiltersActive(response.query_vars);
		        	//debugJS(pagination_type, response.post_count, response.query_vars.length)
		        	switch(pagination_type){
		        		case "paged" :
	                        $(".list-products").html(response.html);
			                var footer = $(".list-products").find(".card-footer");//.insertAfter($(".card-layout-product-category").find(".card-footer"));
			                $(".card-layout-product-category").find(".card-footer").html(footer.html());
			                footer.remove();
			                $(".list-products").removeClass("loading-process");
		        		break;

		        	    case "load_more" :
		        	        if(response.post_count == 0 || response.query_vars.length < 2 || response.page == 1){
                                $(".list-products").html(response.html);
		        	        }else{
		        	        	$(".list-products").append(response.html);
		        	        }
		        	        var footer = $(".list-products").find(".card-footer");//.insertAfter($(".card-layout-product-category").find(".card-footer"));
			                $(".card-layout-product-category").find(".card-footer").html(footer.html());
			                footer.remove();
			                $(".btn-load-more").removeClass("loading disabled");
			                if(!IsBlank(response.sidebar)){
				                var $filters = $(response.sidebar).unwrap();
				                $("#product-filters").html($filters).removeClass("loading-process");
			                    product_filters_events();
				            }
			                
			                debugJS(obj);
			                $("#"+obj.attr("id")).closest(".collapse").collapse("show");
		        	    break;

		        	    case "scroll" :
		        	    debugJS(response)
		        	        $(".list-products").attr("data-page", response.page).attr("data-page-count", response.page_count);
		        	        //if(response.post_count == 0 || response.query_vars.length > 0 || response.page == 1){
		        	        if(($(".list-products").is(':empty') && response.page == 1) || $("#product-filters").hasClass("update-me")){
                                $(".list-products").html(response.html);
		        	        }else{
		        	        	$(".list-products").append(response.html);
		        	        }
		        	        var footer = $(".list-products").find(".card-footer");//.insertAfter($(".card-layout-product-category").find(".card-footer"));
			                $(".card-layout-product-category").find(".card-footer").html(footer.html());
			                footer.remove();
			                $(".btn-load-more").removeClass("loading-- disabled");
			                $(".list-products").removeClass("loading").removeClass("loading-process");
                            
                            if(!IsBlank(response.sidebar)){
				                //var $filters = $(response.sidebar).unwrap();
				                $(".container-product-filters").html(response.sidebar).removeClass("loading-process").removeClass("update-me");
				                product_filters_events();                            	
                            }

		        	    break;
		        	}
		        	$(".list-products").find(".fade").addClass("show");
		            //$("body").removeClass("loading");
		        });
		   	}else{
		   	   window.location.href = url_new;
		   	}
		   	switch(pagination_type){
		        case "paged" :
		   	        $(".list-products").addClass("loading-process");
		   	    break;
		   	    case "load_more" :
                    $(".btn-load-more").addClass("loading disabled");
                    $("#product-filters").addClass("loading-process");
		   	    break;
		   	    case "scroll":
                    $(".list-products").addClass("loading");
                    if(!IsBlank($("#product-filters").hasClass("update-me"))){
                       $("#product-filters").addClass("loading-process");
                       $(".list-products").addClass("loading-process");
                    }
		   	    break;
		   	}
}

		function product_filters_checkbox_event(){
		        var name = $(this).attr("name");
		   	    var value = $(this).val();
		   	    var count =  $(this).closest(".list-group-options").find("input[type='checkbox']:checked").length;
		   	    var counter = $(this).closest(".product-filter-item").find(".count");
		   	        counter.html(count==0?"":"("+count+")");
		   	    if(count>0){
                   $(this).closest(".product-filter-item-body").find(".remove-choices").removeClass("d-none");
		   	    }else{
		   	       $(this).closest(".product-filter-item-body").find(".remove-choices").addClass("d-none");
		   	    }
		   	    $("#product-filters").addClass("update-me");
		   	    generateFilterUrl($(this), name, value);	
		}
        function product_filters_events(){
			if($(".product-filters").length>0){
			   $(".product-filters").find(".color-image").find("label").popover({
			   	  animation : false,
			   	  html : true,
			   	  trigger : "hover",
			   	  template : '<div class="popover" role="tooltip"><div class="arrow"></div><div class="popover-body"></div></div>'
			   });
			   $(".product-filters").find("input[type='checkbox']").on("change", product_filters_checkbox_event);
			   //Min / Max price filter
			   $(".product-filters").find("a#price-filter").on("click", function(e){
	               e.preventDefault();
	               var min = parseFloat($(this).closest(".price-range").find("input[name='fiyat_min']").val());
	                   min = IsBlank(min)||isNaN(min)?0:min;
	               var max = parseFloat($(this).closest(".price-range").find("input[name='fiyat_max']").val());
	                   max = IsBlank(max)||isNaN(max)?0:max;
	                if(min>0 && max==0){
	                   max = min
	                }
	                if(min>max){
	                   var max_tmp=min;
	                   var min_tmp=max;
	                       min=min_tmp;
	                       max=max_tmp;
	                }
	                if(min>0 || max>0){
	               	   generateFilterUrl($(this), "fiyat_araligi", min+"|"+max, true);
	                }else{
	               	   _alert("Lütfen bir fiyat aralığı tanımlayınız.")
	                }
			   });
			   $(".product-filters").find(".remove-choices a").on("click", function(e){
			   	    e.preventDefault();
			   	    var url         = window.location.href.split('?')[0].replace("#","");
			        var querystring = url2json(window.location.href);
			        delete querystring[$(this).data("term")];
			        if(Object.keys(querystring).length>0){
		                var url_new = url+"?"+json2url(querystring); 
				   	}else{
				   	    var url_new = url;
				   	}
				   	var counter = $(this).closest(".product-filter-item").find(".count");
				   	var checked = $(this).closest(".list-group-options").find("input[type='checkbox']:checked");
				   	    checked.prop("checked", false);
				   	    counter.html("");
				   	$(this).parent().addClass("d-none");
				   	$(".product-filters").addClass("update-me loading-process");
				   	$(".list-products").addClass("loading-process");
				   	generateFilterUrl($(this), "", "", false, url_new);
				   	//$("body").addClass("loading");
				   	//window.location.href= url_new;
			   });
			   //selected terms
			   $(".search-option-tag").each(function(){
			   	   $(this).on("click", function(e){
			   	   	  e.preventDefault();
			   	   	  var name = $(this).data("name");
			   	      var value = $(this).data("value");
			   	      generateFilterUrl($(this), name, value);
			   	   });
			   });
			   $(".search-option-tag-clear").on("click", function(e){
			   	   e.preventDefault();
			   	   if($(".search-option-tag").length == 1){
			   	   	  if($(".search-option-tag").data("name") == "kategori"){
			   	   	  	 $("body").addClass("loading");
                         window.location.href = site_config.shop;
			   	   	  }
			   	   }else{
				   	   resetFormItems($(".product-filters"));
				   	   $(".product-filters").find(".remove-choices").addClass("d-none");
				   	   //$("body").addClass("loading");
				   	   var url_new = window.location.href.split('?')[0].replace("#","");
				   	   generateFilterUrl($(this), "", "", false, url_new);
				   	   //window.location.href = window.location.href.split('?')[0].replace("#","");			   	   	
				   	}
			   });
			   $("select[name='siralama']").on("change", function(){
			   	    var url         = window.location.href.split('?')[0].replace("#","");
			        var querystring = url2json(window.location.href);
			            querystring["siralama"] = $(this).val();
			        $("body").addClass("loading");
			        window.location.href = url+"?"+json2url(querystring);
			   });
			}
		}

$( document ).ready(function() {

	   if(typeof ajax == "undefined"){
	   	ajax = false;
	   }

		if(ajax){
			if($(".list-products").length > 0){
			   generateFilterUrl($(this), "", "", false, window.location.href);
			}
	    }

});