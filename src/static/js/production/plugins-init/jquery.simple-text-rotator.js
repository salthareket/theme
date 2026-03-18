function init_text_rotator(){
	var token_init = "text-rotator-init";
    if($(".text-rotator").not("."+token_init).length>0){
        $(".text-rotator").not("."+token_init).each(function(){
        	$(this).addClass(token_init);
			var obj = $(this);
				obj.textrotator({
				  animation: obj.data("text-rotator-animation"),
				  separator: "|",
				  speed: obj.data("text-rotator-speed") || 2000
				});
				obj.removeClass("invisible");
		});
	};
}