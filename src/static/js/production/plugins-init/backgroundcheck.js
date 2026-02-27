function bg_check(){
	if(isLoadedJS("background-check")){
		var token_init = "bg-check-init";
		var hash = {};
		$("[data-bg-check]").not("."+token_init).each(function (i, div) {
			$(this).addClass(token_init);
		    $.each($(div), function (j, obj) {
		        var attr = obj.getAttribute('data-bg-check');
		        if(IsBlank(attr)){
		        	setTimeout(function(){
		        		$(this).imagesLoaded( { background: false }, function() {
				        	BackgroundCheck.init({
								targets: domObjectToSelector(div)
							});
						});
			        }, 100);
		        }else{

			        //if (!(attr in hash)) hash[attr] = [];
			        // Güvenli ve modern versiyon
					if (!Object.prototype.hasOwnProperty.call(hash, attr)) {
					    hash[attr] = [];
					}
			        var dom = domObjectToSelector(obj);
			        if($(dom).length > 0){
			        	hash[attr].push(dom);	      
			        }
		        }
		    });
		});
		
		if(Object.keys(hash).length > 0){
			for(var obj in hash){
				if(typeof hash[obj] == "object"){
					if($(obj).length > 0){
						if($(obj).hasClass("lazyload") || $(obj).hasClass("lazyloading") || ($(obj).hasClass("lazy") && !$(obj).hasClass("loaded"))){
							$.each($(hash[obj]), function (j, i) {
	                            $(i).removeClass(token_init);
							});
						}else{
							$(obj).imagesLoaded( { background: false, targets : hash }, function(e) {
								BackgroundCheck.init({
									targets: Object.keys(e.options.targets).join(","),
									images:  obj
							    });
							});
						}						
					}
				}
			}
		}		
	}
}