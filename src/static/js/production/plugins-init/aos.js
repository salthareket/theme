function init_aos(){
	AOS.init({
		offset: 0,
		easing: 'ease-out',
		duration: 500,
		once : true
	});

	$(".aos-hover")
	.on("mouseover", function(){
		if($(this).hasAttr("data-aos-delay")){
			$(this).attr("data-aos-delay", 0);
		}
	});	
}
