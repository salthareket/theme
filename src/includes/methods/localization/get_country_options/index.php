<?php
echo json_encode(get_countries($vars['continent'] ?? '', $vars['selected'] ?? '', $vars['all'] ?? false));
wp_die();
