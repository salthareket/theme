<?php
$meta = [];
if(isset($vars["meta"])) {
   $meta = $vars["meta"];
}
echo json_encode(SaltHareket\Theme::get_site_config(1, $meta));
die();
