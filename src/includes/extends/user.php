<?php
class User extends Timber\User {
    public $logged;
    public $role;
    public $role_name;
    public $title;
    public $avatar;
    public $avatar_url;
    public $online;
    public $last_login;
    public $last_logout;
    public $last_seen;

    public function logged($logged=0){
        $this->logged = $logged;
    }


    public function is_online(){ // opt
        //if($this->online){
            //return $this->online;
        //}else{
            $this->online = $GLOBALS["salt"]->user_is_online($this->ID);
            return $this->online;
        //}
    }

    /*public function __get( $key ){

    }

    public function __set( $key, $value ){
        if(isset($GLOBALS["salt"]->user) && !is_admin()){
            $GLOBALS["salt"]->update_user();
        }
    }*/
    /*public function get_person_id(){
        return get_user_meta($this->id, '_person_id', true);
    }*/
    /*public function get_last_login($type="text", $format=""){ // opt
        if($this->last_login){
            return $this->last_login;
        }else{
            $last_login = get_user_meta( $this->ID, 'last_login', true );
            if($last_login){
                switch($type){
                    case "text":
                        $last_login = human_time_diff($last_login);
                        break;
                    case "date":
                        $last_login = $this->get_local_date(date('Y-m-d H:i:s', $last_login), $this->get_timezone(), $GLOBALS["user"]->get_timezone(), $format) ;
                        break;
                }            
            }
            $this->last_login = $last_login; 
            return $last_login;       
        }
    }
    public function get_last_logout($type="text", $format=""){ // opt
        if($this->last_logout){
            return $this->last_logout;
        }else{
            $last_logout = get_user_meta( $this->ID, 'last_logout', true );
            if($last_logout){
                switch($type){
                    case "text":
                        $last_logout = human_time_diff($last_logout);
                        break;
                    case "date":
                        $last_logout = $this->get_local_date(date('Y-m-d H:i:s', $last_logout), $this->get_timezone(), $GLOBALS["user"]->get_timezone(), $format) ;
                        break;
                }            
            }
            $this->last_logout = $last_logout;   
            return $last_logout; 
        }
    }
    public function get_last_seen($type="date", $format="Y-m-d H:i"){ // opt
        if($this->last_seen){
           return $this->last_seen;
        }else{
            $last_seen = $this->get_last_logout($type, $format);
            if(empty($last_seen)){
                $last_seen = $this->get_last_login($type, $format);
            }
            $this->last_seen = $last_seen;
            return $last_seen;            
        }
    }*/
    public function get_last_login($type="text", $format="Y-m-d H:i:s"){
        $last_login = get_user_meta( $this->ID, 'last_login', true );//get_the_author_meta('last_login');
        if(!empty($last_login)){
            switch($type){
                case "text":
                    $last_login = human_time_diff($last_login);
                    break;
                case "date":
                    if($this->get_timezone() && $GLOBALS["user"]->get_timezone()){
                        $last_login = $this->get_local_date(date('Y-m-d H:i:s', $last_login), $this->get_timezone(), $GLOBALS["user"]->get_timezone(), $format);
                    }else{
                        $last_login = date($format, $last_login);
                    }
                    break;
            }            
        }
        return $last_login; 
    }
    public function get_last_logout($type="text", $format="Y-m-d H:i:s"){
        $last_logout = get_user_meta( $this->ID, 'last_logout', true );//get_the_author_meta('last_login');
        if($last_logout){
            switch($type){
                case "text":
                    $last_logout = human_time_diff($last_logout);
                    break;
                case "date":
                    if($this->get_timezone() && $GLOBALS["user"]->get_timezone()){
                        $last_logout = $this->get_local_date(date('Y-m-d H:i:s', $last_logout), $this->get_timezone(), $GLOBALS["user"]->get_timezone(), $format) ;
                    }else{
                        $last_login = date($format, $last_logout);
                    }
                    break;
            }            
        }
        return $last_logout; 
    }
    public function get_last_seen($type="text", $format=""){
        $last_seen = $this->get_last_logout($type, $format);
        if(empty($last_seen)){
            $last_seen = $this->get_last_login($type, $format);
        }
        return $last_seen;
    }
    public function get_status(){
        $user_status = true;
        if(ENABLE_MEMBERSHIP_ACTIVATION){
            $user_status = intval(get_user_meta( $this->ID, 'user_status', true ));
        }
        return is_user_logged_in() && ($user_status || $this->get_role()=="administrator")?true:false;
    }
    /*public function is_waiting_upgrade(){
        $status = false;
        if($this->get_role() == "expert"){
            $profile_upgrade = get_field('profile_upgrade', "user_".$this->ID);
            if($profile_upgrade == "requested"){
               $status = true;
            }
        }
        return $status;
    }*/
    public function is_profile_completed(){
        if($this->get_role() == "administrator"){
            return true;
        }else{
            $status = get_user_meta($this->ID, 'profile_completed', true);//get_field('profile_completed', "user_".$this->ID);
            return boolval($status);
        }
    }

