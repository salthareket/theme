function init_slinky(){
	var token_init = "slinky-menu-init";
    if($(".slinky-menu").not("."+token_init).length>0){
	    $(".slinky-menu").not("."+token_init).each(function(){
	        $(this).addClass(token_init);
			var slinky = $(this).slinky({
				title : true,
				theme: "slinky-theme-custom"
			});
			slinky.menu.find(".back").each(function(){
				var title = $(this).next(".title");
					title.appendTo($(this));
			});
			slinky.menu.find("a").not(".back").not(".next").on("click", function(){
				$(this).closest(".offcanvas").offcanvas("hide");
			});  
		});
	}         
}