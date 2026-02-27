<?php
$salt = \Salt::get_instance();//new Salt();
            $data = array(
               "user_id" => $salt->user->ID,
               "redirect_url" => get_account_endpoint_url("profile")
            );
            $_SESSION["groupMeetData"] = $data;
            $page = get_page_by_path( 'api' );
            $response["redirect"] = get_permalink( $page->ID )."google/token/";
            echo json_encode($response);
            die;