    public function get_social_login_providers(){
        global $wpdb;
        $query = "select type from {$wpdb->prefix}social_users where ID = $this->ID";
        return $wpdb->get_col($query);
    }

    public function generate_url_slug(){
        $nickname = $this->nickname;
        if(strlen($nickname) < 8){
            $nickname .= "-" . unique_code(8 - strlen($nickname));
        }
        if(strlen($nickname) > 25){
            $nickname = substr($nickname, 0, 25);
        }
        return $nickname;
    }
    
    public function get_role(){ //opt
        if($this->role){
            return $this->role;
        }else{
            if(isset($this->roles)){
               $this->role = array_keys($this->roles)[0]; 
               return $this->role;
            }else{
               return "";
            }
        }
    }
    public function get_role_name(){ // opt
        if($this->role_name){
            return $this->role_name;
        }else{
            $this->role_name = $this->roles[$this->get_role()];
            return $this->role_name;
        }
    }
    public function get_title() { // opt
        if($this->title){
             return $this->title;
        }else{
            if(empty($this->meta("first_name")) && empty($this->meta("last_name"))){
               $title = $this->user_email;
            }else{
               $title = $this->meta("first_name") ." ". $this->meta("last_name"); 
            }
            if(empty($title)){
               $title = "<span class='name'>".$this->display_name."</span> <small class='username d-block'>".$this->user_login."</small>";
            }
            $this->title = $title;
            return $this->title;  
        }
    }
    public function get_avatar($width = 120, $class="") { // opt
        if($this->avatar){
            return $this->avatar;
        }else{
            $profile_image = get_field('profile_image', "user_".$this->ID);
            if($profile_image){
                $avatar_url = wp_get_attachment_image_src($profile_image, "thumbnail")[0];
                $avatar = "<img data-src='".$avatar_url."' class='avatar img-fluid lazy .".$class."' alt='".$this->display_name."'/>";
            }else{
                $avatar = get_avatar( $this->id, $width, 'mystery', $this->display_name, array( 'class' => $class ));
            }
            $this->avatar = $avatar;
            return $avatar;    
        }
    }
    public function get_avatar_url($width = 120) { // opt
        if($this->avatar_url){
            return $this->avatar_url;
        }else{
            $profile_image = get_field('profile_image', "user_".$this->ID);
            if($profile_image){
                $avatar_url = wp_get_attachment_image_src($profile_image, "thumbnail")[0];
            }else{
                $avatar_url = get_avatar_url( $this->id, array('size' => $width, 'scheme' => 'mystery') );
            }
            $this->avatar_url = $avatar_url;
            return $avatar_url;   
        }
    }
    public function get_social_media(){
        $social_media = array();
        foreach($GLOBALS['wp_social_media'] as $item){
            $data = get_field($item["slug"], "user_".$this->id);
            if(!empty($data)){
                $social_media[$item["slug"]] = $data;
            }
        }
        return $social_media;
    }
    public function get_terms($taxonomy = "") {
        return get_terms_for_user($this->id, $taxonomy);
    }


    public function get_languages($native = false) {
        $return = array();
        $languages = get_field("languages", "user_".$this->id);
        if($languages){
            $all_languages = get_all_languages($native);
            foreach($all_languages as $lang){
                if(in_array($lang["lang"], $languages)){
                   $return[$lang["lang"]] = $lang["name"];
                }
            }
            return $return;            
        }
    }
    public function get_languages_level($native = false) {
        $return = array();
        $languages = $this->get_languages($native);
        if($languages){
            $languages_levels = $this->language;
            foreach($languages_levels as $lang){
                $level_term = get_term_by("id", $lang["level"], "proficiency-level");
                $return[] = array(
                    "language" => $lang["language"],
                    "name"     => $languages[$lang["language"]],
                    "title" => $level_term->name,
                    "level" => get_field("level", "proficiency-level_".$lang["level"])
                );
            }
            $level = array_column($return, 'level');
            array_multisort($level, SORT_DESC, $return); 
        }
        return $return;
    }



