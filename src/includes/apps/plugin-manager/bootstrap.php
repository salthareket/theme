<?php

/**
 * Plugin Manager App Bootstrap
 *
 * variables.php'de şu şekilde include edilir (is_admin koşulunda):
 *   include_once SH_INCLUDES_PATH . 'apps/plugin-manager/bootstrap.php';
 *
 * Mevcut plugin-manager.php ve plugins.php backward-compat için korunur.
 * Bu bootstrap onların yerini alır.
 *
 * @version 1.0.0
 */

$pm_base = __DIR__ . '/';

require_once $pm_base . 'PluginManager.php';
require_once $pm_base . 'Admin/PluginManagerAdmin.php';

// Plugin listesini Data'ya kaydet — plugins.php'den okunur (kaynak dosya, repo'da güncellenir)
$pm_src = defined('SH_INCLUDES_PATH')
    ? dirname( SH_INCLUDES_PATH ) . '/plugins.php'
    : __DIR__ . '/../../../plugins.php';
if ( file_exists( $pm_src ) ) {
    include_once $pm_src;
}

// Admin UI + AJAX
if ( is_admin() ) {
    \SaltHareket\PluginManager\Admin\PluginManagerAdmin::register();
}
