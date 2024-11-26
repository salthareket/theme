<?php



function category_queries_vars_v1($term, $vars){
	if(isset($vars) && !empty($vars)){
	  if(array_key_exists($term, $vars)){
	  	 return $vars[$term];
	  }
	}else{
       return get_query_var($term);
	}
}


function category_queries_vars($term, $vars){
	if(isset($vars) && !empty($vars)){
	  if($GLOBALS["ajax"]){
	  	if(array_key_exists($term, $vars)){
	  	   return $vars[$term];
	    }
	  }else{
		  foreach($vars as $var){
		    if($var["slug"] == $term){
	    	  	 return implode(",", $var["terms"]);
	    	  }
		  }	  	
	  }
	}else{
       return get_query_var($term);
	}
}


function category_queries_ajax($query=array(), $vars=array()){

	            if(empty($vars)){
	               $vars =  woo_sidebar_filter_vars();
	            }

                // Create Query
	            $query['post_type']      = array('product','product_variation');
	            $query['posts_per_page'] = $GLOBALS["site_config"]["pagination_count"];
			    $query['numberposts']    = $GLOBALS["site_config"]["pagination_count"];
			    $query['order']          = "DESC";
			    $query['orderby']        = "publish_date";

			    //$query['gmwsvsfilter'] = 'yes';

			    $keyword = category_queries_vars('keyword', $vars);
				if(!empty($keyword)){
                   $query['s'] = $keyword;
				}

                $meta_query = array();
                $tax_query = array();

			    //show only instock
			    $meta_query[] = array( 
			        array(
			            'key' => '_stock_status',
			            'value' => 'instock',
			            'compare' => '=',
			        ),
			        array(
			            'key' => '_backorders',
			            'value' => 'no',
			            'compare' => '=',
			        ),
			    );

			    //has kategori
				$kategori = category_queries_vars('kategori', $vars);
				if(!empty($kategori)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'product_cat',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $kategori)
					    )
					);
				}else{
                    if(!empty(get_query_var("product_cat"))){
                       $tax_query[] = array( 
							array(
						        'taxonomy'      => 'product_cat',
						        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
						        'terms'         => get_query_var("product_cat")
						    )
						);
                    }
				}

				//has brand
				$marka = category_queries_vars('marka', $vars);
				if(!empty($marka)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'product_brand',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $marka)
					    )
					);
				}else{
                    if(!empty(get_query_var("product_brand"))){
                       $tax_query[] = array( 
							array(
						        'taxonomy'      => 'product_brand',
						        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
						        'terms'         => get_query_var("product_brand")
						    )
						);
                    }
				}

                //get discounted products
                $durum = category_queries_vars('durum', $vars);
                if(!empty($durum)){
					$durumlar = explode(",", $durum);
					//$meta_query["relation"] = "OR";
					foreach($durumlar as $durum_item){
						if($durum_item == "indirimli-urunler"){
						    $meta_query[] = array( 
							    'relation' => 'OR',
							    array( // Simple products type
							        'key'           => '_sale_price',
							        'value'         => 0,
							        'compare'       => '>',
							        'type'          => 'numeric'
							    ),
							    array( // Variable products type
							        'key'           => '_min_variation_sale_price',
							        'value'         => 0,
							        'compare'       => '>',
							        'type'          => 'numeric'
							    )
							);
					    }
					    if($durum_item == "yeni-urunler"){
					       $query['order'] = "DESC";
				           $query['orderby'] = "date";
					    }
					    if($durum_item == "cok-satanlar"){
					    	$query['meta_key'] = "total_sales";
				            $query['orderby'] = "meta_value_num";
					    }
					    if($durum_item == "tukenmek-uzere"){
					    	    $low_stock = get_option('woocommerce_notify_low_stock_amount');
							    $meta_query[] = array(
							    	array(
									        array(
									            'key'     => '_stock',
									            'value'   => 'mt2.meta_value',
									            'compare' => '<=',
									            'type'    => 'numeric'
									        ),
									        array(
										      'key' => '_low_stock_amount', 
										      'compare' => 'EXISTS' 
										    ),
										    'relation' => 'AND'
								    ),
								    "relation" => "OR",
								    array(
								    	array(
								            'key'     => '_stock',
								            'value'   => $low_stock,
								            'compare' => '<=',
								            'type'    => 'numeric'
								        ),
								        array(
										     'key' => '_low_stock_amount',
										     'compare' => 'NOT EXISTS',
										     'value' => ''
										),
								        'relation' => 'AND'
								    )
							    );
						}
						if($durum_item == "ucretsiz-kargo"){
							$free_shipping_min_amount = get_free_shipping_amount();
							//$free_shipping_min_amount = alg_wc_get_left_to_free_shipping("%free_shipping_min_amount_raw%");
							if($free_shipping_min_amount>0){
							    $meta_query[] = array( 
							        array(
							            'key' => '_price', //sale price if yith applied
							            'value' => $free_shipping_min_amount,
							            'compare' => '>=',
								        'type'    => 'numeric'
							        ),
							        'relation' => 'AND'
							    );								
							}
						}
					}
				}

                //has price filter
				$fiyat = category_queries_vars('fiyat', $vars);

				if(empty($fiyat)){
					$fiyat = category_queries_vars('fiyat_araligi', $vars);
				}
				if(!empty($fiyat)){
					$fiyatlar = explode(",", $fiyat);
					$meta_query_item = array();
					foreach($fiyatlar as $fiyat_item){
						$meta_query_item[] = array(
					        'key'     => '_price',
					        'value'   => explode("|", $fiyat_item),
					        'compare' => 'BETWEEN',
					        'type'    => 'NUMERIC'
					    );
					}
					$meta_query_item["relation"] = "OR";
					$meta_query[] = $meta_query_item;
				}

				//has size
				$beden = category_queries_vars('beden', $vars);
				if(!empty($beden)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'pa_beden',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $beden)
					    )
					);
				}

				//has stone
				$tas = category_queries_vars('tas', $vars);
				if(!empty($tas)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'pa_tas',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $tas)
					    )
					);
				}

				//has material
				$materyal = category_queries_vars('materyal', $vars);
				if(!empty($materyal)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'pa_materyal',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $materyal)
					    )
					);
				}

				//has color
				$renk = category_queries_vars('renk', $vars);
				if(!empty($renk)){
					$tax_query[] = array( 
						array(
					        'taxonomy'      => 'pa_renk',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $renk)
					    )
					);
				}

				//has gender
				$cinsiyet = category_queries_vars('cinsiyet', $vars);
				if(!empty($cinsiyet)){
					$tax_query[] = array( 
						'relation' => 'AND',
						array(
					        'taxonomy'      => 'pa_cinsiyet',
					        'field'         => 'slug', // can be 'term_id', 'slug' or 'name'
					        'terms'         => explode(",", $cinsiyet),
					        'operator' => 'IN'
					    )
					);
				}

				//has siralama
				$siralama = category_queries_vars('siralama', $vars);
				if(!empty($siralama)){
					if($siralama == "isim-artan"){
						$query['order'] = "asc";
				        $query['orderby'] = "title";
					}
					if($siralama == "isim-azalan"){
						$query['order'] = "desc";
				        $query['orderby'] = "title";
					}
					if($siralama == "fiyat-artan"){
						$query['meta_key'] = '_price';
						$query['orderby']  = 'meta_value_num';
						$query['order']  = 'asc';
					}
					if($siralama == "fiyat-azalan"){
						$query['meta_key'] = '_price';
						$query['orderby']  = 'meta_value_num';
						$query['order']  = 'desc';
					}
				}
				/*$tax_query[] = array(
					'relation' => 'AND',
			        array(
			            'taxonomy' => 'product_type',
			            'field'    => 'slug',
			            'terms'    => array('simple','variable'),
			            'operator' => 'IN'
			        ),
			    );*/


                /*start : variations as single 
				$query['gmwsvsfilter'] = 'yes';
				$meta_query[] = array(
								'relation' => 'OR',
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => 'NOT EXISTS'
										),
								array(
											'key' => '_wwsvsc_exclude_product_parent',
											'value' => 'yes',
											'compare' => '!=',
										),
				);
				//end : variations as single/**/


			    $query['meta_query'] = $meta_query;
			    $query['tax_query']  = $tax_query;

			    $query['paged'] = category_queries_vars('page', $vars);
			    
			    if(!$query['paged']){
			      // $query['is_paged'] = 1;
			    }



                /*
			    $query_vars = array();

			    $kategori = category_queries_vars('kategori', $vars);
	            if(!empty($kategori)){
	              $query_vars[] = array(
	                 "name"  => "Kategori",
	                 "slug"  => "kategori",
	                 "terms" => explode(",", $kategori)
	              );
	            }
	            $marka = category_queries_vars('marka', $vars);
	            if(!empty($marka)){
	              $query_vars[] = array(
	                 "name"  => "Marka",
	                 "slug"  => "marka",
	                 "terms" => explode(",", $marka)
	              );
	            }
			    $fiyat = category_queries_vars('fiyat', $vars);
	            if(!empty($fiyat)){
	              $query_vars[] = array(
	                 "name"  => "Fiyat",
	                 "slug"  => "fiyat",
	                 "terms" => explode(",", $fiyat)
	              );
	            }
	            $fiyat_araligi = category_queries_vars('fiyat_araligi', $vars);
	            if(!empty($fiyat_araligi)){
	              $query_vars[] = array(
	                 "name"  => "Fiyat Aralığı",
	                 "slug"  => "fiyat_araligi",
	                 "terms" => explode(",", $fiyat_araligi)
	              );
	            }
	            $beden = category_queries_vars('beden', $vars);
	            if(!empty($beden)){
	                $query_vars[] = array(
	                   "name"  => "Beden",
	                   "slug"  => "beden",
	                   "terms" => explode(",", $beden)
	                );
	            }
	            $tas = category_queries_vars('tas', $vars);
	            if(!empty($tas)){
	                $query_vars[] = array(
	                   "name"  => "Taş",
	                   "slug"  => "tas",
	                   "terms" => explode(",", $tas)
	                );
	            }
	            $materyal = category_queries_vars('materyal', $vars);
	            if(!empty($materyal)){
	                $query_vars[] = array(
	                   "name"  => "Materyal",
	                   "slug"  => "materyal",
	                   "terms" => explode(",", $materyal)
	                );
	            }
	            $renk = category_queries_vars('renk', $vars);
	            if(!empty($renk)){
	                $query_vars[] = array(
	                   "name"  => "Renk",
	                   "slug"  => "renk",
	                   "terms" => explode(",", $renk)
	                );
	            }
	            $cinsiyet = category_queries_vars('cinsiyet', $vars);
	            if(!empty($cinsiyet)){
	                $query_vars[] = array(
	                   "name"  => "Cinsiyet",
	                   "slug"  => "cinsiyet",
	                   "terms" => explode(",", $cinsiyet)
	                );
	            }
	            $durum = category_queries_vars('durum', $vars);
	            if(!empty($durum)){
	                $query_vars[] = array(
	                   "name"  => "Özel Seçim",
	                   "slug"  => "durum",
	                   "terms" => explode(",", $durum)
	                );
	            }*/
	            
	            return array(
	            	"query" => $query,
	            	"query_vars" => $vars//$query_vars
	            );
}



