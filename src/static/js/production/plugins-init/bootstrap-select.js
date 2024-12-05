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

if($(".tnp-field select").length>0){
     $(".tnp-field select").addClass("selectpicker")
}