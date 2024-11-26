function stickyScroll(){
	var token_init = "sticky-scroll-init";
	if($(".stick-top").not("."+token_init).length > 0){
		if(typeof stickyOptions !== "undefined"){
			var $options_tmp = stickyOptions;
	        $(".stick-top").not("."+token_init).each(function(){
	        	$(this).addClass(token_init);
	            var $options = $options_tmp;
	        	var $args = $options["assign"]($(this));
	        	if(Object.keys($args).length>0){
	        	   $options = nestedObjectAssign({}, $options, $args);
	        	}
	           	$(this).hcSticky($options);
	           	debugJS($options)
	            $(this).hcSticky('update', $options);
	        });
	        //delete $options_tmp["assign"];			
		}

    }
}