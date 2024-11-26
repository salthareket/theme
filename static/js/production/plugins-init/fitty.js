function text_fit(){
	var token_init = "text-fit-init";
	$(".text-fit").not("."+token_init).each(function(){
        fitty($(this)[0]);
        $(this).addClass(token_init);
    });	
}