<?php
$required_setting = ENABLE_IP2COUNTRY;
$path = getSiteSubfolder();
            qtranxf_setLanguage($vars["language"]);
            //print_r($vars);
            //$_SESSION['user_country'] = $vars["country"];
            setcookie("user_country", "", time() - 3600);
            setcookie("user_country_code", "", time() - 3600);
            setcookie("user_language", "", time() - 3600);
            //setcookie('user_country', $vars["country"]);
            //setcookie('user_country_code', $vars["countryCode"]);
            setcookie('user_country', $vars["country"], time() + (86400 * 365), $path); 
            setcookie('user_country_code', $vars["countryCode"], time() + (86400 * 365), $path);
            setcookie('user_language', $vars["language"], time() + (86400 * 365), $path); 
            setcookie('user_region', json_encode(get_region_by_country_code($vars["countryCode"])), time() + (86400 * 365), $path); 
            
            //print_r($_SESSION['user_country']);
            $url = qtrans_convert_url($vars["language"]);
            $response["redirect"] = $url;
            echo json_encode($response);
            die();