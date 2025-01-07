<?php

require_once dirname(__DIR__, 6) . '/wp-load.php'; // WordPress'i yükle

require_once 'src/update.php';
require_once 'src/plugin-manager.php';
require_once "src/variables.php";
require_once "src/plugins.php";
require_once 'src/Theme.php';
require_once 'src/startersite.php';

$dependencies = get_option('composer_dependencies');
if($dependencies){
	Update::init();
	foreach($dependencies as $package){
		Update::update_composer_lock($package["package"], $package["latest"]);
    }
    //update_option('composer_dependencies', []);
}