<?php
// 1. ABSPATH Kontrolü (Zaten yapmışsın, mantıklı)
if ( !defined('ABSPATH') ) {
    // wp-load.php'yi bulmak için daha garantici bir yol
    $wp_load = dirname(__DIR__, 6) . '/wp-load.php';
    if(file_exists($wp_load)) {
        require_once $wp_load;
    }
}

// 2. PATH Tanımı (Dosya yollarını sabitleyelim ki kaymasın)
$base_path = __DIR__ . '/src/';

require_once $base_path . 'theme.php';
require_once $base_path . 'variables.php';
require_once $base_path . 'startersite.php';

if (is_admin()) {
    // Admin tarafında lazım olan ağır abiler
    require_once $base_path . 'update.php';
    require_once $base_path . 'plugin-manager.php';
    require_once $base_path . 'plugins.php';
}