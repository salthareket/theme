<?php

function woo_wpc_bundle_images($product){
	$items = $product->meta("woosb_ids");
	foreach ($items as $key => $item) {
		$item = wc_get_product($item["id"]);
		$type = $item->get_type();
		switch($type){
			case "simple" :

			break;

			case "variable" :

			break;

			case "grouped" :

			break;

			case "external" :

			break;

			case "woosg" : //smart grouped

			break;
		}
	}
}