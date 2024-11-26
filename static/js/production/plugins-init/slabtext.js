function slab_text(){
	var token_init = "slab-text-init";
    if($(".slab-text-container").not("."+token_init).length>0){
        $(".slab-text-container").not("."+token_init).each(function(){
        	$(this).addClass(token_init);
        	var maxFontSize = $(this).data("max-font-size");
        	if(typeof maxFontSize === "undefined"){
        		const computedStyle = window.getComputedStyle(this);
       			maxFontSize = computedStyle.getPropertyValue('font-size');
        	}
        	var options = {
	            viewportBreakpoint:380
	        };
	        if(!IsBlank(maxFontSize)){
	        	options["maxFontSize"] = maxFontSize;
	        }
	        debugJS(options);
	        $(this).slabText(options);
	        $(window).trigger("resize");
        });
    }
}