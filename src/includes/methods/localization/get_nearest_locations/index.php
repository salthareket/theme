<?php
//$geolocation = new Geolocation_Query($vars);

            $locations = GeoLocation_Query(
                $vars["lat"],
                $vars["lng"],
                $vars["post_type"],
                $vars["distance"],
                $vars["limit"],
                "ThemePost"
            );
            if(in_array("posts", $vars["output"])){
                $context = Timber::context();
                $context["posts"] = $locations;
                $response["html"] = Timber::compile($vars["template"].".twig", $context);
            }
            if(in_array("markers", $vars["output"])){
                $response["data"] = $GLOBALS["salt"]->get_markers($locations);
            }
            echo json_encode($response);
            die();      
            