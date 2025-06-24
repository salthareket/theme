function init_jquery_zoom(){
	var token_init = "jquery-zoom-init";
	$("[data-zoom]").not(".jquery-zoom-init").each(function(){
		$(this)
		.addClass(token_init)
		.trigger('zoom.destroy')
		.zoom({
			url : $(this).data("zoom"),
	        on  : "click"
		});
	});	
}
