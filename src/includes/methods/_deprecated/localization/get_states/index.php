<?php
$woo_countries = new WC_Countries();
echo json_encode($woo_countries->get_states($vars['id'] ?? ''));
wp_die();
