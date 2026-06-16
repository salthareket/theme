<?php
echo json_encode(get_districts($vars['city'] ?? ''));
wp_die();
