<?php

class ThemePost extends Post{

}

class ThemeProduct extends Timber\Post{
	protected $product = null;

    public function get_title(){
        return qtranxf_use($GLOBALS["language"], $this->get_title(), false, false );
    }

	public function product( $post = null ) {
		if(!$this->product){
			$product = wc_get_product( $this->ID );
			$this->product = $product;			
		}
		return $this->product;
	}
	public function setup( $loop_index = 0 ) {
		global $wp_query;
		$wp_query->in_the_loop = true;
		$wp_query->setup_postdata( $this->ID );
        return $this;
    }
    public function teardown() {
		global $wp_query;
		$wp_query->in_the_loop = false;
		return $this;
	}

	public function get_product_type(){
		return WC_Product_Factory::get_product_type($this->id);
	}
	public function get_variation_url(){
		return variation_url_rewrite($this->link);
	}

	public function category() {
		$categories = $this->product->get_category_ids();
		if ( $categories ) {
			$category = reset( $categories );
			$category = Timber::get_term( $category );
			return $category;
		}
		return false;
	}

	/**
	 * Get a WooCommerce product attribute by slug.
	 *
	 * @api
	 *
	 * @param string $slug          The name of the attribute to get.
	 * @param bool   $convert_terms Whether to convert terms to Timber\Term objects.
	 *
	 * @return array|false
	 */
	public function get_product_attribute( $slug, $convert_terms = true ) {
		$attributes = $this->product->get_attributes();

		if ( ! $attributes || empty( $attributes ) ) {
			return false;
		}
		$attribute = false;

		foreach ( $attributes as $key => $value ) {
			if ( "pa_{$slug}" === $key ) {
				$attribute = $attributes[ $key ];
				break;
			}
		}

		if ( ! $attribute ) {
			return false;
		}

		if ( $attribute->is_taxonomy() ) {
			$terms = wc_get_product_terms(
				$this->product->get_id(),
				$attribute->get_name(),
				array(
					'fields' => 'all',
				)
			);

			if ( $convert_terms ) {
				$terms = array_map( function( $term ) {
					return Timber::get_term( $term );
				}, $terms );
			}

			return $terms;
		}

		return $attribute->get_options();
	}


	public function get_author(){
		$author_id = $this->book_author;
		if($author_id){
			return Timber::get_post($author_id);			
		}
		return false;
	}

    public function is_in_grouped(){
	    $grouped_products = wc_get_products(array(
	        'type'     => 'grouped',
	        'limit'    => -1,
	    ));
	    $grouped_product_ids = array();
	    foreach ($grouped_products as $grouped_product) {
	        $children_ids = $grouped_product->get_children();
	        if (in_array($this->ID, $children_ids)) {
	            $grouped_product_ids[] = $grouped_product->get_id();
	        }
	    }
        return $grouped_product_ids;
    }
    
}