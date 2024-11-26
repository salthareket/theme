<?php

class Menu extends Timber\Menu{
	public function get_location(){
	    $locations = get_nav_menu_locations();
	    foreach ($locations as $location => $location_menu_id) {
	        if ($location_menu_id == $this->ID) {
	            return $location;
	        }
	    }
	    return false;
    }
}