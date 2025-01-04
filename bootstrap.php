<?php

require_once dirname(__DIR__, 6) . '/wp-load.php'; // WordPress'i yükle

//require_once 'src/update.php';
require_once dirname(__DIR__, 3) . "/install/update.php";
require_once 'src/plugin-manager.php';
require      "src/variables.php";
require_once "src/plugins.php";
require_once 'src/Theme.php';
require_once 'src/startersite.php';