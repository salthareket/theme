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
				// Dropdown icindeyse dropdown'i kapat, offcanvas icindeyse offcanvas'i kapat
				var $dropdown = $(this).closest(".dropdown");
				var $offcanvas = $(this).closest(".offcanvas");
				if ($dropdown.length > 0) {
					var $toggle = $dropdown.find("[data-bs-toggle='dropdown']");
					if ($toggle.length) {
						bootstrap.Dropdown.getOrCreateInstance($toggle[0]).hide();
					}
				} else if ($offcanvas.length > 0) {
					$offcanvas.offcanvas("hide");
				}
			});
			$(this).data("slinky", slinky);
		});
	}         
}