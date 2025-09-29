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
	$plugins['moment'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'moment/min/moment.min.js'
		],
		"css" => [""],
		"class" => ["item-countdown"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['jquery-countdown'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'jquery-countdown/dist/jquery.countdown.min.js'
		],
		"css" => [""],
		"class" => ["countdown"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => []
	];
	$plugins['zuck.js'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'zuck.js/dist/zuck.min.js'
		],
		"css" => [
			$node_path . 'zuck.js/dist/zuck.min.css',
			$node_path . 'zuck.js/dist/skins/snapgram.min.css'
		],
		"class" => ["stories"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [
			"#zuck-modal-content",
			".story-viewer",
			".modal-stories",
			".btn-close",
			".btn-close-*"
		],
		"required" => [
			"bootbox",
			"swiper"
		]
	];

	$plugins['bootstrap3-typeahead'] = [
   		"c"	=> true,
   		"admin" => false,
		"url" => [
			$node_path . 'bootstrap-3-typeahead/bootstrap3-typeahead.js'
		],
		"css" => [
			$node_path . 'typeahead.js-bootstrap4-css/typeaheadjs.css',
		],
		"class" => ["typeahead"],
		"attrs" => [],
		"init"     => "",
		"whitelist" => [
			".typeahead",
			".typeahead-*",
			".dropdown-menu",
			".dropdown-item"
		]
	];

	return $plugins;
}
