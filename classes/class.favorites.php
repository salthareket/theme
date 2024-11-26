<?php

class Favorites{

	public $favorites;
    public $user_id;
    public $type;

    function __construct($user_id=0) {
        $this->user_id = $user_id;
        $this->get();
        //$this->$type = $type;
    }

    function setCookie(){
        $favorites = json_encode($this->favorites, JSON_NUMERIC_CHECK);
        setcookie( 'wpcf_favorites', $favorites, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN );

    }
    function unsetCookie(){
        unset( $_COOKIE['wpcf_favorites'] );
        setcookie( 'wpcf_favorites', '', time() - ( 15 * 60 ) );
    }

    function remove($id=0){
        $tmp = array_values(array_diff($this->favorites, array($id)));
        /*print_r($this->favorites);
        print_r(array($id));
        print_r($tmp);*/
        $this->favorites = $tmp;//json_encode($tmp, JSON_NUMERIC_CHECK);
        //print_r($this->favorites);
        $this->update();
        $value = get_post_meta( $id, 'wpcf_favorites_count', true );
        $value = empty($value)||$value==null?0:$value;
        if($this->type == "user"){
            update_user_meta($id, 'wpcf_favorites_count', $value - 1 ); 
        }else{
            update_post_meta($id, 'wpcf_favorites_count', $value - 1 ); 
        }
        if($this->user_id){
            $this->unsetCookie();
            //unset( $_COOKIE['wpcf_favorites_count'] );
            //setcookie( 'wpcf_favorites_count', '', time() - ( 15 * 60 ) );
        }
    }

    function check(){
        $args = array(
            "fields" => "ids",
            "include" => $this->favorites
        );
        $result = new WP_User_Query($args);
        $total = $result->total_users;
        $this->favorites = $result->get_results();
        $this->update();
    }

    function exist($id=0){
        return in_array($id, $this->favorites);
    }

    function add($id=0){
        //decode array yapar
        //encode string yapar
        $id=intval($id);
        if(!$this->exist($id)){
            $favorites = $this->favorites;
            array_push($favorites, $id);
            $this->favorites = $favorites;
            $this->update();
            if($this->type == "user"){
               $value = get_user_meta( $id, 'wpcf_favorites_count', true );
               $value = empty($value)||$value==null?0:$value;
               update_user_meta($id, 'wpcf_favorites_count', $value + 1 ); 
            }else{
               $value = get_post_meta( $id, 'wpcf_favorites_count', true );
               $value = empty($value)||$value==null?0:$value;
               update_post_meta($id, 'wpcf_favorites_count', $value + 1 ); 
            }
        }
    }

    function update(){
        /*if(is_array($this->favorites)){
           $favorites = $this->favorites;
           $this->favorites = json_encode($this->favorites, JSON_NUMERIC_CHECK);
        }else{
           $favorites = json_decode($this->favorites, true);
        }*/
        if($this->user_id){
            delete_user_meta($this->user_id, '_favorites');
            if($this->favorites){
                foreach($this->favorites as $favorite){
                    add_user_meta($this->user_id, '_favorites',  $favorite);
                }                
            }
            update_user_meta($this->user_id, 'wpcf_favorites', json_encode($this->favorites, JSON_NUMERIC_CHECK));
            //setcookie( 'wpcf_favorites', $this->favorites, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN );
        //}else{
            //setcookie( 'wpcf_favorites', $this->favorites, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN );
        }
        $this->setCookie();
    }

    function calculate($favorites=array()){
        //$ids = unicode_decode(json_encode($ids, JSON_NUMERIC_CHECK));
        //$favorites = json_decode($ids, true);
        $this->favorites = $favorites;
        if($this->user_id){
            delete_user_meta($this->user_id, '_favorites');
            foreach($this->favorites as $favorite){
                add_user_meta($this->user_id, '_favorites',  $favorite);
            }
            update_user_meta($this->user_id, 'wpcf_favorites', json_encode($this->favorites, JSON_NUMERIC_CHECK));
        }else{
            //setcookie( 'wpcf_favorites', $ids, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN );
            $this->setCookie();
        }
    }

    function get(){
	  	if(is_user_logged_in() || $this->user_id > 0){
	  	    if($this->user_id > 0){
	           $user = get_user_by( "id", $this->user_id );
	  	    }else{
	  	   	   $user = wp_get_current_user();
               $this->user_id = $user->ID;
	  	    }

            global $wpdb;
            //$count = $wpdb->get_var("select count(*) from wp_usermeta where user_id=".$this->user_id." and meta_key='_favorites'");
            $favorites = $wpdb->get_results("select meta_value from wp_usermeta where user_id=".$this->user_id." and meta_key='_favorites'");
            if($favorites){
                $favorites = wp_list_pluck($favorites, "meta_value");
            }
            
	  	    /*if(strlen($user->wpcf_favorites)>2){
			   $favorites = $user->wpcf_favorites;
			}else{
               $favorites = json_decode("[]");
            }*/

            if(!$favorites && isset($_COOKIE['wpcf_favorites'])){
                $favorites = urldecode($_COOKIE['wpcf_favorites']);
                $favorites = json_decode($favorites, true);
                $this->update();
            }
            $this->unsetCookie();
            //unset( $_COOKIE['wpcf_favorites'] );
            //setcookie( 'wpcf_favorites', '', time() - ( 15 * 60 ) );
	  	}else{
            if(!isset($_COOKIE['wpcf_favorites'])) {
                setcookie( 'wpcf_favorites', "[]", time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN );
            } else {
                $favorites = urldecode($_COOKIE['wpcf_favorites']);
                $favorites = json_decode($favorites, true);
            }
        }
        if(empty($favorites) || $favorites == null || $favorites == "null"){
            $favorites = array();//'[]';
        }
        $this->favorites = $favorites;//json_decode($favorites, true);
    }

    function merge(){
        if(isset($_COOKIE['wpcf_favorites'])){
           $favorites = urldecode($_COOKIE['wpcf_favorites']);
           $favorites = json_decode($favorites, true );
           $favorites = array_unique(array_merge($favorites, $this->favorites));
           $this->calculate($favorites);
           //$this->update();
           //print_r($this->favorites);
           //unset( $_COOKIE['wpcf_favorites'] );
           //setcookie( 'wpcf_favorites', '', time() - ( 15 * 60 ) );
           $this->unsetCookie();
        }
    }

    function count(){
        if(is_string($this->favorites)){
            $this->favorites = json_decode($this->favorites, true);
        }
        return count($this->favorites);
    }

    function get_posts(){
        $posts = array();
        if($this->type == "user"){
            if($this->favorites){
                $posts = get_users(array(
                    "include" => $this->favorites
                ));
            }
        }
        return $posts;
    }
}

function favorites_from_cookie() {
        $favorites_obj = new Favorites();
        $favorites->check();
        $favorites_obj->merge();
        /*foreach($GLOBALS['favorites'] as $favorite){
            $favorites_obj->add($favorite);
        }*/
}
add_action('wp_login', 'favorites_from_cookie');