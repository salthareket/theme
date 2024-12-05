function scrollable_init(){
	var token_init = "scrollable-init";
    $(".scrollable").not("."+token_init).each(function(e){
	    $(this).addClass(token_init);
        SimpleScrollbar.initEl($(this)[0]);
    });	
}