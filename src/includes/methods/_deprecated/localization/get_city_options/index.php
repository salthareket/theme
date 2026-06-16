<?php
$country = $vars['country'] ?? null;
if (!$country) {
    $check = array_column($vars, 'country');
    $country = $check[0] ?? '';
}

$localization = new Localization();
$localization->woocommerce_support(false);
echo json_encode($localization->states(['country_code' => $country]));
wp_die();
