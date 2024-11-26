<?php
echo json_encode(
                get_countries(
                    $vars["continent"],
                    $vars["selected"],
                    isset($vars["all"])?$vars["all"]:false
                )
            );
            die();