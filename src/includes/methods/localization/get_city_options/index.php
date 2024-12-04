<?php
if(!isset($vars["country"])){
   $check = array_column($vars, 'country');
   if($check){
      $vars["country"] = $check[0];
   }
}
            $localization = new Localization();
            $localization->woocommerce_support(false);
            echo json_encode($localization->states([
                "country_code" => $vars["country"]
            ]));
            //echo json_encode(get_cities($vars["country"], $vars["selected"]));
            die();