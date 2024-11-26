function readmore_js(){
	//dependencies: readmore-js
    $(".readmore-js").each(function(){
    	var height = $(this).data("height") || 300;
    	if(IsBlank($(this).attr("id"))){
    		$(this).attr("id", generateCode(5));
    	}
        $(this).readmore({
            speed: 75,
            collapsedHeight: height,
            moreLink: '<a href="#" class="btn btn-link btn-slim float-right btn-more" style="display:inline-block;width:auto;margin-top:10px">Read more</a>',
            lessLink: '<a href="#" class="btn btn-link btn-slim float-right btn-less" style="display:inline-block;width:auto;margin-top:10px">Read less</a>',
            beforeToggle: function(trigger, element, expanded) {
	            debugJS(element)
	            if(expanded){
	            	$('html,body').animate({ scrollTop: element.offset().top }, 75);
	            }
            }
        });
    });
}