function woo_filter_category_attributes($category_products){

            $data = array();

            foreach( $category_products as $product ){
                foreach( $product->get_attributes() as $taxonomy => $attribute ){
                    if(taxonomy_exists($taxonomy)){
                        $attribute_name = wc_attribute_label( $taxonomy );
                        $data[$taxonomy]["name"] = get_taxonomy( $taxonomy )->labels->singular_name;
                        foreach ( wp_get_post_terms( $product->get_id(), $taxonomy) as $term ){
                            $data[$taxonomy][$term->term_id] =  array(
                                                                      "slug" => $term->slug,
                                                                      "name" => $term->name
                                                                );
                        }                      
                    }
                }
            }

            // attributes
            $category_attributes = array();
            if($data){    
                foreach ($data as $key => $taxonomy) {
                    $taxonomy_item["name"] = $taxonomy["name"];
                    $taxonomy_item["slug"] = $key;
                    $taxonomy_item["terms"] = array();
                    $query_vars = explode(",", get_query_var(str_replace("pa_", "", $key)));
                    
                    //if url has default taxonomy url
                    if($query_vars[0]==""){
                       $query_vars = explode(",", get_query_var($key));
                    }

                    if($params){
                       $query_vars = explode(",",category_queries_vars(str_replace("pa_", "", $key), $params));
                    }

                    foreach ($taxonomy as $key_term => $term) {
                        if($key_term == "name"){
                          continue;
                        }
                        $taxonomy_item_term = array(
                           "name" => $term["name"],
                           "id"   => $key_term,
                           "slug" => $term["slug"]
                        );
                        $color = get_term_meta( $key_term, "color", true);
                        $color_image = get_field("color_image", $key."_".$key_term, true);
                        if ( $color ) {
                             $taxonomy_item_term["color"] = $color;
                        }
                        if ( $color_image ) {
                             $taxonomy_item_term["color_image"] = $color_image;
                        }

                        $taxonomy_item_term["active"] = in_array( $term["slug"], $query_vars)?"1":"0";
                        array_push ($taxonomy_item["terms"], $taxonomy_item_term);
                    }
                    array_push ($category_attributes, $taxonomy_item);
                }
            }

            return $category_attributes;

}




