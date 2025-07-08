function init_slinky(){
	if($(".slinky-menu").length>0){
		if(!$(".slinky-menu").hasClass("slinky-menu-inited")){
			var slinky = $('.slinky-menu').slinky({
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
			$('.slinky-menu').addClass("slinky-menu-inited");
		}           
	}
}