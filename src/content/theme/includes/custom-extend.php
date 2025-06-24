<?php
class Salt extends SaltBase {

    private static $already_ran = false;

    public function update_profile($vars=array(), $callback=""){
        $response = $this->response();
        $user_id = $this->user->ID;

        $action = isset($vars["action"])?$vars["action"]:"";

        $action_admin = array("upgrade_approved", "upgrade_declined");
        if(in_array($action, $action_admin) && $this->user->get_role() != "administrator"){
          return $response;
        }

        $profile_completed = get_field("profile_completed", "user_".$user_id);
        switch($action){

            case "upgrade" :
            case "profile" :
                $nickname = vars_fix($vars, "nickname");
                $first_name = vars_fix($vars,"first_name");
                $last_name = vars_fix($vars,"last_name");
                $description = stripcslashes(vars_fix($vars,"description"));

                $address = sanitize_text_field(stripcslashes(vars_fix($vars,"address")));
                $postcode = vars_fix($vars,"postcode");
                $country = vars_fix($vars,"country");
                $country_old = $this->user->billing_country;
                $city = vars_fix($vars,"city");
                $city_old = $this->user->billing_city;

                $phone_code = vars_fix($vars,"phone_code");
                $phone = isset($vars["phone"])?$vars["phone"]:"";
                $email = isset($vars["email"])?$vars["email"]:"";

                $email_old = $this->user->user_email;
                $email_update = "";
                if(!empty($email_old)){
                    if(trim($email) != trim($email_old)){
                       update_user_meta($user_id, '_email_temp', $email);
                       $email_update = $email;
                       $email = $email_old;
                    }
                }

                $profile_video = vars_fix($vars,"profile_video");
                $about = stripcslashes(vars_fix($vars,"about"));
                $user_url = vars_fix($vars,"user_url");
                $social_media = vars_fix($vars,"social_media");

                $user_data = array(
                    'ID' => $user_id,
                    //'display_name' => $display_name,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'user_url'     => $user_url,
                    'user_email'   => $email,
                    'description'  => $description
                );

                if(!empty($nickname) && $nickname != $this->user->nickname){
                    $nickname_exist = $this->nickname_exist(array(
                       "nickname" => $nickname
                    ));
                    if($nickname_exist){
                       $nickname = sanitize_title($display_name);
                    }
                    update_user_meta($user_id, 'user_login', $nickname);
                    update_user_meta($user_id, 'nickname', $nickname);
                    $user_data['user_nicename'] = $nickname;
                    $response["refresh"] = true;
                }

                update_field( 'profile_video', $profile_video, "user_".$user_id );

                wp_update_user( $user_data );

                if($social_media){
                    foreach($social_media as $item){
                        update_user_meta( $user_id, $item["brand"], $item["url"] );
                    }
                }
                
                if(!empty($first_name)){
                    update_user_meta( $user_id, 'billing_first_name', $first_name );
                }
                if(!empty($last_name)){
                    update_user_meta( $user_id, 'billing_last_name', $last_name );
                }
                if(!empty($about)){
                    update_user_meta( $user_id, 'about', $about );
                }
                if(!empty($email)){
                    update_user_meta( $user_id, 'user_email', $email );
                }
                if(!empty($address)){
                    update_user_meta( $user_id, 'billing_address_1', $address );
                }
                if(!empty($postcode)){
                    update_user_meta( $user_id, 'billing_postcode', $postcode );
                }
                if(!empty($country)){

                    if(in_array($this->user->get_role(), $GLOBALS["membership_roles"])){
                        //$country_old = get_user_meta($user_id, "billing_country", true);
                        if($country_old){
                            $count = intval(get_option("country_".$country_old."_user_count"));
                            $count = $count<=0?0:($count-1);
                            update_option("country_".$country_old."_user_count", $count); 
                        }
                        $count = intval(get_option("country_".$country."_user_count"));
                        $count = $count + 1;
                        update_option("country_".$country."_user_count", $count);
                    }
                    update_user_meta( $user_id, 'billing_country', $country );

                    // getting timezone
                    $gmtOffset = "";
                    $gmt = "";
                    $timezone_data = array();
                    $country_data = $this->localization->countries([
                        "iso2" => $country
                    ]);
                    $timezones = json_decode($country_data[0]["timezones"], true);
                    if(count($timezones) == 1){
                        $timezone_data = array(
                            "gmtOffset"     => $timezones[0]["gmtOffset"],
                            "gmt" => str_replace("UTC", "", $timezones[0]["gmtOffsetName"]),
                            "timezone"      => $timezones[0]["zoneName"],
                        );
                    }else{
                        if(!empty($city)){
                            $city_data = $this->localization->states([
                                "id" => $city
                            ]);
                            if($city_data){
                               //$localization = new Localization();
                               $timezone_data = $this->localization->get_timezone($country_data[0]["latitude"], $country_data[0]["longitude"], $country, $city_data[0]["name"]);
                            }                        
                        }
                    }
                    if($timezone_data){
                        $timezone = array(
                            "timezone" => $timezone_data["timezone"],
                            "date_format" => "",
                            "time_format" => ""
                        );
                        $gmtOffset = $timezone_data["gmtOffset"];
                        $gmt = $timezone_data["gmt"];
                    }
                }
                if(!empty($city)){

                    if(in_array($this->user->get_role(), $GLOBALS["membership_roles"])){
                        $city_old_id = get_user_meta($user_id, "city", true);
                        if($city_old_id){
                            $count = intval(get_option("state_".$city_old_id."_user_count"));
                            $count = $count<=0?0:($count-1);
                            update_option("state_".$city_old_id."_user_count", $count); 
                        }
                        $count = intval(get_option("state_".$city."_user_count"));
                        $count = $count + 1;
                        update_option("state_".$city."_user_count", $count);
                    }

                    update_user_meta( $user_id, 'city', $city );
                    $woo_data = $this->localization->get_state_woo_data($city);
                    update_user_meta( $user_id, 'billing_city', $woo_data[0]["name"] );
                    update_user_meta( $user_id, 'billing_state', $woo_data[0]["woo"] );
                    if(ENABLE_ECOMMERCE){
                        $customer = new WC_Customer( $user_id );
                        $customer->set_billing_state( $woo_data[0]["woo"] );
                        $customer->save();                        
                    }
                }
                if(!empty($phone_code)){
                    if (strpos($phone_code, "+") !== 0) {
                        $phone_code = "+" . $phone_code;
                    }
                    update_user_meta( $user_id, 'billing_phone_code', $phone_code );
                }
                if(!empty($phone)){
                    update_user_meta( $user_id, 'billing_phone', $phone );
                }
                if(!empty($email)){
                    update_user_meta( $user_id, 'billing_email', $email );
                }
                if(!empty($timezone)){
                    update_user_meta( $user_id, '_wp_utz_opts', $timezone );
                }

                update_user_meta( $user_id, 'gmtOffset', $gmtOffset );
                update_user_meta( $user_id, 'gmt', $gmt );

                $language = isset($vars["language"])?$vars["language"]:"";
                if(!empty($language)){
                    $languages_list = array();
                    update_user_meta( $user_id, "language", $language );
                    foreach($language as $lang){
                        $languages_list[] = $lang["language"];
                        update_user_meta( $user_id, "language_".$lang["language"]."_level", $lang["level"] );
                    }
                    update_field('languages', $languages_list, "user_".$user_id);
                }
                
                if($action == "profile"){
                   $message = "Your profile has been updated.";
                   if($email_update){
                       $message .= "<br>Please check your ".$email_update." account's inbox to verify.";
                       $response["refresh"] = true; 
                       $this->send_email_activation($user_id);
                   }
                   $response["message"] = $message;
                   $profile_completion = $this->profile_completion();
                   $response["data"] = array(
                      "profile_completion" => $profile_completion
                   );
                }
            break;

            case "security" :
                $password = isset($vars["password"])?$vars["password"]:"";
                $password_new = isset($vars["password_1"])?$vars["password_1"]:"";
                $user = get_user_by( 'id', $this->user->ID);
                if(get_user_meta($user->ID, "password_set", true)){
                    if ( $user && wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
                        update_user_meta( $user_id, 'password_set', true );
                        wp_set_password( $password_new, $user->ID );
                        wp_clear_auth_cookie();
                        wp_set_current_user($user->ID);
                        wp_set_auth_cookie($user->ID);
                        $response["refresh_confirm"] = true;
                        $response["message"] = "Your password updated!.";
                    } else {
                       $response["error"] = true;
                       $response["message"] = "Please check your password.";
                    }
                }else{
                    update_user_meta( $user_id, 'password_set', true );
                    wp_set_password( $password_new, $user->ID );
                    wp_clear_auth_cookie();
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                    $response["refresh_confirm"] = true;
                    $response["message"] = "Your password created!.";
                }
            break;

            case "renew_password" :
                $code = $vars["activation-password"];
                $decrypt = new Encrypt();
                $data = $decrypt->decrypt($code);
                if($data){
                    if(isset($data["email"])){
                        $email = $data["email"];
                        $user = get_user_by( 'email', $email );
                        $user_id = $user->ID;
                        $password = isset($vars["password"])?$vars["password"]:"";
                        $password_new = isset($vars["password_1"])?$vars["password_1"]:"";
                        if ( $user ) {
                             wp_set_password( $password, $user_id );
                             $response["message"] = "Your password updated!.";
                             $response["redirect"] = $GLOBALS["base_urls"]["account"];
                        } else {
                           $response["error"] = true;
                           $response["message"] = "The user you are trying to update the password for could not be found.";
                        }
                    }else{
                        $response["error"] = true;
                        $response["message"] = "Your password renew link is invalid.";
                    }
                }else{
                    $response["error"] = true;
                    $response["message"] = "Your password renew link is invalid.";
                }
            break;

            case "save_sms_requirements" :

                $first_name = vars_fix($vars, "first_name");
                $last_name = vars_fix($vars, "last_name");

                $country = vars_fix($vars, "country");
                $city = vars_fix($vars, "city");
                $phone_code = vars_fix($vars, "phone_code");
                $phone = vars_fix($vars, "phone");

                        if(!empty($country)){
                            // getting timezone
                            $gmtOffset = "";
                            $gmt = "";
                            $timezone_data = array();
                            $country_data = $this->localization->countries([
                                "iso2" => $country
                            ]);
                            $timezones = json_decode($country_data[0]["timezones"], true);
                            if(count($timezones) == 1){
                                $timezone_data = array(
                                    "gmtOffset"     => $timezones[0]["gmtOffset"],
                                    "gmt" => str_replace("UTC", "", $timezones[0]["gmtOffsetName"]),
                                    "timezone"      => $timezones[0]["zoneName"],
                                );
                            }else{
                                if(!empty($city)){
                                    $city_data = $this->localization->states([
                                        "id" => $city
                                    ]);
                                    if($city_data){
                                       //$localization = new Localization();
                                       $timezone_data = $this->localization->get_timezone($country_data[0]["latitude"], $country_data[0]["longitude"], $country, $city_data[0]["name"]);
                                    }                        
                                }
                            }
                            if($timezone_data){
                                $timezone = array(
                                    "timezone" => $timezone_data["timezone"],
                                    "date_format" => "",
                                    "time_format" => ""
                                );
                                $gmtOffset = $timezone_data["gmtOffset"];
                                $gmt = $timezone_data["gmt"];
                            }
                        }

                            $user_data = array(
                                'ID' => $user_id,
                            );
                            if(!empty($first_name)){
                                $user_data["first_name"] = $first_name;
                                update_user_meta( $user_id, 'billing_first_name', $first_name );
                            }
                            if(!empty($last_name)){
                                $user_data["last_name"] = $last_name;
                                update_user_meta( $user_id, 'billing_last_name', $last_name );
                            }
                            wp_update_user($user_data);

                            if(!empty($country)){
                                update_user_meta( $user_id, 'billing_country', $country );
                            }
                            if(!empty($city)){
                                update_user_meta( $user_id, 'city', $city );
                                $woo_data = $this->localization->get_state_woo_data($city);
                                $billing_city = $woo_data[0]["name"];
                                $billing_state = $woo_data[0]["woo"];
                                update_user_meta( $user_id, 'billing_city', $billing_city );
                                update_user_meta( $user_id, 'billing_state', $billing_state );
                                if(ENABLE_ECOMMERCE){
                                    $customer = new WC_Customer( $user_id );
                                    $customer->set_billing_state( $billing_state );
                                    $customer->save();                                    
                                }
                            }
                            if(!empty($phone_code)){
                                if (strpos($phone_code, "+") !== 0) {
                                    $phone_code = "+" . $phone_code;
                                }
                                update_user_meta( $user_id, 'billing_phone_code', $phone_code );
                            }
                            if(!empty($phone)){
                                update_user_meta( $user_id, 'billing_phone', $phone );
                            }
                            if(!empty($timezone)){
                                update_user_meta( $user_id, '_wp_utz_opts', $timezone );
                            }
                            if(!empty($gmtOffset)){
                                update_user_meta( $user_id, 'gmtOffset', $gmtOffset );
                            }
                            if(!empty($gmt)){
                                update_user_meta( $user_id, 'gmt', $gmt );
                            }

                            $this->user = new User($user_id);
                            
                            $response = $this->send_activation($this->user->ID);

                            if(!$response["error"] && isset($vars["refresh"])){
                                if($vars["refresh"]){
                                    $response["refresh"] = true;
                                }
                            }
            break;

            case "set_role" :
                $response = $this->response();

                $role = $vars["role"];

                if(is_user_logged_in() && isset($role)){
                    if(in_array($this->user->get_role(), $GLOBALS["membership_roles"])){
                        $user = get_userdata($user_id);
                        update_user_meta( $user_id, 'billing_first_name', $user->first_name );
                        update_user_meta( $user_id, 'billing_last_name', $user->last_name );
                        update_user_meta( $user_id, 'billing_email', $user->user_email );
                        wp_update_user( array( 'ID' => $user_id, 'role' => $role ) ); 

                        $this->user = new User($user_id);
                        
                        if((ENABLE_MEMBERSHIP_ACTIVATION && MEMBERSHIP_ACTIVATION_TYPE == "sms") || (ENABLE_SMS_NOTIFICATIONS && !ENABLE_MEMBERSHIP_ACTIVATION) || (ENABLE_SMS_NOTIFICATIONS && MEMBERSHIP_ACTIVATION_TYPE == "email")){
                                $vars["action"] = "save_sms_requirements";
                                $response = $this->update_profile($vars);

                                if(!$response["error"] && !ENABLE_MEMBERSHIP_ACTIVATION){
                                    $this->notification(
                                        $role."/new-account",
                                        array(
                                            "user" => $this->user,
                                            "recipient" => $this->user->ID,
                                        )
                                    );
                                }

                        }else{
                            if((ENABLE_MEMBERSHIP_ACTIVATION && MEMBERSHIP_ACTIVATION_TYPE == "email")){
                                $response = $this->send_activation($this->user->ID);
                            }else{
                                $this->notification(
                                    $role."/new-account",
                                    array(
                                       "user" => $this->user,
                                       "recipient" => $this->user->ID,
                                    )
                                );                                
                            }
                        }
                        $response["refresh"] = true;     
                    }else{
                        $response["error"] = true;
                        $response["message"] = "Role (".$role.") is not supported!";
                    }
                }else{
                    $response["error"] = true;
                    $response["message"] = "Please login to set your role.";
                }
            break;
        }
        if(!in_array($action, ["upgrade_management"])){
            $this->user = new User();
            $profile_completed_after = $this->profile_completion();
            if($profile_completed != $profile_completed_after["success"]){
                if($action == "upgrade"){
                   $response["redirect"] = get_account_endpoint_url( 'profile' );
                }else{
                   $response["refresh"] = true;
                }
            }
            return $response;           
        }
    }