    public function get_address() {
        $location = array();
        if($this->city){
           $location[] = get_city_name("id", $this->city);
        }
        if($this->billing_country){
           $location[] = get_country_name("code", $this->billing_country);
        }
        $location = implode(", ", $location);

        $address = "";
        if($this->billing_address_1){
           $address = $this->billing_address_1;    
        }
        if($location){
            if(!empty($address)){
               $location = $address ."<br>".$location;
            }
        }
        return $location;
    }
    public function get_location() {
        $location = array();
        if(isset($this->city)){
           if(!empty($this->city)){
                $location[] = get_city_name("id", $this->city);
           }
        }
        if(isset($this->billing_country)){
           $location[] = get_country_name("code", $this->billing_country);
        }
        $location = implode(", ", $location);
        return $location;//mb_convert_encoding($location, "UTF-8", "auto"); 
    }
    public function get_location_data(){
        $vars = array(
            "id" => $this->city
        );
        $localization = new Localization();
        return $localization->states($vars)[0];
    }
    public function get_map_data($popup=false){
        $location_data = $this->get_location_data();
        $data = array(
            "title"        => $this->get_title(),
            "avatar"       => $this->get_avatar_url(60),
            "country"      => get_country_name("code", $this->billing_country),
            "country_code" => $this->billing_country,
            "city"         => get_city_name("id", $this->city),
            "city_code"    => $this->city,
            "lat"          => $location_data["latitude"],
            "lng"          => $location_data["longitude"],
        );
        if($popup){
           $data["popup"] = esc_html($this->get_map_popup());
        }
        return $data;
    }
    public function get_map_popup(){
        $map_data = $this->get_map_data();
        return  "<div class='row gx-3 gy-2'>" .
                    "<div class='col-auto'>" .
                         "<img src='" . $map_data["avatar"] . "' class='img-fluid rounded' style='max-width:50px;'/>" .
                    "</div>" .
                    "<div class='col'>" .
                        "<ul class='list-unstyled m-0'>" .
                            "<li class='fw-bold'>" . $map_data["title"] . "</li>" .
                            "<li class='text-muted' style='font-size:12px;'>" . $this->get_location() . "</li>" .
                        "</ul>" .
                    "</div>" .
                    "<div class='col-12 text-primary' style='font-size:12px;'>" .
                        $this->get_local_date("","",$GLOBALS["user"]->get_timezone()) . " GMT" . $this->get_gmt() . "</span>" .
                    "</div>" .
                "</div>";
    }
    public function get_phone(){
        return $this->billing_phone_code.$this->billing_phone;
    }



    //reviews
    //comment_type : review
    //user_id : writer
    //meta.comment_profile : review yapılan id
    //meta.rating : rating

    public function get_rating(){
        return get_star_votes_profile($this->ID, 1);
    }

    /*$args = "SELECT SQL_CALC_FOUND_ROWS
                    count(*)
                FROM
                    ".$wpdb->prefix."comments AS wpc
                INNER JOIN ".$wpdb->prefix."commentmeta AS wpcm
                        ON
                            wpcm.comment_id = wpc.comment_id AND wpc.comment_type = 'review' and wpcm.meta_key = 'comment_profile' AND wpcm.meta_value = $this->ID
                INNER JOIN ".$wpdb->prefix."commentmeta AS wpcm2
                        ON
                            wpcm2.comment_id = wpc.comment_id AND wpcm2.meta_key = 'rating'
                WHERE 
                    wpc.comment_approved = $approved";*/

    public function get_reviews_count($approved=1){
        global $wpdb;
        $args = "SELECT SQL_CALC_FOUND_ROWS
                    count(*)
                FROM
                    ".$wpdb->prefix."comments AS wpc
                INNER JOIN ".$wpdb->prefix."commentmeta AS wpcm
                        ON
                            wpcm.comment_id = wpc.comment_id AND wpc.comment_type = 'review' and wpcm.meta_key = 'comment_profile' AND wpcm.meta_value = $this->ID
                WHERE 
                    wpc.comment_approved = $approved";
        return $wpdb->get_var($args);
    }
    public function set_review_approve($id=0){
        $salt = new Salt();
        return $salt->sessions(["action"=>"review_approve", "id" => $id]);
    }
    public function get_application_review($post_id=0){
        $query = array( 
            //'status' => $vars["status"], 
            'type' => 'review',
            'meta_key' => 'comment_profile', 
            'meta_value' => $this->ID,
            'post_id' => $post_id,
            'no_found_rows' => false 
        );
        $paginate = new Paginate($query);
        $result = $paginate->get_results("comment");
        if($result["posts"]){
            return $result["posts"][0];
        }else{
            return array();
        }
    }



    //following
    public function is_following($id=0){
        $salt = new Salt();
        $id = $id==0?$this->ID:$id;
        return $salt->is_following($id, "user");
    }
    public function is_follows($id=0){
        $salt = new Salt();
        $id = $id==0?$this->ID:$id;
        return $salt->is_follows($id, "user");
    }
    public function get_followers($id=0){
        $salt = new Salt();
        $id = $id==0?$this->ID:$id;
        return $salt->get_followers($id, "user");
    }
    public function get_followers_count($id=0){
        $salt = new Salt();
        $id = $id==0?$this->ID:$id;
        return $salt->get_followers_count($id, "user");
    }




    public function cart(){
        if(ENABLE_ECOMMERCE){
            if(function_exists("woo_get_cart_object")){
                return woo_get_cart_object();
            }
        }
    }


    public function get_post_count($post_type = "post", $public_only = true){
        $post_count = count_user_posts($this->ID, $post_type, $public_only);
        return intval($post_count);
    }


}