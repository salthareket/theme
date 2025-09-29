function init_twig(){
	if(typeof Twig !== "undefined"){
		Twig.extendFunction('translate', translate);
		twig = Twig.twig;
	}	
}
