<?php
$required_setting = ENABLE_IP2COUNTRY;

$path = getSiteSubfolder();
$ttl  = time() + (86400 * 365);

qtranxf_setLanguage($vars['language']);

// Eski cookie'leri temizle, yenilerini yaz
setcookie('user_country', '', time() - 3600);
setcookie('user_country_code', '', time() - 3600);
setcookie('user_language', '', time() - 3600);

setcookie('user_country', $vars['country'], $ttl, $path);
setcookie('user_country_code', $vars['countryCode'], $ttl, $path);
setcookie('user_language', $vars['language'], $ttl, $path);
setcookie('user_region', json_encode(get_region_by_country_code($vars['countryCode'])), $ttl, $path);

$response['redirect'] = qtrans_convert_url($vars['language']);
echo json_encode($response);
wp_die();