function woo_sidebar_filters($context, $product_page_type, $free_shipping_min_amount, $query, $params){

    //print_r(category_queries_ajax($query, $vars));

    $vars = $GLOBALS['query_vars'];
    //print_r( $query_vars );

	$siralama = get_query_var('siralama');
    if(!empty($siralama)){
        $context['query_vars_sorting'] = array(
            "name"  => "Sıralama",
            "slug"  => "siralama",
            "term"  => $siralama
        );
    }
	$queried_object = get_queried_object();

	if($product_page_type == "product_cat"){
		if($queried_object){
           $term_id = $queried_object->term_id;
		}else{
			//print_r($vars);

			//$term_slug = category_queries_vars("kategori", $vars);
			 //$term_id = get_term_by('slug', $term_slug, "product_cat")->term_id;

		   $term_index = array_search("kategori", array_column($vars, 'slug'));
           $term_id = get_term_by('slug', $vars[$term_index]["terms"][0], "product_cat")->term_id;

		}

		//print_r($query_vars[$term_index]["terms"][0]);
		//print_r(get_term_by('slug',$query_vars[$term_index]["terms"][0] ));
        
        $thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true ); // Get Category Thumbnail

        $context['category'] = Timber::get_term( $term_id, 'product_cat' );
        $context['image'] = wp_get_attachment_url( $thumbnail_id );
        $context['title'] = single_term_title('', false);
    }else{
       $context['title'] = get_post_type_object( "product" )->labels->name;
    }

    if($query){
       $query_args = $query;
       $query_args['posts_per_page'] = -1;
       $query_args['numberposts'] = -1;
       $query_args['ignore_pre_get_posts'] = 1;
    }else{
	    $query_args = array(
	        'status'               => 'publish',
	        'limit'                => -1,
	        'numberposts'          => -1,
	        'posts_per_page'       => -1,
	        'ignore_pre_get_posts' => 1
	    );    	
    }
    //print_r( $query_args);

    if($product_page_type == "product_cat"){
        $query_args["category"] = array($context['category']->slug);
    }
    if($product_page_type == "search"){
        $query_args["s"] = get_query_var("s");
    }
    
    //get all products
    $category_products = wc_get_products($query_args);
    $product_categories = array();
    $product_brands = array();
    foreach( $category_products as $key=>$product ){
        $cats = wp_get_post_terms( $product->get_id(), "product_cat" );
        foreach($cats as $cat){
	        if(!in_array($cat, $product_categories)){
	        	$product_categories[] = $cat;
	        }        	
        }
        $brands = wp_get_post_terms( $product->get_id(), "product_brand" );
        if (!is_wp_error($brands)) {
	        foreach($brands as $brand){
		        if(!in_array($brand, $product_brands)){
		        	$product_brands[] = $brand;
		        }        	
	        }
	    }
    }


	//if($product_page_type == "shop" || $product_page_type == "search"){
        /*$cat_args = array(
            'orderby'    => "menu_order",
            'order'      => "asc",
            'hide_empty' => true,
        );*/
        $kategori = get_query_var('kategori');
        $product_categories_active = explode(",", $kategori);
        //$product_categories = get_terms( 'product_cat', $cat_args );
        if($product_categories_active){
            foreach($product_categories as $key=>$product_category){
                $product_categories[$key]->active = in_array($product_category->slug, $product_categories_active)?"1":"0";
            }
        }
        $context["product_categories"] = $product_categories;
    //}


    /*$brand_args = array(
            'orderby'    => "menu_order",
            'order'      => "asc",
            'hide_empty' => true,
        );*/
        $marka = get_query_var('marka');
        $product_brands_active = explode(",", $marka);
        if($params){
           $product_brands_active = explode(",",category_queries_vars("marka", $params));
        }
        //$product_brands = get_terms( 'product_brand', $brand_args );
        if($product_brands_active){
            foreach($product_brands as $key=>$product_brand){
                $product_brands[$key]->active = in_array($product_brand->slug, $product_brands_active)?"1":"0";
            }
        }
        $context["product_brands"] = $product_brands;





            $context['category_attributes'] = woo_filter_category_attributes($category_products);


            /// get min max prices on category
            if($product_page_type == "product_cat"){
                global $wpdb;
                $results = $wpdb->get_col( "
                    SELECT pm.meta_value
                    FROM {$wpdb->prefix}term_relationships as tr
                    INNER JOIN {$wpdb->prefix}term_taxonomy as tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    INNER JOIN {$wpdb->prefix}terms as t ON tr.term_taxonomy_id = t.term_id
                    INNER JOIN {$wpdb->prefix}postmeta as pm ON tr.object_id = pm.post_id
                    WHERE tt.taxonomy LIKE 'product_cat'
                    AND t.term_id = $term_id
                    AND pm.meta_key = '_price'
                ");
                sort($results, SORT_NUMERIC);
                $min = current($results);
                $max = end($results);
            }else{
                $price_range = get_filtered_price();
                $min = $price_range["min"];
                $max = $price_range["max"];
            }
            $category_price_range = array(
                                 "min" => $min,//wc_price( $min ), 
                                 "max" => $max//wc_price( $max )
            );
            $context['category_price_range'] = $category_price_range;

            // price set
            if($min != $max){
                $category_price_set = array();
                $price_item_step = $max/5;
                $price_item_start = 0;
                for($price_item=0; $price_item_start<=$max; $price_item++){
                    $category_price_set_item = array(
                        "min" => $price_item_start,
                        "max" => ($price_item_start + $price_item_step)
                    );
                    $query_vars = explode(",", get_query_var("fiyat"));
                    if($params){
                       $query_vars = explode(",",category_queries_vars("fiyat", $params));
                    }
                    $category_price_set_item["active"] = in_array( $price_item_start."|".($price_item_start + $price_item_step), $query_vars)?"1":"0";
                    array_push($category_price_set, $category_price_set_item);
                    $price_item_start += $price_item_step;
                }
                $context['category_price_set'] = $category_price_set;
            }

            //choices 
            $query_vars = explode(",", get_query_var("durum"));
            if($params){
               $query_vars = explode(",",category_queries_vars("durum", $params));
            }
            $category_choices = array(
                array(
                    "name"   => trans('Çok Satanlar'),
                    "slug"   => "cok-satanlar",
                    "active" =>  in_array( "cok-satanlar", $query_vars)?"1":"0"
                ),
                array(
                    "name" => trans('İndirimli Ürünler'),
                    "slug" => "indirimli-urunler",
                    "active" =>  in_array( "indirimli-urunler", $query_vars)?"1":"0"
                ),
                array(
                    "name" => trans('Yeni Ürünler'),
                    "slug" => "yeni-urunler",
                    "active" =>  in_array( "yeni-urunler", $query_vars)?"1":"0"
                ),
                array(
                    "name" => trans('Tükenmek Üzere'),
                    "slug" => "tukenmek-uzere",
                    "active" =>  in_array( "tukenmek-uzere", $query_vars)?"1":"0"
                )
            );
            if($free_shipping_min_amount>0){
                $category_choices[] = array(
                    "name"   => trans('Ücretsiz Kargo'),
                    "slug"   => "ucretsiz-kargo",
                    "active" =>  in_array( "ucretsiz-kargo", $query_vars)?"1":"0"
                );
            }
            $context['category_choices'] = $category_choices;

            return $context;
}



