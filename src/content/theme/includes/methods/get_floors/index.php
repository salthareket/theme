<?php

$search_filters = array();
		 	        $vars_new = array();
		 	        $vars_new["ignore_empty_floor"] = true;
		 	        if(isset($vars["magaza_tipi"])){
		 	        	if($vars["magaza_tipi"]){
			 	        	if(!isset($vars_new["taxonomy"])){
			 	        	   $vars_new["taxonomy"] = array();
			 	        	}
			 	           	$vars_new["taxonomy"]["magaza-tipi"] = $vars["magaza_tipi"];
		 	            }
		 	        }
		 	        if(isset($vars["katlar"])){
		 	        	if($vars["katlar"]){
		 	        	   $vars_new["id"] = $vars["katlar"];
		 	        	   $search_filters["katlar"] = get_the_title( $vars["katlar"] );
		 	            }
		 	        }
		 	        if(isset($vars["hizmetler"])){
		 	        	if($vars["hizmetler"]){
			 	           	if(!isset($vars_new["taxonomy"])){
			 	        	   $vars_new["taxonomy"] = array();
			 	        	}
	                        $vars_new["taxonomy"]["hizmetler"] = $vars["hizmetler"];
	                        $search_filters["hizmetler"] = get_term_by("slug", $vars["hizmetler"], "hizmetler")->name;
	                    }
		 	        }
		 	        if(isset($vars["kampanya"])){
		 	           	$vars_new["campaign"] = boolval($vars["kampanya"]);
		 	           	$search_filters["kampanya"] = $vars_new["campaign"];
		 	        }
		 	        $outlet = new Project();
		 	        $output = $outlet->katlar($vars_new);
		 	        if(!$template){
                        $template = "partials/floors";
				    }
				    $templates = array( $template.'.twig' );
				    $context = Timber::context();
				    $context['floors'] = $output["data"];
				    $context['store_count'] = $output["count"];
				    /*if($search_filters){
				    	$search_filters_text = "";
				        foreach($search_filters as $key => $filter){
				       	    switch($key){
					       	   	case "katlar" :
                                     $search_filters_text .= $filter." içinde ";
					       	   	break;
					       	   	case "hizmetler" :
					       	   	     $search_filters_text .= $filter." kategorisinde ";
					       	   	break;
					       	   	case "kampanya" :
					       	   	    if($filter){
                                       $search_filters_text .= "kampanyalı ";
					       	   	    }
					       	   	    $search_filters_text .= "<b>".$output["count"]."</b> mağaza bulundu.";
					       	   	break;
				       	    }
				        }
				    }*/
				    $search_filters_text = "";
				    if($search_filters){
				    	$search_filters_text = "";
				    	$trans_arr = array();
				        foreach($search_filters as $key => $filter){
				       	    switch($key){
					       	   	case "katlar" :
					       	   	     $trans_arr["%floor"] = "<b>".$filter."</b>";
                                     $search_filters_text .= "%floor içinde ";
					       	   	break;
					       	   	case "hizmetler" :
					       	   	     $trans_arr["%category"] = "<b>".$filter."</b>";
					       	   	     $search_filters_text .= "%category kategorisinde ";
					       	   	break;
					       	   	case "kampanya" :
					       	   	    if($filter){
					       	   	       $trans_arr["%campaign"] = "<b>kampanyalı</b> ";
                                       $search_filters_text .= "%campaign ";
					       	   	    }
					       	   	break;
				       	    }
				        }
	
				        if($search_filters_text){				        	
				        	$trans_arr["%count"] = "<b>".$output["count"]."</b>";
                            $singular = $search_filters_text."toplam %count adet mağaza bulundu.";
                            $plural = "";//$search_filters_text."toplam %counts adet mağaza bulundu.";
                            //replacement
				        	$search_filters_text = trans_plural($singular, $plural, $output["count"]);//trans( $search_filters_text, "", $output["count"] );
				        	$find       = array_keys($trans_arr);
                            $replace    = array_values($trans_arr);
                            $search_filters_text = str_ireplace($find, $replace, $search_filters_text);
				        	//$context['search_filters_text'] = $search_filters_text;
				        }
				    }

				    $response["html"] = Timber::compile($templates, $context);
				    $response["data"] = $search_filters_text;
				    echo json_encode($response);
				    wp_die();
				    