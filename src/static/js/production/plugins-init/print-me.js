function printThis(){
	//dependencies: print-me
	var token_init = "print-init";
	if($("[data-print]").not("."+token_init).length > 0){
        $("[data-print]").not("."+token_init).each(function(){
        	var $obj = $(this);
        	$obj.addClass(token_init);
			$obj.on("click", function(e){
	            e.preventDefault();
	            var $args = {};
	            var target = $(this).data("print");
	            var title = $(this).data("print-title");
	            var header = title
	            if(title){
	            	$args["pageTitle"] = title;
	            }
	            if(header){
	            	$args["header"] = "<h3>"+header+"</h3>";
	            }
	            $(target).printThis($args);
			});
	    });
    }
}