function woo_sidebar_filter_vars($vars=array()){
	        $query_vars = array();

            $kategori = isset($vars["kategori"])?$vars["kategori"]:get_query_var('kategori');
            if(!empty($kategori)){
              $query_vars[] = array(
                 "name"  => "Kategori",
                 "slug"  => "kategori",
                 "terms" => explode(",", $kategori)
              );
            }
            $marka = isset($vars["marka"])?$vars["marka"]:get_query_var('marka');
            if(!empty($marka)){
              $query_vars[] = array(
                 "name"  => "Marka",
                 "slug"  => "marka",
                 "terms" => explode(",", $marka)
              );
            }
            $fiyat = isset($vars["fiyat"])?$vars["fiyat"]:get_query_var('fiyat');
            if(!empty($fiyat)){
              $query_vars[] = array(
                 "name"  => "Fiyat",
                 "slug"  => "fiyat",
                 "terms" => explode(",", $fiyat)
              );
            }
            $fiyat_araligi = isset($vars["fiyat_araligi"])?$vars["fiyat_araligi"]:get_query_var('fiyat_araligi');
            if(!empty($fiyat_araligi)){
              $query_vars[] = array(
                 "name"  => "Fiyat Aralığı",
                 "slug"  => "fiyat_araligi",
                 "terms" => explode(",", $fiyat_araligi)
              );
            }
            $beden = isset($vars["beden"])?$vars["beden"]:get_query_var('beden');
            if(!empty($beden)){
                $query_vars[] = array(
                   "name"  => "Beden",
                   "slug"  => "beden",
                   "terms" => explode(",", $beden)
                );
            }
            $tas = isset($vars["tas"])?$vars["tas"]:get_query_var('tas');
            if(!empty($tas)){
                $query_vars[] = array(
                   "name"  => "Taş",
                   "slug"  => "tas",
                   "terms" => explode(",", $tas)
                );
            }
            $materyal = isset($vars["materyal"])?$vars["materyal"]:get_query_var('materyal');
            if(!empty($materyal)){
                $query_vars[] = array(
                   "name"  => "Materyal",
                   "slug"  => "materyal",
                   "terms" => explode(",", $materyal)
                );
            }
            $renk = isset($vars["renk"])?$vars["renk"]:get_query_var('renk');
            if(!empty($renk)){
                $query_vars[] = array(
                   "name"  => "Renk",
                   "slug"  => "renk",
                   "terms" => explode(",", $renk)
                );
            }
            $cinsiyet = isset($vars["cinsiyet"])?$vars["cinsiyet"]:get_query_var('cinsiyet');
            if(!empty($cinsiyet)){
                $query_vars[] = array(
                   "name"  => "Cinsiyet",
                   "slug"  => "cinsiyet",
                   "terms" => explode(",", $cinsiyet)
                );
            }
            $durum = isset($vars["durum"])?$vars["durum"]:get_query_var('durum');
            if(!empty($durum)){
                $query_vars[] = array(
                   "name"  => "Özel Seçim",
                   "slug"  => "durum",
                   "terms" => explode(",", $durum)
                );
            }
            return $query_vars;
}
/**/

