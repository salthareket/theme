<?php

class MenuItem extends Timber\MenuItem{
	public function get_properties($args=array()){

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
       
		global $post;
		$active = $this->current || $this->current_item_parent || $this->current_item_ancestor || in_array($this->object_id, $args["nodes"]) || ( isset($post->ID) && $post->ID == $this->object_id) || (isset($post->ID) && $post->ID == $this->ID);
		
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

		$link_type = $this->link_type;
        
        $itemClass[] = "nav-item";
        $itemClass[] = $active ?'active':'';
		if($link_type != "default" || $link_type == ""){
			$linkClass[] = "nav-link";
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
				    $forms = SaltBase::get_cached_option("forms");//get_field("forms", "options");
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

		        if($this->children){
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
				        	$itemClass[] = "dropdown";
				        	$linkClass[] = "dropdown-toggle";
					        if($args["parent_link"]){
					        	$properties["link"]["href"] = make_onepage_url($this, $this->get_link);
					            $linkTarget = $this->_menu_item_target && (!str_contains($this->get_link(), "#") ? $this->_menu_item_target : $properties["link"]["target"]);
					        }else{
					        	$properties["link"]["href"] = "#";
					        }		        			
		        		}
		        	}
		        }else{
		            $properties["link"]["href"] = $this->children?$this->children[0]->url:$this->url;//$this->url;
		            $properties["link"]["target"] = $this->_menu_item_target;//!empty($this->_menu_item_target) && (!str_contains($this->get_link(), "#") ? $this->_menu_item_target : $properties["link"]["target"]);
		        }
			break;
		}

        $properties["link"]["class"] = implode(" ", $linkClass);
        $properties["link"]["attrs"] = array2Attrs($linkAttrs);

        $properties["item"]["class"] = implode(" ", $itemClass);
        $properties["item"]["attrs"] = array2Attrs($itemAttrs);
		
		return $properties;
	}
}