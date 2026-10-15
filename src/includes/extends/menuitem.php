<?php

class MenuItem extends Timber\MenuItem{

	/**
	 * Request-level cache for get_properties() results.
	 * Key: md5(item_id + serialized args)
	 */
	private static $_props_cache = [];

	/**
	 * Request-level cache for link_type meta (avoids ACF __get per item).
	 */
	private ?string $_link_type_cached = null;

	/**
	 * link_type'i ACF'den bir kez okur, sonra cache'den döner.
	 * Fake populate item'lar (ID >= 1000000) için boş string döner.
	 */
	public function get_link_type(): string {
		if ( $this->_link_type_cached !== null ) {
			return $this->_link_type_cached;
		}
		// Fake populate item — ACF meta yok
		if ( (int) $this->ID >= 1000000 ) {
			return $this->_link_type_cached = '';
		}
		$val = get_post_meta( $this->ID, 'link_type', true );
		return $this->_link_type_cached = ( $val ?: '' );
	}

	public function get_properties($args=array()){

		// ── Request-level cache ───────────────────────────────────────────────
		$cache_key = $this->ID . '_' . md5( serialize( $args ) );
		if ( isset( self::$_props_cache[ $cache_key ] ) ) {
			return self::$_props_cache[ $cache_key ];
		}

		// ── Fake populate item — meta sorgusu yok, minimal properties ─────────
		// ID >= 1000000 → bric_menu_build_tree tarafından oluşturulan dinamik item
		// Bu item'ların ACF meta'sı yok. meta() çağrısı → ACF → WooOrder decode → get_post() zinciri
		// get_properties içindeki switch/meta çağrılarını tamamen atla.
		if ( (int) $this->ID >= 1000000 ) {
			$active = in_array( $this->object_id, $args['nodes'] ?? [] )
			       || in_array( $this->ID,        $args['nodes'] ?? [] );

			$item_classes = [ 'nav-item', $active ? 'active' : '' ];
			$link_classes = [];
			$has_children = ! empty( $this->children );
			$at_max_level = ( $args['max_level'] ?? 9999 ) > 0
			             && ( $args['menu_level'] ?? 1 ) >= ( $args['max_level'] ?? 9999 );

			if ( $has_children && ! $at_max_level ) {
				$item_classes[] = 'dropdown ' . ( $args['menu_level'] ?? 0 ) . ' ' . ( $args['max_level'] ?? 0 );
				$link_classes[] = 'dropdown-toggle nav-link';
				$href           = '#';
				$bs_toggle      = 'dropdown';
			} else {
				$link_classes[] = 'nav-link';
				$link_classes[] = $this->parent ? 'dropdown-item' : '';
				$href           = $this->url ?? $this->link ?? '#';
				$bs_toggle      = '';
			}

			$link_attrs = $bs_toggle ? 'data-bs-toggle="' . $bs_toggle . '"' : '';

			$props = [
				'link' => [
					'class'  => trim( implode( ' ', $link_classes ) ),
					'href'   => $href,
					'target' => '',
					'attrs'  => $link_attrs,
				],
				'item' => [
					'class' => trim( implode( ' ', $item_classes ) ),
					'attrs' => '',
				],
				'megamenu' => false,
			];

			return self::$_props_cache[ $cache_key ] = $props;
		}

		if(!isset($args["parent_link"])){
			$args["parent_link"] = false;
		}
		if(!isset($args["collapsible"])){
			$args["collapsible"] = false;
		}
		if(!isset($args["collapsed"])){
			$args["collapsed"] = false;
		}
		if(!isset($args["nodes"])){
			$args["nodes"] = array();
		}
		if(!isset($args["menu_level"])){
			$args["menu_level"] = 0;
		}
		if(!isset($args["max_level"])){
			$args["max_level"] = 0;
		}
		if(!isset($args["open"])){
			$args["open"] = false;
		}

		if($args["collapsed"]){
			$args["collapsible"] = false;
		}
		if($args["collapsible"]){
			$args["collapsed"] = false;
		}
       
		// Active state hesaplama
		global $wp, $post;

		// 1. CANLI URL KONTROLÜ (Slug silinse de, cache olsa da en garantisi budur)
		$current_url = home_url(add_query_arg([], $wp->request));
		$item_url = $this->get_link();
		$is_truly_active = (untrailingslashit($item_url) === untrailingslashit($current_url));

		// 2. TAXONOMY / TERM KONTROLÜ (Kategori, Etiket, Özel Tax)
		$is_tax_active = false;
		if (is_category() || is_tag() || is_tax()) {
		    $queried_obj = get_queried_object();
		    if (isset($queried_obj->term_id) && $queried_obj->term_id == $this->object_id) {
		        $is_tax_active = true;
		    }
		}

		// 3. ARCHIVE KONTROLÜ (CPT Arşivi veya Tarih Arşivleri)
		$is_archive_active = false;
		if (is_archive()) {
		    if (is_post_type_archive()) {
		        $post_type = get_query_var('post_type');
		        if (is_array($post_type)) $post_type = reset($post_type);
		        // Menü elemanı bir CPT arşivi mi?
		        if ($this->type === 'post_type_archive' && $this->object === $post_type) {
		            $is_archive_active = true;
		        }
		    }
		}

		// 4. BİRLEŞTİRİLMİŞ ACTIVE KARARI
		$active = $is_truly_active || 
		          $is_tax_active || 
		          $is_archive_active || 
		          (isset($post->ID) && ($post->ID == $this->object_id || $post->ID == $this->ID)) || 
		          $this->current_item_parent || 
		          $this->current_item_ancestor ||
		          in_array($this->object_id, $args["nodes"]) || 
		          in_array($this->ID, $args["nodes"]);


		
		$properties = array(
			"link" => array(
                 "class" => "",
                 "href"  => "#",
                 "target" => "",
                 "attrs" => ""
			),
			"item" => array(
				"class" => "",
				"attrs" => "" 
			),
			"megamenu" => false
		);
		$linkClass = array();
		$linkAttrs = array();
		$itemClass = array();
		$itemAttrs = array();

		$link_type = $this->get_link_type();
        
        $itemClass[] = "nav-item";
        $itemClass[] = $active ?'active':'';
		if($link_type != "default" || $link_type == ""){
			$linkClass[] = "nav-link";
		}
		if($args["collapsed"]){
			$itemClass[] = "col-lg col-12";
		}

		switch($link_type){
			case "modal" :
			    $modal_type = $this->meta("modal_type");
				$linkAttrs["data-ajax-method"] = $modal_type; 
				if($this->meta("fullscreen")){
					$linkAttrs["data-fullscreen"] = "true";
					$linkAttrs["data-close"] = "true";
				}
				if($this->meta("size")){
					$linkAttrs["data-size"] = $this->meta("size");
				}
				if($this->meta("class")){
					$linkAttrs["data-class"] = $this->meta("class");
				}
				if($this->link){
					$linkAttrs["data-url"] = $this->link;
				}
				if($modal_type == "form_modal"){
					$form =  $this->meta("form");
				    $linkAttrs["data-id"] = $form;
				    $forms = QueryCache::get_field("forms", "options");
				    $title = $this->title;
				    if($forms){
				   	   $index = array_search2d_by_field($form, $forms, "form");
				   	   if($index>-1){
				   	   	   $title = $forms[$index]["title"];
				   	   }
				    }
				    $linkAttrs["data-title"] = $title;
				}
				if($modal_type == "template_modal"){
				   $linkAttrs["data-template"] = $this->meta("template");
				   $linkAttrs["data-title"] = $this->title;
				}
				if($modal_type == "page_modal"){
				   $linkAttrs["data-id"] = $this->object_id;
				   $linkAttrs["data-title"] = $this->title;
				}
			break;

			case "download" :
			    $attachment_id = attachment_url_to_postid($this->meta("file"));
				$properties["link"]["href"] = get_home_url()."/downloads/".$attachment_id."/";
			break;

			case "megamenu" :
			    $properties["megamenu"] = true;
				$linkClass[] = "has-mega-menu";
			break;

			default :
        		$linkClass[] = is_current_url($this->get_link(), true) || str_contains($this->get_link(), "#") || $this->_menu_item_target == "_blank" ? "" : "btn-loading-page";
        		//$linkClass[] = ($this->_menu_item_target != "_blank" ? "btn-loading-page" : "");
        		$linkClass[] = ($this->parent ? "dropdown-item" : "nav-link");

        		$linkAttr["itemprop"] = "url";
                $properties["link"]["href"] =  ($this->children?$this->children[0]->link:$this->link);

		        if($this->children and (($args["menu_level"] < $args["max_level"] && $args["max_level"] > 0) || $args["max_level"] == 0) ){
		        	if($args["collapsible"]){
		        		$properties["link"]["href"] = "#";
                		$linkAttrs["data-bs-toggle"] = "collapse";
                		$linkAttrs["data-bs-target"] = "#item-".$this->ID;
		        	}else{
		        		if($args["collapsed"]){
					    	if($args["parent_link"]){
					    		$properties["link"]["href"] = make_onepage_url($this, $this->get_link());
					    	}
		        		}else{
				        	$itemClass[] = "dropdown ".$args["menu_level"]." ".$args["max_level"];
				        	$linkClass[] = "dropdown-toggle";
				        	$linkAttrs["data-bs-toggle"] = "dropdown";
					        if($args["parent_link"]){
					        	$properties["link"]["href"] = make_onepage_url($this, $this->get_link());
					            $linkTarget = $this->_menu_item_target && (!str_contains($this->get_link(), "#") ? $this->_menu_item_target : $properties["link"]["target"]);
					        }else{
					        	$properties["link"]["href"] = "#";
					        }		        			
		        		}
		        	}
		        }else{
		            $properties["link"]["href"] = $this->url;//$this->children?$this->children[0]->url:$this->url;
		            $properties["link"]["target"] = $this->_menu_item_target;//!empty($this->_menu_item_target) && (!str_contains($this->get_link(), "#") ? $this->_menu_item_target : $properties["link"]["target"]);
		        }
			break;
		}

        $properties["link"]["class"] = implode(" ", $linkClass);
        $properties["link"]["attrs"] = array2Attrs($linkAttrs);

        $properties["item"]["class"] = implode(" ", $itemClass);
        $properties["item"]["attrs"] = array2Attrs($itemAttrs);

		// Cache'e yaz — aynı item için tekrar çağrılırsa DB'ye gitme
		self::$_props_cache[ $cache_key ] = $properties;

		return $properties;
	}
}