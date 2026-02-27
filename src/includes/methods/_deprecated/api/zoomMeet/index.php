<?php
$salt = \Salt::get_instance();//new Salt();
            $data = array(
               "user_id" => $salt->user->ID,
               "redirect_url" => get_account_endpoint_url("profile")
            );
            $_SESSION["saran-groupMeetData"] = $data;
            //$page = get_page_by_path( 'api' );
            $response["redirect_blank"] = true;
            $response["redirect"] = ZOOM_KEYS["oauth_url"];//get_permalink( $page->ID )."zoom/token/";
            echo json_encode($response);
            die;