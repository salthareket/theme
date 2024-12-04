<?php
$salt = new Salt();
echo json_encode($salt->get_site_config(1));
die();
