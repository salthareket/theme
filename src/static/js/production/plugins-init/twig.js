if(typeof Twig !== "undefined"){
	/*Twig.extendFilter('trans', function(value, params) {
		if(site_config.dictionary.hasOwnProperty(value)){
			value = ajax_request_vars.dictionary[value];
		}
		return value;
	});*/
	twig = Twig.twig;		
}