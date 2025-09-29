{
    	before : function(response, vars, form, objs){
    		var dialog = bootbox.dialog({
		        className : "modal-store page-store modal-fullscreen- loading",
				message: '<div></div>',
				closeButton: false,
				backdrop : true,
				size: 'xl',
	            buttons : {},
	            onHidden: function(e) {
			        response.abort();
			    } 
			});
    	},
    	after : function(response, vars, form, objs){

    		twig({
		        href: ajax_request_vars.theme_url + "theme/templates/magazalar/single-modal.twig",//"/theme/templates/stories-modal.twig",
		        async: false,
		        allowInlineIncludes: true,
		        load: function (template) {
		        	let data = window["store_"+vars["store_id"]];
		        	console.log(vars)
		        	console.log(data)
		            var html = template.render({ data: data });
		    	    $(".modal-store").find(".modal-content").html(html);
		    		$(".modal-store").removeClass("loading");
		    		check_stories();
		    		store_story_link();
		    	}
		    });
    	}
    };