    public function on_post_pre_update($data){
        parent::on_post_pre_update($data);
        return $data; 
    }

    public function on_post_published($post_id, $post, $update){
        
        /*remove_action('save_post', [ $this, 'on_post_published'], 100);
        remove_action('save_post_product', [ $this, 'on_post_published'], 100);
        remove_action('publish_post', [ $this, 'on_post_published'], 100);*/
        /*if (is_callable([parent::class, 'on_post_published'])) {
            remove_action('save_post', [ parent::class, 'on_post_published'], 100);
            remove_action('save_post_product', [ parent::class, 'on_post_published'], 100);
            remove_action('publish_post', [ parent::class, 'on_post_published'], 100);
        }*/
        
        /*if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( get_post_status( $post_id ) !== 'publish' ) {
            return;
        }
        $post_types = get_post_types(['public' => true], 'names');
        if (in_array($post->post_type, $post_types)) {
            if (self::$already_ran) {
                return; // Eğer zaten çalıştıysa, çık
            }

            self::$already_ran = true; // Flag'i ayarla*/

            parent::on_post_published($post_id, $post, $update);

           /* self::$already_ran = false; // Flag'i ayarla
        }*/
        /*add_action('save_post', [ $this, 'on_post_published'], 100, 3);
        add_action('save_post_product', [ $this, 'on_post_published'], 100, 3);
        add_action('publish_post', [ $this, 'on_post_published'], 100, 3);
        if (is_callable([parent::class, 'on_post_published'])) {
            add_action('save_post', [ parent::class, 'on_post_published'], 100, 3);
            add_action('save_post_product', [ parent::class, 'on_post_published'], 100, 3);
            add_action('publish_post', [ parent::class, 'on_post_published'], 100, 3);
        }*/
       
    }

    public function on_term_published($term_id, $tt_id, $taxonomy){
        parent::on_term_published($term_id, $tt_id, $taxonomy);
    }

    public function on_post_delete( $post_id ){
        parent::on_post_delete( $post_id );
    }

    public function on_user_delete($user_id){
        parent::on_user_delete( $user_id );
    }
}