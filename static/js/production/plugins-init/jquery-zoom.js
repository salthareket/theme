$("[data-zoom]").each(function(){
	$(this).zoom({url: $(this).data("zoom")});
});