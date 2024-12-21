<?php

function compile_files_plugins($enable_production=false){

	$node_path = get_home_path() .'node_modules/';
	$node_path_uri = site_url() .'/node_modules/';
	if($enable_production){
	   $node_path = $node_path_uri;
	}

	$plugins = array();
	/*$plugins['bootstrap'] = [
		"c"   => false,
		"admin" => false,
		"url" => $node_path . 'bootstrap/dist/js/bootstrap.bundle.min.js',
		"css" => [],
		"class" => [],
		"attrs" => [],
		"init"  => "" 
	];*/
	
	return $plugins;
}
