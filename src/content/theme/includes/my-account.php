<?php

/*
Predefined Endpoints :

messages
notifications 
favorites
not-activated
renew-password
security
reviews
customer-logout

account_links stored in globals.php

*/


/*
You may use :
unset($my_account_links[$endpoint]) 
to remove predefined endpoint.

You may update :
$my_account_links[$endpoint] 
to update and replace new one.
*/

/* 
Overwrite Predefined Functions

use "my_account_custom_content_{$endpoint}" for function names.
$endpoint must be snake_case format.

*/

function my_account_custom_content_profile() {
        login_required();
        
        $user = Data::get("user");

        $templates = array("my-account/profile.twig");

        $context = Timber::context();
        $type = "profile";

        $context['type'] = $type; 
        $context['title'] = trans("Your ".$user->get_role_name()." Profile");
        $context['description'] = trans("Make sure you completed your profile fully. We will watch you with the right client requests according to your profile info.");


        // Work Status
        $work_status_user = wp_list_pluck($user->get_terms('work-status') , "term_id");
        $context['work_status_user'] = $work_status_user;
        $work_status = Timber::get_terms("work-status");
        $context['work_status'] = $work_status;
        
        if($user->get_role == "client"){
            $context['languages'] = get_all_languages();
            if($user->language){
                $context['language'] = $user->language;
            }else{
                $context['language'] = [];
            }
            $context['proficiency_level'] = Timber::get_terms("proficiency-level");      
        }


        // $user->login_location  included in functions.php  login_location data generated in Localization Class
        if($user->login_location && $context['user']->billing_country == ""){
            $context['user']->billing_country = $user->login_location["country_code"];
            //$context['user']->city = $user->login_location["city"];
            global $wpdb; 
            $query = "SELECT id FROM states WHERE name LIKE '".$user->login_location["city"]."'";
            $city_data = $wpdb->get_var($query);//$wpdb->get_var($query);
            $context['user']->city = $city_data;     
        }
        Timber::render($templates , $context);
    }