function save_role_based_pricing($post_id, $post, $update){

	$post_type = get_post_type($post_id);
    if ( "yith_price_rule" != $post_type ) return;

	//Role Based Pricing
	$args = array(
		    "post_type" => "yith_price_rule",
		    'meta_query' => array(
		    	                array(
								    'key' => '_ywcrbp_active_rule',
								    'value' => 1,
								    'compare' => '='
							    )
							)
	);
	$role_pricing = get_posts($args);
	$role_pricing_data = array();
	foreach($role_pricing as $prole_price){
		$data = get_post_meta($prole_price->ID);
		//values
		$role         = $data["_ywcrbp_role"][0]; //roles
		$type         = $data["_ywcrbp_type_rule"][0];//global, category, tag
		$categories   = $data["_ywcrbp_category_product"];
		$tags         = $data["_ywcrbp_tag_product"];
		$price_type   = $data["_ywcrbp_type_price"][0]; //discount_perc, discount_val, markup_perc, markup_val
		$percent_val  = $data["_ywcrbp_decimal_value"][0]; 
		$price_val    = $data["_ywcrbp_price_value"][0];
		$priority     = $data["_ywcrbp_priority_rule"][0];
		$sql_appendix = "";
		if(strpos($price_type, "perc")>-1){
		   if(strpos($price_type, "discount")>-1){
              $sql_appendix = "/".(100/$percent_val);
		   }else{
              $sql_appendix = "+(mt2.meta_value/".(100/$percent_val).")";
		   }
		}else{
		   if(strpos($price_type, "discount")>-1){
              $sql_appendix = "-".$price_val;
		   }else{
              $sql_appendix = "+".$price_val;
		   }
		}
		if(!array_key_exists($role, $role_pricing_data)){
			$role_pricing_data[$role] = array();
		}
		$category_list = array();
		if($type == "category"){
		   foreach($categories as $category){
              $category_list[] = array(
              	  "id"   => maybe_unserialize($category),
              	  "slug" => get_category(maybe_unserialize($category))->slug
              );
		   }
		}
		$tag_list = array();
		if($type == "tag"){
		   foreach($tags as $tag){
              $tag_list[] = array(
              	  "id"   => maybe_unserialize($tag),
              	  "slug" => get_tag(maybe_unserialize($category))->name
              );
		   }
		}
		$role_pricing_data[$role][] = array(
			"priority"   => $priority,
			"type"       => $type,
			"categories" => $category_list,
			"tags"       => $tag_list,
			"sql_from"   => "CAST(mt2.meta_value AS SIGNED)",
			"sql_to"     => "CAST(mt2.meta_value".$sql_appendix." AS SIGNED)"
		);
	}
	if ( get_option( "role_pricing" ) !== false ) {
         update_option("role_pricing", $role_pricing_data );
    } else {
	     add_option("role_pricing", $role_pricing_data);
    }
}
//add_action( 'save_post', 'save_role_based_pricing', 10, 3 );


/**/

// exclude uncategorized category from results
add_filter( 'woocommerce_product_categories_widget_args', 'custom_woocommerce_product_subcategories_args' );
add_filter( 'woocommerce_product_subcategories_args', 'custom_woocommerce_product_subcategories_args' );
function custom_woocommerce_product_subcategories_args( $args ) {
	if ( false !== get_option( 'default_product_cat' ) ) {
	  	$args['exclude'] = get_option( 'default_product_cat' );
	}
	return $args;
}


add_action( 'woocommerce_product_query', 'ts_custom_pre_get_posts_query' );
function ts_custom_pre_get_posts_query( $q ) {
	$tax_query = (array) $q->get( 'tax_query' );
	$tax_query[] = array(
		'taxonomy' => 'product_cat',
		'field' => 'term_id',
		'terms' => get_option( 'default_product_cat' ), // Don't display products in the clothing category on the shop page.
		'operator' => 'NOT IN'
	);
	$q->set( 'tax_query', $tax_query );
}