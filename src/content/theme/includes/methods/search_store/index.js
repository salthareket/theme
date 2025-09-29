{
	after : function(response, vars, form, objs){
    		console.log(objs.typeahead)
    		objs.typeahead.$menu.removeClass("loading").removeClass("not-found");
			console.log(response);
			if(response){
				return objs.typeahead.render(response).show();
				        		//return process(data);
			}else{
				objs.typeahead.$menu.empty().addClass("not-found").html(objs.typeahead.highlighter("",""));
	            return false;
			}
    }
}