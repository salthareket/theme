<?php

/**
 * Dashboard App Bootstrap
 * Theme Settings menüsünün ilk (üst) alt sayfası.
 *
 * @version 1.0.0
 */

$_dash_base = __DIR__ . '/';
require_once $_dash_base . 'Admin/DashboardAdmin.php';

if ( is_admin() ) {
    \SaltHareket\Dashboard\Admin\DashboardAdmin::register();
}
