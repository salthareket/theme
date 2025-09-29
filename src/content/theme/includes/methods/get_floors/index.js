{
    	before : function(response, vars, form, objs){
    		$("body").addClass("loading");
    	},
    	after : function(response, vars, form, objs){
            $(".search-result-text").html(response.data);
    		$(".container-floors").addClass("search-results").html(response.html);
    		$("body").removeClass("loading");
            store_modal_link();
    	}
    };